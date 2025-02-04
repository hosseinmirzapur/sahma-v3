<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\FileDisk;
use App\Helper\AudioHelper;
use App\Jobs\ConvertVoiceToWaveJob;
use App\Jobs\ExtractVoiceFromVideoJob;
use App\Jobs\SubmitFileToOcrJob;
use App\Jobs\SubmitVoiceToSplitterJob;
use App\Models\Activity;
use App\Models\Department;
use App\Models\DepartmentFile;
use App\Models\Entity;
use App\Models\EntityGroup;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Glide\Filesystem\FileNotFoundException;
use Spatie\Image\Exceptions\InvalidManipulation;
use Spatie\Image\Image;
use Spatie\Image\Manipulations;

class FileService
{
    private ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * @param UploadedFile $file
     * @param FileDisk $disk
     * @param User $user
     * @return array<string>
     * @throws Exception
     */
    private function storeFile(UploadedFile $file, FileDisk $disk, User $user): array
    {

        $originalFileName = $file->getClientOriginalName();
        $extension = $file->getExtension();
        $nowDate = now()->toDateString();
        $now = now();
        $hash = hash('sha3-256', $file);
        $fileName = "$hash-$now-$extension";
        $originalFilePath = "/$nowDate";

        $fileLocation = $file->storeAs(
            $originalFilePath,
            $fileName,
            [
                'disk' => $disk->value
            ]
        );

        if ($fileLocation === false) {
            throw new Exception('Failed to store file in storage');
        }
        $capitalizedDisk = Str::upper($disk->value);
        Log::info("$capitalizedDisk => Stored $capitalizedDisk file to disk $disk->value user: #$user->id.");

        return [
            $fileLocation,
            $originalFileName,
            $disk->value
        ];
    }

    private function logger(
        User $user,
        EntityGroup $eg,
        string $type
    ): void {
        $actionTypes = [
            Activity::TYPE_CREATE => 'ایجاد',
            Activity::TYPE_PRINT => 'چاپ',
            Activity::TYPE_DESCRIPTION => 'توضیح',
            Activity::TYPE_UPLOAD => 'بارگذاری',
            Activity::TYPE_DELETE => 'حذف',
            Activity::TYPE_RENAME => 'تغییر نام',
            Activity::TYPE_COPY => 'کپی',
            Activity::TYPE_EDIT => 'ویرایش',
            Activity::TYPE_TRANSCRIPTION => 'رونویسی',
            Activity::TYPE_LOGIN => 'ورود',
            Activity::TYPE_LOGOUT => 'خروج',
            Activity::TYPE_ARCHIVE => 'بایگانی',
            Activity::TYPE_RETRIEVAL => 'بازیابی',
            Activity::TYPE_MOVE => 'جا به جا',
            Activity::TYPE_DOWNLOAD => 'دانلود'
        ];

        $actionType = $actionTypes[$type] ?? 'نامشخص';

        $description = sprintf(
            'کاربر %s با کد پرسنلی %s آیتم %s را %s کرد.',
            $user->name,
            $user->personal_id,
            $eg->name,
            $actionType
        );

        $this->activityService->logUserAction($user, $type, $eg, $description);
    }

    /**
     * @param array $data
     * @param array $departments
     * @param User $user
     * @return EntityGroup
     */
    private function createEntityGroup(array $data, array $departments, User $user): EntityGroup
    {
        return DB::transaction(function () use ($data, $departments, $user) {
            $eg = EntityGroup::createWithSlug($data);
            Department::query()
                ->whereIn('id', $departments)
                ->each(function (Department $department) use ($eg) {
                    DepartmentFile::query()->create([
                        'department_id' => $department->id,
                        'entity_group_id' => $eg->id,
                    ]);
                });
            $type = ActivityType::TYPE_CREATE;
            $this->logger($user, $eg, $type->value);
            return $eg;
        }, 3);
    }

    /**
     * @throws Exception
     */
    public function storePdf(User $user, UploadedFile $pdf, array $departments, int|null $parentFolderId = null): void
    {
        [$fileLocation, $originalName, $disk] = $this->storeFile($pdf, FileDisk::PDF, $user);

        $pdfInfo = new PdfInfoService($pdf);
        $numberOfPages = $pdfInfo->pages;
        $meta = ['number_of_pages' => $numberOfPages];

        $entityGroup = $this->createEntityGroup(
            [
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $originalName,
                'type' => $disk,
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
                'meta' => $meta
            ],
            $departments,
            $user,
        );

        SubmitFileToOcrJob::dispatch($entityGroup, $entityGroup->user);
    }

    /**
     * @throws Exception
     */
    public function storeVoice(
        User $user,
        UploadedFile $voice,
        array $departments,
        int|null $parentFolderId = null
    ): void {
        if ($voice->getMimeType() === 'application/octet-stream') {
            throw ValidationException::withMessages(
                ['message' => 'فایل مورد نظر قایل پردازش نیست لطفا آن را به فرمت m4a تبدیل نمایید.']
            );
        }

        if ($voice->getClientOriginalExtension() === 'm4a' && $voice->getMimeType() === 'video/3gpp') {
            $extension = 'm4a';
        } else {
            $extension = $voice->extension();
        }
        [$fileLocation, $originalName, $disk] = $this->storeFile($voice, FileDisk::VOICE, $user);


        try {
            $duration = AudioHelper::getAudioDurationByFfmpeg(
                Storage::disk('voice')->path($fileLocation),
                $voice->getContent()
            );
        } catch (Exception) {
            throw ValidationException::withMessages(['message' => 'فایل مورد نظر کیفیت مناسب را برای پردازش ندارد']);
        }

        $meta = ['duration' => $duration];

        $entityGroup = $this->createEntityGroup(
            [
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $originalName,
                'type' => $disk,
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_SPLIT,
                'meta' => $meta
            ],
            $departments,
            $user,
        );


        if ($extension != 'wav') {
            Log::info(
                "STT =>
                entityGroup: #$entityGroup->id audio file need to be converted to .wav it's ($extension)
        "
            );
            ConvertVoiceToWaveJob::dispatch($entityGroup);
        } else {
            SubmitVoiceToSplitterJob::dispatch($entityGroup);
        }
    }

    /**
     * @throws Exception
     */
    public function storeImage(
        User $user,
        UploadedFile $image,
        array $departments,
        int|null $parentFolderId = null
    ): void {
        [$fileLocation, $originalName, $disk] = $this->storeFile($image, FileDisk::IMAGE, $user);
        $extension = $image->extension();

        $fileLocationTiffConverted = null;
        if ($extension == 'tif') {
            $fileLocationTiffConverted = $this->convertTifToPng($fileLocation);
        }

        Log::info("OCR => Stored image file to disk image user: #$user->id.");

        $filePath = $fileLocationTiffConverted ?? $fileLocation;
        // Get the width and height of the image using GD
        $imagePath = Storage::disk('image')->path($filePath);
        /** @phpstan-ignore-next-line */
        list($width, $height) = getimagesize($imagePath);

        $meta = [
            'width' => $width,
            'height' => $height
        ];
        if ($fileLocationTiffConverted) {
            $meta['tif_converted_png_location'] = $fileLocationTiffConverted;
        }

        $entityGroup = $this->createEntityGroup(
            [
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $originalName,
                'type' => $disk,
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
                'meta' => $meta
            ],
            $departments,
            $user,
        );

        SubmitFileToOcrJob::dispatch($entityGroup, $entityGroup->user);
    }

    /**
     * @throws Exception
     */
    public function storeVideo(
        User $user,
        UploadedFile $video,
        array $departments,
        int|null $parentFolderId = null
    ): void {
        if ($video->getMimeType() === 'application/octet-stream') {
            throw ValidationException::withMessages(
                ['message' => 'فایل مورد نظر قایل پردازش نیست لطفا آن را به فرمت m4a تبدیل نمایید.']
            );
        }

        [$fileLocation, $originalName, $disk] = $this->storeFile($video, FileDisk::VIDEO, $user);

        $entityGroup = $this->createEntityGroup(
            [
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $originalName,
                'type' => $disk,
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION,
            ],
            $departments,
            $user,
        );
        ExtractVoiceFromVideoJob::dispatch($entityGroup);
    }

    /**
     * @throws Exception
     */
    public function storeWord(
        User $user,
        UploadedFile $word,
        array $departments,
        ?int $parentFolderId = null
    ): void {
        // Store file and get details
        [$fileLocation, $originalName, $disk] = $this->storeFile($word, FileDisk::WORD, $user);

        // Extract file details
        $filenameWithoutExt = pathinfo($fileLocation, PATHINFO_FILENAME);
        $baseDir = pathinfo($fileLocation, PATHINFO_DIRNAME);

        // Create secure temporary files
        $tmpWordPath = tempnam(sys_get_temp_dir(), 'word_');
        $tmpPdfPath = tempnam(sys_get_temp_dir(), 'pdf_');

        try {
            // Fetch word file from storage
            file_put_contents($tmpWordPath ?? '', Storage::disk('word')->get($fileLocation));

            // Convert Word to PDF using unoconv
            $command = escapeshellcmd("unoconv -f pdf $tmpWordPath");
            Log::info("Starting Word to PDF conversion: $command");

            exec($command, $output, $returnVal);

            if ($returnVal !== 0) {
                throw new Exception("Failed to convert Word to PDF. Error Code: $returnVal");
            }

            Log::info("Conversion finished successfully.");

            // Save PDF to storage
            $pdfFileLocation = "$baseDir/$filenameWithoutExt.pdf";
            if (!Storage::disk('pdf')->put($pdfFileLocation, file_get_contents("$tmpWordPath.pdf"))) {
                throw new Exception("Failed to store converted PDF file.");
            }

            // Create entity group
            $entityGroup = $this->createEntityGroup(
                [
                    'user_id' => $user->id,
                    'parent_folder_id' => $parentFolderId,
                    'name' => $originalName,
                    'type' => $disk,
                    'file_location' => $fileLocation,
                    'status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
                    'result_location' => ['converted_word_to_pdf' => $pdfFileLocation],
                ],
                $departments,
                $user,
            );

            // Dispatch OCR processing job
            SubmitFileToOcrJob::dispatch($entityGroup, $user);
        } finally {
            // Ensure temporary files are deleted
            unlink($tmpWordPath);
            unlink($tmpPdfPath);
        }
    }


    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function handleUploadedFile(Request $request, ?int $folderId = null): void
    {
        // Flatten all allowed MIME types from config
        $mimeTypes = array_merge_recursive(...array_values(config('mime-type', [])));
        $allowedExtensions = array_keys($mimeTypes);

        // Validate request
        $request->validate([
            'file' => 'required|file|mimes:' . implode(',', $allowedExtensions) . '|max:307200',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $departments = $request->input('tags');
        $extension = $file->extension();

        /** @var User $user */
        $user = $request->user() ?? abort(403, 'دسترسی لازم را ندارید.');

        // Define handlers for different file types
        $handlers = [
            'book' => 'storePdf',
            'voice' => 'storeVoice',
            'image' => 'storeImage',
            'video' => 'storeVideo',
            'office' => 'storeWord',
        ];

        // Find the correct handler based on extension
        foreach ($handlers as $type => $method) {
            if (array_key_exists($extension, (array)config("mime-type.$type", []))) {
                $this->$method($user, $file, $departments, $folderId);
            }
        }

        // If no matching handler is found, throw validation exception
        throw ValidationException::withMessages(['message' => 'فایل مورد نظر پشتیبانی نمیشود.']);
    }


    public static function getAudioInfo(string $path): array
    {
        $csvFile = fopen($path, 'r');

        if (!$csvFile) {
            throw new Exception("Failed to open file: $path");
        }

        $data = [];

        try {
            // Read and process each line
            while (($row = fgetcsv($csvFile, 0, "\t")) !== false) {
                $row = array_map('trim', $row); // Ensure clean data
                if (count($row) < 3) {
                    continue; // Skip malformed rows
                }

                [$start, , $text] = $row;

                if ($start === 'start') {
                    continue;
                }

                $startInSeconds = (float) $start / 1000;
                $data[(string) $startInSeconds] = $text;
            }
        } finally {
            fclose($csvFile); // Ensure file is closed
        }

        return $data;
    }


    /**
     * @throws InvalidManipulation
     * @throws FileNotFoundException
     * @throws InvalidManipulation
     */
    public static function setWaterMarkToImage(string $imagePath): void
    {
        $image = Image::load($imagePath);
        $image->watermark(public_path('images/irapardaz-logo.png'))
            ->watermarkPosition(Manipulations::POSITION_CENTER)
            ->watermarkOpacity(15)
            ->watermarkHeight(50, Manipulations::UNIT_PERCENT)
            ->watermarkWidth(70, Manipulations::UNIT_PERCENT);
        $image->save();
    }

    /**
     * Converts a PDF to images, applies watermark, and then converts images back to PDF.
     *
     * @throws Exception
     */
    public static function convertPdfToImage(EntityGroup $entityGroup): string
    {
        // Define paths
        $originalPdfFilePath = Storage::disk('pdf')->path($entityGroup->file_location);
        $convertedImagesDirRelative =
            dirname($entityGroup->file_location) . '/pdf-converted-images-' . $entityGroup->id;
        Storage::disk('image')->makeDirectory($convertedImagesDirRelative);
        $convertedImagesDir = Storage::disk('image')->path($convertedImagesDirRelative);

        // Convert PDF to images using pdftoppm
        $command = sprintf(
            'pdftoppm -png %s %s/converted 2>&1',
            escapeshellarg($originalPdfFilePath),
            escapeshellarg($convertedImagesDir)
        );

        Log::info("Running command: $command");
        exec($command, $output, $returnVal);
        Log::info("Conversion finished with returnVal => " . $returnVal);

        if ($returnVal !== 0) {
            throw new Exception("pdftoppm failed with return code $returnVal.");
        }

        // Retrieve generated images
        $imageFiles = glob("$convertedImagesDir/*.png");

        if (empty($imageFiles)) {
            throw ValidationException::withMessages(['message' => 'No images were generated from the PDF.']);
        }

        // Apply watermark to each image
        foreach ($imageFiles as $imageFile) {
            Log::info("Applying watermark to $imageFile");
            self::setWaterMarkToImage($imageFile);
        }

        $command = sprintf(
            'img2pdf %s -o %s',
            escapeshellarg(implode(' ', $imageFiles)),
            escapeshellarg($originalPdfFilePath)
        );

        Log::info("Running command: $command");
        exec($command);
        Log::info("Image to PDF conversion completed.");

        // Cleanup: Remove the images directory
        Storage::disk('image')->deleteDirectory($convertedImagesDirRelative);

        return $originalPdfFilePath;
    }


    /**
     *
     * @throws Exception
     */
    public static function addWaterMarkToPdf(EntityGroup $entityGroup, string $searchablePdfFile): string
    {
        $originalPdfFilePath = Storage::disk('pdf')->path($searchablePdfFile);
        $convertedImagesDirRelative =
            dirname($entityGroup->file_location) . '/pdf-watermarked-images-' . $entityGroup->id;
        Storage::disk('image')->makeDirectory($convertedImagesDirRelative);
        $convertedImagesDir = Storage::disk('image')->path($convertedImagesDirRelative);

        // Run pdftoppm command to convert PDF pages to images (PNG)
        $command =
            'pdftoppm -png ' . escapeshellarg(
                $originalPdfFilePath
            ) . ' ' . escapeshellarg(
                $convertedImagesDir . '/converted'
            ) . ' 2>&1';
        Log::info("Starting extraction of images from PDF: $command");
        exec($command, $output, $returnVal);

        Log::info("Extract pages of PDF finished with returnVal: $returnVal");

        if ($returnVal !== 0) {
            throw new Exception("Error during pdftoppm execution. Return value: $returnVal.");
        }

        // Retrieve and watermark all PNG images
        $imageFiles = array_filter(scandir($convertedImagesDir), function ($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'png';
        });

        if (empty($imageFiles)) {
            throw ValidationException::withMessages(['message' => 'No images were generated from the PDF.']);
        }

        // Watermark each image
        foreach ($imageFiles as $file) {
            $filePath = "$convertedImagesDir/$file";
            Log::info("Applying watermark to image: $filePath");
            self::setWaterMarkToImage($filePath);
        }

        // Convert the watermarked images back into a single PDF
        $watermarkedPdfDirRelative =
            dirname(
                $entityGroup->file_location
            ) . '/pdf-watermarked-' . $entityGroup->id . '-' . now()->timestamp . '.pdf';
        $watermarkedPdfPath = Storage::disk('pdf')->path($watermarkedPdfDirRelative);

        $command =
            "img2pdf " . escapeshellarg(
                $convertedImagesDir . '/*.png'
            ) . " -o " . escapeshellarg($watermarkedPdfPath);
        Log::info("Converting images back to PDF: $command");
        shell_exec($command);
        Log::info("Image to PDF conversion completed.");

        // Clean up temporary image files
        Storage::disk('image')->deleteDirectory($convertedImagesDirRelative);

        return $watermarkedPdfDirRelative;
    }


    /**
     * Converts a TIFF file to PNG using ImageMagick's convert command.
     *
     * @throws Exception
     */
    public function convertTifToPng(string $tifFilePathFromDisk): string
    {
        // Get the file path from storage path
        $tifFilePathFromRoot = storage_path('app/image/' . $tifFilePathFromDisk);

        // Get file name and generate a unique PNG file name
        $fileName = pathinfo($tifFilePathFromRoot, PATHINFO_FILENAME);
        $pngFilePathFromDisk = dirname($tifFilePathFromDisk) . '/' . uniqid('tiff-converted-') . '.png';
        $pngFilePathFromRoot = storage_path('app/image/' . $pngFilePathFromDisk);

        // Ensure the directory for the converted file exists
        $pngDir = dirname($pngFilePathFromRoot);
        if (!is_dir($pngDir)) {
            if (!mkdir($pngDir, 0755, true)) {
                throw new Exception("Failed to create the directory for converted PNG: $pngDir");
            }
        }

        // Escape paths to avoid shell injection vulnerabilities
        $escapedTifFilePathFromRoot = escapeshellarg($tifFilePathFromRoot);
        $escapedPngFilePathFromRoot = escapeshellarg($pngFilePathFromRoot);

        // ImageMagick convert command to convert TIFF to PNG
        $command = "convert $escapedTifFilePathFromRoot $escapedPngFilePathFromRoot";
        Log::info("Running command to convert TIFF to PNG: $command");

        // Execute the command and capture output for debugging
        $output = null;
        $returnVal = null;
        exec($command, $output, $returnVal);

        // Log output and handle possible errors
        if ($returnVal !== 0) {
            Log::error("Failed to convert TIFF to PNG. Output: " . implode("\n", $output));
            throw new Exception("Error converting TIFF to PNG. Command returned: $returnVal");
        }

        Log::info("TIFF to PNG conversion successful.");

        // Return the relative path to the PNG file
        return $pngFilePathFromDisk;
    }


    public function deleteEntitiesOfEntityGroup(EntityGroup $entityGroup): void
    {
        $entities = $entityGroup->entities;

        foreach ($entities as $entity) {
            $this->deleteEntityFiles($entity);
            $entity->delete();
        }

        $this->deleteEntityGroupFiles($entityGroup);
    }

    /**
     * Delete files associated with an entity.
     */
    protected function deleteEntityFiles(Entity $entity): void
    {
        // Delete CSV file if it exists
        $csvLocation = $entity->meta['csv_location'] ?? null;
        if ($csvLocation) {
            Storage::disk('csv')->delete($csvLocation);
        }

        // Delete voice file if it exists
        if ($entity->file_location) {
            Storage::disk('voice')->delete($entity->file_location);
        }
    }

    /**
     * Delete files related to the entity group.
     */
    protected function deleteEntityGroupFiles(EntityGroup $entityGroup): void
    {
        // Delete word location file if it exists
        $wordLocation = $entityGroup->result_location['word_location'] ?? null;
        if ($wordLocation) {
            Storage::disk('word')->delete($wordLocation);
        }
    }

    /**
     * Delete an entity group, its entities, and associated files.
     */
    public function deleteEntityGroupAndEntitiesAndFiles(EntityGroup $entityGroup, User $user): void
    {
        DB::transaction(function () use ($entityGroup) {
            // Lock the entity group to avoid race conditions
            $entityGroup = EntityGroup::query()->where('id', $entityGroup->id)->lockForUpdate()->firstOrFail();

            // Delete department files
            $this->deleteDepartmentFiles($entityGroup);

            // Delete entity files and the entities themselves
            foreach ($entityGroup->entities as $entity) {
                $this->deleteEntityFiles($entity);
                $entity->delete();
            }

            // Delete entity group files
            $this->deleteEntityGroupFiles($entityGroup);

            // Delete the entity group
            $entityGroup->delete();
        }, 3);
    }

    /**
     * Delete department files associated with the entity group.
     */
    protected function deleteDepartmentFiles(EntityGroup $entityGroup): void
    {
        $departmentFiles = DepartmentFile::query()->where('entity_group_id', $entityGroup->id)->get();
        foreach ($departmentFiles as $departmentFile) {
            $departmentFile->delete();
        }
    }
}
