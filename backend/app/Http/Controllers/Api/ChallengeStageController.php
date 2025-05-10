<?php

namespace App\Http\Controllers\Api;

use App\Models\Challenge;
use App\Models\ChallengeStage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChallengeStage;
use App\Http\Requests\UpdateChallengeStage;
use App\Http\Resources\ChallengeStageResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ChallengeStageController extends Controller
{
    use AuthorizesRequests;

    /**
     * Liste toutes les étapes d'un défi.
     *
     * @param  Challenge  $challenge
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Challenge $challenge)
    {
        // Vérifier si l'utilisateur a accès à ce défi
        $this->authorize('view', $challenge);
        
        $stages = $challenge->stages()->orderBy('order')->get();
        
        return ChallengeStageResource::collection($stages);
    }

    /**
     * Enregistre une nouvelle étape pour un défi.
     *
     * @param  StoreChallengeStage  $request
     * @param  Challenge  $challenge
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreChallengeStage $request, Challenge $challenge): JsonResponse
    {
        // Vérifier si le défi est bien un défi multi-étapes
        if (!$challenge->multi_stage) {
            return response()->json([
                'message' => 'Ce défi n\'est pas un défi multi-étapes'
            ], 400);
        }
        
        $stage = ChallengeStage::create([
            'challenge_id' => $challenge->id,
            'title' => $request->title,
            'description' => $request->description,
            'instructions' => $request->instructions,
            'order' => $request->order,
            'duration' => $request->duration,
            'requires_proof' => $request->requires_proof ?? true,
        ]);
        
        return response()->json([
            'message' => 'Étape ajoutée avec succès',
            'stage' => new ChallengeStageResource($stage)
        ], 201);
    }

    /**
     * Affiche une étape spécifique.
     *
     * @param  Challenge  $challenge
     * @param  ChallengeStage  $stage
     * @return \App\Http\Resources\ChallengeStageResource
     */
    public function show(Challenge $challenge, ChallengeStage $stage)
    {
        // Vérifier que l'étape appartient bien au défi
        if ($stage->challenge_id !== $challenge->id) {
            abort(404);
        }
        
        // Vérifier si l'utilisateur a accès à ce défi
        $this->authorize('view', $challenge);
        
        return new ChallengeStageResource($stage);
    }

    /**
     * Met à jour une étape spécifique.
     *
     * @param  UpdateChallengeStage  $request
     * @param  Challenge  $challenge
     * @param  ChallengeStage  $stage
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateChallengeStage $request, Challenge $challenge, ChallengeStage $stage): JsonResponse
    {
        // Vérifier que l'étape appartient bien au défi
        if ($stage->challenge_id !== $challenge->id) {
            abort(404);
        }
        
        $stage->update($request->validated());
        
        return response()->json([
            'message' => 'Étape mise à jour avec succès',
            'stage' => new ChallengeStageResource($stage->fresh())
        ]);
    }

    /**
     * Supprime une étape spécifique.
     *
     * @param  Challenge  $challenge
     * @param  ChallengeStage  $stage
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Challenge $challenge, ChallengeStage $stage): JsonResponse
    {
        // Vérifier que l'étape appartient bien au défi
        if ($stage->challenge_id !== $challenge->id) {
            abort(404);
        }
        
        // Vérifier que l'utilisateur est le créateur du défi
        $this->authorize('update', $challenge);
        
        // Vérifier qu'il reste plus de 2 étapes après suppression
        $stageCount = $challenge->stages()->count();
        if ($stageCount <= 2) {
            return response()->json([
                'message' => 'Un défi multi-étapes doit contenir au moins 2 étapes'
            ], 400);
        }
        
        $stage->delete();
        
        return response()->json([
            'message' => 'Étape supprimée avec succès'
        ]);
    }
}