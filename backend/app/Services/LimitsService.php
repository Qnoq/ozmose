<?php

namespace App\Services;

use App\Models\User;
use App\Models\ChallengeMedia;

class LimitsService
{
    public function checkMediaUploadLimit(User $user, $fileSize)
    {
        // Récupérer l'espace utilisé par l'utilisateur
        $usedSpace = ChallengeMedia::where('user_id', $user->id)->sum('size');
        
        // Définir les limites selon le statut premium
        $spaceLimit = $user->isPremium() ? 20 * 1024 * 1024 * 1024 : 2 * 1024 * 1024 * 1024; // 20 Go vs 2 Go
        
        // Vérifier si l'upload dépasserait la limite
        if ($usedSpace + $fileSize > $spaceLimit) {
            return [
                'allowed' => false,
                'used' => $usedSpace,
                'limit' => $spaceLimit,
                'can_upgrade' => !$user->isPremium()
            ];
        }
        
        return ['allowed' => true];
    }
    
    public function checkVideoDurationLimit(User $user, $duration)
    {
        // Limite en secondes
        $durationLimit = $user->isPremium() ? 300 : 60; // 5 min vs 1 min
        
        if ($duration > $durationLimit) {
            return [
                'allowed' => false,
                'limit' => $durationLimit,
                'can_upgrade' => !$user->isPremium()
            ];
        }
        
        return ['allowed' => true];
    }
    
    public function checkGroupLimit(User $user)
    {
        // Vérifier le nombre de groupes actuels
        $groupCount = $user->challengeGroups()->count();
        $groupLimit = $user->isPremium() ? PHP_INT_MAX : 3; // Illimité vs 3
        
        if ($groupCount >= $groupLimit) {
            return [
                'allowed' => false,
                'count' => $groupCount,
                'limit' => $groupLimit,
                'can_upgrade' => !$user->isPremium()
            ];
        }
        
        return ['allowed' => true];
    }
}