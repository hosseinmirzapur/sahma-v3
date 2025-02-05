<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\EntityGroup;
use App\Models\User;
use App\Services\AiService;
use App\Services\FileService;
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

class SubmitFileToOcrJob implements ShouldQueue
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
    private Filesystem $pdfStorage;
    private Filesystem $imageStorage;
    private Filesystem $fileStorage;
    private DatabaseManager $db;
    private AiService $aiService;
    private FileService $fileService;
    private OfficeService $officeService;

    /**
     * @param EntityGroup $entityGroup
     * @param User $user
     */
    public function __construct(EntityGroup $entityGroup, User $user)
    {
        $this->entityGroup = $entityGroup;
        $this->user = $user;
        $this->onQueue('file::submit-to-ocr');

        $this->pdfStorage = app('filesystem')->disk('pdf');
        $this->imageStorage = app('filesystem')->disk('image');
        $this->fileStorage = app('filesystem')->disk($entityGroup->type);
        $this->db = app(DatabaseManager::class);
        $this->aiService = app(AiService::class);
        $this->fileService = app(FileService::class);
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
                    "OCR Skipped: entityGroup #{$this->entityGroup->id} is not in the correct state."
                );
                return;
            }

            Log::info("Starting OCR for entityGroup #{$this->entityGroup->id}");

            // Determine file type and location
            if (!empty($this->entityGroup->result_location['converted_word_to_pdf'])) {
                $disk = $this->pdfStorage;
                $fileLocation = $this->entityGroup->result_location['converted_word_to_pdf'];
            } elseif (!empty($this->entityGroup->meta['tif_converted_png_location'])) {
                $disk = $this->imageStorage;
                $fileLocation = $this->entityGroup->meta['tif_converted_png_location'];
            } else {
                $disk = $this->fileStorage;
                $fileLocation = $this->entityGroup->file_location;
            }

            $filePath = $disk->path($fileLocation);
            $isImage = $this->entityGroup->type === 'image';

            // Submit file to OCR service
            $ocrResult = $this->aiService->submitToOcr($filePath, $isImage);
            Log::info("OCR Completed for entityGroup #{$this->entityGroup->id}");

            $downloadedFile = $this->aiService->downloadSearchableFile(strval($ocrResult['pdf_link']));
            $OCRLocation = dirname(
                $this->entityGroup->file_location
            ) . '/Transcripted-' . pathinfo(
                $this->entityGroup->file_location,
                PATHINFO_FILENAME
            ) . '.pdf';

            if (!$this->pdfStorage->put($OCRLocation, $downloadedFile)) {
                throw new Exception('Failed to write OCR PDF to storage.');
            }

            // Add watermark to the OCR PDF
            $fileWaterMarkedPath = $this->fileService->addWaterMarkToPdf($this->entityGroup, $OCRLocation);

            // Generate Word file from OCR text
            $textOcr = trim(strval($ocrResult['text']));
            $wordFileLocation = $this->officeService->generateWordFile($this->entityGroup, $textOcr);

            // Database update within a transaction
            $this->db->transaction(function () use ($OCRLocation, $textOcr, $wordFileLocation, $fileWaterMarkedPath) {
                $entityGroup = EntityGroup::query()->lockForUpdate()->findOrFail($this->entityGroup->id);

                $entityGroup->update([
                    'transcription_result' => $textOcr,
                    'transcription_at' => now(),
                    'result_location' => array_merge($entityGroup->result_location ?? [], [
                        'pdf_location' => $OCRLocation,
                        'pdf_watermark_location' => $fileWaterMarkedPath,
                        'word_location' => $wordFileLocation,
                        'text_eng' => $wordFileLocation,
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
        } catch (Exception | GuzzleException $e) {
            $this->fail();
            throw $e; // Preserve the stack trace
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
