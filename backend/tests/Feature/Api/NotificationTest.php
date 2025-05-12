<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Challenge;
use Illuminate\Support\Str;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use App\Models\ChallengeParticipation;
use App\Notifications\FriendRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use App\Notifications\ChallengeInvitationNotification;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

// Helper pour créer des notifications de test
function createSampleNotifications($user, $count = 3) {
    $notifications = [];
    
    for ($i = 0; $i < $count; $i++) {
        $notification = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'test_notification',
                'message' => "Test notification {$i}",
            ],
            'read_at' => null,
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ]);
        
        $notifications[] = $notification;
    }
    
    return $notifications;
}

it('retrieves all notifications', function () {
    $notifications = createSampleNotifications($this->user, 5);
    
    actingAs($this->user);
    
    $response = getJson('/api/notifications');
    
    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(5);
    
    // Vérifier la structure des notifications
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id', 'type', 'data', 'read_at', 'created_at', 'updated_at', 
                'is_read', 'time_ago'
            ]
        ]
    ]);
});

it('retrieves unread notifications', function () {
    // Créer 3 notifications non lues et 2 lues
    $unreadNotifications = createSampleNotifications($this->user, 3);
    $readNotifications = createSampleNotifications($this->user, 2);
    
    // Marquer certaines comme lues
    foreach ($readNotifications as $notification) {
        $notification->markAsRead();
    }
    
    actingAs($this->user);
    
    $response = getJson('/api/notifications/unread');
    
    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(3);
    
    // Vérifier que toutes sont non lues
    foreach ($responseData as $notification) {
        expect($notification['is_read'])->toBeFalse();
        expect($notification['read_at'])->toBeNull();
    }
});

it('marks a notification as read', function () {
    $notifications = createSampleNotifications($this->user, 1);
    $notificationId = $notifications[0]->id;
    
    actingAs($this->user);
    
    $response = putJson("/api/notifications/{$notificationId}/read");
    
    $response->assertOk()
             ->assertJsonPath('message', 'Notification marquée comme lue');
    
    // Au lieu d'utiliser assertDatabaseHas qui peut causer des problèmes avec la base de données,
    // vérifier directement en rechargeant la notification
    $notification = DatabaseNotification::find($notificationId);
    expect($notification->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    createSampleNotifications($this->user, 5);
    
    actingAs($this->user);
    
    $response = putJson('/api/notifications/read-all');
    
    $response->assertOk()
             ->assertJsonPath('message', 'Toutes les notifications ont été marquées comme lues');
    
    // Vérifier qu'il n'y a plus de notifications non lues
    $unreadCount = $this->user->unreadNotifications()->count();
    expect($unreadCount)->toBe(0);
});

it('deletes a notification', function () {
    $notifications = createSampleNotifications($this->user, 1);
    $notificationId = $notifications[0]->id;
    
    actingAs($this->user);
    
    $response = deleteJson("/api/notifications/{$notificationId}");
    
    $response->assertOk()
             ->assertJsonPath('message', 'Notification supprimée');
    
    // Au lieu d'utiliser assertDatabaseMissing, vérifier directement que la notification n'existe plus
    $notification = DatabaseNotification::find($notificationId);
    expect($notification)->toBeNull();
});

it('counts unread notifications', function () {
    createSampleNotifications($this->user, 3);
    
    actingAs($this->user);
    
    $response = getJson('/api/notifications/count');
    
    $response->assertOk()
             ->assertJsonPath('count', 3);
    
    // Marquer une notification comme lue
    $notification = $this->user->notifications->first();
    $notification->markAsRead();
    
    // Vérifier que le compteur est mis à jour
    $response = getJson('/api/notifications/count');
    $response->assertJsonPath('count', 2);
});

it('retrieves challenge invitation notification', function () {
    // Créer une catégorie pour le défi
    $category = Category::create([
        'name' => 'Test Category',
        'description' => 'Test category description',
        'icon' => 'test-icon'
    ]);
    
    // Créer un défi avec la catégorie correcte
    $challenge = Challenge::create([
        'title' => 'Test Challenge',
        'description' => 'Description',
        'instructions' => 'Instructions',
        'creator_id' => $this->user->id,
        'category_id' => $category->id, // Utiliser l'ID de la catégorie créée
        'difficulty' => 'facile',
        'is_public' => true
    ]);
    
    // Créer un autre utilisateur pour l'invitation
    $friend = User::factory()->create();
    
    // Créer une participation
    $participation = ChallengeParticipation::create([
        'user_id' => $friend->id,
        'challenge_id' => $challenge->id,
        'status' => 'invited',
        'invited_by' => $this->user->id
    ]);
    
    // Envoyer une notification
    $friend->notify(new ChallengeInvitationNotification(
        $this->user,
        $challenge,
        $participation,
        'Viens relever ce défi!'
    ));
    
    // Se connecter en tant qu'ami
    actingAs($friend);
    
    // Récupérer les notifications
    $response = getJson('/api/notifications');
    
    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(1);
    
    // Vérifier le contenu de la notification
    $notification = $responseData[0];
    expect($notification['data']['type'])->toBe('challenge_invitation');
    expect($notification['data']['challenge_title'])->toBe('Test Challenge');
    expect($notification['data']['custom_message'])->toBe('Viens relever ce défi!');
});

it('retrieves friend request notification', function () {
    // Créer un autre utilisateur
    $friend = User::factory()->create();
    
    // Envoyer une notification de demande d'amitié
    $friend->notify(new FriendRequestNotification($this->user));
    
    // Se connecter en tant qu'ami
    actingAs($friend);
    
    // Récupérer les notifications
    $response = getJson('/api/notifications');
    
    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(1);
    
    // Vérifier le contenu de la notification
    $notification = $responseData[0];
    expect($notification['data']['type'])->toBe('friend_request');
    expect($notification['data']['sender_id'])->toBe($this->user->id);
});

it('paginates notifications correctly', function () {
    // Créer 25 notifications (la pagination par défaut est de 20)
    createSampleNotifications($this->user, 25);
    
    actingAs($this->user);
    
    $response = getJson('/api/notifications');
    
    $response->assertOk();
    
    // Vérifier la structure de pagination
    $response->assertJsonStructure([
        'data',
        'links',
        'meta' => [
            'current_page',
            'from',
            'last_page',
            'path',
            'per_page',
            'to',
            'total'
        ]
    ]);
    
    // Vérifier qu'on a 20 notifications par page
    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(20);
    
    // Vérifier le total
    $meta = $response->json('meta');
    expect($meta['total'])->toBe(25);
    expect($meta['last_page'])->toBe(2);
    
    // Vérifier la deuxième page
    $response = getJson('/api/notifications?page=2');
    $responseData = $response->json('data');
    expect($responseData)->toHaveCount(5);
});