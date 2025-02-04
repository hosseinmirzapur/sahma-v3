<?php

namespace App\Models;

use App\Helper\EncryptHelper;
use Barryvdh\LaravelIdeHelper\Eloquent;
use Database\Factories\FolderFactory;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * App\Models\Folder
 *
 * @property int $id
 * @property string $name
 * @property int|null $user_id
 * @property int|null $parent_folder_id
 * @property string|null $deleted_at
 * @property array|null $meta
 * @property string|null $archived_at
 * @property string|null $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activity
 * @property-read int|null $activity_count
 * @property-read Collection<int, EntityGroup> $entityGroups
 * @property-read int|null $entity_groups_count
 * @property-read User|null $user
 * @method static FolderFactory factory($count = null, $state = [])
 * @method static Builder|Folder newModelQuery()
 * @method static Builder|Folder newQuery()
 * @method static Builder|Folder query()
 * @method static Builder|Folder whereArchivedAt($value)
 * @method static Builder|Folder whereCreatedAt($value)
 * @method static Builder|Folder whereDeletedAt($value)
 * @method static Builder|Folder whereId($value)
 * @method static Builder|Folder whereMeta($value)
 * @method static Builder|Folder whereName($value)
 * @method static Builder|Folder whereParentFolderId($value)
 * @method static Builder|Folder whereSlug($value)
 * @method static Builder|Folder whereUpdatedAt($value)
 * @method static Builder|Folder whereUserId($value)
 * @mixin \Eloquent
 */
class Folder extends Model
{
    use HasFactory;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
    protected $fillable = [
    'name',
    'user_id',
    'parent_folder_id',
    'department_id',
    'deleted_at',
    'meta',
    'archived_at',
    ];
    protected $casts = [
    'meta' => 'json'
    ];

  /**
   * @return BelongsTo
   */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

  /**
   * @return HasMany
   */
    public function entityGroups(): HasMany
    {
        return $this->hasMany(EntityGroup::class);
    }

  /**
   * Always create Folder using this function.
   * It sets slug in transaction to make sure Folder will be created using slug.
   *
   * @throws Throwable
   */
    public static function createWithSlug(array $attributes): Folder
    {
      /** @var Folder $folder */
        $folder = DB::transaction(function () use ($attributes) {
          /* @var Folder $f */
            $f = Folder::query()->create($attributes);
            $f->slug = $f->getFolderId();
            $f->save();
            return $f;
        }, 3);

        return $folder;
    }

  /**
   * @throws Exception
   */
    public function getFolderId(): string
    {
        $paddedId = str_pad((string)$this->id, 12, '0', STR_PAD_LEFT);
        $encryptedId = EncryptHelper::encrypt($paddedId);
        return base64_encode($encryptedId);
    }

    public static function convertObfuscatedIdToFolderId(string $obfuscatedId): int
    {
        $base64Decoded = base64_decode($obfuscatedId);
        $decryptedId = EncryptHelper::decrypt($base64Decoded);
        return (int)$decryptedId;
    }

    public function parentFolder(): Folder
    {
        return Folder::query()->where('id', $this->parent_folder_id)->firstOrFail();
    }

    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activity');
    }

    public function subFolders(array $breadCrumb, int $currentFolderId = null): array
    {
        return Folder::query()->where('parent_folder_id', $this->id)
        ->whereNull('deleted_at')
        ->get()
        ->map(function (Folder $folder) use ($breadCrumb, $currentFolderId) {
            return [
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_folder_id' => $folder->parent_folder_id,
            'slug' => $folder->getFolderId(),
            'subFolders' => $folder->subFolders($breadCrumb),
            'isOpen' => in_array($folder->id, $breadCrumb) ||
            (!is_null($currentFolderId) && $folder->id == $currentFolderId)
            ];
        })->toArray();
    }

    public function tempDeleteSubFoldersAndFiles(Folder $folder, User $user): void
    {
        $subFolders = Folder::query()->where('parent_folder_id', $folder->id)->get();
        $now = now();
      /* @var Folder $subFolder */
        foreach ($subFolders as $subFolder) {
            $subFolder->deleted_at = $now;
            $subFolder->deleted_by = $user->id;
            $subFolder->save();
            $this->tempDeleteSubFoldersAndFiles($subFolder, $user);
        }

        $entityGroups = EntityGroup::query()->where('parent_folder_id', $folder->id)->get();

        foreach ($entityGroups as $entityGroup) {
            $entityGroup->deleted_at = $now;
            $entityGroup->deleted_by = $user->id;
            $entityGroup->save();
        }
    }

    public function retrieveSubFoldersAndFiles(Folder $folder, User $user): void
    {
        $subFolders = Folder::query()->where('parent_folder_id', $folder->id)->get();
      /* @var Folder $subFolder */
        foreach ($subFolders as $subFolder) {
            $subFolder->deleted_at = null;
            $subFolder->deleted_by = null;
            $subFolder->save();
            $this->retrieveSubFoldersAndFiles($subFolder, $user);
        }

        $entityGroups = EntityGroup::query()->where('parent_folder_id', $folder->id)->get();

        foreach ($entityGroups as $entityGroup) {
            $entityGroup->deleted_at = null;
            $entityGroup->deleted_by = null;
            $entityGroup->save();
        }
    }

  /**
   * @throws Exception
   */
    public function getParentFolders(Folder $folder, array $breadcrumbs): array
    {
      // Debug statement to see function call
        Log::info("Debug: Entering getParentFolders for folder '{$folder->name}'" . PHP_EOL);

        if (empty($breadcrumbs)) {
            $breadcrumbs[] = [
            'name' => $folder->name,
            'slug' => $folder->getFolderId(),
            'id' => $folder->id
            ];
        }

        if ($folder->parent_folder_id) {
            $parentFolder = $folder->parentFolder();

            $breadcrumbs[] = [
            'name' => $parentFolder->name,
            'slug' => $parentFolder->getFolderId(),
            'id' => $parentFolder->id  // Use the parent folder's ID here
            ];

          // Recursively continue with the parent folder
            return $this->getParentFolders($parentFolder, $breadcrumbs);
        }

      // Debug statement to see final breadcrumbs
        Log::info("Debug: Breadcrumbs for '{$folder->name}': " . print_r($breadcrumbs, true) . PHP_EOL);
        return $breadcrumbs;
    }

    public function getAllSubFoldersId(Folder $folder, array $arrayIds = []): array
    {
        $subFolders = Folder::query()->where('parent_folder_id', $folder->id)->get();
      /* @var Folder $subFolder */
        foreach ($subFolders as $subFolder) {
            $arrayIds [] = $subFolder->id;
            $this->getAllSubFoldersId($subFolder, $arrayIds);
        }

        return $arrayIds;
    }

  /**
   * @throws Throwable
   */
    public function replicateSubFoldersAndFiles(Folder $newFolder): void
    {
        $folders = Folder::query()
        ->where('parent_folder_id', $this->id)
        ->whereNull('deleted_at')
        ->get();

        $files = EntityGroup::query()->where('parent_folder_id', $this->id)->get();

        foreach ($files as $file) {
            $data = array_merge($file->getAttributes(), [
            'user_id' => $newFolder->user_id,
            'parent_folder_id' => $newFolder->id,
            ]);

            $newEntityGroup = EntityGroup::createWithSlug($data);
            $departments = $file->getEntityGroupDepartments();

            foreach ($departments as $department) {
                DepartmentFile::query()->create([
                'entity_group_id' => $newEntityGroup->id,
                'department_id' => $department['id']
                ]);
            }
        }

          /* @var Folder $folder */
        foreach ($folders as $folder) {
            $newSubFolder = Folder::createWithSlug([
            'name' => $folder->name,
            'user_id' => $newFolder->user_id,
            'parent_folder_id' => $newFolder->id,
            ]);

            $folder->replicateSubFoldersAndFiles($newSubFolder);
        }
    }

  /**
   * @throws Throwable
   */
    public function retrieveSubFoldersAndFilesForDownload(string $currentDirectory): void
    {
        $entityGroups = EntityGroup::query()->where('parent_folder_id', $this->id)->get();

        foreach ($entityGroups as $entityGroup) {
            $fileData = $entityGroup->generateFileDataForEmbedding(false);
            $fileContent = $fileData['fileContent'] ?? '';
            $fileName = $fileData['fileName'] ?? '';

            if (Storage::disk('zip')->put("$currentDirectory/$fileName", $fileContent) === false) {
                throw new Exception('Failed to write data for zip');
            }
        }

        $folders = Folder::query()
        ->where('parent_folder_id', $this->id)
        ->whereNull('deleted_at')
        ->get();

        /* @var Folder $folder */
        foreach ($folders as $folder) {
            Storage::disk('zip')->makeDirectory("$currentDirectory/$folder->name");

            $newDir = "$currentDirectory/$folder->name";
            $folder->retrieveSubFoldersAndFilesForDownload($newDir);
        }
    }
}
