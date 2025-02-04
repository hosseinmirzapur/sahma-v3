<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Department;
use App\Models\DepartmentFile;
use App\Models\EntityGroup;
use App\Models\User;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function getUserInfo(User $adminUser): array
    {
        if ($adminUser->is_super_admin) {
            $users = User::query()->whereNull('deleted_at')->get();
        } else {
            $users = User::query()
            ->where('created_by', $adminUser->id)
            ->whereNull('deleted_at')
            ->get();
        }

        return $this->getUsersDepartments($users);
    }

    public function getUsersDepartments(Collection $users): array
    {
        $usersInfo = [];
        /* @var User $user */
        foreach ($users as $user) {
            $departments = Department::query()->select('departments.id', 'departments.name')
                ->join(
                    'department_users',
                    'department_users.department_id',
                    '=',
                    'departments.id'
                )->where('department_users.user_id', $user->id)->get()->toArray(); /** @phpstan-ignore-line */
            if ($user->is_super_admin) {/** @phpstan-ignore-line  */
                $permission = 'super_admin';
            } elseif ($user->role->permission->full) { /** @phpstan-ignore-line  */
                $permission = 'full';
            } elseif ($user->role->permission->modify) { /** @phpstan-ignore-line  */
                $permission = 'modify';
            } else {
                $permission = 'read_only';
            }
            $usersInfo [] =
                [
                    'id' => $user->id,/** @phpstan-ignore-line  */
                    'name' => $user->name, /** @phpstan-ignore-line  */
                    'personalId' => $user->personal_id, /** @phpstan-ignore-line  */
                    'password' => $user->password, /** @phpstan-ignore-line  */
                    'roleTitle' => $user->role->title, /** @phpstan-ignore-line  */
                    'departments' => $departments,
                    'permission' => $permission
                ];
        }
        return $usersInfo;
    }
}
