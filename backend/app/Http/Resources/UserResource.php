<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $array = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($request->user() && $request->user()->id === $this->id, $this->email),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations conditionnelles (compteurs)
            'created_challenges_count' => $this->when(isset($this->created_challenges_count), $this->created_challenges_count),
            'participations_count' => $this->when(isset($this->participations_count), $this->participations_count),
            'friends_count' => $this->when(isset($this->friends_count), $this->friends_count),
            
            // Relations (quand chargées)
            'created_challenges' => ChallengeResource::collection($this->whenLoaded('createdChallenges')),
            'participations' => ChallengeParticipationResource::collection($this->whenLoaded('participations')),
            'participating_challenges' => ChallengeResource::collection($this->whenLoaded('participatingChallenges')),
            'friends' => UserResource::collection($this->whenLoaded('friends')),
            'pending_friend_requests' => UserResource::collection($this->whenLoaded('pendingFriendRequests')),
        ];
        
        // Ajouter les informations de la relation d'amitié si elles existent
        if ($this->pivot) {
            $array['friendship'] = [
                'id' => $this->pivot->id,
                'status' => $this->pivot->status,
                'created_at' => $this->pivot->created_at,
                'updated_at' => $this->pivot->updated_at,
            ];
        }
        
        return $array;
    }
}
