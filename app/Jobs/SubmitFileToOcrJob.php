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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitFileToOcrJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public int $retryAfter = 7300;

    private EntityGroup $entityGroup;
    private User $user;

  /**
   * Create a new job instance.
   */
    public function __construct(EntityGroup $entityGroup, User $user)
    {
        $this->entityGroup = $entityGroup;
        $this->user = $user;
        $this->onQueue('file::submit-to-ocr');
    }

  /**
   * Execute the job.
   * @throws Exception
   * @throws GuzzleException
   */
    public function handle(AiService $aiService, OfficeService $officeService): void
    {
        try {
            if ($this->entityGroup->status != EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION) {
                Log::info("OCR => entityGroup:#{$this->entityGroup->id} is not in OCR state");
                return;
            }
            Log::info("OCR => Submit entityGroup:#{$this->entityGroup->id} to ocr: calling ocr");
            if (isset($this->entityGroup->result_location['converted_word_to_pdf'])) {
                $disk = 'pdf';
                $fileLocation = $this->entityGroup->result_location['converted_word_to_pdf'] ?? '';
            } elseif (isset($this->entityGroup->meta['tif_converted_png_location'])) {
                $disk = 'image';
                $fileLocation = $this->entityGroup->meta['tif_converted_png_location'] ?? '';
            } else {
                $disk = $this->entityGroup->type;
                $fileLocation = $this->entityGroup->file_location;
            }
          // Submit pages to OCR
            $filePath = Storage::disk($disk)->path($fileLocation);

            if ($this->entityGroup->type == 'image') {
                $isImage = true;
            } else {
                $isImage = false;
            }

            $ocrResult = $aiService->submitToOcr($filePath, $isImage);

            Log::info("OCR => OCR has been finished for entityGroup:#{$this->entityGroup->id}");

            $linkSearchablePdf = strval($ocrResult['pdf_link']);
            $downloadedFile = $aiService->downloadSearchableFile($linkSearchablePdf);
            $location = strval(pathinfo($this->entityGroup->file_location, PATHINFO_DIRNAME));
            $OCRLocation = $location . '/Transcripted-' . strval(
                pathinfo($this->entityGroup->file_location, PATHINFO_FILENAME)
            ) . '.pdf';
            if (Storage::disk('pdf')->put($OCRLocation, $downloadedFile) === false) {
                  throw new Exception('Failed to write data in to storage.');
            }

            $fileWaterMarkedPath = FileService::addWaterMarkToPdf($this->entityGroup, $OCRLocation);

            $textOcr = strval($ocrResult['text']);
            $wordFileLocation = $officeService->generateWordFile($this->entityGroup, $textOcr);

            DB::transaction(function () use (
                $OCRLocation,
                $textOcr,
                $wordFileLocation,
                $fileWaterMarkedPath
            ) {
              /** @var EntityGroup $entityGroup */
                $entityGroup = EntityGroup::query()
                ->lockForUpdate()
                ->find($this->entityGroup->id);

                $result = $entityGroup->result_location ?? [];
                $result ['pdf_location'] = $OCRLocation;
                $result ['pdf_watermark_location'] = $fileWaterMarkedPath;
                $result ['word_location'] = $wordFileLocation;
                $result ['text_eng'] = $wordFileLocation;

                $entityGroup->transcription_result = trim($textOcr);
                $entityGroup->transcription_at = now();
                $entityGroup->result_location = $result;
                $entityGroup->status = EntityGroup::STATUS_TRANSCRIBED;
                $entityGroup->save();

                $activity = new Activity();
                $activity->user_id = $this->user->id;
                $activity->status = Activity::TYPE_TRANSCRIPTION;
                $activity->activity()->associate($entityGroup);
                $activity->save();
            }, 3);
        } catch (Exception $e) {
            $this->fail();
            throw new Exception($e->getMessage());
        }
    }

    public function fail(): void
    {
        if ($this->entityGroup->number_of_try >= 3) {
            $this->entityGroup->status = EntityGroup::STATUS_REJECTED;
        } else {
            $this->entityGroup->status = EntityGroup::STATUS_WAITING_FOR_RETRY;
        }
        $this->entityGroup->number_of_try += 1;
        $this->entityGroup->save();
    }
}
