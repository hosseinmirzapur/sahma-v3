<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DepartmentUser;
use App\Models\Folder;
use App\Models\User;
use App\Services\ActivityService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

class FolderController extends Controller
{
    private ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
        $this->middleware('check.permission.folder-and-file-management')
            ->only(['create', 'createRoot', 'move', 'moveRoot', 'temporaryDelete']);
        $this->middleware('convert.obfuscatedId-folder')
            ->only(['show']);
    }

    /**
     * @throws Exception
     */
    public function create(Request $request): RedirectResponse
    {
        $request->validate([
            'folderName' => 'required|string'
        ]);
        /** @var User $user */
        $user = $request->user();
        /** @var Folder|null $parentFolder */
        $parentFolder = $request->attributes->get('folder');

        if ($parentFolder === null) {
            throw ValidationException::withMessages(['message' => 'پوشه مورد نظر یافت نشد.']);
        }

        $folderName = strval($request->input('folderName'));

        $folder = DB::transaction(function () use ($folderName, $user, $parentFolder) {
            $folder = Folder::query()->create([
                'name' => $folderName,
                'user_id' => $user->id,
                'parent_folder_id' => $parentFolder->id
            ]);
            $description = sprintf(
                "کاربر %s با کد پرسنلی %s پوشه %s را درون پوشه %s ایجاد کرد",
                $user->name,
                $user->personal_id,
                $folder->name,
                $parentFolder->name
            );

            $this->activityService->logUserAction($user, Activity::TYPE_CREATE, $folder, $description);

            return $folder;
        }, 3);

        Log::info("User:#$user->personal_id has been created Folder:#$folder->id-$folder->name");
        return redirect()->route('web.user.dashboard.folder.show', ['folderId' => $parentFolder->getFolderId()]);
    }

    public function createRoot(Request $request): RedirectResponse
    {
        $request->validate([
            'folderName' => 'required|string'
        ]);

        /** @var User $user */
        $user = $request->user();

        $folderName = strval($request->input('folderName'));

        $folder = DB::transaction(function () use ($folderName, $user) {
            $folder = Folder::createWithSlug([
                'name' => $folderName,
                'user_id' => $user->id,
                'parent_folder_id' => null
            ]);
            $description = sprintf(
                "کاربر %s با کد پرسنلی %s پوشه %s را در صفحه اول داشبورد ایجاد کرد.",
                $user->name,
                $user->personal_id,
                $folder->name
            );

            $this->activityService->logUserAction($user, Activity::TYPE_CREATE, $folder, $description);

            return $folder;
        }, 3);

        Log::info("User:#$user->personal_id has been created Folder:#$folder->id-$folder->name in Root");

        return redirect()->back()->with('message', 'پوشه جدید با موفقیت اضافه شد');
    }


    /**
     * @throws Exception
     */
    public function show(Request $request): Response|ResponseFactory
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Folder|null $folder */
        $folder = $request->attributes->get('folder');

        if ($folder === null) {
            throw ValidationException::withMessages(['message' => 'پوشه مورد نظر یافت نشد.']);
        }

        $folders = Folder::query()
            ->where('parent_folder_id', $folder->id)
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->select(['id', 'name'])
            ->get()
            ->map(function (Folder $subFolder) {
                return [
                    'id' => $subFolder->id,
                    'name' => $subFolder->name,
                    'slug' => $subFolder->getFolderId()
                ];
            });
        $files = $user->getAllAvailableFilesAsArray($folder);
        $zipFileInfo = strval(session('zipFileInfo', null));
        session()->forget('zipFileInfo');

        // Get the parent folders recursively using a helper function
        $breadcrumbs = $folder->getParentFolders($folder, []);
        $departments = $user->userDepartments->map(function (DepartmentUser $userDepartment) {
            return [
                'id' => $userDepartment->department_id,
                'name' => $userDepartment->department->name,
            ];
        });
        return inertia('Dashboard/DocManagement/index', [
            'folders' => $folders,
            'files' => $files,
            'breadcrumbs' => $breadcrumbs,
            'zipFileInfo' => json_decode($zipFileInfo, true),
            'departments' => $departments
        ]);
    }

    /**
     * @throws Exception
     */
    public function move(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }
        /** @var Folder|null $folder */
        $folder = $request->attributes->get('folder');

        if ($folder === null) {
            throw ValidationException::withMessages(['message' => 'پوشه مورد نظر یافت نشد.']);
        }

        /** @var Folder|null $destinationFolder */
        $destinationFolder = $request->input('destinationFolder') ?? null;
        $destinationFolder = Folder::query()->find($destinationFolder);
        $folder = DB::transaction(function () use ($user, $destinationFolder, $folder) {
            $folder->update([
                'parent_folder_id' => $destinationFolder?->id
            ]);

            $description = sprintf(
                "پوشه %s توسط کاربر %s با کد پرسنلی %s به پوشه %s انتقال یافت",
                $folder->name,
                $user->name,
                $user->personal_id,
                $destinationFolder->name ?? 'NULL'
            );

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $folder, $description);
            return $folder;
        }, 3);

        Log::info(
            sprintf(
                "Folder: #%d-%s has been moved to %s",
                $folder->id,
                $folder->name,
                $destinationFolder ? "Folder: #$destinationFolder->id-$destinationFolder->name" : "Dashboard"
            )
        );
        return redirect()->route(
            $destinationFolder ? "web.user.dashboard.folder.show" : "web.user.dashboard.index",
            $destinationFolder ? ['folderId' => $destinationFolder->getFolderId()] : []
        );
    }

    /**
     * @throws ValidationException
     */
    public function moveRoot(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }
        /** @var Folder|null $folder */
        $folder = $request->attributes->get('folder');

        if ($folder === null) {
            throw ValidationException::withMessages(['message' => 'پوشه مورد نظر یافت نشد.']);
        }
        $folder = DB::transaction(function () use ($user, $folder) {
            $folder = Folder::query()->where('id', $folder->id)->lockForUpdate()->firstOrFail();
            $folder->update([
                'parent_folder_id' => null
            ]);

            $description = sprintf(
                "پوشه %s توسط کاربر %s با کد پرسنلی %s به داشبورد انتقال داد.",
                $folder->name,
                $user->name,
                $user->personal_id
            );

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $folder, $description);

            return $folder;
        }, 3);

        Log::info("Folder:#$folder->id-$folder->name has been moved to root.");

        return redirect()->route('web.user.dashboard.index');
    }

    /**
     * @throws ValidationException
     */
    public function rename(Request $request, ActivityService $activityService): RedirectResponse
    {
        $request->validate(['folderName' => 'required|string']);
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }
        /** @var Folder|null $folder */
        $folder = $request->attributes->get('folder');

        if ($folder === null) {
            throw ValidationException::withMessages(['message' => 'پوشه مورد نظر یافت نشد.']);
        }

        DB::transaction(function () use ($user, $request, $folder, $activityService) {
            $newName = strval($request->input('folderName'));
            $folder->update([
                'name' => $newName
            ]);

            $description = sprintf(
                "کاربر %s با کد پرسنلی %s نام پوشه %s را به %s تغییر داد",
                $user->name,
                $user->personal_id,
                $folder->name,
                $newName
            );

            $activityService->logUserAction($user, Activity::TYPE_RENAME, $folder, $description);
        }, 3);
        return redirect()->back()->with('message', 'نام پوشه با موفقیت تغییر کرد.');
    }
}
