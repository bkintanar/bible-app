<?php
// Read-only simulation of the app's trusted-proxy config against crafted forwarding headers.
require '/var/www/personal/bible-app/vendor/autoload.php';

use Illuminate\Http\Request;

$proxies = (require '/var/www/personal/bible-app/config/trustedproxy.php')['proxies'];
Request::setTrustedProxies($proxies,
    Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);

$cases = [
    'tunnel, normal client' => ['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'],
    'tunnel, client-supplied XFF prepended' => ['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9'],
    'tunnel, client egress via CF WARP (104.28.x in 104.24.0.0/14)' => ['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 104.28.5.5'],
    'tunnel, client egress via CF Workers (2a06:98c0::/29)' => ['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '7.7.7.7, 2a06:98c0:3600::103'],
    'LAN direct, spoofed XFF' => ['REMOTE_ADDR' => '10.0.10.50', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9'],
    'tunnel, X-Forwarded-Host: evil' => ['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_HOST' => 'evil.example', 'HTTP_X_FORWARDED_PROTO' => 'https'],
];

foreach ($cases as $label => $server) {
    $r = Request::create('http://bible.b8.co.nz/x', 'GET', [], [], [], $server + ['HTTP_HOST' => 'bible.b8.co.nz']);
    printf("%-62s ip=%-16s host=%-16s secure=%s\n", $label, $r->ip(), $r->getHost(), $r->isSecure() ? 'y' : 'n');
}
