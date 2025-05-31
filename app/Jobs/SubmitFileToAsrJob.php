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
        $safeASRResult = [];
        if (is_array($ASRResult)) {
          $safeASRResult['tsv'] = $ASRResult['tsv'] ?? '';
          $safeASRResult['text'] = $ASRResult['text'] ?? '';
        } else {
          Log::error("ASR => ASRResult is not an array for entity:#{$entity->id}", ['ASRResult' => $ASRResult]);
          $safeASRResult = ['tsv' => '', 'text' => ''];
        }

        $contentASRResultCsv = strval($safeASRResult['tsv']);
        $csvFileLocation = $officeService->generateCsvFileEntity($contentASRResultCsv);

        $meta = (array) ($entity->meta ?? []);
        $meta['csv_location'] = $csvFileLocation;
        $entity->transcription_result = strval($safeASRResult['text']);
        $entity->meta = $meta;
        $entity->save();
        $textASR .= ' ' . strval($safeASRResult['text']);
      }

      Log::info("ASR => ASR has been finished for entityGroup:#{$this->entityGroup->id}");

      $generateWindowsEntityGroup = $officeService->generateWindowsEntityGroup($this->entityGroup);
      $wordFileLocation = $officeService->generateWordFile($this->entityGroup, $textASR);

      DB::transaction(function () use ($textASR, $generateWindowsEntityGroup, $wordFileLocation) {
        Log::info("ASR => DB Transaction: Starting for entityGroup ID: {$this->entityGroup->id}");

        /** @var EntityGroup $entityGroup */
        $entityGroup = EntityGroup::query()
          ->lockForUpdate()
          ->find($this->entityGroup->id);

        if (!$entityGroup) {
          Log::error("ASR => DB Transaction: EntityGroup not found for ID: {$this->entityGroup->id}");
          return; // Or throw an exception
        }

        Log::info("ASR => DB Transaction: EntityGroup found. result_location before cast: " . json_encode($entityGroup->result_location));
        $result = (array) ($entityGroup->result_location ?? []);
        Log::info("ASR => DB Transaction: result_location after cast: " . json_encode($result));

        $result['voice_windows'] = $generateWindowsEntityGroup;
        $result['word_location'] = $wordFileLocation;
        Log::info("ASR => DB Transaction: result array updated: " . json_encode($result));

        $entityGroup->transcription_result = $textASR;
        $entityGroup->transcription_at = now();
        $entityGroup->result_location = $result;
        $entityGroup->status = EntityGroup::STATUS_TRANSCRIBED;
        $entityGroup->save();
        Log::info("ASR => DB Transaction: EntityGroup saved. Status: {$entityGroup->status}");

        $activity = new Activity();
        $activity->user_id = $this->user->id;
        $activity->status = Activity::TYPE_TRANSCRIPTION;
        $activity->activity()->associate($entityGroup);
        $activity->save();
        Log::info("ASR => DB Transaction: Activity saved.");
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
