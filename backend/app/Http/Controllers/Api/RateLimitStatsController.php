<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RateLimitStatsController extends Controller
{
    /**
     * Récupère les statistiques de rate limiting
     */
    public function getStats(Request $request)
    {
        // Vérifier que l'utilisateur est administrateur
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        $period = $request->get('period', 'today');
        $stats = [];
        
        switch ($period) {
            case 'today':
                $date = date('Y-m-d');
                $stats = $this->getStatsForDate($date);
                break;
            case 'yesterday':
                $date = date('Y-m-d', strtotime('-1 day'));
                $stats = $this->getStatsForDate($date);
                break;
            case 'week':
                $stats = $this->getStatsForLastDays(7);
                break;
            case 'month':
                $stats = $this->getStatsForLastDays(30);
                break;
            default:
                return response()->json(['message' => 'Période non valide'], 400);
        }
        
        return response()->json([
            'period' => $period,
            'stats' => $stats
        ]);
    }
    
    /**
     * Récupère les statistiques pour une date spécifique
     */
    private function getStatsForDate($date)
    {
        $hourlyStats = [];
        
        for ($h = 0; $h < 24; $h++) {
            $hour = str_pad($h, 2, '0', STR_PAD_LEFT);
            $statsKey = "ozmose:stats:ratelimit:{$date}:{$hour}";
            
            $rawStats = Redis::hgetall($statsKey);
            
            if (empty($rawStats)) {
                continue;
            }
            
            $count = (int)($rawStats['count'] ?? 0);
            $totalDuration = (float)($rawStats['total_duration'] ?? 0);
            $slowOperations = (int)($rawStats['slow_operations'] ?? 0);
            
            $hourlyStats[$hour] = [
                'requests_count' => $count,
                'avg_duration' => $count > 0 ? round($totalDuration / $count, 2) : 0,
                'slow_operations' => $slowOperations,
                'slow_percentage' => $count > 0 ? round(($slowOperations / $count) * 100, 2) : 0
            ];
        }
        
        return [
            'date' => $date,
            'hourly' => $hourlyStats,
            'summary' => $this->calculateSummary($hourlyStats)
        ];
    }
    
    /**
     * Récupère les statistiques pour les derniers jours
     */
    private function getStatsForLastDays($days)
    {
        $dailyStats = [];
        
        for ($d = 0; $d < $days; $d++) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $stats = $this->getStatsForDate($date);
            
            if (!empty($stats['hourly'])) {
                $dailyStats[$date] = $stats['summary'];
            }
        }
        
        return [
            'days' => $days,
            'daily' => $dailyStats,
            'summary' => $this->calculateSummary(array_values($dailyStats))
        ];
    }
    
    /**
     * Calcule le résumé des statistiques
     */
    private function calculateSummary($stats)
    {
        $totalRequests = 0;
        $totalDuration = 0;
        $totalSlowOps = 0;
        
        foreach ($stats as $stat) {
            $totalRequests += $stat['requests_count'] ?? 0;
            $totalDuration += ($stat['avg_duration'] ?? 0) * ($stat['requests_count'] ?? 0);
            $totalSlowOps += $stat['slow_operations'] ?? 0;
        }
        
        return [
            'total_requests' => $totalRequests,
            'avg_duration' => $totalRequests > 0 ? round($totalDuration / $totalRequests, 2) : 0,
            'total_slow_operations' => $totalSlowOps,
            'slow_percentage' => $totalRequests > 0 ? round(($totalSlowOps / $totalRequests) * 100, 2) : 0
        ];
    }
}