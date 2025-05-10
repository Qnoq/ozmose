<?php

use App\Models\User;
use App\Models\Challenge;
use App\Models\ChallengeMedia;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\actingAs;
use App\Models\ChallengeParticipation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class, WithFaker::class);

beforeEach(function () {
    // Configurer le stockage pour les tests
    Storage::fake('public');
    
    // Créer un utilisateur et un défi pour les tests
    $this->user = User::factory()->create();
    $this->challenge = Challenge::factory()->create([
        'is_public' => true
    ]);
});

// Test qu'un utilisateur peut participer à un défi public
it('allows user to participate in a public challenge', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    $response = postJson("/api/challenges/{$this->challenge->id}/participate", [
        'notes' => 'Je me lance dans ce défi !'
    ]);
    
    $response->assertCreated()
        ->assertJsonPath('data.status', 'accepted');
    
    $this->assertDatabaseHas('challenge_participations', [
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
});

// Test qu'un utilisateur ne peut pas participer deux fois au même défi
it('prevents user from participating twice in the same challenge', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    // Créer une participation existante
    ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    $response = postJson("/api/challenges/{$this->challenge->id}/participate", [
        'notes' => 'Tentative de participation double'
    ]);
    
    $response->assertStatus(422)
        ->assertJsonPath('message', 'Vous participez déjà à ce défi');
});

// Test qu'un utilisateur peut mettre à jour sa participation
it('allows user to update their own participation', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    $participation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    $response = putJson("/api/participations/{$participation->id}", [
        'status' => 'completed',
        'notes' => 'J\'ai terminé ce défi !'
    ]);
    
    $response->assertOk()
        ->assertJsonPath('data.status', 'completed');
    
    $this->assertDatabaseHas('challenge_participations', [
        'id' => $participation->id,
        'status' => 'completed',
        'notes' => 'J\'ai terminé ce défi !'
    ]);
});

// Test qu'un utilisateur ne peut pas mettre à jour la participation d'un autre
it('prevents user from updating participation of others', function () {
    // Créer un autre utilisateur
    $otherUser = User::factory()->create();
    
    // Créer une participation pour cet autre utilisateur
    $participation = ChallengeParticipation::create([
        'user_id' => $otherUser->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    $response = putJson("/api/participations/{$participation->id}", [
        'status' => 'completed'
    ]);
    
    $response->assertForbidden();
    
    $this->assertDatabaseHas('challenge_participations', [
        'id' => $participation->id,
        'status' => 'accepted' // Le statut n'a pas changé
    ]);
});

// Test qu'un utilisateur peut ajouter une preuve de réalisation
it('allows user to add completion proof', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    $participation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    // Créer un fichier de test
    $image = UploadedFile::fake()->image('proof.jpg', 500, 500);
    
    $response = postJson("/api/participations/{$participation->id}/proof", [
        'media' => $image,
        'caption' => 'Voici ma preuve de réalisation',
        'is_public' => true
    ]);
    
    $response->assertOk()
        ->assertJsonPath('message', 'Preuve de réalisation ajoutée avec succès');
    
    // Vérifier que le fichier a été stocké
    $media = ChallengeMedia::where('participation_id', $participation->id)->first();
    expect($media)->not->toBeNull();
    Storage::disk('public')->assertExists($media->path);
    
    // Vérifier que la participation a été mise à jour
    $this->assertDatabaseHas('challenge_participations', [
        'id' => $participation->id,
        'status' => 'completed'
    ]);
});

// Test qu'un utilisateur peut récupérer ses participations actives
it('allows user to get active participations', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    // Créer plusieurs participations
    $activeParticipation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    $completedChallenge = Challenge::factory()->create();
    ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $completedChallenge->id,
        'status' => 'completed',
        'completed_at' => now()
    ]);
    
    $response = getJson('/api/user/participations/active');
    
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $activeParticipation->id);
});

// Test qu'un utilisateur peut récupérer ses participations complétées
it('allows user to get completed participations', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    // Créer plusieurs participations
    ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    $completedChallenge = Challenge::factory()->create();
    $completedParticipation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $completedChallenge->id,
        'status' => 'completed',
        'completed_at' => now()
    ]);
    
    $response = getJson('/api/user/participations/completed');
    
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $completedParticipation->id);
});

// Test qu'un utilisateur peut inviter des amis à un défi
it('allows user to invite friends to challenge', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    // Créer des amis (dans un contexte de test simplifié)
    $friend1 = User::factory()->create();
    $friend2 = User::factory()->create();
    
    $response = postJson("/api/challenges/{$this->challenge->id}/invite", [
        'friend_ids' => [$friend1->id, $friend2->id],
        'message' => 'Relevez ce défi avec moi !'
    ]);
    
    $response->assertOk()
        ->assertJsonPath('invited_count', 2);
    
    // Vérifier que les invitations ont été créées
    $this->assertDatabaseHas('challenge_participations', [
        'user_id' => $friend1->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'invited',
        'invited_by' => $this->user->id
    ]);
    
    $this->assertDatabaseHas('challenge_participations', [
        'user_id' => $friend2->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'invited',
        'invited_by' => $this->user->id
    ]);
});

// Test qu'un utilisateur peut accepter une invitation
it('allows user to accept invitation', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    // Créer une invitation
    $participation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'invited',
        'invited_by' => User::factory()->create()->id
    ]);
    
    $response = putJson("/api/participations/{$participation->id}/accept");
    
    $response->assertOk()
        ->assertJsonPath('data.status', 'accepted');
    
    $this->assertDatabaseHas('challenge_participations', [
        'id' => $participation->id,
        'status' => 'accepted'
    ]);
});

// Test qu'un utilisateur peut rejeter une invitation
it('allows user to reject invitation', function () {
    // Se connecter en tant qu'utilisateur
    actingAs($this->user);
    
    // Créer une invitation
    $participation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'invited',
        'invited_by' => User::factory()->create()->id
    ]);
    
    $response = putJson("/api/participations/{$participation->id}/reject");
    
    $response->assertOk()
        ->assertJsonPath('message', 'Invitation refusée avec succès');
    
    // L'invitation devrait être supprimée
    $this->assertDatabaseMissing('challenge_participations', [
        'id' => $participation->id
    ]);
});

// Test pour les défis multi-étapes (fonctionnalité premium)
it('initializes stage participations for multi-stage challenges', function () {
    // Créer un utilisateur premium
    $premiumUser = User::factory()->create([
        'is_premium' => true,
        'premium_until' => now()->addYear()
    ]);
    
    // Se connecter en tant qu'utilisateur premium
    actingAs($premiumUser);
    
    // Créer un défi multi-étapes avec des étapes
    $challenge = Challenge::factory()->create([
        'multi_stage' => true
    ]);
    
    // Ajouter quelques étapes au défi
    $stage1 = $challenge->stages()->create([
        'title' => 'Étape 1',
        'description' => 'Description de l\'étape 1',
        'instructions' => 'Instructions pour l\'étape 1',
        'order' => 1
    ]);
    
    $stage2 = $challenge->stages()->create([
        'title' => 'Étape 2',
        'description' => 'Description de l\'étape 2',
        'instructions' => 'Instructions pour l\'étape 2',
        'order' => 2
    ]);
    
    // Participer au défi
    $response = postJson("/api/challenges/{$challenge->id}/participate");
    
    $response->assertCreated();
    
    // Récupérer l'ID de la participation créée
    $participationId = $response->json('data.id');
    
    // Vérifier que les participations aux étapes ont été initialisées
    $this->assertDatabaseHas('challenge_stage_participations', [
        'participation_id' => $participationId,
        'stage_id' => $stage1->id,
        'status' => 'active' // La première étape devrait être active
    ]);
    
    $this->assertDatabaseHas('challenge_stage_participations', [
        'participation_id' => $participationId,
        'stage_id' => $stage2->id,
        'status' => 'locked' // Les étapes suivantes devraient être verrouillées
    ]);
});