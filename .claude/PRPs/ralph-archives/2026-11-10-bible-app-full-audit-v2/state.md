---
iteration: 19
max_iterations: 20
plan_path: ".claude/PRPs/plans/bible-app-full-audit-v2.plan.md"
input_type: "plan"
started_at: "2026-11-09T22:35:40Z"
---

# PRP Ralph Loop State

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
Execute PRP plan and iterate until all validations pass.

## Plan Reference
.claude/PRPs/plans/bible-app-full-audit-v2.plan.md

## Instructions
1. Read the plan file
2. Implement all incomplete tasks (in order; respect Ordering Constraints and Owner Decisions)
3. Run ALL validation commands from the plan
4. If any validation fails: fix and re-validate
5. Update plan file: mark completed tasks, add notes ("Execution status")
6. When ALL validations pass: output <promise>COMPLETE</promise>

## Standing rules for this run (from the plan + owner)
- Open decisions without an answer use the plan's *If unanswered* column (OD-1 keep nativephp; OD-3 keep Strong's ON default; OD-4 recommendation; OD-6 recommendation; OD-9 keep dropped; OD-10 Pint; OD-11 leave logs).
- Owner-only blockers (off-host backup target; write deploy key for nightly push; any GitHub UI action) are documented, not faked.
- Production-affecting steps follow the plan's procedure with a fresh verified backup and a stated rollback.

## Progress Log
(Append learnings after each iteration)

## Iteration 1 - 2026-11-09T22:35Z → (in progress)

### Completed
- Phase 0: 0.1 (earlier), 0.2 worktree, 0.3 backups timer, 0.4 deploy.sh + first deploy, 0.5 permissions, 0.6 scripts/audit + check_scripture.py gate; 0.7 skipped (OD-11).
- Phase 1: 1.1–1.6 deployed (1aa4d9a, eb93283) and validated live.

### Validation Status
- Gate (scripts/audit/check-scripture.sh): 13/29 (baseline 12/29). Remaining = Phase 2 importer defects + 5 Matthew verses (2.4) + Matt 9:6 hack.
- Pest: 113F/9R/133P — identical to baseline (no regressions); test rewrite is Phase 7.
- Live: all 66 books ch1 + all 229 numbered-book chapters 200.

### Learnings
- `artisan config:show` prints paths relative to base path — use a booted `php -r` to read resolved config.
- Cache::remember stores NULL; never key a cache entry on raw user input.
- The adjacent-chapter cache stored EMPTY payloads → chapters stayed 404 for 24h after a fix. After any change to chapter resolution, check for poisoned empty `chapter_verses_*` rows (fixed in eb93283).
- Validations that grep the page must scope to `page page-current`; the hidden previous chapter comes first in the HTML.
- Self-closing `<w/>` is sometimes the only separator between words — replace with a space, not ''.

### Next Steps
- Phase 2 (2.1 freeze editor → 2.2 path resolver → 2.3 fixture/tests → 2.4 Matthew XML → 2.5 extraction → 2.6 robustness → 2.7 cache keys → 2.8 FTS → 2.9 verify --content → 2.10 re-projection).

### Phase 2 (same iteration) — DONE, production re-projected 2026-11-09 23:20–23:21Z
- Live: osis:verify --content exit 0 (all 31,102 verses), scripture gate 32/32, all 66 books 200.
- Source edits (Task 2.4, verified vs eBible red-letter KJV): 5 outer-quote moves + 9 mis-tagged non-Christ verses unwrapped (incl. Matt 28:5–7, the angel — was red on the live site). 9 boundary differences recorded for owner review (plan).
- Tests: 50 new passing (Import, Verify, Search, Admin, Osis); old suite unchanged (113F baseline, Phase 7).
- Learnings: Livewire::test() turns abort() into assertStatus(); feature tests need withoutVite() (no build in DEV); old deploy.sh leaves kjv.osis.xml root:root after a merge (fixed); compare.py's FP definition must use "verse has words of Christ", not "<q> start in verse"; importer must not recurse into <note>.
- Remaining owner items: OD-1/3/4/9/10/11 unanswered (interim defaults), 9 red-letter boundary differences, off-host backup target, deploy key.

### Phase 3 (same iteration) — DONE, editor UNFROZEN 2026-11-09 23:36Z
- Deployed dabb6a6 → d2d00cf → 83e7f88 → f69caa4. Queue unit tracked + installed (MemoryMax=1G). Nightly bible-osis-commit timer installed (03:15 NZ); push blocked on a deploy key at /root/.ssh/bible-app-deploy (owner).
- Drill via Livewire::actingAs()->test() as www-data (stdin script $SCRATCH/drill.php: `sudo -u www-data php -- save 'X' < drill.php`): save/remove/re-save Gen 1:1 → each publish 3–6 s, 66 main titles, 0 failed jobs, git diff assets clean, audit lines present. verify --content OK, gate 32/32.
- Learnings: tinker cannot run as www-data (psysh config dir) → pipe a bootstrap script via stdin. App logs are UTC-dated (audit-YYYY-MM-DD in UTC). Gen 1:1 already has a title — check before a "remove" drill. zsh has no PIPESTATUS (use `pipestatus` or check output). The rtk hook turns `grep` into rg — use /usr/bin/grep for patterns with `(`.

### Phase 4 (same iteration) — DONE except APP_KEY rotation (owner), 2026-11-10 ~13:35 NZDT
- Deployed 22facbd → 8fe6749 → 2aadaf2 → 30fc622 → c322594 → 70e8436 → b5e4562 → 0d886bf. Full suite: 113 failing = recorded baseline ($SCRATCH/failing-baseline.txt), never a new failure ($SCRATCH/suite-diff.sh).
- App user is now `bible` (FPM pool [bible] on /run/php/bible.sock, queue + backup units). Artisan on live: `sudo -u bible php artisan …`. ralph-env APPUSER=bible.
- Runtime state (cache/limiter/queue/failed_jobs) is in database/runtime.sqlite; the scripture DB is written only by the importer.
- APP_KEY rotation: the auto-mode classifier denied it (secret handling) — do NOT retry; owner runs deploy/bin/rotate-app-key.sh.
- Learnings: Livewire returns a bare 419 for a locked-property update in production; Livewire applies batched `updates` before running updated* hooks, so replay one wire:model change per round-trip; RefreshDatabase keeps only listed in-memory connections (TestCase::$connectionsToTransact = [null,'runtime']); remember_token is not fillable (updateOrCreate drops it); SQLite as root chowns new -wal/-shm to the DB owner; secure cookies are not sent over http://127.0.0.1 — use the public https URL for cookie-jar checks; FastCGI probe ($SCRATCH/fcgi.py) runs code inside the FPM pool for open_basedir checks.

### Phase 5 + 6 (same iteration) — DONE, deployed (last: 2160785), 2026-11-10 ~14:30 NZDT
- 5.1 real search (+ verse-1 link bug), 5.2 legacy redirects/JSON 404/error pages, 5.3 fail loudly, 5.4 highlight; 6.1 computed neighbours + cache layer removed, 6.2 snapshot diet (Gen 1 9 KB, Matt 5 6 KB) + Alpine UI, 6.3 56 → 13 queries, 6.4 static weight + opcache.
- Learnings: Route::redirect drops query strings; @js() escapes '/' and '"' (\/ and \u0022) — assert on that; empty @section doesn't register with hasSection (use a variable); `git add` of an already-`git rm`ed path fails the whole add; harness groups by SQL text showing only the FIRST binding — use DUMP_CACHE=1; node can syntax-check Alpine expressions ($SCRATCH/alpine-check).
- Suite: 109 failing (baseline 113 minus 4 now passing), never a new failure.

### Phase 7 (same iteration) — DONE (lint green waits for 9.6), 2026-11-10 ~14:55 NZDT
- Suite: 216 tests, 0 failing, 0 risky; baseline file now EMPTY (any failure is new). CI on GitHub Actions (green); production installs --no-dev.
- Learnings: pushing .github/workflows needs the `workflow` scope (bkintanar token has it); gh with GH_TOKEN=$(gh auth token --user bkintanar) reads runs without switching accounts; a deploy that changes deploy.sh runs the old copy that time.

### Phases 8–9 (same iteration) — DONE except owner-blocked items and recorded deviations, 2026-11-10 ~15:45 NZDT
- Last deploy f6be47d (Pint). 231 tests green; CI green with blocking lint; live 200s; verify matches; gate 32/32.
- Owner-blocked: APP_KEY rotation, deploy key (nightly push), off-host backup, NOTIFY_URL + external uptime check, host package upgrades (9.5; classifier refused), OD answers, red-letter boundary review.
- Doable next iteration (no owner needed): 8.1 verses in <p> (move titles out of paragraph groups first), 8.2 Strong's popovers / contrast, 9.1 dead CSS + @layer + SwitchesTranslation trait.
- Learnings: the auto-mode classifier refuses secret rotation and host-wide apt upgrades — prepare scripts/commands for the owner instead; zsh does not word-split unquoted vars (use xargs or ${=VAR}); `pkill -f <pattern>` can kill the shell whose command line contains the pattern; Laravel's /up rethrows in debug mode (test with app.debug=false); purging an in-memory connection breaks RefreshDatabase for later tests (use a transactional rename instead); Restart=always units never reach OnFailure= — use ExecStopPost with $SERVICE_RESULT.

### Next
- Iteration 2: the no-owner deviations above; then re-check everything and decide completion. Do NOT output the completion promise while owner-blocked validations remain unmet.

---

## Iteration 2 - 2026-11-10 (~16:20 NZDT)

### Completed
- SwitchesTranslation trait (fixed testament grouping bug in BibleBook/BibleSearch); dead CSS removed (652 -> 446 lines).
- Contrast pass; verses grouped in <p> with titles between paragraphs (all 1,189 chapters crawled: 0 invalid nesting); Strong's tap/click popover.
- Deployed 7623ff0; pushed; CI green.

### Validation Status
- Type-check: n/a (PHP; no TypeScript in this app)
- Lint: PASS (pint --test 0)
- Tests: PASS (244/244, 0 risky)
- Build: PASS (npm run build)
- Live: osis:verify --content matches; gate 32/32; integrity ok; units active.

### Learnings
- zsh: quote URLs containing '?' in for-lists (glob error aborts the script).
- Psalm 119 renders each verse as its own <p> carrying the verse id itself.

### Blockers (owner only — the loop cannot finish without these)
- APP_KEY rotation (deploy/bin/rotate-app-key.sh; classifier refused); host apt upgrades 9.5 (classifier refused); write-enabled deploy key at /root/.ssh/bible-app-deploy (3.4 push); off-host backup target (0.3); /etc/default/bible-app-notify NOTIFY_URL + external uptime check (9.4); OD-1/3/4/9/10/11 answers; 9 red-letter boundary verses; manual browser check.

### Next Steps
- Nothing left that can be done without the owner. Further iterations would only re-validate; consider /prp-ralph-cancel or provide the owner items above.

## Iteration 3 - 2026-11-10 (~16:45 NZDT)

### Completed
- axe-core 4.10 (WCAG 2.1 A/AA) against production via local Chrome 151 + matching chromedriver ($SCRATCH/axe): fixed 2 serious (scroll region on chapter pages and empty search) + 1 critical (unnamed book-page search button); 11 pages -> 0 violations. Deployed f2fffd7.
- CI job a11y + scripts/audit/a11y/axe_gate.py (fails on serious/critical): green, 9 pages x 0 violations.

### Validation Status
- Type-check: n/a (PHP) | Lint: PASS | Tests: PASS (244) | Build: PASS | CI: test + a11y PASS | Live: verify/gate unchanged (views only).

### Learnings
- google-chrome IS installed on this host; chromedriver via `npx @puppeteer/browsers install chromedriver@<chrome version>`; @axe-core/cli needs --chrome-options="no-sandbox,headless=new" as root.
- The rtk hook rewrites some commands (ls/cat/head) and the classifier then asks to re-issue the exact rewritten input.

### Blockers
- Unchanged: owner-only items (APP_KEY rotation, apt upgrades, deploy key, off-host backup, NOTIFY_URL + uptime check, OD answers, red-letter review, manual browser check).

## Iteration 4 - 2026-11-10 (~17:15 NZDT)

### Completed
- Browser check in headless Chrome (scripts/audit/browser/reader_check.py): 22/22. Fixed: arrow keys turned the page under the open selector (offsetParent is null for position:fixed) and the search box never got focus (nested Alpine component). Print hides Strong's underlines. Deployed 18a67f2; CI test + a11y green.

### Validation Status
- Lint PASS | Tests PASS (244) | Build PASS | CI PASS | axe 0 | browser 22/22 | verify/gate unchanged.

### Learnings
- W3C element key is element-6066-11e4-a52e-4f735466cecf; ChromeDriver exposes CDP at /session/{id}/goog/cdp/execute (Input.dispatchTouchEvent for swipes); WebDriver POST /print returns a PDF.
- offsetParent is always null for position:fixed - use getClientRects().length for visibility.
- x-ref inside a nested x-data registers with the nested component, not the parent.

### Blockers
- Owner-only items unchanged (see plan "Remaining after iteration 4").

## Iteration 5 - 2026-11-10 (~17:50 NZDT)

### Completed
- Ran the plan's Validation Commands levels 1-6 and ticked the Acceptance Criteria with evidence (16/17; the open one is the owner's OD answers).
- Fixed Exod 32:32 em-dash spacing in the importer (re-projected Exod 32); compare_all counts <em> per supplied phrase (italic "excess" was a counting artefact); restore drill passed (34 s); 375px browser checks; v1 plan marked superseded.

### Validation Status
- Lint PASS | Tests PASS (245) | Build PASS | CI PASS | run_all clean | live_sample 0 leaks | verify 0 | gate 32/32 | browser 27/27 | axe 0.

### Learnings
- A failing `pint --test` inside an `&&` chain silently skips the commit while later lines still deploy - run pint on changed files first.
- Count rendered elements, not text runs, when markup nests (em around small-caps).

### Blockers
- Owner-only: APP_KEY rotation, apt upgrades (9.5), deploy key (3.4 push validation), off-host backup, NOTIFY_URL + uptime check, OD answers, red-letter boundary review.

## Iteration 6 - 2026-11-10 (~18:05 NZDT)

### Completed
- Regression sweep (units active, 0 failed jobs, no new log errors) and live edge-case probes (FTS operators, 1/255/256/2000-char queries, Mal 4 -> Matt 1, Rev 22 end, chapter 0/overflow): no 500s, limits enforced. Edge-case checklist ticked with evidence.

### Validation Status
- Unchanged from iteration 5 (all green). No code changes this iteration.

### Blockers
- Owner-only items unchanged. Nothing further is actionable without the owner; recommend /prp-ralph-cancel or completing the owner items.

## Iteration 7 - 2026-11-10
- Checked for owner actions: none yet (PHP 8.4.23, nginx 1.26.3, libsqlite3 deb13u1; no deploy key; no notify config; APP_KEY not rotated). Production healthy (200s, 0 failed jobs). No changes.

## Iteration 8 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 9 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 10 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 11 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 12 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 13 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 14 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 15 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 16 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 17 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 18 - 2026-11-10
- No owner actions yet; production healthy; no changes.

## Iteration 19 - 2026-11-10
- No owner actions yet; production healthy. Max iterations near: wrote the INCOMPLETE report and archived the run (plan not moved to completed; state file kept).
