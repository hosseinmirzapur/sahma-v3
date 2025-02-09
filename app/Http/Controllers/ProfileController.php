<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class ProfileController extends Controller
{
    public function show(Request $request): Response|ResponseFactory
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403, 'دسترسی لازم را ندارید.');
        }

        $departments = Department::query()
            ->select(['departments.id', 'departments.name'])
            ->join(
                'department_users',
                'department_users.department_id',
                '=',
                'departments.id'
            )
            ->where('department_users.user_id', '=', $user->id)
            ->get()
            ->map(function (Department $department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name
                ];
            });

        if ($user->is_super_admin) {
            $permission = 'مدیر سیستم';
        } elseif ($user->role->permission->full) {
            $permission = 'full';
        } elseif ($user->role->permission->modify) {
            $permission = 'modify';
        } else {
            $permission = 'read_only';
        }

        return inertia('Dashboard/UserManagement/Profile', [
            'user' => $user,
            'departments' => $departments,
            'role' => $user->role->title,
            'permission' => $permission
        ]);
    }
}
