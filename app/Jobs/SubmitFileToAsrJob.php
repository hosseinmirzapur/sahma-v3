<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\EntityGroup;
use App\Models\User;
use App\Services\AiService;
use App\Services\OfficeService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitFileToAsrJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 7200;
    public int $retryAfter = 7300;

    private EntityGroup $entityGroup;
    private User $user;
    private Filesystem $voiceStorage;
    private Filesystem $csvStorage;
    private DatabaseManager $db;
    private AiService $aiService;
    private OfficeService $officeService;

    /**
     * @param EntityGroup $entityGroup
     * @param User $user
     */
    public function __construct(EntityGroup $entityGroup, User $user)
    {
        $this->entityGroup = $entityGroup;
        $this->user = $user;
        $this->onQueue('file::submit-to-ASR');

        $this->voiceStorage = app('filesystem')->disk('voice');
        $this->csvStorage = app('filesystem')->disk('csv');
        $this->db = app(DatabaseManager::class);
        $this->aiService = app(AiService::class);
        $this->officeService = app(OfficeService::class);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            if ($this->entityGroup->status !== EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION) {
                Log::warning(
                    "Skipping ASR: entityGroup #{$this->entityGroup->id} is not in the correct status."
                );
                return;
            }

            Log::info("Starting ASR for entityGroup #{$this->entityGroup->id}");

            $textASR = '';
            $entities = $this->entityGroup->entities()->orderBy('id')->get();

            foreach ($entities as $entity) {
                $filePath = $this->voiceStorage->path($entity->file_location);
                $ASRResult = $this->aiService->submitToASR($filePath);

                $csvFileLocation = $this->officeService->generateCsvFileEntity(strval($ASRResult['tsv']));
                $meta = $entity->meta ?? [];
                $meta['csv_location'] = $csvFileLocation;

                $entity->update([
                    'transcription_result' => strval($ASRResult['text']),
                    'meta' => $meta,
                ]);

                $textASR .= ' ' . $ASRResult['text'];
            }

            Log::info("ASR finished for entityGroup #{$this->entityGroup->id}");

            $generateWindowsEntityGroup = $this->officeService->generateWindowsEntityGroup($this->entityGroup);
            $wordFileLocation = $this->officeService->generateWordFile($this->entityGroup, $textASR);
            $pdfFileLocation = $this->officeService->convertWordFileToPdf($wordFileLocation);

            $this->db
                ->transaction(
                    function () use (
                        $textASR,
                        $generateWindowsEntityGroup,
                        $wordFileLocation,
                        $pdfFileLocation
                    ) {
                        $entityGroup = EntityGroup::query()->lockForUpdate()->findOrFail($this->entityGroup->id);

                        $entityGroup->update([
                            'transcription_result' => $textASR,
                            'transcription_at' => now(),
                            'result_location' => array_merge($entityGroup->result_location ?? [], [
                                'voice_windows' => $generateWindowsEntityGroup,
                                'word_location' => $wordFileLocation,
                                'converted_word_to_pdf' => $pdfFileLocation,
                            ]),
                            'status' => EntityGroup::STATUS_TRANSCRIBED,
                        ]);

                        Activity::query()->create([
                            'user_id' => $this->user->id,
                            'status' => Activity::TYPE_TRANSCRIPTION,
                            'activity_id' => $entityGroup->id,
                            'activity_type' => EntityGroup::class,
                        ]);
                    }, 3);

            // Cleanup storage
            $splitLocation = dirname($this->entityGroup->file_location) . '/' . $this->entityGroup->id;
            $this->voiceStorage->deleteDirectory($splitLocation);

            foreach ($entities as $entity) {
                $this->csvStorage->delete($entity->meta['csv_location'] ?? '');
            }

            Log::info("Deleted storage files for entityGroup #{$this->entityGroup->id}");
        } catch (Exception|GuzzleException $e) {
            $this->fail();
            throw $e; // Preserve stack trace
        }
    }

    /**
     * @return void
     */
    public function fail(): void
    {
        $this->entityGroup::query()->increment('number_of_try');

        if ($this->entityGroup->number_of_try >= 3) {
            $this->entityGroup->status = EntityGroup::STATUS_REJECTED;
        } else {
            $this->entityGroup->status = EntityGroup::STATUS_WAITING_FOR_RETRY;
        }

        $this->entityGroup->save();

        $entityGroupId = $this->entityGroup->id;
        $numberOfTries = $this->entityGroup->number_of_try;
        Log::error("Job failed for entityGroup #$entityGroupId. Retrying ($numberOfTries/3)");
    }
}
