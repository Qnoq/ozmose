<?php

namespace Database\Factories;

use App\Models\TruthOrDareParticipant;
use App\Models\TruthOrDareSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TruthOrDareParticipant>
 */
class TruthOrDareParticipantFactory extends Factory
{
    protected $model = TruthOrDareParticipant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isGuest = $this->faker->boolean(30);
        
        return [
            'session_id' => TruthOrDareSession::factory(),
            'user_id' => $isGuest ? null : User::factory(),
            'guest_name' => $isGuest ? $this->faker->firstName : null,
            'guest_avatar' => $isGuest ? $this->faker->randomElement(['😀', '😎', '🤪', '😇', '🤩', '😏', '🙃', '🤗', '🤔', '😈']) : null,
            'status' => $this->faker->randomElement(['active', 'left', 'kicked']),
            'truths_answered' => $this->faker->numberBetween(0, 15),
            'dares_completed' => $this->faker->numberBetween(0, 10),
            'skips_used' => $this->faker->numberBetween(0, 5),
            'turn_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the participant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the participant has left.
     */
    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'left',
        ]);
    }

    /**
     * Indicate that the participant was kicked.
     */
    public function kicked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'kicked',
        ]);
    }

    /**
     * Indicate that the participant is a guest.
     */
    public function guest(string $name = null, string $avatar = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_name' => $name ?? $this->faker->firstName,
            'guest_avatar' => $avatar ?? $this->faker->randomElement(['😀', '😎', '🤪', '😇', '🤩', '😏', '🙃', '🤗', '🤔', '😈']),
        ]);
    }

    /**
     * Indicate that the participant is a registered user.
     */
    public function user(User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
            'guest_name' => null,
            'guest_avatar' => null,
        ]);
    }

    /**
     * Reset all stats.
     */
    public function fresh(): static
    {
        return $this->state(fn (array $attributes) => [
            'truths_answered' => 0,
            'dares_completed' => 0,
            'skips_used' => 0,
        ]);
    }

    /**
     * Set specific turn order.
     */
    public function withTurnOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'turn_order' => $order,
        ]);
    }

    /**
     * Set specific stats.
     */
    public function withStats(int $truths = 0, int $dares = 0, int $skips = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'truths_answered' => $truths,
            'dares_completed' => $dares,
            'skips_used' => $skips,
        ]);
    }
}