<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Challenge;
use Illuminate\Http\Request;
use App\Services\CacheService;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use App\Models\ChallengeParticipation;
use Illuminate\Support\Facades\Storage;
use App\Models\ChallengeStageParticipation;
use App\Http\Requests\StoreChallengeParticipation;
use App\Http\Requests\UpdateChallengeParticipation;
use App\Http\Resources\ChallengeParticipationResource;
use App\Notifications\ChallengeInvitationNotification;

class ChallengeParticipationController extends Controller
{
    protected $mediaService;
    protected $cacheService;
    protected $leaderboardService;
    
    public function __construct(MediaService $mediaService, CacheService $cacheService, LeaderboardService $leaderboardService)
    {
        $this->mediaService = $mediaService;
        $this->cacheService = $cacheService;
        $this->leaderboardService = $leaderboardService;
    }
    
    /**
     * Affiche la liste des participations de l'utilisateur
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        try {
            $participations = ChallengeParticipation::where('user_id', $request->user()->id)
                ->with(['challenge', 'challenge.category'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return ChallengeParticipationResource::collection($participations);
        } catch (\Exception $e) {
            info('Erreur lors de la récupération des participations', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des participations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche une participation spécifique
     *
     * @param  ChallengeParticipation  $participation
     * @return ChallengeParticipationResource
     */
    public function show(ChallengeParticipation $participation)
    {
        try {
            // Vérifier que l'utilisateur est autorisé à voir cette participation
            if ($participation->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas autorisé à consulter cette participation'
                ], 403);
            }
            
            return new ChallengeParticipationResource(
                $participation->load(['challenge', 'challenge.category', 'challenge.participants'])
            );
        } catch (\Exception $e) {
            info('Erreur lors de la récupération de la participation', [
                'message' => $e->getMessage(),
                'participation_id' => $participation->id
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération de la participation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refuser une invitation à un défi
     *
     * @param  ChallengeParticipation  $participation
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectInvitation(ChallengeParticipation $participation)
    {
        try {
            // Vérifier que l'utilisateur est bien le destinataire de l'invitation
            if ($participation->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas autorisé à refuser cette invitation'
                ], 403);
            }
            
            // Vérifier que le statut est bien "invited"
            if ($participation->status !== 'invited') {
                return response()->json([
                    'message' => 'Cette invitation a déjà été traitée'
                ], 422);
            }
            
            $challengeId = $participation->challenge_id;
            $participation->delete();
            
            info('Invitation à un défi refusée', [
                'user_id' => auth()->id(),
                'challenge_id' => $challengeId
            ]);
            
            return response()->json([
                'message' => 'Invitation refusée avec succès'
            ]);
        } catch (\Exception $e) {
            info('Erreur lors du refus de l\'invitation', [
                'message' => $e->getMessage(),
                'participation_id' => $participation->id
            ]);
            
            return response()->json([
                'message' => 'Erreur lors du refus de l\'invitation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les participations actives de l'utilisateur
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getActiveParticipations(Request $request)
    {
        try {
            $participations = ChallengeParticipation::where('user_id', $request->user()->id)
                ->where('status', 'accepted')
                ->whereNull('completed_at')
                ->whereNull('abandoned_at')
                ->with(['challenge', 'challenge.category'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
            
            return ChallengeParticipationResource::collection($participations);
        } catch (\Exception $e) {
            info('Erreur lors de la récupération des participations actives', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des participations actives',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les participations complétées de l'utilisateur
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getCompletedParticipations(Request $request)
    {
        try {
            $participations = ChallengeParticipation::where('user_id', $request->user()->id)
                ->where('status', 'completed')
                ->with(['challenge', 'challenge.category'])
                ->orderBy('completed_at', 'desc')
                ->paginate(15);
            
            return ChallengeParticipationResource::collection($participations);
        } catch (\Exception $e) {
            info('Erreur lors de la récupération des participations complétées', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des participations complétées',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les statistiques de participation de l'utilisateur
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getParticipationStats(Request $request)
    {
        try {
            $userId = $request->user()->id;
            
            // Utiliser directement les méthodes du service de cache
            $stats = $this->cacheService->getUserStats($userId);
            $categoriesStats = $this->cacheService->getUserCategoryStats($userId);
            
            // Combiner les résultats
            return response()->json(array_merge(
                $stats,
                ['categories_stats' => $categoriesStats]
            ));
        } catch (\Exception $e) {
            info('Erreur lors de la récupération des statistiques', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajoute une preuve de réalisation à une participation
     *
     * @param  Request  $request
     * @param  ChallengeParticipation  $participation
     * @return \Illuminate\Http\JsonResponse
     */
    public function addCompletionProof(Request $request, ChallengeParticipation $participation)
    {
        try {
            $request->validate([
                'media' => 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov|max:20480', // 20MB max
                'caption' => 'nullable|string|max:255',
            ]);

            // Vérifier que l'utilisateur est le propriétaire de la participation
            if ($participation->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas autorisé à ajouter une preuve à cette participation'
                ], 403);
            }
            
            // Utiliser la méthode spécialisée du service
            $media = $this->mediaService->storeCompletionProof(
                $request->file('media'), 
                $participation, 
                [
                    'caption' => $request->caption,
                    'is_public' => $request->is_public ?? false
                ]
            );
            
            info('Preuve de réalisation ajoutée', [
                'participation_id' => $participation->id,
                'media_id' => $media->id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'message' => 'Preuve de réalisation ajoutée avec succès',
                'media' => $media,
                'participation' => new ChallengeParticipationResource($participation->fresh())
            ]);
        } catch (\Exception $e) {
            info('Erreur lors de l\'ajout de la preuve', [
                'message' => $e->getMessage(),
                'participation_id' => $participation->id
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de l\'ajout de la preuve',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreChallengeParticipation $request, Challenge $challenge)
    {
        try {
            // Vérifier si l'utilisateur participe déjà à ce défi
            $existingParticipation = ChallengeParticipation::where('user_id', $request->user()->id)
            ->where('challenge_id', $challenge->id)
            ->first();
            
            if ($existingParticipation) {
                return response()->json([
                    'message' => 'Vous participez déjà à ce défi',
                    'participation' => new ChallengeParticipationResource($existingParticipation)
                ], 422);
            }

            $participation = ChallengeParticipation::create([
                'user_id' => $request->user()->id,
                'challenge_id' => $challenge->id,
                'status' => 'accepted', // Statut initial : en cours
                'started_at' => now(),
                'notes' => $request->notes, // Optionnel
            ]);
            
            // Si c'est un défi multi-étapes, initialiser les participations aux étapes
            if ($challenge->multi_stage) {
                $this->initializeStageParticipations($participation);
            }

            info('Nouvelle participation créée', [
                'user_id' => $request->user()->id,
                'challenge_id' => $challenge->id
            ]);

            return new ChallengeParticipationResource($participation->load(['user', 'challenge']));
        } catch (\Exception $e) {
            info('Erreur lors de la création de la participation', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'challenge_id' => $challenge->id
            ]);

            return response()->json([
                'message' => 'Erreur lors de la création de la participation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour une participation (généralement pour la compléter)
     *
     * @param  UpdateChallengeParticipation  $request
     * @param  ChallengeParticipation  $participation
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateChallengeParticipation $request, ChallengeParticipation $participation)
    {
        try {
            $data = $request->validated();
            
            // Si le statut passe à "completed", gérer les preuves et déclencher les événements associés
            if (isset($data['status']) && $data['status'] === 'completed') {
                // La date de complétion est déjà gérée dans prepareForValidation() du FormRequest
                
                // Gestion des médias de preuve si nécessaire
                if ($request->hasFile('proof_media')) {
                    // Utiliser le service média pour traiter et stocker le fichier
                    $media = $this->mediaService->store($request->file('proof_media'), [
                        'challenge_id' => $participation->challenge_id,
                        'participation_id' => $participation->id,
                        'user_id' => $request->user()->id,
                        'caption' => $request->proof_caption ?? 'Preuve de réalisation',
                        'is_public' => $request->has('is_public') ? $request->is_public : false
                    ]);
                    
                    // Associer l'ID du média à la participation
                    $data['proof_media_id'] = $media->id;
                }
                
                // Si un feedback ou une note est fourni sans date de feedback, ajouter la date
                if ((isset($data['feedback']) || isset($data['rating'])) && !isset($data['feedback_at'])) {
                    $data['feedback_at'] = now();
                }
                
                // Déclencher les actions liées à la complétion d'un défi
                // event(new ChallengeCompleted($participation));
                
                // Attribuer des badges ou points si nécessaire
                $this->awardCompletionAchievements($participation);

                // Ajouter les points au leaderboard
                $pointsAwarded = $this->leaderboardService->addPointsForChallenge($participation);
                
                // Ajouter l'information dans la réponse
                $pointsInfo = [
                    'points_awarded' => $pointsAwarded,
                    'message' => "Bravo ! Vous avez gagné {$pointsAwarded} points !"
                ];
            }
            
            // Si le statut passe à "abandoned", enregistrer la date d'abandon
            if (isset($data['status']) && $data['status'] === 'abandoned' && !isset($data['abandoned_at'])) {
                $data['abandoned_at'] = now();
            }
            
            // Mettre à jour la participation avec les données validées
            $participation->update($data);

            // Une fois la mise à jour effectuée, invalider les caches concernés
            // Utiliser les méthodes du service de cache
            $this->cacheService->clearUserCache($participation->user_id);
            $this->cacheService->clearChallengeCache($participation->challenge_id);
            
            // Si la participation concerne un défi public, invalider les caches publics aussi
            if ($participation->challenge->is_public) {
                // Vider le cache des "challenges populaires" car les stats peuvent changer
                \Illuminate\Support\Facades\Cache::forget('ozmose:challenges:popular:5');
            }

            // Charger les relations utiles pour la réponse
            $participation->load(['user', 'challenge', 'challenge.category']);
            
            // Si le statut est "completed", vérifier si tous les amis ont complété le défi
            if ($participation->status === 'completed') {
                $this->checkGroupCompletion($participation->challenge_id);
            }

            // Si des points ont été attribués, inclure l'info dans la réponse
            $response = new ChallengeParticipationResource($participation);
            
            if (isset($pointsInfo)) {
                return $response->additional(['points_info' => $pointsInfo]);
            }
            
            return $response;
        } catch (\Exception $e) {
            info('Erreur lors de la mise à jour de la participation', [
                'message' => $e->getMessage(),
                'participation_id' => $participation->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la participation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Attribue des badges ou récompenses lors de la complétion d'un défi
     * 
     * @param ChallengeParticipation $participation
     * @return void
     */
    private function awardCompletionAchievements($participation)
    {
        $user = $participation->user;
        $challenge = $participation->challenge;
        
        // Compter le nombre de défis complétés par l'utilisateur
        $completedCount = ChallengeParticipation::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count();
        
        // Attribuer des badges selon le nombre de défis complétés
        // Cette logique sera développée davantage dans une phase ultérieure
        
        // Vous pourriez également attribuer des points selon la difficulté du défi
        if ($challenge->difficulty === 'easy') {
            // $user->addPoints(10);
        } elseif ($challenge->difficulty === 'medium') {
            // $user->addPoints(25);
        } elseif ($challenge->difficulty === 'hard') {
            // $user->addPoints(50);
        }
    }

    /**
     * Vérifie si tous les participants ont complété le défi
     * pour déclencher une notification de groupe
     * 
     * @param int $challengeId
     * @return void
     */
    private function checkGroupCompletion($challengeId)
    {
        $participations = ChallengeParticipation::where('challenge_id', $challengeId)
            ->where('status', 'accepted')
            ->orWhere('status', 'invited')
            ->count();
        
        if ($participations === 0) {
            $completedParticipations = ChallengeParticipation::where('challenge_id', $challengeId)
                ->where('status', 'completed')
                ->with('user')
                ->get();
            
            if ($completedParticipations->count() > 1) {
                // Tous les participants ont complété le défi, envoi d'une notification de groupe
                // foreach ($completedParticipations as $participation) {
                //     Notification::send($participation->user, new GroupChallengeCompletedNotification($challengeId));
                // }
            }
        }
    }

    /**
     * Supprime une participation à un défi
     *
     * @param  ChallengeParticipation  $participation
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ChallengeParticipation $participation)
    {
        try {
            // Vérification que l'utilisateur est bien le propriétaire de cette participation
            if ($participation->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas autorisé à supprimer cette participation'
                ], 403);
            }
            
            $challengeId = $participation->challenge_id;
            $userId = $participation->user_id;
            
            $participation->delete();

            info('Participation supprimée', [
                'user_id' => $userId,
                'challenge_id' => $challengeId
            ]);

            return response()->json([
                'message' => 'Participation au défi supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            info('Erreur lors de la suppression de la participation', [
                'message' => $e->getMessage(),
                'participation_id' => $participation->id
            ]);

            return response()->json([
                'message' => 'Erreur lors de la suppression de la participation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Invite des amis à participer à un défi
     *
     * @param  Request  $request
     * @param  Challenge  $challenge
     * @return \Illuminate\Http\JsonResponse
     */
    public function inviteFriends(Request $request, Challenge $challenge)
    {
        $request->validate([
            'friend_ids' => 'required|array',
            'friend_ids.*' => 'exists:users,id',
            'message' => 'nullable|string|max:255'
        ]);
        
        $invitedCount = 0;
        
        foreach ($request->friend_ids as $friendId) {
            // Vérifier si l'ami est déjà invité/participant
            $existingParticipation = ChallengeParticipation::where('user_id', $friendId)
                ->where('challenge_id', $challenge->id)
                ->first();
                
            if (!$existingParticipation) {
                $participation = ChallengeParticipation::create([
                    'user_id' => $friendId,
                    'challenge_id' => $challenge->id,
                    'status' => 'invited',
                    'invited_by' => $request->user()->id,
                    'invitation_message' => $request->message
                ]);
                
                // Envoyer une notification
                $user = User::find($friendId);
                $user->notify(new ChallengeInvitationNotification(
                    $request->user(),
                    $challenge,
                    $participation,
                    $request->message
                ));
                
                $invitedCount++;
            }
        }
        
        return response()->json([
            'message' => "{$invitedCount} ami(s) invité(s) à participer au défi",
            'invited_count' => $invitedCount
        ]);
    }

    /**
     * Accepter une invitation à un défi
     *
     * @param  ChallengeParticipation  $participation
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptInvitation(ChallengeParticipation $participation)
    {
        // Vérifier que l'utilisateur est bien le destinataire de l'invitation
        if ($participation->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à accepter cette invitation'
            ], 403);
        }
        
        // Vérifier que le statut est bien "invited"
        if ($participation->status !== 'invited') {
            return response()->json([
                'message' => 'Cette invitation a déjà été traitée'
            ], 422);
        }
        
        $participation->update([
            'status' => 'accepted',
            'started_at' => now()
        ]);
        
        return new ChallengeParticipationResource($participation->fresh(['user', 'challenge']));
    }

    /**
     * Récupère toutes les participations de l'utilisateur connecté
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getUserParticipations(Request $request)
    {
        $participations = ChallengeParticipation::where('user_id', $request->user()->id)
            ->with(['challenge', 'challenge.category'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return ChallengeParticipationResource::collection($participations);
    }

    /**
     * Récupère les invitations en attente de l'utilisateur connecté
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getPendingInvitations(Request $request)
    {
        $invitations = ChallengeParticipation::where('user_id', $request->user()->id)
            ->where('status', 'invited')
            ->with(['challenge', 'challenge.category', 'inviter'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return ChallengeParticipationResource::collection($invitations);
    }
    
    /**
     * Après la création d'une participation à un défi multi-étapes,
     * initialiser les participations aux étapes.
     *
     * @param ChallengeParticipation $participation
     * @return void
     */
    private function initializeStageParticipations(ChallengeParticipation $participation)
    {
        // Vérifier si c'est un défi multi-étapes
        if (!$participation->challenge->multi_stage) {
            return;
        }
        
        // Récupérer toutes les étapes du défi
        $stages = $participation->challenge->stages()->orderBy('order')->get();
        
        if ($stages->isEmpty()) {
            return;
        }
        
        // Créer les participations aux étapes
        foreach ($stages as $index => $stage) {
            // La première étape est active, les autres sont verrouillées
            $status = ($index === 0) ? 'active' : 'locked';
            
            ChallengeStageParticipation::create([
                'participation_id' => $participation->id,
                'stage_id' => $stage->id,
                'status' => $status
            ]);
        }
    }
}
