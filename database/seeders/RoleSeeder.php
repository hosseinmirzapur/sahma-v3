<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->superAdmin();
        $this->viewer();
    }

    private function superAdmin(): void
    {
        $role = Role::query()->firstOrCreate([
            'title' => 'مدیر سیستم',
            'slug' => 'system_admin'
        ]);

        Permission::query()->firstOrCreate(
            [
                'full' => 1,
                'modify' => 0,
                'read_only' => 0,
                'role_id' => $role->id
            ]
        );
    }

    private function viewer(): void
    {
        $role = Role::query()->firstOrCreate([
            'title' => 'بازدید کننده',
            'slug' => 'viewer'
        ]);

        Permission::query()->firstOrCreate(
            [
                'full' => 0,
                'modify' => 0,
                'read_only' => 1,
                'role_id' => $role->id
            ]
        );
    }
}
