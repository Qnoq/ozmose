<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CheckRateLimitingPerformance extends Command
{
    protected $signature = 'ozmose:check-ratelimit-performance {iterations=1000}';
    protected $description = 'Teste les performances du middleware de rate limiting';

    public function handle()
    {
        $iterations = (int)$this->argument('iterations');
        $this->info("Exécution de {$iterations} requêtes de test...");

        $key = 'ozmose:ratelimit:test:performance';
        $startTime = microtime(true);

        // Simuler des opérations de rate limiting
        for ($i = 0; $i < $iterations; $i++) {
            $currentAttempts = Redis::incr($key);
            if ($i === 0) {
                Redis::expire($key, 60);
            }
        }

        // Nettoyer la clé de test
        Redis::del($key);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $opsPerSecond = $iterations / $duration;

        $this->info("Résultats du test de performance :");
        $this->info("- Nombre d'opérations : {$iterations}");
        $this->info("- Durée totale : {$duration} secondes");
        $this->info("- Opérations par seconde : {$opsPerSecond}");
        $this->info("- Temps moyen par opération : " . ($duration / $iterations * 1000) . " ms");

        // Recommandations basées sur les résultats
        if ($opsPerSecond < 1000) {
            $this->error("⚠️ Les performances sont inférieures aux attentes. Vérifiez la configuration Redis.");
        } else {
            $this->info("✅ Les performances sont satisfaisantes.");
        }

        return Command::SUCCESS;
    }
}