<?php

/*
 * nativephp/mobile (kept on the web build until OD-1 is decided) registers unauthenticated
 * /_native/* routes. BlockNativeRoutesOnWeb must refuse every one of them outside the native app;
 * nginx denies the prefix too. 4.7.0 added /_native/api/call - the prefix check covers it.
 */
it('refuses every nativephp route on the web build', function (string $path) {
    $this->postJson($path, ['event' => 'App\\Events\\Anything', 'method' => 'Anything', 'payload' => []])->assertNotFound();
})->with(['/_native/api/events', '/_native/api/call', '/_native/anything-added-later']);
