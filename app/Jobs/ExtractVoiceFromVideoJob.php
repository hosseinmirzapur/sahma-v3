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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExtractVoiceFromVideoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public int $retryAfter = 7300;

    private EntityGroup $entityGroup;

  /**
   * Create a new job instance.
   */
    public function __construct(EntityGroup $entityGroup)
    {
        $this->entityGroup = $entityGroup;
        $this->onQueue('file::extract-audio');
    }

  /**
   * Execute the job.
   * @throws Exception
   */
    public function handle(): void
    {
        try {
            if ($this->entityGroup->status != EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION) {
                Log::info("VTT => entityGroup:#{$this->entityGroup->id} is not in separate state");
                return;
            }
            Log::info("VTT => Submit entityGroup:#{$this->entityGroup->id} to separate: calling separate");
          // Replace 'video_file.mp4' with the actual name of your video file in the storage
            $videoPath = Storage::disk('video')->path($this->entityGroup->file_location);

          // Create a unique filename for the extracted audio
            $audioFileName = '/extracted_audio_' . now()->timestamp .
            $this->entityGroup->id . '-' . pathinfo($this->entityGroup->name, PATHINFO_FILENAME) . '.wav';
            $videoPathFromDisk = strval(pathinfo(
                $this->entityGroup->file_location,
                PATHINFO_DIRNAME
            ));
            $extractedVoicePath = Storage::disk('voice')->path($videoPathFromDisk . $audioFileName);

            if (!Storage::disk('voice')->exists($videoPathFromDisk)) {
                Storage::disk('voice')->makeDirectory($videoPathFromDisk);
            }

            $extractedVoicePath = str_replace(' ', '', $extractedVoicePath);

            $command = "ffmpeg -i $videoPath -vn -acodec pcm_s16le -ar 44100 -ac 2 $extractedVoicePath";

            Log::info($command);
            $output = null;
            $returnVal = null;
            Log::info("VTT => Starting extract voice of video entityGroup:#{$this->entityGroup->id}!");
            exec($command, $output, $returnVal);
            Log::info(
                "VTT => Extract voice of video
        entityGroup:#{$this->entityGroup->id}:finished with returnVal=>" . $returnVal
            );

            if ($returnVal != 0) {
                throw new Exception(
                    "VTT => Return value of ffmpeg for entityGroup:#{$this->entityGroup->id} is $returnVal."
                );
            }

            $storagePath = strval(pathinfo($this->entityGroup->file_location, PATHINFO_DIRNAME)) . $audioFileName;

            $result = $this->entityGroup->result_location ?? [];
            $result['wav_location'] = $storagePath;
            $this->entityGroup->result_location = $result;
            $this->entityGroup->status = EntityGroup::STATUS_WAITING_FOR_SPLIT;
            $this->entityGroup->save();

            SubmitVoiceToSplitterJob::dispatch($this->entityGroup);
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
