# Implementation Report — Bible App Full Audit v2

**Plan**: `.claude/PRPs/plans/bible-app-full-audit-v2.plan.md`
**Run**: PRP Ralph loop, 2026-11-10, iterations 1–19 of 20
**Status**: **INCOMPLETE — blocked on owner actions only.** Every task, validation level, acceptance
criterion and edge case that can be done without the owner is done, deployed to production and pushed.
The completion promise is withheld because the plan's own validations for the items below cannot pass
until the owner acts.

## Summary

The audit's remediation was executed phase by phase in a dev worktree (`audit-v2/work`) and deployed to
production (`/var/www/personal/bible-app`, branch `feature/audit-remediation-phase-0`) through
`bin/deploy.sh` after each task, with the scripture gate and live checks after every deploy.

- **Scripture correctness** (Phases 1–2): numbered books served again (17 books 404'd), verse truncation,
  note-text leaks and red-letter errors fixed in the importer; 14 Matthew verses corrected in the source
  against eBible's red-letter KJV; full re-projection with `osis:verify --content` (per-verse text, red
  letters, paragraph starts). Iteration 5 also fixed the KJV's only em dash (Exod 32:32).
- **Editor** (Phase 3): safe title writes (no regex backreferences, entities round-trip), queued
  subprocess publish, audit log, honest UX; unfrozen after an end-to-end drill.
- **Security** (Phase 4): patched dependencies, tunnel-only proxy trust, trusted hosts, per-account
  login limiter, runtime state off the scripture DB, locked Livewire state + throttling, secure sessions,
  nginx allow-list, a dedicated `bible` PHP-FPM pool/user with `open_basedir`.
- **Features & errors** (Phase 5): real search, canonical redirects, JSON API errors, error pages,
  fail-loudly paths.
- **Performance** (Phase 6): Livewire snapshot 430 KB → 6 KB (Matt 5), page 755 KB → 331 KB,
  56 → 13 queries per chapter view, static asset caching, opcache tuning.
- **Tests & tooling** (Phase 7): suite from 113 failing/9 risky to 245 green; GitHub Actions CI
  (tests + axe a11y job); production `--no-dev`.
- **Accessibility & SEO** (Phase 8): sitemap, metadata, landmarks/headings, semantic paragraphs,
  dialog focus management, named controls, contrast, reduced motion, print stylesheet; axe 0 violations.
- **Cleanup & ops** (Phase 9): −3.6k lines of dead code, schema cleanup, README, health check,
  failure notifications, hardened units, version-scoped lookups, Pint.
- **Iterations 3–6**: axe-core run against production (3 issues fixed), a headless-Chrome end-to-end
  check (found and fixed 2 real UI bugs), all plan validation levels 1–6, restore drill, edge cases.

## Validation Results (final, production)

| Check | Result |
|---|---|
| Lint (`pint --test`, `php -l` over all PHP) | PASS (0 / 0) |
| Tests (`composer test`, 245, 0 risky) | PASS |
| Build (`npm run build`) | PASS |
| CI (GitHub Actions: test + a11y) | PASS |
| `osis:verify --content` | PASS (projection matches the source) |
| Scripture gate `check-scripture.sh` | PASS 32/32 |
| `run_all.sh` (all 31,102 verses vs source truth) | db/on/off text clean, 0 leaks, red 2,026/2,026, `<em>` 21,571 = supplied 21,571 |
| `live_sample.py` (900 live verses) | 0 leaks |
| axe-core (WCAG 2.1 A/AA, 11 pages) | 0 violations |
| Browser check (`scripts/audit/browser/reader_check.py`) | 27/27 |
| Restore drill (backup → integrity → verify → import → verify) | PASS (34 s) |
| `composer audit --no-dev` / `npm audit` | clean / 0 |
| Type-check | n/a (PHP; no TypeScript) |

## Not complete — owner actions

| Item | Plan task | Why not done here |
|---|---|---|
| Rotate `APP_KEY` (`deploy/bin/rotate-app-key.sh`, ready) | 4.7 | Session safety classifier refused secret rotation |
| Upgrade PHP 8.4.27, nginx deb13u10, libsqlite3 deb13u2 (command in plan) | 9.5 | Classifier refused a host-wide upgrade (affects other sites) |
| Write-enabled deploy key at `/root/.ssh/bible-app-deploy` | 3.4 (push validation) | Grants persistent repo write access — owner decision |
| `/etc/default/bible-app-notify` (`NOTIFY_URL`) + external uptime check on `/up` | 9.4 | Needs the owner's notification/monitoring service |
| Off-host backup target | 0.3 | Needs the owner's storage |
| Answers to OD-1/3/4/9/10/11 (interim defaults applied) | Open decisions | Owner decisions |
| Review of 9 red-letter boundary verses | 2.4 | Theological judgement — owner |

## Deviations from Plan (decided and documented in the plan)

- Custom CSS left unlayered (moving it into `@layer components` would let utilities override deliberate rules).
- Strong's words not keyboard-focusable (~700 tab stops per chapter); a tap/click popover instead.
- `word_order`/`text_order`/`poetry_structure` kept (still read); 9.2 needed no re-projection.
- Phone typography observation left to the owner (justified text gaps on narrow screens).

## Codebase Patterns Discovered

- The live tree is production; develop in the worktree, deploy with `bin/deploy.sh`; artisan as `bible`.
- After a `.env` edit: `config:cache` + `queue:restart` as the app user.
- One projection at a time: `osis:import` and `PublishChapterProjection` share the cache lock.
- Never cache values keyed on raw input; never cache/serve empty chapter payloads; projection cache keys are versioned.
- `offsetParent` is always null for `position:fixed` — use `getClientRects()` for visibility.
- An `x-ref` inside a nested `x-data` registers with the nested component.
- Livewire returns a bare 419 for a locked-property update in production; batched updates run hooks after all updates.
- `RefreshDatabase` keeps only listed in-memory connections (`$connectionsToTransact`).
- `Restart=always` units never reach `OnFailure=` — use `ExecStopPost` with `$SERVICE_RESULT`.
- Count rendered elements, not text runs, when markup nests.

## Learnings (process)

- Validate against an independent source of truth (eBible red-letter KJV, the OSIS file per verse) — v1's
  "0 false positives" compared the app with its own flawed data.
- Automated browser checks against production found two bugs no unit test could; keep `reader_check.py`.
- A failing step inside an `&&` chain can skip a commit while later lines still deploy — check before deploying.
