<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Department;
use App\Models\DepartmentFile;
use App\Models\DepartmentUser;
use App\Models\Entity;
use App\Models\EntityGroup;
use App\Models\Folder;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\FileService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Throwable;

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
            $description = 'کاربر '  . $user->name . ' ';
            $description .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $description .= 'پوشه '  . $folder->name . ' ';
            $description .= 'را درون پوشه '  . $parentFolder->name . ' ';
            $description .= 'ایجاد کرد.';

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
            $description = 'کاربر '  . $user->name . ' ';
            $description .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $description .= 'پوشه '  . $folder->name . ' ';
            $description .= 'را در صفحه اول داشبورد ایجاد کرد.';

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
        ->get()->map(function (Folder $subFolder) {
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

        $destinationFolder = $request->input('destinationFolder') ?? null;
        $destinationFolder = Folder::query()->find($destinationFolder);
        $folder = DB::transaction(function () use ($user, $destinationFolder, $folder) {
            $folder->parent_folder_id = $destinationFolder?->id;
            $folder->save();

            $description = "پوشه {$folder->name} توسط کاربر  {$user->name}";
            $description .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $description .= " با کد پرسنلی $user->personal_id به پوشه";
            $description .= " $destinationFolder?->name انتقال یافت. ";

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $folder, $description);
            return $folder;
        }, 3);

        if ($destinationFolder) {
            Log::info(
                "Folder:#$folder->id-$folder->name has been moved
               to Folder:#$destinationFolder->id-$destinationFolder->name
               "
            );
            return redirect()
              /** @phpstan-ignore-next-line  */
              ->route('web.user.dashboard.folder.show', ['folderId' => $destinationFolder->getFolderId()]);
        } else {
            Log::info("Folder:#$folder->id-$folder->name has been moved to dashboard.");
            return redirect()->route('web.user.dashboard.index');
        }
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
            $folder->parent_folder_id = null;
            $folder->save();

            $description = "پوشه {$folder->name} توسط کاربر  {$user->name}";
            $description .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $description .= " با کد پرسنلی $user->personal_id ";
            $description .= "به داشبورد انتقال داد.";

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
            $folder->name = $newName;
            $folder->save();

            $description = 'کاربر '  . $user->name . ' ';
            $description .= 'با کد پرسنلی '  . $user->personal_id . ' ';
            $description .= 'نام پوشه '  . $folder->name . ' ';
            $description .= 'را به  '  . $newName . ' ';
            $description .= 'تغییر داد.';

            $activityService->logUserAction($user, Activity::TYPE_RENAME, $folder, $description);
        }, 3);
        return redirect()->back()->with('message', 'نام پوشه با موفقیت تغییر کرد.');
    }
}
