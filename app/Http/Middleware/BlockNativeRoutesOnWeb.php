<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks nativephp/mobile's runtime-only routes when the app is served over the web.
 *
 * The package registers POST /_native/api/events unconditionally (NativeServiceProvider's
 * configurePackage() calls ->hasRoute('api') with no guard), and because those routes are
 * loaded via the package loader rather than the app's route files, they land with an
 * EMPTY middleware stack: no auth, no CSRF, no throttle, not even the `api` group.
 *
 * The controller behind it does, verbatim:
 *
 *     $event = $request->get('event');
 *     $payload = $request->get('payload', []);
 *     if (class_exists($event)) { $event = new $event(...$payload); event($event); }
 *
 * That lets any anonymous caller instantiate any autoloadable class in the application or
 * vendor tree with attacker-controlled constructor arguments, then dispatch it - a standard
 * PHP object-injection primitive. class_exists() also triggers autoloading.
 *
 * Verified reachable on this deployment before this middleware existed:
 *   curl -X POST /_native/api/events \
 *        --data-urlencode 'event=Illuminate\Support\Stringable' \
 *        --data-urlencode 'payload[]=hello'   =>  {"success":true}
 *
 * The package itself already carries the flag needed to scope this - it checks
 * config('nativephp-internal.running') elsewhere in the same service provider - it just
 * never applies it to route registration. We apply it here at the request layer, which
 * keeps working whether or not the package is later removed, and does not depend on the
 * vhost config.
 */
class BlockNativeRoutesOnWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('_native/*') && ! config('nativephp-internal.running', false)) {
            abort(404);
        }

        return $next($request);
    }
}
