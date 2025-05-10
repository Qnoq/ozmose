<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Category;
use App\Models\Challenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    protected $user;
    protected $category;
    
    protected function setUp(): void
    {
        parent::setUp();
    
        // Créer un utilisateur et une catégorie pour les tests
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create();
    }
    
    /**
     * Test qu'un utilisateur authentifié peut créer un défi
     */
    public function test_authorized_user_can_create_challenge(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/challenges', [
            'title' => 'Nouveau défi de test',
            'description' => 'Description du défi de test',
            'instructions' => 'Instructions pour réaliser le défi',
            'difficulty' => 'moyen',
            'category_id' => $this->category->id,
            'is_public' => true,
            'duration' => 3
        ]);
        
        $response->assertCreated()
            ->assertJsonPath('data.title', 'Nouveau défi de test');
        
        $this->assertDatabaseHas('challenges', [
            'title' => 'Nouveau défi de test',
            'creator_id' => $this->user->id
        ]);
    }
    
    /**
     * Test qu'un utilisateur non authentifié ne peut pas créer un défi
     */
    public function test_unauthorized_user_cannot_create_challenge(): void
    {
        $response = $this->postJson('/api/challenges', [
            'title' => 'Défi non autorisé',
            'description' => 'Description non autorisée',
            'instructions' => 'Instructions non autorisées',
            'difficulty' => 'facile',
            'category_id' => $this->category->id
        ]);
        
        $response->assertUnauthorized();
        
        $this->assertDatabaseMissing('challenges', [
            'title' => 'Défi non autorisé'
        ]);
    }
    
    /**
     * Test qu'un utilisateur peut voir un défi public
     */
    public function test_user_can_view_public_challenge(): void
    {
        // Créer un défi public
        $challenge = Challenge::factory()->create([
            'is_public' => true
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson("/api/challenges/{$challenge->id}");
        
        $response->assertOk()
            ->assertJsonPath('data.id', $challenge->id)
            ->assertJsonPath('data.title', $challenge->title);
    }
    
    /**
     * Test qu'un utilisateur peut voir son propre défi privé
     */
    public function test_user_can_view_own_private_challenge(): void
    {
        // Créer un défi privé appartenant à l'utilisateur
        $challenge = Challenge::factory()->create([
            'creator_id' => $this->user->id,
            'is_public' => false
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson("/api/challenges/{$challenge->id}");
        
        $response->assertOk()
            ->assertJsonPath('data.id', $challenge->id);
    }
    
    /**
     * Test qu'un utilisateur ne peut pas voir le défi privé d'un autre utilisateur
     */
    public function test_user_cannot_view_others_private_challenge(): void
    {
        // Créer un autre utilisateur
        $otherUser = User::factory()->create();
        
        // Créer un défi privé appartenant à l'autre utilisateur
        $challenge = Challenge::factory()->create([
            'creator_id' => $otherUser->id,
            'is_public' => false
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson("/api/challenges/{$challenge->id}");
        
        $response->assertForbidden();
    }
    
    /**
     * Test qu'un utilisateur peut mettre à jour son propre défi
     */
    public function test_user_can_update_own_challenge(): void
    {
        // Créer un défi appartenant à l'utilisateur
        $challenge = Challenge::factory()->create([
            'creator_id' => $this->user->id,
            'title' => 'Titre original'
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->putJson("/api/challenges/{$challenge->id}", [
            'title' => 'Titre modifié',
            'description' => 'Description modifiée'
        ]);
        
        $response->assertOk()
            ->assertJsonPath('data.title', 'Titre modifié')
            ->assertJsonPath('data.description', 'Description modifiée');
            
        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'title' => 'Titre modifié'
        ]);
    }
    
    /**
     * Test qu'un utilisateur ne peut pas mettre à jour le défi d'un autre utilisateur
     */
    public function test_user_cannot_update_others_challenge(): void
    {
        // Créer un autre utilisateur
        $otherUser = User::factory()->create();
        
        // Créer un défi appartenant à l'autre utilisateur
        $challenge = Challenge::factory()->create([
            'creator_id' => $otherUser->id,
            'title' => 'Titre original'
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->putJson("/api/challenges/{$challenge->id}", [
            'title' => 'Titre modifié'
        ]);
        
        $response->assertForbidden();
        
        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'title' => 'Titre original'
        ]);
    }
    
    /**
     * Test qu'un utilisateur peut supprimer son propre défi
     */
    public function test_user_can_delete_own_challenge(): void
    {
        // Créer un défi appartenant à l'utilisateur
        $challenge = Challenge::factory()->create([
            'creator_id' => $this->user->id
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->deleteJson("/api/challenges/{$challenge->id}");
        
        $response->assertOk();
        
        // Si vous utilisez le soft delete, utilisez assertSoftDeleted
        // Sinon, utilisez assertDatabaseMissing
        $this->assertDatabaseMissing('challenges', [
            'id' => $challenge->id
        ]);
    }
    
    /**
     * Test qu'un utilisateur ne peut pas supprimer le défi d'un autre utilisateur
     */
    public function test_user_cannot_delete_others_challenge(): void
    {
        // Créer un autre utilisateur
        $otherUser = User::factory()->create();
        
        // Créer un défi appartenant à l'autre utilisateur
        $challenge = Challenge::factory()->create([
            'creator_id' => $otherUser->id
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->deleteJson("/api/challenges/{$challenge->id}");
        
        $response->assertForbidden();
        
        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id
        ]);
    }
    
    /**
     * Test qu'un utilisateur peut voir la liste des défis publics
     */
    public function test_user_can_view_public_challenges(): void
    {
        // Créer des défis publics et privés
        Challenge::factory()->count(5)->create(['is_public' => true]);
        Challenge::factory()->count(3)->create(['is_public' => false]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/challenges/public');
        
        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }
    
    /**
     * Test qu'un utilisateur peut cloner un défi public
     */
    public function test_user_can_clone_public_challenge(): void
    {
        // Créer un défi public
        $originalChallenge = Challenge::factory()->create([
            'is_public' => true,
            'title' => 'Défi original'
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson("/api/challenges/{$originalChallenge->id}/clone", [
            'title' => 'Défi cloné',
            'is_public' => false
        ]);
        
        $response->assertCreated();
        
        $this->assertDatabaseHas('challenges', [
            'title' => 'Défi cloné',
            'creator_id' => $this->user->id,
            'parent_challenge_id' => $originalChallenge->id,
            'is_public' => 0
        ]);
    }
    
    /**
     * Test qu'un utilisateur ne peut pas cloner un défi privé d'un autre utilisateur
     */
    public function test_user_cannot_clone_private_challenge_of_others(): void
    {
        // Créer un autre utilisateur
        $otherUser = User::factory()->create();
        
        // Créer un défi privé appartenant à l'autre utilisateur
        $challenge = Challenge::factory()->create([
            'creator_id' => $otherUser->id,
            'is_public' => false
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson("/api/challenges/{$challenge->id}/clone", [
            'title' => 'Tentative de clonage'
        ]);
        
        $response->assertForbidden();
        
        $this->assertDatabaseMissing('challenges', [
            'title' => 'Tentative de clonage',
            'creator_id' => $this->user->id
        ]);
    }
    
    /**
     * Test qu'un utilisateur premium peut créer un défi multi-étapes
     */
    public function test_premium_user_can_create_multi_stage_challenge(): void
    {
        // Créer un utilisateur premium
        $premiumUser = User::factory()->create([
            'is_premium' => true,
            'premium_until' => now()->addYear()
        ]);
        
        Sanctum::actingAs($premiumUser);
        
        $response = $this->postJson('/api/challenges/multi-stage', [
            'title' => 'Défi multi-étapes',
            'description' => 'Description du défi multi-étapes',
            'instructions' => 'Instructions générales',
            'difficulty' => 'difficile',
            'category_id' => $this->category->id,
            'is_public' => true,
            'stages' => [
                [
                    'title' => 'Étape 1',
                    'description' => 'Description de l\'étape 1',
                    'instructions' => 'Instructions pour l\'étape 1',
                    'order' => 1,
                    'requires_proof' => true
                ],
                [
                    'title' => 'Étape 2',
                    'description' => 'Description de l\'étape 2',
                    'instructions' => 'Instructions pour l\'étape 2',
                    'order' => 2,
                    'requires_proof' => false
                ]
            ]
        ]);
        
        $response->assertCreated();
        
        // Vérifier que le défi a été créé
        $this->assertDatabaseHas('challenges', [
            'title' => 'Défi multi-étapes',
            'creator_id' => $premiumUser->id,
            'multi_stage' => 1
        ]);
        
        // Récupérer l'ID du défi créé
        $challengeId = Challenge::where('title', 'Défi multi-étapes')->first()->id;
        
        // Vérifier que les étapes ont été créées
        $this->assertDatabaseHas('challenge_stages', [
            'challenge_id' => $challengeId,
            'title' => 'Étape 1',
            'order' => 1
        ]);
        
        $this->assertDatabaseHas('challenge_stages', [
            'challenge_id' => $challengeId,
            'title' => 'Étape 2',
            'order' => 2
        ]);
    }
    
    /**
     * Test qu'un utilisateur non premium ne peut pas créer un défi multi-étapes
     */
    public function test_non_premium_user_cannot_create_multi_stage_challenge(): void
    {
        Sanctum::actingAs($this->user); // Utilisateur non premium
        info($this->user);
        $response = $this->postJson('/api/challenges/multi-stage', [
            'title' => 'Défi multi-étapes',
            'description' => 'Description du défi multi-étapes',
            'instructions' => 'Instructions générales',
            'difficulty' => 'difficile',
            'category_id' => $this->category->id,
            'stages' => [
                [
                    'title' => 'Étape 1',
                    'description' => 'Description de l\'étape 1',
                    'instructions' => 'Instructions pour l\'étape 1',
                    'order' => 1
                ],
                [
                    'title' => 'Étape 2',
                    'description' => 'Description de l\'étape 2',
                    'instructions' => 'Instructions pour l\'étape 2',
                    'order' => 2
                ]
            ]
        ]);
        
        $response->assertForbidden();
        
        $this->assertDatabaseMissing('challenges', [
            'title' => 'Défi multi-étapes',
            'creator_id' => $this->user->id,
            'multi_stage' => 1
        ]);
    }
    
    /**
     * Test qu'un utilisateur peut récupérer les brouillons de défis (fonctionnalité premium)
     */
    public function test_premium_user_can_save_and_retrieve_draft(): void
    {
        // Créer un utilisateur premium
        $premiumUser = User::factory()->create([
            'is_premium' => true,
            'premium_until' => now()->addYear()
        ]);
        
        Sanctum::actingAs($premiumUser);
        
        // Sauvegarder un brouillon
        $response = $this->postJson('/api/drafts/challenges', [
            'title' => 'Brouillon de défi',
            'description' => 'Description du brouillon',
            'category_id' => $this->category->id
        ]);
        
        $response->assertOk()
            ->assertJsonPath('success', true);
        
        // Récupérer le brouillon
        $response = $this->getJson('/api/drafts/challenges');
        
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_draft', true)
            ->assertJsonPath('draft.title', 'Brouillon de défi');
    }
}