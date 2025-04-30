<?php

use App\Services\CacheService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Commandes de diagnostic du cache Redis
|--------------------------------------------------------------------------
*/

Artisan::command('ozmose:cache-status', function () {
    $cacheService = app(CacheService::class);
    $status = $cacheService->getStatus();
    
    $this->info('Statut du cache Redis pour Ozmose:');
    $this->newLine();
    
    if ($status['connected']) {
        $this->info('✅ Redis est connecté.');
        $this->table(
            ['Propriété', 'Valeur'],
            [
                ['Version Redis', $status['version']],
                ['Temps d\'activité', $status['uptime']],
                ['Mémoire utilisée', $status['memory_used']],
                ['Nombre total de clés', $status['total_keys']],
                ['Clés de catégories', $status['categories_keys']],
                ['Clés de défis', $status['challenges_keys']],
                ['Clés d\'utilisateurs', $status['users_keys']],
                ['Autres clés', $status['other_keys']]
            ]
        );
    } else {
        $this->error('❌ Impossible de se connecter à Redis!');
        $this->line('Erreur: ' . $status['error']);
        $this->newLine();
        $this->line('Vérifiez que Redis est installé et actif.');
    }
})->purpose('Affiche le statut du cache Redis pour Ozmose');

Artisan::command('ozmose:cache-test', function () {
    $cacheService = app(CacheService::class);
    $result = $cacheService->testConnection();
    
    if ($result) {
        $this->info('✅ Test du cache Redis réussi!');
    } else {
        $this->error('❌ Échec du test du cache Redis!');
        $this->line('Vérifiez que Redis est installé et actif.');
    }
})->purpose('Teste la connexion au cache Redis');

Artisan::command('ozmose:cache-keys {pattern?}', function (string $pattern = null) {
    $redis = Cache::getRedis();
    $pattern = $pattern ?: 'ozmose:*';
    
    $keys = $redis->keys($pattern);
    sort($keys);
    
    $this->info('Clés Redis correspondant au motif: ' . $pattern);
    $this->newLine();
    
    if (count($keys) > 0) {
        $tableData = [];
        foreach ($keys as $key) {
            $type = $redis->type($key);
            $ttl = $redis->ttl($key);
            
            if ($ttl < 0) {
                $ttl = 'Pas d\'expiration';
            } else {
                $minutes = ceil($ttl / 60);
                $ttl = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
            }
            
            $tableData[] = [$key, $type, $ttl];
        }
        
        $this->table(
            ['Clé', 'Type', 'TTL'],
            $tableData
        );
    } else {
        $this->line('Aucune clé trouvée.');
    }
})->purpose('Liste les clés du cache Redis correspondant à un motif');