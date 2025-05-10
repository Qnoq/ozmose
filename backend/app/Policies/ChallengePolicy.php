<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Challenge;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChallengePolicy
{
    use HandlesAuthorization;

    /**
     * Détermine si l'utilisateur peut voir le défi.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Challenge  $challenge
     * @return bool
     */
    public function view(User $user, Challenge $challenge)
    {
        // Un utilisateur peut voir un défi s'il en est le créateur
        // ou si le défi est public
        return $user->id === $challenge->creator_id || !$challenge->is_private;
    }

    /**
     * Détermine si l'utilisateur peut mettre à jour le défi.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Challenge  $challenge
     * @return bool
     */
    public function update(User $user, Challenge $challenge)
    {
        // Seul le créateur peut mettre à jour le défi
        return $user->id === $challenge->creator_id;
    }

    /**
     * Détermine si l'utilisateur peut ajouter des étapes au défi.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Challenge  $challenge
     * @return bool
     */
    public function addStage(User $user, Challenge $challenge)
    {
        // L'utilisateur doit être premium et créateur du défi
        return $user->isPremium() && $user->id === $challenge->creator_id;
    }
}