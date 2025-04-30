<?php

namespace App\Services;

use App\Models\User;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    // Préfixe pour toutes les clés de leaderboard
    const PREFIX = 'ozmose:leaderboard:';
    
    // Durées pour les différents types de classements (en secondes)
    const TTL_WEEKLY = 604800;  // 7 jours
    const TTL_MONTHLY = 2592000; // 30 jours

    /**
     * Ajouter des points à un utilisateur dans tous les classements pertinents
     */
    public function addPointsForChallenge(ChallengeParticipation $participation)
    {
        $userId = $participation->user_id;
        $challenge = $participation->challenge;
        $categoryId = $challenge->category_id;
        
        // Points attribués selon la difficulté
        $points = $this->calculatePoints($challenge->difficulty);
        
        // Ajout aux différents classements
        $this->addPointsToGlobal($userId, $points);
        $this->addPointsToCategory($userId, $categoryId, $points);
        $this->addPointsToWeekly($userId, $points);
        $this->addPointsToMonthly($userId, $points);
        
        // Si c'est un défi premium, points bonus
        if ($challenge->premium_only) {
            $this->addPointsToPremiumBoard($userId, $points * 0.5); // 50% bonus
        }
        
        return $points;
    }
    
    /**
     * Ajouter des points au classement global
     */
    public function addPointsToGlobal($userId, $points)
    {
        Redis::zincrby(self::PREFIX . 'global', $points, $userId);
        return true;
    }
    
    /**
     * Ajouter des points au classement par catégorie
     */
    public function addPointsToCategory($userId, $categoryId, $points)
    {
        Redis::zincrby(self::PREFIX . "category:{$categoryId}", $points, $userId);
        return true;
    }
    
    /**
     * Ajouter des points au classement hebdomadaire
     */
    public function addPointsToWeekly($userId, $points)
    {
        $key = self::PREFIX . 'weekly:' . date('YW');
        Redis::zincrby($key, $points, $userId);
        Redis::expire($key, self::TTL_WEEKLY);
        return true;
    }
    
    /**
     * Ajouter des points au classement mensuel
     */
    public function addPointsToMonthly($userId, $points)
    {
        $key = self::PREFIX . 'monthly:' . date('Ym');
        Redis::zincrby($key, $points, $userId);
        Redis::expire($key, self::TTL_MONTHLY);
        return true;
    }
    
    /**
     * Ajouter des points au classement premium
     */
    public function addPointsToPremiumBoard($userId, $points)
    {
        Redis::zincrby(self::PREFIX . 'premium', $points, $userId);
        return true;
    }
    
    /**
     * Récupérer un classement avec les détails des utilisateurs
     */
    public function getLeaderboard($type = 'global', $param = null, $limit = 10, $withUserDetails = true)
    {
        $key = $this->getLeaderboardKey($type, $param);
        
        // Récupérer les scores avec les IDs utilisateurs
        $results = Redis::zrevrange($key, 0, $limit - 1, 'WITHSCORES');
        
        // Si on ne veut pas les détails utilisateurs, retourner juste les IDs et scores
        if (!$withUserDetails) {
            $formattedResults = [];
            foreach ($results as $userId => $score) {
                $formattedResults[] = [
                    'user_id' => (int)$userId,
                    'score' => (int)$score
                ];
            }
            return $formattedResults;
        }
        
        // Sinon récupérer les détails des utilisateurs
        $leaderboard = [];
        $position = 1;
        
        foreach ($results as $userId => $score) {
            $user = User::find($userId);
            if ($user) {
                $leaderboard[] = [
                    'position' => $position++,
                    'user_id' => (int)$userId,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'score' => (int)$score,
                    'is_premium' => $user->is_premium,
                ];
            }
        }
        
        return $leaderboard;
    }
    
    /**
     * Obtenir la position d'un utilisateur dans un classement
     */
    public function getUserRank($userId, $type = 'global', $param = null)
    {
        $key = $this->getLeaderboardKey($type, $param);
        
        // Récupérer le score de l'utilisateur
        $score = Redis::zscore($key, $userId);
        
        // Si pas de score, l'utilisateur n'est pas classé
        if ($score === null) {
            return [
                'ranked' => false,
                'rank' => null,
                'score' => 0
            ];
        }
        
        // Récupérer le rang (0-based, donc +1 pour le rang réel)
        $rank = Redis::zrevrank($key, $userId);
        
        return [
            'ranked' => true,
            'rank' => $rank !== null ? $rank + 1 : null,
            'score' => (int)$score
        ];
    }
    
    /**
     * Obtenir des stats sur le leaderboard
     */
    public function getLeaderboardStats($type = 'global', $param = null)
    {
        $key = $this->getLeaderboardKey($type, $param);
        
        // Nombre total de participants
        $total = Redis::zcard($key);
        
        // Score le plus élevé
        $topScore = 0;
        if ($total > 0) {
            $top = Redis::zrevrange($key, 0, 0, 'WITHSCORES');
            $topScore = reset($top);
        }
        
        return [
            'total_participants' => $total,
            'top_score' => (int)$topScore,
        ];
    }
    
    /**
     * Obtenir la clé Redis appropriée pour un type de classement
     */
    private function getLeaderboardKey($type, $param = null)
    {
        switch ($type) {
            case 'category':
                return self::PREFIX . "category:{$param}"; // param = category_id
            case 'weekly':
                $week = $param ?: date('YW');
                return self::PREFIX . "weekly:{$week}";
            case 'monthly':
                $month = $param ?: date('Ym');
                return self::PREFIX . "monthly:{$month}";
            case 'premium':
                return self::PREFIX . 'premium';
            default:
                return self::PREFIX . 'global';
        }
    }
    
    /**
     * Calculer les points selon la difficulté du défi
     */
    private function calculatePoints($difficulty)
    {
        return match($difficulty) {
            'facile' => 10,
            'moyen' => 25,
            'difficile' => 50,
            default => 10
        };
    }
    
    /**
     * Réinitialiser un classement spécifique
     */
    public function resetLeaderboard($type = 'global', $param = null)
    {
        $key = $this->getLeaderboardKey($type, $param);
        Redis::del($key);
        return true;
    }
}