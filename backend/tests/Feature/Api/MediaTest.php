<?php

use App\Models\User;
use App\Models\Challenge;
use App\Models\ChallengeMedia;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\deleteJson;
use App\Models\ChallengeParticipation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Configurer le disque de stockage pour les tests
    Storage::fake('public');
    
    // Créer un utilisateur pour les tests
    $this->user = User::factory()->create();
    
    // Créer un utilisateur premium pour les tests de fonctionnalités premium
    $this->premiumUser = User::factory()->create([
        'is_premium' => true,
        'premium_until' => now()->addYear()
    ]);
    
    // Créer un défi pour les tests
    $this->challenge = Challenge::factory()->create([
        'creator_id' => $this->user->id,
        'is_public' => true
    ]);
});

// Test qu'un utilisateur peut récupérer ses médias
it('allows user to retrieve their media', function () {
    actingAs($this->user);
    
    // Créer quelques médias pour l'utilisateur
    $media1 = ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/image1.jpg'
    ]);
    
    $media2 = ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'video',
        'path' => 'tests/video1.mp4'
    ]);
    
    $response = getJson('/api/media');
    
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $media1->id);
});

// Test qu'un utilisateur peut récupérer un média spécifique
it('allows user to retrieve specific media', function () {
    actingAs($this->user);
    
    $media = ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/image1.jpg'
    ]);
    
    $response = getJson("/api/media/{$media->id}");
    
    $response->assertOk()
        ->assertJsonPath('media.id', $media->id)
        ->assertJsonPath('media.type', 'image');
});

// Test qu'un utilisateur ne peut pas récupérer un média qui ne lui appartient pas
it('prevents user from retrieving media of others', function () {
    $otherUser = User::factory()->create();
    
    $media = ChallengeMedia::factory()->create([
        'user_id' => $otherUser->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/image1.jpg',
        'is_public' => false // Média privé
    ]);
    
    actingAs($this->user);
    
    $response = getJson("/api/media/{$media->id}");
    
    $response->assertForbidden();
});

// Test qu'un utilisateur peut uploader une image
it('allows user to upload an image', function () {
    actingAs($this->user);
    
    // Créer une participation
    $participation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    $image = UploadedFile::fake()->image('photo.jpg', 800, 600);
    
    // Utiliser la route d'ajout de preuve au lieu de l'upload direct
    $response = postJson("/api/participations/{$participation->id}/proof", [
        'media' => $image,
        'caption' => 'Test photo upload',
        'is_public' => true
    ]);
    
    $response->assertOk();
    
    // Vérifier que le média a été créé dans la base de données
    $this->assertDatabaseHas('challenge_media', [
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'participation_id' => $participation->id,
    ]);
});

// Test qu'un utilisateur peut uploader une vidéo
it('allows user to upload a video', function () {
    actingAs($this->user);
    
    // Si vous avez créé une participation dans le test précédent
    $participation = ChallengeParticipation::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'status' => 'accepted'
    ]);
    
    $video = UploadedFile::fake()->create('video.mp4', 5 * 1024, 'video/mp4'); // 5MB video
    
    // Utiliser la même route que pour l'upload d'image
    $response = postJson("/api/participations/{$participation->id}/proof", [
        'media' => $video, // Utilisez 'media' au lieu de 'file' si c'est ce que votre API attend
        'caption' => 'Test video upload',
        'is_public' => true
    ]);
    
    // Si la route renvoie 200 OK au lieu de 201 Created
    $response->assertOk(); // ou $response->assertSuccessful();
    
    // Vérifier que le média a été créé dans la base de données
    $this->assertDatabaseHas('challenge_media', [
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'participation_id' => $participation->id,
        'caption' => 'Test video upload'
    ]);
});

// Test qu'un utilisateur peut mettre à jour un média
it('allows user to update media', function () {
    actingAs($this->user);
    
    $media = ChallengeMedia::create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/image1.jpg',
        'caption' => 'Original caption',
        'is_public' => true,
        'original_name' => 'image1.jpg',
        'size' => 1024,
        'mime_type' => 'image/jpeg',
        'storage_disk' => 'public'
    ]);
    
    $response = putJson("/api/media/{$media->id}", [
        'caption' => 'Updated caption',
        'is_public' => false
    ]);
    
    // Vérifier seulement le statut et le message
    $response->assertOk()
        ->assertJsonPath('message', 'Média mis à jour avec succès');
    
    // Vérifier que la base de données a été mise à jour
    $this->assertDatabaseHas('challenge_media', [
        'id' => $media->id,
        'caption' => 'Updated caption',
        'is_public' => false
    ]);
    
    // Vérifier que le modèle a bien été mis à jour
    $updatedMedia = ChallengeMedia::find($media->id);
    expect($updatedMedia->caption)->toBe('Updated caption');
    expect($updatedMedia->is_public)->toBeFalse();
});

// Test qu'un utilisateur ne peut pas mettre à jour un média qui ne lui appartient pas
it('prevents user from updating media of others', function () {
    $otherUser = User::factory()->create();
    
    $media = ChallengeMedia::factory()->create([
        'user_id' => $otherUser->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/image1.jpg'
    ]);
    
    actingAs($this->user);
    
    $response = putJson("/api/media/{$media->id}", [
        'caption' => 'Attempted update'
    ]);
    
    $response->assertForbidden();
    
    // Vérifier que le média n'a pas été mis à jour
    $this->assertDatabaseMissing('challenge_media', [
        'id' => $media->id,
        'caption' => 'Attempted update'
    ]);
});

// Test qu'un utilisateur peut supprimer un média
it('allows user to delete media', function () {
    actingAs($this->user);
    
    $media = ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'test_delete_image.jpg'
    ]);
    
    // Créer un fichier factice pour tester la suppression physique
    Storage::disk('public')->put($media->path, 'test content');
    
    $response = deleteJson("/api/media/{$media->id}");
    
    $response->assertOk()
        ->assertJsonPath('message', 'Média supprimé avec succès');
    
    // Vérifier que le média a été supprimé de la base de données
    $this->assertDatabaseMissing('challenge_media', [
        'id' => $media->id
    ]);
    
    // Vérifier que le fichier a été supprimé
    Storage::disk('public')->assertMissing($media->path);
});

// Test qu'un utilisateur ne peut pas supprimer un média qui ne lui appartient pas
it('prevents user from deleting media of others', function () {
    $otherUser = User::factory()->create();
    
    $media = ChallengeMedia::factory()->create([
        'user_id' => $otherUser->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/image1.jpg'
    ]);
    
    actingAs($this->user);
    
    $response = deleteJson("/api/media/{$media->id}");
    
    $response->assertForbidden();
    
    // Vérifier que le média n'a pas été supprimé
    $this->assertDatabaseHas('challenge_media', [
        'id' => $media->id
    ]);
});

// Test qu'un utilisateur peut filtrer ses médias par type
it('allows user to filter media by type', function () {
    actingAs($this->user);
    
    // Créer des médias de différents types
    ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image'
    ]);
    
    ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'video'
    ]);
    
    // Récupérer uniquement les images
    $response = getJson('/api/media?type=image');
    
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'image');
    
    // Récupérer uniquement les vidéos
    $response = getJson('/api/media?type=video');
    
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'video');
});

// Test des fonctionnalités premium - Création de compilation
it('allows premium user to create compilation', function () {
    actingAs($this->premiumUser);
    
    // Créer des médias pour l'utilisateur premium
    $media1 = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/comp_image1.jpg'
    ]);
    
    $media2 = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'challenge_id' => $this->challenge->id,
        'type' => 'image',
        'path' => 'tests/comp_image2.jpg'
    ]);
    
    // Afficher les IDs des médias pour vérifier qu'ils sont valides
    info('Médias créés pour le test:', [
        'media1_id' => $media1->id,
        'media2_id' => $media2->id,
        'challenge_id' => $this->challenge->id
    ]);
    
    $response = postJson('/api/media/compilations', [
        'media_ids' => [$media1->id, $media2->id],
        'title' => 'Ma compilation de test',
        'caption' => 'Une superbe compilation',
        'is_public' => true,
        'challenge_id' => $this->challenge->id
    ]);
    
    // Afficher l'erreur complète
    if ($response->status() === 500) {
        dd([
            'status' => $response->status(),
            'response' => $response->json(),
            'headers' => $response->headers->all()
        ]);
    }
    
    $response->assertCreated()
        ->assertJsonPath('message', 'Compilation créée avec succès')
        ->assertJsonPath('compilation.type', 'compilation');
    
    // Vérifier que la compilation a été créée
    $this->assertDatabaseHas('challenge_media', [
        'user_id' => $this->premiumUser->id,
        'type' => 'compilation',
        'original_name' => 'Ma compilation de test',
        'challenge_id' => $this->challenge->id
    ]);
});

// Test qu'un utilisateur non premium ne peut pas créer de compilation
it('prevents non-premium user from creating compilation', function () {
    actingAs($this->user); // Utilisateur non premium
    
    // Créer des médias pour l'utilisateur
    $media1 = ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'image',
        'path' => 'tests/comp_image1.jpg'
    ]);
    
    $media2 = ChallengeMedia::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'image',
        'path' => 'tests/comp_image2.jpg'
    ]);
    
    $response = postJson('/api/media/compilations', [
        'media_ids' => [$media1->id, $media2->id],
        'title' => 'Tentative de compilation',
        'caption' => 'Compilation non premium'
    ]);
    
    $response->assertForbidden()
        ->assertJsonPath('message', 'Cette fonctionnalité est réservée aux membres premium');
    
    // Vérifier qu'aucune compilation n'a été créée
    $this->assertDatabaseMissing('challenge_media', [
        'user_id' => $this->user->id,
        'type' => 'compilation'
    ]);
});

// Test qu'un utilisateur premium peut récupérer les détails d'une compilation
it('allows premium user to retrieve compilation details', function () {
    actingAs($this->premiumUser);
    
    // Créer une compilation avec des médias sources
    $compilation = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'type' => 'compilation',
        'original_name' => 'Ma compilation'
    ]);
    
    $media1 = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'type' => 'image',
        'path' => 'tests/detail_image1.jpg',
        'in_compilation' => true,
        'compilation_id' => $compilation->id
    ]);
    
    $media2 = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'type' => 'image',
        'path' => 'tests/detail_image2.jpg',
        'in_compilation' => true,
        'compilation_id' => $compilation->id
    ]);
    
    $response = getJson("/api/media/compilations/{$compilation->id}");
    
    $response->assertOk()
        ->assertJsonPath('compilation.id', $compilation->id)
        ->assertJsonPath('compilation.type', 'compilation')
        ->assertJsonCount(2, 'sources');
});

// Test qu'un utilisateur premium peut supprimer une compilation
it('allows premium user to delete compilation', function () {
    actingAs($this->premiumUser);
    
    // Créer une compilation avec des médias sources
    $compilation = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'type' => 'compilation',
        'original_name' => 'Compilation à supprimer'
    ]);
    
    $media1 = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'type' => 'image',
        'path' => 'tests/delete_comp_image1.jpg',
        'in_compilation' => true,
        'compilation_id' => $compilation->id
    ]);
    
    $media2 = ChallengeMedia::factory()->create([
        'user_id' => $this->premiumUser->id,
        'type' => 'image',
        'path' => 'tests/delete_comp_image2.jpg',
        'in_compilation' => true,
        'compilation_id' => $compilation->id
    ]);
    
    $response = deleteJson("/api/media/compilations/{$compilation->id}");
    
    $response->assertOk()
        ->assertJsonPath('message', 'Compilation supprimée avec succès');
    
    // Vérifier que la compilation a été supprimée
    $this->assertDatabaseMissing('challenge_media', [
        'id' => $compilation->id
    ]);
    
    // Vérifier que les médias sources ont été mis à jour
    $this->assertDatabaseHas('challenge_media', [
        'id' => $media1->id,
        'in_compilation' => false,
        'compilation_id' => null
    ]);
    
    $this->assertDatabaseHas('challenge_media', [
        'id' => $media2->id,
        'in_compilation' => false,
        'compilation_id' => null
    ]);
});

// Test de vérification des limites de stockage
it('provides storage limits information', function () {
    // Arrange: Préparer un utilisateur non-premium avec des médias
    actingAs($this->user);
    
    ChallengeMedia::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'type' => 'image',
        'size' => 1024 * 1024 // 1MB par image
    ]);
    
    // Act: Obtenir les limites de stockage directement du service
    $mediaService = resolve(\App\Services\MediaService::class);
    $storageInfo = $mediaService->checkStorageLimit($this->user);
    
    // Assert: Vérifier les résultats
    expect($storageInfo)->toHaveKeys(['used', 'limit', 'available', 'percentage', 'is_premium']);
    expect($storageInfo['used'])->toEqual(3 * 1024 * 1024); // 3MB
    expect($storageInfo['is_premium'])->toBeFalse();
    expect($storageInfo['limit'])->toEqual(2 * 1024 * 1024 * 1024); // 2GB
});

it('provides premium storage limits information', function () {
    // Arrange: Préparer un utilisateur premium avec des médias
    actingAs($this->premiumUser);
    
    ChallengeMedia::factory()->count(5)->create([
        'user_id' => $this->premiumUser->id,
        'type' => 'image',
        'size' => 1024 * 1024 // 1MB par image
    ]);
    
    // Act: Obtenir les limites de stockage directement du service
    $mediaService = resolve(\App\Services\MediaService::class);
    $storageInfo = $mediaService->checkStorageLimit($this->premiumUser);
    
    // Assert: Vérifier les résultats pour un utilisateur premium
    expect($storageInfo['used'])->toEqual(5 * 1024 * 1024); // 5MB
    expect($storageInfo['is_premium'])->toBeTrue();
    expect($storageInfo['limit'])->toEqual(20 * 1024 * 1024 * 1024); // 20GB
});