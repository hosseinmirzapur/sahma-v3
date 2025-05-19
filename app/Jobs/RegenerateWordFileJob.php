<?php

namespace App\Jobs;

use App\Models\EntityGroup;
use App\Services\OfficeService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RegenerateWordFileJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 1;
  public int $timeout = 7200; // Same as SubmitFileToAsrJob
  public int $retryAfter = 7300; // Same as SubmitFileToAsrJob

  private EntityGroup $entityGroup;

  /**
   * Create a new job instance.
   */
  public function __construct(EntityGroup $entityGroup)
  {
    $this->entityGroup = $entityGroup;
    $this->onQueue('file::regenerate-word'); // Use a dedicated queue
  }

  /**
   * Execute the job.
   */
  public function handle(OfficeService $officeService): void
  {
    try {
      // Ensure the entity group has voice windows data
      if (!isset($this->entityGroup->result_location['voice_windows']) || !is_array($this->entityGroup->result_location['voice_windows'])) {
        Log::warning("RegenerateWordFileJob: EntityGroup:#{$this->entityGroup->id} does not have voice_windows data. Cannot regenerate Word file.");
        return;
      }

      Log::info("RegenerateWordFileJob: Regenerating Word file for EntityGroup:#{$this->entityGroup->id}");

      // Get the updated voice windows data
      $voiceWindows = $this->entityGroup->result_location['voice_windows'];

      // Concatenate the text from voice windows to get the full ASR text
      // Sort by key (start time) before concatenating to maintain order
      ksort($voiceWindows);
      $textASR = implode(' ', $voiceWindows);

      // Generate the new Word file
      $newWordFileLocation = $officeService->generateWordFile($this->entityGroup, $textASR);

      // Update the entity group with the new word file location
      $resultLocation = $this->entityGroup->result_location;
      $resultLocation['word_location'] = $newWordFileLocation;
      $this->entityGroup->result_location = $resultLocation;
      $this->entityGroup->save();

      Log::info("RegenerateWordFileJob: Word file regenerated successfully for EntityGroup:#{$this->entityGroup->id}. New location: {$newWordFileLocation}");

      // Optional: Clean up the old word file if needed.
      // This depends on whether generateWordFile overwrites or creates a new file.
      // If it creates a new file each time, you might want to delete the old one.
      // For now, let's assume generateWordFile handles potential overwriting or we keep old versions.

    } catch (Exception $e) {
      Log::error("RegenerateWordFileJob: Failed to regenerate Word file for EntityGroup:#{$this->entityGroup->id}. Error: " . $e->getMessage());
      $this->fail($e); // Mark the job as failed
    }
  }

  /**
   * Handle a job failure.
   */
  public function failed(Exception $exception): void
  {
    // Log the failure or notify users/admins
    Log::error("RegenerateWordFileJob failed for EntityGroup:#{$this->entityGroup->id}. Exception: " . $exception->getMessage());
    // You might want to update the EntityGroup status to indicate a failure in Word regeneration
    // $this->entityGroup->status = EntityGroup::STATUS_WORD_REGENERATION_FAILED;
    // $this->entityGroup->save();
  }
}
