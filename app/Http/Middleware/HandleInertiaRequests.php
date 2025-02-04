<?php

namespace App\Http\Middleware;

use App\Models\Department;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
  /**
   * The root template that's loaded on the first page visit.
   *
   * @see https://inertiajs.com/server-side-setup#root-template
   *
   */
    public function rootView(Request $request): string|null
    {
        return 'inertia';
    }

  /**
   * Determines the current asset version.
   *
   * @see https://inertiajs.com/asset-versioning
   * @param Request $request
   * @return string|null
   */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

  /**
   * Defines the props that are shared by default.
   *
   * @see https://inertiajs.com/shared-data
   * @param Request $request
   * @return array
   * @throws \Exception
   */
    public function share(Request $request): array
    {
        $share = parent::share($request);

      /* @var User $user */
        $user = $request->user();
        $breadCrumb = [];
        $currentFolder = null;
        if ($request->route('folderId')) {
            $folderId = Folder::convertObfuscatedIdToFolderId(
                strval($request->route('folderId'))
            );
              /* @var Folder $currentFolder */
              $currentFolder = Folder::query()->find($folderId);
          /** @phpstan-ignore-next-line */
              $breadCrumb = $currentFolder->getParentFolders($currentFolder, []);
        }

        $breadCrumbIds = [];
        foreach ($breadCrumb as $folderObject) {
            $breadCrumbIds[] = $folderObject['id'];
        }
        $folders = Folder::query()
        ->whereNull('parent_folder_id')
        ->whereNull('deleted_at')
        ->get()
        ->map(function (Folder $folder) use ($breadCrumbIds, $currentFolder) {
            if (!is_null($currentFolder)) {
                $currentFolder = $currentFolder->id;
            }
            return [
              'id' => $folder->id,
              'name' => $folder->name,
              'parentFolderId' => $folder->parent_folder_id,
              'slug' => $folder->getFolderId(),
              'subFolders' => $folder->subFolders($breadCrumbIds, $currentFolder),
              'isOpen' => in_array($folder->id, $breadCrumbIds)
            ];
        })->toArray();
        $departments = Department::query()->select('departments.*')
        ->join('department_users', 'department_users.department_id', '=', 'departments.id')
        ->where('department_users.user_id', '=', $user?->id)->get()
        ->map(function (Department $department) {
            return [
              'id' => $department->id,
              'name' => $department->name
            ];
        })->toArray();

        return array_merge($share, [
        'authUser' => [
        'name' => $user?->name,
        'id' => $user?->id,
        'folders' => $folders,
        'departments' => $departments,
        'isSuperAdmin' => $user->is_super_admin ?? false,
        'isReadOnly' => $user->role->permission->read_only ?? false,
        'isAdmin' => $user->role->permission->full ?? false,
        'isUser' => $user->role->permission->modify ?? false
        ]
        ]);
    }
}
