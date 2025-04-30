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
}
