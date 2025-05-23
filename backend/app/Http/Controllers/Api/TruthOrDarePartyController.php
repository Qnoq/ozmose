<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\TruthOrDareSession;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\TruthOrDareService;
use App\Models\TruthOrDareParticipant;

class TruthOrDarePartyController extends Controller
{
    protected $truthOrDareService;

    public function __construct(TruthOrDareService $truthOrDareService)
    {
        $this->truthOrDareService = $truthOrDareService;
    }

    /**
     * Créer une session en mode soirée (avec invités)
     */
    public function createPartySession(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'intensity' => 'required|in:soft,spicy,hot',
            'participants' => 'required|array|min:2',
            'participants.*.name' => 'required|string|max:50',
            'participants.*.avatar' => 'nullable|string|max:5', // Emoji
            'include_host' => 'boolean' // Si l'hôte joue aussi
        ]);

        $user = $request->user();
        
        DB::beginTransaction();
        try {
            
            // Créer la session
            $session = TruthOrDareSession::create([
                'creator_id' => $user->id,
                'name' => $validated['name'],
                'intensity' => $validated['intensity'],
                'is_public' => false,
                'is_active' => true,
                'max_participants' => count($validated['participants']) + 1,
            ]);
            
            // Ajouter l'hôte s'il joue
            if ($validated['include_host'] ?? true) {
                TruthOrDareParticipant::create([
                    'session_id' => $session->id,
                    'user_id' => $user->id,
                    'status' => 'active',
                    'turn_order' => 0
                ]);
            }

            // Ajouter les invités
            foreach ($validated['participants'] as $index => $participant) {
                TruthOrDareParticipant::create([
                    'session_id' => $session->id,
                    'guest_name' => $participant['name'],
                    'guest_avatar' => $participant['avatar'] ?? $this->getRandomEmoji(),
                    'status' => 'active',
                    'turn_order' => $index + 1
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Session de soirée créée avec succès',
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
     * Ajouter un participant pendant la partie
     */
    public function addGuestParticipant(Request $request, TruthOrDareSession $truthOrDareSession)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'avatar' => 'nullable|string|max:5'
        ]);

        $user = $request->user();
       
        // Vérifier que l'utilisateur est l'hôte
        if ($truthOrDareSession->creator_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Seul l\'hôte peut ajouter des participants'
            ], 403);
        }

        // Vérifier la limite
        if ($truthOrDareSession->activeParticipants()->count() >= $truthOrDareSession->max_participants) {
            return response()->json([
                'message' => 'La session est complète'
            ], 400);
        }

        // Déterminer l'ordre de tour
        $maxOrder = $truthOrDareSession->participants()->max('turn_order') ?? 0;

        $participant = TruthOrDareParticipant::create([
            'session_id' => $truthOrDareSession->id,
            'guest_name' => $validated['name'],
            'guest_avatar' => $validated['avatar'] ?? $this->getRandomEmoji(),
            'status' => 'active',
            'turn_order' => $maxOrder + 1
        ]);

        return response()->json([
            'message' => 'Participant ajouté',
            'participant' => $participant
        ]);
    }

    /**
     * Gérer les tours de jeu
     */
    public function getNextTurn(TruthOrDareSession $truthOrDareSession)
    {
        // Récupérer le dernier round
        $lastRound = $truthOrDareSession->rounds()->latest()->first();
        
        if (!$lastRound) {
            // Premier tour
            $nextParticipant = $truthOrDareSession->participants()
                ->where('status', 'active')
                ->orderBy('turn_order')
                ->first();
        } else {
            // Trouver le participant du dernier round
            $lastParticipant = $lastRound->participant;
            
            // Trouver le suivant dans l'ordre
            $nextParticipant = $truthOrDareSession->participants()
                ->where('status', 'active')
                ->where('turn_order', '>', $lastParticipant->turn_order)
                ->orderBy('turn_order')
                ->first();
            
            // Si on est à la fin, recommencer
            if (!$nextParticipant) {
                $nextParticipant = $truthOrDareSession->participants()
                    ->where('status', 'active')
                    ->orderBy('turn_order')
                    ->first();
            }
        }

        return response()->json([
            'current_player' => [
                'id' => $nextParticipant->id,
                'name' => $nextParticipant->display_name,
                'avatar' => $nextParticipant->avatar,
                'is_guest' => $nextParticipant->isGuest()
            ]
        ]);
    }

    /**
     * Mode "Roue de la fortune" - Sélection aléatoire
     */
    public function spinWheel(TruthOrDareSession $truthOrDareSession)
    {
        $participants = $truthOrDareSession->activeParticipants()->get();
        
        if ($participants->isEmpty()) {
            return response()->json([
                'message' => 'Aucun participant actif'
            ], 400);
        }

        $selected = $participants->random();

        return response()->json([
            'selected_player' => [
                'id' => $selected->id,
                'name' => $selected->display_name,
                'avatar' => $selected->avatar,
                'is_guest' => $selected->isGuest()
            ]
        ]);
    }

    /**
     * Terminer la session et sauvegarder les stats (optionnel)
     */
    public function endSession(Request $request, TruthOrDareSession $truthOrDareSession)
    {
        $validated = $request->validate([
            'save_stats' => 'boolean',
            'create_accounts' => 'array', // Participants qui veulent créer un compte
            'create_accounts.*.participant_id' => 'required|exists:truth_or_dare_participants,id',
            'create_accounts.*.email' => 'required|email'
        ]);

        // Vérifier que l'utilisateur est l'hôte
        if ($truthOrDareSession->creator_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Seul l\'hôte peut terminer la session'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Si on veut sauvegarder les stats et créer des comptes
            if ($validated['save_stats'] && isset($validated['create_accounts'])) {
                foreach ($validated['create_accounts'] as $accountData) {
                    $participant = TruthOrDareParticipant::find($accountData['participant_id']);
                    
                    if ($participant && $participant->isGuest()) {
                        // Créer un compte utilisateur (simplifié)
                        $newUser = User::create([
                            'name' => $participant->guest_name,
                            'email' => $accountData['email'],
                            'password' => bcrypt(Str::random(8)), // Mot de passe temporaire
                        ]);
                        
                        // Associer le participant au nouvel utilisateur
                        $participant->update(['user_id' => $newUser->id]);
                        
                        // TODO: Envoyer un email d'invitation
                    }
                }
            }

            // Marquer la session comme inactive
            $truthOrDareSession->update(['is_active' => false]);

            DB::commit();

            return response()->json([
                'message' => 'Session terminée',
                'final_stats' => $this->truthOrDareService->getSessionStats($truthOrDareSession)
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Erreur lors de la fermeture de la session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir des emojis aléatoires pour les avatars
     */
    private function getRandomEmoji(): string
    {
        $emojis = ['😀', '😎', '🤪', '😇', '🤩', '😏', '🙃', '🤗', '🤔', '😈'];
        return $emojis[array_rand($emojis)];
    }

    /**
     * Retirer un participant pendant la partie
     */
    public function removeGuestParticipant(Request $request, TruthOrDareSession $truthOrDareSession, TruthOrDareParticipant $participant)
    {
        // Vérifier que l'utilisateur est l'hôte
        if ($truthOrDareSession->creator_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Seul l\'hôte peut retirer des participants'
            ], 403);
        }
        
        // Vérifier que le participant appartient à cette session
        if ($participant->session_id !== $truthOrDareSession->id) {
            return response()->json([
                'message' => 'Ce participant n\'appartient pas à cette session'
            ], 400);
        }
        
        $participant->update(['status' => 'kicked']);
        
        return response()->json([
            'message' => 'Participant retiré'
        ]);
    }
}