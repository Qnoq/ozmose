<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeStageParticipationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'participation_id' => $this->participation_id,
            'stage_id' => $this->stage_id,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations (quand chargées)
            'stage' => new ChallengeStageResource($this->whenLoaded('stage')),
            'proof_media' => new ChallengeMediaResource($this->whenLoaded('proofMedia')),
        ];
    }
}