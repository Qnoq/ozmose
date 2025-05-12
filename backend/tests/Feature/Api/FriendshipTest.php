<?php

use App\Models\User;
use App\Models\Friendship;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Créer deux utilisateurs pour tester les fonctionnalités d'amitié
    $this->user = User::factory()->create();
    $this->friend = User::factory()->create();
});

// Test que l'utilisateur peut envoyer une demande d'amitié
it('allows user to send friend request', function () {
    actingAs($this->user);
    
    $response = postJson("/api/friends/request/{$this->friend->id}");
    
    $response->assertCreated() // Utiliser assertCreated() au lieu de assertOk()
        ->assertJsonPath('message', 'Demande d\'amitié envoyée avec succès');
    
    $this->assertDatabaseHas('friendships', [
        'user_id' => $this->user->id,
        'friend_id' => $this->friend->id,
        'status' => 'pending'
    ]);
});

// Test que l'utilisateur ne peut pas s'envoyer une demande d'amitié à lui-même
it('prevents user from sending friend request to self', function () {
    actingAs($this->user);
    
    $response = postJson("/api/friends/request/{$this->user->id}");
    
    $response->assertStatus(400)
        ->assertJsonPath('message', 'Vous ne pouvez pas vous envoyer une demande d\'amitié à vous-même');
    
    $this->assertDatabaseMissing('friendships', [
        'user_id' => $this->user->id,
        'friend_id' => $this->user->id
    ]);
});

// Test que l'utilisateur ne peut pas envoyer une demande d'amitié à quelqu'un à qui il a déjà envoyé une demande
it('prevents duplicate friend requests', function () {
    actingAs($this->user);
    
    // Créer une amitié en attente
    Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $this->friend->id,
        'status' => 'pending'
    ]);
    
    $response = postJson("/api/friends/request/{$this->friend->id}");
    
    $response->assertStatus(400)
        ->assertJsonPath('message', 'Vous avez déjà envoyé une demande d\'amitié à cet utilisateur');
});

// Test que l'utilisateur peut accepter une demande d'amitié
it('allows user to accept friend request', function () {
    actingAs($this->friend);
    
    // Créer une demande d'amitié
    $friendship = Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $this->friend->id,
        'status' => 'pending'
    ]);
    
    $response = putJson("/api/friends/accept/{$friendship->id}");
    
    $response->assertOk()
        ->assertJsonPath('message', 'Demande d\'amitié acceptée avec succès');
    
    $this->assertDatabaseHas('friendships', [
        'id' => $friendship->id,
        'status' => 'accepted'
    ]);
});

// Test que l'utilisateur peut rejeter une demande d'amitié
it('allows user to reject friend request', function () {
    actingAs($this->friend);
    
    // Créer une demande d'amitié
    $friendship = Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $this->friend->id,
        'status' => 'pending'
    ]);
    
    $response = deleteJson("/api/friends/reject/{$friendship->id}");
    
    $response->assertOk()
        ->assertJsonPath('message', 'Demande d\'amitié rejetée avec succès');
    
    $this->assertDatabaseHas('friendships', [
        'id' => $friendship->id,
        'status' => 'rejected'
    ]);
});

// Test que l'utilisateur peut supprimer une amitié
it('allows user to remove a friendship', function () {
    actingAs($this->user);
    
    // Créer une amitié acceptée
    $friendship = Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $this->friend->id,
        'status' => 'accepted'
    ]);
    
    $response = deleteJson("/api/friends/{$friendship->id}");
    
    $response->assertOk()
        ->assertJsonPath('message', 'Amitié supprimée avec succès');
    
    $this->assertDatabaseMissing('friendships', [
        'id' => $friendship->id
    ]);
});

// Test que l'utilisateur ne peut pas accepter une demande d'amitié qui ne lui est pas destinée
it('prevents user from accepting a friend request not sent to them', function () {
    $anotherUser = User::factory()->create();
    actingAs($anotherUser);
    
    // Créer une demande d'amitié entre deux autres utilisateurs
    $friendship = Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $this->friend->id,
        'status' => 'pending'
    ]);
    
    $response = putJson("/api/friends/accept/{$friendship->id}");
    
    $response->assertStatus(403)
        ->assertJsonPath('message', 'Vous n\'êtes pas autorisé à accepter cette demande d\'amitié');
});

// Test que l'utilisateur peut voir la liste de ses amis
it('allows user to view their friends list', function () {
    actingAs($this->user);
    
    // Créer plusieurs amitiés
    $friend1 = User::factory()->create();
    $friend2 = User::factory()->create();
    
    Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $friend1->id,
        'status' => 'accepted'
    ]);
    
    Friendship::create([
        'user_id' => $friend2->id,
        'friend_id' => $this->user->id,
        'status' => 'accepted'
    ]);
    
    $response = getJson('/api/friends');
    
    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// Test que l'utilisateur peut voir les demandes d'amitié en attente
it('allows user to view pending friend requests', function () {
    actingAs($this->friend);
    
    // Créer plusieurs demandes d'amitié en attente
    $sender1 = User::factory()->create();
    $sender2 = User::factory()->create();
    
    Friendship::create([
        'user_id' => $sender1->id,
        'friend_id' => $this->friend->id,
        'status' => 'pending'
    ]);
    
    Friendship::create([
        'user_id' => $sender2->id,
        'friend_id' => $this->friend->id,
        'status' => 'pending'
    ]);
    
    $response = getJson('/api/friends/requests/pending');
    
    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

// Test que l'amitié entre deux utilisateurs est bidirectionnelle (apparaît dans la liste des deux utilisateurs)
it('friendship is bidirectional between users', function () {
    // Créer une amitié acceptée
    Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $this->friend->id,
        'status' => 'accepted'
    ]);
    
    // Vérifier du côté du premier utilisateur
    actingAs($this->user);
    $response1 = getJson('/api/friends');
    $response1->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->friend->id);
    
    // Vérifier du côté du deuxième utilisateur
    actingAs($this->friend);
    $response2 = getJson('/api/friends');
    $response2->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->user->id);
});

// Test que l'utilisateur ne peut pas supprimer une amitié qui ne le concerne pas
it('prevents user from removing a friendship they are not part of', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    // Créer une amitié entre deux autres utilisateurs
    $friendship = Friendship::create([
        'user_id' => $user1->id,
        'friend_id' => $user2->id,
        'status' => 'accepted'
    ]);
    
    actingAs($this->user);
    $response = deleteJson("/api/friends/{$friendship->id}");
    
    $response->assertStatus(403)
        ->assertJsonPath('message', 'Vous n\'êtes pas autorisé à supprimer cette amitié');
    
    $this->assertDatabaseHas('friendships', [
        'id' => $friendship->id
    ]);
});