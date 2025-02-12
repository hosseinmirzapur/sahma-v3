<?php

namespace App\Jobs;

use App\Models\EntityGroup;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConvertVoiceToWaveJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 7200;

    private EntityGroup $entityGroup;

    public function __construct(EntityGroup $entityGroup)
    {
        $this->entityGroup = $entityGroup;
        $this->onQueue('voice::convert-voice');
    }

    /**
     * @return void
     * @throws FileNotFoundException
     * @throws BindingResolutionException
     */
    public function handle(): void
    {
        try {
            $path = $this->entityGroup->file_location;

            // Ensure meta array is updated correctly
            $meta = $this->entityGroup->meta ?? [];
            $meta['original-file-location'] = $path;
            $this->entityGroup->meta = $meta;
            $this->entityGroup->save();

            if (!Storage::disk('voice')->exists($path)) {
                throw new FileNotFoundException("Audio file not found: $path");
            }

            $audioData = $this->entityGroup->getFileData();
            if (is_null($audioData)) {
                throw new Exception("Failed to get audio data");
            }

            $content = $this->convertToWav($audioData);
            $fileName = pathinfo($path, PATHINFO_FILENAME);
            $convertedPath = dirname($path) . '/converted/' . $fileName . '.wav';

            if (!Storage::disk('voice')->put($convertedPath, $content)) {
                throw new Exception('Failed to write data');
            }

            // Ensure result_location is updated properly
            $this->entityGroup->update([
                'result_location' => array_merge(
                    $this->entityGroup->result_location ?? [],
                    ['wav_location' => $convertedPath]
                )
            ]);

            SubmitVoiceToSplitterJob::dispatch($this->entityGroup);
        } catch (Exception $e) {
            Log::error(
                "ConvertVoiceToWaveJob failed for EntityGroup {$this->entityGroup->id}: " . $e->getMessage()
            );
            $this->fail();
            throw $e;
        }
    }

    private function convertToWav(string $audioData): string
    {
        $tempFileName = "/tmp/" . Carbon::now()->timestamp . '-' . uniqid('converted-voice') . '.wav';

        $command = escapeshellcmd(
            "ffmpeg -y -i '$tempFileName' -ac 1 -ar 16000 -acodec pcm_s16le -loglevel error '$tempFileName' 2>&1"
        );
        shell_exec($command);

        if (!file_exists($tempFileName) || filesize($tempFileName) === 0) {
            throw new Exception("FFmpeg conversion failed.");
        }

        $content = file_get_contents($tempFileName);
        unlink($tempFileName);

        return $content ?: throw new Exception("Failed to read WAV output file.");
    }

    public function fail(): void
    {
        Log::warning("ConvertVoiceToWaveJob retry attempt for EntityGroup ID: {$this->entityGroup->id}");

        if ($this->entityGroup->number_of_try >= 3) {
            $this->entityGroup->update(['status' => EntityGroup::STATUS_REJECTED]);
        } else {
            $this->entityGroup::query()->increment('number_of_try');
            $this->entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_RETRY]);
        }
    }
}
