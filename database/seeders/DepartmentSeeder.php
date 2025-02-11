<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $personalId = $this->command->ask('Departments are being created by admin with Personal ID:');

        $user = User::query()
            ->where('personal_id', $personalId)
            ->firstOrFail();

        Department::query()->insert(
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

        Department::query()
            ->each(function ($department) use ($user) {
                DepartmentUser::query()->create([
                    'user_id' => $user->id,
                    'department_id' => $department->id
                ]);
            });
    }
}
