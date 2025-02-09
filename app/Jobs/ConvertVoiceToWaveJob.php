<?php

namespace App\Jobs;

use App\Models\EntityGroup;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
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

    public int $tries = 3; // Allow 3 retries instead of failing immediately
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
     */
    public function handle(): void
    {
        try {
            $path = $this->entityGroup->file_location;
            $this->entityGroup->meta['original-file-location'] = $path;

            // Read the audio file using Laravel's Storage disk
            if (!Storage::disk('voice')->exists($path)) {
                throw new FileNotFoundException("Audio file not found: $path");
            }

            $tempFilePath = Storage::disk('voice')->path($path);
            $wavContent = $this->convertToWav($tempFilePath);

            // Save converted WAV file
            $convertedPath = dirname($path) . '/converted/' . pathinfo($path, PATHINFO_FILENAME) . '.wav';
            Storage::disk('voice')->put($convertedPath, $wavContent);

            // Update database record
            $this->entityGroup->update([
                'result_location' => array_merge(
                    $this->entityGroup->result_location ?? [],
                    ['wav_location' => $convertedPath]
                )
            ]);

            SubmitVoiceToSplitterJob::dispatch($this->entityGroup);
        } catch (Exception $e) {
            Log::error("ConvertVoiceToWaveJob failed for EntityGroup {$this->entityGroup->id}: " . $e->getMessage());
            $this->fail();
            throw $e;
        }
    }

    /**
     * @param string $filePath
     * @return string
     * @throws Exception
     */
    private function convertToWav(string $filePath): string
    {
        $outputPath = "/tmp/" . Carbon::now()->timestamp . '-' . uniqid('converted-voice') . '.wav';

        $command = escapeshellcmd(
            "ffmpeg -y -i '$filePath' -ac 1 -ar 16000 -acodec pcm_s16le -loglevel error '$outputPath' 2>&1"
        );
        $output = shell_exec($command);

        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new Exception("FFmpeg conversion failed: " . ($output ?: 'Unknown error'));
        }

        $content = file_get_contents($outputPath);
        unlink($outputPath);

        return $content ?: throw new Exception("Failed to read WAV output file");
    }

    public function fail(): void
    {
        $numberOfTry = $this->entityGroup->number_of_try;
        $entityGroupId = $this->entityGroup->id;
        Log::warning("ConvertVoiceToWaveJob retry attempt: $numberOfTry for EntityGroup ID: $entityGroupId");

        if ($numberOfTry >= 3) {
            $this->entityGroup->update(['status' => EntityGroup::STATUS_REJECTED]);
        } else {
            $this->entityGroup::query()->increment('number_of_try');
            $this->entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_RETRY]);
        }
    }
}
