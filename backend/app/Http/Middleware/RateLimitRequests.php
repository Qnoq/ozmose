<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRequests
{
    /**
     * Les différentes limites de requêtes par type d'utilisateur
     */
    protected $limits = [
        'free' => [
            'default' => ['attempts' => 60, 'decay' => 60], // 60 requêtes par minute
            'api' => ['attempts' => 300, 'decay' => 300],   // 300 requêtes par 5 minutes
            'media' => ['attempts' => 10, 'decay' => 60],   // 10 uploads média par minute
            'social' => ['attempts' => 20, 'decay' => 60],  // 20 requêtes sociales par minute
        ],
        'premium' => [
            'default' => ['attempts' => 120, 'decay' => 60], // 120 requêtes par minute
            'api' => ['attempts' => 600, 'decay' => 300],    // 600 requêtes par 5 minutes
            'media' => ['attempts' => 30, 'decay' => 60],    // 30 uploads média par minute
            'social' => ['attempts' => 50, 'decay' => 60],   // 50 requêtes sociales par minute
        ],
    ];
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $type
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $type = 'default')
    {
        $user = $request->user();
        
        // Si l'utilisateur n'est pas connecté, utiliser l'IP comme identifiant
        if (!$user) {
            return $this->handleGuestRateLimit($request, $next, $type);
        }
        
        // Déterminer le type d'utilisateur (gratuit ou premium)
        $userType = $user->isPremium() ? 'premium' : 'free';
        
        // Obtenir les limites appropriées
        $limit = $this->getLimits($userType, $type);
        
        // Créer une clé unique pour ce limiteur
        $key = "ratelimit:{$type}:user:{$user->id}";
        
        // Vérifier si la limite est atteinte
        if ($this->isRateLimited($key, $limit['attempts'], $limit['decay'])) {
            return $this->buildTooManyRequestsResponse($userType, $type, $this->getRetryAfter($key));
        }
        
        return $next($request);
    }
    
    /**
     * Gère le rate limiting pour les invités (non connectés)
     */
    protected function handleGuestRateLimit(Request $request, Closure $next, $type)
    {
        // Utiliser des limites plus restrictives pour les invités
        $limit = [
            'attempts' => 30,  // 30 requêtes par minute
            'decay' => 60
        ];
        
        $ip = $request->ip();
        $key = "ratelimit:{$type}:ip:{$ip}";
        
        if ($this->isRateLimited($key, $limit['attempts'], $limit['decay'])) {
            return $this->buildTooManyRequestsResponse('guest', $type, $this->getRetryAfter($key));
        }
        
        return $next($request);
    }
    
    /**
     * Récupère les limites appropriées selon le type d'utilisateur et le type de requête
     */
    protected function getLimits($userType, $type)
    {
        return $this->limits[$userType][$type] ?? $this->limits[$userType]['default'];
    }
    
    /**
     * Vérifie si la requête dépasse la limite
     */
    protected function isRateLimited($key, $maxAttempts, $decayMinutes)
    {
        // Incrémenter le compteur
        $currentAttempts = Redis::incr($key);
        
        // Définir l'expiration si c'est la première requête
        if ($currentAttempts === 1) {
            Redis::expire($key, $decayMinutes * 60);
        }
        
        // Vérifier si la limite est dépassée
        return $currentAttempts > $maxAttempts;
    }
    
    /**
     * Récupère le temps restant avant que les requêtes soient à nouveau autorisées
     */
    protected function getRetryAfter($key)
    {
        return Redis::ttl($key);
    }
    
    /**
     * Construit la réponse d'erreur pour trop de requêtes
     */
    protected function buildTooManyRequestsResponse($userType, $type, $retryAfter)
    {
        $response = [
            'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
            'error' => 'too_many_requests',
            'retry_after' => $retryAfter
        ];
        
        // Ajouter des informations sur l'upgrade si l'utilisateur est gratuit
        if ($userType === 'free') {
            $response['premium_info'] = [
                'can_upgrade' => true,
                'message' => 'Les utilisateurs premium bénéficient de limites plus élevées.',
                'limits_comparison' => [
                    'free' => $this->limits['free'][$type] ?? $this->limits['free']['default'],
                    'premium' => $this->limits['premium'][$type] ?? $this->limits['premium']['default']
                ]
            ];
        }
        
        return response()->json($response, 429)
            ->header('Retry-After', $retryAfter);
    }
}