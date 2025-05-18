<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\CacheService;
use App\Services\MediaService;
use App\Models\TruthOrDareRound;
use App\Models\TruthOrDareSession;
use Illuminate\Support\Facades\DB;
use App\Models\TruthOrDareQuestion;
use App\Http\Controllers\Controller;
use App\Services\TruthOrDareService;
use App\Models\TruthOrDareParticipant;

class TruthOrDareController extends Controller
{
    protected $cacheService;
    protected $truthOrDareService;

    public function __construct(CacheService $cacheService, TruthOrDareService $truthOrDareService)
    {
        $this->cacheService = $cacheService;
        $this->truthOrDareService = $truthOrDareService;
    }

    /**
     * Méthode helper pour invalider le cache des sessions
     */
    protected function clearUserSessionsCache($userId)
    {
        $pattern = "ozmose:truth-or-dare:sessions:user_{$userId}:*";
        $keys = \Illuminate\Support\Facades\Redis::keys($pattern);
        
        if (!empty($keys)) {
            \Illuminate\Support\Facades\Redis::del($keys);
        }
    }

    /**
     * Liste des sessions avec cache
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $page = $request->get('page', 1);
        
        // Clé de cache unique pour cet utilisateur
        $cacheKey = "ozmose:truth-or-dare:sessions:user_{$userId}:page_{$page}";
        
        $sessions = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($userId) {
            return TruthOrDareSession::where('creator_id', $userId)
                ->orWhereHas('participants', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->with(['creator', 'participants'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        });
        
        return response()->json($sessions);
    }

    /**
     * Afficher les détails d'une session
     */
    public function show(TruthOrDareSession $session, Request $request)
    {
        $user = $request->user();
        
        // Vérifier que l'utilisateur est participant ou créateur
        $isParticipant = $session->participants()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isParticipant && $session->creator_id !== $user->id) {
            return response()->json([
                'message' => 'Vous n\'avez pas accès à cette session'
            ], 403);
        }
        
        return response()->json([
            'session' => $session->load(['creator', 'participants', 'rounds'])
        ]);
    }

    /**
     * Quitter une session
     */
    public function leaveSession(TruthOrDareSession $session, Request $request)
    {
        $user = $request->user();
        
        $participant = TruthOrDareParticipant::where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
            
        if (!$participant) {
            return response()->json([
                'message' => 'Vous n\'êtes pas participant actif de cette session'
            ], 404);
        }
        
        // Si c'est le créateur qui quitte, désactiver la session
        if ($session->creator_id === $user->id) {
            $session->update(['is_active' => false]);
        }
        
        $participant->update(['status' => 'left']);
        
        return response()->json([
            'message' => 'Vous avez quitté la session'
        ]);
    }

    /**
     * Obtenir les questions avec cache
     */
    public function getQuestions(Request $request)
    {
        $type = $request->get('type');
        $intensity = $request->get('intensity');
        $page = $request->get('page', 1);
        
        // Construire une clé de cache
        $cacheKey = "ozmose:truth-or-dare:questions";
        if ($type) $cacheKey .= ":type_{$type}";
        if ($intensity) $cacheKey .= ":intensity_{$intensity}";
        $cacheKey .= ":page_{$page}";
        
        $questions = \Illuminate\Support\Facades\Cache::remember($cacheKey, 1800, function () use ($type, $intensity, $request) {
            $query = TruthOrDareQuestion::query();
            
            if ($type) $query->where('type', $type);
            if ($intensity) $query->where('intensity', $intensity);
            
            // Inclure seulement les questions publiques ou de l'utilisateur
            $query->where(function ($q) use ($request) {
                $q->where('is_official', true)
                  ->orWhere('is_public', true);
                  
                if ($request->user()) {
                    $q->orWhere('creator_id', $request->user()->id);
                }
            });
            
            return $query->paginate(20);
        });
        
        return response()->json($questions);
    }
    
    /**
     * Créer une nouvelle session
     */
    public function createSession(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'intensity' => 'required|in:soft,spicy,hot',
            'is_public' => 'boolean',
            'max_participants' => 'nullable|integer|min:2|max:20',
        ]);

        $user = $request->user();
        
        // Limites pour les utilisateurs gratuits
        if (!$user->isPremium()) {
            $activeSessions = TruthOrDareSession::where('creator_id', $user->id)
                ->where('is_active', true)
                ->count();
                
            if ($activeSessions >= 1) {
                return response()->json([
                    'message' => 'Les utilisateurs gratuits ne peuvent avoir qu\'une session active',
                    'premium_info' => [
                        'can_upgrade' => true,
                        'features' => ['Sessions illimitées', 'Questions exclusives', 'Plus de participants']
                    ]
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            $session = TruthOrDareSession::create([
                'creator_id' => $user->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'intensity' => $validated['intensity'],
                'is_public' => $validated['is_public'] ?? false,
                'max_participants' => $validated['max_participants'] ?? 10,
                'premium_only' => false,
            ]);

            // Générer un code de session
            $session->generateJoinCode();

            // Ajouter le créateur comme participant
            TruthOrDareParticipant::create([
                'session_id' => $session->id,
                'user_id' => $user->id,
                'status' => 'active'
            ]);

            DB::commit();

            // Invalider le cache des sessions de l'utilisateur
            $this->clearUserSessionsCache($request->user()->id);

            return response()->json([
                'message' => 'Session créée avec succès',
                'session' => $session->load('participants')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Erreur lors de la création de la session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejoindre une session avec un code
     */
    public function joinSession(Request $request)
    {
        $validated = $request->validate([
            'join_code' => 'required|string|size:6'
        ]);

        $session = TruthOrDareSession::where('join_code', $validated['join_code'])
            ->where('is_active', true)
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Code de session invalide ou session inactive'
            ], 404);
        }

        $user = $request->user();

        // Vérifier si l'utilisateur est déjà participant
        $existingParticipant = TruthOrDareParticipant::where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingParticipant) {
            if ($existingParticipant->status === 'active') {
                return response()->json([
                    'message' => 'Vous êtes déjà dans cette session'
                ], 400);
            } else {
                // Réactiver le participant
                $existingParticipant->update(['status' => 'active']);
                return response()->json([
                    'message' => 'Vous avez rejoint la session',
                    'session' => $session->load('participants')
                ]);
            }
        }

        // Vérifier la limite de participants
        $activeCount = $session->activeParticipants()->count();
        if ($activeCount >= $session->max_participants) {
            return response()->json([
                'message' => 'La session est complète'
            ], 400);
        }

        // Ajouter le participant
        TruthOrDareParticipant::create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'status' => 'active'
        ]);

        return response()->json([
            'message' => 'Vous avez rejoint la session',
            'session' => $session->load('participants')
        ]);
    }

    /**
     * Obtenir une question aléatoire
     */
    public function getRandomQuestion(Request $request, TruthOrDareSession $session)
    {
        $validated = $request->validate([
            'type' => 'required|in:truth,dare',
        ]);

        $user = $request->user();

        // Vérifier que l'utilisateur est participant actif
        $participant = TruthOrDareParticipant::where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'Vous n\'êtes pas participant actif de cette session'
            ], 403);
        }

        // Récupérer une question aléatoire
        $query = TruthOrDareQuestion::where('type', $validated['type'])
            ->where('intensity', '<=', $session->intensity);

        // Inclure les questions officielles et publiques
        $query->where(function ($q) use ($user) {
            $q->where('is_official', true)
              ->orWhere('is_public', true)
              ->orWhere('creator_id', $user->id);
        });

        // Exclure les questions premium pour les non-premium
        if (!$user->isPremium()) {
            $query->where('is_premium', false);
        }

        // Exclure les questions déjà utilisées dans cette session
        $usedQuestionIds = TruthOrDareRound::where('session_id', $session->id)
            ->pluck('question_id');
            
        if ($usedQuestionIds->isNotEmpty()) {
            $query->whereNotIn('id', $usedQuestionIds);
        }

        $question = $query->inRandomOrder()->first();

        if (!$question) {
            return response()->json([
                'message' => 'Aucune question disponible'
            ], 404);
        }

        // Créer un nouveau round
        $round = TruthOrDareRound::create([
            'session_id' => $session->id,
            'participant_id' => $participant->id,
            'question_id' => $question->id,
            'choice' => $validated['type'],
            'status' => 'pending'
        ]);

        return response()->json([
            'question' => $question,
            'round_id' => $round->id
        ]);
    }

    /**
     * Compléter un round
     */
    public function completeRound(Request $request, TruthOrDareRound $round)
    {
        $validated = $request->validate([
            'response' => 'nullable|string', // Pour les vérités
            'proof_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov|max:20480',
            'rating' => 'nullable|integer|min:1|max:5'
        ]);

        $user = $request->user();

        // Vérifier que c'est bien le participant du round
        if ($round->participant->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à compléter ce round'
            ], 403);
        }

        // Gérer la preuve média si fournie
        if ($request->hasFile('proof_media')) {
            $media = app(MediaService::class)->store($request->file('proof_media'), [
                'user_id' => $user->id,
                'caption' => 'Preuve Action ou Vérité',
            ]);
            $round->proof_media_id = $media->id;
        }

        // Mettre à jour le round
        $round->update([
            'status' => 'completed',
            'response' => $validated['response'] ?? null,
            'rating' => $validated['rating'] ?? null,
        ]);

        // Mettre à jour les statistiques du participant
        if ($round->choice === 'truth') {
            $round->participant->increment('truths_answered');
        } else {
            $round->participant->increment('dares_completed');
        }

        // Mettre à jour les stats de la question
        $round->question->incrementUsage();
        if (isset($validated['rating'])) {
            $round->question->updateRating();
        }

        return response()->json([
            'message' => 'Round complété avec succès',
            'round' => $round->fresh()
        ]);
    }

    /**
     * Passer un round
     */
    public function skipRound(TruthOrDareRound $round, Request $request)
    {
        $user = $request->user();

        // Vérifier que c'est bien le participant du round
        if ($round->participant->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à passer ce round'
            ], 403);
        }

        // Limiter les skips pour les non-premium
        if (!$user->isPremium()) {
            $skipsUsed = $round->participant->skips_used;
            if ($skipsUsed >= 3) {
                return response()->json([
                    'message' => 'Vous avez atteint la limite de passes',
                    'premium_info' => [
                        'can_upgrade' => true,
                        'features' => ['Passes illimités']
                    ]
                ], 403);
            }
        }

        $round->update(['status' => 'skipped']);
        $round->participant->increment('skips_used');

        return response()->json([
            'message' => 'Round passé'
        ]);
    }

    /**
     * Obtenir les statistiques d'une session
     */
    public function getSessionStats(TruthOrDareSession $session)
    {
        info('getSessionStats');
        $stats = $this->truthOrDareService->getSessionStats($session);
        return response()->json($stats);
    }

    /**
     * Créer une question personnalisée (Premium)
     */
    public function createQuestion(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:truth,dare',
            'content' => 'required|string|max:500',
            'intensity' => 'required|in:soft,spicy,hot',
            'category_id' => 'nullable|exists:categories,id',
            'is_public' => 'boolean'
        ]);
        
        $question = TruthOrDareQuestion::create([
            'creator_id' => $request->user()->id,
            'type' => $validated['type'],
            'content' => $validated['content'],
            'intensity' => $validated['intensity'],
            'category_id' => $validated['category_id'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'is_official' => false
        ]);
        
        // Invalider le cache des questions
        $this->cacheService->clearTruthOrDareCache('questions');
        
        return response()->json([
            'message' => 'Question créée avec succès',
            'question' => $question
        ], 201);
    }

    /**
     * Modifier une question (Premium)
     */
    public function updateQuestion(Request $request, TruthOrDareQuestion $question)
    {
        // Vérifier que l'utilisateur est le créateur
        if ($question->creator_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à modifier cette question'
            ], 403);
        }
        
        $validated = $request->validate([
            'content' => 'sometimes|string|max:500',
            'intensity' => 'sometimes|in:soft,spicy,hot',
            'is_public' => 'sometimes|boolean'
        ]);
        
        $question->update($validated);
        
        // Invalider le cache
        $this->cacheService->clearTruthOrDareCache('questions');
        
        return response()->json([
            'message' => 'Question modifiée avec succès',
            'question' => $question
        ]);
    }

    /**
     * Supprimer une question (Premium)
     */
    public function deleteQuestion(Request $request, TruthOrDareQuestion $question)
    {
        if ($question->creator_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette question'
            ], 403);
        }
        
        $question->delete();
        
        // Invalider le cache
        $this->cacheService->clearTruthOrDareCache('questions');
        
        return response()->json([
            'message' => 'Question supprimée avec succès'
        ]);
    }

    /**
     * Obtenir les packs de questions (Premium)
     */
    public function getQuestionPacks(Request $request)
    {
        // Implémenter selon votre logique de packs
        return response()->json([
            'packs' => [
                [
                    'id' => 1,
                    'name' => 'Pack Romantique',
                    'description' => 'Questions pour couples',
                    'questions_count' => 50,
                    'intensity' => 'soft'
                ],
                [
                    'id' => 2,
                    'name' => 'Pack Soirée Épicée',
                    'description' => 'Questions pour soirées entre amis',
                    'questions_count' => 100,
                    'intensity' => 'spicy'
                ]
            ]
        ]);
    }

    /**
     * Archiver une session (Premium)
     */
    public function archiveSession(Request $request, TruthOrDareSession $session)
    {
        if ($session->creator_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Seul le créateur peut archiver la session'
            ], 403);
        }
        
        $session->update(['is_active' => false]);
        
        return response()->json([
            'message' => 'Session archivée avec succès'
        ]);
    }

    /**
     * Obtenir les sessions archivées (Premium)
     */
    public function getArchivedSessions(Request $request)
    {
        $sessions = TruthOrDareSession::where('creator_id', $request->user()->id)
            ->where('is_active', false)
            ->with(['participants'])
            ->orderBy('updated_at', 'desc')
            ->paginate(10);
            
        return response()->json($sessions);
    }
}