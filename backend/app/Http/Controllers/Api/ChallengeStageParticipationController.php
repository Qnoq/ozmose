<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ChallengeStage;
use App\Services\MediaService;
use App\Http\Controllers\Controller;
use App\Models\ChallengeParticipation;
use App\Http\Requests\CompleteStageRequest;
use App\Models\ChallengeStageParticipation;
use App\Notifications\StageCompletedNotification;
use App\Http\Resources\ChallengeStageParticipationResource;

class ChallengeStageParticipationController extends Controller
{
    protected $mediaService;
    
    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }
    
    /**
     * Affiche les participations aux étapes d'un défi
     *
     * @param ChallengeParticipation $participation
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(ChallengeParticipation $participation)
    {
        // Vérifier que l'utilisateur est bien le propriétaire de la participation
        if ($participation->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à consulter cette participation'
            ], 403);
        }
        
        // Vérifier que c'est bien un défi multi-étapes
        if (!$participation->challenge->multi_stage) {
            return response()->json([
                'message' => 'Ce défi n\'est pas un défi multi-étapes'
            ], 400);
        }
        
        // Récupérer les participations aux étapes
        $stageParticipations = ChallengeStageParticipation::where('participation_id', $participation->id)
            ->with(['stage'])
            ->orderBy(function ($query) {
                $query->select('order')
                    ->from('challenge_stages')
                    ->whereColumn('id', 'challenge_stage_participations.stage_id');
            })
            ->get();
        
        return ChallengeStageParticipationResource::collection($stageParticipations);
    }
    
    /**
     * Marque une étape comme complétée
     *
     * @param CompleteStageRequest $request
     * @param ChallengeParticipation $participation
     * @param ChallengeStage $stage
     * @return \Illuminate\Http\JsonResponse
     */
    public function complete(CompleteStageRequest $request, ChallengeParticipation $participation, ChallengeStage $stage)
    {
        // Vérifier que le stage appartient bien au défi de la participation
        if ($stage->challenge_id !== $participation->challenge_id) {
            return response()->json([
                'message' => 'Cette étape n\'appartient pas à ce défi'
            ], 400);
        }
        
        // Récupérer la participation à l'étape
        $stageParticipation = ChallengeStageParticipation::where('participation_id', $participation->id)
            ->where('stage_id', $stage->id)
            ->first();
            
        if (!$stageParticipation) {
            return response()->json([
                'message' => 'Participation à l\'étape non trouvée'
            ], 404);
        }
        
        // Vérifier que l'étape est active
        if ($stageParticipation->status !== 'active') {
            return response()->json([
                'message' => 'Cette étape n\'est pas active'
            ], 400);
        }
        
        // Traiter la preuve si fournie
        $proofMediaId = null;
        if ($request->hasFile('proof_media')) {
            // Utiliser le service média pour traiter et stocker le fichier
            $media = $this->mediaService->store($request->file('proof_media'), [
                'challenge_id' => $participation->challenge_id,
                'participation_id' => $participation->id,
                'user_id' => $request->user()->id,
                'caption' => $request->proof_caption ?? 'Preuve de réalisation - Étape ' . $stage->order,
                'is_public' => $request->has('is_public') ? $request->is_public : false
            ]);
            
            $proofMediaId = $media->id;
        }
        
        // Marquer l'étape comme complétée
        $stageParticipation->update([
            'status' => 'completed',
            'completed_at' => now(),
            'proof_media_id' => $proofMediaId,
            'notes' => $request->notes
        ]);
        
        // Déverrouiller l'étape suivante si elle existe
        $nextStage = ChallengeStage::where('challenge_id', $participation->challenge_id)
            ->where('order', '>', $stage->order)
            ->orderBy('order')
            ->first();
            
        if ($nextStage) {
            $nextStageParticipation = ChallengeStageParticipation::where('participation_id', $participation->id)
                ->where('stage_id', $nextStage->id)
                ->first();
                
            if ($nextStageParticipation) {
                $nextStageParticipation->update([
                    'status' => 'active'
                ]);
            }
        } else {
            // Si c'était la dernière étape, marquer le défi comme complété
            $allCompleted = ChallengeStageParticipation::where('participation_id', $participation->id)
                ->where('status', '!=', 'completed')
                ->doesntExist();
                
            if ($allCompleted) {
                $participation->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }
        }

        // Envoyer une notification au créateur du défi si ce n'est pas l'utilisateur actuel
        $challenge = $participation->challenge;
        $creator = $challenge->creator;
        
        if ($creator->id !== auth()->id()) {
            $isLastStage = !$nextStage;
            $creator->notify(new StageCompletedNotification(
                auth()->user(),
                $challenge,
                $stage,
                $proofMediaId !== null,
                $isLastStage
            ));
        }
        
        return response()->json([
            'message' => 'Étape complétée avec succès',
            'stage_participation' => new ChallengeStageParticipationResource($stageParticipation->fresh(['stage', 'proofMedia'])),
            'all_completed' => $allCompleted ?? false
        ]);
    }
    
    /**
     * Déverrouille manuellement une étape (pour les administrateurs du défi)
     *
     * @param Request $request
     * @param ChallengeParticipation $participation
     * @param ChallengeStage $stage
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlock(Request $request, ChallengeParticipation $participation, ChallengeStage $stage)
    {
        // Vérifier que l'utilisateur est le créateur du défi ou un admin
        $challenge = $participation->challenge;
        if ($challenge->creator_id !== auth()->id()) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à déverrouiller cette étape'
            ], 403);
        }
        
        // Vérifier que le stage appartient bien au défi de la participation
        if ($stage->challenge_id !== $participation->challenge_id) {
            return response()->json([
                'message' => 'Cette étape n\'appartient pas à ce défi'
            ], 400);
        }
        
        // Récupérer la participation à l'étape
        $stageParticipation = ChallengeStageParticipation::where('participation_id', $participation->id)
            ->where('stage_id', $stage->id)
            ->first();
            
        if (!$stageParticipation) {
            return response()->json([
                'message' => 'Participation à l\'étape non trouvée'
            ], 404);
        }
        
        // Vérifier que l'étape est verrouillée
        if ($stageParticipation->status !== 'locked') {
            return response()->json([
                'message' => 'Cette étape n\'est pas verrouillée'
            ], 400);
        }
        
        // Déverrouiller l'étape
        $stageParticipation->update([
            'status' => 'active'
        ]);
        
        return response()->json([
            'message' => 'Étape déverrouillée avec succès',
            'stage_participation' => new ChallengeStageParticipationResource($stageParticipation->fresh(['stage']))
        ]);
    }
}