<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\ChallengeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChallengeGroup>
 */
class ChallengeGroupFactory extends Factory
{
    /**
     * Le nom du modèle correspondant à la factory.
     *
     * @var string
     */
    protected $model = ChallengeGroup::class;

    /**
     * Définir l'état par défaut du modèle.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' groupe',
            'description' => $this->faker->paragraph(),
            'creator_id' => User::factory(),
            'premium_only' => $this->faker->boolean(20), // 20% de chance d'être premium only
            'max_members' => $this->faker->randomElement([10, 20, 30, 50]),
        ];
    }

    /**
     * Indiquer que le groupe est réservé aux utilisateurs premium.
     *
     * @return static
     */
    public function premiumOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'premium_only' => true,
        ]);
    }

    /**
     * Indiquer que le groupe n'est pas réservé aux utilisateurs premium.
     *
     * @return static
     */
    public function notPremiumOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'premium_only' => false,
        ]);
    }

    /**
     * Définir le nombre maximum de membres.
     *
     * @param int $count
     * @return static
     */
    public function withMaxMembers(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'max_members' => $count,
        ]);
    }

    /**
     * Définir le créateur du groupe.
     *
     * @param User $user
     * @return static
     */
    public function forCreator(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_id' => $user->id,
        ]);
    }
}