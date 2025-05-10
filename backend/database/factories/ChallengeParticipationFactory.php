<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeParticipationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ChallengeParticipation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => function () {
                return User::factory()->create()->id;
            },
            'challenge_id' => function () {
                return Challenge::factory()->create()->id;
            },
            'status' => 'accepted',
            'notes' => $this->faker->paragraph(),
            'started_at' => now(),
        ];
    }

    /**
     * Indicate that the participation is completed.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'completed_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the participation is an invitation.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function invited()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'invited',
                'invited_by' => function () {
                    return User::factory()->create()->id;
                },
                'invitation_message' => $this->faker->sentence(),
            ];
        });
    }
}