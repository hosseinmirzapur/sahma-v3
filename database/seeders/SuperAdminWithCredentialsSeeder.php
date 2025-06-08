<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminWithCredentialsSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $role = Role::query()
      ->where('slug', 'system_admin')
      ->firstOrFail();

    $personalId = intval(config('admin.personal_id'));
    $password = strval(config('admin.password'));

    if (!isset($personalId) || !isset($password)) {
      throw new Exception('Unable to read admin credentials. Make sure you have included them in config/admin.php and .env file');
    }

    User::query()->firstOrCreate(
      [
        'name' => 'مدیر سیستم',
        'personal_id' => $personalId,
        'password' => Hash::make($password),
        'role_id' => $role->id,
        'is_super_admin' => 1
      ]
    );
  }
}
