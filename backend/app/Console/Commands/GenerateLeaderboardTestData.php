<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaderboardService;
use App\Models\User;
use App\Models\Category;

class GenerateLeaderboardTestData extends Command
{
    protected $signature = 'ozmose:generate-leaderboard-data {users=10}';
    protected $description = 'Génère des données de test pour les classements';

    protected $leaderboardService;
    
    public function __construct(LeaderboardService $leaderboardService)
    {
        parent::__construct();
        $this->leaderboardService = $leaderboardService;
    }

    public function handle()
    {
        $userCount = (int)$this->argument('users');
        
        $this->info("Génération de données de classement pour {$userCount} utilisateurs...");
        
        // Récupérer les utilisateurs
        $users = User::take($userCount)->get();
        
        if ($users->count() === 0) {
            $this->error('Aucun utilisateur trouvé. Veuillez créer des utilisateurs d\'abord.');
            return 1;
        }
        
        // Récupérer les catégories
        $categories = Category::all();
        
        if ($categories->count() === 0) {
            $this->error('Aucune catégorie trouvée. Veuillez créer des catégories d\'abord.');
            return 1;
        }
        
        $this->info("Réinitialisation des classements...");
        
        // Réinitialiser les classements
        $this->leaderboardService->resetLeaderboard('global');
        $this->leaderboardService->resetLeaderboard('weekly');
        $this->leaderboardService->resetLeaderboard('monthly');
        $this->leaderboardService->resetLeaderboard('premium');
        
        foreach ($categories as $category) {
            $this->leaderboardService->resetLeaderboard('category', $category->id);
        }
        
        $this->info("Génération de scores aléatoires...");
        
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();
        
        foreach ($users as $user) {
            // Points aléatoires pour le classement global
            $globalPoints = rand(50, 1000);
            $this->leaderboardService->addPointsToGlobal($user->id, $globalPoints);
            
            // Points aléatoires pour le classement hebdomadaire
            $weeklyPoints = rand(5, 200);
            $this->leaderboardService->addPointsToWeekly($user->id, $weeklyPoints);
            
            // Points aléatoires pour le classement mensuel
            $monthlyPoints = rand(20, 500);
            $this->leaderboardService->addPointsToMonthly($user->id, $monthlyPoints);
            
            // Points aléatoires pour chaque catégorie
            foreach ($categories as $category) {
                $categoryPoints = rand(10, 300);
                $this->leaderboardService->addPointsToCategory($user->id, $category->id, $categoryPoints);
            }
            
            // Si l'utilisateur est premium, ajouter au classement premium
            if ($user->isPremium()) {
                $premiumPoints = rand(50, 800);
                $this->leaderboardService->addPointsToPremiumBoard($user->id, $premiumPoints);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Données de classement générées avec succès !");
        
        // Afficher un exemple de classement global
        $this->info("Top 5 du classement global :");
        $topGlobal = $this->leaderboardService->getLeaderboard('global', null, 5);
        
        $this->table(
            ['Position', 'Utilisateur', 'Score'], 
            array_map(function ($item) {
                return [
                    $item['position'],
                    $item['name'],
                    $item['score']
                ];
            }, $topGlobal)
        );
        
        return 0;
    }
}