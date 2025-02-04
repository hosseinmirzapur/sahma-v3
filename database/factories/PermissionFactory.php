<?php

namespace Database\Factories;

use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   * @throws Exception
   */
    public function definition(): array
    {
        return match (random_int(0, 2)) {
            0 => [
            'full' => 1,
            'modify' => 0,
            'read_only' => 0
            ],
            1 => [
            'full' => 0,
            'modify' => 1,
            'read_only' => 0
            ],
            2 => [
            'full' => 0,
            'modify' => 0,
            'read_only' => 1
            ],
        };
    }
}
