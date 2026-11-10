# Implementation Report — Bible App Full Audit v2

**Plan**: `.claude/PRPs/plans/completed/bible-app-full-audit-v2.plan.md`
**Runs**: PRP Ralph run 1 (2026-11-10, iterations 1–20, ended owner-blocked) and run 2 (2026-11-11,
iteration 1: the owner's answers, then everything left)
**Completed**: 2026-11-11
**Status**: **COMPLETE.** Every task, validation level, acceptance criterion and edge case passes on
production; every open decision has the owner's answer.

## Summary

The audit's remediation was executed phase by phase in a dev worktree (`audit-v2/work`) and deployed to
production (`/var/www/personal/bible-app`, branch `feature/audit-remediation-phase-0`) through
`bin/deploy.sh` after each task, with the scripture gate and live checks after every deploy.

- **Scripture correctness** (Phases 1–2): numbered books served again (17 books 404'd), verse truncation,
  note-text leaks and red-letter errors fixed in the importer; 14 Matthew verses corrected in the source
  against eBible's red-letter KJV; full re-projection with `osis:verify --content` (per-verse text, red
  letters, paragraph starts). Run 2: the owner reviewed the last 9 red-letter differences (4 changed:
  Rev 22:7, Rev 1:8, Acts 1:4, Luke 6:5; 5 kept as documented differences between traditions).
- **Editor** (Phase 3): safe title writes (no regex backreferences, entities round-trip), queued
  subprocess publish, audit log, honest UX; unfrozen after an end-to-end drill. Run 2: the nightly job
  now pushes editor changes to GitHub with a write deploy key.
- **Security** (Phase 4): patched dependencies, tunnel-only proxy trust, trusted hosts, per-account
  login limiter, runtime state off the scripture DB, locked Livewire state + throttling, secure sessions,
  nginx allow-list, a dedicated `bible` PHP-FPM pool/user with `open_basedir`. Run 2: APP_KEY rotated
  (old key kept as a previous key), `nativephp/mobile` removed, PHP 8.4.27 / nginx deb13u10 /
  libsqlite3 deb13u2.
- **Features & errors** (Phase 5): real search, canonical redirects, JSON API errors, error pages,
  fail-loudly paths.
- **Performance** (Phase 6): Livewire snapshot 430 KB → 6 KB (Matt 5), page 755 KB → 331 KB,
  56 → 13 queries per chapter view, static asset caching, opcache tuning. Run 2: Strong's numbers off by
  default (owner), so a new visitor gets the plain text.
- **Tests & tooling** (Phase 7): suite from 113 failing/9 risky to 257 green; GitHub Actions CI
  (tests + axe a11y job); production `--no-dev`.
- **Accessibility & SEO** (Phase 8): sitemap, metadata, landmarks/headings, semantic paragraphs,
  dialog focus management, named controls, contrast, reduced motion, print stylesheet; axe 0 violations.
- **Cleanup & ops** (Phase 9): −3.6k lines of dead code, schema cleanup, README, health check, hardened
  units, version-scoped lookups, Pint. Run 2: the KJV's 66 book titles and 14 epistle colophons rendered
  (importer, `osis:verify`, the gate and the reader); alerts to a private ntfy topic, a 5-minute uptime
  check, one alert per crash.

## Validation Results (final, production, 2026-11-11)

| Check | Result |
|---|---|
| Lint (`pint --test`, `php -l` over all PHP) | PASS (0 / 0) |
| Tests (`composer check`: Pint + 257 Pest tests, 0 risky) | PASS |
| Build (`npm run build`) | PASS |
| CI (GitHub Actions: test + a11y) | PASS on the final commit |
| `osis:verify --content` | PASS — every verse, and the 14 colophons, match the source |
| Scripture gate `check-scripture.sh` | PASS 33/33 (colophon check added) |
| `run_all.sh` (all 31,102 verses vs source truth) | text clean on db/on/off, 0 leaks, red 2,027 = truth 2,027 (on/off/table), `<em>` 21,571 = supplied 21,571 |
| `kjv_wj_crosscheck.py` (eBible red-letter KJV) | only_ours 0, only_ref 1, word_diff 4 — exactly the 5 differences the owner kept |
| `live_sample.py` | 1,165 live verses in 40 chapters (30 random + the 10 changed/red-heavy ones): 0 leaks, 0 text/red/sim issues |
| axe-core (WCAG 2.1 A/AA, 12 production pages incl. Philemon 1, Romans 16, Revelation 22) | 0 violations |
| Browser check (`scripts/audit/browser/reader_check.py`, headless Chrome on production) | 33/33 |
| Restore drill (backup → integrity → verify → import → verify) | PASS (26 s, 14 colophons rebuilt) |
| Alerts | ntfy test received; worker SIGKILL → exactly 1 alert + restart; clean restart → none; uptime check state machine exercised |
| Nightly OSIS push | the hardened unit pushed 8 commits with the deploy key |
| `composer audit` (incl. dev) / `npm audit` | clean / 0 |
| Host | PHP 8.4.27, nginx 1.26.3-3+deb13u10, libsqlite3 3.46.1-7+deb13u2; all 24 vhosts unchanged |
| Type-check | n/a (PHP; no TypeScript) |

## Owner decisions (run 2, 2026-11-11)

| Item | Answer | Done |
|---|---|---|
| Host actions | Rotate APP_KEY, upgrade PHP/nginx/SQLite, add a GitHub deploy key, delete the old log — all approved | 4.7, 9.5, 3.4, 0.7 |
| Red letters (9 verses) | Fix 4 (Rev 22:7, Rev 1:8, Acts 1:4, Luke 6:5), keep 5 | `572b29c`, re-projected |
| Alerts | Private ntfy.sh topic (+ 5-minute uptime self-check) | `/etc/default/bible-app-notify`, `bible-uptime.timer` |
| Off-host backup | GitHub is enough (the XML is the source of truth; the DB rebuilds from it) | nightly push with the deploy key |
| OD-1 nativephp/mobile | Remove | `ab0fb62` |
| OD-3 Strong's default | Off by default | `24cb104` |
| OD-9 book titles & colophons | Render | `24cb104`, gated re-projection |
| OD-4, OD-6, OD-10 | Not asked separately; the plan's default (= recommendation) was applied in run 1 and stands | — |

## Found while validating (run 2)

- **Contrast regression exposed by the new default:** with Strong's off now the default, axe on production
  (and CI's a11y job) flagged Matt 26:2 — supplied words inside Christ's words were red-500 at 80 %
  opacity, 4.34:1. Fixed in the importer and the reader (red-600, 4.83:1) and re-projected.
- **Typography:** the browser check's screenshots showed the title/colophon in the fallback serif and
  Rom 16's supplied words still italic (an `!important` `.prose em.italic` rule). Fixed and now asserted.
- **Duplicate crash alerts:** run 1 believed `Restart=always` units never reach `OnFailure=` and added an
  `ExecStopPost` hook too. On systemd 257 a signal-killed worker does trigger OnFailure, so a crash
  alerted twice; the hook was removed and one alert per crash measured.
- **Dev-tooling advisory:** phpunit CVE-2026-24765 (dev only) → pest 3.8.7 / phpunit 11.5.56.

## Deviations from Plan (decided and documented in the plan)

- Custom CSS left unlayered (moving it into `@layer components` would let utilities override deliberate rules).
- Strong's words not keyboard-focusable (~700 tab stops per chapter); a tap/click popover instead.
- `word_order`/`text_order`/`poetry_structure` kept (still read).
- Phone typography observation left to the owner (justified text gaps on narrow screens).
- The uptime check runs on this host (owner's choice of ntfy); a monitor elsewhere would also catch the
  whole host being down — optional.
- Colophons are stored as non-canonical `titles` rows (type `colophon`, placement `after`) rather than in
  a new table.

## Codebase Patterns Discovered

- The live tree is production; develop in the worktree, deploy with `bin/deploy.sh`; artisan as `bible`.
- After a `.env` edit: `config:cache` + `queue:restart` as the app user.
- One projection at a time: `osis:import` and `PublishChapterProjection` share the cache lock.
- A full re-projection on production: down → stop queue → backup → deploy → import → verify → gate →
  queue → up (~1 min). Book-level rows (main title, colophon) are rebuilt only by the book pass, so the
  chapter purge must keep them.
- Never cache values keyed on raw input; never cache/serve empty chapter payloads; projection cache keys are versioned.
- `offsetParent` is always null for `position:fixed` — use `getClientRects()` for visibility.
- An `x-ref` inside a nested `x-data` registers with the nested component.
- Livewire returns a bare 419 for a locked-property update in production; batched updates run hooks after all updates.
- `RefreshDatabase` keeps only listed in-memory connections (`$connectionsToTransact`).
- systemd 257: a `Restart=always` unit that fails (signal, non-zero exit) still triggers `OnFailure=`;
  a clean exit does not. Don't add a second hook.
- Count rendered elements, not text runs, when markup nests.
- Pint does not read `.gitignore`: exclude generated PHP (the perf harness's compiled views) in `pint.json`.
- Changing a default render path (Strong's off) needs axe and the browser check re-run on that path.

## Learnings (process)

- Validate against an independent source of truth (eBible red-letter KJV, the OSIS file per verse) — v1's
  "0 false positives" compared the app with its own flawed data.
- Automated browser checks and axe against production found bugs no unit test could (run 1: two UI bugs;
  run 2: a contrast failure and two typography bugs). Look at the screenshots, not only the pass count.
- A failing step inside an `&&` chain can skip a commit while later lines still deploy — check before deploying.
- Ask every owner question up front, with a recommended option: run 2 closed 20 iterations' worth of
  blocked items in one.
