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
        // Runs before routing on EVERY request, so it also covers routes registered by
        // packages outside the app's own route files (which is how nativephp/mobile's
        // unauthenticated POST /_native/api/events endpoint reaches the web build).
        $middleware->prepend(\App\Http\Middleware\BlockNativeRoutesOnWeb::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
