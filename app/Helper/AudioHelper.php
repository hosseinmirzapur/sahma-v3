<?php

namespace App\Helper;

use Exception;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Support\Facades\Storage;
use wapmorgan\MediaFile\Adapters\WavAdapter;
use wapmorgan\MediaFile\Exceptions\FileAccessException;
use wapmorgan\MediaFile\MediaFile;

class AudioHelper
{
    /**
     * @param string $filename
     * @param string $audioData
     * @return array
     * @throws FileAccessException
     */
    public static function getWavDurationAndMetaData(string $filename, string $audioData): array
    {
        return [
            'duration' => self::getAudioDuration('get-duration-' . $filename, $audioData),
            'metadata' => self::getWavMetaData('get-meta-data-' . $filename, $audioData)
        ];
    }

    /**
     * @param string $filename
     * @param string $audioData
     * @return float|int|null
     * @throws FileAccessException
     * @throws Exception
     */
    public static function getAudioDuration(string $filename, string $audioData): float|int|null
    {
        $tmpfname = '/tmp/' . $filename;
        $extension = pathinfo($tmpfname, PATHINFO_EXTENSION);
        if ($extension === 'ogg' || $extension === 'mp4') {
            return self::getAudioDurationByFfmpeg($filename, $audioData);
        }
        $handle = fopen($tmpfname, "w");
        if ($handle === false) {
            throw new Exception();
        }
        fwrite($handle, $audioData);
        fclose($handle);
        $duration = MediaFile::open($tmpfname)->getAudio()->getLength();
        unlink($tmpfname);
        return $duration;
    }

    /**
     * @param string $fileLocation
     * @param string $audioData
     * @return float
     * @throws Exception
     */
    public static function getAudioDurationByFfmpeg(string $fileLocation, string $audioData): float
    {
        $handle = fopen($fileLocation, "w");
        if ($handle === false) {
            throw new Exception();
        }
        fwrite($handle, $audioData);
        fclose($handle);
        $time = exec(
            "ffmpeg -i " .
            escapeshellarg($fileLocation) .
            " 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//"
        );
        if ($time === false) {
            throw new Exception('error');
        }
        list($hours, $minutes, $seconds) = explode(':', $time);
        return (intval($hours) * 3600) + (intval($minutes) * 60) + floatval($seconds);
    }

    /**
     * @param string $text
     * @return float|int
     */
    public static function getMaxPossibleAudioDurationForText(string $text): float|int
    {
        $words = explode(" ", trim($text));
        $totalDuration = 0;
        foreach ($words as $word) {
            if (mb_strlen($word) > 2) {
                $totalDuration += 1.2;
            } else {
                $totalDuration += 0.8;
            }
        }

        if ($totalDuration <= 4) {
            $totalDuration = 4;
        }
        return $totalDuration;
    }

    /**
     * @param string $filename
     * @param string $audioData
     * @return array|null
     * @throws FileAccessException
     * @throws Exception
     */
    public static function getWavMetaData(string $filename, string $audioData): ?array
    {
        $tmpfname = '/tmp/' . $filename;
        $handle = fopen($tmpfname, "w");
        if ($handle === false) {
            throw new Exception();
        }
        fwrite($handle, $audioData);
        fclose($handle);
        $audioAdapter = MediaFile::open($tmpfname)->getAudio();
        if ($audioAdapter instanceof WavAdapter) {
            $meta = $audioAdapter->getMetadata();
            $metadata = [
                'format' => $meta->getFormat(),
                'channels' => $meta->getChannels(),
                'sampleRate' => $meta->getSampleRate(),
                'bytesPerSecond' => $meta->getBytesPerSecond(),
                'blockSize' => $meta->getBlockSize(),
                'bitsPerSample' => $meta->getBitsPerSample(),
                'extensionSize' => $meta->getExtensionSize(),
                'extensionData' => $meta->getExtensionData()
            ];
        }
        unlink($tmpfname);
        return $metadata ?? null;
    }

    public static function voiceConvertToMp3(string $audioData, $start, $end): ?string
    {
        // Create a unique temporary filename
        $tempFileName = uniqid('converting-voice-temp-file') . '.wav';
        $tempPath = 'temp/' . $tempFileName;

        // Store the temporary audio data in storage
        Storage::put($tempPath, $audioData);

        // Define the output path for the MP3 file
        $outputPath = 'temp/' . uniqid('converted-voice-') . '.mp3';

        // Create FFMpeg instance
        $ffmpeg = FFMpeg::create();

        // Open the temporary WAV file
        $audio = $ffmpeg->open(Storage::path($tempPath));

        // Set MP3 format and save the converted file
        if ($start !== null && $end !== null) {
            $duration = $end - $start;
            $audio->clip($start, $duration)->save(new Mp3(), Storage::path($outputPath));
        } else {
            $audio->save(new Mp3(), Storage::path($outputPath));
        }

        // Retrieve the converted MP3 content
        $content = Storage::get($outputPath);

        // Clean up temporary files
        Storage::delete([$tempPath, $outputPath]);

        return $content;
    }
}
