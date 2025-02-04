<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Department;
use App\Models\DepartmentUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
//        $admin = User::query()->firstOrCreate([
//            'personal_id' => 1403,
//            'password' => Hash::make('password'),
//            'name' => 'admin'
//        ]);
//        $departments = Department::all();
//        /* @var Department $department*/
//        foreach ($departments as $department) {
//            DepartmentUser::query()->create([
//              'user_id' => $admin->id,
//              'department_id' => $department->id
//            ]);
//        }
        $this->call([
            AddRoleAndSuperAdminAndDepartmentSeeder::class
        ]);
    }
}
