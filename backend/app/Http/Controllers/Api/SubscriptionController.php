<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function getPricingPlans()
    {
        $plans = [
            [
                'id' => 'basic',
                'name' => 'Ozmose Basic',
                'price' => 2.99,
                'currency' => 'EUR',
                'interval' => 'month',
                'features' => [
                    'Stockage 5 Go',
                    'Vidéos jusqu\'à 2 minutes',
                    '5 groupes de défis maximum',
                    'Mode compétition limité'
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
        
        return response()->json(['plans' => $plans]);
    }
    
    public function subscribe(Request $request)
    {
        info('Requête reçue', $request->all());
        $request->validate([
            'plan_id' => 'required|string',
            'payment_method_id' => 'required|string'
        ]);
        
        $user = $request->user();
        $planId = $request->plan_id;
        info('planId juste avant Subscription::create', ['planId' => $planId]);
        try {
            // Logique Stripe...
            // 1. Récupérer les informations du client
            // 2. Créer ou mettre à jour l'abonnement
            // 3. Mettre à jour les informations utilisateur
            
            $now = now();
            $premiumUntil = $user->premium_until;

            // On choisit la date de base : la plus grande entre maintenant et la date actuelle d'expiration
            $baseDate = $premiumUntil && $premiumUntil->gt($now) ? $premiumUntil : $now;

            // On ajoute la durée selon le plan
            if ($planId === 'annual_premium') {
                $newPremiumUntil = $baseDate->copy()->addYear();
            } else {
                $newPremiumUntil = $baseDate->copy()->addMonth();
            }

            // Mise à jour de l'utilisateur
            $user->update([
                'is_premium' => true,
                'premium_until' => $newPremiumUntil,
                'subscription_plan' => $planId,
                'subscription_status' => 'active'
            ]);
            
            // Créer l'enregistrement d'abonnement
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'stripe_id' => 'stripe_sub_123', // ID réel de Stripe
                'status' => 'active',
                'plan_id' => $planId,
                'amount' => $planId === 'premium' ? 4.99 : 49.99,
                'currency' => 'EUR',
                'interval' => $planId === 'annual_premium' ? 'year' : 'month',
                'ends_at' => $newPremiumUntil
            ]);
            
            return response()->json([
                'message' => 'Abonnement activé avec succès',
                'subscription' => $subscription,
                'user' => [
                    'is_premium' => $user->is_premium,
                    'premium_until' => $user->premium_until,
                    'subscription_plan' => $user->subscription_plan
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'activation de l\'abonnement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function cancel(Request $request)
    {
        $user = $request->user();
        
        try {
            // Logique Stripe pour annuler l'abonnement...
            
            // L'utilisateur garde son statut premium jusqu'à la date d'expiration
            $subscription = $user->subscription;
            
            if ($subscription) {
                $subscription->update([
                    'status' => 'canceled',
                ]);
            }
            
            return response()->json([
                'message' => 'Abonnement annulé avec succès',
                'premium_until' => $user->premium_until
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'annulation de l\'abonnement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function resumeSubscription(Request $request)
    {
        $user = $request->user();

        // On récupère le dernier abonnement annulé de l'utilisateur
        $subscription = $user->subscriptions()
            ->where('status', 'canceled')
            ->latest('ends_at')
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'Aucun abonnement annulé à reprendre.'
            ], 404);
        }

        // On prolonge la date de fin à partir de maintenant ou de la date d'expiration actuelle
        $now = now();
        $baseDate = $user->premium_until && $user->premium_until->gt($now) ? $user->premium_until : $now;
        $newPremiumUntil = $subscription->interval === 'year'
            ? $baseDate->copy()->addYear()
            : $baseDate->copy()->addMonth();

        // Mise à jour de l'abonnement
        $subscription->update([
            'status' => 'active',
            'ends_at' => $newPremiumUntil,
        ]);

        // Mise à jour de l'utilisateur
        $user->update([
            'is_premium' => true,
            'premium_until' => $newPremiumUntil,
            'subscription_plan' => $subscription->plan_id,
            'subscription_status' => 'active'
        ]);

        return response()->json([
            'message' => 'Abonnement repris avec succès.',
            'subscription' => $subscription,
            'user' => [
                'is_premium' => $user->is_premium,
                'premium_until' => $user->premium_until,
                'subscription_plan' => $user->subscription_plan
            ]
        ]);
    }
    
    public function getSubscriptionStatus(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'is_premium' => $user->isPremium(),
            'subscription' => $user->subscription,
            'premium_until' => $user->premium_until,
            'days_remaining' => $user->getRemainingDays(),
            'premium_level' => $user->getPremiumLevel()
        ]);
    }
}
