<?php

namespace App\Jobs;

use App\Models\Entity;
use App\Models\EntityGroup;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateSplitVoiceToEntitiesJob implements ShouldQueue
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
        $this->onQueue('voice::split-to-entities');
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            if ($this->entityGroup->status != EntityGroup::STATUS_WAITING_FOR_SPLIT) {
                Log::warning("entityGroup: #{$this->entityGroup->id} is not in split status");
                return;
            }
            if (is_null($this->entityGroup->meta) || !isset($this->entityGroup->meta['windows'])) {
                throw new Exception("Windows is not set in voice meta, entityGroup: #{$this->entityGroup->id}");
            }
            $windows = $this->entityGroup->meta['windows'];
            $audioData = $this->entityGroup->getFileData(true);
            if (is_null($audioData)) {
                throw new Exception("Failed to get audio data, entityGroup: #{$this->entityGroup->id}");
            }
            $path = $this->entityGroup->file_location;
            $entities = [];
            foreach ($windows as $i => $window) {
                $content = $this->clipAudio($audioData, $window[0], $window[1]);
                $extension = pathinfo($path, PATHINFO_EXTENSION);
                $fileName = pathinfo($path, PATHINFO_FILENAME);
                $slicePath = dirname($path) . '/' . $this->entityGroup->id .
                    '/slice-' . $i . '-' . $fileName . '.' . $extension;
                if (Storage::disk('voice')->put($slicePath, $content) === false) {
                    throw new Exception("Failed to write data, entityGroup: #{$this->entityGroup->id}");
                }
                $meta = [];
                $meta['window'] = [
                    'start' => $window[0],
                    'end' => $window[1]
                ];
                $entities[] = [
                    'file_location' => $slicePath,
                    'meta' => $meta
                ];
            }
            DB::transaction(function () use ($entities) {
                $entityGroup = EntityGroup::query()
                    ->lockForUpdate()
                    ->findOrFail($this->entityGroup->id);
                foreach ($entities as $entity) {
                    Entity::query()->create([
                        'entity_group_id' => $entityGroup->id,
                        'type' => 'voice',
                        'file_location' => $entity['file_location'],
                        'meta' => $entity['meta'],
                    ]);
                }
                $this->entityGroup->status = EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION;
                $this->entityGroup->save();
            }, 3);
            Log::info("STT => questionAnswerGroup: #{$this->entityGroup->id} split successfully");
            SubmitFileToAsrJob::dispatch($this->entityGroup, $this->entityGroup->user);
        } catch (Exception $e) {
            $this->fail();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param string $audioData
     * @param float $start
     * @param float $end
     * @return string
     * @throws FileNotFoundException
     * @throws Exception
     */
    private function clipAudio(string $audioData, float $start, float $end): string
    {
        $duration = $end - $start;
        $tempFileName = Carbon::now()->timestamp . '-' . uniqid('splitter-voice-temp-file') . '.wav';
        $tmpPath = '/tmp/' . $tempFileName;
        $tempSlicePath = '/tmp/' . 'slice-' . $tempFileName;
        file_put_contents($tmpPath, $audioData);
        exec(
            'ffmpeg -y -i ' .
            $tmpPath .
            ' -ss ' .
            $this->formatTime($start) .
            ' -t ' .
            $this->formatTime($duration) .
            ' -acodec copy -ac 2 -ar 16000 -acodec pcm_s16le -b:a 16k -ac 1 -loglevel quiet ' .
            $tempSlicePath
        );
        $content = file_get_contents($tempSlicePath);
        unlink($tmpPath);
        unlink($tempSlicePath);
        if ($content === false) {
            throw new FileNotFoundException('temp file not found in splitting process');
        }
        return $content;
    }

    /**
     * @param float $seconds
     * @return string
     * @throws Exception
     */
    private function formatTime(float $seconds): string
    {
        $decimalPart = $seconds - floor($seconds);
        if ($seconds / 3600 > 23) {
            throw new Exception('Hour can not be more than 23');
        }
        return sprintf(
            '%02d:%02d:%02d.%02d',
            ($seconds / 3600),
            ($seconds / 60 % 60),
            $seconds % 60,
            (int)($decimalPart * 100)
        );
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
