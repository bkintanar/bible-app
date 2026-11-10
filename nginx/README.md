# nginx configuration

The live vhost, kept in the repo so the deployment is reviewable and recoverable:

    /etc/nginx/sites-available/bible.local   -> symlink to $APP/nginx/bible.b8.co.nz.conf
    /etc/nginx/snippets/bible-headers.conf   <- copy of bible-headers.conf

Because the vhost is a symlink into the live tree, a deploy that changes it needs
`nginx -t && systemctl reload nginx` afterwards (and `nginx -t` before every reload).
Only the tunnel (10.0.10.31) and localhost may connect; everything else gets 403.

## Topology

The site is public at https://bible.b8.co.nz through a **Cloudflare Tunnel**
(cloudflared running on 10.0.10.31), which terminates TLS and forwards to this
origin over plain HTTP on port 80. Consequences:

- Do **not** add HSTS or a TLS listener here; Cloudflare owns that edge.
- Laravel learns the real scheme from `X-Forwarded-Proto`. The tunnel peer must be
  listed in `config/trustedproxy.php` — trusting Cloudflare's *published edge ranges*
  is not enough, because traffic arrives from the tunnel, not an edge address.
  Without that, `GET https://bible.b8.co.nz/` answered `302` to `http://…`.
- Rate limiting keys on `$request->ip()`, so it depends on the same trust setting.

## Two things that bite

**nginx has no `add_header` inheritance.** Any `location` block containing an
`add_header` discards every header inherited from `server`. That is why the headers
live in a snippet included per-location rather than declared once at server level —
the previous `/build/` block set `Cache-Control` and silently dropped
`X-Frame-Options` and `X-Content-Type-Options` from every CSS and JS file.

**`systemctl reload` did not pick up the new snippet.** A full `systemctl restart
nginx` was required. Verify with `nginx -T` and a real request, not just `nginx -t`.
