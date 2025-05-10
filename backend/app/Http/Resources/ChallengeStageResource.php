<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeStageResource extends JsonResource
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
            'challenge_id' => $this->challenge_id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'order' => $this->order,
            'duration' => $this->duration,
            'requires_proof' => $this->requires_proof,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations (quand chargées)
            'challenge' => new ChallengeResource($this->whenLoaded('challenge')),
        ];
    }
}