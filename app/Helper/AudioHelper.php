<?php

namespace App\Helper;

use Exception;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
   * @param string $absoluteFilePath Absolute path to the file.
   * @return float
   * @throws Exception
   */
  public static function getAudioDurationByFfmpeg(string $absoluteFilePath): float
  {
    // Ensure the file exists before attempting to process
    if (!file_exists($absoluteFilePath)) {
      Log::error("FFmpeg duration check failed: File not found at $absoluteFilePath");
      throw new Exception("File not found for duration check: $absoluteFilePath");
    }

    $time = exec(
      "ffmpeg -i " .
      escapeshellarg($absoluteFilePath) .
      " 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//"
    );

    if ($time === false || !preg_match('/^\d{2}:\d{2}:\d{2}\.\d{2}$/', $time)) {
      Log::error("FFmpeg duration check failed for: $absoluteFilePath. Output: " . ($time ?: 'No output'));
      // Attempt ffprobe as a fallback or for more info
      $duration = self::getAudioDurationByFfprobe($absoluteFilePath);
      if ($duration !== null) {
        return $duration;
      }
      throw new Exception("Could not determine audio duration using ffmpeg for file: $absoluteFilePath");
    }

    list($hours, $minutes, $seconds) = explode(':', $time);
    return (intval($hours) * 3600) + (intval($minutes) * 60) + floatval($seconds);
  }

  /**
   * Get audio duration using ffprobe.
   *
   * @param string $absoluteFilePath
   * @return float|null Duration in seconds or null on failure.
   */
  public static function getAudioDurationByFfprobe(string $absoluteFilePath): ?float
  {
    try {
      $ffprobe = FFProbe::create([
        'ffmpeg.binaries' => config('media-library.ffmpeg_path'),
        'ffprobe.binaries' => config('media-library.ffprobe_path'),
      ]);

      if (!$ffprobe->isValid($absoluteFilePath)) {
        Log::warning("FFprobe validation failed for duration check: $absoluteFilePath");
        return null;
      }

      $format = $ffprobe->format($absoluteFilePath);
      $duration = $format->get('duration');

      return is_numeric($duration) ? floatval($duration) : null;
    } catch (\Throwable $e) {
      Log::error("FFprobe duration check encountered an error for $absoluteFilePath: " . $e->getMessage());
      return null;
    }
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

  /**
   * Validates if the file at the given path is a supported audio file using ffprobe.
   *
   * @param string $filePath Path relative to the storage disk 'voice'.
   * @throws ValidationException If the file is not a valid or supported audio format.
   * @throws Exception If ffprobe fails unexpectedly.
   */
  public static function validateAudioFile(string $filePath): void
  {
    $absolutePath = Storage::disk('voice')->path($filePath);
    if (!file_exists($absolutePath)) {
      Log::error("Audio validation failed: File not found at $absolutePath (relative: $filePath)");
      throw new Exception("Audio file not found for validation: $filePath");
    }

    try {
      // Assuming ffprobe is in PATH or configure paths if needed
      $ffprobe = FFProbe::create();
      if (!$ffprobe->isValid($absolutePath)) {
        Log::warning("Audio validation failed (ffprobe invalid): $filePath");
        throw ValidationException::withMessages(['file' => 'فایل صوتی نامعتبر است یا فرمت آن پشتیبانی نمی‌شود. (ffprobe invalid)']);
      }

      $streams = $ffprobe->streams($absolutePath)->audios();
      if ($streams->count() === 0) {
        Log::warning("Audio validation failed (no audio streams): $filePath");
        throw ValidationException::withMessages(['file' => 'فایل آپلود شده دارای جریان صوتی (audio stream) نمی‌باشد.']);
      }

      // Optional: Check format against config (might be less reliable than stream check)
      // $format = $ffprobe->format($absolutePath)->get('format_name');
      // $allowedFormats = array_keys(Config::get('mime-type.voice', []));
      // if (!in_array($format, $allowedFormats)) { // This mapping might need adjustment based on ffprobe output
      //     Log::warning("Audio validation failed (format '$format' not in allowed list): $filePath");
      //     throw ValidationException::withMessages(['file' => "فرمت فایل صوتی ($format) پشتیبانی نمی‌شود."]);
      // }

    } catch (\Throwable $e) {
      Log::error("Audio validation failed with error for $filePath: " . $e->getMessage());
      throw new Exception("خطا در اعتبارسنجی فایل صوتی: " . $e->getMessage());
    }
  }

  /**
   * Validates if the file at the given path is a supported video file using ffprobe.
   *
   * @param string $filePath Path relative to the storage disk 'video'.
   * @throws ValidationException If the file is not a valid or supported video format.
   * @throws Exception If ffprobe fails unexpectedly.
   */
  public static function validateVideoFile(string $filePath): void
  {
    $absolutePath = Storage::disk('video')->path($filePath);
    if (!file_exists($absolutePath)) {
      Log::error("Video validation failed: File not found at $absolutePath (relative: $filePath)");
      throw new Exception("Video file not found for validation: $filePath");
    }

    try {
      // Assuming ffprobe is in PATH or configure paths if needed
      $ffprobe = FFProbe::create();
      if (!$ffprobe->isValid($absolutePath)) {
        Log::warning("Video validation failed (ffprobe invalid): $filePath");
        throw ValidationException::withMessages(['file' => 'فایل ویدئویی نامعتبر است یا فرمت آن پشتیبانی نمی‌شود. (ffprobe invalid)']);
      }

      $streams = $ffprobe->streams($absolutePath)->videos();
      if ($streams->count() === 0) {
        Log::warning("Video validation failed (no video streams): $filePath");
        throw ValidationException::withMessages(['file' => 'فایل آپلود شده دارای جریان ویدئویی (video stream) نمی‌باشد.']);
      }

      // Optional: Check format against config (might be less reliable than stream check)
      // $format = $ffprobe->format($absolutePath)->get('format_name');
      // ... check against config('mime-type.video') ...

    } catch (\Throwable $e) {
      Log::error("Video validation failed with error for $filePath: " . $e->getMessage());
      throw new Exception("خطا در اعتبارسنجی فایل ویدئویی: " . $e->getMessage());
    }
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
