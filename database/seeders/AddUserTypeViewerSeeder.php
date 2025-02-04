<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AddUserTypeViewerSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
    public function run(): void
    {
        $role = Role::query()->create([
          'title' => 'بازدید کننده',
          'slug' => 'viewer'
        ]);

        Permission::query()->create(
            [
              'full' => 0,
              'modify' => 0,
              'read_only' => 1,
              'role_id' => $role->id
            ]
        );

        User::query()->create(
            [
              'name' => 'بازدید کننده',
              'personal_id' => 2,
              'password' => Hash::make('1234'),
              'role_id' => $role->id,
              'is_super_admin' => 0
            ]
        );
    }
}
