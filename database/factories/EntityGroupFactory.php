<?php

namespace Database\Factories;

use App\Models\EntityGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class EntityGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
        'image' => ['png', 'jpeg', 'tif'],
        'voice' => ['wav', 'mp3'],
        'pdf' => ['pdf'],
        'video' => ['mp4', 'mkv']
        ];

        $keys = array_keys($types);

        $randomKeyType = $keys[array_rand($keys)];

        $randomExtension = strval($types[$randomKeyType][array_rand($types[$randomKeyType])]);

        $status = [
        'pdf' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
        'image' => EntityGroup::STATUS_WAITING_FOR_TRANSCRIPTION,
        'voice' => EntityGroup::STATUS_WAITING_FOR_SPLIT,
        'video' => EntityGroup::STATUS_WAITING_FOR_AUDIO_SEPARATION,
        ];

        return [
            'name' => $this->faker->word . '.' . $randomExtension,
            'type' => $randomKeyType,
            'status' => $status[$randomKeyType],
            'file_location' => $this->faker->words(5, true),
        ];
    }
}
