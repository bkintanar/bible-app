#!/usr/bin/env bash
# Nightly backup of the bible-app read model (SQLite) AND its source of truth (OSIS XML).
#
# Runs as the app user from bible-backup.service. The XML is the source of truth; the DB is a
# rebuildable projection, but rebuilding takes a full import, so both are kept.
#
# - `.backup` takes a consistent online snapshot of a live WAL database (never `cp` it).
# - The copy is switched to journal_mode=DELETE so the backup is ONE self-contained file
#   (opening a WAL-mode copy creates -wal/-shm sidecars).
# - Only files directly in $D are pruned; archive/ and pre-v2/ subdirectories are kept forever.
set -euo pipefail

APP=/var/www/personal/bible-app
D=/var/backups/bible-app
S=$(date +%F)
DBB="$D/bible-$S.sqlite"

sqlite3 -readonly "$APP/database/bible_app.sqlite" ".backup '$DBB'"
sqlite3 "$DBB" 'PRAGMA journal_mode=DELETE;' >/dev/null
rm -f "$DBB-wal" "$DBB-shm"
sqlite3 -readonly "$DBB" 'PRAGMA integrity_check;' | grep -qx ok
VERSES=$(sqlite3 -readonly "$DBB" 'select count(*) from verses;')
gzip -f "$DBB"

tar czf "$D/osis-$S.tgz" -C "$APP" assets/kjv.osis.xml assets/asv.osis.xml assets/mao.osis.xml

find "$D" -maxdepth 1 -type f -mtime +14 -delete

# Runtime state (cache, queue) is not backed up - nothing in it is irreplaceable - but expired
# cache rows are never deleted by the database cache store itself, so prune them here.
RT="$APP/database/runtime.sqlite"
if [ -f "$RT" ]; then
    PRUNED=$(sqlite3 -cmd '.timeout 10000' "$RT" "DELETE FROM cache WHERE expiration < CAST(strftime('%s','now') AS INTEGER); SELECT changes();")
else
    PRUNED=0
fi

# Off-host copy: not configured yet (owner to provide a target). Until then this is an
# on-disk backup only, which does not survive loss of this host.
echo "bible-backup $S ok: verses=$VERSES, expired cache rows pruned=$PRUNED (on-disk only; no off-host target configured)"
