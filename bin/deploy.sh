#!/usr/bin/env bash
# Deploy a commit from the dev worktree into the LIVE tree (which nginx serves directly).
#
# Usage (as root, from anywhere):   /var/www/personal/bible-app/bin/deploy.sh <ref>
#   e.g.  bin/deploy.sh audit-v2/work      bin/deploy.sh HEAD   (re-run build/caches only)
#
# Rules this script encodes (see .claude/PRPs/plans/bible-app-full-audit-v2.plan.md):
#  - The live tree only ever FAST-FORWARDS; development happens in the worktree.
#  - Runtime OSIS edits must be committed first, or a merge could clobber them.
#  - Artisan runs as the app user, never root (root-owned -wal/-shm/cache files lock the app out).
#  - No restrictive umask: composer/npm run as root and their output must stay world-readable
#    for PHP-FPM and nginx. Only bootstrap/cache/*.php (config.php holds APP_KEY) is tightened.
#  - Dev dependencies are kept until CI exists (plan Task 7.4); tests never run in this tree.
set -euo pipefail

REF=${1:?usage: deploy.sh <ref>}
APP=/var/www/personal/bible-app
# The app user (Task 4.7): owns storage, database, the KJV source; runs PHP-FPM [bible] and the queue.
APPUSER=${APPUSER:-bible}
cd "$APP"

git diff --quiet assets/ || { echo "uncommitted OSIS edits in the live tree - commit them first"; exit 1; }
echo "deploy: from $(git rev-parse --short HEAD) to $(git rev-parse --short "$REF")"
git merge --ff-only "$REF"
# Git (running as root) re-creates changed files as root:root 644. The editor writes the KJV source
# as the app user, so restore that; the other OSIS files stay read-only (not projected).
chown "$APPUSER:$APPUSER" assets/kjv.osis.xml && chmod 664 assets/kjv.osis.xml
chown "root:$APPUSER" assets/asv.osis.xml assets/mao.osis.xml && chmod 644 assets/asv.osis.xml assets/mao.osis.xml

composer install --no-interaction -o
npm ci --no-audit --no-fund
npm run build

sudo -u "$APPUSER" php artisan migrate --force
sudo -u "$APPUSER" php artisan optimize
chown "$APPUSER:$APPUSER" bootstrap/cache/*.php
chmod 640 bootstrap/cache/*.php
sudo -u "$APPUSER" php artisan queue:restart

curl -fsS -H 'Host: bible.b8.co.nz' http://127.0.0.1/up >/dev/null
echo "deploy OK: $(git rev-parse --short HEAD)"
