<?php

namespace App\Jobs;

use App\Models\Entity;
use App\Models\EntityGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateSplitVoiceToEntitiesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 7200;
    public int $retryAfter = 7300;

    private EntityGroup $entityGroup;

    public function __construct(EntityGroup $entityGroup)
    {
        $this->entityGroup = $entityGroup;
        $this->onQueue('voice::split-to-entities');
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            if ($this->entityGroup->status !== EntityGroup::STATUS_WAITING_FOR_SPLIT) {
                Log::warning("EntityGroup #{$this->entityGroup->id} is not in split status.");
                return;
            }

            if (empty($this->entityGroup->meta['windows'])) {
                throw new RuntimeException(
                    "Windows data missing in EntityGroup #{$this->entityGroup->id} meta."
                );
            }

            $windows = $this->entityGroup->meta['windows'];
            $audioData = $this->entityGroup->getFileData(true);

            if (!$audioData) {
                throw new RuntimeException(
                    "Failed to retrieve audio data for EntityGroup #{$this->entityGroup->id}."
                );
            }

            $path = $this->entityGroup->file_location;
            $entities = [];

            foreach ($windows as $i => $window) {
                $content = $this->clipAudio($audioData, $window[0], $window[1]);

                $extension = pathinfo($path, PATHINFO_EXTENSION);
                $fileName = pathinfo($path, PATHINFO_FILENAME);
                $slicePath = "voices/{$this->entityGroup->id}/slice-$i-$fileName.$extension";

                if (!Storage::disk('voice')->put($slicePath, $content)) {
                    throw new RuntimeException(
                        "Failed to save clipped audio for EntityGroup #{$this->entityGroup->id}."
                    );
                }

                $entities[] = [
                    'file_location' => $slicePath,
                    'meta' => ['window' => ['start' => $window[0], 'end' => $window[1]]]
                ];
            }

            DB::transaction(function () use ($entities) {
                foreach ($entities as $entity) {
                    Entity::query()->create([
                        'entity_group_id' => $this->entityGroup->id,
                        'type' => 'voice',
                        'file_location' => $entity['file_location'],
                        'meta' => $entity['meta'],
                    ]);
                }

                $this->entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION]);
            }, 3);

            Log::info("EntityGroup #{$this->entityGroup->id} split successfully.");
            SubmitFileToAsrJob::dispatch($this->entityGroup, $this->entityGroup->user);
        } catch (Throwable $e) {
            Log::error("Error in CreateSplitVoiceToEntitiesJob: " . $e->getMessage());
            $this->fail();
        }
    }

    /**
     * @param string $audioData
     * @param float $start
     * @param float $end
     * @return string
     * @throws FileNotFoundException
     */
    private function clipAudio(string $audioData, float $start, float $end): string
    {
        $duration = max($end - $start, 0);
        $tempInputPath = $this->createTempFile($audioData);
        $tempOutputPath = sys_get_temp_dir() . '/' . Str::uuid() . '.wav';

        $command = [
            'ffmpeg', '-y', '-i', $tempInputPath,
            '-ss', $this->formatTime($start),
            '-t', $this->formatTime($duration),
            '-ac', '1', '-ar', '16000',
            '-acodec', 'pcm_s16le', '-hide_banner', '-loglevel', 'error', $tempOutputPath
        ];

        $process = Process::timeout(30)->run($command);

        if (!$process->successful()) {
            throw new RuntimeException("FFmpeg clipping failed: " . $process->errorOutput());
        }

        $content = @file_get_contents($tempOutputPath);

        $this->cleanupFiles([$tempInputPath, $tempOutputPath]);

        if (!$content) {
            throw new FileNotFoundException("FFmpeg failed to generate output file.");
        }

        return $content;
    }

    /**
     * @param string $data
     * @return string
     */
    private function createTempFile(string $data): string
    {
        $path = sys_get_temp_dir() . '/' . Str::uuid();
        if (!file_put_contents($path, $data)) {
            throw new RuntimeException('Failed to create temporary file.');
        }
        return $path;
    }

    /**
     * @param float $seconds
     * @return string
     */
    private function formatTime(float $seconds): string
    {
        if ($seconds > 86399) { // Prevent hours from exceeding 23:59:59
            throw new RuntimeException('Time format error: Hour cannot be more than 23.');
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $milliseconds = round(($seconds - floor($seconds)) * 100);

        return sprintf('%02d:%02d:%02d.%02d', $hours, $minutes, $secs, $milliseconds);
    }

    /**
     * @param array $paths
     * @return void
     */
    private function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    public function fail(): void
    {
        $this->entityGroup::query()->increment('number_of_try');

        if ($this->entityGroup->number_of_try >= 3) {
            $this->entityGroup->update(['status' => EntityGroup::STATUS_REJECTED]);
        } else {
            $this->entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_RETRY]);
        }
    }
}
