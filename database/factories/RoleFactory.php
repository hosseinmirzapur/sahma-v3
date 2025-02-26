<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rolesTitles = ['مدیر', 'ادمین بخش', 'اپراتور'];
        $randomKeyRoleTitle = array_rand($rolesTitles);
        $randomRoleTitle = $rolesTitles[$randomKeyRoleTitle];
        return [
            'title' => $randomRoleTitle,
            'slug' => $randomRoleTitle,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
