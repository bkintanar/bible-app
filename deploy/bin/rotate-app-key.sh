#!/usr/bin/env bash
# Rotate APP_KEY on the live tree WITHOUT printing either key. Run as root by the owner.
#
# Why (audit SEC-5): until Task 4.7 the app ran in the shared [www] PHP-FPM pool as www-data, so
# every other vhost on this host could read .env. Treat the old key as disclosed.
#
# The old key is kept in APP_PREVIOUS_KEYS, so cookies and sessions encrypted with it still decrypt
# (Laravel tries the previous keys). Open Livewire pages fail their checksum once and need a reload.
# After a few weeks (longest-lived cookie: the 1-year `resume` cookie, which is harmless to lose),
# APP_PREVIOUS_KEYS can be emptied.
set -euo pipefail

APP=${APP:-/var/www/personal/bible-app}
APPUSER=${APPUSER:-bible}
cd "$APP"

cp -a .env ".env.pre-rotation.$(date +%Y%m%d%H%M%S)"   # same owner/mode (640 root:bible)

python3 - "$APP/.env" <<'PY'
import base64, os, sys
p = sys.argv[1]
lines = open(p).read().split('\n')
new = 'base64:' + base64.b64encode(os.urandom(32)).decode()
old, previous, out = None, [], []
for line in lines:
    if line.startswith('APP_KEY='):
        old = line[len('APP_KEY='):].strip().strip('"').strip("'")
        out += ['APP_KEY=' + new, '__PREVIOUS__']
    elif line.startswith('APP_PREVIOUS_KEYS='):
        previous = [k for k in line[len('APP_PREVIOUS_KEYS='):].strip().strip('"').split(',') if k]
    else:
        out.append(line)
if not old or not old.startswith('base64:'):
    sys.exit('APP_KEY missing or not base64: - nothing changed')
keys = [old] + [k for k in previous if k != old]
out = [('APP_PREVIOUS_KEYS=' + ','.join(keys)) if l == '__PREVIOUS__' else l for l in out]
with open(p, 'w') as f:  # rewrite in place: owner and mode are kept
    f.write('\n'.join(out))
print(f'APP_KEY rotated ({len(keys)} previous key(s) kept)')
PY

sudo -u "$APPUSER" php artisan config:cache >/dev/null
chmod 640 bootstrap/cache/config.php
systemctl reload php8.4-fpm
sudo -u "$APPUSER" php artisan queue:restart >/dev/null
curl -fsS -H 'Host: bible.b8.co.nz' http://127.0.0.1/up >/dev/null
echo "OK. Backup of the previous .env: $(ls -t .env.pre-rotation.* | head -1) - delete it once you are satisfied."
