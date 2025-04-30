<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheActiveUser
{
    protected $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            // Enregistrer l'utilisateur dans le service de cache
            $this->registerActiveUser(auth()->id());
        }

        return $next($request);
    }

    /**
     * Enregistre un utilisateur actif pour la gestion du cache
     */
    protected function registerActiveUser($userId)
    {
        $cacheKey = 'ozmose:active_users';
        $activeUsers = Cache::get($cacheKey, []);
        
        if (!in_array($userId, $activeUsers)) {
            $activeUsers[] = $userId;
            Cache::put($cacheKey, $activeUsers, now()->addDays(7));
        }
    }
}