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
    protected $cachedLimits = [];

    // Méthode dédiée pour générer des clés cohérentes
    protected function generateCacheKey($identifier, $type)
    {
        // Préfixer toutes les clés pour faciliter le monitoring et le nettoyage
        return "ozmose:ratelimit:{$type}:{$identifier}";
    }

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
        // Mesurer le temps avant le rate limiting
        $startTime = microtime(true);
        
        static $lastCheckedTime = [];
        static $requestsCount = [];
        
        $user = $request->user();
        $identifier = $user ? $user->id : $request->ip();
        $key = "{$identifier}:{$type}";
        
        // Vérification en mémoire locale pour éviter des appels Redis répétés
        $currentTime = time();
        if (isset($lastCheckedTime[$key]) && $currentTime - $lastCheckedTime[$key] < 1) {
            $requestsCount[$key] = ($requestsCount[$key] ?? 0) + 1;
            
            // Si le nombre est bien en-dessous de la limite, éviter la vérification Redis
            if ($requestsCount[$key] < 5) { // Beaucoup moins que la limite la plus basse
                return $next($request);
            }
        }
        
        // Reset des compteurs en mémoire
        $lastCheckedTime[$key] = $currentTime;
        $requestsCount[$key] = 1;
        
        // Court-circuit pour les requêtes internes ou les administrateurs
        if ($request->is('api/internal/*') || ($user && $this->isAdmin($user))) {
            return $next($request);
        }
        
        // Si l'utilisateur n'est pas connecté, utiliser l'IP comme identifiant
        if (!$user) {
            return $this->handleGuestRateLimit($request, $next, $type);
        }
        
        // Déterminer le type d'utilisateur (gratuit ou premium)
        $userType = $this->isUserPremium($user) ? 'premium' : 'free';
        
        // Obtenir les limites appropriées
        $limit = $this->getLimits($userType, $type);
        
        // Créer une clé unique pour ce limiteur
        $key = $this->generateCacheKey($user->id, $type);
        
        // Vérifier si la limite est atteinte
        if ($this->isRateLimited($key, $limit['attempts'], $limit['decay'])) {
            $response = $this->buildTooManyRequestsResponse($userType, $type, $this->getRetryAfter($key));
            
            // Mesurer le temps après le rate limiting
            $this->logPerformanceMetrics($startTime, $type, $user, $request);
            
            return $response;
        }
        
        // Mesurer le temps après le rate limiting
        $this->logPerformanceMetrics($startTime, $type, $user, $request);
        
        return $next($request);
    }

    /**
     * Vérifie si un utilisateur est administrateur
     */
    protected function isAdmin($user)
    {
        static $cachedAdminStatus = [];
        
        if (!isset($cachedAdminStatus[$user->id])) {
            // À adapter selon votre logique d'administration
            $cachedAdminStatus[$user->id] = $user->is_admin ?? $user->id === 1;
        }
        
        return $cachedAdminStatus[$user->id];
    }

    /**
     * Enregistre les métriques de performance du rate limiting
     */
    protected function logPerformanceMetrics($startTime, $type, $user, $request)
    {
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // en millisecondes
        
        // Stocker les métriques (log ou Redis) seulement si l'opération est lente
        if ($duration > 5) {
            \Illuminate\Support\Facades\Log::channel('performance')->info('Rate limiting slow operation', [
                'duration' => $duration,
                'type' => $type,
                'user_id' => $user ? $user->id : 'guest',
                'ip' => $request->ip(),
                'endpoint' => $request->path()
            ]);
            
            // Stocker dans Redis pour les statistiques d'agrégation
            $date = date('Y-m-d');
            $hour = date('H');
            $statsKey = "ozmose:stats:ratelimit:{$date}:{$hour}";
            
            Redis::hincrby($statsKey, 'count', 1);
            Redis::hincrby($statsKey, 'total_duration', $duration);
            Redis::hincrby($statsKey, 'slow_operations', $duration > 5 ? 1 : 0);
            
            // Définir l'expiration de la clé de statistiques (48h)
            Redis::expire($statsKey, 172800);
        }
    }

    // Ajouter un mécanisme de mise en cache des statuts premium
    protected function isUserPremium($user)
    {
        static $cachedStatus = [];
        
        if (!$user) {
            return false;
        }
        
        if (!isset($cachedStatus[$user->id])) {
            // Utiliser une propriété simple plutôt qu'une méthode pour les performances
            $cachedStatus[$user->id] = $user->is_premium && $user->premium_until && $user->premium_until->isFuture();
        }
        
        return $cachedStatus[$user->id];
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
    // Méthode pour récupérer les limites avec mise en cache
    protected function getLimits($userType, $type)
    {
        $cacheKey = "{$userType}:{$type}";
        
        if (!isset($this->cachedLimits[$cacheKey])) {
            $this->cachedLimits[$cacheKey] = $this->limits[$userType][$type] ?? $this->limits[$userType]['default'];
        }
        
        return $this->cachedLimits[$cacheKey];
    }
    
    /**
     * Vérifie si la requête dépasse la limite
     */
    protected function isRateLimited($key, $maxAttempts, $decayMinutes)
    {
        $redis = Redis::connection()->client();
        
        // Utiliser un pipeline Redis pour réduire les allers-retours
        $results = $redis->pipeline(function($pipe) use ($key, $decayMinutes) {
            $pipe->incr($key);
            $pipe->expire($key, $decayMinutes * 60);
        });
        
        $currentAttempts = $results[0];
        
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