<?php

use Illuminate\Support\Facades\Route;

/*
 * OD-1 (owner, 2026-11-11): nativephp/mobile is removed from the web app. It registered
 * unauthenticated /_native/* routes whose controller instantiated an attacker-named class; this
 * guards against the package (or its routes) coming back.
 */
it('registers no nativephp routes', function () {
    $native = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), '_native'));

    expect($native)->toBeEmpty();
});

it('answers the old nativephp endpoints with 404', function (string $path) {
    $this->postJson($path, ['event' => 'App\\Events\\Anything', 'method' => 'Anything', 'payload' => []])->assertNotFound();
})->with(['/_native/api/events', '/_native/api/call']);
