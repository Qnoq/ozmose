<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations conditionnelles (compteurs)
            'challenges_count' => $this->when(isset($this->challenges_count), $this->challenges_count),
            
            // Relations (quand chargées)
            'challenges' => ChallengeResource::collection($this->whenLoaded('challenges')),
        ];
    }
}
