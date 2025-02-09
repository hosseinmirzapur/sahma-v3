<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\EntityGroup;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

class ArchiveController extends Controller
{
    public function __construct()
    {
        $this->middleware('convert.obfuscatedId-folder')->only(['retrievalFolder']);
        $this->middleware('convert.obfuscatedId-entityGroup')->only(['retrievalFile']);
    }

    public function index(): Response|ResponseFactory
    {
        $folders = Folder::query()
            ->whereNotNull('archived_at')
            ->select(['id', 'name'])
            ->get()
            ->map(function (Folder $folder) {
                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'slug' => $folder->getFolderId()
                ];
            });

        $files = EntityGroup::query()
            ->whereNotNull('archived_at')
            ->select(['id', 'type', 'name'])
            ->get()
            ->map(function (EntityGroup $file) {
                return [
                    'id' => $file->id,
                    'type' => $file->type,
                    'name' => $file->name,
                    'slug' => $file->getEntityGroupId()
                ];
            });

        return inertia('Dashboard/Archive', [
            'folders' => $folders,
            'files' => $files
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function retrievalFolder(Request $request): RedirectResponse
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
            $folder = Folder::query()
                ->where('id', $folder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $folder->update([
                'deleted_at' => null,
                'deleted_by' => null
            ]);

            $description = "پوشه $folder->name توسط کاربر  $user->name با کد پرسنلی
             $user->personal_id بازگردانی شد .";

            Activity::query()->create([
                'user_id' => $user->id,
                'description' => $description,
                'status' => Activity::TYPE_RETRIEVAL,
                'activity_id' => $folder->id,
                'activity_type' => Folder::class,
            ]);

            return $folder;
        }, 3);
        Log::info("Folder:#$folder->id-$folder->name has been retrieval by User::#$folder->deleted_by");

        $folder->retrieveSubFoldersAndFiles($folder, $user);

        return redirect()->back()->with(['message' => 'پوشه با موفقیت بازیابی شد.']);
    }

    public function retrievalFile(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        /** @var EntityGroup $entityGroup */
        $entityGroup = $request->attributes->get('entityGroup');

        $entityGroup = DB::transaction(function () use ($user, $entityGroup) {
            $entityGroup = EntityGroup::query()
                ->where('id', $entityGroup->id)
                ->lockForUpdate()
                ->firstOrFail();
            $entityGroup->update([
                'deleted_at' => null,
                'deleted_by' => null
            ]);
            $description = "فایل $entityGroup->name توسط کاربر  $user->name با کد پرسنلی
             $user->personal_id بازگردانی شد .";

            Activity::query()->create([
                'user_id' => $user->id,
                'description' => $description,
                'status' => Activity::TYPE_RETRIEVAL,
                'activity_id' => $entityGroup->id,
                'activity_type' => EntityGroup::class,
            ]);

            return $entityGroup;
        }, 3);

        Log::info(
            "EntityGroup:#$entityGroup->id-$entityGroup->name has
            been retrieval by User::#$entityGroup->deleted_by"
        );

        return redirect()->back()->with(['message' => 'فایل با موفقیت بازیابی شد.']);
    }
}
