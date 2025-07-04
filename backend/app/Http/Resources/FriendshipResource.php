<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
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
            'user_id' => $this->user_id,
            'friend_id' => $this->friend_id,
            'status' => $this->status,  // 'pending', 'accepted', 'rejected'
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations (quand chargées)
            'user' => new UserResource($this->whenLoaded('user')),
            'friend' => new UserResource($this->whenLoaded('friend')),
        ];
    }
}
