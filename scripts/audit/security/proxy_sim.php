<?php

// Read-only simulation of an app tree's trusted-proxy config against crafted forwarding headers.
// Usage: php proxy_sim.php [app_root]   (default: the live tree)
$root = rtrim($argv[1] ?? '/var/www/personal/bible-app', '/');
require $root.'/vendor/autoload.php';

use Illuminate\Http\Request;

$proxies = (require $root.'/config/trustedproxy.php')['proxies'];
// Must match bootstrap/app.php's trustProxies(headers: ...).
Request::setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

// [server vars, expected ip, expected host, expected secure]
$cases = [
    'tunnel, normal client' => [['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'], '203.0.113.9', 'bible.b8.co.nz', false],
    'tunnel, client-supplied XFF prepended' => [['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9'], '203.0.113.9', 'bible.b8.co.nz', false],
    'tunnel, client egress via CF WARP (104.28.x)' => [['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 104.28.5.5'], '104.28.5.5', 'bible.b8.co.nz', false],
    'tunnel, client egress via CF Workers (2a06:98c0::/29)' => [['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '7.7.7.7, 2a06:98c0:3600::103'], '2a06:98c0:3600::103', 'bible.b8.co.nz', false],
    'LAN direct, spoofed XFF' => [['REMOTE_ADDR' => '10.0.10.50', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9'], '10.0.10.50', 'bible.b8.co.nz', false],
    'tunnel, X-Forwarded-Host: evil + Proto https' => [['REMOTE_ADDR' => '10.0.10.31', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_HOST' => 'evil.example', 'HTTP_X_FORWARDED_PROTO' => 'https'], '203.0.113.9', 'bible.b8.co.nz', true],
];

$failed = 0;
foreach ($cases as $label => [$server, $ip, $host, $secure]) {
    $r = Request::create('http://bible.b8.co.nz/x', 'GET', [], [], [], $server + ['HTTP_HOST' => 'bible.b8.co.nz']);
    $ok = $r->ip() === $ip && $r->getHost() === $host && $r->isSecure() === $secure;
    $failed += $ok ? 0 : 1;
    printf("%s %-52s ip=%-22s host=%-16s secure=%s\n", $ok ? 'PASS' : 'FAIL', $label, $r->ip(), $r->getHost(), $r->isSecure() ? 'y' : 'n');
}
exit($failed ? 1 : 0);
