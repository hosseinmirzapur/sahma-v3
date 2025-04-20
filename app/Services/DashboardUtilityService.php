<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\DepartmentFile;
use App\Models\EntityGroup;
use App\Models\Folder;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DashboardUtilityService
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function copySelectedFolders(array $selectedFoldersId, User $user, ?string $folderId): void
    {
        foreach (Folder::query()->whereIn('id', $selectedFoldersId)->get() as $selectedFolder) {
            $newFolder = Folder::createWithSlug([
                'name' => $selectedFolder->name,
                'user_id' => $user->id,
                'parent_folder_id' => $folderId,
            ]);

            $selectedFolder->replicateSubFoldersAndFiles($newFolder);

            $description = sprintf(
                'کاربر %s با کد پرسنلی %s پوشه %s را کپی کرد.',
                $user->name,
                $user->personal_id,
                $newFolder->name
            );

            foreach ([$selectedFolder, $newFolder] as $folder) {
                $this->activityService->logUserAction($user, Activity::TYPE_COPY, $folder, $description);
            }
        }
    }


    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function copySelectedFiles(array $selectedFilesId, User $user, ?string $folderId): void
    {
        foreach (EntityGroup::query()->whereIn('id', $selectedFilesId)->get() as $selectedFile) {
            $data = array_merge(
                $selectedFile->getAttributes(),
                [
                    'user_id' => $user->id,
                    'parent_folder_id' => $folderId,
                ]
            );

            $newEntityGroup = EntityGroup::createWithSlug($data);

            $departments = $selectedFile->getEntityGroupDepartments();
            $departmentData = array_map(fn($department) => [
                'entity_group_id' => $newEntityGroup->id,
                'department_id' => $department['id'],
            ], $departments);

            DepartmentFile::query()->insert($departmentData);

            $description = sprintf(
                'کاربر %s با کد پرسنلی %s پوشه %s را کپی کرد.',
                $user->name,
                $user->personal_id,
                $newEntityGroup->name
            );

            foreach ([$selectedFile, $newEntityGroup] as $file) {
                $this->activityService->logUserAction($user, Activity::TYPE_COPY, $file, $description);
            }
        }
    }


    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function moveSelectedFolders(array $selectedFoldersId, User $user, ?string $folderId): void
    {
        $folders = Folder::query()->whereIn('id', $selectedFoldersId)->get();

        if ($folders->isEmpty()) {
            throw ValidationException::withMessages(['message' => 'پوشه‌های مورد نظر یافت نشدند.']);
        }

        foreach ($folders as $folder) {
            $folder->update(['parent_folder_id' => $folderId]);

            $description = sprintf(
                'کاربر %s با کد پرسنلی %s پوشه %s را انتقال داد.',
                $user->name,
                $user->personal_id,
                $folder->name
            );

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $folder, $description);
        }
    }


    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function moveSelectedFiles(array $selectedFilesId, User $user, ?string $folderId): void
    {
        $files = EntityGroup::query()->whereIn('id', $selectedFilesId)->get();

        if ($files->isEmpty()) {
            throw ValidationException::withMessages(['message' => 'فایل‌های مورد نظر یافت نشدند.']);
        }

        foreach ($files as $file) {
            $file->update(['parent_folder_id' => $folderId]);

            $description = sprintf(
                'کاربر %s با کد پرسنلی %s فایل %s را انتقال داد.',
                $user->name,
                $user->personal_id,
                $file->name
            );

            $this->activityService->logUserAction($user, Activity::TYPE_MOVE, $file, $description);
        }
    }


    /**
     * @param array $selectedFoldersId
     * @param User $user
     * @return void
     */
    public function permanentDeleteSelectedFolders(array $selectedFoldersId, User $user): void
    {
        DB::transaction(function () use ($user, $selectedFoldersId) {
            $folders = Folder::query()->whereIn('id', $selectedFoldersId)->lockForUpdate()->get();

            if ($folders->isEmpty()) {
                throw ValidationException::withMessages(['message' => 'پوشه‌های مورد نظر یافت نشدند.']);
            }

            foreach ($folders as $folder) {
                $this->deleteChildEntityGroupsAndSubFolders($folder, $user);
            }
        }, 3);
    }


    /**
     * @param array $selectedFilesId
     * @param User $user
     */
    public function permanentDeleteSelectedFiles(array $selectedFilesId, User $user): void
    {
        /* @var FileService $fileService */
        $fileService = app(FileService::class);

        DB::transaction(function () use ($user, $selectedFilesId, $fileService) {
            $files = EntityGroup::query()->whereIn('id', $selectedFilesId)->lockForUpdate()->get();

            if ($files->isEmpty()) {
                throw ValidationException::withMessages(['message' => 'فایل‌های مورد نظر یافت نشدند.']);
            }

            foreach ($files as $file) {
                $fileService->deleteEntityGroupAndEntitiesAndFiles($file, $user);
            }
        }, 3);
    }

    private function logger(
        User $user,
        Folder|EntityGroup $item,
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
            $item->name,
            $actionType
        );

        $this->activityService->logUserAction($user, $type, $item, $description);
    }


    /**
     * @throws BindingResolutionException
     */
    private function deleteChildEntityGroupsAndSubFolders(Folder $folder, User $user): void
    {
        $entityGroups = EntityGroup::query()->where('parent_folder_id', $folder->id)->get();

        /* @var  FileService $fileService */
        $fileService = app()->make(FileService::class);

        foreach ($entityGroups as $entityGroup) {
            $fileService->deleteEntityGroupAndEntitiesAndFiles($entityGroup, $user);
            $this->logger($user, $entityGroup, Activity::TYPE_DELETE);
        }

        $subFolders = Folder::query()->where('parent_folder_id', $folder->id)->get();

        foreach ($subFolders as $subFolder) {
            $this->deleteChildEntityGroupsAndSubFolders($subFolder, $user);
            $this->logger($user, $subFolder, Activity::TYPE_DELETE);
        }

        $folder->delete();
    }

    public function trashSelectedFolders(array $selectedFoldersId, User $user): void
    {
        Folder::query()
            ->whereIn('id', $selectedFoldersId)
            ->lockForUpdate()
            ->each(function (Folder $folder) use ($user) {
                $folder->update([
                    'archived_at' => null,
                    'deleted_at' => now()
                ]);
                $this->logger($user, $folder, Activity::TYPE_DELETE);
            });
    }

    public function trashSelectedFiles(array $selectedFilesId, User $user): void
    {
        EntityGroup::query()
            ->whereIn('id', $selectedFilesId)
            ->lockForUpdate()
            ->each(function (EntityGroup $entityGroup) use ($user) {
                $entityGroup->update([
                    'deleted_at' => now(),
                    'archived_at' => null,
                ]);
                $this->logger($user, $entityGroup, Activity::TYPE_DELETE);
            });
    }

    public function retrieveTrashSelectedFolders(array $selectedFoldersId, User $user): void
    {
        Folder::query()
            ->whereIn('id', $selectedFoldersId)
            ->lockForUpdate()
            ->each(function (Folder $folder) use ($user) {
                $folder->update([
                    'deleted_at' => null,
                ]);
                $this->logger($user, $folder, Activity::TYPE_RETRIEVAL);
            });
    }

    public function retrieveTrashSelectedFiles(array $selectedFilesId, User $user): void
    {
        EntityGroup::query()
            ->whereIn('id', $selectedFilesId)
            ->lockForUpdate()
            ->each(function (EntityGroup $entityGroup) use ($user) {
                $entityGroup->update([
                    'deleted_at' => null,
                ]);
                $this->logger($user, $entityGroup, Activity::TYPE_RETRIEVAL);
            });
    }

    public function archiveSelectedFolders(array $selectedFoldersId, User $user): void
    {
        Folder::query()
            ->whereIn('id', $selectedFoldersId)
            ->lockForUpdate()
            ->each(function (Folder $folder) use ($user) {
                $folder->update([
                    'archived_at' => now(),
                ]);
                $this->logger($user, $folder, Activity::TYPE_ARCHIVE);
            });
    }

    public function archiveSelectedFiles(array $selectedFilesId, User $user): void
    {
        EntityGroup::query()
            ->where('id', $selectedFilesId)
            ->lockForUpdate()
            ->each(function (EntityGroup $entityGroup) use ($user) {
                $entityGroup->update([
                    'archived_at' => now(),
                ]);
                $this->logger($user, $entityGroup, Activity::TYPE_ARCHIVE);
            });
    }

    public function retrieveArchiveSelectedFolders(array $selectedFoldersId, User $user): void
    {
        Folder::query()
            ->whereIn('id', $selectedFoldersId)
            ->lockForUpdate()
            ->each(function (Folder $folder) use ($user) {
                $folder->update([
                    'archived_at' => null,
                ]);
                $this->logger($user, $folder, Activity::TYPE_RETRIEVAL);
            });
    }

    public function retrieveArchiveSelectedFiles(array $selectedFilesId, User $user): void
    {
        EntityGroup::query()
            ->where('id', $selectedFilesId)
            ->lockForUpdate()
            ->each(function (EntityGroup $entityGroup) use ($user) {
                $entityGroup->update([
                    'archived_at' => null,
                ]);
                $this->logger($user, $entityGroup, Activity::TYPE_RETRIEVAL);
            });
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function downloadZipFile(User $user, array $folderIds, array $fileIds): string
    {
        $now = now();
        $todayDate = $now->toDateString();
        $folderName = uniqid("$user->id-");
        $baseFolder = "$todayDate/$folderName";

        Storage::disk('zip')->makeDirectory($baseFolder);

        DB::transaction(function () use ($folderIds, $fileIds, $baseFolder) {
            Folder::query()
                ->whereIn('id', $folderIds)
                ->lock()
                ->each(function (Folder $folder) use ($baseFolder) {
                    $baseFolderDir = "$baseFolder/$folder->name";
                    Storage::disk('zip')->makeDirectory($baseFolderDir);
                    $folder->retrieveSubFoldersAndFilesForDownload($baseFolderDir);
                });

            EntityGroup::query()
                ->whereIn('id', $fileIds)
                ->lock()
                ->each(function (EntityGroup $entityGroup) use ($baseFolder) {
                    // Determine the correct disk and path for the entityGroup
                    $originalDisk = match ($entityGroup->type) {
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
                    $originalPath = $entityGroup->file_location;

                    // Check if the file exists on the original disk
                    if (Storage::disk($originalDisk)->exists($originalPath)) {
                        // Copy the raw file content to the temporary directory
                        $tempPath = "$baseFolder/" . $entityGroup->name;
                        Storage::disk('zip')->put($tempPath, Storage::disk($originalDisk)->get($originalPath));
                    } else {
                        // Handle the case where the file is missing on the original disk
                        Log::error("File not found on disk '$originalDisk' at path '$originalPath' for EntityGroup ID: {$entityGroup->id}");
                        // You might want to throw an exception or handle this case differently
                    }
                });
        }, 3);

        $baseFolderPath = Storage::disk('zip')->path($baseFolder);

        // Define the name for the output ZIP file
        $outputZipName = $baseFolderPath . '.zip';

        // Use the zip command to create a ZIP file
        $command = "cd $baseFolderPath && zip -r $outputZipName .";

        // Run the command
        $output = shell_exec($command);

        Storage::disk('zip')->deleteDirectory($baseFolder);

        if (!is_null($output)) {
            return "$baseFolder.zip";
        } else {
            throw ValidationException::withMessages(['message' => 'فرایند زیپ با موفقیت انجام نشد!']);
        }
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function getRedirectRouteAfterOperation(string $folderId): string
    {
        /* @var Folder $destinationFolder */
        $destinationFolder = Folder::query()->find($folderId);

        if (is_null($destinationFolder)) {
            throw ValidationException::withMessages(['message' => 'پوشه مورد نظر یافت نشد.']);
        }

        return route('web.user.dashboard.folder.show', ['folderId' => $destinationFolder->getFolderId()]);
    }
}
