<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'premium' => \App\Http\Middleware\CheckPremiumAccess::class,
            'cache.active.user' => \App\Http\Middleware\CacheActiveUser::class,
            'rate.limit.requests' => \App\Http\Middleware\RateLimitRequests::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        $middleware->appendToGroup('api', \App\Http\Middleware\CacheActiveUser::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\RateLimitRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
