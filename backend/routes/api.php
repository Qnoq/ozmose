<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\AdminCacheController;
use App\Http\Controllers\Api\FriendshipController;
use App\Http\Controllers\Api\ChallengeStageController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\ChallengeGroupController;
use App\Http\Controllers\Api\RateLimitStatsController;
use App\Http\Controllers\Api\ChallengeParticipationController;
use App\Http\Controllers\Api\ChallengeStageParticipationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques avec rate limiting pour les invités
Route::middleware(['rate.limit.requests:public'])->group(function () {
    // Authentification
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    
    // Contenus publics
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/challenges/public', [ChallengeController::class, 'publicChallenges'])->name('challenges.public');
});

// Routes protégées par Sanctum
Route::middleware(['auth:sanctum', 'cache.active.user', 'rate.limit.requests:default'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Routes pour la gestion du profil utilisateur
    |--------------------------------------------------------------------------
    */
    Route::prefix('user')->name('user.')->group(function () {
        // Profil de base
        Route::get('/', [UserController::class, 'show'])->name('show');
        Route::put('/', [UserController::class, 'update'])->name('update');
        
        // Défis et participations liés à l'utilisateur
        Route::get('/challenges/created', [UserController::class, 'createdChallenges'])->name('challenges.created');
        Route::get('/challenges/participating', [UserController::class, 'participatingChallenges'])->name('challenges.participating');
        
        // Gestion des amis
        Route::get('/friends', [UserController::class, 'friends'])->name('friends');
        Route::get('/friends/pending', [UserController::class, 'pendingFriendRequests'])->name('friends.pending');
        
        // Participations de l'utilisateur
        Route::prefix('participations')->name('participations.')->group(function () {
            Route::get('/active', [ChallengeParticipationController::class, 'getActiveParticipations'])->name('active');
            Route::get('/completed', [ChallengeParticipationController::class, 'getCompletedParticipations'])->name('completed');
            Route::get('/stats', [ChallengeParticipationController::class, 'getParticipationStats'])->name('stats');
        });
        
        // Invitations en attente
        Route::get('/invitations/pending', [ChallengeParticipationController::class, 'getPendingInvitations'])->name('invitations.pending');
    });
    
    // Déconnexion
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    
    /*
    |--------------------------------------------------------------------------
    | Routes pour les fonctionnalités sociales
    |--------------------------------------------------------------------------
    */
    Route::middleware(['rate.limit.requests:social'])->group(function () {
        // Recherche d'utilisateurs
        Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
        Route::get('/users/{user}', [UserController::class, 'showOtherUser'])->name('users.show');
        
        // Gestion des amitiés
        Route::prefix('friends')->name('friends.')->group(function () {
            Route::get('/', [FriendshipController::class, 'index'])->name('index');
            Route::post('/request/{user}', [FriendshipController::class, 'sendRequest'])->name('request');
            Route::put('/accept/{friendship}', [FriendshipController::class, 'acceptRequest'])->name('accept');
            Route::delete('/reject/{friendship}', [FriendshipController::class, 'rejectRequest'])->name('reject');
            Route::delete('/{friendship}', [FriendshipController::class, 'destroy'])->name('destroy');
            Route::get('/requests/pending', [FriendshipController::class, 'pendingRequests'])->name('requests.pending');
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Routes pour les notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
        Route::get('/count', [NotificationController::class, 'count'])->name('count');
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Routes pour la gestion des médias
    |--------------------------------------------------------------------------
    */
    Route::middleware(['rate.limit.requests:media'])->group(function () {
        // Médias standards
        Route::prefix('media')->name('media.')->group(function () {
            Route::get('/', [MediaController::class, 'index'])->name('index');
            Route::get('/{media}', [MediaController::class, 'show'])->name('show');
            Route::put('/{media}', [MediaController::class, 'update'])->name('update');
            Route::delete('/{media}', [MediaController::class, 'destroy'])->name('destroy');
        });
        
        // Preuves de réalisation
        Route::post('/participations/{participation}/proof', [ChallengeParticipationController::class, 'addCompletionProof'])
            ->name('participations.proof.add');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Routes pour la gestion des défis (standard)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['rate.limit.requests:api'])->group(function () {
        // Catégories
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
            Route::get('/{category}/challenges', [CategoryController::class, 'challenges'])->name('challenges');
        });
        
        // Défis - CRUD standard (avec des noms explicites pour les routes)
        Route::prefix('challenges')->name('challenges.')->group(function () {
            Route::get('/', [ChallengeController::class, 'index'])->name('index');
            Route::post('/', [ChallengeController::class, 'store'])->name('store');
            Route::get('/{challenge}', [ChallengeController::class, 'show'])->name('show');
            Route::put('/{challenge}', [ChallengeController::class, 'update'])->name('update');
            Route::delete('/{challenge}', [ChallengeController::class, 'destroy'])->name('destroy');
            Route::get('/{challenge}/participants', [ChallengeController::class, 'participants'])->name('participants');
            
            // Clonage de défis
            Route::post('/{challenge}/clone', [ChallengeController::class, 'clone'])->name('clone');
            Route::get('/{challenge}/clones', [ChallengeController::class, 'clones'])->name('clones');
            
            // Invitations
            Route::post('/{challenge}/invite', [ChallengeParticipationController::class, 'inviteFriends'])->name('invite');
            
            // Participation
            Route::post('/{challenge}/participate', [ChallengeParticipationController::class, 'store'])->name('participate');
        });
        
        // Infos sur les fonctionnalités
        Route::get('/features/drafts-info', [ChallengeController::class, 'getDraftsFeatureInfo'])->name('features.drafts-info');
        
        // Participations aux défis
        Route::prefix('participations')->name('participations.')->group(function () {
            Route::get('/', [ChallengeParticipationController::class, 'index'])->name('index');
            Route::get('/{participation}', [ChallengeParticipationController::class, 'show'])->name('show');
            Route::put('/{participation}', [ChallengeParticipationController::class, 'update'])->name('update');
            Route::delete('/{participation}', [ChallengeParticipationController::class, 'destroy'])->name('destroy');
            
            // Gestion des invitations
            Route::put('/{participation}/accept', [ChallengeParticipationController::class, 'acceptInvitation'])->name('accept');
            Route::put('/{participation}/reject', [ChallengeParticipationController::class, 'rejectInvitation'])->name('reject');
        });
        
        // Abonnements
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/plans', [SubscriptionController::class, 'getPricingPlans'])->name('plans');
            Route::get('/status', [SubscriptionController::class, 'getSubscriptionStatus'])->name('status');
            Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
            Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
            Route::post('/resume', [SubscriptionController::class, 'resumeSubscription'])->name('resume');
        });

        // Groupes de défis
        Route::prefix('challenge-groups')->name('challenge-groups.')->group(function () {
            // CRUD de base
            Route::get('/', [ChallengeGroupController::class, 'index'])->name('index');
            Route::post('/', [ChallengeGroupController::class, 'store'])->name('store');
            Route::get('/{challengeGroup}', [ChallengeGroupController::class, 'show'])->name('show');
            Route::put('/{challengeGroup}', [ChallengeGroupController::class, 'update'])->name('update');
            Route::delete('/{challengeGroup}', [ChallengeGroupController::class, 'destroy'])->name('destroy');
            
            // Gestion des membres
            Route::get('/{challengeGroup}/members', [ChallengeGroupController::class, 'members'])->name('members.index');
            Route::post('/{challengeGroup}/members', [ChallengeGroupController::class, 'addMember'])->name('members.add');
            Route::delete('/{challengeGroup}/members/{user}', [ChallengeGroupController::class, 'removeMember'])->name('members.remove');
            Route::put('/{challengeGroup}/members/{user}/role', [ChallengeGroupController::class, 'changeMemberRole'])->name('members.role');
            
            // Gestion des défis dans un groupe
            Route::get('/{challengeGroup}/challenges', [ChallengeGroupController::class, 'challenges'])->name('challenges.index');
            Route::post('/{challengeGroup}/challenges', [ChallengeGroupController::class, 'addChallenge'])->name('challenges.add');
            Route::delete('/{challengeGroup}/challenges/{challenge}', [ChallengeGroupController::class, 'removeChallenge'])->name('challenges.remove');
        });
        
        // Leaderboards
        Route::prefix('leaderboards')->name('leaderboards.')->group(function () {
            Route::get('/global', [LeaderboardController::class, 'global'])->name('global');
            Route::get('/weekly', [LeaderboardController::class, 'weekly'])->name('weekly');
            Route::get('/monthly', [LeaderboardController::class, 'monthly'])->name('monthly');
            Route::get('/category/{category}', [LeaderboardController::class, 'category'])->name('category');
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Routes premium (réservées aux abonnés)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['premium', 'rate.limit.requests:premium'])->group(function () {
        // Défis avancés
        Route::prefix('challenges')->name('challenges.')->group(function () {
            // Défis multi-étapes
            Route::post('/multi-stage', [ChallengeController::class, 'createMultiStage'])->name('multi-stage.create');
            Route::get('/multi-stage/stats', [ChallengeController::class, 'getMultiStageStats'])->name('multi-stage.stats');
            
            // Défis programmés
            Route::post('/scheduled', [ChallengeController::class, 'createScheduled'])->name('scheduled.create');
        });
        
        // Gestion des étapes (en utilisant la ressource imbriquée mais avec des noms explicites)
        Route::prefix('challenges/{challenge}/stages')->name('challenges.stages.')->group(function() {
            Route::get('/', [ChallengeStageController::class, 'index'])->name('index');
            Route::post('/', [ChallengeStageController::class, 'store'])->name('store');
            Route::get('/{stage}', [ChallengeStageController::class, 'show'])->name('show');
            Route::put('/{stage}', [ChallengeStageController::class, 'update'])->name('update');
            Route::delete('/{stage}', [ChallengeStageController::class, 'destroy'])->name('destroy');
        });
        
        // Médias premium
        Route::prefix('media/compilations')->name('media.compilations.')->group(function () {
            Route::post('/', [MediaController::class, 'createCompilation'])->name('create');
            Route::get('/', [MediaController::class, 'getUserCompilations'])->name('index');
            Route::get('/{media}', [MediaController::class, 'getCompilationDetails'])->name('show');
            Route::delete('/{media}', [MediaController::class, 'deleteCompilation'])->name('delete');
        });

        // Participation et progression des défis multi-étapes
        Route::prefix('participations')->name('participations.stages.')->group(function () {
            Route::get('/{participation}/stages', [ChallengeStageParticipationController::class, 'index'])->name('index');
            Route::post('/{participation}/stages/{stage}/complete', [ChallengeStageParticipationController::class, 'complete'])->name('complete');
            Route::put('/{participation}/stages/{stage}/unlock', [ChallengeStageParticipationController::class, 'unlock'])->name('unlock');
        });
        
        // Brouillons de défis
        Route::prefix('drafts/challenges')->name('drafts.challenges.')->group(function () {
            Route::post('/', [ChallengeController::class, 'saveDraft'])->name('save');
            Route::get('/', [ChallengeController::class, 'getDraft'])->name('get');
            Route::delete('/', [ChallengeController::class, 'deleteDraft'])->name('delete');
            Route::post('/create', [ChallengeController::class, 'createFromDraft'])->name('create');
        });
        
        // Leaderboards premium
        Route::get('/leaderboards/premium', [LeaderboardController::class, 'premium'])->name('leaderboards.premium');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Routes d'administration
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'admin'])->group(function () {
        // Statistiques de rate limiting
        Route::get('/rate-limit-stats', [RateLimitStatsController::class, 'getStats'])->name('rate-limit.stats');
        
        // Gestion du cache
        Route::prefix('cache')->name('cache.')->group(function () {
            Route::get('/status', [AdminCacheController::class, 'status'])->name('status');
            Route::get('/test', [AdminCacheController::class, 'test'])->name('test');
            Route::get('/keys', [AdminCacheController::class, 'keys'])->name('keys');
            Route::post('/clear', [AdminCacheController::class, 'clear'])->name('clear');
        });
    });
});