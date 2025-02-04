<?php

namespace App\Services;

use App\Models\EntityGroup;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

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

      // Replace '\n' with paragraph breaks and add to the section
        $paragraphs = explode("\n", $text);
        $previousIsEmpty = false;
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph); // Remove leading and trailing whitespace
            if (!empty($paragraph)) {
                if ($previousIsEmpty) {
                  // Add a single paragraph break if the previous paragraph was empty
                    $section->addTextBreak(1, ['alignment' => Jc::BOTH]);
                }
                $section->addText($paragraph, ['alignment' => Jc::BOTH]);
                $previousIsEmpty = false;
            } else {
                $previousIsEmpty = true;
            }
        }

        $originalFileName = pathinfo(
            $entityGroup->name,
            PATHINFO_FILENAME
        );
        $filename = "$originalFileName.docx";
        $objWriter = IOFactory::createWriter($phpWord);

        $location = '/tmp/' . $filename;
        $objWriter->save($location);
        $nowDate = now()->toDateString();
        $nowTimestamp = now()->timestamp;
        $storageFilePath = "$nowDate/$nowTimestamp-$filename";
        $fileContent = file_get_contents($location);
        if ($fileContent === false) {
            throw new Exception("WORD => Failed to read result file word $entityGroup->id");
        }
        if (Storage::disk('word')->put($storageFilePath, $fileContent) === false) {
            throw new Exception('WORD => Failed to write result file.');
        }
        unlink($location);
        return $storageFilePath;
    }

  /**
   * @throws Exception
   */
    public function generateCsvFileEntity(string $text): string
    {
        $originalFileName = uniqid('split_voice_');
        $originalFileName = trim(str_replace(' ', '', $originalFileName));
        $now = now();
        $fileName = "$now->timestamp-$originalFileName.csv";
        $tmpAddress = "/tmp/$fileName";

        $csvFile = fopen($tmpAddress, "w") or throw new Exception("Unable to create a txt file");
        fwrite($csvFile, $text);
        fclose($csvFile);

        $content = file_get_contents($tmpAddress);
        if ($content === false) {
            throw new Exception('Failed to read file content from disk');
        }
        unlink($tmpAddress);
        $nowDate = now()->toDateString();
        $csvAddress = "$nowDate/$fileName";
        if (Storage::disk('csv')->put($csvAddress, $content) === false) {
            throw new Exception('Failed to write data');
        }

        return $csvAddress;
    }

  /**
   * @throws Exception
   */
    public function generateWindowsEntityGroup(EntityGroup $entityGroup): array
    {
        $entities = $entityGroup->entities;
        $voiceWindows = [];
        foreach ($entities as $entity) {
            if (!isset($entity->meta['csv_location'])) {
                throw ValidationException::withMessages(['message' => 'csv location does not set']);
            }
            $beginningOfWindow = $entity->meta['window']['start'] ?? 0;
            $csvFilePath = Storage::disk('csv')->path($entity->meta['csv_location']);
            $entityWindows = FileService::getAudioInfo($csvFilePath);

            foreach ($entityWindows as $key => $text) {
                $start = strval($key + $beginningOfWindow);
                $voiceWindows[$start] = $text;
            }
        }
        return $voiceWindows;
    }

    public function convertWordFileToPdf(string $wordFilePath): string
    {
        $filename = strval(pathinfo(strval($wordFilePath), PATHINFO_FILENAME));
        $baseDir = strval(pathinfo(strval($wordFilePath), PATHINFO_DIRNAME));

        $wordFilePath = Storage::disk('word')->path($wordFilePath);

      // Prepare the pdftoppm command
        $command = "unoconv -f pdf $wordFilePath";
      // Execute the command
        shell_exec($command);

        $pdfFileLocation = "$baseDir/$filename.pdf";

        Storage::disk('pdf')->put(
            "$baseDir/$filename.pdf",
            strval(Storage::disk('word')->get("$baseDir/$filename.pdf"))
        );

        return $pdfFileLocation;
    }
}
