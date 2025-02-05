<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function getUserInfo(User $adminUser): array
    {
        $usersQuery = User::query()->whereNull('deleted_at');

        if (!$adminUser->is_super_admin) {
            $usersQuery->where('created_by', $adminUser->id);
        }

        $users = $usersQuery->with(['role.permission', 'departments:id,name'])->get();

        return $this->getUsersDepartments($users);
    }

    /**
     * @param Collection<int, User> $users
     * @return array
     */
    public function getUsersDepartments(Collection $users): array
    {
        return $users->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'personalId' => $user->personal_id,
                'roleTitle' => $user->role->title,
                'departments' => $user->userDepartments->toArray(),
                'permission' => $this->determinePermission($user),
            ];
        })->toArray();
    }

    private function determinePermission(User $user): string
    {
        if ($user->is_super_admin) {
            return 'super_admin';
        }

        return match (true) {
            (bool)$user->role->permission->full => 'full',
            (bool)$user->role->permission->modify => 'modify',
            default => 'read_only',
        };
    }
}
