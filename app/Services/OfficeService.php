<?php

namespace App\Services;

use App\Models\EntityGroup;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\Process\Process;

class OfficeService
{
  /**
   * @throws \PhpOffice\PhpWord\Exception\Exception
   * @throws Exception
   */
  public function generateWordFile(EntityGroup $entityGroup, string $text): string
  {
    $phpWord = new PhpWord();
    $phpWord->setDefaultParagraphStyle(['align' => Jc::BOTH, 'bidi' => true]);
    $phpWord->setDefaultFontName('B Nazanin');
    $phpWord->setDefaultFontSize(12);

    $section = $phpWord->addSection();
    $paragraphs = array_filter(array_map('trim', explode("\n", $text))); // Removes empty lines

    foreach ($paragraphs as $paragraph) {
      $section->addText($paragraph, ['alignment' => Jc::BOTH]);
      $section->addTextBreak();
    }

    $filename = pathinfo($entityGroup->name, PATHINFO_FILENAME) . ".docx";
    $storageFilePath = now()->toDateString() . '/' . now()->timestamp . "-$filename";

    $tempPath = tempnam(sys_get_temp_dir(), 'word_') . '.docx';
    $objWriter = IOFactory::createWriter($phpWord);
    $objWriter->save($tempPath);

    Storage::disk('word')->put($storageFilePath, file_get_contents($tempPath));
    unlink($tempPath);

    return $storageFilePath;
  }

  /**
   * @throws Exception
   */
  public function generateCsvFileEntity(string $text): string
  {
    $filename = now()->timestamp . '-' . str_replace(
      [' ', '.'],
      '',
      uniqid('split_voice_', true)
    ) . '.csv';
    $storagePath = now()->toDateString() . '/' . $filename;

    Storage::disk('csv')->put($storagePath, $text);

    return $storagePath;
  }

  /**
   * @throws Exception
   */
  public function generateWindowsEntityGroup(EntityGroup $entityGroup): array
  {
    $voiceWindows = [];
    foreach ($entityGroup->entities as $entity) {
      if (
        empty($entity->meta['csv_location']) ||
        !Storage::disk('csv')
          ->exists($entity->meta['csv_location'])
      ) {
        throw ValidationException::withMessages(['message' => 'CSV location is not set or does not exist']);
      }

      $beginningOfWindow = $entity->meta['window']['start'] ?? 0;
      $csvFilePath = Storage::disk('csv')->path($entity->meta['csv_location']);
      $entityWindows = FileService::getAudioInfo($csvFilePath);

      foreach ($entityWindows as $key => $text) {
        $voiceWindows[(string) ($key + $beginningOfWindow)] = $text;
      }
    }

    return $voiceWindows;
  }

  /**
   * @param string $wordFilePath
   * @return string
   * @throws Exception
   */
  public function convertWordFileToPdf(string $wordFilePath): string
  {
    $wordFilePath = Storage::disk('word')->path($wordFilePath);
    $filename = pathinfo($wordFilePath, PATHINFO_FILENAME);
    // Note: $outputPdfPath will be the full path to the desired PDF file.
    // LibreOffice's --outdir expects a directory, not a full file path for output.
    $outputDir = Storage::disk('pdf')->path(''); // Get the root path of the 'pdf' disk
    $expectedPdfName = "$filename.pdf";
    $outputPdfPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $expectedPdfName;

    $libreofficePath = Config::get('services.libreoffice.path', 'libreoffice');

    // Ensure the output directory exists on the 'pdf' disk
    // We use the relative path for the disk operations
    $relativeOutputDirForDisk = ''; // Assuming output is to the root of the 'pdf' disk.
    // If $filename.pdf was in a subdirectory of the pdf disk,
    // this would be that subdirectory.
    if (!Storage::disk('pdf')->exists($relativeOutputDirForDisk)) {
      Storage::disk('pdf')->makeDirectory($relativeOutputDirForDisk);
    }

    // The --outdir for libreoffice needs to be an absolute system path
    $absoluteOutputDir = Storage::disk('pdf')->path($relativeOutputDirForDisk);
    // Ensure the absolute output directory actually exists on the filesystem
    if (!file_exists($absoluteOutputDir)) {
      mkdir($absoluteOutputDir, 0777, true);
    }


    $process = new Process([
      $libreofficePath,
      '--headless',
      '--convert-to',
      'pdf',
      '--outdir',
      $absoluteOutputDir, // LibreOffice needs the directory to output to
      $wordFilePath
    ]);
    $process->setTimeout(3600); // Set a timeout (e.g., 1 hour)
    $process->run();

    if (!$process->isSuccessful()) {
      $errorOutput = $process->getErrorOutput();
      if (str_contains($errorOutput, 'not found') || str_contains($errorOutput, 'No such file or directory')) {
        throw new Exception("Failed to convert Word to PDF: The 'libreoffice' command was not found. Please ensure it is installed and the path is correctly configured in config/services.php or .env file (LIBREOFFICE_PATH). Error: " . $errorOutput);
      }
      throw new Exception("Failed to convert Word to PDF using LibreOffice: " . $errorOutput . " Command: " . $process->getCommandLine());
    }

    // After conversion, LibreOffice places the file in $absoluteOutputDir with the original name + .pdf
    // So, $outputPdfPath should now exist.
    if (!file_exists($outputPdfPath) || filesize($outputPdfPath) === 0) {
      throw new Exception("Failed to convert Word to PDF: LibreOffice command ran, but the output file '$outputPdfPath' was not created or is empty. Command: " . $process->getCommandLine() . " Error Log: " . $process->getErrorOutput());
    }

    // The method is expected to return the storage path relative to the disk's root,
    // or an identifier that can be used with Storage::disk('pdf').
    // Since $outputPdfPath is an absolute path, we need to return the relative path
    // or just the filename if it's in the root of the 'pdf' disk.
    // Given the original logic: Storage::disk('pdf')->path("$filename.pdf")
    // it implies the file is at the root of the 'pdf' disk.
    return $expectedPdfName;
  }
}
