# Implementation Report

**Plan**: `.claude/PRPs/plans/bible-app-audit-remediation.plan.md`
**Branch**: `feature/audit-remediation-phase-0`
**Date**: 2026-08-19
**Status**: **PARTIAL — 13 of 70 tasks complete.** Deliberately stopped; see *Why This Stopped*.

---

## Summary

Executed the safe, high-value, reversible core of the remediation plan: the headline scripture
rendering bug, the three API correctness defects, the 779× query-shape fix, WAL, containment items,
and my own deployment gaps. Eight commits, 18 files, +203/−187.

**The plan is a multi-week programme with six unanswered decisions and several irreversible steps.
It was never executable end-to-end in one pass, and attempting that would have been reckless.**
What is delivered is complete, individually validated, and independently revertable.

---

## Why This Stopped Where It Did

Three categories of remaining work were **not** attempted, each for a stated reason:

| Category | Examples | Why not |
|---|---|---|
| **Blocked on user decisions** | Task 0.4 (remove `nativephp/mobile`?), Task 0.3 (Livewire CVE upgrade), Task 6.3 (delete the 1,351-LOC XML reader?) | O-1…O-6 are unanswered. O-1 is also a **hard technical blocker**: `composer` cannot reach the private repo (HTTP 401, no `auth.json`), so **no composer operation can run at all** — including the CVE-2025-54068 upgrade |
| **Blocked on missing prerequisites** | Task 0.5 (auth on `/admin/*`) | `middleware('auth')` is unsatisfiable — there is no `User` model and no `users` table. Adding it would 500 every request to that route. Needs O-5 |
| **Multi-day / irreversible** | Phase 1B (projection engine), Phase 4 (test suite), Phase 6 (delete ~2,240 LOC) | Phase 1B alone is ~1 week and gates re-projection; Phase 6 deletions are irreversible and depend on decisions above |

---

## Assessment vs Reality

| Metric | Predicted | Actual | Reasoning |
|---|---|---|---|
| Complexity | HIGH | **HIGH — confirmed** | No task was easier than scoped. Two were harder: the `dd()` removal needed an accompanying route-constraint fix, and the render fix needed matching an existing class contract |
| Confidence | 8/10 one-pass | **9/10 for what was attempted** | Every root cause in the plan held up under implementation. The 779× measurement reproduced exactly. No diagnosis had to be revised |
| Regressions | 0 expected | **0 actual** | Test suite identical to baseline (113 failed / 9 risky / 133 passed / 469 assertions) after every commit |

---

## Tasks Completed

| # | Task | Files | Status |
|---|---|---|---|
| 0.10 | DB backup, verified (`integrity_check ok`, 31,102 verses) | `/var/backups/bible-app/` | ✅ |
| 0.1 | Commit 8 pending Gen 28–29 verse titles | `assets/kjv.osis.xml` | ✅ |
| 0.2 | Ignore rule + delete 20 backups (**142MB reclaimed**) | `.gitignore` | ✅ |
| 0.6 | Remove both copy-per-save backup writers | `VerseTitleManager.php` | ✅ |
| 0.7 | Replace all 6 `dd()` with `abort(404)` | 4 files | ✅ |
| 0.8 | Remove `/dashboard`, `/livewire-search-poc`, `/test-cache` | `routes/web.php` (150→60) | ✅ |
| 0.9 | Create `failed_jobs` + `job_batches`; harden systemd unit | 2 migrations, unit file | ✅ |
| 1.1 | Render `<transChange>` + `<divineName>` on the default path | `BibleChapter.php` | ✅ |
| 2.1 | `BibleService:140` — dead book endpoint | `BibleService.php` | ✅ |
| 2.2 | `SearchController:61` — wrong verse numbers | `Api/SearchController.php` | ✅ |
| 2.3 | 9 catches `\Exception`→`\Throwable`, log `$e` | 4 API controllers | ✅ |
| 2.5 | Unconditional FTS5 quoting | `DatabaseBibleReader.php` | ✅ |
| 2.6 | Numeric route constraints (partial) | `routes/web.php`, `routes/api.php` | ✅ |
| 3.0 | `whereHas` → `resolveChapterId` (**779×**) | `DatabaseBibleReader.php` | ✅ |
| 3.1 | WAL + `busy_timeout` + `synchronous=NORMAL` | `config/database.php` | ✅ |

---

## Validation Results

| Check | Result | Details |
|---|---|---|
| PHP syntax | ✅ | `php -l` clean on every changed file |
| Test suite | ✅ **no regression** | 113 failed / 9 risky / 133 passed — **identical to baseline** after every commit |
| Lint (Pint) | ⚠️ unchanged | Same 3 files failed identically on `main`; verified by running Pint against `git show main:<file>`. Deferred per Constraint 9 (O-4 undecided) |
| Live smoke | ✅ | 10/10 routes correct |
| Error paths | ✅ | 5/5 now 404, previously 500 |
| Query plan | ✅ | `SEARCH verses USING INDEX …` — no `SCAN` |
| Queue worker | ✅ | Real job dispatched and drained in 2s, 0 failures, `NRestarts=0` |
| Build | ✅ | `route:cache` now succeeds (previously failed on closures) |

**On the test suite:** it cannot validate this work — 98 of its 113 failures are rot against an
architecture that no longer exists, and it covers none of the bugs fixed here. It was used purely as
a **regression detector**, and detected none. Correctness was validated live instead.

---

## Measured Outcomes

| Before | After |
|---|---|
| Ps 23:1 → "The Lordis my shepherd" | **"The LORD is my shepherd"** |
| Gen 2:4 → "Theseare the generations" | **"These *are* the generations"** |
| Raw OSIS tags in rendered DOM | **0** across Ps 23, Gen 2, Exod 20, Deut 6, Isa 53, Ps 119, Matt 5 |
| `/api/books/{book}` → 500 for **every** book | **200** for all |
| API search `verse` always `1` | matches `reference` exactly |
| `q=AND`/`OR`/`NOT`/`a(b` → 500 | **200** |
| Bad URLs → 500 leaking DB config | **404**, no leak |
| Verse fetch: `SCAN verses` 21.938ms | `SEARCH … USING INDEX` **0.028ms** |
| `journal_mode=delete`, reader tail 629ms | **`wal`**, tail 0.21ms |
| No `failed_jobs` → worker dies permanently | table exists, job drains cleanly |
| `assets/` 159MB | **17MB** |
| `route:cache` fails | succeeds |

---

## Deviations from Plan

1. **Task 2.6 pulled forward into Phase 0.** Removing `dd()` was not sufficient on its own —
   `/books/Gen/chapters/abc` still 500'd because `ChapterController::show` type-hints
   `int $chapterNumber`, so the `TypeError` fires *before* the method body and `abort(404)` could
   never run. Added `whereNumber()` immediately. This also fixed `chapters/1.5` silently serving
   chapter 1.
2. **Task 3.0 implemented via a shared `resolveChapterId()` helper** rather than two inline fixes.
   The two call sites normalised case *differently* — `getVerses()` did, `getParagraphDataFromDatabase()`
   did not — so a lowercase ref returned verses but no paragraph data and fell through to arbitrary
   artificial paragraphing. One resolver fixes the performance bug and that latent correctness bug together.
3. **Systemd `After=` replaced with `RequiresMountsFor=`** per the audit: SQLite is a local file, so
   there is no daemon to order against.

---

## Issues Encountered

1. **My own bug, caught by validation.** The first `dd()` replacement script matched `dd([` *after*
   its anchor string, so it rewrote the wrong block and labelled the chapter-not-found branch
   "Book not found". Caught by reading back the diff; fixed with a proper brace-matching scanner.
2. **A misplaced log injection.** The regex that added `Log::error(..., ['exception' => $e])` also
   matched a `return $this->errorResponse(...)` inside a **validation guard**, not a catch — which
   would have been an undefined `$e` fatal. Caught by an explicit "is every injected line inside a
   catch?" check across all 9 sites; removed.
3. **The vhost changed underneath me.** `server_name` was moved from `bible.local` to
   `bible.b8.co.nz` mid-session, so `Host: bible.local` requests fell through to nginx's default
   block. This briefly looked like I had 404'd the whole site. Re-validated against the correct host.
   **The nginx `location ^~ /build/` header-stripping bug (O1) is still unfixed** — I did not touch
   the vhost, since it is now under concurrent edit.

---

## Tests Written

**None.** Stated plainly rather than glossed: the plan puts test work in Phase 4, explicitly
**after** a fixture exists (Task 4.3), because there is no way to write a meaningful Feature test
today — the `:memory:` test database is migrated but **never seeded with Bible data**, so any test
asserting on scripture content fails regardless of correctness.

Writing tests against the current suite would also be actively unsafe: **`RefreshDatabase` only
avoids `migrate:fresh`-ing the real 147MB database because `phpunit.xml` pins `:memory:`.**

Every change here was instead validated by direct observation, recorded above. The regression
signal is the unchanged baseline.

---

## Next Steps

1. **Answer O-1** — it is the highest-leverage unblock. Removing `nativephp/mobile` closes the
   unauthenticated object-injection endpoint **and** restores `composer`, which currently cannot run
   at all — which in turn unblocks the Livewire CVSS 9.2 RCE upgrade.
2. **Answer O-5** — `/admin/verse-titles` still accepts anonymous writes to the source of truth.
   Scaffold auth, or IP-restrict at nginx as an interim.
3. **Fix nginx O1** (`/build/` strips both security headers) once the vhost is no longer being edited.
4. **Phase 1B** — the projection engine. Gates every remaining scripture fix.
5. Remaining Phase 1 items (1.2/1.2b/1.3: red-letter attribution) **require Phase 1B.1 first**, since
   they need a re-projection and the importer is not yet idempotent.

**Plan intentionally NOT archived** — 57 of 70 tasks remain.
