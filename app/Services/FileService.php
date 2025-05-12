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
    $entityGroup = DB::transaction(function () use ($fileLocation, $parentFolderId, $user, $departments, $bookOriginalFileName, $meta) {
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
  }

  /**
   * @throws Exception
   */
  public function storeSpreadsheet(
    User $user,
    UploadedFile $spreadsheet,
    array $departments,
    int|null $parentFolderId = null
  ): void {
    $spreadsheetOriginalFileName = $spreadsheet->getClientOriginalName();
    $extension = $spreadsheet->getClientOriginalExtension(); // Use client extension

    $nowDate = now()->toDateString();
    $now = now()->timestamp;
    $hash = hash('sha3-256', $spreadsheet->getContent()); // Hash content for uniqueness
    $fileName = "$hash-$now.$extension";
    $filePath = "/$nowDate";

    // Store the uploaded spreadsheet file using the 'excel' disk
    $fileLocation = $spreadsheet->storeAs(
      $filePath,
      $fileName,
      ['disk' => 'excel'] // Use the 'excel' disk
    );

    if ($fileLocation === false) {
      throw new Exception('Failed to store Spreadsheet file in storage.');
    }

    Log::info("SPREADSHEET => Stored spreadsheet file to disk 'excel' for user: #$user->id.");

    // Save file metadata and assign to departments
    /* @var EntityGroup $entityGroup */
    $entityGroup = DB::transaction(function () use ($fileLocation, $parentFolderId, $user, $departments, $spreadsheetOriginalFileName) {
      $entityGroup = EntityGroup::createWithSlug([
        'user_id' => $user->id,
        'parent_folder_id' => $parentFolderId,
        'name' => $spreadsheetOriginalFileName,
        'type' => 'spreadsheet', // New type for Excel files
        'file_location' => $fileLocation,
        'status' => EntityGroup::STATUS_MANUAL_PROCESS_DONE, // Mark as completed, no AI processing needed
        'meta' => [], // No specific meta needed for now
        'result_location' => null // No result location needed
      ]);

      // Insert department relationships
      $departmentFileData = collect($departments)->map(fn($departmentId) => [
        'entity_group_id' => $entityGroup->id,
        'department_id' => $departmentId,
      ])->toArray();

      DepartmentFile::query()->insert($departmentFileData);

      // Log activity
      $description = " کاربر $user->name";
      $description .= " با کد پرسنلی $user->personal_id";
      $description .= 'فایل اکسل ' . $entityGroup->name . ' '; // Adjusted description
      $description .= "بارگزاری کرد.";
      $this->activityService->logUserAction($user, Activity::TYPE_UPLOAD, $entityGroup, $description);


      return $entityGroup;
    }, 3);

    Log::info("EntityGroup (Spreadsheet) created for user #$user->id with ID: " . $entityGroup->id);

    // No AI jobs dispatched for spreadsheets
  }

  /**
   * @throws Exception
   */
  public function storePowerpoint(
    User $user,
    UploadedFile $powerpoint,
    array $departments,
    int|null $parentFolderId = null
  ): void {
    $powerpointOriginalFileName = $powerpoint->getClientOriginalName();
    $extension = $powerpoint->getClientOriginalExtension(); // Use client extension

    $nowDate = now()->toDateString();
    $now = now()->timestamp;
    $hash = hash('sha3-256', $powerpoint->getContent()); // Hash content for uniqueness
    $fileName = "$hash-$now.$extension";
    $filePath = "/$nowDate";

    // Store the uploaded powerpoint file using the 'powerpoint' disk
    $fileLocation = $powerpoint->storeAs(
      $filePath,
      $fileName,
      ['disk' => 'powerpoint'] // Use the 'powerpoint' disk
    );

    if ($fileLocation === false) {
      throw new Exception('Failed to store PowerPoint file in storage.');
    }

    Log::info("POWERPOINT => Stored powerpoint file to disk 'powerpoint' for user: #$user->id.");

    // Save file metadata and assign to departments
    /* @var EntityGroup $entityGroup */
    $entityGroup = DB::transaction(function () use ($fileLocation, $parentFolderId, $user, $departments, $powerpointOriginalFileName) {
      $entityGroup = EntityGroup::createWithSlug([
        'user_id' => $user->id,
        'parent_folder_id' => $parentFolderId,
        'name' => $powerpointOriginalFileName,
        'type' => 'powerpoint', // New type for PowerPoint files
        'file_location' => $fileLocation,
        'status' => EntityGroup::STATUS_MANUAL_PROCESS_DONE, // Mark as completed, no AI processing needed
        'meta' => [], // No specific meta needed for now
        'result_location' => null // No result location needed
      ]);

      // Insert department relationships
      $departmentFileData = collect($departments)->map(fn($departmentId) => [
        'entity_group_id' => $entityGroup->id,
        'department_id' => $departmentId,
      ])->toArray();

      DepartmentFile::query()->insert($departmentFileData);

      // Log activity
      $description = " کاربر $user->name";
      $description .= " با کد پرسنلی $user->personal_id";
      $description .= 'فایل پاورپوینت ' . $entityGroup->name . ' '; // Adjusted description
      $description .= "بارگزاری کرد.";
      $this->activityService->logUserAction($user, Activity::TYPE_UPLOAD, $entityGroup, $description);


      return $entityGroup;
    }, 3);

    Log::info("EntityGroup (PowerPoint) created for user #$user->id with ID: " . $entityGroup->id);

    // No AI jobs dispatched for powerpoints
  }

  /**
   * @throws Exception
   */
  public function storeArchive(
    User $user,
    UploadedFile $archive,
    array $departments,
    int|null $parentFolderId = null
  ): void {
    $archiveOriginalFileName = $archive->getClientOriginalName();
    $extension = $archive->getClientOriginalExtension(); // Use client extension

    $nowDate = now()->toDateString();
    $now = now()->timestamp;
    $hash = hash('sha3-256', $archive->getContent()); // Hash content for uniqueness
    $fileName = "$hash-$now.$extension";
    $filePath = "/$nowDate";

    // Store the uploaded archive file using the 'archive' disk
    $fileLocation = $archive->storeAs(
      $filePath,
      $fileName,
      ['disk' => 'archive'] // Use the 'archive' disk
    );

    if ($fileLocation === false) {
      throw new Exception('Failed to store Archive file in storage.');
    }

    Log::info("ARCHIVE => Stored archive file to disk 'archive' for user: #$user->id.");

    // Save file metadata and assign to departments
    /* @var EntityGroup $entityGroup */
    $entityGroup = DB::transaction(function () use ($fileLocation, $parentFolderId, $user, $departments, $archiveOriginalFileName) {
      $entityGroup = EntityGroup::createWithSlug([
        'user_id' => $user->id,
        'parent_folder_id' => $parentFolderId,
        'name' => $archiveOriginalFileName,
        'type' => 'archive', // New type for Archive files
        'file_location' => $fileLocation,
        'status' => EntityGroup::STATUS_MANUAL_PROCESS_DONE, // Mark as completed, no AI processing needed
        'meta' => [], // No specific meta needed for now
        'result_location' => null // No result location needed
      ]);

      // Insert department relationships
      $departmentFileData = collect($departments)->map(fn($departmentId) => [
        'entity_group_id' => $entityGroup->id,
        'department_id' => $departmentId,
      ])->toArray();

      DepartmentFile::query()->insert($departmentFileData);

      // Log activity
      $description = " کاربر $user->name";
      $description .= " با کد پرسنلی $user->personal_id";
      $description .= 'فایل فشرده ' . $entityGroup->name . ' '; // Adjusted description
      $description .= "بارگزاری کرد.";
      $this->activityService->logUserAction($user, Activity::TYPE_UPLOAD, $entityGroup, $description);


      return $entityGroup;
    }, 3);

    Log::info("EntityGroup (Archive) created for user #$user->id with ID: " . $entityGroup->id);

    // No AI jobs dispatched for archives
  }


  /**
   * @throws ValidationException
   * @throws Exception
   */
  public function storeVoice(
    User $user,
    UploadedFile $voice,
    array $departments,
    int|null $parentFolderId = null
  ): void {
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

    // Validate the stored file using ffprobe
    try {
      AudioHelper::validateAudioFile($fileLocation);
      Log::info("VOICE => File validated successfully using ffprobe: $fileLocation User: #$user->id");
    } catch (ValidationException $e) {
      Log::warning("VOICE => File validation failed using ffprobe: $fileLocation User: #$user->id. Deleting file.");
      Storage::disk('voice')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw the validation exception
    } catch (Exception $e) { // Catch potential exceptions from validateAudioFile
      Log::error("VOICE => Error during ffprobe validation for $fileLocation User: #$user->id. Deleting file. Error: " . $e->getMessage());
      Storage::disk('voice')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw
    }

    Log::info("VOICE => Stored voice file to disk voice user: #$user->id.");

    // Get duration *before* transaction, after validation
    $duration = null;
    try {
      $duration = AudioHelper::getAudioDurationByFfmpeg(Storage::disk('voice')->path($fileLocation));
    } catch (Exception $e) {
      Log::error("VOICE => Failed to get duration for validated file: $fileLocation User: #$user->id. Error: " . $e->getMessage());
      // Decide if this should be a fatal error or just logged. For now, log and continue without duration.
    }

    $meta = ['duration' => $duration]; // Initialize meta with duration (or null)
    /* @var EntityGroup $entityGroup */
    $entityGroup = DB::transaction(function () use ($fileLocation, $parentFolderId, $user, $departments, $voiceOriginalFileName, $meta) {
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
    $entityGroup = DB::transaction(function () use ($fileLocation, $parentFolderId, $user, $departments, $imageOriginalFileName, $meta) {
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
    }
    ;
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

    // Validate the stored file using ffprobe
    try {
      AudioHelper::validateVideoFile($fileLocation); // Using AudioHelper for now
      Log::info("VTT => File validated successfully using ffprobe: $fileLocation User: #$user->id");
    } catch (ValidationException $e) {
      Log::warning("VTT => File validation failed using ffprobe: $fileLocation User: #$user->id. Deleting file.");
      Storage::disk('video')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw the validation exception
    } catch (Exception $e) { // Catch potential exceptions from validateVideoFile
      Log::error("VTT => Error during ffprobe validation for $fileLocation User: #$user->id. Deleting file. Error: " . $e->getMessage());
      Storage::disk('video')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw
    }


    // Validate the stored file using ffprobe
    try {
      AudioHelper::validateVideoFile($fileLocation); // Using AudioHelper for now
      Log::info("VTT => File validated successfully using ffprobe: $fileLocation User: #$user->id");
    } catch (ValidationException $e) {
      Log::warning("VTT => File validation failed using ffprobe: $fileLocation User: #$user->id. Deleting file.");
      Storage::disk('video')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw the validation exception
    } catch (Exception $e) { // Catch potential exceptions from validateVideoFile
      Log::error("VTT => Error during ffprobe validation for $fileLocation User: #$user->id. Deleting file. Error: " . $e->getMessage());
      Storage::disk('video')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw
    }


    // Validate the stored file using ffprobe
    try {
      AudioHelper::validateVideoFile($fileLocation); // Using AudioHelper for now
      Log::info("VTT => File validated successfully using ffprobe: $fileLocation User: #$user->id");
    } catch (ValidationException $e) {
      Log::warning("VTT => File validation failed using ffprobe: $fileLocation User: #$user->id. Deleting file.");
      Storage::disk('video')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw the validation exception
    } catch (Exception $e) { // Catch potential exceptions from validateVideoFile
      Log::error("VTT => Error during ffprobe validation for $fileLocation User: #$user->id. Deleting file. Error: " . $e->getMessage());
      Storage::disk('video')->delete($fileLocation); // Clean up stored file
      throw $e; // Re-throw
    }


    Log::info("VTT => Stored video file to disk video user: #$user->id.");

    /* @var EntityGroup $entityGroup */
    $entityGroup = DB::transaction(function () use ($fileLocation, $parentFolderId, $user, $departments, $videoOriginalFileName) {
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

    // Store the uploaded word file
    $wordFileLocation = $word->storeAs(
      $originalFilePath,
      $fileName,
      ['disk' => 'word']
    );

    if ($wordFileLocation === false) {
      throw new Exception('Failed to store Word file in storage.');
    }

    Log::info("WORD => Stored word file to disk 'word' for user: #$user->id.");

    // Define safe temporary storage paths
    $tmpDir = storage_path('app/tmp');
    if (!file_exists($tmpDir)) {
      mkdir($tmpDir, 0777, true);
    }

    $filenameOriginalWord = pathinfo($wordFileLocation, PATHINFO_FILENAME);
    $baseNameOriginalWord = pathinfo($wordFileLocation, PATHINFO_BASENAME);
    $baseDirOriginalWord = pathinfo($wordFileLocation, PATHINFO_DIRNAME);

    $tmpFilePath = "$tmpDir/$baseNameOriginalWord";
    $tempPdfFilePath = "$tmpDir/$filenameOriginalWord.pdf";

    // Save the Word file to a temporary location
    file_put_contents($tmpFilePath, Storage::disk('word')->get($wordFileLocation));

    // Convert Word file to PDF using unoconv
    $command = "unoconv -f pdf " . escapeshellarg($tmpFilePath);
    $output = [];
    $returnVal = null;

    Log::info("Starting Word to PDF conversion: $command");
    exec($command . ' 2>&1', $output, $returnVal);

    Log::info("unoconv output: " . implode("\n", $output));
    Log::info("unoconv exit code: " . $returnVal);

    // Validate if conversion succeeded
    if ($returnVal !== 0 || !file_exists($tempPdfFilePath)) {
      throw new Exception("Failed to convert Word to PDF. Output: " . implode("\n", $output));
    }

    Log::info("Word to PDF conversion successful.");

    // Define final PDF storage path
    $pdfFileLocation = "$baseDirOriginalWord/$filenameOriginalWord.pdf";

    // Move the converted PDF file to Laravel storage
    if (!Storage::disk('pdf')->put($pdfFileLocation, file_get_contents($tempPdfFilePath))) {
      throw new Exception("Failed to store converted PDF in storage.");
    }

    Log::info("Stored converted PDF file successfully.");

    // Clean up temporary files
    unlink($tmpFilePath);
    unlink($tempPdfFilePath);

    // Save file metadata and assign to departments
    $entityGroup = DB::transaction(function () use ($wordFileLocation, $pdfFileLocation, $parentFolderId, $user, $departments, $wordOriginalFileName) {
      $result['converted_word_to_pdf'] = $pdfFileLocation;

      $entityGroup = EntityGroup::createWithSlug([
        'user_id' => $user->id,
        'parent_folder_id' => $parentFolderId,
        'name' => $wordOriginalFileName,
        'type' => 'word',
        'file_location' => $wordFileLocation,
        'status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
        'result_location' => $result
      ]);

      // Insert department relationships
      $departmentFileData = collect($departments)->map(fn($departmentId) => [
        'entity_group_id' => $entityGroup->id,
        'department_id' => $departmentId,
      ])->toArray();

      DepartmentFile::query()->insert($departmentFileData);

      return $entityGroup;
    }, 3);

    Log::info("EntityGroup created for user #$user->id with ID: " . $entityGroup->id);

    // Dispatch OCR job if AI service is not in manual mode
    if (!ConfigHelper::isAiServiceManual()) {
      SubmitFileToOcrJob::dispatch($entityGroup, $entityGroup->user);
      Log::info("SubmitFileToOcrJob dispatched for EntityGroup #$entityGroup->id");
    }
  }


  /**
   * Handles the uploaded file from the request, validates it, determines its type,
   * and calls the appropriate specific storage method.
   *
   * @param Request $request The incoming request object.
   * @param int|null $folderId The ID of the parent folder, or null for root upload.
   * @return void
   * @throws ValidationException If validation fails.
   * @throws Exception If storing the file fails or an unexpected error occurs.
   */
  public function handleUploadedFile(Request $request, int $folderId = null): void
  {
    // 1. Get Authenticated User
    /** @var User|null $user */
    $user = $request->user();
    if ($user === null) {
      Log::error("Attempted file upload without authenticated user.");
      abort(403, 'دسترسی لازم را ندارید.');
    }

    // 2. Get File and Tags from Request
    /** @var UploadedFile|null $file */
    $file = $request->file('file');
    $departments = (array) $request->input('tags');

    // Pre-validation check for file presence and validity
    if (!$file || !$file->isValid()) {
      Log::warning("File object not present or invalid before validation. User: #{$user->id}");
      throw ValidationException::withMessages(['file' => 'فایل معتبر یافت نشد یا در آپلود خطا رخ داده است.']);
    }

    // 3. Get File Extension (used for routing and validation)
    $extension = strtolower($file->getClientOriginalExtension());

    // 4. Prepare Validation Rules (using Extensions)
    $allowedExtensions = [];
    foreach ((array) config('mime-type') as $category => $mimes) {
      $allowedExtensions = array_merge($allowedExtensions, array_keys((array) $mimes));
    }
    $validationExtensionsString = implode(',', array_unique($allowedExtensions));

    // Log essential details before validation attempt
    Log::debug(
      "Attempting to validate file upload for User: #$user->id, File: {$file->getClientOriginalName()}, Ext: $extension, FolderID: "
      . ($folderId ?? 'ROOT') . ", ValidationRule: extensions:$validationExtensionsString"
    );

    // 5. Perform Validation
    try {
      $request->validate([
        'file' => [
          'required',
          'file',
          'extensions:' . $validationExtensionsString, // Validate based on allowed extensions
          'max:307200' // 300MB limit (307200 KB)
        ],
        'tags' => 'required|array|min:1',
        'tags.*' => 'numeric|exists:departments,id', // Validate tags are numeric department IDs that exist
      ]);
      // Log validation success
      Log::info("File validation passed for User: #{$user->id}, File: {$file->getClientOriginalName()}");
    } catch (ValidationException $e) {
      // Log validation failure with specific errors
      Log::error(
        "File upload validation failed for User: #{$user->id}, File: {$file->getClientOriginalName()}. Errors: "
        . json_encode($e->errors())
      );
      throw $e; // Re-throw the exception
    }

    // 6. Determine File Type and Delegate Storage
    Log::info("Routing file '{$file->getClientOriginalName()}' (Ext: {$extension}) to appropriate store method.");

    if (in_array($extension, array_keys((array) config('mime-type.book')))) {
      $this->storePdf($user, $file, $departments, $folderId);
    } elseif (in_array($extension, array_keys((array) config('mime-type.voice')))) {
      $this->storeVoice($user, $file, $departments, $folderId);
    } elseif (in_array($extension, array_keys((array) config('mime-type.image')))) {
      $this->storeImage($user, $file, $departments, $folderId);
    } elseif (in_array($extension, array_keys((array) config('mime-type.video')))) {
      $this->storeVideo($user, $file, $departments, $folderId);
    } elseif (in_array($extension, array_keys((array) config('mime-type.office')))) {
      // Delegate to specific office type handlers
      if (in_array($extension, ['xlsx', 'xls'])) {
        $this->storeSpreadsheet($user, $file, $departments, $folderId);
      } elseif (in_array($extension, ['pptx', 'ppt'])) {
        $this->storePowerpoint($user, $file, $departments, $folderId);
      } elseif (in_array($extension, ['docx', 'doc'])) {
        $this->storeWord($user, $file, $departments, $folderId);
      } else {
        // Should not happen if config/validation is correct
        Log::error("Unhandled office file extension '{$extension}' for User: #{$user->id}, File: {$file->getClientOriginalName()}");
        throw ValidationException::withMessages(['file' => "فرمت فایل آفیس '{$extension}' پشتیبانی نمی‌شود."]);
      }
    } elseif (in_array($extension, array_keys((array) config('mime-type.archive')))) {
      $this->storeArchive($user, $file, $departments, $folderId);
    } else {
      // This indicates a mismatch between validation and routing logic
      Log::error("Unsupported file extension '{$extension}' made it past validation for User: #{$user->id}, File: {$file->getClientOriginalName()}. Check config and validation logic.");
      throw ValidationException::withMessages(['file' => 'فایل مورد نظر پشتیبانی نمیشود.']);
    }

    // Log overall success for this function's scope
    Log::info("Successfully processed and initiated storage for file '{$file->getClientOriginalName()}' for User: #{$user->id}");
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
