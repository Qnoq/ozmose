<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\ChallengeGroupController;
use App\Http\Controllers\Api\ChallengeParticipationController;
use App\Http\Controllers\Api\AdminCacheController;

// Routes publiques avec rate limiting pour les invités
Route::middleware(['rate.limit.requests:public'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/challenges/public', [ChallengeController::class, 'publicChallenges']);
});

// Routes protégées par Sanctum avec rate limiting par défaut
Route::middleware(['auth:sanctum', 'cache.active.user', 'rate.limit.requests:default'])->group(function () {
    
    // Routes d'administration (généralement à accès restreint)
    Route::prefix('cache')->middleware(['rate.limit.requests:admin'])->group(function () {
        Route::get('/status', [AdminCacheController::class, 'status']);
        Route::get('/test', [AdminCacheController::class, 'test']);
        Route::get('/keys', [AdminCacheController::class, 'keys']);
        Route::post('/clear', [AdminCacheController::class, 'clear']);
    });

    // Profil utilisateur (rate limiting standard)
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);
    Route::get('/user/challenges/created', [UserController::class, 'createdChallenges']);
    Route::get('/user/challenges/participating', [UserController::class, 'participatingChallenges']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Routes liées aux fonctionnalités sociales (rate limiting plus strict)
    Route::middleware(['rate.limit.requests:social'])->group(function () {
        // Amis et recherche d'utilisateurs
        Route::get('/user/friends', [UserController::class, 'friends']);
        Route::get('/user/friends/pending', [UserController::class, 'pendingFriendRequests']);
        Route::get('/users/search', [UserController::class, 'search']);
        Route::get('/users/{user}', [UserController::class, 'showOtherUser']);
        
        // Gestion des amitiés
        Route::get('/friends', [FriendshipController::class, 'index']);
        Route::post('/friends/request/{user}', [FriendshipController::class, 'sendRequest']);
        Route::put('/friends/accept/{friendship}', [FriendshipController::class, 'acceptRequest']);
        Route::delete('/friends/reject/{friendship}', [FriendshipController::class, 'rejectRequest']);
        Route::delete('/friends/{friendship}', [FriendshipController::class, 'destroy']);
        Route::get('/friends/requests/pending', [FriendshipController::class, 'pendingRequests']);
        
        // Invitations aux défis (aspect social)
        Route::post('/challenges/{challenge}/invite', [ChallengeParticipationController::class, 'inviteFriends']);
    });
    
    // Routes liées au téléchargement des médias (rate limiting strict)
    Route::middleware(['rate.limit.requests:media'])->group(function () {
        // Preuves de réalisation
        Route::post('/participations/{participation}/proof', [ChallengeParticipationController::class, 'addCompletionProof']);
        
        // Gestion des médias (routes non premium)
        Route::get('/media', [MediaController::class, 'index']);
        Route::get('/media/{media}', [MediaController::class, 'show']);
        Route::put('/media/{media}', [MediaController::class, 'update']);
        Route::delete('/media/{media}', [MediaController::class, 'destroy']);
    });
    
    // Gestion des défis (rate limiting API standard)
    Route::middleware(['rate.limit.requests:api'])->group(function () {
        // Défis - CRUD
        Route::apiResource('challenges', ChallengeController::class);
        Route::get('/challenges/{challenge}/participants', [ChallengeController::class, 'participants']);
        
        // Participations aux défis
        Route::post('/challenges/{challenge}/participate', [ChallengeParticipationController::class, 'store']);
        Route::put('/participations/{participation}', [ChallengeParticipationController::class, 'update']);
        Route::delete('/participations/{participation}', [ChallengeParticipationController::class, 'destroy']);
        Route::get('/participations', [ChallengeParticipationController::class, 'index']);
        Route::get('/participations/{participation}', [ChallengeParticipationController::class, 'show']);
        Route::put('/participations/{participation}/accept', [ChallengeParticipationController::class, 'acceptInvitation']);
        Route::put('/participations/{participation}/reject', [ChallengeParticipationController::class, 'rejectInvitation']);
        
        // Filtres et statistiques de participation
        Route::get('/user/participations/active', [ChallengeParticipationController::class, 'getActiveParticipations']);
        Route::get('/user/participations/completed', [ChallengeParticipationController::class, 'getCompletedParticipations']);
        Route::get('/user/invitations/pending', [ChallengeParticipationController::class, 'getPendingInvitations']);
        Route::get('/user/participations/stats', [ChallengeParticipationController::class, 'getParticipationStats']);
        
        // Catégories
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::get('/categories/{category}/challenges', [CategoryController::class, 'challenges']);

        // Abonnements
        Route::prefix('subscriptions')->group(function () {
            Route::get('/plans', [SubscriptionController::class, 'getPricingPlans']);
            Route::get('/status', [SubscriptionController::class, 'getSubscriptionStatus']);
            Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
            Route::post('/cancel', [SubscriptionController::class, 'cancel']);
            Route::post('/resume', [SubscriptionController::class, 'resumeSubscription']);
        });
        
        // Groupes de défis
        Route::prefix('challenge-groups')->group(function () {
            // CRUD de base
            Route::get('/', [ChallengeGroupController::class, 'index']);
            Route::post('/', [ChallengeGroupController::class, 'store']);
            Route::get('/{challengeGroup}', [ChallengeGroupController::class, 'show']);
            Route::put('/{challengeGroup}', [ChallengeGroupController::class, 'update']);
            Route::delete('/{challengeGroup}', [ChallengeGroupController::class, 'destroy']);
            
            // Gestion des membres
            Route::post('/{challengeGroup}/members', [ChallengeGroupController::class, 'addMember']);
            Route::delete('/{challengeGroup}/members/{user}', [ChallengeGroupController::class, 'removeMember']);
            Route::put('/{challengeGroup}/members/{user}/role', [ChallengeGroupController::class, 'changeMemberRole']);
            Route::get('/{challengeGroup}/members', [ChallengeGroupController::class, 'members']);
            
            // Gestion des défis dans un groupe
            Route::post('/{challengeGroup}/challenges', [ChallengeGroupController::class, 'addChallenge']);
            Route::delete('/{challengeGroup}/challenges/{challenge}', [ChallengeGroupController::class, 'removeChallenge']);
            Route::get('/{challengeGroup}/challenges', [ChallengeGroupController::class, 'challenges']);
        });

        // Leaderboards (classements)
        Route::prefix('leaderboards')->group(function () {
            Route::get('/global', [LeaderboardController::class, 'global']);
            Route::get('/weekly', [LeaderboardController::class, 'weekly']);
            Route::get('/monthly', [LeaderboardController::class, 'monthly']);
            Route::get('/category/{category}', [LeaderboardController::class, 'category']);
        });

        // Informations sur les fonctionnalités
        Route::get('/features/drafts-info', [ChallengeController::class, 'getDraftsFeatureInfo']);
    });
    
    // Routes exclusivement premium (avec rate limiting premium)
    Route::middleware(['premium', 'rate.limit.requests:premium'])->group(function () {
        // Défis avancés
        Route::post('/challenges/multi-stage', [ChallengeController::class, 'createMultiStage']);
        Route::post('/challenges/scheduled', [ChallengeController::class, 'createScheduled']);
        
        // Compilations de médias
        Route::prefix('media')->group(function () {
            Route::post('/compilations', [MediaController::class, 'createCompilation']);
            Route::get('/compilations', [MediaController::class, 'getUserCompilations']);
            Route::get('/compilations/{media}', [MediaController::class, 'getCompilationDetails']);
            Route::delete('/compilations/{media}', [MediaController::class, 'deleteCompilation']);
        });

        // Leaderboards premium
        Route::get('/leaderboards/premium', [LeaderboardController::class, 'premium']);

        // Brouillons de défis
        Route::prefix('drafts/challenges')->group(function () {
            Route::post('/', [ChallengeController::class, 'saveDraft']);
            Route::get('/', [ChallengeController::class, 'getDraft']);
            Route::delete('/', [ChallengeController::class, 'deleteDraft']);
            Route::post('/create', [ChallengeController::class, 'createFromDraft']);
        });
    });
});