<?php

namespace App\Providers;

use App\Services\CacheService;
use App\Services\MediaService;
use App\Services\LeaderboardService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaService::class, function ($app) {
            return new MediaService();
        });
        
        $this->app->singleton(CacheService::class, function ($app) {
            return new CacheService();
        });

        $this->app->singleton(LeaderboardService::class, function ($app) {
            return new LeaderboardService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
