<?php

use App\Models\User;
use App\Models\TruthOrDareSession;
use App\Models\TruthOrDareQuestion;
use App\Models\TruthOrDareParticipant;
use App\Models\TruthOrDareRound;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Créer un utilisateur pour les tests
    $this->user = User::factory()->create();
    
    // Créer un utilisateur premium pour les tests de fonctionnalités premium
    $this->premiumUser = User::factory()->create([
        'is_premium' => true,
        'premium_until' => now()->addYear()
    ]);
    
    // Créer quelques questions officielles
    $this->officialTruth = TruthOrDareQuestion::factory()->create([
        'type' => 'truth',
        'intensity' => 'soft',
        'is_official' => true,
        'is_public' => false,
        'content' => 'Quelle est ta plus grande peur ?'
    ]);
    
    $this->officialDare = TruthOrDareQuestion::factory()->create([
        'type' => 'dare',
        'intensity' => 'soft',
        'is_official' => true,
        'is_public' => false,
        'content' => 'Fais 10 pompes'
    ]);
});

// Tests de récupération des sessions
it('allows user to view their sessions', function () {
    actingAs($this->user);
    
    // Créer des sessions pour l'utilisateur
    $session1 = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id,
        'name' => 'Session 1',
        'is_active' => true
    ]);
    
    $session2 = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id,
        'name' => 'Session 2',
        'is_active' => false
    ]);
    
    // Ajouter l'utilisateur comme participant
    TruthOrDareParticipant::factory()->create([
        'session_id' => $session1->id,
        'user_id' => $this->user->id,
        'status' => 'active'
    ]);
    
    $response = getJson('/api/truth-or-dare/sessions');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'creator', 'participants']
            ]
        ])
        ->assertJsonCount(2, 'data');
});

// Tests de création de session
it('allows user to create a session', function () {
    actingAs($this->user);
    
    $sessionData = [
        'name' => 'Soirée entre amis',
        'intensity' => 'soft',
        'is_public' => false,
        'max_participants' => 8,
    ];
    
    $response = postJson('/api/truth-or-dare/sessions', $sessionData);
    
    $response->assertCreated()
        ->assertJsonPath('message', 'Session créée avec succès')
        ->assertJsonPath('session.name', 'Soirée entre amis')
        ->assertJsonPath('session.intensity', 'soft')
        ->assertJsonPath('session.creator_id', $this->user->id);
    
    // Vérifier que le code de session a été généré
    $session = TruthOrDareSession::find($response->json('session.id'));
    expect($session->join_code)->toHaveLength(6);
    
    // Vérifier que l'utilisateur est ajouté comme participant
    expect($session->participants()->count())->toBe(1);
});

it('limits free users to one active session', function () {
    actingAs($this->user);
    
    // Créer une première session
    TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id,
        'is_active' => true
    ]);
    
    // Essayer de créer une deuxième session
    $sessionData = [
        'name' => 'Deuxième session',
        'intensity' => 'soft',
    ];
    
    $response = postJson('/api/truth-or-dare/sessions', $sessionData);
    
    $response->assertStatus(403)
        ->assertJsonPath('message', 'Les utilisateurs gratuits ne peuvent avoir qu\'une session active')
        ->assertJsonPath('premium_info.can_upgrade', true);
});

it('allows premium users to create multiple sessions', function () {
    actingAs($this->premiumUser);
    
    // Créer plusieurs sessions
    TruthOrDareSession::factory()->create([
        'creator_id' => $this->premiumUser->id,
        'is_active' => true
    ]);
    
    $sessionData = [
        'name' => 'Session Premium',
        'intensity' => 'spicy',
    ];
    
    $response = postJson('/api/truth-or-dare/sessions', $sessionData);
    
    $response->assertCreated();
});

// Tests de jointure de session
it('allows user to join a session with code', function () {
    actingAs($this->user);
    
    // Créer une session par un autre utilisateur
    $otherUser = User::factory()->create();
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => $otherUser->id,
        'join_code' => 'TEST01',
        'is_active' => true,
        'max_participants' => 5
    ]);
    
    // Ajouter le créateur comme participant
    TruthOrDareParticipant::factory()->create([
        'session_id' => $session->id,
        'user_id' => $otherUser->id
    ]);
    
    $response = postJson('/api/truth-or-dare/sessions/join', [
        'join_code' => 'TEST01'
    ]);
    
    $response->assertOk()
        ->assertJsonPath('message', 'Vous avez rejoint la session');
    
    // Vérifier que l'utilisateur est ajouté comme participant
    expect($session->participants()->count())->toBe(2);
});

it('prevents user from joining a full session', function () {
    actingAs($this->user);
    
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => User::factory()->create()->id,
        'join_code' => 'FULL01',
        'is_active' => true,
        'max_participants' => 2
    ]);
    
    // Remplir la session
    TruthOrDareParticipant::factory()->count(2)->create([
        'session_id' => $session->id,
        'status' => 'active'
    ]);
    
    $response = postJson('/api/truth-or-dare/sessions/join', [
        'join_code' => 'FULL01'
    ]);
    
    $response->assertStatus(400)
        ->assertJsonPath('message', 'La session est complète');
});

// Tests des questions
it('returns appropriate questions for session intensity', function () {
    actingAs($this->user);
    
    // Créer une session soft
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id,
        'intensity' => 'soft',
        'is_active' => true
    ]);
    
    $participant = TruthOrDareParticipant::factory()->create([
        'session_id' => $session->id,
        'user_id' => $this->user->id,
        'status' => 'active'
    ]);
    
    // Créer des questions de différentes intensités
    $softQuestion = TruthOrDareQuestion::factory()->create([
        'type' => 'truth',
        'intensity' => 'soft',
        'is_official' => true
    ]);
    
    $hotQuestion = TruthOrDareQuestion::factory()->create([
        'type' => 'truth',
        'intensity' => 'hot',
        'is_official' => true
    ]);
    
    $response = postJson("/api/truth-or-dare/sessions/{$session->id}/question", [
        'type' => 'truth'
    ]);
    
    $response->assertOk()
        ->assertJsonPath('question.intensity', 'soft');
});

it('excludes premium questions for free users', function () {
    actingAs($this->user);
    
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id,
        'intensity' => 'soft',
        'is_active' => true
    ]);
    
    TruthOrDareParticipant::factory()->create([
        'session_id' => $session->id,
        'user_id' => $this->user->id,
        'status' => 'active'
    ]);
    
    // Créer uniquement des questions premium
    TruthOrDareQuestion::factory()->count(3)->create([
        'type' => 'truth',
        'intensity' => 'soft',
        'is_premium' => true,
        'is_official' => true
    ]);
    
    $response = postJson("/api/truth-or-dare/sessions/{$session->id}/question", [
        'type' => 'truth'
    ]);
    
    $response->assertStatus(404)
        ->assertJsonPath('message', 'Aucune question disponible');
});

// Tests des rounds
it('allows participant to complete a round', function () {
    actingAs($this->user);
    
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id
    ]);
    
    $participant = TruthOrDareParticipant::factory()->create([
        'session_id' => $session->id,
        'user_id' => $this->user->id,
        'status' => 'active',
        'truths_answered' => 0
    ]);
    
    $round = TruthOrDareRound::factory()->create([
        'session_id' => $session->id,
        'participant_id' => $participant->id,
        'question_id' => $this->officialTruth->id,
        'choice' => 'truth',
        'status' => 'pending'
    ]);
    
    $response = postJson("/api/truth-or-dare/rounds/{$round->id}/complete", [
        'response' => 'J\'ai peur du noir',
        'rating' => 4
    ]);
    
    $response->assertOk()
        ->assertJsonPath('message', 'Round complété avec succès');
    
    // Vérifier les mises à jour
    $round->refresh();
    expect($round->status)->toBe('completed');
    expect($round->response)->toBe('J\'ai peur du noir');
    expect($round->rating)->toBe(4);
    
    $participant->refresh();
    expect($participant->truths_answered)->toBe(1);
});

it('allows free users to skip up to 3 rounds', function () {
    actingAs($this->user);
    
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id
    ]);
    
    $participant = TruthOrDareParticipant::factory()->create([
        'session_id' => $session->id,
        'user_id' => $this->user->id,
        'status' => 'active',
        'skips_used' => 2 // Déjà 2 skips utilisés
    ]);
    
    $round = TruthOrDareRound::factory()->create([
        'session_id' => $session->id,
        'participant_id' => $participant->id,
        'status' => 'pending'
    ]);
    
    // Le 3ème skip devrait fonctionner
    $response = postJson("/api/truth-or-dare/rounds/{$round->id}/skip");
    $response->assertOk();
    
    // Créer un nouveau round
    $round2 = TruthOrDareRound::factory()->create([
        'session_id' => $session->id,
        'participant_id' => $participant->id,
        'status' => 'pending'
    ]);
    
    // Le 4ème skip devrait échouer
    $participant->refresh();
    $response = postJson("/api/truth-or-dare/rounds/{$round2->id}/skip");
    
    $response->assertStatus(403)
        ->assertJsonPath('message', 'Vous avez atteint la limite de passes')
        ->assertJsonPath('premium_info.can_upgrade', true);
});

// Tests des statistiques
it('returns session statistics', function () {
    actingAs($this->user);
    
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id
    ]);
    
    // Créer des participants avec des statistiques
    $participant1 = TruthOrDareParticipant::factory()->create([
        'session_id' => $session->id,
        'user_id' => $this->user->id,
        'truths_answered' => 5,
        'dares_completed' => 3,
        'skips_used' => 1
    ]);
    
    $participant2 = TruthOrDareParticipant::factory()->create([
        'session_id' => $session->id,
        'user_id' => User::factory()->create()->id,
        'truths_answered' => 2,
        'dares_completed' => 4,
        'skips_used' => 0
    ]);
    
    // Créer des rounds
    TruthOrDareRound::factory()->count(5)->create([
        'session_id' => $session->id,
        'participant_id' => $participant1->id,
        'status' => 'completed'
    ]);
    
    TruthOrDareRound::factory()->count(3)->create([
        'session_id' => $session->id,
        'participant_id' => $participant2->id,
        'status' => 'completed'
    ]);
    
    TruthOrDareRound::factory()->create([
        'session_id' => $session->id,
        'participant_id' => $participant1->id,
        'status' => 'skipped'
    ]);
    
    $response = getJson("/api/truth-or-dare/sessions/{$session->id}/stats");
    
    $response->assertOk()
        ->assertJsonPath('total_rounds', 9)
        ->assertJsonPath('completed_rounds', 8)
        ->assertJsonPath('skipped_rounds', 1)
        ->assertJsonCount(2, 'participants');
});

// Tests de cache
it('caches user sessions', function () {
    actingAs($this->user);
    
    Cache::flush();
    
    $session = TruthOrDareSession::factory()->create([
        'creator_id' => $this->user->id
    ]);
    
    // Premier appel - met en cache
    $response1 = getJson('/api/truth-or-dare/sessions');
    $response1->assertOk();
    
    // Vérifier que le cache existe
    $cacheKey = "ozmose:truth-or-dare:sessions:user_{$this->user->id}:page_1";
    expect(Cache::has($cacheKey))->toBeTrue();
    
    // Deuxième appel - récupère du cache
    $response2 = getJson('/api/truth-or-dare/sessions');
    $response2->assertOk();
});

it('invalidates cache when creating new session', function () {
    actingAs($this->user);
    
    // Mettre en cache les sessions
    getJson('/api/truth-or-dare/sessions');
    
    // Créer une nouvelle session
    postJson('/api/truth-or-dare/sessions', [
        'name' => 'Nouvelle session',
        'intensity' => 'soft'
    ]);
    
    // Vérifier que le cache a été invalidé
    $pattern = "ozmose:truth-or-dare:sessions:user_{$this->user->id}:*";
    $keys = \Illuminate\Support\Facades\Redis::keys($pattern);
    expect($keys)->toBeEmpty();
});

// Tests de validation
it('validates session creation data', function () {
    actingAs($this->user);
    
    $response = postJson('/api/truth-or-dare/sessions', [
        'name' => '', // Nom manquant
        'intensity' => 'invalid' // Intensité invalide
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'intensity']);
});

it('validates join code format', function () {
    actingAs($this->user);
    
    $response = postJson('/api/truth-or-dare/sessions/join', [
        'join_code' => '123' // Trop court
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['join_code']);
});