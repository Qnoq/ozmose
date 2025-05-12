<?php

namespace Database\Factories;

use App\Models\ChallengeMedia;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeMediaFactory extends Factory
{
    protected $model = ChallengeMedia::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'challenge_id' => Challenge::factory(),
            'type' => $this->faker->randomElement(['image', 'video']),
            'path' => 'media/' . $this->faker->uuid . '.jpg',
            'original_name' => $this->faker->word . '.jpg',
            'size' => $this->faker->numberBetween(1000, 5000000),
            'mime_type' => 'image/jpeg',
            'caption' => $this->faker->sentence(),
            'width' => $this->faker->numberBetween(640, 1920),
            'height' => $this->faker->numberBetween(480, 1080),
            'is_public' => $this->faker->boolean(80),
            'order' => 0,
            'storage_disk' => 'public',
            'high_quality' => false,
            'in_compilation' => false,
            'compilation_id' => null,
        ];
    }

    public function image(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'path' => 'images/' . $this->faker->uuid . '.jpg',
            ];
        });
    }

    public function video(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'video',
                'mime_type' => 'video/mp4',
                'path' => 'videos/' . $this->faker->uuid . '.mp4',
                'duration' => $this->faker->numberBetween(10, 300),
            ];
        });
    }

    public function compilation(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'compilation',
                'mime_type' => 'video/mp4',
                'path' => null,
                'high_quality' => true,
            ];
        });
    }
}