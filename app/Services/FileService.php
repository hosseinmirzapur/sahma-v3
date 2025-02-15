<?php

namespace App\Services;

use App\Helper\AudioHelper;
use App\Helper\ConfigHelper;
use App\Jobs\ConvertVoiceToWaveJob;
use App\Jobs\ExtractVoiceFromVideoJob;
use App\Jobs\SubmitFileToOcrJob;
use App\Jobs\SubmitVoiceToSplitterJob;
use App\Models\Activity;
use App\Models\DepartmentFile;
use App\Models\Entity;
use App\Models\EntityGroup;
use App\Models\User;
use Directory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * @throws Exception
     */
    public function storePdf(User $user, UploadedFile $pdf, array $departments, int|null $parentFolderId = null): void
    {
        $bookOriginalFileName = $pdf->getClientOriginalName();

        $extension = $pdf->extension();

        $nowDate = now()->toDateString();
        $now = now()->timestamp;
        $hash = hash('sha3-256', $pdf);
        $fileName = "$hash-$now.$extension";
        $originalPdfPath = "/$nowDate";

        $fileLocation = $pdf->storeAs(
            $originalPdfPath,
            $fileName,
            [
                'disk' => 'pdf'
            ]
        );
        if ($fileLocation === false) {
            throw new Exception('Failed to store file in storage');
        }

        Log::info("PDF => Stored PDF file to disk pdf user: #$user->id.");

        $pdfInfo = new PdfInfoService($pdf);
        $numberOfPages = $pdfInfo->pages;
        $meta = ['number_of_pages' => $numberOfPages];

        /* @var EntityGroup $entityGroup */
        $entityGroup = DB::transaction(function () use (
            $fileLocation,
            $parentFolderId,
            $user,
            $departments,
            $bookOriginalFileName,
            $meta
        ) {
            $entityGroup = EntityGroup::createWithSlug([
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $bookOriginalFileName,
                'type' => 'pdf',
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
                'meta' => $meta
            ]);
            $departmentFileData = collect($departments)->map(function ($departmentId) use ($entityGroup) {
                return [
                    'entity_group_id' => $entityGroup->id,
                    'department_id' => $departmentId,
                ];
            })->toArray();
            DepartmentFile::query()->insert($departmentFileData);

            $description = " کاربر $user->name";
            $description .= " با کد پرسنلی $user->personal_id";
            $description .= 'فایل ' . $entityGroup->name . ' ';
            $description .= "بارگزاری کرد.";

            $this->activityService->logUserAction($user, Activity::TYPE_UPLOAD, $entityGroup, $description);

            return $entityGroup;
        });
        if (ConfigHelper::isAiServiceManual()) {
            return;
        }
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
        $voiceOriginalFileName = $voice->getClientOriginalName();

        if ($voice->getClientOriginalExtension() === 'm4a' && $voice->getMimeType() === 'video/3gpp') {
            $extension = 'm4a';
        } else {
            $extension = $voice->extension();
        }
        $nowDate = now()->toDateString();
        $now = now()->timestamp;
        $hash = hash('sha3-256', $voice);
        $fileName = "$hash-$now.$extension";
        $originalPdfPath = "/$nowDate";

        $fileLocation = $voice->storeAs(
            $originalPdfPath,
            $fileName,
            [
                'disk' => 'voice'
            ]
        );
        if ($fileLocation === false) {
            throw new Exception('Failed to store file in storage');
        }

        Log::info("VOICE => Stored voice file to disk voice user: #$user->id.");

        try {
            $duration = AudioHelper::getAudioDurationByFfmpeg(
                Storage::disk('voice')->path($fileLocation),
                $voice->getContent()
            );
        } catch (Exception) {
            throw ValidationException::withMessages(['message' => 'فایل مورد نظر کیفیت مناسب را برای پردازش ندارد']);
        }

        $meta = ['duration' => $duration];

        /* @var EntityGroup $entityGroup */
        $entityGroup = DB::transaction(function () use (
            $fileLocation,
            $parentFolderId,
            $user,
            $departments,
            $voiceOriginalFileName,
            $meta
        ) {
            $entityGroup = EntityGroup::createWithSlug([
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $voiceOriginalFileName,
                'type' => 'voice',
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_SPLIT,
                'meta' => $meta
            ]);

            $departmentFileData = collect($departments)->map(function ($departmentId) use ($entityGroup) {
                return [
                    'entity_group_id' => $entityGroup->id,
                    'department_id' => $departmentId,
                ];
            })->toArray();

            DepartmentFile::query()->insert($departmentFileData);

            $description = " کاربر $user->name";
            $description .= " با کد پرسنلی $user->personal_id";
            $description .= 'فایل ' . $entityGroup->name . ' ';
            $description .= "بارگزاری کرد.";

            $this->activityService->logUserAction($user, Activity::TYPE_UPLOAD, $entityGroup, $description);

            return $entityGroup;
        }, 3);

        if (ConfigHelper::isAiServiceManual()) {
            return;
        }

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
        $imageOriginalFileName = $image->getClientOriginalName();

        $extension = $image->extension();

        $nowDate = now()->toDateString();
        $now = now()->timestamp;
        $hash = hash('sha3-256', $image);
        $fileName = "$hash-$now.$extension";
        $originalPdfPath = "/$nowDate";

        $fileLocation = $image->storeAs(
            $originalPdfPath,
            $fileName,
            [
                'disk' => 'image'
            ]
        );
        if ($fileLocation === false) {
            throw new Exception('Failed to store file in storage');
        }
        $fileLocationTiffConverted = null;
        if ($extension == 'tif') {
            $fileLocationTiffConverted = $this->convertTifToPng($fileLocation);
        }

        Log::info("OCR => Stored image file to disk image user: #$user->id.");

        if ($fileLocationTiffConverted) {
            $filePath = $fileLocationTiffConverted;
        } else {
            $filePath = $fileLocation;
        }
        // Get the width and height of the image using GD
        $imagePath = Storage::disk('image')->path(strval($filePath));
        /** @phpstan-ignore-next-line */
        list($width, $height) = getimagesize($imagePath);

        $meta = [
            'width' => $width,
            'height' => $height
        ];
        if ($fileLocationTiffConverted) {
            $meta['tif_converted_png_location'] = $fileLocationTiffConverted;
        }

        /* @var EntityGroup $entityGroup */
        $entityGroup = DB::transaction(function () use (
            $fileLocation,
            $parentFolderId,
            $user,
            $departments,
            $imageOriginalFileName,
            $meta
        ) {
            $entityGroup = EntityGroup::createWithSlug([
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $imageOriginalFileName,
                'type' => 'image',
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
                'meta' => $meta
            ]);
            $departmentFileData = collect($departments)->map(function ($departmentId) use ($entityGroup) {
                return [
                    'entity_group_id' => $entityGroup->id,
                    'department_id' => $departmentId,
                ];
            })->toArray();
            DepartmentFile::query()->insert($departmentFileData);

            $description = " کاربر $user->name";
            $description .= " با کد پرسنلی $user->personal_id";
            $description .= 'فایل ' . $entityGroup->name . ' ';
            $description .= "بارگزاری کرد.";

            $this->activityService->logUserAction($user, Activity::TYPE_UPLOAD, $entityGroup, $description);

            return $entityGroup;
        }, 3);

        if (ConfigHelper::isAiServiceManual()) {
            return;
        }

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
        };
        $videoOriginalFileName = $video->getClientOriginalName();

        $extension = $video->extension();

        $nowDate = now()->toDateString();
        $now = now()->timestamp;
        $hash = hash('sha3-256', $video);
        $fileName = "$hash-$now.$extension";
        $originalPdfPath = "/$nowDate";

        $fileLocation = $video->storeAs(
            $originalPdfPath,
            $fileName,
            [
                'disk' => 'video'
            ]
        );
        if ($fileLocation === false) {
            throw new Exception('Failed to store file in storage');
        }

        Log::info("VTT => Stored video file to disk video user: #$user->id.");

        /* @var EntityGroup $entityGroup */
        $entityGroup = DB::transaction(function () use (
            $fileLocation,
            $parentFolderId,
            $user,
            $departments,
            $videoOriginalFileName
        ) {
            $entityGroup = EntityGroup::createWithSlug([
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $videoOriginalFileName,
                'type' => 'video',
                'file_location' => $fileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION,
            ]);
            $departmentFileData = collect($departments)->map(function ($departmentId) use ($entityGroup) {
                return [
                    'entity_group_id' => $entityGroup->id,
                    'department_id' => $departmentId,
                ];
            })->toArray();
            DepartmentFile::query()->insert($departmentFileData);

            $description = " کاربر $user->name";
            $description .= " با کد پرسنلی $user->personal_id";
            $description .= 'فایل ' . $entityGroup->name . ' ';
            $description .= "بارگزاری کرد.";

            $this->activityService->logUserAction($user, Activity::TYPE_UPLOAD, $entityGroup, $description);

            return $entityGroup;
        }, 3);

        if (ConfigHelper::isAiServiceManual()) {
            return;
        }

        ExtractVoiceFromVideoJob::dispatch($entityGroup);
    }

    /**
     * @throws Exception
     */
    public function storeWord(
        User $user,
        UploadedFile $word,
        array $departments,
        int|null $parentFolderId = null
    ): void {
        $wordOriginalFileName = $word->getClientOriginalName();

        $extension = $word->extension();

        $nowDate = now()->toDateString();
        $now = now()->timestamp;
        $hash = hash('sha3-256', $word);
        $fileName = "$hash-$now.$extension";
        $originalFilePath = "$nowDate";

        $wordFileLocation = $word->storeAs(
            $originalFilePath,
            $fileName,
            [
                'disk' => 'word'
            ]
        );

        $filenameOriginalWord = strval(pathinfo(strval($wordFileLocation), PATHINFO_FILENAME));
        $baseNameOriginalWord = strval(pathinfo(strval($wordFileLocation), PATHINFO_BASENAME));
        $baseDirOriginalWord = strval(pathinfo(strval($wordFileLocation), PATHINFO_DIRNAME));

        if ($wordFileLocation === false) {
            throw new Exception('Failed to store file in storage');
        }

        Log::info("WORD => Stored word file to disk word user: #$user->id.");

        $tmpFilePath = "/tmp/$baseNameOriginalWord";
        $tempPdfFilePath = "/tmp/$filenameOriginalWord.pdf";

        file_put_contents("/tmp/$baseNameOriginalWord", strval(
            Storage::disk('word')->get($wordFileLocation)
        ));

        $command = "unoconv -f pdf $tmpFilePath";
        $output = null;
        $returnVal = null;
        Log::info("Starting convert word to pdf!");
        exec($command, $output, $returnVal);
        Log::info($command);
        Log::info(
            "converting finished with returnVal=>" . $returnVal
        );

        $pdfFileLocation = "$baseDirOriginalWord/$filenameOriginalWord.pdf";
        if (!Storage::disk('pdf')->put($pdfFileLocation, strval(file_get_contents($tempPdfFilePath)))) {
            throw new Exception("Failed to put converted word file.");
        }

        unlink($tmpFilePath);

        /* @var EntityGroup $entityGroup */
        $entityGroup = DB::transaction(function () use (
            $wordFileLocation,
            $pdfFileLocation,
            $parentFolderId,
            $user,
            $departments,
            $wordOriginalFileName
        ) {
            $result ['converted_word_to_pdf'] = $pdfFileLocation;

            $entityGroup = EntityGroup::createWithSlug([
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolderId,
                'name' => $wordOriginalFileName,
                'type' => 'word',
                'file_location' => $wordFileLocation,
                'status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
                'result_location' => $result
            ]);

            $departmentFileData = collect($departments)->map(function ($departmentId) use ($entityGroup) {
                return [
                    'entity_group_id' => $entityGroup->id,
                    'department_id' => $departmentId,
                ];
            })->toArray();
            DepartmentFile::query()->insert($departmentFileData);

            return $entityGroup;
        }, 3);

        if (ConfigHelper::isAiServiceManual()) {
            return;
        }

        SubmitFileToOcrJob::dispatch($entityGroup, $entityGroup->user);
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function handleUploadedFile(Request $request, int $folderId = null): void
    {
        $mimeTypes = [];
        foreach ((array)config('mime-type') as $mime) {
            $mimeTypes = array_merge($mimeTypes, array_keys((array)$mime));
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $departments = (array)$request->input('tags');
        $extension = $file->extension();
        $request->validate([
            'file' => 'required|file|mimes:' . implode(',', $mimeTypes) . '|max:307200',
            'tags' => 'required|array|min:1',
            'tags.*' => 'string',
        ]);

        /* @var User $user */
        $user = $request->user();
        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        if (in_array($extension, array_keys((array)config('mime-type.book')))) {
            $this->storePdf($user, $file, $departments, $folderId);
        } elseif (in_array($extension, array_keys((array)config('mime-type.voice')))) {
            $this->storeVoice($user, $file, $departments, $folderId);
        } elseif (in_array($extension, array_keys((array)config('mime-type.image')))) {
            $this->storeImage($user, $file, $departments, $folderId);
        } elseif (in_array($extension, array_keys((array)config('mime-type.video')))) {
            $this->storeVideo($user, $file, $departments, $folderId);
        } elseif (in_array($extension, array_keys((array)config('mime-type.office')))) {
            $this->storeWord($user, $file, $departments, $folderId);
        } else {
            throw ValidationException::withMessages(['message' => 'فایل مورد نظر پشتیبانی نمیشود.']);
        }
    }

    public static function getAudioInfo(string $path): array
    {
        $csvFile = fopen($path, 'r');

        // Initialize an empty array to store the data
        $data = [];

        // Read each line from the CSV file and parse it into an array
        /** @phpstan-ignore-next-line */
        while (($row = fgetcsv($csvFile, 0, "\t")) !== false) {
            // $row is now an array containing values from the CSV line
            $start = $row[0];
            $end = $row[1];
            $text = $row[2];

            if ($start == 'start') {
                continue;
            }
            $startInSeconds = $start / 1000;

            $data[strval($startInSeconds)] = $text;
        }


        // Close the file pointer
        /** @phpstan-ignore-next-line */
        fclose($csvFile);
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
     * @throws Exception
     */
    public static function convertPdfToImage(EntityGroup $entityGroup): string
    {
        $originalPdfFilePath = Storage::disk('pdf')->path($entityGroup->file_location);
        $convertedImagesDirAbsolute = dirname($entityGroup->file_location) .
            '/pdf-converted-images-' . $entityGroup->id;
        Storage::disk('image')->makeDirectory($convertedImagesDirAbsolute);
        $convertedImagesDir = Storage::disk('image')->path($convertedImagesDirAbsolute);
        // Run command
        $command = 'pdftoppm -png "' . $originalPdfFilePath . '" "' . $convertedImagesDir . '/converted"' . ' 2>&1';
        Log::info($command);
        $output = null;
        $returnVal = null;
        Log::info("ITT => Starting extract images of pdf");
        exec($command, $output, $returnVal);
        Log::info("ITT => Extract pages of pdf finished with returnVal=>" . $returnVal);

        if ($returnVal != 0) {
            throw new Exception("ITT => Return value of pdftoppm is $returnVal.");
        }

        // Check if job has been done successfully!
        $pics = [];
        // Get all pics
        /** @var Directory $dir */
        $dir = dir($convertedImagesDir);
        while (($file = $dir->read()) !== false) {
            $explodedFilename = explode(".", $file);
            if ($file != '.' && $file != '..' && end($explodedFilename) == 'png') {
                Log::info("ITT => Starting watermak image");
                self::setWaterMarkToImage("$convertedImagesDir/$file");
                Log::info("ITT => watermark finished.");
                $pics[] = $file;
            }
        }
        sort($pics);

        // List all PNG files in the directory
        $imageFiles = glob($convertedImagesDir . '/*.png');
        Log::info($convertedImagesDir);
        if (empty($imageFiles)) {
            throw ValidationException::withMessages(['message' => 'no images exists']);
        }

        // Prepare the pdftoppm command
        $command = "img2pdf $convertedImagesDir/*.png -o ";
        $command .= '"' . $originalPdfFilePath . '"';
        Log::info($command);
        // Execute the command
        shell_exec($command);
        Log::info("ITT => convert image to pdf done.");

        Storage::disk('image')->deleteDirectory($convertedImagesDirAbsolute);

        return $originalPdfFilePath;
    }

    /**
     * @throws Exception
     */
    public static function addWaterMarkToPdf(EntityGroup $entityGroup, string $searchablePdfFile): string
    {
        $originalPdfFilePath = Storage::disk('pdf')->path($searchablePdfFile);
        $convertedImagesDirAbsolute = dirname($entityGroup->file_location) .
            '/pdf-watermarked-images-' . $entityGroup->id;
        Storage::disk('image')->makeDirectory($convertedImagesDirAbsolute);
        $convertedImagesDir = Storage::disk('image')->path($convertedImagesDirAbsolute);
        // Run command
        $command = 'pdftoppm -png "' . $originalPdfFilePath . '" "' . $convertedImagesDir . '/converted"' . ' 2>&1';
        Log::info($command);
        $output = null;
        $returnVal = null;
        Log::info("ITT => Starting extract images of pdf");
        exec($command, $output, $returnVal);
        Log::info("ITT => Extract pages of pdf finished with returnVal=>" . $returnVal);

        if ($returnVal != 0) {
            throw new Exception("ITT => Return value of pdftoppm is $returnVal.");
        }

        // Check if job has been done successfully!
        $pics = [];
        // Get all pics
        /** @var Directory $dir */
        $dir = dir($convertedImagesDir);
        while (($file = $dir->read()) !== false) {
            $explodedFilename = explode(".", $file);
            if ($file != '.' && $file != '..' && end($explodedFilename) == 'png') {
                Log::info("ITT => Starting watermark image");
                self::setWaterMarkToImage("$convertedImagesDir/$file");
                Log::info("ITT => watermark finished.");
                $pics[] = $file;
            }
        }
        sort($pics);

        // List all PNG files in the directory
        $imageFiles = glob($convertedImagesDir . '/*.png');
        Log::info($convertedImagesDir);
        if (empty($imageFiles)) {
            throw ValidationException::withMessages(['message' => 'no images exists']);
        }

        $convertedWatermarkedImagesDirAbsolute = dirname($entityGroup->file_location) .
            '/pdf-watermarked-' . $entityGroup->id . '-' . now()->timestamp . '.pdf';

        $watermarkPdfPath = Storage::disk('pdf')->path($convertedWatermarkedImagesDirAbsolute);

        // Prepare the pdftoppm command
        $command = "img2pdf $convertedImagesDir/*.png -o ";
        $command .= '"' . $watermarkPdfPath . '"';
        Log::info($command);
        // Execute the command
        shell_exec($command);
        Log::info("ITT => convert image to pdf done.");

        Storage::disk('image')->deleteDirectory($convertedImagesDirAbsolute);

        return $convertedWatermarkedImagesDirAbsolute;
    }

    public function convertTifToPng(string $tifFilePathFromDisk): string
    {
        $tifFilePathFromRoot = storage_path('app/image/' . $tifFilePathFromDisk);
        $fileName = pathinfo($tifFilePathFromRoot, PATHINFO_FILENAME);
        $pngFilePathFromDisk = dirname($tifFilePathFromDisk) . '/' . uniqid('tiff-converted-') . '.png';
        $pngFilePathFromRoot = storage_path('app/image/' . $pngFilePathFromDisk);
        $command = "convert $tifFilePathFromRoot $pngFilePathFromRoot";
        Log::info($command);
        // Execute the command
        shell_exec($command);
        Log::info("ITT => convert tif to image done.");

        return $pngFilePathFromDisk;
    }

    public function deleteEntitiesOfEntityGroup(EntityGroup $entityGroup): void
    {
        $entities = $entityGroup->entities;
        /* @var Entity $entity */
        foreach ($entities as $entity) {
            Storage::disk('csv')->delete($entity->meta['csv_location'] ?? '');
            Storage::disk('voice')->delete($entity->file_location);
            $entity->delete();
        }
        Storage::disk('word')->delete($entityGroup->result_location['word_location'] ?? '');
    }

    public function deleteEntityGroupAndEntitiesAndFiles(EntityGroup $entityGroup, User $user): void
    {
        /** @phpstan-ignore-next-line */
        DB::transaction(function () use ($user, $entityGroup) {
            $entityGroup = EntityGroup::query()->where('id', $entityGroup->id)->lockForUpdate()->firstOrFail();
            DepartmentFile::query()->where('entity_group_id', $entityGroup->id)->delete();
            $entities = $entityGroup->entities();
            foreach ($entities->get() as $entity) {
                Storage::disk('csv')->delete($entity->meta['csv_location'] ?? '');
                Storage::disk('voice')->delete($entity->file_location);
            }
            $entities->delete();
            Storage::disk($entityGroup->type)->delete($entityGroup->file_location);
            if (in_array($entityGroup->type, ['pdf', 'image'])) {
                Storage::disk('pdf')->delete($entityGroup->result_location['pdf_location'] ?? '');
            }
            Storage::disk('word')->delete($entityGroup->result_location['word_location'] ?? '');
            $entityGroup->delete();
        }, 3);
    }
}
