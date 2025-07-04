<?php

namespace App\Http\Controllers\Api;

use App\Models\Challenge;
use Illuminate\Http\Request;
use App\Models\ChallengeGroup;
use App\Models\ChallengeStage;
use App\Services\CacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\StoreChallenge;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\UpdateChallenge;
use App\Models\ChallengeParticipation;
use App\Http\Resources\ChallengeResource;
use Illuminate\Support\Facades\Validator;
use App\Models\ChallengeStageParticipation;
use App\Http\Requests\StoreMultiStageChallenge;

class ChallengeController extends Controller
{
    protected $cacheService;
    
    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Liste des défis de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $challenges = Challenge::where('creator_id', $user->id)
            ->orWhereHas('participations', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['creator', 'category'])
            ->withCount('participations')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return ChallengeResource::collection($challenges);
    }

    /**
     * Sauvegarde automatique d'un brouillon de défi
     */
    public function saveDraft(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Vérification supplémentaire du statut premium
            if (!$user->isPremium()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette fonctionnalité est réservée aux utilisateurs premium',
                    'premium_info' => [
                        'can_upgrade' => true,
                        'features' => ['Sauvegarde automatique de vos brouillons', 'Et bien plus encore...']
                    ]
                ], 403);
            }

            $userId = auth()->id();
            $key = "ozmose:drafts:challenge:{$userId}";
            
            // Valider minimalement les données
            $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'difficulty' => 'nullable|string|in:facile,moyen,difficile',
                'category_id' => 'nullable|exists:categories,id',
                'is_public' => 'nullable|boolean',
            ]);
            
            // Extraire les données pertinentes du formulaire
            $draftData = $request->except(['_token', '_method']);
            
            // Ajouter un timestamp
            $draftData['last_saved'] = now()->toIso8601String();
            
            // Sauvegarder dans Redis avec une durée de vie de 24h
            Redis::setex($key, 86400, json_encode($draftData));
            
            return response()->json([
                'success' => true,
                'message' => 'Brouillon sauvegardé automatiquement',
                'timestamp' => now()->toIso8601String()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la sauvegarde du brouillon', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde du brouillon',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupération d'un brouillon de défi
     */
    public function getDraft()
    {
        try {
            $userId = auth()->id();
            $key = "ozmose:drafts:challenge:{$userId}";
            
            // Récupérer le brouillon depuis Redis
            $draft = Redis::get($key);
            
            if (!$draft) {
                return response()->json([
                    'success' => true,
                    'has_draft' => false,
                    'message' => 'Aucun brouillon trouvé'
                ]);
            }
            
            // Décoder le brouillon
            $draftData = json_decode($draft, true);
            
            return response()->json([
                'success' => true,
                'has_draft' => true,
                'draft' => $draftData,
                'last_saved' => $draftData['last_saved'] ?? now()->toIso8601String()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du brouillon', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du brouillon',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Supprimer un brouillon de défi
     */
    public function deleteDraft()
    {
        try {
            $userId = auth()->id();
            $key = "ozmose:drafts:challenge:{$userId}";
            
            // Supprimer le brouillon
            Redis::del($key);
            
            return response()->json([
                'success' => true,
                'message' => 'Brouillon supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du brouillon', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du brouillon',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Créer un défi à partir d'un brouillon
     */
    public function createFromDraft(Request $request)
    {
        try {
            // Récupérer d'abord le brouillon
            $draftResponse = $this->getDraft()->getData();
            
            if (!$draftResponse->success || !$draftResponse->has_draft) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun brouillon disponible'
                ], 404);
            }
            
            // Fusionner le brouillon avec les données de la requête
            $mergedData = array_merge(
                (array)$draftResponse->draft,
                $request->except(['_token', '_method'])
            );
            
            // Valider les données fusionnées
            $validator = Validator::make($mergedData, [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'instructions' => 'required|string',
                'difficulty' => 'required|in:facile,moyen,difficile',
                'category_id' => 'required|exists:categories,id',
                'is_public' => 'boolean',
                'duration' => 'nullable|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Créer le challenge
            $challenge = Challenge::create([
                'title' => $mergedData['title'],
                'description' => $mergedData['description'],
                'instructions' => $mergedData['instructions'],
                'difficulty' => $mergedData['difficulty'],
                'category_id' => $mergedData['category_id'],
                'is_public' => $mergedData['is_public'] ?? false,
                'duration' => $mergedData['duration'] ?? null,
                'creator_id' => auth()->id(),
            ]);
            
            // Invalider les caches concernés
            $this->cacheService->clearCategoriesCache();
            $this->cacheService->clearUserCache(auth()->id());
            
            // Supprimer le brouillon après création réussie
            $this->deleteDraft();
            
            return new ChallengeResource($challenge);
            
        } catch (\Exception $e) {
            // Log de l'erreur
            info('Erreur lors de la création du défi depuis le brouillon :', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la création du défi depuis le brouillon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Informations sur la fonctionnalité de brouillons premium
     */
    public function getDraftsFeatureInfo()
    {
        $user = auth()->user();
        $isPremium = $user->isPremium();
        
        return response()->json([
            'feature_name' => 'Brouillons automatiques de défis',
            'description' => 'Sauvegardez automatiquement votre travail lors de la création de défis et reprenez à tout moment.',
            'benefits' => [
                'Évitez de perdre votre travail en cas de fermeture accidentelle',
                'Prenez le temps de peaufiner vos défis sur plusieurs sessions',
                'Reprenez facilement là où vous vous êtes arrêté'
            ],
            'is_premium' => true,
            'user_has_access' => $isPremium,
            'premium_info' => !$isPremium ? [
                'message' => 'Passez premium pour accéder à cette fonctionnalité !',
                'price' => '4.99€/mois',
                'upgrade_url' => '/subscriptions/plans'
            ] : null
        ]);
    }

    /**
     * Créer un nouveau défi
     */
    public function store(StoreChallenge $request)
    {
        try {
            $challenge = Challenge::create([
                'title' => $request->title,
                'description' => $request->description,
                'instructions' => $request->instructions,
                'difficulty' => $request->difficulty,
                'category_id' => $request->category_id,
                'is_public' => $request->is_public ?? false,
                'duration' => $request->duration,
                'creator_id' => auth()->id(), // L'ID de l'utilisateur connecté
            ]);
            
            // Invalider les caches concernés
            $this->cacheService->clearCategoriesCache();
            $this->cacheService->clearUserCache(auth()->id());
            
            // Suppression du brouillon seulement si l'utilisateur est premium
            if (auth()->user()->isPremium()) {
                $userId = auth()->id();
                $key = "ozmose:drafts:challenge:{$userId}";
                Redis::del($key);
            }
            
            return new ChallengeResource($challenge);
        } catch (\Exception $e) {
            // Log de l'erreur
            info('Erreur lors de la création du défi :', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la création du défi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un défi spécifique
     */
    public function show(Challenge $challenge, Request $request)
    {
        // Vérifier si l'utilisateur a accès au défi
        $user = $request->user();
        
        if (!$challenge->is_public && 
            $challenge->creator_id !== $user->id && 
            !$challenge->participations()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        
        $challenge->load(['creator', 'category', 'media']);
        
        if ($request->has('with_participants')) {
            $challenge->load('participants');
        }
        
        return new ChallengeResource($challenge);
    }

    /**
     * Récupérer les clones d'un défi
     * 
     * @param Challenge $challenge
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function clones(Challenge $challenge)
    {
        // Vérifier si l'utilisateur a accès au défi
        if (!$challenge->is_public && 
            $challenge->creator_id !== auth()->id() && 
            !$challenge->participations()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        
        $clones = $challenge->clones()
            ->with(['creator', 'category'])
            ->withCount('participations')
            ->paginate(10);
        
        return ChallengeResource::collection($clones);
    }

    /**
     * Mettre à jour un défi
     */
    public function update(UpdateChallenge $request, Challenge $challenge)
    {
        try {
            $oldCategoryId = $challenge->category_id;
            
            $challenge->update($request->validated());
            
            // Invalider les caches concernés
            $this->cacheService->clearChallengeCache($challenge->id);
            
            // Si la catégorie a changé, invalider aussi le cache des catégories
            if ($oldCategoryId != $challenge->category_id) {
                $this->cacheService->clearCategoriesCache();
            }
            
            return new ChallengeResource($challenge->fresh(['creator', 'category']));
        } catch (\Exception $e) {
            info('Erreur lors de la mise à jour du défi', [
                'message' => $e->getMessage(),
                'challenge_id' => $challenge->id
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du défi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un défi
     */
    public function destroy(Challenge $challenge, Request $request)
    {
        // Vérifier si l'utilisateur est le créateur du défi
        if ($challenge->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Garder une référence à la catégorie avant suppression
        $categoryId = $challenge->category_id;
        
        $challenge->delete();
        
        // Invalider les caches concernés
        $this->cacheService->clearChallengeCache($challenge->id);
        $this->cacheService->clearCategoriesCache();
        $this->cacheService->clearUserCache($request->user()->id);

        return response()->json(['message' => 'Défi supprimé avec succès']);
    }

    /**
     * Liste des participants d'un défi
     */
    public function participants(Challenge $challenge, Request $request)
    {
        // Vérifier l'accès
        $user = $request->user();
        if (!$challenge->is_public && 
            $challenge->creator_id !== $user->id && 
            !$challenge->participations()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $participants = $challenge->participants()
            ->withPivot('status', 'completed_at')
            ->paginate(20);

        return UserResource::collection($participants);
    }

    /**
     * Liste des défis publics
     */
    public function publicChallenges(Request $request)
    {
        $sort = $request->get('sort', 'newest');
        $page = $request->get('page', 1);
        $categoryId = $request->get('category_id');
        
        // Construire une clé de cache unique
        $cacheKey = "ozmose:challenges:public:{$sort}";
        if ($categoryId) {
            $cacheKey .= ":category_{$categoryId}";
        }
        $cacheKey .= ":page_{$page}";
        
        // Utiliser directement le Cache facade pour ce cas spécifique
        $challenges = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($request, $sort, $categoryId) {
            $query = Challenge::where('is_public', true)
                ->with(['creator', 'category'])
                ->withCount('participations');
                
            // Filtrer par catégorie si spécifié
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }
            
            // Trier selon différents critères
            switch ($sort) {
                case 'popular':
                    $query->orderByDesc('participations_count');
                    break;
                case 'newest':
                    $query->orderByDesc('created_at');
                    break;
                case 'oldest':
                    $query->orderBy('created_at');
                    break;
                default:
                    $query->orderByDesc('created_at');
            }
            
            return $query->paginate(15);
        });
        
        return ChallengeResource::collection($challenges);
    }

    /**
     * Cloner un défi public
     * 
     * @param Request $request
     * @param Challenge $challenge
     * @return \Illuminate\Http\JsonResponse
     */
    public function clone(Request $request, Challenge $challenge)
    {
        // Vérifier que le défi est public
        if (!$challenge->is_public) {
            return response()->json([
                'message' => 'Ce défi n\'est pas public et ne peut pas être cloné'
            ], 403);
        }
        
        // Valider les données de personnalisation (optionnelles)
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'instructions' => 'sometimes|string',
            'is_public' => 'sometimes|boolean',
            'group_id' => 'sometimes|exists:challenge_groups,id',
            'skip_media' => 'sometimes|boolean',
        ]);
        
        try {
            // Début de la transaction
            DB::beginTransaction();
            
            // Cloner le défi avec replicate()
            $clone = $challenge->replicate(['id', 'created_at', 'updated_at']);
            
            // Définir le nouvel utilisateur comme créateur
            $clone->creator_id = $request->user()->id;
            
            // Définir la référence au défi parent
            $clone->parent_challenge_id = $challenge->id;
            
            // Par défaut, les clones sont privés sauf indication contraire
            $clone->is_public = $validated['is_public'] ?? false;
            
            // Appliquer les personnalisations
            if (isset($validated['title'])) {
                $clone->title = $validated['title'];
            }
            
            if (isset($validated['description'])) {
                $clone->description = $validated['description'];
            }
            
            if (isset($validated['instructions'])) {
                $clone->instructions = $validated['instructions'];
            }
            
            // Sauvegarder le clone
            $clone->save();
            
            // Cloner les médias si demandé
            if (!$request->has('skip_media') || !$request->skip_media) {
                foreach ($challenge->media as $media) {
                    $newMedia = $media->replicate(['id', 'created_at', 'updated_at']);
                    $newMedia->challenge_id = $clone->id;
                    $newMedia->save();
                }
            }
            
            // Si un groupe est spécifié, l'associer au groupe
            if ($request->has('group_id')) {
                $group = ChallengeGroup::find($request->group_id);
                
                if ($group && ($group->isAdmin($request->user()->id) || $group->creator_id === $request->user()->id)) {
                    $group->challenges()->attach($clone->id);
                }
            }
            
            // Invalider les caches concernés
            $this->cacheService->clearCategoriesCache();
            $this->cacheService->clearUserCache($request->user()->id);
            
            // Valider la transaction
            DB::commit();
            
            // Log de l'action
            info('Défi cloné avec succès', [
                'user_id' => $request->user()->id,
                'original_challenge_id' => $challenge->id,
                'cloned_challenge_id' => $clone->id
            ]);
            
            return response()->json([
                'message' => 'Défi cloné avec succès',
                'challenge' => new ChallengeResource($clone->load(['creator', 'category', 'media']))
            ], 201);
        } catch (\Exception $e) {
            // Annuler la transaction en cas d'erreur
            DB::rollBack();
            
            // Log de l'erreur
            info('Erreur lors du clonage du défi', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'challenge_id' => $challenge->id
            ]);
            
            return response()->json([
                'message' => 'Erreur lors du clonage du défi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée un nouveau défi multi-étapes
     * 
     * @param StoreMultiStageChallenge $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createMultiStage(StoreMultiStageChallenge $request)
    {
        try {
            // Utiliser une transaction pour garantir l'intégrité des données
            DB::beginTransaction();
            
            // Créer le défi principal
            $challenge = Challenge::create([
                'title' => $request->title,
                'description' => $request->description,
                'instructions' => $request->instructions,
                'difficulty' => $request->difficulty,
                'category_id' => $request->category_id,
                'is_public' => $request->is_public ?? false,
                'duration' => $request->duration,
                'creator_id' => auth()->id(),
                'multi_stage' => true, // Marquer comme défi multi-étapes
            ]);
            
            // Ajouter les étapes
            foreach ($request->stages as $stageData) {
                ChallengeStage::create([
                    'challenge_id' => $challenge->id,
                    'title' => $stageData['title'],
                    'description' => $stageData['description'],
                    'instructions' => $stageData['instructions'],
                    'order' => $stageData['order'],
                    'duration' => $stageData['duration'] ?? null,
                    'requires_proof' => $stageData['requires_proof'] ?? true,
                ]);
            }
            
            // Valider les changements
            DB::commit();
            
            // Charger le défi avec ses étapes
            $challenge->load('stages');
            
            // Invalider les caches concernés
            $this->cacheService->clearCategoriesCache();
            $this->cacheService->clearUserCache(auth()->id());
            
            return response()->json([
                'message' => 'Défi multi-étapes créé avec succès',
                'challenge' => new ChallengeResource($challenge)
            ], 201);
            
        } catch (\Exception $e) {
            // Annuler la transaction en cas d'erreur
            DB::rollBack();
            
            info('Erreur lors de la création du défi multi-étapes', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la création du défi multi-étapes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les statistiques sur les défis multi-étapes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMultiStageStats()
    {
        // Vérifier que l'utilisateur est premium
        if (!auth()->user()->isPremium()) {
            return response()->json([
                'message' => 'Cette fonctionnalité est réservée aux utilisateurs premium',
                'premium_info' => [
                    'can_upgrade' => true,
                    'features' => ['Défis multi-étapes', 'Statistiques avancées', 'Et bien plus encore...']
                ]
            ], 403);
        }
        
        $userId = auth()->id();
        
        // Nombre de défis multi-étapes créés
        $createdCount = Challenge::where('creator_id', $userId)
            ->where('multi_stage', true)
            ->count();
        
        // Nombre de défis multi-étapes auxquels l'utilisateur participe
        $participatingCount = ChallengeParticipation::where('user_id', $userId)
            ->whereHas('challenge', function ($query) {
                $query->where('multi_stage', true);
            })
            ->count();
        
        // Nombre de défis multi-étapes complétés
        $completedCount = ChallengeParticipation::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereHas('challenge', function ($query) {
                $query->where('multi_stage', true);
            })
            ->count();
        
        // Étapes complétées au total
        $completedStagesCount = ChallengeStageParticipation::whereHas('participation', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('status', 'completed')
            ->count();
        
        // Étapes en cours
        $activeStagesCount = ChallengeStageParticipation::whereHas('participation', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('status', 'active')
            ->count();
        
        return response()->json([
            'created' => [
                'count' => $createdCount,
                'percentage' => Challenge::where('creator_id', $userId)->count() > 0 
                    ? round(($createdCount / Challenge::where('creator_id', $userId)->count()) * 100) 
                    : 0
            ],
            'participating' => [
                'count' => $participatingCount,
                'completed_count' => $completedCount,
                'completion_rate' => $participatingCount > 0 
                    ? round(($completedCount / $participatingCount) * 100) 
                    : 0
            ],
            'stages' => [
                'completed_count' => $completedStagesCount,
                'active_count' => $activeStagesCount
            ]
        ]);
    }
}