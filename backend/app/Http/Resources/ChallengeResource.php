<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'difficulty' => $this->difficulty,
            'duration' => $this->duration,
            'is_public' => $this->is_public,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        
            // Nouvelles données de clonage
            'is_clone' => $this->parent_challenge_id !== null,
            'parent_challenge_id' => $this->when($this->parent_challenge_id, $this->parent_challenge_id),
            'clones_count' => $this->when(isset($this->clones_count), $this->clones_count),
            
            // Relations conditionnelles (compteurs)
            'participants_count' => $this->when(isset($this->participants_count), $this->participants_count),
            'media_count' => $this->when(isset($this->media_count), $this->media_count),
            
            // Relations (quand chargées)
            'creator' => new UserResource($this->whenLoaded('creator')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'participants' => UserResource::collection($this->whenLoaded('participants')),
            'participations' => ChallengeParticipationResource::collection($this->whenLoaded('participations')),
            'media' => ChallengeMediaResource::collection($this->whenLoaded('media')),
            'parent' => new ChallengeResource($this->whenLoaded('parent')),
            'stages' => ChallengeStageResource::collection($this->whenLoaded('stages')),
        ];
    }
}
