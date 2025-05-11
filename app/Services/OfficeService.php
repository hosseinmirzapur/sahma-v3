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
    /**
     * Converts a Word file (from 'word' disk) to PDF (to 'pdf' disk).
     *
     * @param string $wordDiskRelativePath The path to the word file, relative to the 'word' disk's root.
     *                                     Example: "2025-05-11/1746960518-Ashoora_2.docx"
     * @return string The path to the created PDF file, relative to the 'pdf' disk's root.
     *                Example: "1746960518-Ashoora_2.pdf" (if output is to the root of 'pdf' disk)
     * @throws Exception
     */
    public function convertWordFileToPdf(string $wordDiskRelativePath): string
    {
        // 1. Validate and get absolute path to the source Word file
        if (!Storage::disk('word')->exists($wordDiskRelativePath)) {
            throw new Exception("Source Word file does not exist on 'word' disk: " . $wordDiskRelativePath);
        }
        $absoluteWordFilePath = Storage::disk('word')->path($wordDiskRelativePath);

        // Check if the file is readable by the PHP process itself (good first check)
        if (!is_readable($absoluteWordFilePath)) {
            throw new Exception("Source Word file is not readable by the PHP process: " . $absoluteWordFilePath . ". Check file permissions (owner/group/other read bits).");
        }

        // 2. Determine output directory and expected PDF filename
        $sourceFileBasename = pathinfo($absoluteWordFilePath, PATHINFO_BASENAME); // "1746960518-Ashoora_2.docx"
        $expectedPdfFilename = pathinfo($sourceFileBasename, PATHINFO_FILENAME) . ".pdf"; // "1746960518-Ashoora_2.pdf"

        // PDFs will be placed in the root of the 'pdf' disk.
        // LibreOffice's --outdir needs an absolute directory path.
        $absoluteOutputDir = Storage::disk('pdf')->path(''); // Gets the absolute root path of the 'pdf' disk.

        // Ensure the absolute output directory exists and is writable
        if (!is_dir($absoluteOutputDir)) {
            if (!mkdir($absoluteOutputDir, 0775, true) && !is_dir($absoluteOutputDir)) {
                throw new Exception("Failed to create output directory for PDF: " . $absoluteOutputDir);
            }
            // Ensure correct ownership if needed, e.g., chown($absoluteOutputDir, 'www-data:www-data');
        }
        if (!is_writable($absoluteOutputDir)) {
            throw new Exception("Output directory for PDF is not writable by the PHP process: " . $absoluteOutputDir . ". Check permissions.");
        }

        // 3. Prepare and execute LibreOffice command
        $libreofficePath = Config::get('services.libreoffice.path', 'libreoffice');
        $env = [
            'HOME' => sys_get_temp_dir(), // Crucial for LibreOffice to find/create a user profile
            // You might also need to set 'LD_LIBRARY_PATH' or other locale vars depending on your setup
            // 'LC_ALL' => 'en_US.UTF-8', // Example
        ];

        $process = new Process([
            $libreofficePath,
            '--headless',       // Run without GUI
            '--norestore',      // Don't try to recover previous documents
            '--invisible',      // Often used with headless, can help in some environments
            '--convert-to',
            'pdf',
            '--outdir',
            $absoluteOutputDir,    // Absolute path to the directory where PDF will be saved
            $absoluteWordFilePath  // Absolute path to the source DOCX
        ], null, $env); // null for cwd, then environment variables

        $process->setTimeout(300); // 5 minutes timeout, adjust as needed

        // For debugging, capture the exact command
        $commandLine = $process->getCommandLine();

        $process->run();

        // 4. Check for process execution success
        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            $stdOutput = $process->getOutput(); // Capture standard output too
            $exitCode = $process->getExitCode();

            $fullError = "Failed to convert Word to PDF using LibreOffice." . PHP_EOL .
                "Command: " . $commandLine . PHP_EOL .
                "Exit Code: " . $exitCode . PHP_EOL .
                "Error Output: " . trim($errorOutput) . PHP_EOL .
                "Standard Output: " . trim($stdOutput);

            if (str_contains($errorOutput, 'source file could not be loaded')) {
                $fullError .= PHP_EOL . "CRITICAL HINT: 'source file could not be loaded'. This STRONGLY indicates a PERMISSION ISSUE on the input file '{$absoluteWordFilePath}' or one of its parent directories. The user running LibreOffice (likely your web/queue worker user, e.g., 'www-data') needs READ access to this file and EXECUTE/TRAVERSE access to its parent directories.";
            } elseif (preg_match('/(command not found|No such file or directory)/i', $errorOutput) && str_contains($errorOutput, $libreofficePath)) {
                $fullError .= PHP_EOL . "CRITICAL HINT: The 'libreoffice' command ('{$libreofficePath}') was not found. Please ensure LibreOffice is installed and the path is correct in your 'config/services.php' or .env (LIBREOFFICE_PATH), and it's executable by the PHP process user.";
            }

            throw new Exception($fullError);
        }

        // 5. Verify the output PDF file was actually created by LibreOffice
        // LibreOffice names the output file based on the input filename and places it in $absoluteOutputDir
        $absoluteExpectedPdfPath = rtrim($absoluteOutputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $expectedPdfFilename;

        if (!file_exists($absoluteExpectedPdfPath) || filesize($absoluteExpectedPdfPath) === 0) {
            throw new Exception(
                "LibreOffice command ran successfully, but the output PDF file was not found or is empty." . PHP_EOL .
                "Expected at: " . $absoluteExpectedPdfPath . PHP_EOL .
                "Input Word file: " . $absoluteWordFilePath . PHP_EOL .
                "Command: " . $commandLine . PHP_EOL .
                "Process Error Output (if any from successful run): " . trim($process->getErrorOutput()) . PHP_EOL .
                "Process Standard Output: " . trim($process->getOutput())
            );
        }

        // 6. Return the relative path for the 'pdf' disk (as the PDF is in its root)
        return $expectedPdfFilename;
    }
}
