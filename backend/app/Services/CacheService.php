<?php

namespace App\Services;

use App\Models\User;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\TruthOrDareSession;
use App\Models\TruthOrDareQuestion;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\ChallengeParticipation;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ChallengeResource;

class CacheService
{
    /**
     * Les différentes durées de cache (en minutes)
     */
    const TTL_SHORT = 5;        // 5 minutes - pour les données très dynamiques
    const TTL_MEDIUM = 60;      // 1 heure - pour les données semi-dynamiques
    const TTL_LONG = 1440;      // 24 heures - pour les données qui changent rarement
    const TTL_VERY_LONG = 10080; // 7 jours - pour les données presque statiques

    /**
     * Préfixe pour toutes les clés de cache
     */
    const PREFIX = 'ozmose:';

    /**
     * Récupère toutes les catégories (données qui changent rarement)
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllCategories()
    {
        return Cache::remember(self::PREFIX . 'categories:all', self::TTL_LONG, function () {
            return CategoryResource::collection(Category::all());
        });
    }

    /**
     * Récupère les catégories avec le comptage des défis pour l'utilisateur actuel
     *
     * @param int $userId ID de l'utilisateur
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getCategoriesWithChallengeCount($userId)
    {
        return Cache::remember(self::PREFIX . "users:{$userId}:categories_count", self::TTL_MEDIUM, function () use ($userId) {
            return Category::withCount([
                'challenges' => function ($query) use ($userId) {
                    $query->where(function ($q) use ($userId) {
                        $q->where('is_public', true)
                          ->orWhere('creator_id', $userId);
                    });
                }
            ])->get();
        });
    }

    /**
     * Récupère les défis populaires
     *
     * @param int $limit Nombre de défis à récupérer
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getPopularChallenges($limit = 5)
    {
        return Cache::remember(self::PREFIX . "challenges:popular:{$limit}", self::TTL_MEDIUM, function () use ($limit) {
            return ChallengeResource::collection(
                Challenge::where('is_public', true)
                    ->withCount('participations')
                    ->orderByDesc('participations_count')
                    ->take($limit)
                    ->with(['creator:id,name', 'category:id,name'])
                    ->get()
            );
        });
    }

    /**
     * Récupère les défis récents
     *
     * @param int $limit Nombre de défis à récupérer
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getRecentChallenges($limit = 5)
    {
        return Cache::remember(self::PREFIX . "challenges:recent:{$limit}", self::TTL_SHORT, function () use ($limit) {
            return ChallengeResource::collection(
                Challenge::where('is_public', true)
                    ->orderByDesc('created_at')
                    ->take($limit)
                    ->with(['creator:id,name', 'category:id,name'])
                    ->get()
            );
        });
    }

    /**
     * Récupère les défis actifs d'un utilisateur
     *
     * @param int $userId ID de l'utilisateur
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getUserActiveChallenges($userId)
    {
        return Cache::remember(self::PREFIX . "users:{$userId}:active_challenges", self::TTL_SHORT, function () use ($userId) {
            $participations = ChallengeParticipation::where('user_id', $userId)
                ->where('status', 'accepted')
                ->whereNull('completed_at')
                ->whereNull('abandoned_at')
                ->with(['challenge.creator', 'challenge.category'])
                ->get();

            return $participations;
        });
    }

    /**
     * Récupère les statistiques de participation d'un utilisateur
     *
     * @param int $userId ID de l'utilisateur
     * @return array
     */
    public function getUserStats($userId)
    {
        return Cache::remember(self::PREFIX . "users:{$userId}:stats", self::TTL_MEDIUM, function () use ($userId) {
            // Nombre total de défis
            $totalCount = ChallengeParticipation::where('user_id', $userId)->count();
            
            // Défis complétés
            $completedCount = ChallengeParticipation::where('user_id', $userId)
                ->where('status', 'completed')
                ->count();
            
            // Défis actifs
            $activeCount = ChallengeParticipation::where('user_id', $userId)
                ->where('status', 'accepted')
                ->whereNull('completed_at')
                ->whereNull('abandoned_at')
                ->count();
            
            // Défis abandonnés
            $abandonedCount = ChallengeParticipation::where('user_id', $userId)
                ->where('status', 'abandoned')
                ->count();
            
            // Invitations en attente
            $pendingCount = ChallengeParticipation::where('user_id', $userId)
                ->where('status', 'invited')
                ->count();
            
            // Taux de complétion
            $completionRate = $totalCount > 0 ? 
                round(($completedCount / ($completedCount + $abandonedCount)) * 100, 1) : 0;
            
            return [
                'total_challenges' => $totalCount,
                'completed_challenges' => $completedCount,
                'active_challenges' => $activeCount,
                'abandoned_challenges' => $abandonedCount,
                'pending_invitations' => $pendingCount,
                'completion_rate' => $completionRate
            ];
        });
    }

    /**
     * Récupère les statistiques par catégorie pour un utilisateur
     *
     * @param int $userId ID de l'utilisateur
     * @return array
     */
    public function getUserCategoryStats($userId)
    {
        return Cache::remember(self::PREFIX . "users:{$userId}:category_stats", self::TTL_MEDIUM, function () use ($userId) {
            return ChallengeParticipation::where('user_id', $userId)
                ->where('status', 'completed')
                ->join('challenges', 'challenge_participations.challenge_id', '=', 'challenges.id')
                ->join('categories', 'challenges.category_id', '=', 'categories.id')
                ->select('categories.name', \DB::raw('count(*) as count'))
                ->groupBy('categories.name')
                ->get();
        });
    }

    /**
     * Récupère les amis d'un utilisateur
     *
     * @param int $userId ID de l'utilisateur
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserFriends($userId)
    {
        return Cache::remember(self::PREFIX . "users:{$userId}:friends", self::TTL_MEDIUM, function () use ($userId) {
            $user = User::find($userId);
            return $user->friends();
        });
    }

    /**
     * Récupère un défi spécifique avec toutes ses relations
     *
     * @param int $challengeId ID du défi
     * @return Challenge
     */
    public function getChallenge($challengeId)
    {
        return Cache::remember(self::PREFIX . "challenges:{$challengeId}", self::TTL_SHORT, function () use ($challengeId) {
            return Challenge::with(['creator', 'category', 'participations.user'])
                ->findOrFail($challengeId);
        });
    }

    /**
     * Efface le cache lié aux catégories
     */
    public function clearCategoriesCache()
    {
        Cache::forget(self::PREFIX . 'categories:all');
        
        // On pourrait aussi effacer les caches par utilisateur si nécessaire
    }

    /**
     * Efface le cache lié à un défi spécifique
     *
     * @param int $challengeId ID du défi
     */
    public function clearChallengeCache($challengeId)
    {
        Cache::forget(self::PREFIX . "challenges:{$challengeId}");
        
        // Effacer aussi les listes qui pourraient contenir ce défi
        Cache::forget(self::PREFIX . "challenges:popular:5");
        Cache::forget(self::PREFIX . "challenges:recent:5");
    }

    /**
     * Efface le cache lié à un utilisateur spécifique
     *
     * @param int $userId ID de l'utilisateur
     */
    public function clearUserCache($userId)
    {
        Cache::forget(self::PREFIX . "users:{$userId}:stats");
        Cache::forget(self::PREFIX . "users:{$userId}:category_stats");
        Cache::forget(self::PREFIX . "users:{$userId}:active_challenges");
        Cache::forget(self::PREFIX . "users:{$userId}:friends");
        Cache::forget(self::PREFIX . "users:{$userId}:categories_count");
    }

    /**
     * Vérifie l'état du cache Redis
     *
     * @return array
     */
    public function getStatus()
    {
        try {
            $redis = Cache::getRedis();
            $info = $redis->info();
            
            // Récupérer les clés avec le préfixe Ozmose
            $keys = $redis->keys(self::PREFIX . '*');
            
            // Regrouper les clés par type
            $categoriesKeys = [];
            $challengesKeys = [];
            $usersKeys = [];
            $otherKeys = [];
            
            foreach ($keys as $key) {
                if (strpos($key, self::PREFIX . 'categories:') === 0) {
                    $categoriesKeys[] = $key;
                } elseif (strpos($key, self::PREFIX . 'challenges:') === 0) {
                    $challengesKeys[] = $key;
                } elseif (strpos($key, self::PREFIX . 'users:') === 0) {
                    $usersKeys[] = $key;
                } else {
                    $otherKeys[] = $key;
                }
            }
            
            return [
                'connected' => true,
                'total_keys' => count($keys),
                'categories_keys' => count($categoriesKeys),
                'challenges_keys' => count($challengesKeys),
                'users_keys' => count($usersKeys),
                'other_keys' => count($otherKeys),
                'memory_used' => isset($info['used_memory_human']) ? $info['used_memory_human'] : 'N/A',
                'uptime' => isset($info['uptime_in_seconds']) ? $this->formatUptime($info['uptime_in_seconds']) : 'N/A',
                'version' => isset($info['redis_version']) ? $info['redis_version'] : 'N/A'
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Formater la durée d'activité en jours, heures, minutes
     *
     * @param int $seconds
     * @return string
     */
    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $uptime = '';
        if ($days > 0) {
            $uptime .= $days . ' jour' . ($days > 1 ? 's' : '') . ', ';
        }
        
        $uptime .= $hours . ' heure' . ($hours > 1 ? 's' : '') . ', ';
        $uptime .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        
        return $uptime;
    }

    /**
     * Test si le cache Redis est opérationnel
     *
     * @return bool
     */
    public function testConnection()
    {
        try {
            $testKey = self::PREFIX . 'test:' . uniqid();
            Cache::put($testKey, 'test', 5);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            
            return $value === 'test';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère les questions populaires pour Action ou Vérité
     */
    public function getPopularTruthOrDareQuestions($limit = 10)
    {
        return Cache::remember(self::PREFIX . "truth-or-dare:questions:popular:{$limit}", self::TTL_MEDIUM, function () use ($limit) {
            return TruthOrDareQuestion::where('is_official', true)
                ->orWhere('is_public', true)
                ->orderByDesc('times_used')
                ->orderByDesc('rating')
                ->take($limit)
                ->get();
        });
    }

    /**
     * Récupère les sessions actives pour le dashboard
     */
    public function getActiveTruthOrDareSessions()
    {
        return Cache::remember(self::PREFIX . "truth-or-dare:sessions:active", self::TTL_SHORT, function () {
            return TruthOrDareSession::where('is_active', true)
                ->withCount('participants')
                ->orderByDesc('created_at')
                ->take(10)
                ->get();
        });
    }

    /**
     * Efface le cache lié à Action ou Vérité
     */
    public function clearTruthOrDareCache($type = 'all')
    {
        $prefix = self::PREFIX . "truth-or-dare:";
        
        switch ($type) {
            case 'questions':
                Cache::forget($prefix . 'questions:popular:10');
                break;
            case 'sessions':
                Cache::forget($prefix . 'sessions:active');
                break;
            case 'all':
                $keys = Redis::keys($prefix . '*');
                if (!empty($keys)) {
                    Redis::del($keys);
                }
                break;
        }
    }
}