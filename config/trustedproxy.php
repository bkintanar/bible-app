<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | bible.b8.co.nz is served through a Cloudflare TUNNEL. cloudflared runs on 10.0.10.31 (not on
    | this host, which is 10.0.10.73) and connects to nginx here over plain HTTP, so every public
    | request arrives with REMOTE_ADDR=10.0.10.31. That is the only peer to trust.
    |
    | Verified 2026-11-10 by sniffing a tagged public request: cloudflared sends X-Forwarded-For
    | with the real client IP as its LAST entry (equal to CF-Connecting-IP; a client-supplied XFF is
    | kept in front of it) and X-Forwarded-Proto: https. It sends no X-Forwarded-Host.
    |
    | Cloudflare's published edge ranges are deliberately NOT trusted: nothing reaches this origin
    | from them, and trusting them let a request sent from a Cloudflare Worker (whose egress is in
    | those ranges) choose its own ip() through a forged X-Forwarded-For chain. Never trust '*':
    | the origin is also reachable on the LAN, where '*' would accept spoofed forwarding headers.
    |
    | To re-derive if the tunnel moves: check REMOTE_ADDR on an inbound public request.
    |
    */

    'proxies' => [
        '10.0.10.31',
    ],

];
