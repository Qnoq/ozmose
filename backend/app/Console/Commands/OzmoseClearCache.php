<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OzmoseClearCache extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'ozmose:clear-cache {type? : Type de cache à vider (all, categories, challenges, users)}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Vide le cache Redis d\'Ozmose';

    /**
     * Exécuter la commande console.
     */
    public function handle()
    {
        $type = $this->argument('type') ?: 'all';
        
        switch ($type) {
            case 'all':
                // Vider tous les caches d'Ozmose en utilisant le préfixe
                $this->info('Vidage de tous les caches Ozmose...');
                $this->flushByPrefix('ozmose:');
                break;
                
            case 'categories':
                $this->info('Vidage du cache des catégories...');
                $this->flushByPrefix('ozmose:categories:');
                break;
                
            case 'challenges':
                $this->info('Vidage du cache des défis...');
                $this->flushByPrefix('ozmose:challenges:');
                break;
                
            case 'users':
                $this->info('Vidage du cache des utilisateurs...');
                $this->flushByPrefix('ozmose:users:');
                break;
                
            default:
                $this->error("Type de cache inconnu: {$type}");
                return 1;
        }
        
        $this->info('Cache vidé avec succès !');
        return 0;
    }
    
    /**
     * Vide le cache en fonction d'un préfixe
     * Note: Cette méthode est spécifique à Redis
     */
    protected function flushByPrefix($prefix)
    {
        try {
            $redis = Cache::getRedis();
            
            $keys = $redis->keys($prefix . '*');
            
            if (count($keys) > 0) {
                $redis->del($keys);
                $this->info(count($keys) . ' clés supprimées.');
            } else {
                $this->info('Aucune clé trouvée avec le préfixe: ' . $prefix);
            }
        } catch (\Exception $e) {
            $this->error('Erreur lors de la suppression des clés: ' . $e->getMessage());
            
            // Méthode alternative si la précédente échoue
            $this->info('Tentative avec méthode alternative...');
            
            // Utiliser le Cache Facade directement
            Cache::flush();
            $this->info('Cache entièrement vidé.');
        }
    }
}