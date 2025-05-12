<?php

namespace Database\Factories;

use App\Models\TruthOrDareSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TruthOrDareSession>
 */
class TruthOrDareSessionFactory extends Factory
{
    protected $model = TruthOrDareSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creator_id' => User::factory(),
            'name' => $this->faker->randomElement([
                'Soirée entre amis',
                'Soirée en couple',
                'Anniversaire de ' . $this->faker->firstName,
                'Samedi soir',
                'Vendredi fou',
                'Weekend party',
                'Soirée jeux',
                'Apéro dinatoire',
            ]),
            'description' => $this->faker->optional()->sentence(10),
            'intensity' => $this->faker->randomElement(['soft', 'spicy', 'hot']),
            'is_public' => $this->faker->boolean(30),
            'is_active' => $this->faker->boolean(80),
            'join_code' => null, // Sera généré dans le modèle
            'max_participants' => $this->faker->randomElement([4, 6, 8, 10, 12, 15, 20]),
            'premium_only' => $this->faker->boolean(20),
        ];
    }

    /**
     * Indicate that the session is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the session is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the session is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the session is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Indicate that the session is premium only.
     */
    public function premiumOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'premium_only' => true,
        ]);
    }

    /**
     * Set specific intensity.
     */
    public function soft(): static
    {
        return $this->state(fn (array $attributes) => [
            'intensity' => 'soft',
        ]);
    }

    /**
     * Set specific intensity.
     */
    public function spicy(): static
    {
        return $this->state(fn (array $attributes) => [
            'intensity' => 'spicy',
        ]);
    }

    /**
     * Set specific intensity.
     */
    public function hot(): static
    {
        return $this->state(fn (array $attributes) => [
            'intensity' => 'hot',
        ]);
    }

    /**
     * Set a specific join code.
     */
    public function withJoinCode(string $code = null): static
    {
        return $this->state(fn (array $attributes) => [
            'join_code' => $code ?? strtoupper(Str::random(6)),
        ]);
    }

    /**
     * Set maximum participants.
     */
    public function withMaxParticipants(int $max): static
    {
        return $this->state(fn (array $attributes) => [
            'max_participants' => $max,
        ]);
    }
}