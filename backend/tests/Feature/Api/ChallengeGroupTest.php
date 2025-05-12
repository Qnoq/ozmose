<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Challenge;
use App\Models\ChallengeGroup;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\deleteJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Créer des utilisateurs test
    $this->user = User::factory()->create(['is_premium' => false]);
    $this->premiumUser = User::factory()->create(['is_premium' => true]);
    $this->anotherUser = User::factory()->create();
    
    // Créer un groupe pour les tests
    $this->group = ChallengeGroup::factory()->create([
        'creator_id' => $this->user->id,
        'name' => 'Groupe de test',
        'description' => 'Description du groupe de test',
        'max_members' => 10
    ]);
    
    // Ajouter le créateur comme membre du groupe avec le rôle 'creator'
    $this->group->members()->attach($this->user->id, ['role' => 'creator']);
    
    // Créer un défi pour les tests d'ajout de défis au groupe
    $this->challenge = Challenge::factory()->create([
        'creator_id' => $this->user->id,
        'is_public' => true
    ]);
    
    // Créer un groupe premium pour les tests
    $this->premiumGroup = ChallengeGroup::factory()->create([
        'creator_id' => $this->premiumUser->id,
        'name' => 'Groupe premium',
        'description' => 'Groupe avec fonctionnalités premium',
        'premium_only' => true,
        'max_members' => 50
    ]);
    
    // Ajouter le créateur premium comme membre du groupe premium
    $this->premiumGroup->members()->attach($this->premiumUser->id, ['role' => 'creator']);
});

// Tests de récupération de groupes
it('can list user challenge groups', function () {
    // L'utilisateur devrait voir ses propres groupes
    actingAs($this->user);
    
    $response = getJson('/api/challenge-groups');
    
    $response->assertOk()
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.name', 'Groupe de test');
});

it('shows empty list when user has no groups', function () {
    // Un nouvel utilisateur sans groupes
    $newUser = User::factory()->create();
    actingAs($newUser);
    
    $response = getJson('/api/challenge-groups');
    
    $response->assertOk()
             ->assertJsonCount(0, 'data');
});

// Tests de création de groupes
it('can create a new challenge group', function () {
    actingAs($this->user);
    
    $response = postJson('/api/challenge-groups', [
        'name' => 'Nouveau groupe',
        'description' => 'Description du nouveau groupe',
        'max_members' => 5
    ]);
    
    $response->assertCreated()
             ->assertJsonPath('message', 'Groupe créé avec succès')
             ->assertJsonPath('group.name', 'Nouveau groupe');
    
    // Vérifier que le groupe a été créé en base de données
    $this->assertDatabaseHas('challenge_groups', [
        'name' => 'Nouveau groupe',
        'creator_id' => $this->user->id
    ]);
});

it('limits max_members for non-premium users', function () {
    actingAs($this->user); // Utilisateur non premium
    
    $response = postJson('/api/challenge-groups', [
        'name' => 'Groupe avec trop de membres',
        'description' => 'Ce groupe dépasse la limite pour un utilisateur non premium',
        'max_members' => 30 // Trop élevé pour un utilisateur non premium
    ]);
    
    $response->assertCreated(); // Devrait quand même créer le groupe
    
    // Mais avec une limite de membres réduite
    $group = ChallengeGroup::where('name', 'Groupe avec trop de membres')->first();
    expect($group->max_members)->toBe(10); // Limite pour les utilisateurs non premium
});

it('enforces group limits for non-premium users', function () {
    actingAs($this->user);
    
    // Comme l'utilisateur a déjà un groupe créé dans beforeEach,
    // nous n'avons besoin de créer que 2 groupes supplémentaires pour atteindre la limite de 3
    for ($i = 0; $i < 2; $i++) {
        $group = ChallengeGroup::factory()->create([
            'creator_id' => $this->user->id,
            'name' => "Groupe test {$i}"
        ]);
        
        // Attacher l'utilisateur comme membre
        $group->members()->attach($this->user->id, ['role' => 'creator']);
    }
    
    // Vérifier que l'utilisateur a bien 3 groupes (1 de beforeEach + 2 créés ici)
    $count = $this->user->challengeGroups()->count();
    expect($count)->toBe(3); // Maintenant cela devrait être correct
    
    // Tentative de création d'un groupe supplémentaire
    $response = postJson('/api/challenge-groups', [
        'name' => 'Groupe de trop',
        'description' => 'Ce groupe dépasse la limite de groupes'
    ]);
    
    $response->assertForbidden()
             ->assertJsonPath('message', "Vous avez atteint la limite de 3 groupes pour les utilisateurs gratuits");
});

it('allows premium users to create unlimited groups', function () {
    actingAs($this->premiumUser);
    
    // Créer déjà 5 groupes (au-delà de la limite gratuite)
    ChallengeGroup::factory()->count(5)->create([
        'creator_id' => $this->premiumUser->id
    ]);
    
    // Tentative de création d'un groupe supplémentaire
    $response = postJson('/api/challenge-groups', [
        'name' => 'Encore un groupe',
        'description' => 'Un groupe de plus pour l\'utilisateur premium'
    ]);
    
    $response->assertCreated()
             ->assertJsonPath('group.name', 'Encore un groupe');
});

// Tests de détails de groupe
it('can show group details', function () {
    actingAs($this->user);
    
    $response = getJson("/api/challenge-groups/{$this->group->id}");
    
    $response->assertOk()
             ->assertJsonPath('group.name', 'Groupe de test')
             ->assertJsonPath('group.description', 'Description du groupe de test');
});

it('prevents non-members from viewing group details', function () {
    actingAs($this->anotherUser);
    
    $response = getJson("/api/challenge-groups/{$this->group->id}");
    
    $response->assertForbidden()
             ->assertJsonPath('message', 'Vous n\'êtes pas membre de ce groupe');
});

// Tests de mise à jour de groupe
it('can update group as creator', function () {
    actingAs($this->user);
    
    $response = putJson("/api/challenge-groups/{$this->group->id}", [
        'name' => 'Groupe renommé',
        'description' => 'Description mise à jour'
    ]);
    
    $response->assertOk()
             ->assertJsonPath('message', 'Groupe mis à jour avec succès')
             ->assertJsonPath('group.name', 'Groupe renommé');
    
    // Vérifier que le groupe a été mis à jour en base de données
    $this->assertDatabaseHas('challenge_groups', [
        'id' => $this->group->id,
        'name' => 'Groupe renommé',
        'description' => 'Description mise à jour'
    ]);
});

it('prevents non-admins from updating group', function () {
    // Attacher l'autre utilisateur comme membre normal
    $this->group->members()->attach($this->anotherUser->id, ['role' => 'member']);
    
    actingAs($this->anotherUser);
    
    $response = putJson("/api/challenge-groups/{$this->group->id}", [
        'name' => 'Tentative de modification',
        'description' => 'Ceci ne devrait pas fonctionner'
    ]);
    
    $response->assertForbidden()
             ->assertJsonPath('message', 'Vous n\'avez pas les droits pour modifier ce groupe');
    
    // Vérifier que le groupe n'a pas été modifié
    $this->assertDatabaseMissing('challenge_groups', [
        'id' => $this->group->id,
        'name' => 'Tentative de modification'
    ]);
});

// Tests de suppression de groupe
it('can delete group as creator', function () {
    actingAs($this->user);
    
    $response = deleteJson("/api/challenge-groups/{$this->group->id}");
    
    $response->assertOk()
             ->assertJsonPath('message', 'Groupe supprimé avec succès');
    
    // Vérifier que le groupe a été supprimé
    $this->assertDatabaseMissing('challenge_groups', ['id' => $this->group->id]);
});

it('prevents non-creators from deleting group', function () {
    // Ajouter l'autre utilisateur comme admin (pas créateur)
    $this->group->members()->attach($this->anotherUser->id, ['role' => 'admin']);
    
    actingAs($this->anotherUser);
    
    $response = deleteJson("/api/challenge-groups/{$this->group->id}");
    
    $response->assertForbidden()
             ->assertJsonPath('message', 'Seul le créateur du groupe peut le supprimer');
    
    // Vérifier que le groupe existe toujours
    $this->assertDatabaseHas('challenge_groups', ['id' => $this->group->id]);
});

it('can add a new member to group', function () {
    actingAs($this->user);
    
    // S'assurer que l'utilisateur n'est pas déjà membre
    $this->group->members()->detach($this->anotherUser->id);
    
    $response = postJson("/api/challenge-groups/{$this->group->id}/members", [
        'user_id' => $this->anotherUser->id,
        'role' => 'member'
    ]);
    
    $response->assertOk()
             ->assertJsonPath('message', 'Membre ajouté avec succès');
    
    // Vérifier que l'utilisateur a été ajouté au groupe
    $this->assertDatabaseHas('challenge_group_user', [
        'challenge_group_id' => $this->group->id,
        'user_id' => $this->anotherUser->id,
        'role' => 'member'
    ]);
});

it('prevents adding an existing member', function () {
    actingAs($this->user);
    
    // D'abord ajouter l'utilisateur
    $this->group->members()->attach($this->anotherUser->id, ['role' => 'member']);
    
    // Tenter de l'ajouter à nouveau
    $response = postJson("/api/challenge-groups/{$this->group->id}/members", [
        'user_id' => $this->anotherUser->id,
        'role' => 'member'
    ]);
    
    $response->assertStatus(400)
             ->assertJsonPath('message', 'Cet utilisateur est déjà membre du groupe');
    
    // Vérifier qu'il n'y a qu'une seule entrée pour cet utilisateur
    $count = DB::table('challenge_group_user')
        ->where('challenge_group_id', $this->group->id)
        ->where('user_id', $this->anotherUser->id)
        ->count();
    
    expect($count)->toBe(1); // Il ne devrait y avoir qu'une seule entrée
});

it('can remove members from group', function () {
    // D'abord ajouter un membre
    $this->group->members()->attach($this->anotherUser->id, ['role' => 'member']);
    
    actingAs($this->user);
    
    $response = deleteJson("/api/challenge-groups/{$this->group->id}/members/{$this->anotherUser->id}");
    
    $response->assertOk()
             ->assertJsonPath('message', 'Membre retiré avec succès');
    
    // Vérifier que l'utilisateur a été retiré du groupe
    $this->assertDatabaseMissing('challenge_group_user', [
        'challenge_group_id' => $this->group->id,
        'user_id' => $this->anotherUser->id
    ]);
});

it('can change member role as creator', function () {
    // D'abord ajouter un membre
    $this->group->members()->attach($this->anotherUser->id, ['role' => 'member']);
    
    actingAs($this->user);
    
    $response = putJson("/api/challenge-groups/{$this->group->id}/members/{$this->anotherUser->id}/role", [
        'role' => 'admin'
    ]);
    
    $response->assertOk()
             ->assertJsonPath('message', 'Rôle modifié avec succès')
             ->assertJsonPath('user.role', 'admin');
    
    // Vérifier que le rôle a été modifié
    $this->assertDatabaseHas('challenge_group_user', [
        'challenge_group_id' => $this->group->id,
        'user_id' => $this->anotherUser->id,
        'role' => 'admin'
    ]);
});

it('cannot change creator role', function () {
    actingAs($this->user);
    
    $response = putJson("/api/challenge-groups/{$this->group->id}/members/{$this->user->id}/role", [
        'role' => 'member'
    ]);
    
    $response->assertBadRequest()
             ->assertJsonPath('message', 'Le rôle du créateur ne peut pas être modifié');
});

// Tests de gestion des défis
it('can add challenges to group', function () {
    actingAs($this->user);
    
    $response = postJson("/api/challenge-groups/{$this->group->id}/challenges", [
        'challenge_id' => $this->challenge->id
    ]);
    
    $response->assertOk()
             ->assertJsonPath('message', 'Défi ajouté au groupe avec succès');
    
    // Vérifier que le défi a été ajouté au groupe
    $this->assertDatabaseHas('challenge_group_challenge', [
        'challenge_group_id' => $this->group->id,
        'challenge_id' => $this->challenge->id
    ]);
});

it('can remove challenges from group', function () {
    // D'abord ajouter un défi
    $this->group->challenges()->attach($this->challenge->id);
    
    actingAs($this->user);
    
    $response = deleteJson("/api/challenge-groups/{$this->group->id}/challenges/{$this->challenge->id}");
    
    $response->assertOk()
             ->assertJsonPath('message', 'Défi retiré du groupe avec succès');
    
    // Vérifier que le défi a été retiré du groupe
    $this->assertDatabaseMissing('challenge_group_challenge', [
        'challenge_group_id' => $this->group->id,
        'challenge_id' => $this->challenge->id
    ]);
});

it('lists group members', function () {
    // Ajouter quelques membres
    $this->group->members()->attach($this->anotherUser->id, ['role' => 'member']);
    $this->group->members()->attach($this->premiumUser->id, ['role' => 'admin']);
    
    actingAs($this->user);
    
    $response = getJson("/api/challenge-groups/{$this->group->id}/members");
    
    $response->assertOk()
             ->assertJsonCount(3, 'data'); // Le créateur + 2 membres ajoutés
    
    // Vérifier que le rôle est bien présent dans la réponse
    $hasCreator = false;
    $data = $response->json('data');
    
    foreach ($data as $member) {
        if (isset($member['role']) && $member['role'] === 'creator') {
            $hasCreator = true;
            break;
        }
    }
    
    expect($hasCreator)->toBeTrue('Aucun membre avec le rôle "creator" trouvé dans la réponse');
});

it('lists group challenges', function () {
    // Ajouter quelques défis
    $challenges = Challenge::factory()->count(3)->create([
        'creator_id' => $this->user->id,
        'is_public' => true
    ]);
    
    foreach ($challenges as $challenge) {
        $this->group->challenges()->attach($challenge->id);
    }
    
    actingAs($this->user);
    
    $response = getJson("/api/challenge-groups/{$this->group->id}/challenges");
    
    $response->assertOk()
             ->assertJsonCount(3, 'data');
});

// Tests spécifiques aux fonctionnalités premium
it('prevents non-premium users from joining premium-only groups', function () {
    actingAs($this->premiumUser);
    
    $response = postJson("/api/challenge-groups/{$this->premiumGroup->id}/members", [
        'user_id' => $this->user->id, // Utilisateur non premium
        'role' => 'member'
    ]);
    
    $response->assertBadRequest()
             ->assertJsonPath('message', 'Ce groupe est réservé aux membres premium');
});