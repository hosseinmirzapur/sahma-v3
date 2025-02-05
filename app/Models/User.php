<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Database\Factories\UserFactory;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property int $personal_id
 * @property string|null $email
 * @property mixed $password
 * @property int $role_id
 * @property array|null $meta
 * @property int $is_super_admin
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read Collection<int, Activity> $activity
 * @property-read int|null $activity_count
 * @property-read Collection<int, Letter> $letters
 * @property-read int|null $letters_count
 * @property-read Collection<int, Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Role $role
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read Collection<int, DepartmentUser> $userDepartments
 * @property-read int|null $user_departments_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User query()
 * @method static Builder|User whereCreatedAt($value)
 * @method static Builder|User whereDeletedAt($value)
 * @method static Builder|User whereEmail($value)
 * @method static Builder|User whereId($value)
 * @method static Builder|User whereIsSuperAdmin($value)
 * @method static Builder|User whereMeta($value)
 * @method static Builder|User whereName($value)
 * @method static Builder|User wherePassword($value)
 * @method static Builder|User wherePersonalId($value)
 * @method static Builder|User whereRememberToken($value)
 * @method static Builder|User whereRoleId($value)
 * @method static Builder|User whereUpdatedAt($value)
 * @mixin Eloquent
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
    protected $fillable = [
    'name',
    'email',
    'personal_id',
    'password',
    'deleted_at',
    'role_id',
    'meta',
    'is_super_admin',
    'created_by'
    ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
    protected $hidden = [
    'password',
    'remember_token',
    ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
    protected $casts = [
    'password' => 'hashed',
    'meta' => 'json'
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function userDepartments(): HasMany
    {
        return $this->hasMany(DepartmentUser::class);
    }

    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activity');
    }

  /**
   * @return HasMany
   */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * @throws Exception
     */
    public function getAllAvailableFilesAsArray(Folder $folder): array
    {
      /** @var EntityGroup[]|Collection $entityGroups */
        $entityGroups = $this->queryDepartmentFiles($folder)
            ->whereNull('entity_groups.deleted_at')
            ->whereNull('entity_groups.archived_at')
            ->distinct()
            ->get();

        return $entityGroups->map(function (EntityGroup $entityGroup): array {
            return [
            'id' => $entityGroup->id,
            'name' => $entityGroup->name,
            'type' => $entityGroup->type,
            'status' => $entityGroup->status,
            'slug' => $entityGroup->getEntityGroupId(),
            'description' => $entityGroup->description,
            'parentSlug' => $entityGroup->parentFolder
            ? route('web.user.dashboard.folder.show', ['folderId' => $entityGroup->parentFolder->getFolderId()])
            : null,
            'departments' => $entityGroup->getEntityGroupDepartments()
            ];
        })->toArray();
    }

    public function queryDepartmentFiles(Folder|null $parentFolder): Builder|EntityGroup
    {
        $departments = $this->userDepartments()->pluck('department_users.department_id')->toArray();
        return EntityGroup::query()
            ->select('entity_groups.*')
            ->join(
                'department_files',
                'department_files.entity_group_id',
                '=',
                'entity_groups.id'
            )
            ->where('entity_groups.parent_folder_id', $parentFolder?->id)
            ->whereIn('department_files.department_id', $departments);
    }

    public function getUserDepartmentIds(): array
    {
        return Department::query()->select('departments.*')
        ->join('department_users', 'department_users.department_id', '=', 'departments.id')
        ->where('department_users.user_id', '=', $this->id)
         ->pluck('departments')
         ->toArray();
    }
}
