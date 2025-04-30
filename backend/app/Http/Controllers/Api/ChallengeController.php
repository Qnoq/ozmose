<?php

namespace App\Http\Controllers\Api;

use App\Models\Challenge;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\StoreChallenge;
use App\Http\Requests\UpdateChallenge;
use App\Http\Resources\ChallengeResource;
use App\Services\CacheService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

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
        
        // Créer une nouvelle requête avec les données fusionnées
        $newRequest = Request::create(
            '/api/challenges',
            'POST',
            $mergedData
        );
        
        // Transférer les headers, incluant l'authentification
        $newRequest->headers->replace($request->headers->all());
        
        // Utiliser la méthode store existante
        $response = $this->store($newRequest);
        
        // Si la création a réussi, supprimer le brouillon
        if ($response->getStatusCode() === 201) {
            $this->deleteDraft();
        }
        
        return $response;
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
}