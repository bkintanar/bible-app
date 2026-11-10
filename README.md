# Bible

A King James Bible reader at **https://bible.b8.co.nz** — Laravel 12, Livewire 3, SQLite (FTS5),
Tailwind 4 — with a small editor for section headings.

## Architecture

```
assets/kjv.osis.xml  ──osis:import──▶  database/bible_app.sqlite  ──▶  reader (Livewire), API, search (FTS5)
  SOURCE OF TRUTH                       a PROJECTION, rebuildable
        ▲                                        ▲
        │ editor writes the XML (locked, atomic, │ queued PublishChapterProjection re-projects the chapter
        │ validated)                             │ (osis:import --chapter, in a subprocess)
```

- **The OSIS XML is the source of truth.** The database is a projection of it, rebuilt by
  `osis:import` and checked against it by `osis:verify --content`. Never edit scripture in the DB.
- `database/runtime.sqlite` holds cache, rate limiter, queue and failed jobs, so page views never
  write the scripture database.
- The verse-title editor (`/admin/verse-titles`, login required) writes the XML, logs every change
  to `storage/logs/audit-*.log`, and queues a re-projection of that chapter. A nightly unit
  commits editor changes to git (`deploy/systemd/bible-osis-commit.*`).

## Requirements

PHP 8.4 (`dom`, `xmlreader`, `intl`, `mbstring`, `pdo_sqlite`), the `sqlite3` CLI, Composer,
Node 22.

## Local setup

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
touch database/bible_app.sqlite database/runtime.sqlite
php artisan migrate
php artisan db:seed                                  # books and book groups
php artisan osis:import assets/kjv.osis.xml          # ~30 s, ~480 MB
php artisan osis:verify assets/kjv.osis.xml --content    # must say "Projection matches the source"
php artisan admin:create                             # editor account (prompts for the password)
php artisan serve
```

## Commands

| Command | What it does |
|---|---|
| `osis:import <file> [--chapter=Gen.1] [--book=Gen] [--production] [--allow-deletions] [--lock-wait=60]` | Project the OSIS file (or one chapter/book) into the database. Refuses production without `--production`; exits 1 if any in-scope verse was skipped; removing more than 50 verses needs `--allow-deletions`; waits for a running publish (shared lock). |
| `osis:verify <file> --content` | Compare the projection with the source, verse by verse (text, words of Christ, paragraph starts). |
| `admin:create` / `admin:unlock <email> [--ip=]` | Create or update the editor account (rotates the remember token) / clear login rate limits. |

## Tests and tooling

```bash
composer test      # pest --parallel, in-memory SQLite + tests/Fixtures/mini-kjv.osis.xml
composer lint      # pint --test
composer check     # both
```

CI (`.github/workflows/ci.yml`) runs the tests on every push. Tests never run in the live tree.

## Production (this host)

- `/var/www/personal/bible-app` **is production** (nginx serves it). Work happens in the dev
  worktree (`git worktree`, branch `audit-v2/work`); deploy with
  `sudo bin/deploy.sh audit-v2/work` (fast-forward only; refuses uncommitted OSIS edits; builds,
  migrates, caches, restarts the queue). If the live branch has nightly `content(osis)` commits,
  merge them into the work branch first.
- PHP runs as the app user **`bible`** in its own FPM pool (`deploy/php-fpm/bible.conf`). Run
  artisan as that user: `sudo -u bible php artisan …` — never as root.
- After editing `.env`: `sudo -u bible php artisan config:cache && sudo -u bible php artisan queue:restart`.
- Units: `deploy/systemd/` (queue worker, nightly backup, nightly OSIS commit, 5-minute uptime
  check). Install or update one with `cp deploy/systemd/<unit> /etc/systemd/system/ &&
  systemctl daemon-reload`. nginx: `nginx/README.md`. Backups and restore: `deploy/RESTORE.md`.
  APP_KEY rotation: `deploy/bin/rotate-app-key.sh`.
- **Alerts** (`deploy/bin/notify.sh`): queue-worker crashes, a failed nightly backup or OSIS
  commit/push, and the site going down (`bible-uptime.timer`, alerts after 2 failed checks and again
  on recovery) go to the ntfy topic in `/etc/default/bible-app-notify` (root-only;
  `NOTIFY_URL=https://ntfy.sh/<topic>` - subscribe to that topic in the ntfy app). Everything is also
  in the journal: `journalctl -t bible-notify -t bible-uptime`. The uptime check runs on this host,
  so it cannot report the host itself being down.
- **Off-host copy:** the OSIS XML is the source of truth and lives in git; the nightly job pushes
  editor changes to GitHub with the write deploy key `/root/.ssh/bible-app-deploy`. The database is
  rebuilt from it with `osis:import` (~35 s, see `deploy/RESTORE.md`). The nightly DB/XML backups in
  `/var/backups/bible-app` are on this host only; the admin login and audit logs are not in git.
