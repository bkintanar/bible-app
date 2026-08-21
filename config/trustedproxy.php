<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare proxy ranges
    |--------------------------------------------------------------------------
    |
    | bible.b8.co.nz is served through Cloudflare, which terminates TLS and forwards
    | to this origin over plain HTTP. Without trusting the proxy, Laravel treats every
    | request as insecure and generates http:// URLs - so GET https://bible.b8.co.nz/
    | answered 302 to http://bible.b8.co.nz/..., downgrading the protocol on the way in.
    |
    | Trusting '*' would let anyone reaching this origin directly (it is also on the LAN
    | at 10.0.10.73) spoof X-Forwarded-For and X-Forwarded-Proto, so the ranges are
    | explicit. Refresh from https://www.cloudflare.com/ips-v4 and ips-v6 when they change.
    |
    */

    'proxies' => [

        // The immediate peer. Traffic arrives via a Cloudflare TUNNEL (cloudflared) running on
        // this host, NOT from a Cloudflare edge address - the probe showed
        // REMOTE_ADDR=10.0.10.31 alongside CF-Warp-Tag-Id and X-Forwarded-Proto: https. So the
        // published Cloudflare ranges below never matched and the proxy stayed untrusted, which
        // is why an HTTPS request still produced an http:// redirect.
        //
        // To re-derive if the tunnel moves: check REMOTE_ADDR on an inbound public request.
        '10.0.10.31',

        // Kept for the case where DNS is ever pointed straight at a public origin instead of
        // through the tunnel. Refresh from https://www.cloudflare.com/ips-v4 and ips-v6.

        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],

];
