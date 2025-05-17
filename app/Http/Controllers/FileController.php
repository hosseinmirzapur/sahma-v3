<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVoiceToWaveJob;
use App\Jobs\ExtractVoiceFromVideoJob;
use App\Jobs\SubmitFileToAsrJob;
use App\Jobs\SubmitFileToOcrJob;
use App\Jobs\CreateSplitVoiceToEntitiesJob;
use App\Jobs\SubmitVoiceToSplitterJob;
use App\Models\Activity;
use App\Models\DepartmentFile;
use App\Models\EntityGroup;
use App\Models\Folder;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\FileService;
use App\Services\OfficeService;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Response as IlluminateResponse;

class FileController extends Controller
{
  private ActivityService $activityService;

  public function __construct(ActivityService $activityService)
  {
    $this->activityService = $activityService;

    // Apply middleware to resolve EntityGroup from obfuscated ID (slug)
    // This middleware finds the EntityGroup by its slug ({fileId} route param)
    // and adds it to the request attributes as 'entityGroup'.
    $this->middleware('convert.obfuscatedId-entityGroup')
      ->only([
        'show',
        'addDescription',
        'serveRawFile',           // Needed for external viewers/direct access
        'move',                   // Needs entityGroup to update parent_folder_id
        'delete',                 // Needs entityGroup to delete
        'rename',                 // Needs entityGroup to rename
        'downloadWordFile',       // Needs entityGroup to find file
        'downloadSearchAbleFile', // Needs entityGroup to find file
        'downloadOriginalFile',   // Needs entityGroup to find file
        'transcribe',             // Needs entityGroup to dispatch job
        'printSearchAbleFile',    // Needs entityGroup to find file
        'printOriginalFile',      // Needs entityGroup to find/convert file
        'updateAsrText'           // Needs entityGroup to update ASR text
        // 'modifyDepartments' is NOT included here because it uses findOrFail($fileId) with raw ID
        // 'manualProcess' uses its own query where('slug', ...)
      ]);

    // Apply middleware to resolve Folder from obfuscated ID
    $this->middleware('convert.obfuscatedId-folder')
      ->only(['upload']); // Only 'upload' needs a parent Folder resolved this way

    // Apply permission middleware
    $this->middleware('check.permission.folder-and-file-management')
      ->only(['upload', 'uploadRoot']); // Permissions needed for uploading
  }

  /**
   * Display the specific file service view (ITT, STT, VTT, ExternalViewer).
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @return Response|ResponseFactory
   * @throws ValidationException
   * @throws BindingResolutionException
   * @throws Exception
   */
  public function show(Request $request): Response|ResponseFactory
  {
    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');

    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for show method. Route parameter issue or middleware failure.");
      abort(404, 'File identifier not processed correctly.');
    }

    // Determine component type based on file type
    $type = match ($entityGroup->type) {
      'pdf', 'image' => 'ITT', // Word will now use ExternalViewer
      'voice' => 'STT',
      'video' => 'VTT',
      'word', 'spreadsheet', 'powerpoint', 'archive' => 'ExternalViewer', // Group types using ExternalViewer
      default => throw ValidationException::withMessages(['message' => 'Unsupported file type.'])
    };

    // Initialize props that might be overridden
    $fileContent = null; // Default to null, ITT/STT/VTT might populate later
    $fileType = '';      // Default to empty
    $externalViewerUrl = null; // Initialize external viewer URL

    // Define fileIdParam early for reuse
    $fileIdParam = ['fileId' => $entityGroup->getEntityGroupId()]; // Use the slug/obfuscated ID

    // Generate external viewer URL for applicable types
    if (in_array($entityGroup->type, ['word', 'spreadsheet', 'powerpoint'])) {
      $rawFileUrl = route('web.user.dashboard.file.serve.raw', $fileIdParam);
      $externalViewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode($rawFileUrl);
      $type = 'ExternalViewer'; // Ensure component type is correct
    } elseif (!in_array($entityGroup->type, ['archive'])) { // Only fetch embeddable content if not using external viewer or archive
      $fileData = $entityGroup->generateFileDataForEmbedding();
      $fileContent = $fileData['fileContent'] ?? null;
      $fileType = $fileData['fileType'] ?? '';
    }

    // Sort voice windows only if necessary
    $voiceWindows = null;
    if (
      in_array($entityGroup->type, ['video', 'voice'])
      && $entityGroup->status === EntityGroup::STATUS_TRANSCRIBED
      && is_array($entityGroup->result_location) // Check if result_location is an array
    ) {
      $voiceWindows = Arr::get($entityGroup->result_location, 'voice_windows', []);
      if (is_array($voiceWindows)) {
        ksort($voiceWindows);
      } else {
        $voiceWindows = []; // Ensure it's an array if ksort fails
      }
    }

    $searchedInput = $request->input('searchable_text');

    // Define download routes (fileIdParam already defined)
    $downloadRoutes = [
      'original' => route("web.user.dashboard.file.download.original-file", $fileIdParam),
      'searchable' => route("web.user.dashboard.file.download.searchable", $fileIdParam),
      'word' => route("web.user.dashboard.file.download.word", $fileIdParam),
    ];

    // Disable specific download routes for types handled externally or not applicable
    if (in_array($entityGroup->type, ['spreadsheet', 'powerpoint', 'archive'])) {
      // Keep only original download for these types
      $downloadRoutes = [
        'original' => route("web.user.dashboard.file.download.original-file", $fileIdParam),
        'searchable' => null,
        'word' => null,
      ];
    } elseif (!in_array($entityGroup->type, ['pdf', 'image'])) {
      $downloadRoutes['searchable'] = null; // No searchable PDF for voice/video
    }
    if (!in_array($entityGroup->type, ['pdf', 'image', 'word', 'voice', 'video']) || $entityGroup->status !== EntityGroup::STATUS_TRANSCRIBED) {
      $downloadRoutes['word'] = null; // No transcribed Word file unless transcribed
    }


    // Construct file metadata
    $file = [
      'id' => $entityGroup->id,
      'slug' => $entityGroup->getEntityGroupId(),
      'name' => pathinfo($entityGroup->name, PATHINFO_FILENAME),
      'status' => $entityGroup->status,
      'type' => $entityGroup->type, // Pass type to frontend
      'transcribeResult' => $entityGroup->transcription_result,
      'extension' => pathinfo($entityGroup->name, PATHINFO_EXTENSION),
      'created_at' => timestamp_to_persian_datetime($entityGroup->created_at, false),
      'departments' => $entityGroup->getEntityGroupDepartments(),
      'previousPage' => $entityGroup->parent_folder_id
        ? route('web.user.dashboard.folder.show', ['folderId' => $entityGroup->parentFolder?->getFolderId()])
        : route('web.user.dashboard.index'),
    ];

    // Prepare props for Inertia view
    $props = [
      'file' => $file,
      'fileContent' => $fileContent, // May be null for external viewer types
      'fileType' => $fileType,
      'activities' => ActivityService::getActivityByType($entityGroup),
      'voiceWindows' => $voiceWindows,
      'component' => $type, // Determined earlier, potentially updated for Word/Office
      'searchedInput' => $searchedInput,
      'downloadRoute' => $downloadRoutes, // Pass potentially modified routes
      'printRoute' => in_array($entityGroup->type, ['pdf', 'image', 'word']) // Only allow printing for these types
        ? route("web.user.dashboard.file.print.original", $fileIdParam)
        : null,
      'externalViewerUrl' => $externalViewerUrl, // Pass the generated URL (or null)
    ];

    // Note: The specific external viewer URL generation for spreadsheet/powerpoint
    // was moved earlier to handle the 'word' case as well.
    // The props assignment now correctly uses the $externalViewerUrl variable.

    // Return the Inertia response with the potentially modified props
    return inertia('Dashboard/DocManagement/Services', $props);
  }

  /**
   * Serves the raw file content for external viewers or direct access.
   * Needs 'convert.obfuscatedId-entityGroup' middleware applied in constructor.
   *
   * @param Request $request
   * @return StreamedResponse|IlluminateResponse
   * @throws Exception
   */
  public function serveRawFile(Request $request): StreamedResponse|IlluminateResponse
  {
    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');

    // Crucial Check: Ensure middleware provided the entityGroup
    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for serveRawFile. Route parameter issue or middleware failure.");
      abort(404, 'File identifier not processed correctly.');
    }

    if (!$entityGroup->fileExists()) {
      Log::warning("File not found for EntityGroup ID: {$entityGroup->id} at location: {$entityGroup->file_location} on disk type: {$entityGroup->type}");
      abort(404, 'File not found on server.');
    }

    $diskName = match ($entityGroup->type) {
      'spreadsheet' => 'excel',
      'powerpoint' => 'powerpoint',
      'archive' => 'archive',
      'word' => 'word', // Serve original word if needed by viewer
      'pdf' => 'pdf',
      'image' => 'image',
      'voice' => 'voice',
      'video' => 'video',
      default => $entityGroup->type, // Fallback (should ideally not happen for known types)
    };

    $storage = Storage::disk($diskName);
    $path = $entityGroup->file_location;

    if (!$storage->exists($path)) {
      Log::error("File path {$path} not found on disk '{$diskName}' for EntityGroup ID: {$entityGroup->id}. Storage inconsistency?");
      abort(404, "File not found on disk '{$diskName}'.");
    }

    $mimeType = $storage->mimeType($path) ?: 'application/octet-stream';
    $stream = $storage->readStream($path);

    if ($stream === false) {
      Log::error("Failed to read stream for file path {$path} on disk '{$diskName}' for EntityGroup ID: {$entityGroup->id}.");
      abort(500, "Could not read file stream.");
    }


    $headers = ['Content-Type' => $mimeType];

    // 'inline' for viewers, 'attachment' forces download.
    if ($entityGroup->type === 'archive') {
      $headers['Content-Disposition'] = 'attachment; filename="' . $entityGroup->name . '"';
    } else {
      // Let Office viewer handle based on Content-Type, inline might be needed.
      $headers['Content-Disposition'] = 'inline; filename="' . $entityGroup->name . '"';
    }

    // Use StreamedResponse for potentially large files
    return response()->stream(function () use ($stream) {
      fpassthru($stream); // Reads and outputs the stream until EOF
      if (is_resource($stream)) { // Close the stream if it's still open
        fclose($stream);
      }
    }, 200, $headers);
  }


  /**
   * Add or update the description for a file.
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @return RedirectResponse
   */
  public function addDescription(Request $request): RedirectResponse
  {
    $request->validate(['description' => 'required|string']);

    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403); // Should be protected by auth middleware anyway
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for addDescription.");
      abort(404);
    }

    $descriptionInput = $request->input('description');
    $isAdding = is_null($entityGroup->description); // Check before updating

    DB::transaction(function () use ($user, $entityGroup, $descriptionInput, $isAdding) {
      $actionVerb = $isAdding ? 'اضافه کرد' : 'تغییر داد';
      $changeType = $isAdding ? 'توضیحات' : 'توضیحات'; // Or 'تغییر توضیحات' ?

      $descriptionActivity = sprintf(
        'کاربر %s با کد پرسنلی %s %s به فایل %s %s "%s" را %s.',
        $user->name,
        $user->personal_id,
        $changeType, // 'توضیحات' or 'تغییر توضیحات'
        $entityGroup->name,
        $actionVerb, // 'اضافه کرد' or 'تغییر داد'
        $descriptionInput,
        $actionVerb // 'اضافه کرد' or 'تغییر داد'
      );

      // Update the description
      $entityGroup->description = $descriptionInput;
      $entityGroup->save();

      // Log the user action
      $this->activityService->logUserAction(
        $user,
        Activity::TYPE_DESCRIPTION,
        $entityGroup,
        $descriptionActivity
      );
    }, 3);

    return redirect()->back()->with('message', 'توضیحات با موفقیت ' . ($isAdding ? 'اضافه شد' : 'تغییر کرد') . '.');
  }


  /**
   * Handle file upload into a specific folder.
   * Needs 'convert.obfuscatedId-folder' middleware.
   * Needs 'check.permission.folder-and-file-management' middleware.
   *
   * @param Request $request
   * @param FileService $fileService
   * @return RedirectResponse
   * @throws ValidationException
   * @throws Exception
   */
  public function upload(Request $request, FileService $fileService): RedirectResponse
  {
    /* @var Folder|null $folder */
    $folder = $request->attributes->get('folder');
    if (!$folder) {
      Log::error("Folder not found in request attributes for upload method.");
      abort(404, 'Target folder not found.');
    }

    /** @phpstan-ignore-next-line */
    $fileService->handleUploadedFile($request, $folder->id);
    return redirect()->back()->with('message', 'فایل جدید با موفقیت بارگذاری شد.');
  }

  /**
   * Handle file upload into the root directory.
   * Needs 'check.permission.folder-and-file-management' middleware.
   *
   * @param Request $request
   * @param FileService $fileService
   * @return RedirectResponse
   * @throws ValidationException
   * @throws Exception
   */
  public function uploadRoot(Request $request, FileService $fileService): RedirectResponse
  {
    // No parent folder ID is passed, so it uploads to root
    $fileService->handleUploadedFile($request);
    return redirect()->back()->with('message', 'فایل جدید با موفقیت بارگذاری شد.');
  }

  /**
   * Move a file to a different folder or to the root.
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @return RedirectResponse
   * @throws Exception
   */
  public function move(Request $request): RedirectResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403);
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for move method.");
      abort(404);
    }

    // Destination folder ID might be null (move to root) or an actual folder ID
    $destinationFolderId = $request->input('destinationFolder'); // This should be the raw folder ID
    $destinationFolder = $destinationFolderId ? Folder::query()->find($destinationFolderId) : null;

    // Prevent moving into the same folder
    if ($entityGroup->parent_folder_id === $destinationFolder?->id) {
      return redirect()->back()->withErrors(['message' => 'فایل از قبل در این پوشه قرار دارد.']);
    }


    DB::transaction(function () use ($user, $destinationFolder, $entityGroup) {
      $entityGroup->parent_folder_id = $destinationFolder?->id; // Set to null if $destinationFolder is null
      $entityGroup->save();

      $description = sprintf(
        'کاربر %s با کد پرسنلی %s فایل %s را %s.',
        $user->name,
        $user->personal_id,
        $entityGroup->name,
        $destinationFolder ? "به پوشه '{$destinationFolder->name}' انتقال داد" : "به داشبورد اصلی انتقال داد"
      );

      $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $entityGroup, $description);
    }, 3);

    Log::info(sprintf(
      'File:#%d-%s has been moved to Folder:#%s by User:#%d',
      $entityGroup->id,
      $entityGroup->name,
      $destinationFolder?->id ?? 'ROOT',
      $user->id
    ));

    return $destinationFolder
      ? redirect()->route('web.user.dashboard.folder.show', ['folderId' => $destinationFolder->getFolderId()])
      : redirect()->route('web.user.dashboard.index');
  }

  /**
   * Move a file explicitly to the root directory.
   * Uses Route-Model binding for EntityGroup (using raw ID, not slug/middleware).
   *
   * @param EntityGroup $entityGroup
   * @param Request $request
   * @return RedirectResponse
   */
  public function moveRoot(EntityGroup $entityGroup, Request $request): RedirectResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    if ($entityGroup->parent_folder_id === null) {
      return redirect()->back()->withErrors(['message' => 'فایل از قبل در داشبورد اصلی قرار دارد.']);
    }

    DB::transaction(function () use ($user, $entityGroup) {
      $entityGroup->update(['parent_folder_id' => null]); // Move to root

      $description = sprintf(
        'کاربر %s با کد پرسنلی %s فایل %s را به داشبورد اصلی انتقال داد.',
        $user->name,
        $user->personal_id,
        $entityGroup->name
      );

      $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $entityGroup, $description);
    }, 3);

    Log::info(sprintf(
      'File:#%d-%s has been explicitly moved to root by User:#%d.',
      $entityGroup->id,
      $entityGroup->name,
      $user->id
    ));

    return redirect()->route('web.user.dashboard.index');
  }

  /**
   * Permanently delete a file (EntityGroup and associated data).
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @param FileService $fileService
   * @return RedirectResponse
   */
  public function delete(Request $request, FileService $fileService): RedirectResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for delete method.");
      abort(404);
    }

    $parentFolderId = $entityGroup->parent_folder_id; // Get parent before deleting

    $fileService->deleteEntityGroupAndEntitiesAndFiles($entityGroup, $user);

    Log::info(
      "File:#$entityGroup->id-$entityGroup->name has been permanently deleted by User:#$user->id"
    );

    // Redirect to parent folder if it existed, otherwise to dashboard
    if ($parentFolderId && ($parentFolder = Folder::find($parentFolderId))) {
      return redirect()->route('web.user.dashboard.folder.show', ['folderId' => $parentFolder->getFolderId()])
        ->with('message', 'فایل با موفقیت حذف شد.');
    } else {
      return redirect()->route('web.user.dashboard.index')
        ->with('message', 'فایل با موفقیت حذف شد.');
    }
    // Note: The original code redirected to archive.index, which might be incorrect for permanent deletion.
    // Adjust the redirect target as needed for your application flow (e.g., back to parent folder or dashboard).
  }

  /**
   * Rename a file.
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @return RedirectResponse
   */
  public function rename(Request $request): RedirectResponse
  {
    $request->validate(['fileName' => 'required|string|max:255']); // Add max length

    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for rename method.");
      abort(404);
    }

    $newNameWithoutExtension = strval($request->input('fileName'));
    $extension = pathinfo($entityGroup->name, PATHINFO_EXTENSION);
    $newNameWithExtension = $newNameWithoutExtension . '.' . $extension; // Re-add extension

    DB::transaction(function () use ($user, $entityGroup, $newNameWithExtension) {
      $oldName = $entityGroup->name;
      $entityGroup->update(['name' => $newNameWithExtension]);

      $description = sprintf(
        'کاربر %s با کد پرسنلی %s نام فایل %s را به %s تغییر داد.',
        $user->name,
        $user->personal_id,
        $oldName,
        $newNameWithExtension, // Log name with extension
      );

      $this->activityService->logUserAction(
        $user,
        Activity::TYPE_RENAME,
        $entityGroup,
        $description
      );
    }, 3);

    Log::info("File:#{$entityGroup->id} renamed from '{$entityGroup->getOriginal('name')}' to '{$entityGroup->name}' by User:#{$user->id}");

    return redirect()->back()->with('message', 'نام فایل با موفقیت تغییر کرد.');
  }


  /**
   * Download the transcribed Word file (if available).
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @param ActivityService $activityService
   * @return StreamedResponse
   * @throws ValidationException
   */
  public function downloadWordFile(Request $request, ActivityService $activityService): StreamedResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for downloadWordFile.");
      abort(404);
    }

    if ($entityGroup->status == EntityGroup::STATUS_TRANSCRIBED && isset($entityGroup->result_location['word_location'])) {
      $fileLocation = $entityGroup->result_location['word_location'];
      $disk = 'word'; // Assume transcribed word is on 'word' disk

      if (!Storage::disk($disk)->exists($fileLocation)) {
        Log::error("Transcribed Word file not found at path {$fileLocation} on disk '{$disk}' for EntityGroup ID: {$entityGroup->id}.");
        throw ValidationException::withMessages(['message' => 'فایل Word رونویسی شده یافت نشد.']);
      }

      // Generate a user-friendly filename
      $originalFilename = pathinfo($entityGroup->name, PATHINFO_FILENAME);
      $wordExtension = pathinfo($fileLocation, PATHINFO_EXTENSION);
      $fileName = 'Transcribed-' . $originalFilename . '.' . $wordExtension;


      $descriptionText = sprintf(
        'کاربر %s با کد پرسنلی %s فایل متنی رونویسی شده %s را دانلود کرد.',
        $user->name,
        $user->personal_id,
        $entityGroup->name
      );

      $activityService->logUserAction($user, Activity::TYPE_DOWNLOAD, $entityGroup, $descriptionText);
      Log::info("User:#{$user->id} downloaded transcribed Word file for EntityGroup:#{$entityGroup->id}");
      return Storage::disk($disk)->download($fileLocation, $fileName);
    }
    throw ValidationException::withMessages(['message' => 'فایل Word رونویسی شده آماده نیست یا وجود ندارد.']);
  }

  /**
   * Download the searchable PDF file (if available).
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @param ActivityService $activityService
   * @return StreamedResponse
   * @throws ValidationException
   */
  public function downloadSearchAbleFile(Request $request, ActivityService $activityService): StreamedResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found for downloadSearchAbleFile.");
      abort(404);
    }


    if (!in_array($entityGroup->type, ['pdf', 'image', 'word'])) { // Word can also have a searchable PDF result
      throw ValidationException::withMessages(
        ['message' => 'فایل PDF قابل جستجو فقط برای اسناد عکسی، PDF یا Word پردازش شده موجود میباشد.']
      );
    }

    // Prefer watermarked version if available, otherwise the regular searchable PDF
    $fileLocation = $entityGroup->result_location['pdf_watermark_location']
      ?? $entityGroup->result_location['pdf_location']
      ?? null;
    $isWatermarked = isset($entityGroup->result_location['pdf_watermark_location']);

    if ($entityGroup->status == EntityGroup::STATUS_TRANSCRIBED && $fileLocation) {
      $disk = 'pdf'; // Searchable PDFs are stored on the 'pdf' disk

      if (!Storage::disk($disk)->exists($fileLocation)) {
        Log::error("Searchable PDF file not found at path {$fileLocation} on disk '{$disk}' for EntityGroup ID: {$entityGroup->id}.");
        throw ValidationException::withMessages(['message' => 'فایل PDF قابل جستجو یافت نشد.']);
      }

      $originalFilename = pathinfo($entityGroup->name, PATHINFO_FILENAME);
      $pdfExtension = pathinfo($fileLocation, PATHINFO_EXTENSION); // Should be 'pdf'
      $fileName = 'Searchable-' . ($isWatermarked ? 'Watermarked-' : '') . $originalFilename . '.' . $pdfExtension;

      $descriptionText = sprintf(
        "کاربر %s با کد پرسنلی %s فایل قابل جستجو %s (%s) را دانلود کرد",
        $user->name,
        $user->personal_id,
        $entityGroup->name,
        $isWatermarked ? 'دارای واترمارک' : 'بدون واترمارک'
      );

      $activityService->logUserAction($user, Activity::TYPE_DOWNLOAD, $entityGroup, $descriptionText);
      Log::info("User:#{$user->id} downloaded searchable PDF ({$fileName}) for EntityGroup:#{$entityGroup->id}");
      return Storage::disk($disk)->download($fileLocation, $fileName);
    }
    throw ValidationException::withMessages(['message' => 'فایل PDF قابل جستجو آماده نیست یا وجود ندارد.']);
  }

  /**
   * Download the original uploaded file.
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @param ActivityService $activityService
   * @return StreamedResponse
   */
  public function downloadOriginalFile(Request $request, ActivityService $activityService): StreamedResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found for downloadOriginalFile.");
      abort(404);
    }

    // Determine correct disk based on type
    $diskName = match ($entityGroup->type) {
      'spreadsheet' => 'excel',
      'powerpoint' => 'powerpoint',
      'archive' => 'archive',
      'word' => 'word',
      'pdf' => 'pdf',
      'image' => 'image',
      'voice' => 'voice',
      'video' => 'video',
      default => $entityGroup->type, // Should not happen if type is valid
    };

    // Check file existence before attempting download
    if (!$entityGroup->fileExists()) { // Use the model's method which checks the correct disk
      Log::error("Original file path {$entityGroup->file_location} not found on disk '{$diskName}' for EntityGroup ID: {$entityGroup->id}. Cannot download.");
      abort(404, "فایل اصلی یافت نشد.");
    }

    $descriptionText = sprintf(
      "کاربر %s با کد پرسنلی %s فایل اصلی %s را دانلود کرد",
      $user->name,
      $user->personal_id,
      $entityGroup->name
    );

    $activityService->logUserAction($user, Activity::TYPE_DOWNLOAD, $entityGroup, $descriptionText);
    Log::info("User:#{$user->id} downloaded original file ({$entityGroup->name}, type: {$entityGroup->type}) for EntityGroup:#{$entityGroup->id}");

    // Use the model's file_location and name, and the determined disk
    return Storage::disk($diskName)->download($entityGroup->file_location, $entityGroup->name);
  }

  /**
   * Re-submit a file for transcription/processing if it's in a retry state.
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @param FileService $fileService
   * @return RedirectResponse
   * @throws ValidationException
   */
  public function transcribe(Request $request, FileService $fileService): RedirectResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found for transcribe method.");
      abort(404);
    }


    if ($entityGroup->status !== EntityGroup::STATUS_WAITING_FOR_RETRY) {
      throw ValidationException::withMessages([
        'message' => 'فایل مورد نظر در وضعیت قابل پردازش مجدد قرار ندارد.'
      ]);
    }

    DB::transaction(function () use ($user, $entityGroup, $fileService) {
      Log::info("Retrying transcription for EntityGroup:#{$entityGroup->id} (Type: {$entityGroup->type}) by User:#{$user->id}");
      $originalStatus = $entityGroup->status; // Keep track for logging if needed
      $dispatchedJob = null;
      $newStatus = null;

      switch (true) {
        // OCR Types
        case in_array($entityGroup->type, ['image', 'pdf', 'word']):
          $newStatus = EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION;
          SubmitFileToOcrJob::dispatch($entityGroup, $user);
          $dispatchedJob = SubmitFileToOcrJob::class;
          break;

        // Voice/Video Types - Check stages
        case in_array($entityGroup->type, ['voice', 'video']):
          // Stage 1: Need audio extraction/conversion? (Video always needs extraction, Voice needs conversion if not WAV)
          if ($entityGroup->type === 'video' && !isset($entityGroup->result_location['wav_location'])) {
            $newStatus = EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION;
            ExtractVoiceFromVideoJob::dispatch($entityGroup);
            $dispatchedJob = ExtractVoiceFromVideoJob::class;
          } elseif ($entityGroup->type === 'voice' && !isset($entityGroup->result_location['wav_location']) && !$entityGroup->isWav()) {
            $newStatus = EntityGroup::STATUS_WAITING_FOR_SPLIT; // Assuming conversion happens first then split
            ConvertVoiceToWaveJob::dispatch($entityGroup);
            $dispatchedJob = ConvertVoiceToWaveJob::class;
            // Stage 2: Need splitting? (No windows metadata yet)
          } elseif (!isset($entityGroup->meta['windows'])) {
            $newStatus = EntityGroup::STATUS_WAITING_FOR_SPLIT;
            SubmitVoiceToSplitterJob::dispatch($entityGroup);
            $dispatchedJob = SubmitVoiceToSplitterJob::class;
            // Stage 3: Need entities created from windows?
          } elseif ($entityGroup->entities()->doesntExist()) { // Check if entities exist
            $newStatus = EntityGroup::STATUS_WAITING_FOR_SPLIT; // Or a new status like WAITING_FOR_ENTITY_CREATION?
            CreateSplitVoiceToEntitiesJob::dispatch($entityGroup);
            $dispatchedJob = CreateSplitVoiceToEntitiesJob::class;
            // Stage 4: Need transcription (ASR)
          } else {
            // Already have WAV, windows, and possibly old entities. Delete old entities and resubmit for ASR.
            Log::info("Deleting existing entities before re-submitting to ASR for EntityGroup:#{$entityGroup->id}");
            $fileService->deleteEntitiesOfEntityGroup($entityGroup); // Ensure this only deletes entities, not the group/wav
            $newStatus = EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION;
            SubmitFileToAsrJob::dispatch($entityGroup, $user);
            $dispatchedJob = SubmitFileToAsrJob::class;
          }
          break;

        default:
          Log::error("Attempted to retry transcription for unsupported type: {$entityGroup->type} for EntityGroup:#{$entityGroup->id}");
          // Don't throw ValidationException inside transaction, rethrow outside or handle differently
          throw new \RuntimeException('نوع فایل برای پردازش مجدد پشتیبانی نمی‌شود.');
      }

      // Update status only if a job was dispatched
      if ($newStatus && $dispatchedJob) {
        $entityGroup->update(['status' => $newStatus]);

        $descriptionText = sprintf(
          'کاربر %s با کد پرسنلی %s فایل %s را برای پردازش مجدد (%s) ارسال کرد.',
          $user->name,
          $user->personal_id,
          $entityGroup->name,
          class_basename($dispatchedJob) // Log the job name
        );

        $this->activityService->logUserAction(
          $user,
          Activity::TYPE_TRANSCRIPTION, // Consider a new type like TYPE_RETRY_TRANSCRIPTION
          $entityGroup,
          $descriptionText
        );
        Log::info("Dispatched {$dispatchedJob} for EntityGroup:#{$entityGroup->id}. Status changed from {$originalStatus} to {$newStatus}.");
      } else {
        Log::warning("No suitable job dispatched for retry on EntityGroup:#{$entityGroup->id}. Status remains {$originalStatus}.");
        // Potentially throw an error or return a specific message
        throw new \RuntimeException('مرحله مناسبی برای پردازش مجدد یافت نشد.');
      }
    }, 3); // End transaction

    return redirect()->back()->with('message', 'درخواست پردازش مجدد ارسال شد.');
  }


  /**
   * Prepare the searchable PDF file for printing (inline display).
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @return Application|IlluminateResponse|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
   * @throws ValidationException
   */
  public function printSearchAbleFile(Request $request): Application|IlluminateResponse|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403);
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found for printSearchAbleFile.");
      abort(404);
    }

    if (!in_array($entityGroup->type, ['pdf', 'image', 'word'])) {
      throw ValidationException::withMessages([
        'message' => 'پرینت فایل قابل جستجو فقط برای فایل‌های PDF، عکس یا Word پردازش شده مقدور است.'
      ]);
    }

    // Use the watermarked version if available, otherwise the standard searchable PDF
    $fileLocation = $entityGroup->result_location['pdf_watermark_location']
      ?? $entityGroup->result_location['pdf_location']
      ?? null;

    if ($entityGroup->status !== EntityGroup::STATUS_TRANSCRIBED || !$fileLocation) {
      throw ValidationException::withMessages(['message' => 'فایل PDF قابل جستجو برای پرینت آماده نیست یا وجود ندارد.']);
    }

    $disk = 'pdf';
    if (!Storage::disk($disk)->exists($fileLocation)) {
      Log::error("Searchable PDF for printing not found at path {$fileLocation} on disk '{$disk}' for EntityGroup ID: {$entityGroup->id}.");
      throw ValidationException::withMessages(['message' => 'فایل PDF قابل جستجو یافت نشد.']);
    }

    $pdfContent = Storage::disk($disk)->get($fileLocation);
    $pdfFileName = 'Printable-Searchable-' . pathinfo($entityGroup->name, PATHINFO_FILENAME) . '.pdf';

    $descriptionText = sprintf(
      "کاربر %s با کد پرسنلی %s فایل قابل جستجو %s را برای پرینت آماده کرد.",
      $user->name,
      $user->personal_id,
      $entityGroup->name
    );

    $this->activityService->logUserAction($user, Activity::TYPE_PRINT, $entityGroup, $descriptionText);
    Log::info("User:#{$user->id} prepared searchable PDF for print: EntityGroup:#{$entityGroup->id}");

    return response($pdfContent, 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => "inline; filename=\"{$pdfFileName}\"", // Use inline for printing
    ]);
  }

  /**
   * Prepare the original file (or its PDF conversion) for printing.
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @return Application|IlluminateResponse|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
   * @throws ValidationException
   * @throws BindingResolutionException
   * @throws Exception
   */
  public function printOriginalFile(Request $request): Application|IlluminateResponse|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403);
    }

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');
    if (!$entityGroup) {
      Log::error("EntityGroup not found for printOriginalFile.");
      abort(404);
    }

    $fileLocation = null;
    $disk = null;

    // Determine the file to print based on type
    switch ($entityGroup->type) {
      case 'pdf':
        $disk = 'pdf';
        $fileLocation = $entityGroup->file_location;
        break;

      case 'image':
        // Images need conversion to PDF for reliable printing via browser PDF viewer
        // We might use the 'pdf_location' if OCR already created one, or convert on the fly.
        // For simplicity, let's check if a searchable PDF exists first.
        if ($entityGroup->status === EntityGroup::STATUS_TRANSCRIBED && isset($entityGroup->result_location['pdf_location'])) {
          $disk = 'pdf';
          $fileLocation = $entityGroup->result_location['pdf_location'];
          Log::info("Printing searchable PDF for image EntityGroup:#{$entityGroup->id}");
        } else {
          // For now, disallow printing original image directly if no PDF result exists.
          Log::warning("Direct printing of original image type not implemented without PDF result for EntityGroup:#{$entityGroup->id}.");
          throw ValidationException::withMessages(['message' => 'پرینت مستقیم فایل عکس اصلی پشتیبانی نمی‌شود (نیاز به تبدیل به PDF دارد).']);
        }
        break;

      case 'word':
        // Word needs conversion to PDF. Check if already converted.
        $fileLocation = $entityGroup->result_location['converted_word_to_pdf'] ?? null;
        if ($fileLocation && Storage::disk('pdf')->exists($fileLocation)) {
          $disk = 'pdf';
          Log::info("Printing pre-converted PDF for Word EntityGroup:#{$entityGroup->id}");
        } else {
          // Convert on the fly if not already done
          Log::info("Converting Word to PDF on-the-fly for printing EntityGroup:#{$entityGroup->id}");
          try {
            /* @var OfficeService $officeService */
            $officeService = app()->make(OfficeService::class);
            $wordDisk = 'word';
            if (!Storage::disk($wordDisk)->exists($entityGroup->file_location)) {
              throw new Exception("Original Word file not found for conversion.");
            }
            $originalWordPath = Storage::disk($wordDisk)->path($entityGroup->file_location);
            $fileLocation = $officeService->convertWordFileToPdf($originalWordPath); // This returns a path relative to the 'pdf' disk

            // Store the converted file path if conversion was successful
            $resultLocation = $entityGroup->result_location ?? [];
            $resultLocation['converted_word_to_pdf'] = $fileLocation;
            $entityGroup->result_location = $resultLocation;
            $entityGroup->save();
            $disk = 'pdf';
            Log::info("On-the-fly Word to PDF conversion successful for EntityGroup:#{$entityGroup->id}. Path: {$fileLocation}");
          } catch (Exception $e) {
            Log::error("Failed to convert Word to PDF for printing EntityGroup:#{$entityGroup->id}: " . $e->getMessage());
            throw ValidationException::withMessages(['message' => 'خطا در تبدیل فایل Word به PDF برای پرینت.']);
          }
        }
        break;

      default:
        // Other types (voice, video, spreadsheet, ppt, archive) are generally not printed this way
        Log::warning("Attempted to print unsupported original file type: {$entityGroup->type} for EntityGroup:#{$entityGroup->id}");
        throw ValidationException::withMessages(['message' => 'پرینت برای این نوع فایل پشتیبانی نمی‌شود.']);
    }

    // Final check if we have a printable file
    if (!$disk || !$fileLocation || !Storage::disk($disk)->exists($fileLocation)) {
      Log::error("Could not find or prepare a printable PDF for EntityGroup:#{$entityGroup->id}. Disk: {$disk}, Location: {$fileLocation}");
      throw ValidationException::withMessages(['message' => 'فایل قابل پرینت یافت نشد یا آماده نیست.']);
    }

    $pdfContent = Storage::disk($disk)->get($fileLocation);
    $pdfFileName = 'Printable-Original-' . pathinfo($entityGroup->name, PATHINFO_FILENAME) . '.pdf'; // Always serve as PDF

    // Logging user activity
    $descriptionText = sprintf(
      'کاربر %s با کد پرسنلی %s فایل اصلی %s (%s) را برای پرینت آماده کرد.',
      $user->name,
      $user->personal_id,
      $entityGroup->name,
      $entityGroup->type // Include original type for clarity
    );

    Log::info("User:#{$user->id} prepared original file ({$entityGroup->type}) for print as PDF: EntityGroup:#{$entityGroup->id}");


    return response($pdfContent)
      ->header('Content-Type', 'application/pdf')
      ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\""); // Inline for print preview
  }


  /**
   * Modify the departments associated with a file.
   * Uses the raw database ID ($fileId), NOT the slug/middleware.
   *
   * @param Request $request
   * @param int|null $fileId // Expecting the raw database ID here
   * @return RedirectResponse
   */
  public function modifyDepartments(Request $request, ?int $fileId): RedirectResponse
  {
    /** @var User|null $user */
    $user = $request->user();
    if (!$user) {
      abort(403, 'دسترسی لازم را ندارید.');
    }

    $data = $request->validate([
      'departments' => 'required|array',
      'departments.*' => 'required|integer|exists:departments,id', // Validate each ID exists
    ]);

    /** @var EntityGroup $entityGroup */
    $entityGroup = EntityGroup::query()->findOrFail($fileId); // Find by raw ID

    /** @var array<int> $newDepartmentIds */
    $newDepartmentIds = array_map('intval', $data['departments']);
    sort($newDepartmentIds); // Sort for easier comparison

    $currentDepartmentIds = $entityGroup->getEntityGroupDepartments(); // Get current associated departments
    $currentIds = collect($currentDepartmentIds)->pluck('id')->sort()->values()->all();


    // Only proceed if departments have actually changed
    if ($newDepartmentIds !== $currentIds) {
      DB::transaction(function () use ($entityGroup, $newDepartmentIds, $user, $currentIds) {
        // Sync departments efficiently
        $entityGroup->departments()->sync($newDepartmentIds); // Use the relationship if defined

        // Alternative if no relationship method:
        // // Bulk delete existing records for performance
        // DepartmentFile::query()->where('entity_group_id', $entityGroup->id)->delete();
        // // Bulk insert new records to optimize database interaction
        // $departmentsData = collect($newDepartmentIds)
        //     ->map(fn($departmentId) => [
        //         'entity_group_id' => $entityGroup->id,
        //         'department_id' => $departmentId,
        //         // Add created_at/updated_at if needed/not handled by model
        //     ]);
        // if ($departmentsData->isNotEmpty()){
        //     DepartmentFile::query()->insert($departmentsData->toArray());
        // }

        // Log activity
        $added = array_diff($newDepartmentIds, $currentIds);
        $removed = array_diff($currentIds, $newDepartmentIds);
        $details = [];
        if (!empty($added)) {
          $details[] = "اضافه شدن دپارتمان‌های ID: " . implode(', ', $added);
        }
        if (!empty($removed)) {
          $details[] = "حذف شدن دپارتمان‌های ID: " . implode(', ', $removed);
        }

        $description = sprintf(
          'کاربر %s با کد پرسنلی %s دپارتمان‌های فایل %s را ویرایش کرد. (%s)',
          $user->name,
          $user->personal_id,
          $entityGroup->name,
          implode('; ', $details) ?: 'بدون تغییر ظاهری' // Log details
        );
        Log::info("Departments modified for EntityGroup:#{$entityGroup->id} by User:#{$user->id}. Details: " . implode('; ', $details));
      });

      return redirect()->back()->with([
        'message' => 'دپارتمان‌های فایل با موفقیت ویرایش شد!'
      ]);
    } else {
      // No changes detected
      return redirect()->back()->with([
        'info' => 'تغییری در دپارتمان‌ها ایجاد نشد.' // Use info instead of message
      ]);
    }
  }

  /**
   * Manually trigger the initial processing job for a file.
   * Uses the file slug ($fileId) via route model binding resolution.
   *
   * @param string $fileId // The obfuscated slug from the route
   * @return RedirectResponse
   * @throws ValidationException
   */
  public function manualProcess(string $fileId): RedirectResponse
  {
    // Route model binding should resolve this using the slug if configured correctly
    // Or find manually:
    $entityGroup = EntityGroup::query()
      ->where('slug', $fileId) // Find by slug
      ->firstOrFail();

    // Ensure user is authorized (e.g., owner or admin) - Add policy check if needed
    // Gate::authorize('manual-process', $entityGroup);

    // Check if already processing or done? Optional, depending on desired behavior.
    // if (in_array($entityGroup->status, [EntityGroup::STATUS_TRANSCRIBED, ...other processing statuses])) {
    //    return redirect()->back()->withErrors(['message' => 'فایل در حال حاضر در حال پردازش است یا پردازش آن تمام شده.']);
    // }

    $dispatchedJob = null;
    $newStatus = EntityGroup::STATUS_WAITING_FOR_MANUAL_PROCESS; // Set status indicating manual trigger

    Log::info("Manual process triggered for EntityGroup:#{$entityGroup->id} (Type: {$entityGroup->type})");

    switch ($entityGroup->type) {
      case 'word':
      case 'image':
      case 'pdf':
        SubmitFileToOcrJob::dispatch($entityGroup, $entityGroup->user);
        $dispatchedJob = SubmitFileToOcrJob::class;
        break;
      case 'voice':
        // Check if it needs conversion first
        if (!isset($entityGroup->result_location['wav_location'])) {
          ConvertVoiceToWaveJob::dispatch($entityGroup);
          $dispatchedJob = ConvertVoiceToWaveJob::class;
        } else {
          // Already WAV (or assumed to be), submit for splitting
          SubmitVoiceToSplitterJob::dispatch($entityGroup);
          $dispatchedJob = SubmitVoiceToSplitterJob::class;
        }
        break;
      case 'video':
        ExtractVoiceFromVideoJob::dispatch($entityGroup);
        $dispatchedJob = ExtractVoiceFromVideoJob::class;
        break;
      // Add cases for spreadsheet, powerpoint, archive if they ever need manual processing steps
      case 'spreadsheet':
      case 'powerpoint':
      case 'archive':
        // These types usually don't have automated processing jobs
        Log::warning("Manual process triggered for type '{$entityGroup->type}' which has no automated job. EntityGroup:#{$entityGroup->id}");
        return redirect()->back()->withErrors(['message' => 'این نوع فایل پردازش خودکار ندارد.']);
      default:
        throw ValidationException::withMessages([
          'message' => 'نوع فایل برای پردازش دستی پشتیبانی نمی‌شود.'
        ]);
    }

    // Update status and log activity
    if ($dispatchedJob) {
      $entityGroup->update(['status' => $newStatus]);
      Log::info("Dispatched {$dispatchedJob} via manual trigger for EntityGroup:#{$entityGroup->id}. Status set to {$newStatus}.");

      // Optional: Add Activity Log entry for manual trigger
      /** @var User $currentUser */
      $currentUser = auth()->user(); // Get the user who triggered it
      if ($currentUser) {
        $description = sprintf(
          'کاربر %s با کد پرسنلی %s پردازش دستی فایل %s (%s) را آغاز کرد.',
          $currentUser->name,
          $currentUser->personal_id,
          $entityGroup->name,
          class_basename($dispatchedJob)
        );
        $this->activityService->logUserAction($currentUser, 'MANUAL_PROCESS', $entityGroup, $description); // Define a new activity type
      }

      // Determine the correct redirect URL
      $backUrl = $entityGroup->parent_folder_id && $entityGroup->parentFolder
        ? route('web.user.dashboard.folder.show', ['folderId' => $entityGroup->parentFolder->getFolderId()])
        : route('web.user.dashboard.index');

      return redirect()->to($backUrl) // Redirect to the calculated URL
        ->with('message', 'درخواست پردازش دستی ارسال شد.');
    } else {
      // Should not happen if logic is correct, but handle defensively
      Log::error("Manual process triggered but no job was dispatched for EntityGroup:#{$entityGroup->id}");
      return redirect()->back()->withErrors(['message' => 'خطایی در ارسال درخواست پردازش دستی رخ داد.']);
    }
  }

  /**
   * Update a specific text chunk of ASR result and regenerate the Word file.
   * Needs 'convert.obfuscatedId-entityGroup' middleware.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   * @throws ValidationException
   */
  public function updateAsrText(Request $request)
  {
    $request->validate([
      'index' => 'required|integer|min:0',
      'text' => 'required|string',
    ]);

    /** @var EntityGroup|null $entityGroup */
    $entityGroup = $request->attributes->get('entityGroup');

    if (!$entityGroup) {
      Log::error("EntityGroup not found in request attributes for updateAsrText.");
      return response()->json(['message' => 'File not found.'], 404);
    }

    // Ensure it's a voice or video file with ASR results
    if (!in_array($entityGroup->type, ['voice', 'video']) || !isset($entityGroup->result_location['voice_windows']) || !is_array($entityGroup->result_location['voice_windows'])) {
      return response()->json(['message' => 'Invalid file type or ASR data not available.'], 400);
    }

    $index = $request->input('index');
    $updatedText = $request->input('text');
    $voiceWindows = $entityGroup->result_location['voice_windows'];

    // Check if the index is valid
    if (!array_key_exists($index, $voiceWindows)) {
      return response()->json(['message' => 'Invalid text chunk index.'], 400);
    }

    // Update the text
    $voiceWindows[$index] = $updatedText;

    // Update the result_location with the modified voice_windows
    $resultLocation = $entityGroup->result_location;
    $resultLocation['voice_windows'] = $voiceWindows;
    $entityGroup->result_location = $resultLocation;

    // Save the updated EntityGroup
    $entityGroup->save();

    // TODO: Dispatch a job to regenerate the Word file based on the updated ASR data.
    // Example: RegenerateWordFileJob::dispatch($entityGroup);
    Log::info("ASR text updated for EntityGroup:#{$entityGroup->id} at index {$index}. Word file regeneration job needs to be dispatched.");


    return response()->json(['message' => 'ASR text updated successfully.']);
  }
}
