<?php

namespace App\Services;

use App\Models\EntityGroup;
use App\Models\Folder;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;

class FolderService
{
    public function deleteFolderRecursive(Folder $folder): void
    {
        Log::info("Folder:#$folder->id-$folder->name START to delete it's subFolders and files.");
      // Get all subFolders of the current folder
        $subFolders = Folder::query()->where('parent_folder_id', $folder->id)->get();
        $subFiles = EntityGroup::query()->where('parent_folder_id', $folder->id)->get();

        foreach ($subFiles as $file) {
            $file->deleted_at = now();
            $file->deleted_by = $folder->deleted_by;
            $file->save();
            Log::info("File:#$file->id-$file->name has been temporary deleted by User::#$folder->deleted_by");
        }

      // If the current folder has subFolders, recursively delete them
      /* @var Folder $subFolder */
        foreach ($subFolders as $subFolder) {
            $subFolder->deleted_at = now();
            $subFolder->deleted_by = $folder->deleted_by;
            $subFolder->save();
            Log::info(
                "SubFolder:#$subFolder->id-$subFolder->name has been temporary deleted
                 by User::#$folder->deleted_by"
            );
            Log::info(
                "Folder:#$subFolder->id-$subFolder->name IS_GOING_TO delete it's subFolders and files."
            );
            dispatch(
          /**
           * @throws BindingResolutionException
           */
                function () use ($folder) {
                    /**
                    * @var FolderService $folderService
                    */
                    $folderService = app()->make(FolderService::class);
                    $folderService->deleteFolderRecursive($folder);
                }
            )->onQueue('folder::delete-sub-folders-and-files');
        }
    }
}
