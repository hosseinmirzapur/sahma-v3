<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()
            ->where('slug', 'system_admin')
            ->firstOrFail();

        User::query()->firstOrCreate(
            [
                'name' => 'مدیر سیستم',
                'personal_id' => 1,
                'password' => Hash::make('12345678'),
                'role_id' => $role->id,
                'is_super_admin' => 1
            ]
        );
    }
}
