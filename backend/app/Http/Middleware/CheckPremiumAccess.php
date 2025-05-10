<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPremiumAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->isPremium()) {
            return response()->json([
                'message' => 'Cette fonctionnalité est réservée aux membres premium',
                'premium_info' => [
                    'can_upgrade' => true,
                    'plans' => $this->getAvailablePlans()
                ]
            ], 403);
        }

        return $next($request);
    }

    /**
     * Retourne la liste des plans disponibles
     * 
     * @return array
     */
    private function getAvailablePlans()
    {
        return [
            [
                'id' => 'free',
                'name' => 'Ozmose Gratuit',
                'price' => 0,
                'currency' => 'EUR',
                'interval' => null,
                'features' => [
                    'Stockage 2 Go',
                    'Vidéos jusqu\'à 1 minute',
                    '3 groupes de défis maximum',
                    'Fonctionnalités de base'
                ]
            ],
            [
                'id' => 'premium',
                'name' => 'Ozmose Premium',
                'price' => 4.99,
                'currency' => 'EUR',
                'interval' => 'month',
                'features' => [
                    'Stockage 20 Go',
                    'Vidéos jusqu\'à 5 minutes en qualité HD',
                    'Groupes illimités',
                    'Mode compétition avancé',
                    'Défis multi-étapes',
                    'Défis programmables',
                    'Statistiques avancées',
                    'Badges exclusifs',
                    'Mode "Secret" pour couples'
                ]
            ],
            [
                'id' => 'annual_premium',
                'name' => 'Ozmose Premium Annuel',
                'price' => 49.99,
                'currency' => 'EUR',
                'interval' => 'year',
                'savings' => '17%',
                'features' => [
                    'Tous les avantages Premium',
                    '2 mois gratuits',
                    'Badge "Premium Annuel" exclusif'
                ]
            ]
        ];
    }
}
