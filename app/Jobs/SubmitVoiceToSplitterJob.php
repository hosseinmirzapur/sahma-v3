<?php

namespace App\Jobs;

use App\Models\EntityGroup;
use App\Services\AiService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitVoiceToSplitterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 7200;
    public int $retryAfter = 7300;

    private EntityGroup $entityGroup;
    public DatabaseManager $db;
    public AiService $aiService;

    /**
     * @param EntityGroup $entityGroup
     */
    public function __construct(EntityGroup $entityGroup)
    {
        $this->entityGroup = $entityGroup;
        $this->onQueue('voice::submit-to-splitter');
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->db = app(DatabaseManager::class);
        $this->aiService = app(AiService::class);
        try {
            if ($this->entityGroup->status !== EntityGroup::STATUS_WAITING_FOR_SPLIT) {
                Log::warning("STT Skipped: EntityGroup #{$this->entityGroup->id} is not ready for window extraction.");
                return;
            }

            Log::info("STT => Processing EntityGroup #{$this->entityGroup->id} for voice splitting.");

            $meta = $this->entityGroup->meta ?? [];
            if (!isset($meta['windows'])) {
                $content = $this->entityGroup->getFileData(true);

                if (empty($content)) {
                    throw new Exception(
                        "STT Error: Failed to retrieve voice data for EntityGroup #{$this->entityGroup->id}."
                    );
                }

                Log::info(
                    "STT => Submitting EntityGroup #{$this->entityGroup->id} to AI Service for window extraction."
                );
                $meta['windows'] = $this->aiService->getVoiceWindows($content);

                $this->db->transaction(function () use ($meta) {
                    $this->entityGroup->update(['meta' => $meta]);
                });

                CreateSplitVoiceToEntitiesJob::dispatch($this->entityGroup);
            } else {
                Log::info("STT => EntityGroup #{$this->entityGroup->id} already has extracted windows.");
            }
        } catch (Exception | GuzzleException $e) {
            $this->fail();
            throw $e; // Preserve the original exception
        }
    }

    /**
     * @return void
     */
    public function fail(): void
    {
        $this->entityGroup::query()->increment('number_of_try');

        $this->entityGroup->status = $this->entityGroup->number_of_try >= 3
            ? EntityGroup::STATUS_REJECTED
            : EntityGroup::STATUS_WAITING_FOR_RETRY;

        $this->entityGroup->save();

        $entityGroupId = $this->entityGroup->id;
        $numberOfTries = $this->entityGroup->number_of_try;
        Log::error(
            "Job failed for EntityGroup #$entityGroupId. Retry attempt: $numberOfTries/3"
        );
    }
}
