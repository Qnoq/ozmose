<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\ChallengeGroupController;
use App\Http\Controllers\Api\ChallengeMediaController;
use App\Http\Controllers\Api\ChallengeParticipationController;

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées par Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Profil utilisateur
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);
    Route::get('/user/challenges/created', [UserController::class, 'createdChallenges']);
    Route::get('/user/challenges/participating', [UserController::class, 'participatingChallenges']);
    Route::get('/user/friends', [UserController::class, 'friends']);
    Route::get('/user/friends/pending', [UserController::class, 'pendingFriendRequests']);
    
    // Recherche et autres utilisateurs
    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/{user}', [UserController::class, 'showOtherUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Challenges
    Route::apiResource('challenges', ChallengeController::class);
    Route::get('/challenges/{challenge}/participants', [ChallengeController::class, 'participants']);
    
    // Participations
    Route::post('/challenges/{challenge}/participate', [ChallengeParticipationController::class, 'store']);
    Route::put('/participations/{participation}', [ChallengeParticipationController::class, 'update']);
    Route::delete('/participations/{participation}', [ChallengeParticipationController::class, 'destroy']);

    // Nouvelles routes pour la gestion des participations
    Route::get('/participations', [ChallengeParticipationController::class, 'index']); // Liste toutes les participations de l'utilisateur
    Route::get('/participations/{participation}', [ChallengeParticipationController::class, 'show']); // Affiche les détails d'une participation spécifique
    
    // Routes pour les invitations
    Route::post('/challenges/{challenge}/invite', [ChallengeParticipationController::class, 'inviteFriends']); // Inviter des amis à un défi
    Route::put('/participations/{participation}/accept', [ChallengeParticipationController::class, 'acceptInvitation']); // Accepter une invitation
    Route::put('/participations/{participation}/reject', [ChallengeParticipationController::class, 'rejectInvitation']); // Refuser une invitation
    
    // Routes pour les filtres spécifiques
    Route::get('/user/participations/active', [ChallengeParticipationController::class, 'getActiveParticipations']); // Défis en cours
    Route::get('/user/participations/completed', [ChallengeParticipationController::class, 'getCompletedParticipations']); // Défis complétés
    Route::get('/user/invitations/pending', [ChallengeParticipationController::class, 'getPendingInvitations']); // Invitations en attente
    
    // Routes pour les statistiques des participations
    Route::get('/user/participations/stats', [ChallengeParticipationController::class, 'getParticipationStats']); // Statistiques de participation
    
    // Route pour les preuves de réalisation
    Route::post('/participations/{participation}/proof', [ChallengeParticipationController::class, 'addCompletionProof']); // Ajouter une preuve de réalisation
    
    // Catégories
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::get('/categories/{category}/challenges', [CategoryController::class, 'challenges']);

    // Amis
    Route::get('/friends', [FriendshipController::class, 'index']);
    Route::post('/friends/request/{user}', [FriendshipController::class, 'sendRequest']);
    Route::put('/friends/accept/{friendship}', [FriendshipController::class, 'acceptRequest']);
    Route::delete('/friends/reject/{friendship}', [FriendshipController::class, 'rejectRequest']);
    Route::delete('/friends/{friendship}', [FriendshipController::class, 'destroy']);
    Route::get('/friends/requests/pending', [FriendshipController::class, 'pendingRequests']);

    // Routes pour les abonnements
    Route::prefix('subscriptions')->group(function () {
        Route::get('/plans', [SubscriptionController::class, 'getPricingPlans']);
        Route::get('/status', [SubscriptionController::class, 'getSubscriptionStatus']);
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/resume', [SubscriptionController::class, 'resumeSubscription']);
    });
    
    // Routes pour les groupes de défis (accessibles à tous, avec limitations pour utilisateurs gratuits)
    Route::prefix('challenge-groups')->group(function () {
        // Routes de base (CRUD)
        Route::get('/', [ChallengeGroupController::class, 'index']);
        Route::post('/', [ChallengeGroupController::class, 'store']);
        Route::get('/{challengeGroup}', [ChallengeGroupController::class, 'show']);
        Route::put('/{challengeGroup}', [ChallengeGroupController::class, 'update']);
        Route::delete('/{challengeGroup}', [ChallengeGroupController::class, 'destroy']);
        
        // Routes pour les membres
        Route::post('/{challengeGroup}/members', [ChallengeGroupController::class, 'addMember']);
        Route::delete('/{challengeGroup}/members/{user}', [ChallengeGroupController::class, 'removeMember']);
        Route::put('/{challengeGroup}/members/{user}/role', [ChallengeGroupController::class, 'changeMemberRole']);
        Route::get('/{challengeGroup}/members', [ChallengeGroupController::class, 'members']);
        
        // Routes pour les défis
        Route::post('/{challengeGroup}/challenges', [ChallengeGroupController::class, 'addChallenge']);
        Route::delete('/{challengeGroup}/challenges/{challenge}', [ChallengeGroupController::class, 'removeChallenge']);
        Route::get('/{challengeGroup}/challenges', [ChallengeGroupController::class, 'challenges']);
    });
    
    // Routes exclusivement premium (protégées par le middleware premium)
    Route::middleware('premium')->group(function () {
        // Défis multi-étapes
        Route::post('/challenges/multi-stage', [ChallengeController::class, 'createMultiStage']);
        
        // Défis programmés
        Route::post('/challenges/scheduled', [ChallengeController::class, 'createScheduled']);
        
        // Compilations de médias
        Route::prefix('media')->group(function () {
            Route::post('/compilations', [MediaController::class, 'createCompilation']);
            Route::get('/compilations', [MediaController::class, 'getUserCompilations']);
            Route::get('/compilations/{media}', [MediaController::class, 'getCompilationDetails']);
            Route::delete('/compilations/{media}', [MediaController::class, 'deleteCompilation']);
        });
        
        // Statistiques avancées
        // Route::get('/user/stats/advanced', [StatsController::class, 'getAdvancedStats']);
    });
});

// Routes publiques pour explorer les défis
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/challenges/public', [ChallengeController::class, 'publicChallenges']);