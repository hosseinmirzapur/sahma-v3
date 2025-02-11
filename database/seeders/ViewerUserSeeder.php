<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ViewerUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()
            ->where('slug', 'viewer')
            ->firstOrFail();
        User::query()->firstOrCreate(
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
