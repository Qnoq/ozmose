<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use App\Models\Category;

class LeaderboardController extends Controller
{
    protected $leaderboardService;
    
    public function __construct(LeaderboardService $leaderboardService)
    {
        $this->leaderboardService = $leaderboardService;
    }
    
    /**
     * Récupérer le classement global
     */
    public function global(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $leaderboard = $this->leaderboardService->getLeaderboard('global', null, $limit);
        $stats = $this->leaderboardService->getLeaderboardStats('global');
        
        // Ajouter le rang de l'utilisateur connecté s'il est authentifié
        $userRank = null;
        if (auth()->check()) {
            $userRank = $this->leaderboardService->getUserRank(auth()->id(), 'global');
        }
        
        return response()->json([
            'leaderboard' => $leaderboard,
            'stats' => $stats,
            'user_rank' => $userRank
        ]);
    }
    
    /**
     * Récupérer le classement par catégorie
     */
    public function category(Request $request, Category $category)
    {
        info('category leaderboard');
        $limit = $request->get('limit', 10);
        
        $leaderboard = $this->leaderboardService->getLeaderboard('category', $category->id, $limit);
        $stats = $this->leaderboardService->getLeaderboardStats('category', $category->id);
        
        // Ajouter le rang de l'utilisateur connecté s'il est authentifié
        $userRank = null;
        if (auth()->check()) {
            $userRank = $this->leaderboardService->getUserRank(auth()->id(), 'category', $category->id);
        }
        
        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'icon' => $category->icon,
            ],
            'leaderboard' => $leaderboard,
            'stats' => $stats,
            'user_rank' => $userRank
        ]);
    }
    
    /**
     * Récupérer le classement hebdomadaire
     */
    public function weekly(Request $request)
    {
        $limit = $request->get('limit', 10);
        $week = $request->get('week', date('YW')); // Format: YYYYWW (année + numéro de semaine)
        
        $leaderboard = $this->leaderboardService->getLeaderboard('weekly', $week, $limit);
        $stats = $this->leaderboardService->getLeaderboardStats('weekly', $week);
        
        // Ajouter le rang de l'utilisateur connecté s'il est authentifié
        $userRank = null;
        if (auth()->check()) {
            $userRank = $this->leaderboardService->getUserRank(auth()->id(), 'weekly', $week);
        }
        
        return response()->json([
            'week' => $week,
            'leaderboard' => $leaderboard,
            'stats' => $stats,
            'user_rank' => $userRank
        ]);
    }
    
    /**
     * Récupérer le classement mensuel
     */
    public function monthly(Request $request)
    {
        $limit = $request->get('limit', 10);
        $month = $request->get('month', date('Ym')); // Format: YYYYMM
        
        $leaderboard = $this->leaderboardService->getLeaderboard('monthly', $month, $limit);
        $stats = $this->leaderboardService->getLeaderboardStats('monthly', $month);
        
        // Ajouter le rang de l'utilisateur connecté s'il est authentifié
        $userRank = null;
        if (auth()->check()) {
            $userRank = $this->leaderboardService->getUserRank(auth()->id(), 'monthly', $month);
        }
        
        return response()->json([
            'month' => $month,
            'leaderboard' => $leaderboard,
            'stats' => $stats,
            'user_rank' => $userRank
        ]);
    }
    
    /**
     * Récupérer le classement premium
     */
    public function premium(Request $request)
    {
        // Vérifier si l'utilisateur est premium
        if (!auth()->check() || !auth()->user()->isPremium()) {
            return response()->json([
                'message' => 'Fonctionnalité réservée aux utilisateurs premium',
                'premium_info' => [
                    'can_upgrade' => true
                ]
            ], 403);
        }
        
        $limit = $request->get('limit', 10);
        
        $leaderboard = $this->leaderboardService->getLeaderboard('premium', null, $limit);
        $stats = $this->leaderboardService->getLeaderboardStats('premium');
        $userRank = $this->leaderboardService->getUserRank(auth()->id(), 'premium');
        
        return response()->json([
            'leaderboard' => $leaderboard,
            'stats' => $stats,
            'user_rank' => $userRank
        ]);
    }
}