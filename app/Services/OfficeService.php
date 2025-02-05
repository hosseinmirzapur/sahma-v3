<?php

namespace App\Services;

use App\Models\EntityGroup;
use Exception;
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
            if (empty($entity->meta['csv_location']) ||
                !Storage::disk('csv')
                    ->exists($entity->meta['csv_location'])
            ) {
                throw ValidationException::withMessages(['message' => 'CSV location is not set or does not exist']);
            }

            $beginningOfWindow = $entity->meta['window']['start'] ?? 0;
            $csvFilePath = Storage::disk('csv')->path($entity->meta['csv_location']);
            $entityWindows = FileService::getAudioInfo($csvFilePath);

            foreach ($entityWindows as $key => $text) {
                $voiceWindows[(string)($key + $beginningOfWindow)] = $text;
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
        $outputPdfPath = Storage::disk('pdf')->path("$filename.pdf");

        $process = new Process(['unoconv', '-f', 'pdf', '-o', $outputPdfPath, $wordFilePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception("Failed to convert Word to PDF: " . $process->getErrorOutput());
        }

        return $outputPdfPath;
    }
}
