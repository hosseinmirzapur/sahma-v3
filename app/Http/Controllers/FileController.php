<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVoiceToWaveJob;
use App\Jobs\ExtractVoiceFromVideoJob;
use App\Jobs\SubmitFileToAsrJob;
use App\Jobs\SubmitFileToOcrJob;
use App\Jobs\CreateSplitVoiceToEntitiesJob;
use App\Jobs\SubmitVoiceToSplitterJob;
use App\Models\Activity;
use App\Models\Department;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Laravel\Octane\Exceptions\DdException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    private ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
        $this->middleware('convert.obfuscatedId-entityGroup')->only(['show', 'addDescription']);
        $this->middleware('convert.obfuscatedId-folder')->only(['upload']);
        $this->middleware('check.permission.folder-and-file-management')->only(['upload', 'uploadRoot']);
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
        $type = match ($entityGroup->type) {
            'pdf', 'image', 'word' => 'ITT',
            'voice' => 'STT',
            'video' => 'VTT',
            default => throw ValidationException::withMessages(['message' => 'unsupported file.'])
        };

        $fileData = $entityGroup->generateFileDataForEmbedding();

        $fileContent = $fileData['fileContent'] ?? '';
        $fileType = $fileData['fileType'] ?? '';

        if (
            in_array($entityGroup->type, ['video', 'voice']) &&
            $entityGroup->status == EntityGroup::STATUS_TRANSCRIBED
        ) {
            $voiceWindows = $entityGroup->result_location['voice_windows'] ?? [];
            ksort($voiceWindows);
        }
        $searchedInput = $request->input('searchable_text') ?? null;
        $downloadRoutes = [
        'original' => strval(
            route(
                "web.user.dashboard.file.download.original-file",
                ['fileId' => $entityGroup->getEntityGroupId()]
            )
        ),
        'searchable' => strval(
            route("web.user.dashboard.file.download.searchable", ['fileId' => $entityGroup->getEntityGroupId()])
        ),
        'word' => strval(
            route("web.user.dashboard.file.download.word", ['fileId' => $entityGroup->getEntityGroupId()])
        ),

        ];
        $file = [
        'id' => $entityGroup->id,
        'slug' => $entityGroup->getEntityGroupId(),
        'name' => strval(pathinfo($entityGroup->name, PATHINFO_FILENAME)),
        'status' => $entityGroup->status,
        'transcribeResult' => $entityGroup->transcription_result,
        'extension' => strval(pathinfo($entityGroup->name, PATHINFO_EXTENSION)),
        'created_at' => timestamp_to_persian_datetime($entityGroup->created_at, false),
        'departments' => $entityGroup->getEntityGroupDepartments(),
        'previousPage' => $entityGroup->parent_folder_id ?
        route(
            'web.user.dashboard.folder.show',
            ['folderId' => $entityGroup->parentFolder?->getFolderId()]
        ) : route('web.user.dashboard.index')
        ];
        return inertia('Dashboard/DocManagement/Services', [
          'file' => $file,
          'fileContent' => $fileContent,
          'fileType' => $fileType,
          'activities' => ActivityService::getActivityByType($entityGroup),
          'voiceWindows' => $voiceWindows ?? null,
          'component' => $type,
          'searchedInput' => $searchedInput,
          'downloadRoute' => $downloadRoutes,
          'printRoute' =>  route(
              "web.user.dashboard.file.print.original",
              ['fileId' => $entityGroup->getEntityGroupId()]
          ),
        ]);
    }

    public function addDescription(Request $request): RedirectResponse
    {
        $request->validate([
        'description' => 'required|string'
        ]);
      /* @var User $user */
        $user = $request->user();

      /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        $descriptionInput = strval($request->input('description'));

        DB::transaction(function () use ($user, $entityGroup, $descriptionInput) {
            $descriptionActivity = 'کاربر '  . $user->name . ' ';
            $descriptionActivity .= 'با کد پرسنلی '  . $user->personal_id . ' '; /** @phpstan-ignore-line */
            if (is_null($entityGroup->description)) {
                $descriptionActivity .= 'به فایل '  . $entityGroup->name . ' ';
                $descriptionActivity .= 'توضیحات " '  . $descriptionInput . ' "';
                $descriptionActivity .= 'را اضافه کرد.';
            } else {
                $descriptionActivity .= 'در فایل '  . $entityGroup->name . ' ';
                $descriptionActivity .= ' به توضیحات " '  . $descriptionInput . ' "';
                $descriptionActivity .= 'تغییر داد.';
            }
            $entityGroup->description = $descriptionInput;
            $entityGroup->save();

            $this->activityService->logUserAction(
                $user, /** @phpstan-ignore-line */
                Activity::TYPE_DESCRIPTION,
                $entityGroup,
                $descriptionActivity
            );
        }, 3);
        return redirect()->back()->with(['message' => 'توضیحات با موفقیت اضافه شد.']);
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
      /* @var User $user */
        $user = $request->user();

      /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        $destinationFolder = $request->input('destinationFolder') ?? null;
        $destinationFolder = Folder::query()->find($destinationFolder);
        $entityGroup = DB::transaction(function () use ($user, $destinationFolder, $entityGroup) {
            $entityGroup->parent_folder_id = $destinationFolder?->id;
            $entityGroup->save();

            $description = " کاربر $user->name";
            $description .= " با کد پرسنلی $user->personal_id";
            $description .= 'فایل '  . $entityGroup->name . ' ';
            if (is_null($destinationFolder)) {
                $description .= " را به داشبورد ";
            } else {
                $description .= "را درون پوشه $destinationFolder->name";
            }
            $description .= 'انتقال داد.';

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $entityGroup, $description);
            return $entityGroup;
        }, 3);
        Log::info(
            "File:#$entityGroup->id-$entityGroup->name has been moved
               to Folder:#$destinationFolder?->id-$destinationFolder?->name"
        );
        if ($destinationFolder) {
            return redirect()
            /** @phpstan-ignore-next-line */
            ->route('web.user.dashboard.folder.show', ['folderId' => $destinationFolder->getFolderId()]);
        } else {
            return redirect()->route('web.user.dashboard.index');
        }
    }

    public function moveRoot(EntityGroup $entityGroup, Request $request): RedirectResponse
    {
      /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $entityGroup = DB::transaction(function () use ($user, $entityGroup) {
            $entityGroup->parent_folder_id = null;
            $entityGroup->save();

            $description = " کاربر $user->name";
            $description .= " با کد پرسنلی $user->personal_id";
            $description .= 'فایل '  . $entityGroup->name . ' ';
            $description .= " را به داشبورد ";
            $description .= 'انتقال داد.';

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $entityGroup, $description);

            return $entityGroup;
        }, 3);

        Log::info("File:#$entityGroup->id-$entityGroup->name has been moved to root.");

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
        $request->validate([
        'fileName' => 'required|string'
        ]);
      /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

      /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        DB::transaction(function () use ($user, $entityGroup, $request) {
            $newName = strval($request->input('fileName'));

            $entityGroup->name = strval($request->input('fileName'));
            $entityGroup->save();

            $description = 'کاربر '  . $user->name . ' ';
            $description .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $description .= 'نام فایل '  . $entityGroup->name . ' ';
            $description .= 'را به  '  . $newName . ' ';
            $description .= 'تغییر داد.';

            $this->activityService->logUserAction($user, Activity::TYPE_RENAME, $entityGroup, $description);
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

            $descriptionText = 'کاربر '  . $user->name . ' ';
            $descriptionText .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $descriptionText .= 'فایل متنی  '  . $entityGroup->name . ' ';
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

            $descriptionText = 'کاربر '  . $user->name . ' ';
            $descriptionText .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $descriptionText .= 'فایل جستجو شونده  '  . $entityGroup->name . ' ';
            $descriptionText .= 'را دانلود کرد.';

            $activityService->logUserAction($user, Activity::TYPE_DOWNLOAD, $entityGroup, $descriptionText);
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
        $descriptionText = 'کاربر '  . $user->name . ' ';
        $descriptionText .= 'با کد پرسنلی '  . $user->personal_id . ' ';
        $descriptionText .= 'فایل اصلی  '  . $entityGroup->name . ' ';
        $descriptionText .= 'را دانلود کرد.';

        $activityService->logUserAction($user, Activity::TYPE_DOWNLOAD, $entityGroup, $descriptionText);

        return Storage::disk($entityGroup->type)->download($entityGroup->file_location, $entityGroup->name);
    }

  /**
   * @throws ValidationException
   */
    public function transcribe(Request $request, FileService $fileService): RedirectResponse
    {
      /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

      /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');
        if ($entityGroup->status != EntityGroup::STATUS_WAITING_FOR_RETRY) {
            throw ValidationException::withMessages(['message' => 'فایل مورد نظر در وضعیت پردازش مجدد قرار ندارد.']);
        }
        DB::transaction(function () use ($user, $entityGroup, $fileService) {
            if (in_array($entityGroup->type, ['image', 'pdf', 'word'])) {
                $entityGroup->status = EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION;
                $entityGroup->save();
                SubmitFileToOcrJob::dispatch($entityGroup, $user);
            } else {
                if (!isset($entityGroup->result_location['wav_location'])) {
                    if ($entityGroup->type == 'video') {
                        $entityGroup->status = EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION;
                        $entityGroup->save();
                        ExtractVoiceFromVideoJob::dispatch($entityGroup);
                    } else {
                        $entityGroup->status = EntityGroup::STATUS_WAITING_FOR_SPLIT;
                        $entityGroup->save();
                        ConvertVoiceToWaveJob::dispatch($entityGroup);
                    }
                } elseif (!isset($entityGroup->meta['windows'])) {
                    $entityGroup->status = EntityGroup::STATUS_WAITING_FOR_SPLIT;
                    $entityGroup->save();
                    SubmitVoiceToSplitterJob::dispatch($entityGroup);
                } elseif ($entityGroup->entities()->count() == 0) {
                    $entityGroup->status = EntityGroup::STATUS_WAITING_FOR_SPLIT;
                    $entityGroup->save();
                    CreateSplitVoiceToEntitiesJob::dispatch($entityGroup);
                } else {
                    $fileService->deleteEntitiesOfEntityGroup($entityGroup);
                    $entityGroup->status = EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION;
                    $entityGroup->save();
                    SubmitFileToAsrJob::dispatch($entityGroup, $user);
                }
            }

            $descriptionText = 'کاربر '  . $user->name . ' ';
            $descriptionText .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $descriptionText .= 'فایل '  . $entityGroup->name . ' ';
            $descriptionText .= 'برای بررسی مجدد هوشمند ارسال کرد.';

            $this->activityService->logUserAction(
                $user,
                Activity::TYPE_TRANSCRIPTION,
                $entityGroup,
                $descriptionText
            );
        }, 3);
        return redirect()->back()->with(['message' => 'درخواست تبدیل ارسال شد.']);
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
            throw ValidationException::withMessages(['message' => 'پرینت فقط برای فایل ها عکس و pdf مقدور است.']);
        }

        $pdfContent = Storage::disk('pdf')->get($entityGroup->result_location['pdf_location'] ?? '');
        $pdfFileName = pathinfo($entityGroup->result_location['pdf_location'] ?? '', PATHINFO_BASENAME);

        $descriptionText = 'کاربر '  . $user->name . ' ';
        $descriptionText .= 'با کد پرسنلی '  . $user->personal_id . ' ';
        $descriptionText .= 'فایل جستجو شونده '  . $entityGroup->name . ' ';
        $descriptionText .= 'پزینت کرد.';

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
   */
    public function printOriginalFile(Request $request): Application|\Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
      /* @codingStandardsIgnoreEnd */
      /* @var User $user */
        $user = $request->user();

      /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');
        if ($entityGroup->status == EntityGroup::STATUS_TRANSCRIBED) {
            $disk = 'pdf';
            if (in_array($entityGroup->type, ['image', 'pdf'])) {
                $fileLocation = $entityGroup->result_location['pdf_location'] ?? '';
            } else {
                if (!isset($entityGroup->result_location['converted_word_to_pdf'])) {
                    if ($entityGroup->type == 'word') {
                        $wordFilePath = $entityGroup->file_location;
                    } else {
                        $wordFilePath = $entityGroup->result_location['word_location'] ?? '';
                    }
                  /* @var OfficeService $officeService */
                    $officeService = app()->make(OfficeService::class);
                    $fileLocation = $officeService->convertWordFileToPdf($wordFilePath);
                    $result = $entityGroup->result_location;
                    $result['converted_word_to_pdf'] = $fileLocation;
                    $entityGroup->result_location = $result;
                    $entityGroup->save();
                } else {
                    $fileLocation = $entityGroup->result_location['converted_word_to_pdf'] ?? '';
                }
            }
        } else {
            if ($entityGroup->type == 'pdf') {
                $disk = 'pdf';
                $fileLocation = $entityGroup->file_location;
            } else {
                throw ValidationException::withMessages(['message' => 'فایل قابل پرینت آماده نیست']);
            }
        }

        $pdfContent = Storage::disk($disk)->get($fileLocation);
        $pdfFileName = strval(pathinfo($entityGroup->name, PATHINFO_FILENAME)) . '.pdf';

        $descriptionText = 'کاربر '  . $user->name . ' ';
        $descriptionText .= 'با کد پرسنلی '  . $user->personal_id . ' ';
        $descriptionText .= 'فایل اضلی '  . $entityGroup->name . ' ';
        $descriptionText .= 'پزینت کرد.';

        $this->activityService->logUserAction(
            $user,
            Activity::TYPE_PRINT,
            $entityGroup,
            $descriptionText
        );

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

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $data = $request->validate([
            'departments' => 'required|array',
            'departments.*' => 'required|integer|exists:departments,id',
        ]);


        /** @var EntityGroup $entityGroup */
        $entityGroup = EntityGroup::query()->findOrFail($fileId);

        DepartmentFile::query()->where('entity_group_id', $entityGroup->id)->delete();
        foreach ($data['departments'] as $departmentId) {
            DepartmentFile::query()->create([
                'entity_group_id' => $entityGroup->id,
                'department_id' => $departmentId,
            ]);
        }
        return redirect()->back()->with([
            'message' => 'فایل مورد نظر با موفقیت ویرایش شد!'
        ]);
    }
}
