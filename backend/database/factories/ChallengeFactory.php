<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Category;
use App\Models\Challenge;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Challenge::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'instructions' => $this->faker->paragraphs(2, true),
            'creator_id' => function () {
                return User::factory()->create()->id;
            },
            'category_id' => function () {
                return Category::factory()->create()->id;
            },
            'difficulty' => $this->faker->randomElement(['facile', 'moyen', 'difficile']),
            'is_public' => $this->faker->boolean(70), // 70% de chance d'être public
            'duration' => $this->faker->numberBetween(1, 10),
            'multi_stage' => false,
        ];
    }

    /**
     * Indicate that the challenge is public.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function public()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_public' => true,
            ];
        });
    }

    /**
     * Indicate that the challenge is private.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function private()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_public' => false,
            ];
        });
    }

    /**
     * Indicate that the challenge is multi-stage.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function multiStage()
    {
        return $this->state(function (array $attributes) {
            return [
                'multi_stage' => true,
            ];
        });
    }
}