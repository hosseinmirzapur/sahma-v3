<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchActionRequest;
use App\Models\EntityGroup;
use App\Models\Folder;
use App\Models\User;
use App\Services\DashboardUtilityService;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Morilog\Jalali\Jalalian;
use Throwable;

class DashboardController extends Controller
{
    /**
     *
     * @param Request $request
     * @return Response|ResponseFactory
     * @throws Exception
     */
    public function dashboard(Request $request): Response|ResponseFactory
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $rootFolders = Folder::query()
            ->select(['id', 'name', 'parent_folder_id', 'deleted_at', 'archived_at'])
            ->whereNull(['parent_folder_id', 'deleted_at', 'archived_at'])
            ->get()
            ->map(function (Folder $folder) {
                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'slug' => $folder->getFolderId()
                ];
            });

        /** @var EntityGroup[]|Collection $entityGroups */
        $entityGroups = $user->queryDepartmentFiles(null)
            ->whereNull('entity_groups.parent_folder_id')
            ->whereNull('entity_groups.deleted_at')
            ->whereNull('entity_groups.archived_at')
            ->select([
                'entity_groups.id',
                'entity_groups.name',
                'entity_groups.type',
                'entity_groups.status',
                'entity_groups.description',
                'entity_groups.archived_at',
                'entity_groups.deleted_at',
                'entity_groups.parent_folder_id',
            ])
            ->distinct()
            ->get();

        $files = $entityGroups->map(function (EntityGroup $entityGroup) {
            return [
                'id' => $entityGroup->id,
                'name' => $entityGroup->name,
                'type' => $entityGroup->type,
                'status' => $entityGroup->status,
                'slug' => $entityGroup->getEntityGroupId(),
                'description' => $entityGroup->description,
                'parentSlug' => optional($entityGroup->parentFolder)->getFolderId()/** @phpstan-ignore-line */
            ];
        })->toArray();

        $zipFileInfo = strval(session('zipFileInfo', null));
        session()->forget('zipFileInfo');

        return inertia('Dashboard/DocManagement/index', [
            'folders' => $rootFolders,
            'files' => $files,
            'breadcrumbs' => null,
            'zipFileInfo' => json_decode($zipFileInfo, true)
        ]);
    }

    public function searchForm(): Response|ResponseFactory
    {
        $extensions = [];
        $mimeTypes = (array)config('mime-type');
        foreach ($mimeTypes as $category => $subArray) {
            $extensions[$category] = array_keys((array)$subArray);
        }
        return inertia('Dashboard/DocManagement/SearchPage', [
            'files' => ['empty'],
            'extensions' => $extensions
        ]);
    }

    /**
     * @throws Exception
     */
    public function searchAction(SearchActionRequest $request): Response|ResponseFactory
    {
        // included validation on its own file
        $request->validated();

        $fileStatus = strval($request->input('fileStatus'));
        $fileType = strval($request->input('fileType'));
        $fileExtensions = (array)$request->input('fileExtension', []);
        $filename = $request->input('fileName');
        $searchableText = strval($request->input('searchable_text'));
        $adminType = strval($request->input('adminType'));
        $adminIdentifier = strval($request->input('adminIdentifier'));
        $departments = (array)$request->input('departments');

        $user = $request->user();

        $entityGroups = EntityGroup::query()
            ->select('entity_groups.*')
            ->join(
                'department_files',
                'department_files.entity_group_id',
                '=',
                'entity_groups.id'
            )
            ->whereNull('entity_groups.deleted_at')
            ->distinct();

        if (!empty($departments)) {
            $entityGroups = $entityGroups->whereIn('department_files.department_id', $departments);
        }

        $entityGroups = match ($fileStatus) {
            'transcribed' => $entityGroups->where(
                'entity_groups.status',
                EntityGroup::STATUS_TRANSCRIBED,
            ),
            'not_transcribed' => $entityGroups->where(
                'entity_groups.status',
                '<>',
                EntityGroup::STATUS_TRANSCRIBED
            ),
            default => $entityGroups
        };
        $entityGroups = match ($fileType) {
            'image' => $entityGroups->where('entity_groups.type', 'image'),
            'word' => $entityGroups->where('entity_groups.type', 'word'),
            'book' => $entityGroups->where('entity_groups.type', 'pdf'),
            'voice' => $entityGroups->where('entity_groups.type', 'voice'),
            'video' => $entityGroups->where('entity_groups.type', 'video'),
            default => $entityGroups
        };

        if (!empty($fileExtensions)) {
            $entityGroups->where(function ($query) use ($fileExtensions) {
                foreach ($fileExtensions as $extension) {
                    $extension = strval($extension);
                    $query->orWhere('name', 'LIKE', "%.$extension");
                }
            });
        }

        if (!is_null($filename)) {
            $entityGroups->where('entity_groups.name', 'LIKE', '%' . strval($filename) . '%');
        }
        $fromDate = $request->input('fromDate');
        $toDate = $request->input('toDate');

        if (!is_null($fromDate) && !is_null($toDate)) {
            $fromDate = (Jalalian::fromFormat('Y-m-d', strval($fromDate)))->toCarbon()->toDateString();
            $toDate = (Jalalian::fromFormat('Y-m-d', strval($toDate)))->toCarbon()->toDateString();
            $fromDate = strval($fromDate) . ' 00:00:00';
            $toDate = strval($toDate) . ' 23:59:59';
            $entityGroups->whereBetween('entity_groups.created_at', [$fromDate, $toDate]);
        }
        if ($searchableText) {
            $entityGroups->textSearch($searchableText);
        }

        if ($adminType != 'all') {
            $entityGroups = match ($adminType) {
                'owner' => $entityGroups->where('user_id', $user->id),
                'other' => $entityGroups->where('user_id', '!=', $user->id),
                'identifier' => $entityGroups->join(
                    'users',
                    'users.id',
                    '=',
                    'entity_groups.user_id'
                )->where('users.name', 'LIKE', "%$adminIdentifier%")
                    ->orWhere('personal_id', 'LIKE', "%$adminIdentifier%"),
                default => throw ValidationException::withMessages(['message' => 'unsupported identifier admin.'])
            };
        }
        $entityGroups = $entityGroups->distinct()->get();
        $files = $entityGroups->map(function (EntityGroup $eg) use ($searchableText) {
            return [
                'id' => $eg->id,
                'name' => $eg->name,
                'type' => $eg->type,
                'status' => $eg->status,
                'slug' => $eg->getEntityGroupId(),
                'searchable_text' => $searchableText
            ];
        });

        $extensions = [];
        $mimeTypes = (array)config('mime-type');
        foreach ($mimeTypes as $category => $subArray) {
            $extensions[$category] = array_keys((array)$subArray);
        }

        return inertia(
            'Dashboard/DocManagement/SearchPage',
            [
                'files' => $files,
                'searchableText' => $searchableText,
                'extensions' => $extensions
            ]
        );
    }

    /**
     * @throws Throwable
     */
    public function copy(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
            'destinationFolder' => 'nullable|integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $folderId = $request->input('destinationFolder', null);
        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        if (!empty($selectedFoldersId)) {
            $utilityService->copySelectedFolders(
                $selectedFoldersId,
                $user,
                $folderId == null ? null : strval($folderId)
            );
        }

        if (!empty($selectedFilesId)) {
            $utilityService->copySelectedFiles(
                $selectedFilesId,
                $user,
                $folderId == null ? null : strval($folderId)
            );
        }

        return redirect($folderId ?
            $utilityService->getRedirectRouteAfterOperation($folderId) : /** @phpstan-ignore-line */
            route('web.user.dashboard.index'));
    }

    /**
     * @throws Throwable
     */
    public function move(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
            'destinationFolder' => 'nullable|integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $folderId = $request->input('destinationFolder', null);
        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        if (!empty($selectedFoldersId)) {
            $utilityService->moveSelectedFolders(
                $selectedFoldersId,
                $user,
                $folderId == null ? null : strval($folderId)
            );
        }

        if (!empty($selectedFilesId)) {
            $utilityService->moveSelectedFiles(
                $selectedFilesId,
                $user,
                $folderId == null ? null : strval($folderId)
            );
        }
        return redirect($folderId ?
            $utilityService->getRedirectRouteAfterOperation($folderId) : /** @phpstan-ignore-line */
            route('web.user.dashboard.index'));
    }

    /**
     * @throws ValidationException
     */
    public function permanentDelete(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        if (!$user->is_super_admin) {
            throw ValidationException::withMessages([
                'message' => 'دسترسی برای حذف دائمی فقط توسط مدیر سیستم امکان پذیر هست'
            ]);
        }
        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        if (!empty($selectedFoldersId)) {
            $utilityService->permanentDeleteSelectedFolders($selectedFoldersId, $user);
        }

        if (!empty($selectedFilesId)) {
            $utilityService->permanentDeleteSelectedFiles($selectedFilesId, $user);
        }

        return redirect()->back();
    }

    /**
     * @throws ValidationException
     */
    public function trashAction(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        if (!empty($selectedFoldersId)) {
            $utilityService->trashSelectedFolders($selectedFoldersId, $user);
        }

        if (!empty($selectedFilesId)) {
            $utilityService->trashSelectedFiles($selectedFilesId, $user);
        }

        return redirect()->back()->with(['message' => 'موارد انتخاب شده با موفقیت حذف شدند']);
    }

    /**
     * @throws ValidationException
     */
    public function trashRetrieve(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        if (!empty($selectedFoldersId)) {
            $utilityService->retrieveTrashSelectedFolders($selectedFoldersId, $user);
        }

        if (!empty($selectedFilesId)) {
            $utilityService->retrieveTrashSelectedFiles($selectedFilesId, $user);
        }

        return redirect()->back()->with(['message' => 'موارد انتخاب شده با موفقیت بازگردانی شدند']);
    }

    /**
     * @throws ValidationException
     */
    public function archiveAction(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        if (!empty($selectedFoldersId)) {
            $utilityService->archiveSelectedFolders($selectedFoldersId, $user);
        }

        if (!empty($selectedFilesId)) {
            $utilityService->archiveSelectedFiles($selectedFilesId, $user);
        }

        return redirect()->back()->with(['message' => 'موارد انتخاب شده با موفقیت آرشیو شدند']);
    }

    /**
     * @throws ValidationException
     */
    public function archiveRetrieve(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        if (!empty($selectedFoldersId)) {
            $utilityService->retrieveArchiveSelectedFolders($selectedFoldersId, $user);
        }

        if (!empty($selectedFilesId)) {
            $utilityService->retrieveArchiveSelectedFiles($selectedFilesId, $user);
        }

        return redirect()->back()->with(['message' => 'موارد انتخاب شده با موفقیت بازگردانی شدند']);
    }

    /**
     * @throws ValidationException
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function createZip(Request $request, DashboardUtilityService $utilityService): RedirectResponse
    {
        $request->validate([
            'folders' => 'nullable|array',
            'folders.*' => 'integer',
            'files' => 'nullable|array',
            'files.*' => 'integer',
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $selectedFoldersId = (array)$request->input('folders', []);
        $selectedFilesId = (array)$request->input('files', []);

        if (empty($selectedFilesId) && empty($selectedFoldersId)) {
            throw ValidationException::withMessages(['message' => 'لظفا فایل یا فولدر مورد نظر را انتخاب کنید.']);
        }

        $zipPath = $utilityService->downloadZipFile($user, $selectedFoldersId, $selectedFilesId);
        $zipFileName = pathinfo($zipPath, PATHINFO_BASENAME);

        $entityGroup = EntityGroup::createWithSlug([
            'user_id' => $user->id,
            'name' => $zipFileName,
            'type' => 'zip',
            'status' => EntityGroup::STATUS_ZIPPED,
            'file_location' => $zipPath
        ]);

        $downloadUrl = strval(
            route(
                'web.user.dashboard.file.download.original-file',
                ['fileId' => $entityGroup->getEntityGroupId()]
            )
        );
        $zipFileInfo = [
            'downloadUrl' => $downloadUrl,
            'zipFileSize' => $entityGroup->getFileSizeHumanReadable(
                intval(Storage::disk($entityGroup->type)->size($entityGroup->file_location))
            ),
            'zipFileName' => $zipFileName
        ];
        return redirect()->back()->with(['zipFileInfo' => json_encode($zipFileInfo)]);
    }

    public function archiveList(): Response|ResponseFactory
    {
        $folders = Folder::query()
            ->select(['id', 'name', 'archived_at'])
            ->whereNotNull('archived_at')
            ->get()
            ->map(function (Folder $folder) {
                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'slug' => $folder->getFolderId()
                ];
            });

        $files = EntityGroup::query()
            ->select(['id', 'type', 'name', 'archived_at'])
            ->whereNotNull('archived_at')
            ->get()
            ->map(function (EntityGroup $file) {
                return [
                    'id' => $file->id,
                    'type' => $file->type,
                    'name' => $file->name,
                    'slug' => $file->getEntityGroupId()
                ];
            });

        return inertia('Dashboard/DocManagement/Archive', [
            'folders' => $folders,
            'files' => $files
        ]);
    }

    public function trashList(): Response|ResponseFactory
    {
        $folders = Folder::query()
            ->select(['id', 'name', 'deleted_at'])
            ->whereNotNull('deleted_at')
            ->get()
            ->map(function (Folder $folder) {
                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'slug' => $folder->getFolderId()
                ];
            });

        $files = EntityGroup::query()
            ->select(['id', 'type', 'name', 'deleted_at'])
            ->whereNotNull('deleted_at')
            ->get()
            ->map(function (EntityGroup $file) {
                return [
                    'id' => $file->id,
                    'type' => $file->type,
                    'name' => $file->name,
                    'slug' => $file->getEntityGroupId()
                ];
            });

        return inertia('Dashboard/DocManagement/Archive', [
            'folders' => $folders,
            'files' => $files,
            'status' => 'trash'
        ]);
    }
}
