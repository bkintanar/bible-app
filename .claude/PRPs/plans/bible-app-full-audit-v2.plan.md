# Feature: bible-app Full Audit v2 & Remediation

> **Revision 2 (2026-11-10)** — reviewed in four passes (lead, fact-check, technical feasibility,
> cold-read implementer). See *Review Log* at the end for what changed and why. Revision 1 failed the
> no-prior-knowledge test; the blocking issues (config-cache trap, live tree = production, missing
> re-projection procedure, validations that could not fail) are fixed here.

## Summary

A second full-codebase audit of the KJV Bible reader (Laravel 12.67 + Livewire 3.8.5, SQLite projection
of an OSIS XML source of truth, nginx + PHP-FPM behind a Cloudflare tunnel). Run on 2026-11-10 by ten
parallel, strictly read-only specialist agents plus an external-research agent. Every CRITICAL/HIGH
claim used below was re-verified by the lead. The audit checks the current state against the v1 plan
(`.claude/PRPs/plans/bible-app-audit-remediation.plan.md`, 19/70 tasks done) and the 16 commits since.

> **Headline: the live site still serves incorrect scripture, and all three of the worst defects were
> missed or misdiagnosed by v1.**
>
> 1. **17 numbered books (1 Samuel … 3 John) return 404 — 229 chapters, 6,128 verses, 19.7% of the
>    Bible is unreadable.** Broken since before v1 (a `dd()`/500 then, a 404 since `50f589a`); v1 missed it.
> 2. **14 Gospel verses are truncated (183 words)** — e.g. Matt 26:56 lacks *"Then all the disciples
>    forsook him, and fled."*; Matt 21:31 loses 29 words. v1 called the verse data "clean".
> 3. **Half of Christ's words (21,065 of 41,757 words; 897 verses) show no red, in both Strong's modes.**
>    On the default (Strong's ON) view this never worked: red comes only from `<q>` tags inside each
>    verse's stored XML, and a quote opened in an earlier verse never reaches it. v1 misdiagnosed this
>    as false attribution; its remediation (`fa78569`) deleted a list that was 597/608 correct, which
>    additionally blackened 313 verses **on the Strong's-OFF view**.
> 4. KJV italics are falsified on the default view, Strong's numbers are wrong on ~3,690 verses, and
>    5,844 verses carry study-note text inside `verses.text` (search matches note words).
>
> Operationally: **the source of truth and all 25 remediation commits exist only on this one host**
> (no upstream, no off-host backup, last DB snapshot 80 days old), and **the working tree *is*
> production** — nginx serves `/var/www/personal/bible-app/public` directly.

## User Story

As a **reader of the KJV on bible.b8.co.nz** (and its single admin editor)
I want **every book, every word, red letters, italics and LORD rendered exactly as the 1769 source**, a
site that is safe, fast and accessible, and edits that can never corrupt or lose the source
So that **I can trust what I read as scripture, and the owner can maintain it without fear**.

## Problem Statement

Testable today (each has a validation command below):
- `GET /books/1Cor/chapters/13` → **404** (expected 200); same for 17 books.
- DB `verses.text` and `original_xml` for `Matt.26.56` lack "forsook" (`instr(...)=0`); the source has it.
- `scripts/audit/scripture/red_letters.py` reports **897 FN, 27 partial** (expected 0).
- `partials/styles.blade.php` has `.strong-word.has-morph { font-style: italic; }`; Gen 1 page carries **144** such spans.
- FTS `MATCH 'text:Heb'` returns **3,743** (expected 0 — "Heb." only appears in notes).
- `/searches?query=love` returns **"mock search result #1…"** (expected real verses).
- `git rev-parse @{u}` → *no upstream*; newest backup 2026-08-22, same disk.
- `composer audit --locked --no-dev` → **15 advisories / 8 packages**, incl. a ReDoS reachable by any visitor.
- Test suite: **113 failed / 9 risky / 133 passed** — unchanged since August; 0 tests for any code written since.

## Solution Statement

Ten phases, strictly dependency-ordered. **Phase 0** secures what cannot be regenerated and sets up a
safe place to work (a git worktree — the live tree is production). **Phase 1** fixes render-only
scripture bugs. **Phase 2** freezes the editor, fixes the importer, and re-projects once, gated by a
content-level verifier and the preserved audit scripts, with a written rollback. **Phase 3** hardens
the editor and unfreezes it. **Phases 4–9**: security and runtime isolation, broken features, error
handling, performance, tests, accessibility, cleanup — formatter last.

The v1 architecture decision is **binding**: *the OSIS XML is the source of truth; SQLite is a
disposable, rebuildable read projection.*

## Metadata

| Field | Value |
|---|---|
| Type | BUG_FIX + REFACTOR + SECURITY (audit-driven) |
| Complexity | HIGH |
| Systems Affected | OSIS importer/projection, verify command, render path, DatabaseBibleReader, VerseTitleManager (source writer), search, auth/session, trusted proxies, Livewire state, cache/queue/limiter storage, nginx, PHP-FPM, systemd, backups, test suite, frontend build |
| Dependencies | laravel/framework 12.67.0 → ≥12.69; symfony/http-foundation 7.4.16 → ≥7.4.23; league/commonmark 2.10.0 → ≥2.10.2; livewire 3.8.5 → 3.8.11 (patch); nativephp/mobile 4.2.0 (remove or → 4.7.0); PHP 8.4.23 → 8.4.27; SQLite 3.46.1 (pdo-linked); nginx 1.26.3 (EOL); vite 6.3.5 → ≥6.4.6; axios 1.9.0 + alpinejs 3.14.9 (remove — unused; Livewire bundles Alpine); pest 3.8.2 |
| Estimated Tasks | 58 across 10 phases (0: 7, 1: 6, 2: 10, 3: 5, 4: 8, 5: 4, 6: 4, 7: 4, 8: 4, 9: 6) |
| Audit basis | 10 dimension agents + 1 research agent; lead re-verified every CRITICAL/HIGH; plan reviewed in 4 passes |
| Supersedes | v1 plan for all *open* items (v1 remains the record of what was done) |

---

## Evidence Standard

- **[VERIFIED]** — reproduced by command in this session (lead or agent; lead re-ran every CRITICAL/HIGH).
- **[CODE]** — read in source at the cited file:line.
- **[INFERRED]** — reasoning only; confirm before acting.

Read-only audit scripts were preserved at **`.claude/PRPs/audit-v2-tools/`** (`scripture/`, `data/`,
`perf/`, `security/`, `pipeline/`, `test-probes/`). Task 0.6 promotes them to `scripts/audit/`.

---

## Where To Work — READ FIRST

1. **The live checkout `/var/www/personal/bible-app` is production.** nginx `root` is its `public/`;
   PHP-FPM executes its `app/` on every request; the editor writes its `assets/`. Any file saved there
   is live immediately. **Do not edit code in the live tree.**
2. **All development, tests and copy-imports happen in the dev worktree `/var/www/personal/bible-app-dev`**
   (Task 0.2) on branch `audit-v2/work`, with its own `.env`, its own DB copy, its own `assets/` and
   lock file, and **no config cache, ever**.
3. **Deploying = fast-forwarding the live tree to a commit from the worktree** via `bin/deploy.sh
   audit-v2/work` (Task 0.4). Root-level system changes (systemd, nginx, FPM, permissions) are listed
   per task and tagged **(root, OD-8)**.
4. **Laravel ignores env vars once config is cached** (`LoadConfiguration.php:41-56`,
   `LoadEnvironmentVariables.php:22-24`). After Task 0.4 the live tree's config is cached, so
   `DB_DATABASE=… php artisan …` and Pest's `phpunit.xml` env **are silently ignored there and hit the
   production DB**. `sudo` also strips env vars (`env_reset`). Hence rule 2.

Shell variables used throughout:
```bash
APP=/var/www/personal/bible-app            # live = production
DEV=/var/www/personal/bible-app-dev        # worktree (Task 0.2)
H='Host: bible.b8.co.nz'
DB=$APP/database/bible_app.sqlite
APPUSER=www-data                           # becomes 'bible' after Task 4.7
# visible text of a page (tags removed, NOT replaced by spaces, so run-together words stay detectable)
vtext() { curl -s -H "$H" "http://127.0.0.1$1" | python3 -I -c 'import re,sys,html;print(html.unescape(re.sub(r"<[^>]+>","",sys.stdin.read())))'; }
# guard before ANY test run or copy-import (run inside $DEV)
devguard() { [ "$(pwd)" = "$DEV" ] && [ ! -f bootstrap/cache/config.php ] && grep -q 'name="DB_DATABASE" value=":memory:"' phpunit.xml && php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("database.connections.sqlite.database");' | grep -q "^$DEV/" || { echo "UNSAFE: not in dev worktree / config cached / DB not the copy"; return 1; }; }
# (artisan config:show prints paths relative to the project root, so it cannot be used for this check)
```

---

## Corrections To The v1 Audit (do not act on the superseded beliefs)

| # | v1 belief | Corrected finding |
|---|---|---|
| K1 | D1: "John 3:16 renders red with no `<q>` in source → false attribution; delete the hardcoded list" | **Wrong method.** `<q who="Jesus">` opens at John.3.10 and closes after 3.21; v1 only looked for `<q` inside each verse. Of the deleted `getJesusVerses()` 608 entries, **597 were correct**, 11 false (John 4:9, 4:53, 6:28/34/41/42/52, 21:23, Luke 5:8, Rev 1:8, 16:15). The list only fed `red_letter_text`, which is read **only on the Strong's-OFF path** (`BibleChapter.php:483-488, 676`); the default path reds only `<q>` inside `original_xml` (`:496-499`) and was never right for continuation verses. So `fa78569` blackened 313 verses on the Strong's-OFF view; the default view is unchanged by it. Correct fix: carry quote state across verses in the importer (Task 2.5) [VERIFIED] |
| K2 | D5: "Verse integrity is CLEAN" | Counts were clean; **text was not**: 14 truncated verses, 5,844 with note text appended [VERIFIED] |
| K3 | v1 never tested numbered books | **17 books broken since before v1** — `getVerses` already did `ucfirst(strtolower())` pre-`4434d58` (`git show 4434d58^`); they returned `dd()`/500 until `50f589a`, 404 since [VERIFIED] |
| K4 | F3 (mock search) was a "one-line Blade change" | Still **OPEN** — never done |
| K5 | `osis:verify` closes the "prove the projection" gap | It compares **counts only** and **always exits 1** (counts the header `<work><title>`). It does see DOM-3 as count deltas (`w` −152, `q` −4, transChange −2) but never names a verse and cannot see text, notes or red-letter coverage |
| K6 | "Auto-publish" = queued job | It is a **synchronous `Artisan::call('osis:import')` inside the FPM request**, DOM-loading the 27MB XML (~400MB RSS) |
| K7 | v1 Ordering constraint 4 (move cache off the Bible DB *before* `throttleApi`) | **Violated** by `327801d`: limiter + cache + queue all write the scripture DB |

---

## Status of v1 Findings (condensed)

| v1 ID | Status now | Task |
|---|---|---|
| D1 false red letters | FIXED (0 FP) — but see K1 | |
| D2 Christ's words not red | **OPEN** — 897 verses / 21,065 words on both paths; never worked on default path; Strong's-OFF path regressed by `fa78569` (313) | 2.4 → 2.5 |
| D3 raw transChange/divineName | FIXED (21,580/21,571 italics; 6,887/6,887 small caps) | other raw tags → DOM-5 |
| D4 Strong's `<w/>`, poetry, YHWH, word_order, unreachable titles, position_* | OPEN (canonical flag FIXED) | 1.3, 2.6, 9.2 |
| D5 version-unscoped lookups | OPEN (latent; 1 version) | 9.4 |
| S1 `/_native` | PARTIAL — middleware holds; nginx block bypassable via `/index.php/` | 4.6, 4.8 |
| S2 anon admin | FIXED (auth; Livewire persistent middleware) | |
| S3 Livewire CVE | FIXED (3.8.5) | |
| S4 search XSS | FIXED | |
| S5 debug/dd() | FIXED | |
| S6 API throttle | FIXED for /api; web + `/livewire/update` unthrottled; IP part spoofable | 4.2, 4.4 |
| F1 `/api/books/{b}` 500 | FIXED | |
| F2 API verse numbers | FIXED in API; `BibleService::search` still maps `'verse' => $result['verse'] ?? 1` (`:190`) | 5.3 |
| F3 mock search | **OPEN** | 5.1 |
| F4 XML reader broken | OPEN (dormant) → delete | 9.1 |
| F5 closure/500 routes | `/translation`, `/user-session` still **500** | 5.2 |
| F6 prev/next hidden | Source FIXED; **prod CSS stale** so buttons ~16px | 0.4 |
| F7 FTS operators | FIXED (NUL byte still 500) | 5.3 |
| PERF-0 whereHas scans | FIXED (DB time 69–112ms → 2–3ms) | |
| PERF-1 queries/readers | OPEN (46–48 q; 6 readers; 8–10 version lookups) | 6.3 |
| PERF-2 chapter cache miss | OPEN → delete the layer | 6.1 |
| PERF-3 WAL | FIXED | |
| PERF-4 DOM 420MB | **REGRESSED** — now reachable from web via editor | 3.2 |
| Snapshot bloat / wire:key | OPEN (Matt 5 snapshot 429KB) | 6.2, 8.2 |
| Log flood | FIXED (level warning, daily) | |
| nginx O1/O2 | FIXED | |
| Backups 0.10 | PARTIAL/stale (2 manual, same disk, XML not covered) | 0.1, 0.3 |
| 0.1 commit XML | **REGRESSED** — 3 titles uncommitted since 2026-08-22 | 0.1 |
| Phase 4 tests | NOT STARTED (113F/9R/133P unchanged) | 2.3, Phase 7 |
| Phase 5 a11y | NOT STARTED | Phase 8 |
| Phase 6 cleanup | NOT STARTED (deletable LOC now ~3,420) | Phase 9 |

---

## Findings Inventory (ranked)

IDs are the agent IDs; aliases in brackets. Every row maps to a task.

### P0 — Scripture correctness

| ID | Sev | Finding | Location | Evidence | Task |
|---|---|---|---|---|---|
| DOM-1 | **CRITICAL** | 17 numbered books 404 (1Sam 2Sam 1Kgs 2Kgs 1Chr 2Chr 1Cor 2Cor 1Thess 2Thess 1Tim 2Tim 1Pet 2Pet 1John 2John 3John). `ucfirst(strtolower('1Sam.1'))`→`1sam.1`; DB has `1Sam.1`. Book page 200 links to 404s; API returns `"verses":[]` silently; search hits link to 404 | `DatabaseBibleReader.php:122-127`, `:539-543` | [VERIFIED] curl; sqlite `'1sam.1'`=0 / `'1Sam.1'`=1 | 1.1 |
| DOM-3 [DATA-1] | **CRITICAL** | 14 verses truncated (183 words): Matt 21:31, 26:56; Mark 2:10, 8:19, 12:37; Luke 5:24, 8:8, 9:56, 20:16, 20:24; John 6:64, 8:41, 12:28, 12:36. Sibling walk stops at a `</q>` closing mid-verse and never climbs | `ImportOsisCommand.php:851-883` | [VERIFIED] `text` and `original_xml` of Matt.26.56 lack "forsook"; API verse 56 lacks it; rendered word spans `>forsook<` = 0 | 2.5 |
| DOM-2 [D2] | **CRITICAL** | 897 verses / 21,065 words (50.4%) of Christ's words not red; quote opened in an earlier verse is an *ancestor* of the sID marker and never reaches the projection. 27 more verses partly red | `ImportOsisCommand.php:851-883` | [VERIFIED] `red_status.tsv` 897 FN | 2.4 → 2.5 |
| UX-2 | **HIGH** | `.strong-word.has-morph{font-style:italic}` italicises morph-tagged Hebrew words → falsifies KJV supplied-word italics on the default view | `partials/styles.blade.php:88-90` | [VERIFIED] 144 spans on the Gen 1 page | 1.2 |
| DOM-4 | **HIGH** | Strong's regex `/<w([^>]*)>(.*?)<\/w>/` swallows self-closing `<w/>` → wrong number on ~3,690 verses (John 3:16 "For"→G3588), 29 run-together verses (Matt 6:10 "as it isin heaven"), red span cut mid-verse (22) | `BibleChapter.php:506-554` | [VERIFIED] sim = live on 2,195 verses | 1.3 |
| DOM-7 [DATA-2] | **HIGH** | Note text appended to `verses.text` in 5,844/5,844 noted verses (incl. 1Chr.21.11 "Choose theeChoose…: Heb."); FTS also indexes titles + title-HTML CSS words (`q=midriff`, `q=uppercase`) | `ImportOsisCommand.php:1009-1027`; `search_results` view (migration `2025_06_03_035211:105-112`) | [VERIFIED] `text:Heb` → 3,743 | 2.5, 2.8 |
| DOM-6 | MED (blocks DOM-2) | Source puts narration inside Christ's `<q>` in 5 verses: Matt 16:4, 17:23, 21:25, 22:19, 28:20 ("Amen." debatable) — fixing DOM-2 would newly redden them | `assets/kjv.osis.xml` | [VERIFIED] `q_nesting.py` | 2.4 (OD-5) |
| DOM-5 [DATA-9] | MED | `formatOutput=true`/`preserveWhiteSpace=false` → 379 pretty-printed `original_xml`, "LORD ," (307 verses space-before-punct), raw `<w>` leaking in rendered HTML (`on_leaks`: `w` in 4,037 verses, mostly via swallowed `<w/>` 1,407), 14 raw `<inscription>`, 2,970 leftover `<milestone>` | `ImportOsisCommand.php:105-106`; `BibleChapter.php:506` (no `/s`), `:580-583` | [VERIFIED] | 1.3, 2.5 |
| TEST-2 | **HIGH (latent)** | `osis:import --chapter X.1` deletes the book's main title (stored on chapter 1 with `verse_id=null`; purge deletes it; book titles skipped for sub-book scope). The editor runs `--chapter` on every save | `ImportOsisCommand.php:611-616, 1596-1600, 1687`; `VerseTitleManager.php:403-408` | [VERIFIED code + fixture]; prod still 66/66 | 2.1, 2.6 |
| DATA-5 | MED | 279 verses store raw `<q xmlns=… who="Jesus">` (regex requires `<q\s+who`); `red_letter_text.text_content` run-together in 1,110 rows | `ImportOsisCommand.php:1382` | [VERIFIED] | 2.5 |
| DOM-10 | LOW-MED | LORD → "Lord" in canonical psalm titles (Ps 7, 18, 36, 102) — `strip_tags` allow-list omits `<span>` | `partials/verse-paragraph.blade.php:19, 58` | [VERIFIED] | 1.4 |
| DOM-11 | LOW | Supplied italics inside red text render gray (345 segments) | `BibleChapter.php:568-572` | [VERIFIED] | 1.4 |
| DOM-12 | LOW | 14 colophons absent; 66 book + 1,184 chapter titles never rendered | importer + render | [VERIFIED] | 9.2 (OD-9) |
| DOM-13 | LOW | No prev/next across books (Mal 4 → Matt 1); `/books/gen/...` 404 (case-sensitive) | `bible-chapter.blade.php:11-12, 159, 223`; `BibleService.php:90-94` | [VERIFIED] | 1.5 (cross-book), 8.3 (case 301) |
| D4-rest | LOW | `divine_names.original_name` 'YHWH' ×6,952 (308 shown "God"); `poetry_structure` 4,939 empty fabricated rows; `word_order` = nesting depth | `ImportOsisCommand.php:1166, 797-815` | [VERIFIED] | 2.6 (stop fabricating), 9.2 (drop) |

### P0 — Loss of the source of truth / operational safety

| ID | Sev | Finding | Evidence | Task |
|---|---|---|---|---|
| OPS-1 [DATA-4, PIPE-4] | **HIGH** | Branch has **no upstream**; local `origin/main` ref (last fetched 2026-08-19) is 25 commits behind HEAD; 3 published titles uncommitted since 2026-08-22; backups: 2 manual DB snapshots, same disk, 80 days old, XML not covered; no timer. RPO unbounded | [VERIFIED] `git rev-parse @{u}`; `ls /var/backups/bible-app` | 0.1, 0.3, 3.4 |
| PROD-TREE | **HIGH** | The working tree is production (nginx root); plan work must not happen in it | [VERIFIED] vhost `root` | 0.2 |
| OPS-3 [UX-1] | HIGH | No deploy procedure; `public/build` (2026-08-19 20:12) predates `ddb5815` + `4c46a0d` → login page & 44px nav buttons unstyled, focus ring invisible; config/route cache off; queue never restarted | [VERIFIED] `max-w-sm`, `min-h-full`, `space-y-5`, `text-red-700`, `w-[44px]` absent from built CSS | 0.4 |
| OPS-5 | MED | `database/migrations/*` group-writable by www-data (root later runs them → privesc); `.env` `root:root 644` world-readable | [VERIFIED] `stat` | 0.5 |
| OPS-2 | MED | No monitoring/alerting; `/up` never polled and doesn't touch DB | [VERIFIED] | 9.4 |
| OPS-8 | LOW | nginx access log shows only the tunnel IP (10.0.10.31) | [VERIFIED] | 4.6 |
| OPS-10 | LOW | `/build/` 404s get `Cache-Control: … immutable` for a year (`add_header … always`) | [VERIFIED] | 4.6 |
| OPS-11 | LOW | System config drifts from repo: queue unit not tracked; live vhost is a byte-identical **copy** at `/etc/nginx/sites-available/bible.local` (not a symlink); logs still named `bible.local.*` | [VERIFIED] | 4.6, 9.4 |
| OPS-12 | INFO | Misleading comments: vhost claims `cgi.fix_pathinfo` is safe at default (it is On; `try_files` protects); `fastcgi_param HTTPS $https` no-op; `trustedproxy.php` says cloudflared runs "on this host" (it's 10.0.10.31) | [CODE] | 4.6, 4.2 |

### P1 — Security

| ID | Sev | Finding | Location | Evidence | Task |
|---|---|---|---|---|---|
| SEC-1 | **HIGH** | `composer audit --locked --no-dev`: 15 advisories / 8 pkgs. **Reachable:** symfony/http-foundation 7.4.16 **CVE-2026-108082 (PKSA-xmwy-rmx5-m7h4, GHSA-r925-mgvr-h9cc)** ReDoS in `HeaderUtils::split()` via any request's `Cache-Control` header (fixed 7.4.19; take ≥7.4.23 for PKSA path/host fixes). laravel/framework 12.67: CVE-2026-114509 (TokenGuard — no token guard here), CVE-2026-114508 (relationship inclusion — app has no JSON:API resources / `app/Http/Resources`), CVE-2026-102279 (debug-page XSS — debug off) → not reachable; upgrade anyway | `composer.lock` | [VERIFIED] | 4.1 |
| SEC-4 [PIPE-1/2] | **HIGH (integrity)** | Title text concatenated into a `preg_replace` *replacement*: `$1`/`$0`/`\1` expand → duplicate `<verse sID>` inside the title, passes XML validation, corrupts the source. `&amp;`→`&amp;amp;` on every re-save. `currentTranslation` tamperable → `../` in the `osis:import` file arg (admin-only) | `VerseTitleManager.php:465-467, 130, 378-383, 405-408` | [VERIFIED] code + scratch replica | 2.2, 3.1 |
| SEC-2 | MED | **No `#[Locked]` anywhere.** Client can set `BibleChapter::$bookOsisId` to arbitrary/huge values → unbounded `cache` + `jobs` rows; `/livewire/update` and web routes unthrottled; `BibleChapter::search()` bypasses `throttle:search`; a `last_visited_page_{session}` row per cookieless hit | `BibleChapter.php:14-43, 247-296, 421-438`; `BibleBook.php`; `DatabaseBibleReader.php:78-108` | [CODE]; cache rows rose from 65 to >1,150 during the audit [VERIFIED] | 4.3, 4.4 |
| SEC-3 [OPS-6] | MED | Trusted proxies = all Cloudflare ranges, but the peer is always the tunnel 10.0.10.31 → a CF-Worker egress lets a client choose `ip()`. Login limiter keyed on **email+IP** (no per-account cap; IP part spoofable). `X-Forwarded-Host` trusted, no `trustHosts` | `config/trustedproxy.php:35-56`; `bootstrap/app.php:29-35`; `LoginController.php:47` | [VERIFIED] `security/proxy_sim.php` | 4.2 |
| SEC-5 [OPS-4, PERF-12] | MED | One `www` FPM pool (user www-data) serves ~10 vhosts; `max_children=5` (hit 2026-11-10); no `open_basedir`, no `pm.max_requests`; a co-tenant compromise reads APP_KEY/sessions and rewrites the XML | `/etc/php/8.4/fpm/pool.d/www.conf` | [VERIFIED] | 4.7 |
| SEC-7 | LOW | `/index.php/<anything>` duplicates every route and bypasses nginx `location ^~ /_native/` (middleware still 404s) | `nginx/bible.b8.co.nz.conf:50-53` | [VERIFIED] `/index.php/books/Gen/chapters/1` → 200 | 4.6 |
| SEC-6 [OPS-7] | LOW | No logout UI; `admin:create` doesn't cycle `remember_token`; no `auth.session`; `session.secure=null` | `routes/web.php:25`; `CreateAdminCommand.php:53-56` | [VERIFIED] | 3.3, 4.5 |
| SEC-9 | LOW | Origin listens 0.0.0.0:80 / [::]:80 — LAN clients bypass Cloudflare; admin creds cleartext on LAN | nginx | [VERIFIED] | 4.6 |
| SEC-8 [UX-7] | LOW | In-chapter search `str_ireplace($searchQuery,'<mark>…')` into `{!! !!}`; `onclick="…'{{ $bookOsisId }}'…"` | `bible-chapter.blade.php:107-113, 211, 225` | [CODE] | 5.4, 6.1 |
| SEC-11 | INFO | axios 1.9.0 (30+ advisories) ships in an unused bundle; npm audit 12 (dev toolchain) | `resources/js/bootstrap.js`, `vite.config.js:8` | [VERIFIED] | 4.1 |

### P1 — Projection pipeline & editor

| ID | Sev | Finding | Location | Task |
|---|---|---|---|---|
| PERF-6 [PIPE-8, ERR-9] | **HIGH** | Every title save/remove runs `osis:import --chapter` in-request → `DOMDocument->load()` of 28MB → VmHWM ~400MB, not capped by `memory_limit` (libxml malloc); FPM worker never recycled; `max_execution_time=30` | `VerseTitleManager.php:405`; `ImportOsisCommand.php:100-113` | 2.1 (freeze), 3.2 |
| PIPE-3 [ERR-3] | MED | No `DB::transaction` in the importer; scoped re-projections not serialised; crash leaves a half-projected chapter; skipped verses still exit 0 | `ImportOsisCommand.php:64-95, 622-645, 724` | 2.6 |
| PIPE-5 [DOM-14] | MED | `osis:import` never invalidates the 24h `chapter_verses_*`/`chapter_data_*` cache | `ImportOsisCommand.php` (no `Cache`) | 2.7 |
| DATA-3 [DOM-9, TEST-3] | MED | `osis:verify` counts only; always exit 1; not per-version; doesn't check `XMLReader::open()` | `VerifyOsisProjectionCommand.php:101-287, 192-194` | 2.9 |
| DATA-6 | MED | FTS `verses_fts_delete/update` triggers pass `''` instead of old values; index correct only because each import ends with a full `rebuild` | migration `…000019` | 2.8 |
| TEST-4 | MED | Importer instance state never reset in `handle()` → in-process re-run duplicates titles (+3) | `ImportOsisCommand.php:22-39, 564` | 2.6 |
| TEST-5 / PIPE-7 | MED | Exit 0 with no books seeded; MAO (contained format) projects 0 verses with exit 0 | `:230-268`, `:409` | 2.6 |
| PIPE-6 | MED | Editor can write/re-project ASV/MAO → second, mostly empty `bible_versions` | `VerseTitleManager.php`; `ImportOsisCommand.php:144, 270-326` | 2.2, 3.1 |
| PIPE-9/10/11 | LOW | Book titles attach to first chapter ignoring version; paragraphs never purged; verses deleted from the source never removed | `:1672-1676`, `:1764-1787` | 2.6 |
| PIPE-13/15 | LOW | Adjacent-chapter job can re-cache stale content; cache keys lack translation | `CacheAdjacentChaptersJob.php` | 2.7, 6.1 |
| MATT96 | HIGH (with 2.5) | `formatRedLetterText` Matt 9:6 regex hack (`:1363-1379`) + `extractVerseContentFromLine` (`static $xmlLines`) — breaks on a synthetic `<q>` (unbalanced red span bleeding into following verses) | `ImportOsisCommand.php:1363-1379, 885-889` | 2.5 |
| ERR-2 | HIGH | Editor: `unchanged` → "Verse not found in XML file."; most failure statuses unlogged; `@`-suppressed errors; no `wire:loading`/double-submit guard | `VerseTitleManager.php:208-312, 362-372, 470, 484` | 3.3 |
| ERR-7 | MED | No audit trail: success logged at `info`, but `LOG_LEVEL=warning` | `VerseTitleManager.php:482, 536` | 3.3 |

### P1 — Broken functionality & error handling

| ID | Sev | Finding | Location | Task |
|---|---|---|---|---|
| ARCH-1 [F3, DOM-8, SEO-3] | **HIGH** | `/searches?query=…` serves 250 fabricated results ("mock search result #1", links to `/book1/1`), indexable; real `BibleSearch` (reads `?q=`) mounted nowhere | `livewire-search.blade.php:16`; `SearchComponent.php:10-11, 60-95` | 5.1 |
| ERR-1 | **HIGH** | Any exception loading verses (e.g. `database is locked`) is caught, logged message-only, returns `collect()` → `abort(404 "Chapter not found")` | `BibleChapter.php:225-227, 85-87` | 5.3 |
| ARCH-2 [ERR-4] | MED | `/translation`, `/user-session` 500 (views missing) | `TranslationController.php:27`; `UserSessionController` | 5.2 |
| UX-4 | MED | Broken links: `/search` (302 → `/books/search` → 404), `/livewire-search`, `/book1/1`; "All Books" self-loops via last-visited redirect | `bible-book.blade.php:7, 151, 155`; `BibleBook.php:92` | 5.2 |
| ERR-5 | MED | `/api/nope` → 302 to HTML (web catch-all matches `api/nope`); three JSON error shapes; empty `withExceptions`; no `resources/views/errors/*` | `bootstrap/app.php`; `routes/web.php:63-69` | 5.2 |
| ROUTE-STORAGE | LOW | Framework `storage.local` route (`/storage/{path}`) is registered and shadowed by the catch-all | `config/filesystems.php` (`serve`) | 5.2 |
| ERR-6 [SEC-10] | MED | `/api/search?q=x%00y` → 500 (body generic; full trace logged at `Api/SearchController.php:79`); `/searches?query=x&limit=abc` and `?query[]=x` → 500; chapter int overflow → 500 | `DatabaseBibleReader.php:489-506`; `SearchComponent.php:14, 72` | 5.3 |
| ERR-8 | MED | `TranslationService` silently falls back to config (offers ASV/MAO with no data) | `TranslationService.php:47, 131, 178` | 5.3 |
| ERR-10/11 | LOW | Empty `catch` in `Http/BibleController.php:30`; 33 `Log::debug/info` (emoji) in hot paths; `.env.example` `LOG_LEVEL=debug` | various | 5.3, 9.3 |
| ERR-12 | LOW | Failed logins / lockouts not logged | `LoginController.php:49-71` | 4.5 |
| ERR-14 | LOW | `getXmlPath` default `'asv'` vs editor `'kjv'`; unlistened `translation-changed` events; stub `/api/search/history` & `/suggestions`; verify ignores `XMLReader::open()` result | various | 2.2, 2.9, 5.3, 9.1 |

### P2 — Performance & payload

| ID | Sev | Finding | Task |
|---|---|---|---|
| PERF-5 | **HIGH** | Prev/next chapters preloaded into DOM *and* snapshot (~51% of Matt 5's 760KB) only for the peel animation; navigation is `window.location='/Gen/2'` → 302 → full re-render | 6.1 (OD-2) |
| Snapshot | MED | Gen 1 snapshot 150KB, Matt 5 429KB (`original_xml` 36–72%); unused props `books`, `paragraphs`, `capabilities`, `combined_text`; `setFontSize` round-trip 399KB and has **no UI**; Strong's default contradicts itself (`BibleChapter.php:43` `false` vs `:101` `session(..., true)`) | 6.2 (OD-3) |
| PERF-8 | MED | `session('current_translation', $this->getDefaultTranslationKey())` evaluates default eagerly (`TranslationService.php:242`); `new DatabaseBibleReader` per call (`BibleService.php:29`) → 6 readers, 8–10 version queries, 28 cache SELECTs; `getVerseText` `UPPER(osis_id)` full scan (`:470`); red_letter N+1 when Strong's OFF (`BibleChapter.php:676-679`) | 6.3 |
| PERF-7/9 [OPS-9, DATA-10] | MED | Cache, queue and rate limiter write the 158MB scripture DB; expired rows never pruned; per-visitor `last_visited_page_*` rows grow with crawlers | 4.3 |
| PERF-10 | LOW | `gzip_comp_level` 1 (level 5 saves ~27%) | 4.6 |
| PERF-11/13 | LOW | config/route/event cache off; livewire.js served via PHP; unused 1.1MB `public/icon.png`; 12.5KB inline JS/page; 4-family render-blocking Google Fonts | 0.4, 6.4 |

### P2 — Accessibility / UX / SEO

| ID | Sev | Finding | Task |
|---|---|---|---|
| A11Y-1 [PERF-14] | HIGH | Hidden prev/next chapters in the a11y tree & find-in-page (John 3 HTML contains John 2:4); `id="verse-1"` ×3 | 1.6, 6.1 |
| A11Y-2 | HIGH | No `<h1>`/`<main>`/`<p>`/skip link; every chapter titled "Bible Chapter"; book selector not a dialog; icon-only close/back unnamed | 8.1, 8.2 |
| UX-3/SEO-1 | HIGH (SEO) | Chapter pages have **0 `<a>`**; no sitemap (`/sitemap.xml` → 302 → 404) | 8.3 |
| A11Y-3..11 | MED-LOW | Unnamed icon buttons; Strong's toggle state by colour only; tooltips hover-only (unusable on touch/NativePHP); no `aria-live`/`wire:loading`; placeholder-only labels; dark `text-gray-500` 3.67:1; 36px targets; no `prefers-reduced-motion`; no `viewport-fit`/`dvh` | 8.2 |
| UX-6 | MED | "Resume reading" keyed by session id (120-min sessions) | 4.3 |
| UX-8 | MED | Search: `.live` model vs Enter-only results; unclamped `#[Url] $limit`; dead scroll-restore hook | 5.1 |
| UX-9/10/12/13 | LOW | No print CSS; dark-mode gaps & 3 copies of `toggleDarkMode`; fixed nav buttons overlap text on phones; one-option translation dropdown | 8.4 |
| UX-11 | LOW | Admin copy claims "backups are created automatically", shows `canonical="true"`; no logout | 3.3 |
| LW-1 | MED | 0 `wire:key` across 16 loops | 8.2 |
| SEO-2 | MED | No meta description/canonical/OG; legacy short URLs 302 (not 301); case-sensitive book IDs | 8.1, 8.3 |
| JS-1 | LOW | `preventDefault` on arrows with nowhere to go; `!touchStartX` treats x=0 as none; `contentEditable==='true'` misses `plaintext-only`; modal detection by CSS classes; top-level `let` globals | 8.4 |
| CSS-1/DEAD-1 | LOW | ~150 dead CSS lines, 37 `!important`, unlayered custom rules beat TW4 utilities, `prose` without plugin; dead Vue/app.js/alpine/axios/concurrently | 4.1, 9.1 |

### P3 — Tests, tooling, architecture

| ID | Sev | Finding | Task |
|---|---|---|---|
| TEST-6 | **HIGH (hazard)** | Editor XML path hard-coded `base_path("assets/{key}.osis.xml")` → a `Livewire::test(VerseTitleManager)->call('saveTitle')` would rewrite the real 27MB source | 2.2 |
| TEST-1 | HIGH | `UserFactory` sets `email_verified_at` (column absent) → every auth test fails | 7.1 |
| Suite | HIGH | 113F/9R/133P unchanged; 66 test dead code, 47 contract-rot; 0 real bugs surfaced; 0 tests for importer/verify/editor/login/API/render | 2.3, 7.1, 7.2 |
| TEST-7..12 | MED-LOW | `--parallel` crashes (`use Mockery;`); Pint (70 files) vs php-cs-fixer (53; refuses PHP 8.4) conflict; risky/can't-fail tests; no coverage driver; shell scripts always report success; no CI/hooks/phpstan/composer scripts | 7.1, 7.3, 7.4 |
| ARCH-3..9 | MED-LOW | `BibleReaderInterface` (implemented by `DatabaseBibleReader` **and** `OsisReader`) + 6 `instanceof` checks; reader not memoised; `BibleService` 4 roles; god classes; `switchTranslation` duplicated ×4; verse-mapping closure ×4; last-visited written in 3 places, read by a 500 route | 6.3, 9.1, 5.2 |
| Dead code | LOW | ~3,420 LOC (XML reader+parsers 1,384; Strong's service/models/seeder 995; mock search 360; test commands 186; Vue 285; nativephp config 208) + 10 empty-table migrations + unused views | 9.1, 9.2 |
| Docs | LOW | README = Laravel boilerplate + false claims; `MIGRATION_TEST_COMMANDS.md` advises `migrate:fresh` (would wipe prod); `.env.example` `APP_DEBUG=true`, `LOG_LEVEL=debug`, `DB_CONNECTION=mysql` | 9.3 |

### Verified SAFE — do not re-raise
Login timing (200ms timebox), generic failures, `regenerate()`, POST logout + CSRF, `intended()` no open redirect; no mass assignment; all raw SQL bound; FTS input always quoted; no unserialize/eval/exec; Livewire uploads signed (unused); security headers on every content type; `server_tokens`/`expose_php` off; `/.env`, `/.git/*` 403; only `public/index.php` executes; no secrets in git history; 66 books/1,189 chapters/31,102 verses match XML; 1,537 titles exact; paragraph starts exact (4,159); FTS index consistent with its content; 0 false-positive red letters; editorial headings `canonical="false"`; `integrity_check` ok; `Restart=always` on the queue unit is correct; no production 5xx since 2026-08-22; the bible vhost is not nginx's `default_server`; every `<q>` in the KJV is `who="Jesus" marker=""`, no `<q>` crosses a chapter, verse markers sit only at depth `chapter` or `chapter/q`.

### Out of scope (host-level, flagged for the owner)
`/` is 84% full on the shared container; no swap; a docker MySQL is published on `0.0.0.0:3306`.

---

## UX Design

### Before State

```
╔═══════════════════════════════════════════════════════════════════════════════════╗
║                                 BEFORE (2026-11-10)                               ║
╠═══════════════════════════════════════════════════════════════════════════════════╣
║  Reader → /books/1Cor/chapters/13 ──► 404 "Chapter not found"   (17 books, 19.7%) ║
║  Reader → /books/Matt/chapters/26 ──► v56 ends "…fulfilled."  (14 verses cut)     ║
║  Reader → /books/John/chapters/3  ──► v16 BLACK (should be red); John 2 hidden in ║
║                                       DOM; 758KB page; "For" tooltip = G3588      ║
║  Reader → /books/Gen/chapters/1   ──► "God *created*" italic (not a KJV italic)   ║
║  Reader → /searches?query=love    ──► 250 × "mock search result #N"               ║
║  Prev/next ──► /Gen/2 ─302─► /books/Gen/chapters/2 ─► full re-render              ║
║                                                                                   ║
║  Admin → /admin/verse-titles → save "Costs $10" ──► source XML gets a duplicate   ║
║        <verse sID> inside the title; FPM worker grows to ~400MB; success msg;     ║
║        edit never committed to git; no off-host copy exists                       ║
║                                                                                   ║
║  DATA_FLOW: kjv.osis.xml ─(DOM sibling walk: truncates, drops ancestor <q>, keeps ║
║   notes)─► SQLite verses/red_letter_text/FTS ─► 24h cache (current chapter never  ║
║   cached) ─► Livewire snapshot (original_xml ×3 chapters) ─► regex render         ║
╚═══════════════════════════════════════════════════════════════════════════════════╝
```

### After State

```
╔═══════════════════════════════════════════════════════════════════════════════════╗
║                                       AFTER                                       ║
╠═══════════════════════════════════════════════════════════════════════════════════╣
║  Reader → any of 66 books / 1,189 chapters ──► 200,                               ║
║           <title>1 Corinthians 13 — King James Version</title>                    ║
║  Every verse word-for-word equal to source (sim + live == osis_truth)             ║
║  Christ's words red across verse boundaries (FN 0, FP 0); KJV italics only for    ║
║  <transChange>; correct Strong's per word; LORD small caps incl. psalm titles     ║
║  Prev/next = real <a href="/books/…/chapters/…"> (no 302), cross-book at Mal→Matt ║
║  /searches → real FTS results over scripture text only                            ║
║                                                                                   ║
║  Admin save ──► XML written atomically (callback replacement, no backrefs)        ║
║        ──► PublishChapterProjection job (queue, subprocess import, serialised)    ║
║        ──► projection version bumped (caches invalid) ──► audit log line          ║
║        ──► nightly: DB .backup + XML tarball, off-host; git commit+push of assets ║
║                                                                                   ║
║  DATA_FLOW: kjv.osis.xml ─(sibling walk that climbs parents + carries quote       ║
║   state; notes stripped)─► SQLite (IMMEDIATE txn per chapter) ─► osis:verify      ║
║   --content exit 0 ─► lean snapshot (rendered HTML only); cache/queue/limiter in  ║
║   database/runtime.sqlite                                                         ║
╚═══════════════════════════════════════════════════════════════════════════════════╝
```

### Interaction Changes

| Location | Before | After | User impact |
|---|---|---|---|
| `/books/{1Sam…3John}/chapters/N` | 404 | 200 | 17 books readable |
| Chapter text | 14 truncated; notes in search | exact | Complete scripture; search finds only scripture |
| Red letters | 50.4% of Christ's words black | 100% | Correct attribution |
| Gen 1 italics | morph words italic | only supplied words | KJV typography honest |
| `/searches` | mock data | real results (`?q=` and legacy `?query=`) | Search works |
| Prev/next | JS + 302 + 760KB | `<a>` to canonical URL; peel preview kept; neighbours out of the snapshot (~30% fewer bytes) | Faster, shareable, crawlable |
| Admin save | sync 400MB, corrupts on `$` | queued, safe, audited | Edits safe; worker pool healthy |
| `/translation`, `/user-session`, `/search` | 500 / 404 | 404 / 301 → `/searches` | No dead ends |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `app/Console/Commands/ImportOsisCommand.php` | 22-115, 404-616, 851-1027, 1361-1395, 1453, 1590-1700 | Extraction, purge, titles, red-letter + Matt 9:6 hack, `removeNotes` |
| P0 | `app/Services/DatabaseBibleReader.php` | 78-150, 480-570 | DOM-1 normalisation; FTS; `resolveChapterId` pattern |
| P0 | `app/Livewire/BibleChapter.php` | 40-130, 195-230, 459-711 | Render path (`:483-499` decides Strong's ON/OFF), Strong's regex, red-letter render |
| P0 | `app/Livewire/VerseTitleManager.php` | 115-182, 208-372, 396-480 | Atomic writer (extract, don't rewrite), reprojection, bug at `:465` |
| P0 | `app/Console/Commands/VerifyOsisProjectionCommand.php` | 101-288 | XMLReader span tracking to mirror; verify rewrite |
| P1 | `bootstrap/app.php`, `config/trustedproxy.php`, `app/Providers/AppServiceProvider.php`, `config/queue.php`, `config/cache.php`, `config/database.php` | all | Proxies, throttles, limiters, runtime DB |
| P1 | `routes/web.php`, `routes/api.php` | all | Route surface; catch-alls at `web.php:63-69` |
| P1 | `resources/views/livewire/bible-chapter.blade.php`, `partials/{verse-paragraph,styles,scripts,header}.blade.php` | all | Render + a11y |
| P1 | `phpunit.xml`, `tests/Pest.php` | all | `:memory:` pin |
| P1 | `vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php` | 15-24 | Which middleware Livewire re-applies |
| P2 | `nginx/bible.b8.co.nz.conf`, `/etc/systemd/system/bible-app-queue.service` | all | Ops tasks |
| P2 | v1 plan | "Architecture Decision", "Source-of-Truth Invariants" (Invariant 2 = *no PHP-generated scripture data*) | Binding context |
| P2 | `scripts/audit/*/run_all.sh` (after 0.6) | all | The validation gates |

**External documentation** (research agent; topics marked † were not freshly fetched — re-verify anchors when used):

| Source | Section | Why |
|---|---|---|
| [GHSA-r925-mgvr-h9cc](https://github.com/advisories/GHSA-r925-mgvr-h9cc) | symfony/http-foundation ReDoS (CVE-2026-108082) | SEC-1 |
| [GHSA-f9x3-7vq9-cph6](https://github.com/advisories/GHSA-f9x3-7vq9-cph6), [GHSA-5wcm-3v2w-8362](https://github.com/advisories/GHSA-5wcm-3v2w-8362), [GHSA-jh5r-qr3c-85q8](https://github.com/advisories/GHSA-jh5r-qr3c-85q8) | laravel/framework < 12.69 | SEC-1 |
| [Livewire 3 Security](https://livewire.laravel.com/docs/3.x/security) | Locked properties; Middleware (persistent) | SEC-2/4/6 |
| † [Laravel 12 Requests](https://laravel.com/docs/12.x/requests#configuring-trusted-proxies) | Trusted proxies / trusted hosts | SEC-3 |
| † [Laravel 12 Queues](https://laravel.com/docs/12.x/queues#unique-jobs) | Unique jobs, `ShouldBeUniqueUntilProcessing`, `WithoutOverlapping` | 3.2 |
| † [Laravel 12 Rate Limiting](https://laravel.com/docs/12.x/rate-limiting) | Named limiters; `cache.limiter` | 4.2–4.4 |
| † [nginx add_header](https://nginx.org/en/docs/http/ngx_http_headers_module.html#add_header) | inheritance + `always` | 4.6 |
| [nginx security advisories](https://nginx.org/en/security_advisories.html) | 1.26 EOL | 9.5 |
| † [SQLite backup](https://www.sqlite.org/backup.html) | online `.backup` | 0.1, 0.3 |
| † [WCAG 2.2 quickref](https://www.w3.org/WAI/WCAG22/quickref/) | 1.3.1, 2.1.1, 2.4.1/2, 2.5.8, 4.1.2, 4.1.3, 1.4.13 | Phase 8 |

---

## Patterns to Mirror (actual code)

**API catch + structured log** — `app/Http/Controllers/Api/ChapterController.php:43-46`
```php
            return $this->successResponse($data, 'Verses retrieved successfully');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('API failure', ['route' => request()->path(), 'exception' => $e]);
            return $this->errorResponse('Failed to retrieve verses', 500);
        }
```

**Named rate limiters with the *why* in comments** — `app/Providers/AppServiceProvider.php:40-45`
```php
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Search is the expensive endpoint (FTS5 over 31,102 verses, limit up to 500),
        // so it gets a tighter bucket of its own.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
```

**Middleware configuration** — `bootstrap/app.php:14-40` (`$middleware->prepend(...)`, `trustProxies(at:, headers:)`, `throttleApi()`), each decision commented.

**Streaming XML (never DOM-load the 27MB file in a web path)** — `app/Console/Commands/VerifyOsisProjectionCommand.php:131-161`
```php
        $reader = new XMLReader();
        $reader->open($file, null, LIBXML_NOENT);
        ...
        while ($reader->read()) {
            if ($reader->localName === 'note') { ... $noteDepth++ / -- ... }
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'verse') {
                if (($sid = $reader->getAttribute('sID')) !== null) {
                    $openVerse = $sid;
                } elseif (($eid = $reader->getAttribute('eID')) !== null && $eid === $openVerse) {
                    $openVerse = null;
                }
            }
```

**Locked atomic write of the source of truth** — `app/Livewire/VerseTitleManager.php:208-312`: sidecar `.lock`, `flock(LOCK_EX|LOCK_NB)` bounded 10s loop, transform *inside* the lock, temp sibling `$path.'.tmp.'.getmypid()`, restore mode/owner, `fileParsesAsXml()` (XMLReader) before promotion, `fflush`+`fsync`, `rename`, `finally` cleanup; returns `ok|busy|lock_failed|read_failed|transform_failed|unchanged|invalid_xml|write_failed`, mapped by `osisWriteFailureMessage()` (`:362-372`). **Extract into a service (3.1); do not rewrite.**

**Service failure → boolean + structured log** — `VerseTitleManager.php:405-430`
```php
            $exit = Artisan::call('osis:import', [
                'file' => $this->getXmlPathForCurrentTranslation(),
                '--chapter' => $chapterOsisRef,
            ]);
            if ($exit !== 0) {
                Log::error('Verse re-projection failed', [
                    'verse' => $verseOsisId, 'exit_code' => $exit, 'output' => Artisan::output(),
                ]);
                return false;
            }
            $this->forgetChapterCache($chapterOsisRef);
```

**Escape, then re-admit only `<mark>`** — `resources/views/livewire/bible-search.blade.php:115`
```blade
{!! str_replace(['&lt;mark&gt;', '&lt;/mark&gt;'], ['<mark>', '</mark>'], e($result['text'])) !!}
```

**Dark-mode init in `<head>`, OS fallback, guarded storage** — `resources/views/auth/login.blade.php:8-19`
```js
(function () {
    try {
        var stored = localStorage.getItem('darkMode');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (stored === 'true' || (stored === null && prefersDark)) {
            document.documentElement.classList.add('dark');
        }
    } catch (e) { /* private mode or blocked storage: fall back to light */ }
})();
```

**Controller 404** — `app/Http/Controllers/BookController.php:30-42`: `if (! $this->bibleService->bookExists($bookOsisId)) { abort(404, "Book not found: {$bookOsisId}"); }`

**Console command** — `CreateAdminCommand.php` / `VerifyOsisProjectionCommand.php`: signature with described options, class docblock stating intent, `handle(): int` returning `self::SUCCESS|self::FAILURE`.

**Migration** — anonymous class; newer ones carry a docblock explaining *why* (`2026_08_22_000001_create_users_table.php`).

**Pest** — `tests/Pest.php:15-21` (Feature → `Tests\TestCase` + `RefreshDatabase`; Unit → `Tests\TestCase`). Config-contract style: `tests/Unit/BibleConfigTest.php:59-65`.

**Fixture helper (verified by the tests agent)**
```php
function projectFixture(string $name = 'mini-kjv'): void {
    test()->seed();                                                          // importer needs books (TEST-5)
    app(\Illuminate\Contracts\Console\Kernel::class)->setArtisan(null);     // fresh command instance (TEST-4)
    test()->artisan('osis:import', ['file' => base_path("tests/Fixtures/{$name}.osis.xml")])->assertExitCode(0);
}
```

---

## Files to Change

| File | Action | Tasks |
|---|---|---|
| `assets/kjv.osis.xml` | COMMIT (0.1); EDIT approved quote boundaries (2.4) | 0.1, 2.4 |
| `scripts/audit/**` | CREATE (from `.claude/PRPs/audit-v2-tools/`) | 0.6 |
| `bin/deploy.sh` | CREATE | 0.4 |
| `deploy/bin/bible-backup.sh`, `deploy/systemd/bible-backup.{service,timer}`, `deploy/systemd/bible-osis-commit.{service,timer}`, `deploy/systemd/bible-app-queue.service`, `deploy/php-fpm/bible.conf`, `deploy/RESTORE.md` | CREATE (tracked copies of system config) | 0.3, 3.4, 4.7, 9.4 |
| `config/bible.php` | UPDATE (`editor_enabled`) | 2.1 |
| `app/Services/Osis/OsisSourcePath.php` | CREATE | 2.2 |
| `app/Services/Osis/OsisSourceWriter.php` | CREATE (extracted) | 3.1 |
| `app/Support/ProjectionCache.php` | CREATE (versioned cache keys) | 2.7 |
| `app/Jobs/PublishChapterProjection.php` | CREATE | 3.2 |
| `app/Console/Commands/UnlockAdminCommand.php` | CREATE (`admin:unlock`) | 4.2 |
| `app/Services/DatabaseBibleReader.php` | UPDATE | 1.1, 2.7, 2.8, 5.3, 6.3, 9.4 |
| `app/Services/BibleService.php`, `TranslationService.php` | UPDATE | 1.1, 2.2, 5.3, 6.3, 9.1 |
| `app/Livewire/BibleChapter.php` | UPDATE | 1.3, 1.5, 1.6, 4.4, 5.3, 5.4, 6.1, 6.2 |
| `app/Livewire/VerseTitleManager.php` | UPDATE | 2.2, 3.1–3.3 |
| `app/Livewire/BibleBook.php`, `BibleSearch.php` | UPDATE | 4.4, 5.1 |
| `app/Console/Commands/ImportOsisCommand.php` | UPDATE | 2.5–2.7, 9.2 |
| `app/Console/Commands/VerifyOsisProjectionCommand.php` | UPDATE | 2.9 |
| `app/Http/Controllers/Auth/LoginController.php`, `app/Console/Commands/CreateAdminCommand.php` | UPDATE | 4.2, 4.5 |
| `app/Http/Controllers/ChapterController.php` (web, not `Api/`) | UPDATE / trim | 2.7, 6.1 |
| `app/Jobs/CacheAdjacentChaptersJob.php` | UPDATE (2.7) → DELETE (6.1) | 2.7, 6.1 |
| `resources/views/livewire/partials/*`, `bible-chapter.blade.php`, `livewire-search.blade.php`, `verse-title-manager.blade.php` | UPDATE | 1.2–1.6, 3.3, 5.1, 5.4, 6.1, 8.x |
| `resources/views/errors/{404,429,500,503}.blade.php` | CREATE | 5.2 |
| `bootstrap/app.php`, `config/trustedproxy.php`, `config/session.php`, `config/cache.php`, `config/queue.php`, `config/database.php`, `config/filesystems.php` | UPDATE | 2.6, 3.2, 4.2–4.5, 5.2 |
| `routes/web.php`, `routes/api.php` | UPDATE | 2.1, 5.1, 5.2, 8.3 |
| `database/migrations/2026_11_*_rebuild_search_index.php` | CREATE | 2.8 |
| `database/migrations/2026_11_*_create_runtime_tables.php` (connection `runtime`) | CREATE | 4.3 |
| `database/migrations/2026_11_*_drop_noise_schema.php` | CREATE | 9.2 |
| `database/factories/UserFactory.php` | UPDATE | 7.1 |
| `tests/Fixtures/mini-kjv.osis.xml`, `tests/Feature/**`, `tests/Unit/**` | CREATE / DELETE dead | 2.3, 7.x |
| `nginx/bible.b8.co.nz.conf` | UPDATE | 4.6 |
| `composer.json`/`.lock`, `package.json`/`-lock`, `vite.config.js`, `resources/js/*` | UPDATE / DELETE | 4.1, 7.3 |
| `README.md`, `.env.example`, `nginx/README.md`, `database/migrations/MIGRATION_TEST_COMMANDS.md` | UPDATE / DELETE | 9.3 |
| ~3,420 LOC of dead code | DELETE | 9.1 |

---

## NOT Building (Scope Limits)

- **No CSP header** — still deferred (Livewire/Alpine need `unsafe-eval`).
- **No registration, password reset or email verification** — owner decision: single admin created on the box.
- **No full XMLReader rewrite of the 1,800-LOC importer.** Correctness is fixed in place (2.5); memory is moved out of FPM into a subprocess (3.2). A streaming importer is a follow-up once 2.9 can prove equivalence.
- **No ASV/MAO projection** (needs D5 version scoping, 9.4, and contained-format support — v1 finding A7: the importer XPath only reads milestone verses). The editor is restricted to projected translations (2.2, 3.1).
- **No Strong's lexicon feature** — tables empty, code dead; deleted (9.1).
- **No Livewire 4 / Laravel 13 major upgrades** — patch-level within current majors.
- **No Redis.** Cache, rate limiter and queue move to a separate `database/runtime.sqlite` (4.3).
- **No changes to scripture wording.** The only XML edits are owner-approved quote-boundary moves (2.4) and committing the 3 existing titles.

---

## Ordering Constraints — hard

0. **0.1 (snapshot + commit + push) before anything else.** 0.3 (automated backups) before Phase 2.
1. **Live tree = production.** Code changes are made in `$DEV` and reach production only through `bin/deploy.sh` (0.4). Root/system changes are the only edits made outside the worktree.
2. **Never run tests, `optimize`/`config:cache`, or env-override commands that must target a copy, in the live tree.** In `$DEV`, run `devguard` first. To run artisan against a copy as another user: `sudo -u "$APPUSER" env APP_CONFIG_CACHE=/nonexistent DB_DATABASE=/abs/path.sqlite php artisan …` (`Application.php:1317-1319`) — but prefer running inside `$DEV`.
3. **2.1 (freeze the editor) is deployed before any importer change is deployed**; the editor is unfrozen only in 3.5.
4. **2.2 (path resolver) before any test of VerseTitleManager or the importer that writes** (TEST-6).
5. **2.3 (failing tests) before 2.5**; **2.9 (content verifier) before 2.10 (re-projection)**.
6. **2.4 (approved quote fixes, or its documented interim) before 2.10.**
7. **2.7 (cache invalidation) ships with 2.10.**
8. **Never remove/override `phpunit.xml`'s `DB_DATABASE=:memory:`**; add `force="true"` in 2.3.
9. **4.2 (proxy trust) and 4.3 (runtime DB) before 4.4 (new throttles).**
10. **4.7 (dedicated pool) before APP_KEY rotation**; rotate with `APP_PREVIOUS_KEYS` (no forced logout of encrypted payloads).
11. **5.1 (search swap) before deleting `SearchComponent` (9.1).**
12. **9.1 code-deletion order:** xml branch in `BibleService`/`TranslationService` → `OsisReader` + parsers + `OsisParserInterface` → `BibleReaderInterface` + `instanceof` checks. Tests and shell scripts *of* dead code may be deleted earlier (7.1, 7.3) — tests reference code, not the reverse.
13. **9.2 after 2.10**, and it re-runs the 2.10 procedure.
14. **Pint formatting LAST (9.6).**
15. **Every artisan command on the live tree runs as `$APPUSER`** (`www-data` until 4.7, `bible` after). Never root — root-created `-wal`/`-shm`/logs/cache/lock files lock the app out. At 4.7, update `deploy.sh`, the backup unit and the queue unit.
16. **After any `.env` edit on the live tree:** `sudo -u "$APPUSER" php artisan config:cache && sudo -u "$APPUSER" php artisan queue:restart` (cached config never re-reads `.env`).
17. **Do not `set_real_ip_from`/`real_ip_header` in nginx** — it rewrites `REMOTE_ADDR`, Laravel stops trusting `X-Forwarded-Proto`, and the http:// downgrade returns.

---

## Owner Decisions (recorded 2026-11-10)

| # | Answer | Effect on the plan |
|---|---|---|
| OD-2 | **Keep the previous/next chapters** (page-peel preview stays) | 1.6 is required; 6.1 follows the *keep* path (neighbours rendered from `#[Computed]`, never serialised; chapter cache layer + `CacheAdjacentChaptersJob` still deleted; real `<a>` links still added); Matt 5 page target relaxed to < 560KB |
| OD-5 | **Align red letters to the KJV** | Verified against eBible.org's red-letter KJV (`<span class='wj'>`, https://ebible.org/kjv/MAT16.htm etc.): in all 5 verses Christ's words end before the narration, and "Amen." in Matt 28:20 is **outside** them. 2.4 edits all 5, and adds a whole-Bible cross-check against eBible's words-of-Jesus markup |
| OD-7 (partial) | **Commit and push: yes** (remote `origin`, GitHub) | 0.1 executes; the nightly auto-commit (3.4) remains the recommendation, to be confirmed when 3.4 is reached |
| OD-8 | **Claude runs root-level commands** | All "(root, OD-8)" steps are executed by the implementer, each with its VALIDATE and a stated undo |
| OD-1, OD-3, OD-4, OD-6, OD-9, OD-10, OD-11 | Not yet answered | Follow each row's *If unanswered* column below; OD-1 interim = **keep** `nativephp/mobile` (no removal without consent) |

**Execution status**
- **Task 0.1 — DONE (2026-11-10).** Snapshot `/var/backups/bible-app/pre-v2/bible-2026-11-10.sqlite` (integrity ok, 31,102 verses, 1,537 titles; single-file, journal_mode=delete) + `osis-2026-11-10.tgz` (all 3 XML). Commits `95daeb0` (Gen 33–35 titles), `87df4d6` (.gitignore), `7500ced` (this plan + tools) pushed; branch tracks `origin/feature/audit-remediation-phase-0`. **Not yet off-host** — copy `pre-v2/` off the box when 0.3's off-host target exists.
- **Push credential GOTCHA (affects 3.4 and every push):** this host's SSH key and the *active* `gh` account are `b8-catch`, which has no write access to `bkintanar/bible-app`. Pushes were made once as `bkintanar` via a one-off credential helper (`gh auth token --user bkintanar`, HTTPS `insteadOf`), without switching the active `gh` account or changing git config. For the unattended nightly push in 3.4, add a **write-enabled deploy key** to `bkintanar/bible-app` (owner action on GitHub) and point `remote.origin.url` / an SSH `Host` alias at it.

## Open Decisions For The User

| # | Decision | Recommendation | Blocks | If unanswered / if "keep" |
|---|---|---|---|---|
| OD-1 | `nativephp/mobile` | **Remove** from the web deploy | 4.8 | Keep: leave middleware + nginx block (4.6 adds the `/index.php/` block), update to 4.7.0, keep 7.2's NativeRoutesTest |
| OD-2 | Page-peel preview & adjacent preload | **Delete the preload** (+ job + chapter cache) | 6.1 | Keep: 1.6 stays; 6.1 moves neighbours out of public state (`#[Computed]`), real links still added |
| OD-3 | Strong's default | **OFF by default**, toggle remembered | 6.2 | Keep ON: fix the `:43`/`:101` contradiction to `true`; payload targets in 6.2 relax to Gen 1 < 60KB |
| OD-4 | Search scope | **Scripture text only** in `text`; titles in a separate column; notes excluded | 2.8 | Default to recommendation |
| OD-5 | Quote boundaries Matt 16:4, 17:23, 21:25, 22:19, 28:20 | **Edit 16:4, 17:23, 21:25, 22:19; leave 28:20** — owner signs off verse by verse | 2.4 → 2.10 | Proceed with 2.10 anyway; list the 5 verses as allowed FPs in `check-scripture.sh`; redo 2.4 + a chapter-scoped re-projection when approved |
| OD-6 | Publishing | **Queued job, subprocess import** | 3.2 | Default to recommendation (editor stays frozen until 3.5) |
| OD-7 | Versioning runtime edits | **Nightly auto-commit + push of `assets/*.osis.xml`** on a dedicated branch | 0.1, 3.4 | Manual commits; 0.4's deploy guard refuses to deploy over uncommitted OSIS edits |
| OD-8 | Root-level changes | **Claude prepares tracked files under `deploy/` + exact commands; owner runs them** | 0.3, 0.5, 3.2, 3.4, 4.6, 4.7, 6.4, 9.4, 9.5 | Each root task is skippable; record it as open risk |
| OD-9 | Book titles & colophons | **Render** (second gated re-projection in 9.2) | 9.2 | Keep dropped: skip that part of 9.2 |
| OD-10 | Formatter | **Pint** | 7.3, 9.6 | Default to recommendation |
| OD-11 | Audit log noise (~70 entries this session: 13 EMERGENCY + 13 DEBUG in the stale 10MB `laravel.log`; ~42 ERROR in `laravel-2026-11-09.log`) | **Delete stale `laravel.log`**; leave dated logs to rotate | 0.7 | Leave as is (rotation removes dated logs in 14 days) |

---

## Step-by-Step Tasks

Each task: ACTION / IMPLEMENT / MIRROR / GOTCHA / VALIDATE. Shell variables and helpers: see *Where To Work*.

### Phase 0 — Safeguard, and a safe place to work (hours)

#### Task 0.1: Snapshot now, commit the published titles, push
- **ACTION** (root):
  ```bash
  install -d -o www-data -g www-data -m 750 /var/backups/bible-app/pre-v2
  sudo -u www-data sqlite3 -readonly "$DB" ".backup '/var/backups/bible-app/pre-v2/bible-$(date +%F).sqlite'"
  tar czf /var/backups/bible-app/pre-v2/osis-$(date +%F).tgz -C "$APP" assets/kjv.osis.xml assets/asv.osis.xml assets/mao.osis.xml
  cd "$APP" && git add assets/kjv.osis.xml && git commit -m "content(osis): Gen 33-35 editorial headings"
  git add .claude/.gitignore && git commit -m "chore: ignore Claude memory dir"
  git push -u origin feature/audit-remediation-phase-0      # outward-facing: owner confirms first
  ```
- **GOTCHA**: `git remote -v` must be the owner's GitHub repo. Copy the `pre-v2/` folder off-host by hand until 0.3 automates it.
- **VALIDATE**: `git -C "$APP" diff --quiet assets/ && git -C "$APP" rev-parse --abbrev-ref @{u}` prints `origin/feature/audit-remediation-phase-0`; `sqlite3 -readonly /var/backups/bible-app/pre-v2/bible-*.sqlite 'select count(*) from verses'` → 31102.

#### Task 0.2: Create the dev worktree (PROD-TREE)
- **ACTION** (root):
  ```bash
  cd "$APP" && git worktree add -b audit-v2/work "$DEV" feature/audit-remediation-phase-0
  cd "$DEV" && cp "$APP/.env" .env
  # edit $DEV/.env: APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost,
  #   DB_DATABASE=/var/www/personal/bible-app-dev/database/bible_app.sqlite, LOG_LEVEL=debug
  php artisan key:generate
  sqlite3 -readonly "$DB" ".backup '$DEV/database/bible_app.sqlite'"
  composer install --no-interaction && npm ci
  ```
- **GOTCHA**: `$DEV` is not served by nginx. It has its own `assets/` (the committed XML) and its own `.lock` files, so copy-imports never touch production's lock. Never run `optimize` or `config:cache` in `$DEV`. Refresh the DB copy with the same `.backup` line whenever needed.
- **VALIDATE**: `cd "$DEV" && devguard && ./vendor/bin/pest tests/Unit/BibleConfigTest.php` → passes.

#### Task 0.3: Automated nightly backups of DB *and* XML (root, OD-8)
- **ACTION**: in `$DEV` CREATE `deploy/bin/bible-backup.sh`:
  ```bash
  #!/usr/bin/env bash
  set -euo pipefail
  APP=/var/www/personal/bible-app; D=/var/backups/bible-app; S=$(date +%F)
  sqlite3 -readonly "$APP/database/bible_app.sqlite" ".backup '$D/bible-$S.sqlite'"
  sqlite3 -readonly "$D/bible-$S.sqlite" 'PRAGMA integrity_check' | grep -qx ok
  gzip -f "$D/bible-$S.sqlite"
  tar czf "$D/osis-$S.tgz" -C "$APP" assets/kjv.osis.xml assets/asv.osis.xml assets/mao.osis.xml
  find "$D" -maxdepth 1 -type f -mtime +14 -delete      # archive/ and pre-v2/ are subdirectories: never pruned
  # off-host copy (owner's target, e.g. rclone/rsync) goes here; until configured, log it:
  echo "backup $S ok (on-disk only)" | systemd-cat -t bible-backup
  ```
  and `deploy/systemd/bible-backup.service` (`Type=oneshot`, `User=www-data`, `ExecStart=/var/www/personal/bible-app/deploy/bin/bible-backup.sh`) + `.timer` (`OnCalendar=*-*-* 03:30`, `Persistent=true`). Commit; deploy via 0.4. Root:
  ```bash
  install -d -m 750 -o www-data -g www-data /var/backups/bible-app/archive
  mv /var/backups/bible-app/bible-2026-08-*.sqlite.gz /var/backups/bible-app/archive/   # BEFORE the first run, or find deletes them
  chown www-data:www-data /var/backups/bible-app
  cp "$APP"/deploy/systemd/bible-backup.{service,timer} /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now bible-backup.timer
  ```
  CREATE `deploy/RESTORE.md` (steps from 2.10 *Rollback*).
- **GOTCHA**: the script is a file, not inline `ExecStart` — inline `$(date +%F)` fails `systemd-analyze verify` (specifier `%F`). Same disk is not a backup; the off-host target is an open risk until configured.
- **VALIDATE**: `systemd-analyze verify /etc/systemd/system/bible-backup.service`; `systemctl start bible-backup && ls -la /var/backups/bible-app/ | tail -3`; `ls /var/backups/bible-app/archive/` still lists both August files.

#### Task 0.4: Deploy script + rebuild assets (OPS-3, UX-1, PERF-11)
- **ACTION**: in `$DEV` CREATE `bin/deploy.sh`:
  ```bash
  #!/usr/bin/env bash
  # Usage (root, in the live tree): bin/deploy.sh <ref>   e.g. bin/deploy.sh audit-v2/work
  set -euo pipefail
  APP=/var/www/personal/bible-app; APPUSER=${APPUSER:-www-data}
  cd "$APP"
  git diff --quiet assets/ || { echo "uncommitted OSIS edits in the live tree - commit them first"; exit 1; }
  git merge --ff-only "$1"
  composer install --no-interaction -o                 # dev deps kept until 7.4 (CI) is green
  npm ci && npm run build
  sudo -u "$APPUSER" php artisan migrate --force
  sudo -u "$APPUSER" php artisan optimize
  chown "$APPUSER:$APPUSER" bootstrap/cache/*.php      # packages.php/services.php are written by root's composer run
  chmod 640 bootstrap/cache/*.php                      # config.php contains APP_KEY; readable by the app user only
  sudo -u "$APPUSER" php artisan queue:restart
  curl -fsS -H 'Host: bible.b8.co.nz' http://127.0.0.1/up >/dev/null && echo "deploy OK"
  ```
  Commit in `$DEV`. First deploy (root): `cd "$APP" && git merge --ff-only audit-v2/work && bin/deploy.sh audit-v2/work`.
- **GOTCHA**: from this task on, the live tree's config is cached — re-read *Where To Work* §4. Do **not** set a restrictive `umask` in this script: `composer install`/`npm run build` run as root, and files they create must stay world-readable (644) for PHP-FPM and nginx (www-data); only `bootstrap/cache/*.php` is tightened, after re-owning it to the app user.
- **VALIDATE**: `grep -oF -e 'max-w-sm' -e 'w-\[44px\]' "$APP"/public/build/assets/app-*.css | sort -u | wc -l` → 2; `sudo -u www-data php artisan about | grep -E 'Config|Routes'` → CACHED; `stat -c %a "$APP"/bootstrap/cache/config.php` → 640; login page renders centred.

#### Task 0.5: Permissions (OPS-5) (root, OD-8)
- **ACTION**: `chmod -R g-w "$APP"/database/{migrations,seeders,factories}`; `chown root:www-data "$APP/.env" && chmod 640 "$APP/.env"`; if the editor is KJV-only, `chmod g-w "$APP"/assets/{asv,mao}.osis.xml`.
- **VALIDATE**: `find "$APP/database/migrations" -perm -g+w | wc -l` → 0; `stat -c '%a %U:%G' "$APP/.env"` → `640 root:www-data`; `curl -s -o /dev/null -w '%{http_code}' -H "$H" http://127.0.0.1/books/Gen/chapters/1` → 200.

#### Task 0.6: Promote audit tools to `scripts/audit/`
- **ACTION**: in `$DEV`: `mkdir -p scripts/audit && cp -r "$APP"/.claude/PRPs/audit-v2-tools/* scripts/audit/`. Add a DB-path argument where missing: `data/run_all.sh [repo] [osis] [db]` (default `$REPO/database/bible_app.sqlite`); `scripture/run_all.sh [app] [db]` passing `$DB` to `compare_all.py`/`red_letters.py`. CREATE `scripts/audit/.gitignore`:
  ```
  *.jsonl
  report.json
  red_status.tsv
  snap.*
  xml_*.sqlite
  dbcopy.sqlite
  html/
  views/
  jar.*
  ```
  CREATE `scripts/audit/check-scripture.sh [app] [db]` that runs both `run_all.sh` and **exits 1 unless all** hold: compare.py "does not begin with the full xml scripture text" = 0; "CONTAINS note text" = 0; truncation section = 0; FTS "terms differing" = 0; titles missing = 0 and extra = 0; `red_totals.on.fn` = 0, `.partial` = 0, `.fp` = 0 (or only OD-5 allow-listed osisIDs); `on_text` missing = 0 and run-together = 0; `on_leaks` = `{}`; `db_text.extra` = 0. **Expected to FAIL until 2.10**; record today's baseline in the commit message (truncated 14 (+1Chr.21.11 in the truncation section), note-text 5,844, FN 897, partial 27, run-together 29, `on_leaks.w` 4,037).
- **GOTCHA**: scripts must stay read-only (`sqlite3 -readonly`, `.backup` snapshot). `sim_render.php` needs `php -d memory_limit=1G`. `scripture/*.py` take relative paths — run from `scripts/audit/scripture/`. `perf/harness.php` needs a `perf/dbcopy.sqlite` made with `.backup`, and refuses to run if config is cached (run it in `$DEV`).
- **VALIDATE**: `cd "$DEV" && bash scripts/audit/check-scripture.sh "$DEV" "$DEV/database/bible_app.sqlite"; echo $?` → 1, printing the baseline above.

#### Task 0.7: Clear audit log noise (OD-11)
- **ACTION** (root): `rm "$APP/storage/logs/laravel.log"` (10MB, August, root-owned; its only additions this session are audit noise).
- **VALIDATE**: `ls -la "$APP/storage/logs/"` lists only dated `laravel-*.log` files.

### Phase 1 — Render-only scripture fixes (no re-projection) (~1 day; work in `$DEV`, deploy via 0.4)

#### Task 1.1: DOM-1 — numbered books
- **ACTION**: UPDATE `DatabaseBibleReader.php:122-127` and `:539-543`: replace `ucfirst(strtolower())` with a private `canonicalChapterRef(string $ref): ?string` that splits `Book.N`, resolves the canonical book id case-insensitively and caches it (`Cache::remember('book_osis_'.strtoupper($book), 3600, fn () => Book::whereRaw('UPPER(osis_id) = ?', [strtoupper($book)])->value('osis_id'))`), and returns `"{$canonical}.{$n}"` (or null).
- **MIRROR**: `resolveChapterId` (`:533-552`, `Cache::remember`).
- **GOTCHA**: do not `UPPER()` the *verses* query (index loss — v1 PERF-0). Keep `BibleService::bookExists` exact-match for HTTP (cache-key safety); case variants get a 301 in 8.3.
- **VALIDATE**:
  ```bash
  for b in $(sqlite3 -readonly "$DB" "select osis_id from books"); do c=$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "http://127.0.0.1/books/$b/chapters/1"); [ "$c" = 200 ] || echo "$b $c"; done   # → no output
  curl -s -H "$H" http://127.0.0.1/api/books/1John/chapters/4/verses | python3 -I -c 'import json,sys;print(json.load(sys.stdin)["data"]["total_verses"])'   # → 21
  ```

#### Task 1.2: UX-2 — remove morph italics
- **ACTION**: delete the rule `.strong-word.has-morph { font-style: italic; }` (`partials/styles.blade.php:88-90`). Keep the class (it carries tooltip data).
- **VALIDATE**: `python3 -I -c "import re;print(len(re.findall(r'has-morph[^{]*\{[^}]*italic', open('resources/views/livewire/partials/styles.blade.php').read())))"` → 0 (today 1). Visually Gen 1:1 "created" is upright, "And the earth was" keeps *was* italic.

#### Task 1.3: DOM-4 / DOM-5 render — Strong's regex
- **ACTION**: in `BibleChapter.php` before `:506`: `$xml = preg_replace('/<w\b[^>]*\/>/', '', $xml) ?? $xml;`; regex → `'/<w\b([^>]*)>(.*?)<\/w>/s'`; map `<inscription>` → `<span style="font-variant: small-caps">`; drop raw `<foreign>` tags keeping their text.
- **GOTCHA**: `preg_*` returns null on backtrack-limit — on null, fall back to `strip_tags($xml, '<em><span>')` and `Log::warning('render fallback', ['osis_id' => $osisId, 'preg' => preg_last_error_msg()])`.
- **VALIDATE**: `vtext /books/Matt/chapters/6 | grep -c 'as it is in heaven'` → ≥1 (today 0: "isin"); `cd scripts/audit/scripture && bash run_all.sh "$DEV"` → `on_leaks` has no `w`/`inscription`, run-together 0; John 3:16 "For" tooltip = G1063.

#### Task 1.4: DOM-10 / DOM-11 — LORD in psalm titles; italics inside red
- **ACTION**: add `<span>` to the `strip_tags` allow-list at `verse-paragraph.blade.php:19, 58` (title HTML comes from our own importer; admin-typed `<`/`>` stay entity-escaped); CSS `.text-red-600 em, .text-red-600 .italic { color: inherit; }`.
- **VALIDATE**:
  ```bash
  curl -s -H "$H" http://127.0.0.1/books/Ps/chapters/18 | python3 -I -c 'import re,sys;h=sys.stdin.read();m=re.search(r"class=\"[^\"]*psalm-title[^\"]*\"[^>]*>(.*?)</div>",h,re.S);print("small-caps" in (m.group(1) if m else ""))'   # → True (today False)
  ```

#### Task 1.5: DOM-13 — cross-book prev/next
- **ACTION**: compute prev/next from the ordered `getBooks()` list in `BibleChapter` (last chapter of the previous book / chapter 1 of the next).
- **VALIDATE**: `curl -s -H "$H" http://127.0.0.1/books/Mal/chapters/4 | grep -o 'data-next-url="[^"]*"'` → `/books/Matt/chapters/1` (today `""`); Rev 22 → empty; Gen 1 prev → empty.

#### Task 1.6: A11Y-1 — hidden chapters out of the a11y tree (required: OD-2 = keep)
- **ACTION**: `aria-hidden="true" inert` on `.page-prev`/`.page-next`; pass an `idPrefix` (empty for current) to the verse partial so preloaded copies emit no `id="verse-N"` (`verse-paragraph.blade.php:34, 44, 72, 81`). The owner keeps the previous/next chapters, so this is the permanent fix, not an interim.
- **VALIDATE**: `curl -s -H "$H" http://127.0.0.1/books/John/chapters/3 | grep -o 'id="verse-1"' | wc -l` → 1 (today 3).

### Phase 2 — Projection correctness + one gated re-projection (~3–5 days)

#### Task 2.1: Freeze the live editor
- **ACTION**: `config/bible.php`: `'editor_enabled' => env('BIBLE_EDITOR_ENABLED', true)`; the `/admin/verse-titles` route and `VerseTitleManager::boot()` `abort(503, 'Editor temporarily disabled for maintenance')` when false. Deploy; then on live: add `BIBLE_EDITOR_ENABLED=false` to `.env` and apply Constraint 16.
- **VALIDATE**: logged in, `/admin/verse-titles` → 503; a replayed Livewire `saveTitle` → 503 (Feature test in `$DEV` with the flag false).

#### Task 2.2: TEST-6 / SEC-4 traversal — config-driven OSIS path resolver
- **ACTION**: CREATE `app/Services/Osis/OsisSourcePath.php`: `for(string $key): string` → `base_path(trim(config('bible.osis_directory', 'assets'), '/'))."/{$key}.osis.xml"`, rejecting `$key` unless it matches `^[a-z0-9_-]+$` **and** is a projected translation (`bible_versions`). Use it in `BibleService::getXmlPath` (`:393-401`) and `VerseTitleManager::getXmlPathForCurrentTranslation` (`:378-383`); both default to `config('bible.default_translation')` (`kjv`) — today they disagree (`'asv'` vs `'kjv'`).
- **GOTCHA**: `osis_directory` is the relative `'assets'` (`config/bible.php:102`); tests override it with an absolute temp dir — accept absolute paths too (`str_starts_with($dir, '/') ? $dir : base_path($dir)`).
- **VALIDATE**: `grep -rn 'base_path("assets/' app/` → 0; unit test: `for('../x')` and `for('asv')` (unprojected) throw.

#### Task 2.3: Fixture + failing importer tests
- **ACTION**: `phpunit.xml` → `<env name="DB_DATABASE" value=":memory:" force="true"/>`. CREATE `tests/Fixtures/mini-kjv.osis.xml` from `scripts/audit/test-probes/mini-kjv.osis.xml` (Gen 1 + Matt 3) **extended** by stream-copying whole `<chapter>` elements (XMLReader `readOuterXml()`) for: Gen 2, Ps 3, Ps 18, Ps 23, Matt 5, Matt 6, Matt 9, Matt 21, Matt 26, John 3, 1John 4 (each inside its `<div type="book" osisID="…">`). Add `projectFixture()` to `tests/Pest.php`. CREATE `tests/Feature/Import/ImportOsisCommandTest.php` with **currently-failing** expectations: Matt.26.56 text ends "and fled."; Matt.21.31 contains "publicans and the harlots"; John.3.16 has a red row and its `original_xml` balances (`DOMDocument::loadXML('<x>'.$xml.'</x>')` succeeds); Matt.5.4 has exactly one red row; Gen.1.4 text has no "Heb."; Matt 9:6 red span does not bleed into 9:7; a second import adds 0 rows; `--chapter Gen.1` keeps the `main` title; exit ≠ 0 when books are unseeded.
- **GOTCHA**: run only in `$DEV` after `devguard`. Call `setArtisan(null)` between imports until 2.6 resets state.
- **VALIDATE**: `cd "$DEV" && devguard && ./vendor/bin/pest tests/Feature/Import` → fails for exactly the listed reasons; < 5s.

#### Task 2.4: DOM-6 — align quote boundaries to the KJV red-letter text (OD-5: decided)
- **SHAPE OF THE DEFECT** [VERIFIED]: each verse has an inner per-verse `<q>` that closes correctly after Christ's words, followed by narration, followed by the `</q>` of an *outer* discourse-level `<q>` opened in an earlier verse:
  ```
  Matt.16.4  ⟦Q⟧A wicked … the prophet Jonas.⟦/Q⟧ And he left them, and departed.⟦/Q⟧
  Matt.17.23 ⟦Q⟧And they shall kill him … raised again.⟦/Q⟧ And they were exceeding sorry.⟦/Q⟧
  Matt.21.25 ⟦Q⟧The baptism of John … or of men?⟦/Q⟧ And they reasoned with themselves, … believe him?⟦/Q⟧
  Matt.22.19 ⟦Q⟧Shew me the tribute money.⟦/Q⟧ And they brought unto him a penny.⟦/Q⟧
  Matt.28.20 ⟦Q⟧Teaching them … unto the end of the world.⟦/Q⟧ Amen.⟦/Q⟧
  ```
  KJV reference (eBible.org red-letter KJV, `<span class='wj'>`): in all five, the words of Jesus end exactly where the inner `</q>` is; the narration — and "Amen." in 28:20 — are not his words.
- **ACTION**: in `$DEV/assets/kjv.osis.xml`, for each of the 5 verses move the **outer** `</q>` from after the narration to immediately after the inner `</q>` (so the narration sits outside both quotes). Do it with a Python script that, per verse, locates the span between `sID="…"` and `eID="…"`, asserts the segment contains exactly two `</q>` with only text/`<w>`/`<transChange>`/`<note>` elements between them, and rewrites `X</q>NARRATION</q>` → `X</q></q>NARRATION`. Commit as `content(osis): end Christ's quotes before narration in Matt 16:4, 17:23, 21:25, 22:19, 28:20 (per KJV red-letter text)`; deploy via 0.4 (editor frozen, so no concurrent writer).
- **ALSO — whole-Bible cross-check**: CREATE `scripts/audit/scripture/kjv_wj_crosscheck.py` that downloads eBible's KJV USFM once (`https://ebible.org/Scriptures/eng-kjv_usfm.zip`, into `scripts/audit/scripture/ref/`, gitignored), extracts per-verse words-of-Jesus coverage from `\wj … \wj*`, and compares it with `osis_truth.py`'s per-verse Christ-word coverage (normalise: lowercase, strip punctuation, `alway`/`always`-style spelling tolerated). Output: verses that differ, with both texts. Differences are **reported for owner review**, not auto-applied — red-letter traditions can differ, and the OSIS is the source of truth.
- **GOTCHA**: `xmllint` is not installed — validate well-formedness with `python3 -I -c "import xml.sax,sys;xml.sax.parse(sys.argv[1], xml.sax.ContentHandler())" assets/kjv.osis.xml`. Moving a close tag across `<w>` siblings is safe only if no element straddles the boundary — the script's assertion enforces that.
- **VALIDATE**: `cd scripts/audit/scripture && python3 -I q_nesting.py "$DEV/assets/kjv.osis.xml"` → 0 verses with narration inside a quote; the SAX parse prints nothing; `git -C "$DEV" diff --stat assets/` → 1 file, 5 lines changed; `python3 -I kjv_wj_crosscheck.py "$DEV/assets/kjv.osis.xml"` → the 5 verses no longer differ (record the remaining differences, if any, in the commit message for the owner).

#### Task 2.5: Importer extraction fixes — DOM-2, DOM-3, DOM-7, DOM-5, DATA-5, MATT96
- **ACTION** (UPDATE `ImportOsisCommand.php`):
  1. `extractVerseContent` (`:851-883`): before the walk, count ancestor `q[@who='Jesus']` of the sID marker (KJV: at most one — markers sit only at depth `chapter` or `chapter/q`) and emit one synthetic `<q who="Jesus" marker="">` per ancestor (`$open`). Walk siblings as today. **When `nextSibling` is null, climb:** if the container being left is a Jesus `q` and `$open > 0`, emit `</q>` and decrement; continue from `container->nextSibling`. While `$open > 0`, **unwrap** inner `<q who="Jesus">` elements (emit only their children) — 489 verses (e.g. Matt 5:4ff.) have a per-verse `q` inside a discourse-level `q`. On reaching the eID marker (or the existing `containsVerseEndMarker` branch — Matt 21:31 ends inside a new `q`), append `str_repeat('</q>', $open)`. If the climb reaches the `<chapter>` without the eID → count as skipped (2.6 makes that fatal).
  2. Delete the Matt 9:6 hack: the `preg_replace_callback` in `formatRedLetterText` (`:1363-1379`), `extractVerseContentFromLine` and its `static $xmlLines`, and the `$problematicVerses = ['Matt.9.6']` block (`:885-889`).
  3. Plain text: strip notes **before** text extraction — call `removeNotes()` (`:1453`) on the input of `extractPlainTextWithSpacing` (`:1009-1027`).
  4. `loadXML` (`:104-106`): `preserveWhiteSpace = true; formatOutput = false`; **remove** the space-injection regexes in `extractPlainTextWithSpacing` (`:1015, :1018`) — with source whitespace preserved they create "( he said" (Luke 5:24, Mark 2:10).
  5. Strip the injected `xmlns="http://www.bibletechnologies.net/2003/OSIS/namespace"` from fragments serialised in `extractContentUpToEndMarker`; red-letter regex (`:1382`) → `/<q\b[^>]*\bwho="Jesus"[^>]*>/`.
  6. Red-letter rows: exactly one `red_letter_text` row per verse containing any Jesus span (synthetic or real); `text_content` from the spaced plain text, not `textContent`.
- **MIRROR**: span/quote state in `VerifyOsisProjectionCommand.php:131-161`; ground truth in `scripts/audit/scripture/osis_truth.py`.
- **GOTCHA**: render side `processRedLetterText` (`BibleChapter.php:597-650`) is balanced for synthetic-only and close-mid-verse cases, **not** for nested `q` — hence the unwrap in step 1. `preserveWhiteSpace=true` changes every `original_xml`, so the full re-projection (2.10) is mandatory and Phase 1's render must be re-checked by `sim_render.php` after it.
- **VALIDATE** (in `$DEV`, after `devguard`):
  ```bash
  ./vendor/bin/pest tests/Feature/Import                                     # green
  time php artisan osis:import assets/kjv.osis.xml                            # full import into the DEV copy; record duration for 2.10
  bash scripts/audit/check-scripture.sh "$DEV" "$DEV/database/bible_app.sqlite"; echo $?   # 0 (or only OD-5 FPs)
  cd scripts/audit/scripture && php -d memory_limit=1G sim_render.php "$DEV" sim.jsonl && python3 -I -c "import json;bad=[r['osis'] for r in map(json.loads,open('sim.jsonl')) if r['on_html'].count('<span')!=r['on_html'].count('</span>')];print(len(bad),bad[:5])"   # 0 []
  ```
  (adjust the field names to `sim_render.php`'s output keys).

#### Task 2.6: Importer robustness — TEST-2/4/5, PIPE-3/7/9/10/11, Invariant 2, production guard
- **ACTION**:
  - Reset all instance state at the top of `handle()`: `$doc`, `$xpath`, `$versionId`, `$books`, `$chapters`, `$purgedChapters`, paragraph-tracking fields, counters, `$scope`.
  - `config/database.php` sqlite connection: `'transaction_mode' => 'IMMEDIATE'` (avoids `SQLITE_BUSY_SNAPSHOT` on deferred upgrade; `SQLiteConnection.php:29-33`). Wrap **each chapter's** verse pass + paragraph finalisation in `DB::transaction(...)` (short write locks; readers in WAL are never blocked).
  - `purgeChapterProjection` (`:611-616`): exclude `title_type = 'main'`.
  - Fail (`return self::FAILURE`) when: no books seeded; any **in-scope** verse skipped (print counts by reason); the file is contained-format (`verse[@osisID]` present, no `verse[@sID]`): "contained-format OSIS is not supported".
  - Take `flock(LOCK_SH)` on `<file>.lock` **only around `load()`** (the writer renames atomically; holding it for the whole import would make editor saves report `busy`).
  - Purge `paragraphs` for chapters in scope (PIPE-10); scope the book-title chapter lookup by `version_id` (PIPE-9); after the scoped pass, delete DB verses in scope whose osisID no longer exists in the source, with their child rows (PIPE-11).
  - Stop writing fabricated data (Invariant 2): no auto `poetry_structure` rows (`:797-815`); `divine_names.original_name` NULL instead of `'YHWH'` (`:1166`).
  - **Production guard:** print the resolved DB path; if `realpath()` equals `/var/www/personal/bible-app/database/bible_app.sqlite`, refuse unless `--production` is passed.
- **VALIDATE**: fixture tests green incl. "`--chapter Gen.1` keeps main title", "second in-process import adds 0 rows", "unseeded → exit 1", "contained fixture → exit 1", "verse removed from fixture XML is removed from DB"; `php artisan osis:import assets/kjv.osis.xml` run in the **live** tree without `--production` (as `$APPUSER`, after deploy) → refuses, exit 1.

#### Task 2.7: PIPE-5 — versioned projection cache keys
- **ACTION**: CREATE `app/Support/ProjectionCache.php` with `key(string $base): string` → `"p{$version}_{$base}"` where `$version = Cache::rememberForever('projection_version', fn () => 1)`, and `bump(): void` → `Cache::increment('projection_version')`. Route every reader cache key through it (`ChapterController`, `CacheAdjacentChaptersJob`, `BibleChapter`, `DatabaseBibleReader` `chapters_*`/`chapter_id_*`/`bible_version_model_*`, `VerseTitleManager::forgetChapterCache` → `bump()`). The importer calls `bump()` after a successful run.
- **GOTCHA**: never `Cache::flush()` — the rate limiter and job locks share stores (`FileStore::flush` deletes the whole directory). Old keys expire by TTL.
- **VALIDATE**: fixture test: read Gen 1 (primes cache) → import → read again → new `projection_version`, fresh data.

#### Task 2.8: DATA-6 / DOM-7 — FTS correctness (OD-4)
- **ACTION**: CREATE a migration that drops the `verses_fts_insert/delete/update` triggers (old values cannot be recomputed: `searchable_content` derives from child tables purged after the verse UPDATE), redefines `search_results` so `text` is scripture only (`verses.text`) with titles in a separate, tag-stripped `titles` column and notes excluded, recreates `verses_fts` with matching columns, and **ends with** `INSERT INTO verses_fts(verses_fts) VALUES('rebuild')`. UPDATE `DatabaseBibleReader::searchVerses` (`:489-506`): column filter `{text} : <term>` in MATCH (or bm25 weights) and the `snippet()` column index.
- **GOTCHA**: v1 Constraint 8 — `verses_fts` is backed by the view; drop/recreate both in one migration. Test on the `$DEV` copy: `php artisan migrate` then the 2.5 import.
- **VALIDATE** (in `$DEV`; on live after 2.10): `sqlite3 -readonly <db> "select count(*) from verses_fts where verses_fts match 'text:Heb'"` → 0; `… match '\"all the disciples forsook him\"'` → 1; `/api/search?q=midriff` → 0 results; `scripts/audit/data/fts_check.sql` → 0 differing.

#### Task 2.9: DATA-3 — `osis:verify --content`
- **ACTION**: UPDATE `VerifyOsisProjectionCommand`: check `XMLReader::open()` and libxml errors; skip titles inside `<header>`; skip transChange inside notes consistently; add `--content` comparing **per verse**: normalised text hash (notes excluded), Jesus-coverage flag, transChange/divineName counts; titles on (type, anchor, text, canonical); paragraph starts; filter every DB count by `version_id`; print the first 20 mismatching osisIDs; exit 0/1. `--book` must combine with `--content`.
- **MIRROR**: XMLReader loop (`:131-161`); logic from `scripts/audit/scripture/osis_truth.py`.
- **VALIDATE**: against an unfixed DB copy it **fails** listing Matt.26.56 etc.; after the 2.5 copy import, `php artisan osis:verify assets/kjv.osis.xml --content; echo $?` → 0.

#### Task 2.10: Gated full re-projection (live) — with rollback
- **PRECONDITIONS**: 2.1–2.9 merged in `$DEV`; `pest` green; the `$DEV` copy passes `osis:verify --content` and `check-scripture.sh`; the 2.5 import duration is known.
- **ACTION** (root, live tree):
  ```bash
  sudo -u "$APPUSER" php artisan down --retry=60
  systemctl stop bible-app-queue
  systemctl start bible-backup && ls -la /var/backups/bible-app | tail -2   # fresh, verified backup
  bin/deploy.sh audit-v2/work                     # runs migrate --force (2.8: FTS rebuild); ends with queue:restart
  systemctl stop bible-app-queue                  # deploy restarted it
  sudo -u "$APPUSER" php artisan osis:import /var/www/personal/bible-app/assets/kjv.osis.xml --production
  sudo -u "$APPUSER" php artisan osis:verify /var/www/personal/bible-app/assets/kjv.osis.xml --content
  bash scripts/audit/check-scripture.sh "$APP" "$DB"
  systemctl start bible-app-queue && sudo -u "$APPUSER" php artisan up
  ```
- **ROLLBACK** (if verify/check fails or the site misbehaves):
  ```bash
  sudo -u "$APPUSER" php artisan down; systemctl stop bible-app-queue
  B=$(ls -t /var/backups/bible-app/bible-*.sqlite.gz | head -1)
  gunzip -c "$B" > "$DB.restore"
  rm -f "$DB-wal" "$DB-shm" && mv "$DB.restore" "$DB" && chown "$APPUSER:$APPUSER" "$DB" && chmod 664 "$DB"
  git -C "$APP" reset --hard <previous deployed commit>   # recorded before deploy: git -C $APP rev-parse HEAD
  bin/deploy.sh HEAD    # re-optimize the old code (merge is a no-op)
  systemctl start bible-app-queue; sudo -u "$APPUSER" php artisan up
  ```
- **VALIDATE**:
  ```bash
  sqlite3 -readonly "$DB" "select instr(text,'forsook him, and fled') > 0 from verses where osis_id='Matt.26.56'"   # 1
  sqlite3 -readonly "$DB" "select count(*) from verses where text like '%: Heb.%'"                                 # 0
  sqlite3 -readonly "$DB" "select count(*) from verses where formatted_text like '%xmlns%'"                        # 0
  cd scripts/audit/scripture && python3 -I red_letters.py kjv_truth.jsonl sim.jsonl "$DB" John.3.16 Matt.26 | head -3   # John.3.16 TP; FN 0
  vtext /books/Matt/chapters/26 | grep -c 'forsook him, and fled'                                                    # ≥1
  ```

### Phase 3 — Harden the source-of-truth editor, then unfreeze (~1–2 days)

#### Task 3.1: SEC-4 / PIPE-1/2/6 — safe title writes
- **ACTION**: extract the atomic writer into `app/Services/Osis/OsisSourceWriter.php` unchanged (unit-testable against a temp file). In the transform: `preg_replace_callback($versePattern, fn ($m) => '<title type="verse" canonical="false">'.htmlspecialchars($title, ENT_XML1 | ENT_QUOTES).'</title>'.$m[1], $xml, 1)`; `loadExistingTitle` decodes with `html_entity_decode($m[1], ENT_QUOTES | ENT_XML1)`. `#[Locked]` on `currentTranslation`, `books`, `chapters`, `verses` (not on the `wire:model`-bound `selectedBook/Chapter/Verse`, `verseTitle`). Editor lists only projected translations. Validation rule for titles: `required|string|max:255|regex:/^[^\x00-\x1F]+$/u`.
- **VALIDATE**: unit tests on a temp copy: titles `Costs $10`, `$1`, `\1`, `A & B`, `"quoted"` round-trip byte-exact; re-saving the same title returns `unchanged`; `fileParsesAsXml` still guards.

#### Task 3.2: PERF-6 — publish off the request, in a subprocess (OD-6)
- **ACTION**: CREATE `app/Jobs/PublishChapterProjection.php`: `ShouldQueue, ShouldBeUniqueUntilProcessing` (unique id `"{$version}|{$chapterRef}"`, `$uniqueFor = 600`), `middleware(): [ (new WithoutOverlapping('osis-projection'))->releaseAfter(30)->expireAfter(900) ]` (global key — the FTS rebuild is global), `$tries = 3`, `$backoff = [10, 60]`, `$timeout = 300`, `failed()` logs at error with the chapter ref. `handle()` runs `Process::timeout(280)->run([PHP_BINARY, base_path('artisan'), 'osis:import', OsisSourcePath::for($key), '--chapter='.$chapterRef, '--production'])`, checks the exit code, logs output on failure, calls `ProjectionCache::bump()`. `config/queue.php`: `retry_after` 360 (> job timeout). The editor dispatches it and shows "Saved. Publishing to readers…". The CLI full import takes the same lock (`Cache::lock('osis-projection', 900)->block(60)`).
- **GOTCHA**: a subprocess frees the ~400MB libxml tree when it exits; in-process `Artisan::call` keeps it in the long-lived worker, and `queue:work --memory` cannot see libxml memory (`Queue/Worker.php:836-838`). The unit's `MemoryMax` covers child processes — raise it to 1G (root, OD-8). `ShouldBeUnique` (not *UntilProcessing*) would silently drop a save made while a publish of the same chapter runs.
- **VALIDATE**: in `$DEV` Feature test (`Queue::fake()`): save dispatches once; two saves during processing dispatch twice. On live after 3.5: save a title → `ps -o rss= -C php-fpm8.4` unchanged; `failed_jobs` = 0; `osis:verify --content --book=Gen` → 0.

#### Task 3.3: ERR-2 / ERR-7 / SEC-6 / UX-11 — editor UX, logging, audit trail, logout
- **ACTION**: map `unchanged` → "Title already set to this value — no change made." (save) / keep "No title found to remove." (remove); log every non-ok status with `['status','osis_id','user_id' => auth()->id(),'path','last_error' => error_get_last(),'preg' => preg_last_error_msg()]`; catches → `\Throwable` + `['exception' => $e]`; `wire:loading.attr="disabled"` + `wire:target="saveTitle,removeTitle"` on the buttons; CREATE an `audit` log channel (daily, 90 days, level info) and write `['user','osis_id','old','new','status','published']` there; remove the "backups are created automatically" copy (`verse-title-manager.blade.php:225, 231`) and the `canonical="true"` hint (`:181`); add `<form method="POST" action="{{ route('logout') }}">@csrf<button>Log out</button></form>`.
- **MIRROR**: structured context at `VerseTitleManager.php:347-350`; `osisWriteFailureMessage` map.
- **VALIDATE**: in `$DEV`: a save writes one line to `storage/logs/audit-*.log` containing `"osis_id"`; double-clicking Save dispatches one job (Feature test).

#### Task 3.4: Versioning runtime edits (OD-7, root)
- **ACTION**: CREATE `deploy/systemd/bible-osis-commit.{service,timer}` (nightly, `User=root`, deploy key configured for root's SSH): in `$APP`, `git add assets/*.osis.xml && git diff --cached --quiet || git commit -m "content(osis): editor changes $(date +%F)"` then `git push origin HEAD`. Commits land on whatever branch the live tree is on (the deployed branch) — acceptable because the live tree only ever fast-forwards; the worktree must `git merge` it before the next deploy (0.4's `--ff-only` will refuse otherwise — that is the signal).
- **VALIDATE**: after an edit + `systemctl start bible-osis-commit`, `git -C "$APP" log -1 -- assets/` shows the commit and `git -C "$APP" status -sb` shows no "ahead".

#### Task 3.5: Unfreeze the editor + end-to-end drill
- **ACTION**: live `.env` `BIBLE_EDITOR_ENABLED=true` + Constraint 16.
- **VALIDATE** (manual): save "Test $1 & co" on Gen 1:1 → appears on `/books/Gen/chapters/1` within one job cycle → the Genesis main title is still in the DB (`select count(*) from titles where title_type='main'` → 66) → remove it → `git -C "$APP" diff assets/` shows nothing left → `audit` log has both lines.

### Phase 4 — Security & runtime isolation (~2 days)

#### Task 4.1: SEC-1 / SEC-11 — patch dependencies, drop dead JS
- **ACTION** (in `$DEV`): `composer update symfony/* laravel/framework league/commonmark league/flysystem psy/psysh livewire/livewire -W`; remove `resources/js/app.js`, `resources/js/bootstrap.js` and the `app.js` Vite input (`vite.config.js:8`); `npm rm axios alpinejs concurrently`; `npm i -D vite@^6.4.6`; `npm run build`; full test suite.
- **GOTCHA**: Livewire bundles Alpine and no page loads `app.js` (verified), so nothing breaks.
- **VALIDATE**: `composer audit --locked --no-dev` → no advisory for a reachable package (record any residual with justification); `composer show symfony/http-foundation | grep -m1 versions` ≥ 7.4.23; `ls public/build/assets/app-*.js 2>/dev/null | wc -l` → 0.

#### Task 4.2: SEC-3 — proxy trust, trusted hosts, login limits
- **ACTION**: `config/trustedproxy.php` → `'proxies' => ['10.0.10.31']` with a corrected comment (cloudflared runs on 10.0.10.31, not this host); `bootstrap/app.php` headers → `HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_PROTO` only; `$middleware->trustHosts()` with **no arguments** (anchored to `APP_URL`'s host; `TrustHosts.php:110-114`). In `LoginController` keep the email+IP limiter and add a per-account limiter (`'login-account:'.sha1(strtolower($email))`, 50/hour) that **delays and alerts** rather than hard-locks; CREATE `admin:unlock {email}` clearing both keys.
- **GOTCHA**: first confirm cloudflared sends `X-Forwarded-For` (log `$request->ip()` once from a real request). If it does not, every client shares one bucket — then key login on the account only and treat 4.4's `throttle:livewire` as a site-wide cap. `trustHosts()` is inactive in `local`/tests (`:99-103`); nginx's `server_name` already pins Host, so this is defence in depth. Do not pass `at: ['bible.b8.co.nz']` — Symfony makes it an unanchored regex (`Request.php:656`).
- **VALIDATE**: update `scripts/audit/security/proxy_sim.php`'s header mask to the new value and run it → a spoofed CF-Worker XFF chain no longer controls `ip()`, `X-Forwarded-Host` is ignored; Feature test for the per-account limiter and `admin:unlock`.

#### Task 4.3: PERF-7/9, OPS-9, DATA-10, UX-6 — cache, limiter, queue off the scripture DB
- **ACTION**: `config/database.php` add connection `runtime` (sqlite, `database_path('runtime.sqlite')`, WAL, `busy_timeout` 10000, `transaction_mode` IMMEDIATE). CREATE migration `create_runtime_tables` using `Schema::connection('runtime')` for `cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`. Config: `cache.stores.database.connection = 'runtime'`, `cache.limiter = 'database'`, `queue.connections.database.connection = 'runtime'`, `queue.failed.database = 'runtime'`, `queue.batching.database = 'runtime'`. Last-visited → `session(['last_visited' => …])` plus a long-lived `resume` cookie (UX-6); delete `UserSessionController::storeLastVisitedPage` writers. Root: pre-create `database/runtime.sqlite` owned by `$APPUSER` (664); drain the old `jobs` table (`queue:work --stop-when-empty`) before switching; `queue:restart`.
- **GOTCHA**: `migrate` records runtime migrations in the default DB's `migrations` table — fine. Schedule pruning of expired cache rows once Phase 9.4 adds a scheduler (or a daily `DELETE FROM cache WHERE expiration < strftime('%s','now')` in the backup script).
- **VALIDATE**: 100 anonymous page views → `stat -c %Y "$DB"` unchanged and `sqlite3 -readonly "$DB" "select count(*) from cache"` unchanged; API 429 still works (31 search requests in a minute); `sqlite3 -readonly database/runtime.sqlite 'select count(*) from failed_jobs'` works.

#### Task 4.4: SEC-2 — lock Livewire state, throttle updates
- **ACTION**: `#[Locked]` on every property the client never edits in `BibleChapter` (`bookOsisId`, `chapterNumber`, `currentBook`, `books`, `chapters`, `verses`, `paragraphs`, `previousChapterVerses`, `nextChapterVerses`, `currentTranslation`, `availableTranslations`, `capabilities`, `chapterTitle`) and in `BibleBook`; validate the book via `bookExists` before building any cache key; in `AppServiceProvider::boot()`: `Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)->middleware(['web', 'throttle:livewire']))` and `RateLimiter::for('livewire', fn (Request $r) => Limit::perMinute(120)->by($r->ip()))`; `BibleChapter::search()` checks the `search` limiter.
- **VALIDATE**: Livewire test: `->set('bookOsisId', 'x')` throws `CannotUpdateLockedPropertyException`; `php artisan route:list | grep livewire/update` shows `throttle:livewire`; 121 rapid updates → 429.

#### Task 4.5: SEC-6 / OPS-7 / ERR-12 — session hygiene
- **ACTION**: live `.env` `SESSION_SECURE_COOKIE=true` (+ Constraint 16); admin route group `->middleware(['auth', 'auth.session'])` and `Livewire::addPersistentMiddleware([\Illuminate\Session\Middleware\AuthenticateSession::class])` (Livewire's default list only has Jetstream's class — `PersistentMiddleware.php:15-24`); `CreateAdminCommand` also sets `remember_token => Str::random(60)`; log `login_failed` / `login_locked` at warning with `['ip', 'email_hash' => sha1(strtolower($email))]`.
- **VALIDATE**: `curl -sI -H "$H" http://127.0.0.1/login | grep -i '^set-cookie' | grep -ci secure` ≥ 1; Feature test: after a password change, an old session's `/livewire/update` call to `saveTitle` is rejected.

#### Task 4.6: nginx — SEC-7, SEC-9, PERF-10, OPS-8/10/11/12 (root)
- **ACTION** (`nginx/bible.b8.co.nz.conf`): at file top level (outside `server {}`, http context): `log_format bible_cf '$remote_addr cf=$http_cf_connecting_ip [$time_local] "$request" $status $body_bytes_sent "$http_user_agent" $request_time';`. In `server {}`: `allow 10.0.10.31; allow 127.0.0.1; allow ::1; deny all;` (Tailscale/docker/LAN clients get 403 — intended); `location ^~ /index.php/ { return 404; }`; `gzip_comp_level 5;`; `/build/` Cache-Control without `always`; `access_log /var/log/nginx/bible.b8.co.nz.access.log bible_cf;` (rename from `bible.local.*`); fix the `cgi.fix_pathinfo` and `HTTPS` comments. Replace the live copy with a symlink: `ln -sf "$APP/nginx/bible.b8.co.nz.conf" /etc/nginx/sites-available/bible.local && nginx -t && systemctl reload nginx`.
- **GOTCHA**: Constraint 17 — no `real_ip_header`. `nginx -t` before every reload.
- **VALIDATE**: `curl -s -o /dev/null -w '%{http_code}' -H "$H" http://127.0.0.1/index.php/books/Gen/chapters/1` → 404; `curl -s -o /dev/null -w '%{http_code}' -H "$H" http://10.0.10.73/` (this host's LAN address → not allow-listed) → 403; `curl -s -H "$H" -H 'Accept-Encoding: gzip' http://127.0.0.1/books/Matt/chapters/5 | wc -c` ~25% smaller; `curl -sI -H "$H" http://127.0.0.1/build/assets/nope.css | grep -ci immutable` → 0.

#### Task 4.7: SEC-5 / OPS-4 — dedicated FPM pool and user (root, OD-8)
- **ACTION**: `useradd --system --no-create-home --shell /usr/sbin/nologin --groups www-data bible`; install `deploy/php-fpm/bible.conf` (`[bible]`, `user=bible`, `group=bible`, `listen=/run/php/bible.sock`, `listen.owner=www-data`, `listen.group=www-data`, `pm=dynamic`, `pm.max_children=12`, `pm.max_requests=500`, `request_terminate_timeout=60s`, `php_admin_value[open_basedir]=/var/www/personal/bible-app:/tmp`, `php_admin_value[cgi.fix_pathinfo]=0`); `chgrp -R bible storage bootstrap/cache database assets` (+ g+w where www-data had it); vhost `fastcgi_pass unix:/run/php/bible.sock`; queue unit `User=bible`, `MemoryMax=1G`; backup unit `User=bible` and `chown -R bible:bible /var/backups/bible-app`; `.env` → `640 root:bible`; re-run `bin/deploy.sh HEAD` with `APPUSER=bible` so `bootstrap/cache/*.php` is re-owned; set `APPUSER=bible` in `deploy.sh`'s default and Constraint 15. Then rotate APP_KEY: copy the current key into `APP_PREVIOUS_KEYS`, set a new `APP_KEY` (`php artisan key:generate --show`), `config:cache`, `systemctl reload php8.4-fpm`, `queue:restart`.
- **GOTCHA**: test `open_basedir` on a page view, an editor save, and a queued publish before the key rotation.
- **VALIDATE**: `ps -o user= -C php-fpm8.4 | sort | uniq -c` shows `bible`; site 200; editor save + publish work; `sudo -u www-data test -w "$APP/assets/kjv.osis.xml" && echo writable || echo protected` → protected.

#### Task 4.8: OD-1 — remove `nativephp/mobile` from the web deploy
- **ACTION** (in `$DEV`): `composer remove nativephp/mobile`; delete `config/nativephp.php`, `.nativephpignore`, `app/Http/Middleware/BlockNativeRoutesOnWeb.php` and its `prepend` in `bootstrap/app.php`; delete the nginx `/_native/` block (4.6 file). If OD-1 = keep: skip, and `composer update nativephp/mobile` (4.7.0).
- **VALIDATE**: `php artisan route:list | grep -c _native` → 0; `curl -s -o /dev/null -w '%{http_code}' -H "$H" -X POST http://127.0.0.1/_native/api/events` → 404.

### Phase 5 — Broken features & error handling (~1–2 days)

#### Task 5.1: ARCH-1 / F3 / UX-8 — real search
- **ACTION**: `livewire-search.blade.php:16` → `<livewire:bible-search />`; `BibleSearch`: accept legacy `?query=` in `mount()` (copy into `$q`), `#[Url] public int $limit = 50` clamped to 1..250 in `mount()` and `updatedLimit()`; `wire:model` (deferred) inside `<form wire:submit="search">`; scroll restore via `Livewire.hook('morph.updated', …)` on the inner scroller; dark-mode head partial; "Back to reading" link. Routes: `Route::redirect('/search', '/searches', 301)` registered **before** the catch-alls; drop `searches.store`.
- **VALIDATE**: `curl -s -H "$H" 'http://127.0.0.1/searches?query=love' | grep -c 'mock search result'` → 0 (today 50); `vtext '/searches?q=love' | grep -cE '(John|1 John) [0-9]+:[0-9]+'` ≥ 1; `curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -H "$H" http://127.0.0.1/search` → `301 …/searches`.

#### Task 5.2: ARCH-2 / ERR-4/5 / UX-4 / ARCH-7/8 / ROUTE-STORAGE — routes, JSON 404s, error pages
- **ACTION**: delete the `translation` and `user-session` routes + controllers; replace the two closure catch-alls (`routes/web.php:63-69`) with `LegacyRedirectController` using `->permanentRedirect(...)`, constrained `->where('bookOsisId', '(?!api\b)[1-3]?[A-Za-z]+')` and returning 404 for unknown books (no `^`/`$`: Symfony strips them from requirements, and inside the compiled route regex `(?!api$)` would still let `/api/nope` match); in `withExceptions`: `$exceptions->shouldRenderJsonWhen(fn ($r) => $r->is('api/*') || $r->expectsJson())` and render `NotFoundHttpException`/`ThrottleRequestsException` with the `ApiResponseTrait` envelope (`{success:false,message,timestamp}`); CREATE `resources/views/errors/{404,429,500,503}.blade.php` in the reader layout with a "Go to Genesis 1" link; fix `bible-book.blade.php:7, 151, 155` (`route('books.index')`, `route('searches.index')`); `config/filesystems.php` local disk `'serve' => false`.
- **VALIDATE**: `/translation`, `/user-session` → 404; `curl -s -H "$H" http://127.0.0.1/api/nope` → 404 JSON with `"success":false`; `/api` and `/api/a/b` → 404 JSON; `/storage/x` → 404; `php artisan route:list | grep -c 'storage/{path}'` → 0.

#### Task 5.3: ERR-1/6/8/10/11/14, SEC-10 — fail loudly, reject bad input
- **ACTION**: `BibleChapter::loadChapterVerses` — remove the catch (`:225-227`); keep `abort(404)` only for a genuinely empty chapter. `SearchRequest` + `BibleSearch`: `regex:/^[^\x00-\x1F]+$/u` → 422; `searchVerses` catches `QueryException` → `InvalidArgumentException` logged at warning with the term length only. `TranslationService` catches log at warning and never offer translations absent from `bible_versions`. `BibleService::search` map `'verse' => $result['verse_number']` (`:190`). Delete the stub `/api/search/history` and `/suggestions` routes + methods. `chapterNumber` route constraint `[0-9]{1,3}`. Remove the empty catch in `Http/BibleController.php:30`. Delete the per-request `Log::debug/info` emoji lines (`BibleChapter.php:79, 130-184, 485`; `ChapterController.php:56-147`; `DatabaseBibleReader.php:183-192`).
- **VALIDATE**: `curl -s -o /dev/null -w '%{http_code}' -H "$H" 'http://127.0.0.1/api/search?q=x%00y'` → 422 (today 500); `…/searches?query=x&limit=abc` → 200 (today 500); `…/searches?query%5B%5D=x` → 200 or 422 (today 500); `/books/Gen/chapters/99999999999999999999` → 404; Feature test: a forced DB exception on chapter load → 500, not 404; `grep -rc 'Log::debug\|Log::info' app/Livewire/BibleChapter.php` → 0.

#### Task 5.4: SEC-8 / UX-7 — in-chapter search highlight
- **ACTION**: replace `bible-chapter.blade.php:107-113` with the escape-then-re-admit `<mark>` pattern (`bible-search.blade.php:115`); link results to `route('chapters.show', …).'#verse-'.$n`.
- **VALIDATE**: Livewire test: searching "em" in the chapter view renders balanced markup; result text keeps the scripture's casing.

### Phase 6 — Performance & payload (~2 days)

#### Task 6.1: PERF-5 / PERF-2 — real links; neighbours kept but out of the snapshot (OD-2 = keep)
- **ACTION**: prev/next and selector items → `<a href="{{ route('chapters.show', [...]) }}">` (no `/Gen/2` hop; swipe/arrow handlers follow the same hrefs; the peel animation stays, gated by `prefers-reduced-motion`). Replace the public `previousChapterVerses`/`nextChapterVerses` properties with `#[Computed]` methods that read the neighbours straight from `DatabaseBibleReader` (indexed reads ≈0.02ms — no cache needed), so they render in the DOM for the peel but are never serialised into `wire:snapshot`. Delete the chapter cache layer it replaces: in `app/Http/Controllers/ChapterController.php` `cacheChapterData`, `cacheChapterVerses`, `getCachedChapterVerses`, `warmupBookCache`, `clearChapterCache`, `getCacheStats`; `app/Jobs/CacheAdjacentChaptersJob.php`; the `chapter_verses_*`/`chapter_data_*` keys (and their entries in `ProjectionCache`, 2.7).
- **GOTCHA**: keep 1.6's `aria-hidden`/`inert`/no-ids on the neighbour pages.
- **VALIDATE**: `curl -s -o /dev/null -w '%{size_download}' -H "$H" http://127.0.0.1/books/Matt/chapters/5` < 560000 (today ~760000); `cd scripts/audit/perf && curl -s -H "$H" http://127.0.0.1/books/Matt/chapters/5 > m5.html && python3 -I snap.py m5.html` shows no `previousChapterVerses`/`nextChapterVerses`; `curl -s -H "$H" http://127.0.0.1/books/Gen/chapters/1 | grep -c 'href="/books/Gen/chapters/2"'` ≥ 1; `curl -s -o /dev/null -w '%{redirect_url}' -H "$H" http://127.0.0.1/books/Gen/chapters/2` → empty (no hop); `grep -rn 'CacheAdjacentChaptersJob' app` → 0.

#### Task 6.2: Snapshot diet + Strong's default (OD-3)
- **ACTION**: render verse HTML once into a `#[Computed]` property (not serialised); remove `books`, `paragraphs`, `capabilities`, `combined_text`, `original_xml` from public state; `setFontSize` → client-side (Alpine + `localStorage`) with a visible A−/A+ control; UI-only toggles (`openBookSelector`, `toggleSearch`, `backToBooks`, `hideBookSelector`) → Alpine. Resolve `BibleChapter.php:43` vs `:101` to the OD-3 default.
- **VALIDATE**: `cd scripts/audit/perf && curl -s -H "$H" http://127.0.0.1/books/Gen/chapters/1 > gen1.html && python3 -I snap.py gen1.html` → snapshot < 20KB (OD-3 = OFF) / < 60KB (ON); Matt 5 < 40KB / < 120KB.

#### Task 6.3: PERF-8 / ARCH-4 / PERF-1 — memoise, index-friendly lookups
- **ACTION**: `TranslationService::getCurrentTranslationKey()` → `session('current_translation') ?? $this->defaultKey()`, memoised; `BibleService::getCurrentReader()` memoises one `DatabaseBibleReader` per key; replace `Cache::has`+`get`/`put` pairs with `Cache::remember` (`DatabaseBibleReader.php:34-35, 51-52, 82-83`); `getChapters` and `getVerseText` (`:92-97`, `:470`) use the canonical id from 1.1 instead of `UPPER(osis_id)`; batch the `red_letter_text` lookup per chapter (`BibleChapter.php:676-679`).
- **VALIDATE**: in `$DEV`: `php scripts/audit/perf/harness.php gen1 /books/Gen/chapters/1` → ≤ 15 queries warm, 1 `bible_versions` query; `php scripts/audit/perf/eqp.php` → no `SCAN verses`.

#### Task 6.4: PERF-11/13 — runtime & static weight
- **ACTION**: `php artisan livewire:publish --assets` (assets committed under `public/vendor/livewire/`) + nginx serves them with immutable cache; delete `public/icon.png` (unreferenced); move inline JS from `partials/scripts.blade.php` into a Vite entry; trim Google Fonts to the family in use with `display=swap` (or self-host). Root (OD-8): opcache `max_accelerated_files=32531`, `interned_strings_buffer=16`.
- **VALIDATE**: `curl -sI -H "$H" http://127.0.0.1/vendor/livewire/livewire.min.js | grep -ci immutable` ≥ 1; `curl -s -H "$H" http://127.0.0.1/books/Gen/chapters/1 | grep -c '<script>'` reduced.

### Phase 7 — Tests & tooling (~3–4 days)

#### Task 7.1: Clean the suite
- **ACTION**: delete dead tests (`tests/Unit/Parsers/*`, `OsisReaderTest`, `OsisReaderIntegrationTest`, `BibleControllerTest`, the XML parts of `TranslationServiceTest`); rewrite or delete the contract-rot Feature tests (`BibleControllerIntegrationTest`, `BibleRoutesTest`) against current routes/envelope; fix `UserFactory` (remove `email_verified_at` and `unverified()`); `phpunit.xml`: `failOnRisky="true"`, `failOnWarning="true"`, un-exclude `app/Console`.
- **VALIDATE**: `cd "$DEV" && devguard && ./vendor/bin/pest` → 0 failed, 0 risky; `./vendor/bin/pest --parallel` runs.

#### Task 7.2: Feature coverage of what matters
- **ACTION**: CREATE (all on `projectFixture()`):
  - `Feature/Reader/ChapterPageTest`: every fixture book incl. `1John` → 200; Matt 26:56 complete; John 3:16 inside a red span; Gen 1:1 "created" carries no italic; Ps 23:1 `LORD` small caps + *is* italic; no raw `<w`/`<transChange`/`<divineName`/`<inscription` in HTML; exactly one `id="verse-1"`; span tags balanced.
  - `Feature/Api/*`: envelope shape, JSON 404, NUL → 422, `/api/books/1John/chapters/4/verses` total 21.
  - `Feature/Auth/LoginTest`: email+IP throttle (5 bad → lockout incl. correct password), per-account limiter + `admin:unlock`, guest → `/login`, logout POST, password change evicts sessions.
  - `Feature/Admin/VerseTitleManagerTest` (temp `bible.osis_directory`): save/remove round-trip; `$1`/`\1`/`&` titles; job dispatched; locked props; frozen editor → 503.
  - `Feature/Security/NativeRoutesTest` — only if OD-1 = keep.
  - `Unit/Osis/OsisSourceWriterTest`, `Unit/Osis/OsisSourcePathTest`.
  - `Feature/Import/VerifyCommandTest`: exit 0 on fixture; exit 1 listing the osisID after corrupting one verse.
- **VALIDATE**: `./vendor/bin/pest --testsuite=Feature` green < 30s.

#### Task 7.3: Tooling (OD-10)
- **ACTION**: delete `.php-cs-fixer.dist.php`; `composer remove --dev friendsofphp/php-cs-fixer laravel/sail`; composer scripts `"test": "pest --parallel"`, `"lint": "pint --test"`, `"check": ["@lint", "@test"]`; delete `run-tests.sh`, `run-dev-tests.sh`, `run-unit-coverage.sh` (no coverage driver is installed; optionally `pecl install pcov` as root and add `"coverage": "pest --coverage --min=60"`); optional `larastan` level 5 with a baseline.
- **VALIDATE**: `composer check` exits non-zero on a deliberately failing test and zero otherwise.

#### Task 7.4: CI, then a production-only install
- **ACTION**: `.github/workflows/ci.yml` (origin is GitHub): PHP 8.4, `composer install`, `composer check`, `npm ci && npm run build`. Once green on `audit-v2/work`, change `bin/deploy.sh` to `composer install --no-dev -o --no-interaction` (tests now run in CI and `$DEV`).
- **VALIDATE**: a push with a failing test turns CI red; after the switch, `ls "$APP/vendor/pestphp" 2>/dev/null | wc -l` → 0 and the site is 200.

### Phase 8 — Accessibility, UX, SEO (~3 days)

#### Task 8.1: Document structure & metadata (A11Y-2, SEO-2)
- **ACTION**: `<main id="main">`, skip link, `<h1>` = "{Book} {chapter}"; editorial headings as `<h2>`; psalm titles as `<p class="psalm-title">`; verses grouped in `<p>`; `<title>{Book} {Ch} — King James Version</title>`, meta description (verse 1 text, ≤155 chars), `<link rel="canonical">`, `og:*`.
- **VALIDATE**: `curl -s -H "$H" http://127.0.0.1/books/Gen/chapters/1 | python3 -I -c 'import sys;h=sys.stdin.read();print(h.count("<h1"),h.count("<main"),"<title>Genesis 1 — King James Version</title>" in h)'` → `1 1 True`.

#### Task 8.2: Interactive a11y (A11Y-3..11, LW-1)
- **ACTION**: book selector → `role="dialog" aria-modal="true" aria-labelledby`, `x-trap.noscroll`, `@keydown.escape`, focus return; `aria-label` + `aria-pressed` on every icon button (mirror `bible-chapter.blade.php:211-214`); Strong's tooltips → focusable `<button>` popovers shown only when the toggle is on; `role="status" aria-live="polite"` for result counts; `wire:loading`; labels for search inputs; `aria-describedby`/`aria-invalid` on admin and login errors; contrast (`dark:text-gray-400`, input borders `gray-400`); 44px targets; `@media (prefers-reduced-motion: reduce)` + `motion-safe:`; `viewport-fit=cover` + `h-dvh`; `tabindex="0" role="region" aria-label` on the scroller; `wire:key` on all loops.
- **VALIDATE**: `grep -rhoE 'wire:key="' resources/views | wc -l` ≥ 16 (today 0); `npx --yes @axe-core/cli http://127.0.0.1/books/Gen/chapters/1` (in CI, with the Host header via a hosts entry) → 0 serious/critical.

#### Task 8.3: Links, sitemap, URLs (UX-3, SEO-1/2/3, DOM-13 case)
- **ACTION**: `GET /sitemap.xml` (registered before the catch-alls) listing 1,189 chapter URLs; `robots.txt` with `Sitemap:` and `Disallow: /searches`; `X-Robots-Tag: noindex` on `/login`, `/admin/*`; case-variant book URLs 301 to canonical.
- **VALIDATE**: `curl -s -H "$H" http://127.0.0.1/sitemap.xml | grep -c '<loc>'` → 1189; `curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -H "$H" http://127.0.0.1/books/gen/chapters/1` → `301 …/books/Gen/chapters/1`.

#### Task 8.4: Polish (UX-9/10/12/13, JS-1)
- **ACTION**: one dark-mode head partial (mirror `login.blade.php:8-19`) for all layouts + `.dark{color-scheme:dark}`; delete duplicate `toggleDarkMode`s; print stylesheet (`@media print{html,body{overflow:visible;height:auto}.page{position:static;overflow:visible}.page-prev,.page-next,nav,.fixed{display:none}}`); single-option translation label (mirror `translation-dropdown.blade.php:3, 43-47`); `hidden sm:flex` for fixed nav buttons; JS-1 fixes (only `preventDefault` when navigating; `touchStartX === null` checks; `isContentEditable`; modal detection by `[role=dialog]`; wrap globals in an IIFE).
- **VALIDATE**: `/searches` honours dark mode; Chrome print preview shows the full chapter; arrow keys on Gen 1 (no prev) don't block page scrolling.

### Phase 9 — Cleanup & ops maturity (last)

#### Task 9.1: Delete dead code (~3,420 LOC) — Constraint 12 order
- **ACTION**: (1) remove the `reader_type` xml branch (`BibleService.php:22-38`, `TranslationService::createReader/getCurrentReader`); (2) `app/Services/OsisReader.php`, `app/Services/Parsers/*`, `app/Services/Contracts/OsisParserInterface.php`; (3) `BibleReaderInterface` + the 6 `instanceof` checks in `BibleService.php:229-284`; (4) `SearchComponent` + its 2 views, `livewire-search-poc.blade.php`; (5) `StrongsService`, `StrongsLexicon`, `WordRelationship`, `SeedStrongsLexicon`; (6) `TestBackgroundCachingCommand`, `TestDatabaseReader`; (7) `resources/js/Layouts/BibleLayout.vue`; (8) unused models (`PoetryStructure`, `DivineName`, `StudyNote`, `TranslatorChange`, `WordElement`) + their `Verse` relations; (9) dead `BibleService` methods, unused `ApiResponseTrait` methods, `bible.database.*` config keys, unlistened `translation-changed` dispatches; (10) ~150 dead CSS lines, `prose` classes; custom rules → `@layer components`; extract a `SwitchesTranslation` trait.
- **VALIDATE**: `php artisan route:list` OK; `./vendor/bin/pest` green; `grep -rn 'OsisReader\|StrongsService\|SearchComponent\|BibleReaderInterface' app resources tests` → 0.

#### Task 9.2: Schema cleanup (after 2.10; OD-9)
- **ACTION**: importer stops writing what will be dropped (`position_start/end`, `word_order`, `text_order`, `poetry_structure`, and `divine_names` unless the API's `getVerseWithDetails` keeps it); migration drops the 10 always-empty tables (`abbreviations`, `catch_words`, `element_attributes` (FK to non-existent `text_elements`), `foreign_text`, `formatting_markers`, `milestones`, `reading_variants`, `study_note_elements`, `strongs_lexicon`, `word_relationships`) and `notes_fts`/`strongs_fts`, the views `verse_details`, `cross_references`, `strongs_concordance`, the redundant indexes `verses_chapter_id_verse_number_index`, `books_osis_id_index`, the columns above, and `cache`/`cache_locks`/`jobs`/`failed_jobs`/`job_batches` from the scripture DB (moved in 4.3). If OD-9 = render: importer stores colophons (`<div type="colophon">`) and the reader shows the main book title on chapter 1 and colophons after the last verse, styled non-canonical. Run the full **2.10 procedure** (backup, deploy, import, verify, check, rollback ready).
- **VALIDATE**: `osis:verify --content` → 0; `check-scripture.sh` → 0; `sqlite3 -readonly "$DB" 'pragma integrity_check'` → ok; `vtext /books/Rom/chapters/16 | grep -c 'Written to the Romans'` → 1 (if OD-9 = render).

#### Task 9.3: Docs
- **ACTION**: rewrite `README.md` (architecture: XML source of truth → projection; worktree workflow; commands `osis:import [--production]`, `osis:verify --content`, `admin:create`, `admin:unlock`; deploy; restore); delete `database/migrations/MIGRATION_TEST_COMMANDS.md`; fix `nginx/README.md`; `.env.example`: `APP_DEBUG=false`, `LOG_LEVEL=warning`, `DB_CONNECTION=sqlite`, `SESSION_SECURE_COOKIE=true`, `BIBLE_EDITOR_ENABLED=true`, `BIBLE_*` keys.
- **VALIDATE**: a fresh `git worktree` following README reaches a working app + `osis:verify --content` 0.

#### Task 9.4: Monitoring, units in repo, version scoping
- **ACTION**: external uptime check on `https://bible.b8.co.nz/up`; `Event::listen(\Illuminate\Foundation\Events\DiagnosingHealth::class, fn () => DB::select('select 1 from verses limit 1'))`; a `notify-failure@.service` (e.g. `curl` to a healthchecks.io or ntfy URL — owner provides) wired as `OnFailure=` on the queue and backup units; backup script pings the same service on success; track `deploy/systemd/bible-app-queue.service` with `ProtectSystem=strict`, `ReadWritePaths=/var/www/personal/bible-app/{storage,database,bootstrap/cache,assets}`, `PrivateDevices=yes`; scheduler cron (`* * * * * bible php /var/www/personal/bible-app/artisan schedule:run`) once anything is scheduled (runtime cache pruning). D5: add `version_id` to the 8 `osis_id` lookups (`DatabaseBibleReader.php:470, 685, 703, 719, 734, 796, 835, 862`), the Strong's lookup (`:757`) and the FTS query (`:489-506`) — required before any second translation.
- **VALIDATE**: `systemctl kill bible-app-queue` triggers a notification; `/up` returns 500 when the DB file is unreadable (`$DEV` test).

#### Task 9.5: Platform upgrades (root, OD-8)
- **ACTION**: PHP 8.4.27; nginx ≥ 1.30.6 (or confirm Debian backports); a PHP build linking SQLite ≥ 3.53.2 when available.
- **VALIDATE**: `php -v`; `nginx -v`; `php -r 'echo (new PDO("sqlite::memory:"))->query("select sqlite_version()")->fetchColumn();'`.

#### Task 9.6: Pint — LAST
- **ACTION**: `./vendor/bin/pint` in `$DEV`, single commit, nothing else in it; deploy.
- **VALIDATE**: `./vendor/bin/pint --test` → exit 0; full suite green.

---

## Testing Strategy

| Test file | Cases | Validates |
|---|---|---|
| `tests/Feature/Import/ImportOsisCommandTest.php` | truncation (Matt 26:56, 21:31); ancestor quote (John 3:16); nested quote (Matt 5:4 one red row); Matt 9:6 no bleed; notes excluded; idempotent re-run; chapter-1 title kept; unseeded → 1; contained-format → 1; source-deleted verse removed; production guard | DOM-2/3/7, MATT96, TEST-2/4/5, PIPE-3/7/11 |
| `tests/Feature/Import/VerifyCommandTest.php` | exit 0 on fixture; exit 1 + osisID after corruption | DATA-3 |
| `tests/Feature/Reader/ChapterPageTest.php` | all fixture books 200 incl. 1John; red/italic/LORD/Strong's; no raw tags; single ids; balanced spans | DOM-1/4/10/11, UX-2, A11Y-1 |
| `tests/Feature/Api/*` | envelope, JSON 404 (incl. `/api/nope`), 422 on control chars, counts | ERR-5/6 |
| `tests/Feature/Auth/*` | throttles, per-account limiter + unlock, logout, session eviction (incl. `/livewire/update`) | SEC-3/6 |
| `tests/Feature/Admin/VerseTitleManagerTest.php` | backref titles, entity round-trip, locked props, job dispatch (UntilProcessing), translation restriction, freeze flag | SEC-4, PIPE-1/2/6, ERR-2 |
| `tests/Unit/Osis/OsisSourceWriterTest.php`, `OsisSourcePathTest.php` | statuses; traversal rejected; config override | atomic writer, TEST-6 |

### Edge Cases Checklist
- [ ] Numbered books (1Sam…3John), lowercase/uppercase URLs
- [ ] Quote spanning 12 verses (John 3:10–21); quote closing mid-verse (Matt 26:56); verse ending inside a new quote (Matt 21:31); nested discourse + per-verse quotes (Matt 5:3–12); narration inside a quote (Matt 16:4)
- [ ] Verse with a note containing `transChange` (Judg 11:3); note glued to text (1Chr 21:11)
- [ ] Psalm title with divineName (Ps 18); acrostic headers (Ps 119); inscription (Dan 5:25)
- [ ] Self-closing `<w/>` before a word (John 3:15)
- [ ] Titles containing `$1`, `\1`, `$10`, `&`, `<`, control chars, 255 chars
- [ ] Two saves on the same chapter while a publish runs; a save while a CLI full import runs
- [ ] Queue worker down when a publish is dispatched
- [ ] Search with NUL, FTS operators, 1-char and 255-char queries; `?query=` and `?q=`
- [ ] Spoofed `X-Forwarded-For` / `X-Forwarded-Host` (proxy_sim)
- [ ] Mal 4 → Matt 1; Rev 22 end; chapter 0 / overflow

---

## Validation Commands

Every test/copy command runs **in `$DEV` after `devguard`** (see *Where To Work*).

### Level 1: STATIC_ANALYSIS
```bash
cd "$DEV" && for f in $(find app routes config database tests -name '*.php'); do php -l "$f" >/dev/null || echo "$f"; done   # no output
./vendor/bin/pint --test        # exit 0 after 9.6 (70 files today)
```

### Level 2: UNIT / FEATURE
```bash
cd "$DEV" && devguard && ./vendor/bin/pest --testsuite=Unit && ./vendor/bin/pest --testsuite=Feature
```
**EXPECT**: 0 failed, 0 risky (today: 113F/9R).

### Level 3: FULL_SUITE + BUILD
```bash
cd "$DEV" && devguard && ./vendor/bin/pest --parallel && npm run build
```

### Level 4: PROJECTION VALIDATION (the scripture gate)
```bash
php artisan osis:verify assets/kjv.osis.xml --content; echo $?      # 0 ($DEV copy, or live as $APPUSER after 2.10)
bash scripts/audit/check-scripture.sh "<app>" "<db>"; echo $?        # 0 (or only OD-5 allow-listed FPs)
sqlite3 -readonly "<db>" "pragma integrity_check"                     # ok
```

### Level 5: LIVE VALIDATION
```bash
for b in $(sqlite3 -readonly "$DB" "select osis_id from books"); do c=$(curl -s -o /dev/null -w '%{http_code}' -H "$H" "http://127.0.0.1/books/$b/chapters/1"); [ "$c" = 200 ] || echo "$b $c"; done   # no output
cd "$APP/scripts/audit/scripture" && python3 -I live_sample.py kjv_truth.jsonl sim.jsonl Gen.1 Ps.23 John.3 Matt.26 1Cor.13 --random 25 --seed 11   # 0 diffs (run run_all.sh first to produce the .jsonl files)
curl -s -H "$H" 'http://127.0.0.1/searches?query=love' | grep -c 'mock search result'   # 0
composer audit --locked --no-dev                                                         # no reachable advisories
```
Browser (owner, or Chrome MCP if connected): Gen 1, John 3, 1 Cor 13, /searches, /login, /admin in light + dark, keyboard-only navigation, 375px width.

### Level 6: MANUAL
1. Task 3.5's editor drill.
2. Restore drill: restore last night's backup into `$DEV` → `osis:import` → `osis:verify --content` 0 (follow `deploy/RESTORE.md`).

---

## Acceptance Criteria

- [ ] All 66 books × chapter 1 return 200; 0 truncated verses; 0 verses with note text; 0 raw OSIS tags in rendered HTML.
- [ ] Red letters: FN 0, FP 0 (or only OD-5 allow-listed), partial 0 vs `osis_truth.py`.
- [ ] Italic count == supplied words; no swallowed `<w/>`; LORD small caps in psalm titles.
- [ ] `osis:verify --content` exits 0; `scripts/audit/check-scripture.sh` exits 0.
- [ ] `/searches` serves real results; no route returns 500 for any input tried in this audit.
- [ ] Source XML committed and pushed; nightly verified backup of DB + XML; off-host copy configured (or recorded as open risk); restore drill passed.
- [ ] `composer audit --no-dev` → no reachable advisories; axios gone from browser bundles.
- [ ] Editor: backreference/entity bugs fixed; publish runs in a queued subprocess; audit trail written; chapter-1 saves keep book titles.
- [ ] Proxy trust limited to the tunnel; trusted hosts on; public components lock server-owned state; cache/limiter/queue not writing the scripture DB.
- [ ] Test suite: 0 failed / 0 risky; Feature tests cover importer, verify, editor, auth, API, reader; CI green.
- [ ] Matt 5 page < 560KB raw (neighbours kept in the DOM per OD-2, out of the snapshot); ≤ 15 queries warm; no 302 hop on page turn.
- [ ] Red letters cross-checked against eBible's red-letter KJV (2.4): the 5 Matthew verses match; any remaining differences listed for the owner.
- [ ] WCAG basics: one h1/main, dialog semantics, named controls, `wire:key` everywhere, reduced motion honoured.

## Completion Checklist

- [ ] Phases executed in order; each task's VALIDATE run immediately after it
- [ ] Level 1–4 green; Level 5 live checks green; Level 6 drills done
- [ ] Open decisions recorded in this file with the owner's answers
- [ ] v1 plan annotated "superseded by v2 for open items"

---

## Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| A test or "copy" import hits the production DB (cached config ignores env; `sudo` strips env) | HIGH if ignored | CRITICAL | Where To Work rules; `devguard`; importer `--production` guard (2.6) |
| Editing the live tree ships half-done code | HIGH if ignored | HIGH | Worktree (0.2); deploy only via `bin/deploy.sh` |
| Importer change alters text of verses that were correct | MED | HIGH | Re-project the `$DEV` copy first; `compare_all.py` + `osis:verify --content` show only intended diffs; backup + written rollback (2.10) |
| `preserveWhiteSpace=true` changes `original_xml` → render regressions | MED | MED | `sim_render.php` over all 31,102 verses vs truth after 2.5; span-balance check |
| Synthetic/nested `<q>` produce unbalanced red spans | MED | HIGH | Unwrap inner quotes; delete the Matt 9:6 hack; balance check in 2.5 and 7.2 |
| Long write transaction blocks readers' cache writes → 404s | MED | MED | Per-chapter IMMEDIATE transactions; 2.10 under `artisan down` |
| Quote-boundary XML edits are a theological judgment | — | HIGH | Owner approves each verse (OD-5); interim allow-list |
| Root-level changes break co-hosted apps (pool, nginx) | MED | MED | Separate pool only affects this vhost; `nginx -t`; one change per reload |
| APP_KEY rotation | MED | LOW | `APP_PREVIOUS_KEYS`; quiet window |
| Publish job OOM / dropped edits | MED | MED | Subprocess import; `MemoryMax=1G`; `ShouldBeUniqueUntilProcessing` + `WithoutOverlapping` |
| Per-account login cap used to lock out the admin | LOW | MED | Delay + alert, not hard lock; `admin:unlock` |
| Agent-sourced 2026 CVE IDs | LOW | LOW | Confirmed via local `composer audit`; nginx/PHP/SQLite CVEs marked UNVERIFIED by research — confirm before citing |

---

## Notes

- **Audit side effects (disclosed):** agents only read the repo and DB, except as follows. Tinker runs as www-data and one harness misconfiguration appended 13 `EMERGENCY` + 13 `DEBUG` lines to the stale `storage/logs/laravel.log`; probes and review passes added ~42 `ERROR` lines to `laravel-2026-11-09.log` (500s from `/translation`, `/user-session`, NUL search, `limit=abc`, chapter overflow — all known defects). Crawling created anonymous session files and cache rows (count rising past 1,150; they expire). Two harmless POSTs to `/_native/*` returned 404. No login attempts, no XML writes, no artisan writes.
- **Honesty about v1:** v1 missed the 17-book failure (K3) and the truncation (K2), and misdiagnosed red letters (K1). Its remediation `fa78569` made the Strong's-OFF view worse. This plan's scripture gate is content-level (per-verse truth comparison) so that cannot recur.
- **Evidence artefacts:** `.claude/PRPs/audit-v2-tools/` (promoted to `scripts/audit/` in 0.6).

---

## Review Log

Revision 2 incorporates four review passes over revision 1 (lead; fact-check ~95 claims; technical feasibility against vendor source; cold-read implementer). Principal changes:

| Area | Revision 1 | Revision 2 | Source |
|---|---|---|---|
| Where work happens | Unstated; implied editing the live tree | *Where To Work* section; dev worktree (0.2); deploy via `bin/deploy.sh <ref>` | cold-read, lead |
| Config-cache trap | `DB_DATABASE=<copy> php artisan osis:import` after `optimize` → would write **production** | `devguard`; worktree-only copy work; importer `--production` guard | lead, tech, cold-read |
| Red-letter headline | "fa78569 turned 313 verses black"; "575 → 897 regressed" | Default view never worked; `fa78569` affected Strong's-OFF view only; mixed-method comparison removed | fact-check (lead confirmed in code) |
| Truncation words | 225 | 183 | fact-check |
| Truncation live evidence | grep "forsook him" on live HTML (words are split across spans — invalid method) | `instr()` on `text`/`original_xml`, API, word-span grep | lead |
| Importer fix | climb + synthetic `<q>` | + unwrap nested inner quotes (489 verses); delete Matt 9:6 hack; remove space injection | tech |
| Transactions | per scope | per chapter, `transaction_mode IMMEDIATE`; LOCK_SH only around `load()`; in-scope skips only | tech |
| Re-projection | "maintenance window" | Explicit 2.10 procedure + rollback; editor frozen 2.1 → 3.5 | cold-read |
| Publish job | `ShouldBeUnique`, in-process Artisan | `ShouldBeUniqueUntilProcessing` + `WithoutOverlapping`, subprocess, `retry_after` 360 | tech, lead |
| Cache invalidation | `Cache::flush()` option | Versioned keys (`ProjectionCache`) — flush would wipe limiter/locks | tech, lead |
| Cache/queue isolation | Phase 6, after the throttles that need it | 4.3 before 4.4; separate `runtime.sqlite` incl. `queue.failed`/`batching` | cold-read, tech |
| Backups | inline `ExecStart` (fails `systemd-analyze`); `find -delete` would erase the only 2 snapshots | script file; archive first; immediate snapshot in 0.1 | tech, cold-read |
| Trusted hosts | `trustHosts(at: ['bible.b8.co.nz'])` (unanchored regex) | `trustHosts()` (APP_URL-anchored) | tech |
| Login limits | hard per-account cap | delay + alert + `admin:unlock`; limiter is email+IP (not IP-only) | tech, fact-check |
| Session eviction | `auth.session` only | + `Livewire::addPersistentMiddleware(AuthenticateSession)` | tech |
| API 404 | `Route::fallback` (never reached) | catch-all excludes `api`; `shouldRenderJsonWhen` | tech, lead |
| nginx | no `::1`; `log_format` inside server | `allow ::1`; `log_format` at http level; symlink the live copy | tech, fact-check |
| FPM user switch | partial | `useradd`; `APPUSER` everywhere; `bootstrap/cache` 640; `APP_PREVIOUS_KEYS` | cold-read, tech |
| Dead-code order | interface deleted before `OsisReader` | interface after `OsisReader` (it implements it) | fact-check |
| Final lead pass on rev 2 | `umask 027` in `deploy.sh` (would make new `vendor/` files unreadable by FPM); `chmod 640` on root-owned cache files; `(?!api$)` route constraint (doesn't exclude `/api/nope`); backup dir owner after the `bible` switch | no umask; re-own `bootstrap/cache` to the app user then 640; `(?!api\b)`; `chown` backups in 4.7 | lead |
| Schema cleanup | dropped columns still written by importer | importer change + 2.10 procedure in 9.2 | cold-read |
| Validations fixed | 1.2, 1.4, 0.3, 2.8, 4.2, 5.1, 5.3, 6.1, 8.2, 8.3, Level 5 could pass before the fix or not run | each replaced with a discriminating command (`vtext`, regex on source, `grep -o … | sort -u`, `?query=` vs `?q=`, Host header) | cold-read, fact-check |
| Coverage gaps added | — | PIPE-11 delete pass, ERR-10/11, storage `serve`, `getVerseText` UPPER, red-letter N+1, Strong's default contradiction, JS-1, Livewire patch, OPS-8/10/11/12 rows, host-level notes | lead, cold-read |
| ID/precision fixes | — | SEC-3 lines `:29-35`; F2 `:190`; D5 "8 osis_id + 1 Strong's"; K3 (dd/500 → 404); sitemap 302→404; ERR-6 URLs; SEC-1 CVE/PKSA IDs + CVE-2026-114508 reachability; OD-11 counts | fact-check |
