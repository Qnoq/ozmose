<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'premium_only' => $this->premium_only,
            'max_members' => $this->max_members,
            'creator_id' => $this->creator_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Compteurs conditionnels
            'members_count' => $this->when(isset($this->members_count), $this->members_count),
            'challenges_count' => $this->when(isset($this->challenges_count), $this->challenges_count),
            
            // Relations
            'creator' => new UserResource($this->whenLoaded('creator')),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'challenges' => ChallengeResource::collection($this->whenLoaded('challenges')),
            
            // Informations utilisateur
            'user_role' => $this->when($this->relationLoaded('members') && $user, function () use ($user) {
                $member = $this->members->firstWhere('id', $user->id);
                if (!$member) return null;
                return $member->pivot->role;
            }),
            
            // Métadonnées
            'is_at_capacity' => $this->when(isset($this->members_count), 
                                          function() { return $this->members_count >= $this->max_members; },
                                          $this->isAtMemberLimit()),
            'remaining_slots' => $this->when(isset($this->members_count), 
                                           function() { return max(0, $this->max_members - $this->members_count); },
                                           $this->getRemainingSlots()),
            'can_manage' => $this->when($user, function() use ($user) {
                return $this->isAdmin($user->id);
            }),
        ];
    }
}