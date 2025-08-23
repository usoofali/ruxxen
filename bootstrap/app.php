<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.role' => \App\Http\Middleware\CheckRole::class,
            'setup.check' => \App\Http\Middleware\CheckSetupStatus::class,
        ]);
        
        // Apply setup check middleware globally
        $middleware->append(\App\Http\Middleware\CheckSetupStatus::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
