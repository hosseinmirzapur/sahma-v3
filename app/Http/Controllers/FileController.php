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

class FileController extends Controller
{
    private ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
        $this->middleware('convert.obfuscatedId-entityGroup')
            ->only(['show', 'addDescription']);
        $this->middleware('convert.obfuscatedId-folder')
            ->only(['upload']);
        $this->middleware('check.permission.folder-and-file-management')
            ->only(['upload', 'uploadRoot']);
    }

    /**
     * @throws ValidationException
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function show(Request $request): Response|ResponseFactory
    {
        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        // Determine component type based on file type
        $type = match ($entityGroup->type) {
            'pdf', 'image', 'word' => 'ITT',
            'voice' => 'STT',
            'video' => 'VTT',
            default => throw ValidationException::withMessages(['message' => 'Unsupported file type.'])
        };

        $fileData = $entityGroup->generateFileDataForEmbedding();
        $fileContent = $fileData['fileContent'] ?? '';
        $fileType = $fileData['fileType'] ?? '';

        // Sort voice windows only if necessary
        $voiceWindows = null;
        if (
            in_array(
                $entityGroup->type,
                ['video', 'voice']
            ) && $entityGroup->status === EntityGroup::STATUS_TRANSCRIBED
        ) {
            /** @phpstan-ignore-next-line */
            $voiceWindows = Arr::get($entityGroup->result_location, 'voice_windows', []);
            /** @phpstan-ignore-next-line */
            ksort($voiceWindows);
        }

        $searchedInput = $request->input('searchable_text');

        // Define download routes
        $fileId = ['fileId' => $entityGroup->getEntityGroupId()];
        $downloadRoutes = [
            'original' => route("web.user.dashboard.file.download.original-file", $fileId),
            'searchable' => route("web.user.dashboard.file.download.searchable", $fileId),
            'word' => route("web.user.dashboard.file.download.word", $fileId),
        ];

        // Construct file metadata
        $file = [
            'id' => $entityGroup->id,
            'slug' => $entityGroup->getEntityGroupId(),
            'name' => pathinfo($entityGroup->name, PATHINFO_FILENAME),
            'status' => $entityGroup->status,
            'transcribeResult' => $entityGroup->transcription_result,
            'extension' => pathinfo($entityGroup->name, PATHINFO_EXTENSION),
            'created_at' => timestamp_to_persian_datetime($entityGroup->created_at, false),
            'departments' => $entityGroup->getEntityGroupDepartments(),
            'previousPage' => $entityGroup->parent_folder_id
                ? route('web.user.dashboard.folder.show', ['folderId' => $entityGroup->parentFolder?->getFolderId()])
                : route('web.user.dashboard.index'),
        ];

        return inertia('Dashboard/DocManagement/Services', [
            'file' => $file,
            'fileContent' => $fileContent,
            'fileType' => $fileType,
            'activities' => ActivityService::getActivityByType($entityGroup),
            'voiceWindows' => $voiceWindows,
            'component' => $type,
            'searchedInput' => $searchedInput,
            'downloadRoute' => $downloadRoutes,
            'printRoute' => route("web.user.dashboard.file.print.original", $fileId),
        ]);
    }


    public function addDescription(Request $request): RedirectResponse
    {
        // Validate description input
        $request->validate([
            'description' => 'required|string'
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');
        $descriptionInput = $request->input('description');

        // Start database transaction
        DB::transaction(function () use ($user, $entityGroup, $descriptionInput) {
            // Build description activity message
            $descriptionActivity = sprintf(
                'کاربر %s با کد پرسنلی %s %s به فایل %s %s "%s" را %s.',
                $user->name,
                $user->personal_id,
                is_null($entityGroup->description) ? 'توضیحات' : 'تغییر داد',
                $entityGroup->name,
                is_null($entityGroup->description) ? 'اضافه کرد' : 'تغییر داد',
                $descriptionInput,
                is_null($entityGroup->description) ? 'اضافه کرد' : 'تغییر داد'
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

        return redirect()->back()->with('message', 'توضیحات با موفقیت اضافه شد.');
    }


    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function upload(Request $request, FileService $fileService): RedirectResponse
    {
        /* @var Folder $folder */
        $folder = $request->attributes->get('folder');
        /** @phpstan-ignore-next-line */
        $fileService->handleUploadedFile($request, $folder->id);
        return redirect()->back()->with('message', 'فایل جدید با موفقیت بارگذاری شد.');
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function uploadRoot(Request $request, FileService $fileService): RedirectResponse
    {
        $fileService->handleUploadedFile($request);
        return redirect()->back()->with('message', 'فایل جدید با موفقیت بارگذاری شد.');
    }

    /**
     * @throws Exception
     */
    public function move(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        // Find the destination folder if provided
        $destinationFolder = Folder::query()
            ->find(
                $request->input('destinationFolder')
            );

        // Perform the move operation inside a transaction
        DB::transaction(function () use ($user, $destinationFolder, $entityGroup) {
            $entityGroup->parent_folder_id = $destinationFolder?->id;
            $entityGroup->save();

            $description = sprintf(
                'کاربر %s با کد پرسنلی %s فایل %s را %s.',
                $user->name,
                $user->personal_id,
                $entityGroup->name,
                $destinationFolder ? "درون پوشه $destinationFolder->name انتقال داد" : "به داشبورد انتقال داد"
            );

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $entityGroup, $description);
        }, 3);

        // Log the move action
        Log::info(sprintf(
            'File:#%d-%s has been moved to Folder:#%s-%s',
            $entityGroup->id,
            $entityGroup->name,
            $destinationFolder?->id ?? 'NULL',
            $destinationFolder?->name ?? 'Dashboard'
        ));

        // Redirect to the appropriate location
        return $destinationFolder
            ? redirect()
                ->route('web.user.dashboard.folder.show', [
                    'folderId' => $destinationFolder->getFolderId()
                ])
            : redirect()
                ->route('web.user.dashboard.index');
    }


    public function moveRoot(EntityGroup $entityGroup, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user() ?? abort(403, 'دسترسی لازم را ندارید.');

        DB::transaction(function () use ($user, $entityGroup) {
            $entityGroup->update(['parent_folder_id' => null]);

            $description = sprintf(
                'کاربر %s با کد پرسنلی %s فایل %s را به داشبورد انتقال داد.',
                $user->name,
                $user->personal_id,
                $entityGroup->name
            );

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $entityGroup, $description);
        }, 3);

        Log::info(sprintf(
            'File:#%d-%s has been moved to root.',
            $entityGroup->id,
            $entityGroup->name
        ));

        return redirect()->route('web.user.dashboard.index');
    }


    public function delete(Request $request, FileService $fileService): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        $fileService->deleteEntityGroupAndEntitiesAndFiles($entityGroup, $user);

        Log::info(
            "File:#$entityGroup->id-$entityGroup->name has been permanently deleted by User:#$user->id"
        );

        return redirect()->route('web.user.dashboard.archive.index');
    }

    public function rename(Request $request): RedirectResponse
    {
        // Validate input
        $request->validate([
            'fileName' => 'required|string'
        ]);

        /** @var User $user */
        $user = $request->user() ?? abort(403, 'دسترسی لازم را ندارید.');

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');
        $newName = strval($request->input('fileName'));

        DB::transaction(function () use ($user, $entityGroup, $newName) {
            $oldName = $entityGroup->name;
            $entityGroup->update(['name' => $newName]);

            $description = sprintf(
                'کاربر %s با کد پرسنلی %s نام فایل %s را به %s تغییر داد.',
                $user->name,
                $user->personal_id,
                $oldName,
                $newName,
            );

            $this->activityService->logUserAction(
                $user,
                Activity::TYPE_RENAME,
                $entityGroup,
                $description
            );
        }, 3);

        return redirect()->back()->with('message', 'نام فایل با موفقیت تغییر کرد.');
    }


    /**
     * @throws ValidationException
     */
    public function downloadWordFile(Request $request, ActivityService $activityService): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        if ($entityGroup->status == EntityGroup::STATUS_TRANSCRIBED) {
            $fileLocation = $entityGroup->result_location['word_location'] ?? '';
            $fileName = 'Transcribed-' . strval(pathinfo($entityGroup->name, PATHINFO_FILENAME)) . '.' .
                strval(pathinfo($entityGroup->result_location['word_location'] ?? '', PATHINFO_EXTENSION));

            $descriptionText = 'کاربر ' . $user->name . ' ';
            $descriptionText .= 'با کد پرسنلی ' . $user->personal_id . ' ';
            $descriptionText .= 'فایل متنی  ' . $entityGroup->name . ' ';
            $descriptionText .= 'را دانلود کرد.';

            $activityService->logUserAction($user, Activity::TYPE_DOWNLOAD, $entityGroup, $descriptionText);
            return Storage::disk('word')->download($fileLocation, $fileName);
        }
        throw ValidationException::withMessages(['message' => 'فایل pdf قابل جستجو آماده نیست.']);
    }

    /**
     * @throws ValidationException
     */
    public function downloadSearchAbleFile(Request $request, ActivityService $activityService): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        if (!in_array($entityGroup->type, ['pdf', 'image'])) {
            throw ValidationException::withMessages(
                ['message' => 'فایل pdf قابل جستجو فقط برای اسناد عکسی یا pdf موجود میباشد.']
            );
        }

        if ($entityGroup->status == EntityGroup::STATUS_TRANSCRIBED) {
            $fileLocation = $entityGroup->result_location['pdf_watermark_location'] ?? '';
            $fileName = 'Transcribed-watermarked' .
                strval(pathinfo($entityGroup->name, PATHINFO_FILENAME)) . '.' .
                strval(
                    pathinfo(
                        $entityGroup->result_location['pdf_watermark_location'] ?? '',
                        PATHINFO_EXTENSION
                    )
                );

            $descriptionText = sprintf(
                "کاربر %s با کد پرسنلی %s فایل جستجو شونده %sرا دانلود کرد",
                $user->name,
                $user->personal_id,
                $entityGroup->name
            );

            $activityService->logUserAction(
                $user,
                Activity::TYPE_DOWNLOAD,
                $entityGroup,
                $descriptionText
            );
            return Storage::disk('pdf')->download($fileLocation, $fileName);
        }
        throw ValidationException::withMessages(['message' => 'فایل pdf قابل جستجو آماده نیست.']);
    }

    public function downloadOriginalFile(Request $request, ActivityService $activityService): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');
        $descriptionText = sprintf(
            "کاربر %s با کد پرسنلی %s فایل اصلی %s را دانلود کرد",
            $user->name,
            $user->personal_id,
            $entityGroup->name
        );

        $activityService->logUserAction($user, Activity::TYPE_DOWNLOAD, $entityGroup, $descriptionText);

        return Storage::disk($entityGroup->type)->download($entityGroup->file_location, $entityGroup->name);
    }

    /**
     * @throws ValidationException
     */
    public function transcribe(Request $request, FileService $fileService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user() ?? abort(403, 'دسترسی لازم را ندارید.');

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        if ($entityGroup->status !== EntityGroup::STATUS_WAITING_FOR_RETRY) {
            throw ValidationException::withMessages([
                'message' => 'فایل مورد نظر در وضعیت پردازش مجدد قرار ندارد.'
            ]);
        }

        DB::transaction(function () use ($user, $entityGroup, $fileService) {
            switch (true) {
                case in_array($entityGroup->type, ['image', 'pdf', 'word']):
                    $entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION]);
                    SubmitFileToOcrJob::dispatch($entityGroup, $user);
                    break;

                case !isset($entityGroup->result_location['wav_location']):
                    $status = ($entityGroup->type === 'video')
                        ? EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION
                        : EntityGroup::STATUS_WAITING_FOR_SPLIT;

                    $entityGroup->update(['status' => $status]);

                    $job = ($entityGroup->type === 'video')
                        ? ExtractVoiceFromVideoJob::class
                        : ConvertVoiceToWaveJob::class;

                    $job::dispatch($entityGroup);
                    break;

                case !isset($entityGroup->meta['windows']):
                    $entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_SPLIT]);
                    SubmitVoiceToSplitterJob::dispatch($entityGroup);
                    break;

                case $entityGroup->entities()->count() === 0:
                    $entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_SPLIT]);
                    CreateSplitVoiceToEntitiesJob::dispatch($entityGroup);
                    break;

                default:
                    $fileService->deleteEntitiesOfEntityGroup($entityGroup);
                    $entityGroup->update(['status' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION]);
                    SubmitFileToAsrJob::dispatch($entityGroup, $user);
                    break;
            }

            $descriptionText = sprintf(
                'کاربر %s با کد پرسنلی %s فایل %s برای بررسی مجدد هوشمند ارسال کرد.',
                $user->name,
                $user->personal_id,
                $entityGroup->name
            );

            $this->activityService->logUserAction(
                $user,
                Activity::TYPE_TRANSCRIPTION,
                $entityGroup,
                $descriptionText
            );
        }, 3);

        return redirect()->back()->with('message', 'درخواست تبدیل ارسال شد.');
    }


    /**
     * @throws ValidationException
     * @codingStandardsIgnoreStart
     */
    public function printSearchAbleFile(Request $request): Application|\Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        /* @codingStandardsIgnoreEnd */
        /* @var User $user */
        $user = $request->user();

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        if (!in_array($entityGroup->type, ['pdf', 'image', 'word'])) {
            throw ValidationException::withMessages([
                'message' => 'پرینت فقط برای فایل ها عکس و pdf مقدور است.'
            ]);
        }

        $pdfContent = Storage::disk('pdf')->get($entityGroup->result_location['pdf_location'] ?? '');
        $pdfFileName = pathinfo($entityGroup->result_location['pdf_location'] ?? '', PATHINFO_BASENAME);

        $descriptionText = sprintf(
            "کاربر %s با کد پرسنلی %s فایل جستجو شونده %s را پرینت کرد.",
            $user->name,
            $user->personal_id,
            $entityGroup->name
        );

        $this->activityService->logUserAction(
            $user,
            Activity::TYPE_PRINT,
            $entityGroup,
            $descriptionText
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=$pdfFileName",
        ]);
    }

    /**
     * @codingStandardsIgnoreStart
     * @throws ValidationException
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function printOriginalFile(Request $request): Application|\Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        /* @codingStandardsIgnoreEnd */

        /** @var User $user */
        $user = $request->user();

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        // Determine the file location and processing steps
        if ($entityGroup->status === EntityGroup::STATUS_TRANSCRIBED) {
            $disk = 'pdf';

            if (in_array($entityGroup->type, ['image', 'pdf'])) {
                $fileLocation = $entityGroup->result_location['pdf_location'] ?? '';
            } else {
                $fileLocation = $entityGroup->result_location['converted_word_to_pdf'] ?? null;

                if (!$fileLocation) {
                    $wordFilePath = $entityGroup->type === 'word'
                        ? $entityGroup->file_location
                        : ($entityGroup->result_location['word_location'] ?? '');

                    /* @var OfficeService $officeService */
                    $officeService = app()->make(OfficeService::class);
                    $fileLocation = $officeService->convertWordFileToPdf($wordFilePath);

                    // Store the converted file path
                    $entityGroup->result_location = array_merge($entityGroup->result_location, [
                        'converted_word_to_pdf' => $fileLocation,
                    ]);
                    $entityGroup->save();
                }
            }
        } elseif ($entityGroup->type === 'pdf') {
            $disk = 'pdf';
            $fileLocation = $entityGroup->file_location;
        } else {
            throw ValidationException::withMessages(['message' => 'فایل قابل پرینت آماده نیست']);
        }

        $pdfContent = Storage::disk($disk)->get($fileLocation);
        $pdfFileName = pathinfo($entityGroup->name, PATHINFO_FILENAME) . '.pdf';

        // Logging user activity
        $descriptionText = sprintf(
            'کاربر %s با کد پرسنلی %s فایل اصلی %s را پرینت کرد.',
            $user->name,
            $user->personal_id,
            $entityGroup->name
        );

        $this->activityService->logUserAction($user, Activity::TYPE_PRINT, $entityGroup, $descriptionText);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=$pdfFileName");
    }


    /**
     * @param Request $request
     * @param string|null $fileId
     * @return RedirectResponse
     */
    public function modifyDepartments(Request $request, ?string $fileId): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $data = $request->validate([
            'departments' => 'required|array',
            'departments.*' => 'required|integer|exists:departments,id',
        ]);

        /** @var EntityGroup $entityGroup */
        $entityGroup = EntityGroup::query()->findOrFail($fileId);

        DB::transaction(function () use ($entityGroup, $data) {
            // Bulk delete existing records for performance
            DepartmentFile::query()->where('entity_group_id', $entityGroup->id)->delete();

            // Bulk insert new records to optimize database interaction
            /** @var array<int, int> $departmentIds */
            $departmentIds = $data['departments'];
            $departments = collect($departmentIds)
                ->map(fn($departmentId) => [
                    'entity_group_id' => $entityGroup->id,
                    'department_id' => $departmentId,
                ]);

            DepartmentFile::query()->insert($departments->toArray());
        });

        return redirect()->back()->with([
            'message' => 'فایل مورد نظر با موفقیت ویرایش شد!'
        ]);
    }
}
