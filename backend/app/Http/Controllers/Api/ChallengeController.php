<?php

namespace App\Http\Controllers\Api;

use App\Models\Challenge;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\StoreChallenge;
use App\Http\Requests\UpdateChallenge;
use App\Http\Resources\ChallengeResource;

class ChallengeController extends Controller
{
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
            $challenge->update($request->validated());
            info('Défi mis à jour avec succès', ['id' => $challenge->id]);
            
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

        $challenge->delete();

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
        $query = Challenge::where('is_public', true)
            ->with(['creator', 'category'])
            ->withCount('participations');
            
        // Filtrer par catégorie si spécifié
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        // Trier selon différents critères
        if ($request->has('sort')) {
            switch ($request->sort) {
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
        } else {
            $query->orderByDesc('created_at');
        }
        
        $challenges = $query->paginate(15);
        
        return ChallengeResource::collection($challenges);
    }
}