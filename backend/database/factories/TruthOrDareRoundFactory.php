<?php

namespace Database\Factories;

use App\Models\TruthOrDareRound;
use App\Models\TruthOrDareSession;
use App\Models\TruthOrDareParticipant;
use App\Models\TruthOrDareQuestion;
use App\Models\ChallengeMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TruthOrDareRound>
 */
class TruthOrDareRoundFactory extends Factory
{
    protected $model = TruthOrDareRound::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $choice = $this->faker->randomElement(['truth', 'dare']);
        $status = $this->faker->randomElement(['pending', 'completed', 'skipped']);
        
        return [
            'session_id' => TruthOrDareSession::factory(),
            'participant_id' => TruthOrDareParticipant::factory(),
            'question_id' => TruthOrDareQuestion::factory()->state(['type' => $choice]),
            'choice' => $choice,
            'status' => $status,
            'response' => $status === 'completed' && $choice === 'truth' ? $this->faker->sentence() : null,
            'proof_media_id' => $status === 'completed' && $choice === 'dare' && $this->faker->boolean(30) ? ChallengeMedia::factory() : null,
            'rating' => $status === 'completed' ? $this->faker->optional()->numberBetween(1, 5) : null,
        ];
    }

    /**
     * Indicate that the round is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'response' => null,
            'proof_media_id' => null,
            'rating' => null,
        ]);
    }

    /**
     * Indicate that the round is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the round was skipped.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'skipped',
            'response' => null,
            'proof_media_id' => null,
            'rating' => null,
        ]);
    }

    /**
     * Set as truth round.
     */
    public function truth(): static
    {
        return $this->state(fn (array $attributes) => [
            'choice' => 'truth',
            'question_id' => TruthOrDareQuestion::factory()->truth(),
        ]);
    }

    /**
     * Set as dare round.
     */
    public function dare(): static
    {
        return $this->state(fn (array $attributes) => [
            'choice' => 'dare',
            'question_id' => TruthOrDareQuestion::factory()->dare(),
        ]);
    }

    /**
     * Add a rating.
     */
    public function withRating(int $rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
        ]);
    }

    /**
     * Add a response for truth rounds.
     */
    public function withResponse(string $response): static
    {
        return $this->state(fn (array $attributes) => [
            'response' => $response,
        ]);
    }

    /**
     * Add proof media for dare rounds.
     */
    public function withProof(): static
    {
        return $this->state(fn (array $attributes) => [
            'proof_media_id' => ChallengeMedia::factory(),
        ]);
    }
}