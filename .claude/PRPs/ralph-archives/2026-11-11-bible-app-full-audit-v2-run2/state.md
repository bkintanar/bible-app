---
iteration: 1
max_iterations: 20
plan_path: ".claude/PRPs/plans/bible-app-full-audit-v2.plan.md"
input_type: "plan"
started_at: "2026-11-10T18:19:48Z"
---

# PRP Ralph Loop State (run 2: the owner-blocked remainder)

Run 1 (iterations 1-20, 2026-11-10) is archived at
.claude/PRPs/ralph-archives/2026-11-10-bible-app-full-audit-v2/ (in the dev worktree). Everything not
needing the owner is done and deployed. This run closes the remainder with the owner's answers.

## Codebase Patterns
(Consolidate reusable patterns here - future iterations read this first)
- LIVE TREE /var/www/personal/bible-app IS PRODUCTION (nginx root). Code changes are made in the dev worktree /var/www/personal/bible-app-dev (branch audit-v2/work) and reach production only via `bin/deploy.sh audit-v2/work` (or a manual `git merge --ff-only audit-v2/work` before deploy.sh exists).
- After Task 0.4 the live tree's config is CACHED: env overrides and phpunit env are ignored there. Run tests / copy-imports ONLY in the worktree after `devguard` (plan, "Where To Work").
- Artisan on the live tree runs as `www-data` (`sudo -u www-data php artisan …`), never root (root-owned -wal/-shm/logs lock the app out). After Task 4.7 the app user is `bible`.
- Pushing to GitHub: host identity b8-catch cannot push; use the one-off bkintanar gh-token credential helper (memory file github-push-credentials.md).
- Commit messages end with the Co-Authored-By / Claude-Session trailers.
- Live HTTP checks: `curl -s -H 'Host: bible.b8.co.nz' http://127.0.0.1/...`; visible-text checks must strip tags WITHOUT inserting spaces (Strong's wraps every word in a span). Scope page greps to `page page-current` (the hidden previous chapter renders first).
- Helpers (APP, DEV, H, DB, vtext, devguard): `. /tmp/claude-0/-var-www-personal-bible-app/0d7853e9-af4e-4302-b32d-820559376867/scratchpad/ralph-env.sh` (devguard reads the resolved DB path via a booted `php -r`; `artisan config:show` prints relative paths).
- Scripture gate: `bash scripts/audit/check-scripture.sh <app_root> <db>` (≈10s, read-only snapshot). Render simulation uses <app_root>'s real code, so run it against $DEV to measure uncommitted render changes.
- Never cache a value keyed on raw user input (Cache::remember stores NULL); never cache or serve an empty chapter payload.
- Deploy: commit in $DEV → `cd $APP && bin/deploy.sh audit-v2/work` (as root). Record `git -C $APP rev-parse HEAD` first for rollback.
- Before every deploy: if the live branch has nightly `content(osis)` commits, `git -C $DEV merge feature/audit-remediation-phase-0` first (deploy.sh merges --ff-only).
- One projection at a time: osis:import and PublishChapterProjection share the cache lock `laravel-queue-overlap:osis-projection`.
- Editor is LIVE (BIBLE_EDITOR_ENABLED=true): any re-projection must not race it — osis:import waits on the lock; a gated full re-projection should still freeze the editor (flag false + Constraint 16) first.


## Current Task
Close the owner-blocked remainder of the plan: APP_KEY rotation (4.7), host package upgrades (9.5),
write deploy key for the nightly OSIS push (3.4), failure notifications + uptime check (9.4), off-host
backup (0.3), OD-1/3/4/9/10/11 answers, review of the 9 red-letter differences vs eBible (2.4).

## Plan Reference
.claude/PRPs/plans/bible-app-full-audit-v2.plan.md

## Instructions
1. Read the plan file
2. Implement all incomplete tasks
3. Run ALL validation commands from the plan
4. If any validation fails: fix and re-validate
5. Update plan file: mark completed tasks, add notes
6. When ALL validations pass: output <promise>COMPLETE</promise>

## Progress Log

---
## Iteration 1 (run 2) - 2026-11-11

### Completed
- Asked the owner every open item up front (2 AskUserQuestion calls): all host actions approved; red letters
  "fix 4, keep 5"; alerts → ntfy.sh; off-host = GitHub; OD-1 remove; OD-3 off by default; OD-9 render.
- Host: PHP 8.4.27 / nginx deb13u10 / libsqlite3 deb13u2 (24 vhosts unchanged); APP_KEY rotated (old key in
  APP_PREVIOUS_KEYS, decrypt verified); stale laravel.log deleted; deploy key added + nightly push proven
  (8 commits pushed by the hardened unit); ntfy topic + uptime timer; one alert per crash.
- Code (572b29c..ea92b49): 4 red-letter source edits; NativePHP removed (+ dev phpunit CVE); book titles +
  colophons (importer, verify, gate x_colophons, reader, CSS, fixture Phlm 1); Strong's off by default;
  ops units; contrast fix for supplied words in red (re-projected); typography fixes.
- Two gated production re-projections (~63 s and ~47 s maintenance): verify 0, gate 33/33.

### Validation Status
- Lint: PASS (pint 0, php -l 0) | Tests: PASS (257) | Build: PASS | Type-check: n/a
- osis:verify --content 0 | gate 33/33 | run_all clean (red 2,027, em 21,571) | live_sample 1,165 verses 0
- axe 12 production pages 0 | browser check 33/33 | restore drill 26 s | audits clean | 0 failed jobs
- CI: PASS (run 42923736634 on ea92b49: test + a11y success)

### Learnings
- systemd 257: Restart=always + failure still triggers OnFailure (run 1's belief was wrong) → no ExecStopPost.
- Flipping a default render path needs axe + browser check on that path: found a 4.34:1 contrast issue.
- Look at screenshots: they showed a font fallback and an !important italic override the pass count missed.
- Pint ignores .gitignore → pint.json excludes generated views.

### Next Steps
- None: report, archive, move the plan to completed/, remove this state file, output the promise.

---
