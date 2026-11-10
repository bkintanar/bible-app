# Restoring bible-app

The OSIS XML in `assets/` is the **source of truth**; the SQLite database is a **projection** of it.
Backups (`deploy/bin/bible-backup.sh`, nightly via `bible-backup.timer`) hold both:

```
/var/backups/bible-app/bible-YYYY-MM-DD.sqlite.gz   # projection (verified integrity_check before gzip)
/var/backups/bible-app/osis-YYYY-MM-DD.tgz          # assets/{kjv,asv,mao}.osis.xml
/var/backups/bible-app/archive/                     # older one-off snapshots, never pruned
/var/backups/bible-app/pre-v2/                      # snapshot taken before the audit-v2 work
```

`APPUSER` is `bible` (the dedicated FPM pool user, since Task 4.7). Run everything as root unless shown.

## A. Restore the database only (bad deploy, bad projection)

```bash
APP=/var/www/personal/bible-app; DB=$APP/database/bible_app.sqlite; APPUSER=bible
sudo -u "$APPUSER" php "$APP/artisan" down
systemctl stop bible-app-queue
B=$(ls -t /var/backups/bible-app/bible-*.sqlite.gz | head -1)       # or pick a date
gunzip -c "$B" > "$DB.restore"
sqlite3 -readonly "$DB.restore" 'PRAGMA integrity_check'           # must print: ok
rm -f "$DB-wal" "$DB-shm" && mv "$DB.restore" "$DB"
chown "$APPUSER:$APPUSER" "$DB" && chmod 664 "$DB"
systemctl start bible-app-queue
sudo -u "$APPUSER" php "$APP/artisan" up
```

## B. Restore the source of truth (XML)

Prefer git: `git -C "$APP" log -- assets/` and `git checkout <commit> -- assets/kjv.osis.xml`.
If git is unavailable: `tar xzf /var/backups/bible-app/osis-YYYY-MM-DD.tgz -C "$APP"`.
Then re-project (C), because the database must match the restored source.

## C. Rebuild the projection from the XML

```bash
sudo -u "$APPUSER" php "$APP/artisan" down
sudo -u "$APPUSER" php "$APP/artisan" osis:import "$APP/assets/kjv.osis.xml" --production
sudo -u "$APPUSER" php "$APP/artisan" osis:verify "$APP/assets/kjv.osis.xml" --content
sudo -u "$APPUSER" php "$APP/artisan" up
```

`osis:import` waits up to `--lock-wait` seconds (default 60) for a running chapter publish to finish
and then exits 1 rather than rebuilding the index alongside it. It refuses the production database
without `--production`, and exits 1 if it skipped any verse in scope. The run takes ~31 s and ~480 MB.

## D. Total loss of the host

1. Clone `git@github.com:bkintanar/bible-app.git` (branch with the latest `content(osis)` commits).
2. Install PHP 8.4 + extensions, nginx, composer, node; `composer install`, `npm ci && npm run build`.
3. Copy `.env` from a secure store (it is not in git), `php artisan migrate --force`.
4. Either restore the latest `bible-*.sqlite.gz` (A) or rebuild from the XML (C).
5. `php artisan admin:create` (users are not in git).

**Off-host copies are not configured yet** — until they are, D depends on git alone for the XML and
on rebuilding the projection.
