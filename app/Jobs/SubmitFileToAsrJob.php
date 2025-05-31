<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Entity;
use App\Models\EntityGroup;
use App\Models\User;
use App\Services\AiService;
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

class SubmitFileToAsrJob implements ShouldQueue
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
    $this->onQueue('file::submit-to-ASR');
  }

  /**
   * Execute the job.
   * @throws Exception
   * @throws GuzzleException
   */
  public function handle(AiService $aiService, OfficeService $officeService): void
  {
    try {
      if (
        !in_array($this->entityGroup->status, [
          EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
          EntityGroup::STATUS_WAITING_FOR_MANUAL_PROCESS // Allow if manually triggered and this is the next step
        ])
      ) {
        Log::info("ASR => entityGroup:#{$this->entityGroup->id} (status: {$this->entityGroup->status}) is not in a state to start ASR processing by this job.");
        return;
      }
      Log::info("ASR => Submit entityGroup:#{$this->entityGroup->id} to ASR: calling ASR");

      // Submit pages to ASR
      $textASR = '';
      $entities = $this->entityGroup->entities()->orderBy('entities.id')->get();
      foreach ($entities as $entity) {
        $filePath = Storage::disk('voice')->path($entity->file_location);
        $ASRResult = $aiService->submitToASR($filePath);
        $contentASRResultCsv = strval($ASRResult['tsv']);
        $csvFileLocation = $officeService->generateCsvFileEntity($contentASRResultCsv);

        $meta = $entity->meta ?? [];
        $meta['csv_location'] = $csvFileLocation;
        $entity->transcription_result = strval($ASRResult['text']);
        $entity->meta = $meta;
        $entity->save();
        $textASR .= ' ' . strval($ASRResult['text']);
      }

      Log::info("ASR => ASR has been finished for entityGroup:#{$this->entityGroup->id}");

      $generateWindowsEntityGroup = $officeService->generateWindowsEntityGroup($this->entityGroup);
      $wordFileLocation = $officeService->generateWordFile($this->entityGroup, $textASR);

      DB::transaction(function () use ($textASR, $generateWindowsEntityGroup, $wordFileLocation) {
        /** @var EntityGroup $entityGroup */
        $entityGroup = EntityGroup::query()
          ->lockForUpdate()
          ->find($this->entityGroup->id);

        $result = $entityGroup->result_location ?? [];
        $result['voice_windows'] = $generateWindowsEntityGroup;
        $result['word_location'] = $wordFileLocation;

        $entityGroup->transcription_result = $textASR;
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

      $splitLocation = dirname($this->entityGroup->file_location) . '/' . $this->entityGroup->id;
      Storage::disk('voice')->deleteDirectory($splitLocation);
      /* @var  Entity $entity */
      foreach ($entities as $entity) {
        if (is_array($entity->meta) && isset($entity->meta['csv_location'])) {
          Storage::disk('csv')->delete($entity->meta['csv_location']);
        }
      }
      Log::info("ASR => delete all entities file in storage for entityGroup:#{$this->entityGroup->id}");
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
