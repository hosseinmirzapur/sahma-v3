<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AddRoleAndSuperAdminAndDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()->create([
            'title' => 'مدیر سیستم',
            'slug' => 'مدیر سیستم'
        ]);

        Permission::query()->create(
            [
                'full' => 1,
                'modify' => 0,
                'read_only' => 0,
                'role_id' => $role->id
            ]
        );

        $user = User::query()->create(
            [
                'name' => 'مدیر سیستم',
                'personal_id' => 1,
                'password' => Hash::make('1234'),
                'role_id' => $role->id,
                'is_super_admin' => 1
            ]
        );
        Department::insert(
            [
                [
                    'name' => 'بازرسی',
                    'created_by' => $user->id
                ],
                [
                    'name' => 'مالی',
                    'created_by' => $user->id
                ],
                [
                    'name' => 'آموزش',
                    'created_by' => $user->id
                ],
                [
                    'name' => 'ارزیابی عملکرد',
                    'created_by' => $user->id
                ]
            ]
        );
        $departments = Department::all();
        /* @var Department $department*/
        foreach ($departments as $department) {
            DepartmentUser::query()->create([
                'user_id' => $user->id,
                'department_id' => $department->id
            ]);
        }
    }
}
