<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminCacheController extends Controller
{
    protected $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        
        // Limiter l'accès aux administrateurs seulement
        $this->middleware(function ($request, $next) {
            // Vous devrez adapter cette vérification selon votre logique d'administration
            // Exemple simple : vérifier un rôle 'admin' ou un ID spécifique
            if (!auth()->check() || auth()->id() != 1) { // ID 1 généralement pour l'admin
                return response()->json(['message' => 'Accès non autorisé'], 403);
            }
            
            return $next($request);
        });
    }

    /**
     * Affiche le statut du cache Redis
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $status = $this->cacheService->getStatus();
        
        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Teste la connexion au cache Redis
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function test()
    {
        $result = $this->cacheService->testConnection();
        
        return response()->json([
            'connected' => $result,
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Liste les clés du cache correspondant à un motif
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function keys(Request $request)
    {
        $pattern = $request->get('pattern', 'ozmose:*');
        
        try {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            sort($keys);
            
            $keyDetails = [];
            foreach ($keys as $key) {
                $type = $redis->type($key);
                $ttl = $redis->ttl($key);
                
                $keyDetails[] = [
                    'key' => $key,
                    'type' => $type,
                    'ttl' => $ttl,
                    'ttl_formatted' => $ttl < 0 ? 'No Expiration' : gmdate('H:i:s', $ttl)
                ];
            }
            
            return response()->json([
                'pattern' => $pattern,
                'count' => count($keys),
                'keys' => $keyDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la récupération des clés',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vide une partie spécifique du cache
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear(Request $request)
    {
        $type = $request->get('type', 'all');
        
        $validTypes = ['all', 'categories', 'challenges', 'users'];
        
        if (!in_array($type, $validTypes)) {
            return response()->json([
                'error' => 'Type invalide',
                'valid_types' => $validTypes
            ], 400);
        }
        
        try {
            $redis = Cache::getRedis();
            $pattern = 'ozmose:';
            
            if ($type !== 'all') {
                $pattern .= $type . ':';
            }
            
            $keys = $redis->keys($pattern . '*');
            $count = count($keys);
            
            if ($count > 0) {
                $redis->del($keys);
            }
            
            return response()->json([
                'success' => true,
                'type' => $type,
                'keys_deleted' => $count,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du vidage du cache',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}