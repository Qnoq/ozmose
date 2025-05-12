<?php

namespace Database\Factories;

use App\Models\TruthOrDareQuestion;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TruthOrDareQuestion>
 */
class TruthOrDareQuestionFactory extends Factory
{
    protected $model = TruthOrDareQuestion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['truth', 'dare']);
        $intensity = $this->faker->randomElement(['soft', 'spicy', 'hot']);
        
        return [
            'creator_id' => User::factory(),
            'category_id' => $this->faker->optional()->randomElement(Category::pluck('id')->toArray()),
            'type' => $type,
            'content' => $this->generateContent($type, $intensity),
            'intensity' => $intensity,
            'is_public' => $this->faker->boolean(70),
            'is_premium' => $this->faker->boolean(20),
            'times_used' => $this->faker->numberBetween(0, 100),
            'rating' => $this->faker->optional()->randomFloat(1, 1, 5),
            'is_official' => $this->faker->boolean(10),
        ];
    }

    /**
     * Generate content based on type and intensity
     */
    private function generateContent(string $type, string $intensity): string
    {
        $content = [
            'truth' => [
                'soft' => [
                    'Quel est ton plat préféré ?',
                    'Quelle est ta plus grande peur ?',
                    'Quel est ton plus beau souvenir ?',
                    'Si tu pouvais avoir un super pouvoir, lequel choisirais-tu ?',
                    'Quel est le dernier mensonge que tu as dit ?',
                ],
                'spicy' => [
                    'As-tu déjà menti à ton/ta partenaire ?',
                    'Quelle est ta plus grande insécurité ?',
                    'As-tu déjà eu le cœur brisé ?',
                    'Quelle est la chose la plus embarrassante que tu aies faite ?',
                    'As-tu déjà fantasmé sur quelqu\'un dans cette pièce ?',
                ],
                'hot' => [
                    'Quel est ton fantasme le plus fou ?',
                    'As-tu déjà fait l\'amour dans un lieu public ?',
                    'Quelle est ta position préférée ?',
                    'As-tu déjà eu une aventure d\'un soir ?',
                    'Quel est le pire rendez-vous que tu aies eu ?',
                ],
            ],
            'dare' => [
                'soft' => [
                    'Fais 10 pompes',
                    'Chante ta chanson préférée',
                    'Imite un animal pendant 30 secondes',
                    'Danse sans musique pendant 1 minute',
                    'Raconte une blague',
                ],
                'spicy' => [
                    'Embrasse la personne à ta droite',
                    'Enlève un vêtement',
                    'Bois ton verre cul-sec',
                    'Appelle un(e) ex et dis-lui que tu penses à lui/elle',
                    'Poste une photo embarrassante sur les réseaux sociaux',
                ],
                'hot' => [
                    'Fais un strip-tease de 30 secondes',
                    'Embrasse passionnément la personne de ton choix',
                    'Simule un orgasme',
                    'Lèche le cou de la personne à ta gauche',
                    'Enlève deux vêtements',
                ],
            ],
        ];

        return $this->faker->randomElement($content[$type][$intensity]);
    }

    /**
     * Indicate that the question is official.
     */
    public function official(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_official' => true,
            'is_public' => false,
            'creator_id' => null,
        ]);
    }

    /**
     * Indicate that the question is premium.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
        ]);
    }

    /**
     * Indicate that the question is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Set specific type.
     */
    public function truth(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'truth',
        ]);
    }

    /**
     * Set specific type.
     */
    public function dare(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dare',
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
}