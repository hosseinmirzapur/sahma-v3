<?php

namespace App\Jobs;

use App\Models\EntityGroup;
use App\Models\User;
use App\Services\AiService;
use App\Services\OfficeService;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ConvertVoiceToWaveJob implements ShouldQueue
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
        $this->onQueue('voice::convert-voice');
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            $path = $this->entityGroup->file_location;
            $meta = $this->entityGroup->meta;
            $meta['original-file-location'] = $path;
            $this->entityGroup->meta = $meta;
            $audioData = $this->entityGroup->getFileData();
            if (is_null($audioData)) {
                throw new Exception("Failed to get audio data");
            }
            $content = $this->voiceConvertToWav($audioData);
            $fileName = pathinfo($path, PATHINFO_FILENAME);
            $pathWav =  dirname($path) . '/converted/' . $fileName . '.wav';
            if (Storage::disk('voice')->put($pathWav, $content) === false) {
                throw new Exception('Failed to write data');
            }
            $resultLocation = $this->entityGroup->result_location ?? [];
            $resultLocation['wav_location'] = $pathWav;
            $this->entityGroup->result_location = $resultLocation;
            $this->entityGroup->save();
            SubmitVoiceToSplitterJob::dispatch($this->entityGroup);
        } catch (Exception $e) {
            $this->fail();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param string $audioData
     * @return string
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function voiceConvertToWav(string $audioData): string
    {
        $tempFileName = Carbon::now()->timestamp . '-' . uniqid('converting-voice-temp-file') . '.wav';
        $tmpPath = "/tmp/$tempFileName";
        $tempWavPath = "/tmp/$tempFileName.wav";
        $handle = fopen($tmpPath, "w");
        if ($handle === false) {
            throw new Exception('Failed to open audio file');
        }
        fwrite($handle, $audioData);
        fclose($handle);
        exec(
            'ffmpeg -y -i ' .
            $tmpPath .
            ' -ac 2 -ar 16000 -acodec pcm_s16le -b:a 16k -ac 1 -loglevel quiet ' .
            $tempWavPath
        );
        $content = file_get_contents($tempWavPath);
        unlink($tmpPath);
        unlink($tempWavPath);
        if ($content === false) {
            throw new Exception('temp file not found in converting process');
        }
        return $content;
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
