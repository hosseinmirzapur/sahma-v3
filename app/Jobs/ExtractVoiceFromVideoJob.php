<?php

namespace App\Jobs;

use App\Models\EntityGroup;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ExtractVoiceFromVideoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 7200;
    public int $retryAfter = 7300;
    private EntityGroup $entityGroup;
    private Filesystem $videoStorage;
    private Filesystem $voiceStorage;

    public function __construct(EntityGroup $entityGroup)
    {
        $this->entityGroup = $entityGroup;
        $this->onQueue('file::extract-audio');
        $this->videoStorage = app('filesystem')->disk('video');
        $this->voiceStorage = app('filesystem')->disk('voice');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            if ($this->entityGroup->status !== EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION) {
                Log::warning(
                    "Skipping extraction: entityGroup #{$this->entityGroup->id} is not in the correct status."
                );
                return;
            }

            Log::info("Starting audio extraction for entityGroup: #{$this->entityGroup->id}");

            $videoPath = $this->videoStorage->path($this->entityGroup->file_location);
            $outputDir = pathinfo($this->entityGroup->file_location, PATHINFO_DIRNAME);
            $audioFileName = "/extracted_audio_{$this->entityGroup->id}_" . now()->timestamp . ".wav";
            $extractedVoicePath = $this->voiceStorage->path($outputDir . $audioFileName);

            if (!$this->voiceStorage->exists($outputDir)) {
                $this->voiceStorage->makeDirectory($outputDir);
            }

            $command = [
                'ffmpeg', '-i', $videoPath, '-vn',
                '-acodec', 'pcm_s16le', '-ar', '44100', '-ac', '2', $extractedVoicePath
            ];

            $process = Process::run($command);

            if (!$process->successful()) {
                throw new Exception(
                    "FFmpeg failed with exit code {$process->exitCode()}: {$process->errorOutput()}"
                );
            }

            $this->entityGroup->result_location = array_merge(
                $this->entityGroup->result_location ?? [],
                ['wav_location' => $outputDir . $audioFileName]
            );
            $this->entityGroup->status = EntityGroup::STATUS_WAITING_FOR_SPLIT;
            $this->entityGroup->save();

            Log::info("Audio extraction completed for entityGroup #{$this->entityGroup->id}");
            SubmitVoiceToSplitterJob::dispatch($this->entityGroup);
        } catch (Exception $e) {
            $this->fail();
            throw $e; // Preserve original stack trace
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

        $entityGroupId = $this->entityGroup->id;
        $numberOfTry = $this->entityGroup->number_of_try;

        $this->entityGroup->save();
        Log::error(
            "Job failed for entityGroup #$entityGroupId. Retrying ($numberOfTry/3)"
        );
    }
}
