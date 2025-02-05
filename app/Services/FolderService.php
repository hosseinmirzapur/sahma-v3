<?php

namespace App\Services;

use App\Models\EntityGroup;
use App\Models\Folder;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;

class FolderService
{
    /**
     * @param Folder $folder
     * @return void
     */
    public function deleteFolderRecursive(Folder $folder): void
    {
        Log::info("Starting deletion of Folder:#$folder->id-$folder->name and its contents.");

        // Load all related subFolders and files in a single query
        $folder->load(['subFolders', 'files']);

        // Soft-delete all files in the folder in bulk
        EntityGroup::query()->where('parent_folder_id', $folder->id)
            ->update([
                'deleted_at' => now(),
                'deleted_by' => $folder->deleted_by
            ]);

        Log::info("Soft-deleted all files in Folder:#$folder->id.");

        // Soft-delete all subFolders in bulk
        Folder::query()->where('parent_folder_id', $folder->id)
            ->update([
                'deleted_at' => now(),
                'deleted_by' => $folder->deleted_by
            ]);

        Log::info("Soft-deleted all subfolders in Folder:#$folder->id.");

        // Dispatch jobs for each subfolder to delete their contents recursively
        foreach ($folder->subFolders as $subFolder) {
            Log::info("Dispatching recursive deletion for SubFolder:#$subFolder->id-$subFolder->name.");

            dispatch(function () use ($subFolder) {
                try {
                    /** @var FolderService $folderService */
                    $folderService = app()->make(FolderService::class);
                    $folderService->deleteFolderRecursive($subFolder);
                } catch (BindingResolutionException $e) {
                    Log::error("Error resolving FolderService: " . $e->getMessage());
                }
            })->onQueue('folder::delete-sub-folders-and-files');
        }
    }
}
