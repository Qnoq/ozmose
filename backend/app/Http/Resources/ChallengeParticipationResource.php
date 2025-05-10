<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeParticipationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isMultiStage = $this->whenLoaded('challenge', function () {
            return $this->challenge->multi_stage ?? false;
        }, false);
        
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'challenge_id' => $this->challenge_id,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations (quand chargées)
            'user' => new UserResource($this->whenLoaded('user')),
            'challenge' => new ChallengeResource($this->whenLoaded('challenge')),
            
            // Champs spécifiques aux défis multi-étapes
            'is_multi_stage' => $isMultiStage,
            'active_stage' => $this->when($isMultiStage, function () {
                $activeStageParticipation = $this->stageParticipations()->where('status', 'active')->first();
                return $activeStageParticipation ? new ChallengeStageParticipationResource($activeStageParticipation->load('stage')) : null;
            }),
            'stages_progress' => $this->when($isMultiStage, function () {
                $total = $this->stageParticipations()->count();
                $completed = $this->stageParticipations()->where('status', 'completed')->count();
                return [
                    'completed' => $completed,
                    'total' => $total,
                    'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0
                ];
            }),
        ];
    }
}
