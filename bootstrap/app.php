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

        // This origin sits behind a Cloudflare tunnel, which terminates TLS and forwards over plain
        // HTTP. Untrusted, Laravel read every request as insecure and generated http:// URLs.
        // Only X-Forwarded-For and -Proto are honoured: cloudflared sends no X-Forwarded-Host, so
        // accepting one could only ever come from a forged header. See config/trustedproxy.php.
        // NOTE: config() is not available this early in the bootstrap - the container has no
        // 'config' binding yet - so the file is required directly rather than resolved.
        $middleware->trustProxies(
            at: (require __DIR__.'/../config/trustedproxy.php')['proxies'],
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        );

        // Only APP_URL's host (and its subdomains) may build URLs: a forged Host header could
        // otherwise poison absolute links. No argument on purpose - at: ['bible.b8.co.nz'] becomes
        // an UNANCHORED regex in Symfony. Inactive in local/testing (TrustHosts.php).
        $middleware->trustHosts();

        // Public, unauthenticated API over a 31k-verse FTS index; now internet-facing.
        // No argument: throttleApi()'s first parameter is the LIMITER NAME, not a rate, so
        // passing '120,1' pointed it at a limiter that does not exist. The rate lives in the
        // 'api' limiter defined in AppServiceProvider::boot().
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
