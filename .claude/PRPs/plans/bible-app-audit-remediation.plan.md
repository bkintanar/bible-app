# Feature: bible-app Full Audit & Remediation

## Summary

A full-codebase audit of this Laravel 12 + Livewire 3 KJV Bible reader, executed by 11 parallel
specialist agents with every load-bearing claim independently re-verified against the live site,
the live 147MB SQLite database, the source OSIS XML, and installed vendor source. This document is
the audit record **and** the remediation plan.

The headline conclusion is not "the code is messy." It is this:

> **The application renders incorrect scripture, in the default view, today.**
> It attributes other speakers' words to Christ, omits 28% of Christ's actual words,
> and leaks raw OSIS tags into the visible verse text so that Psalm 23:1 reads
> **"The Lordis my shepherd"**.

Everything else — the unauthenticated object-injection endpoint, the anonymous Scripture-rewrite
page, the 318KB payload, the 113 failing tests — is secondary to that, though several items are
independently urgent.

## Problem Statement

A dormant (14 months), never-CI'd Laravel app was deployed to nginx at `http://bible.local`. It
presents as working — chapters render, search returns results, the API answers 200 — but that
surface conceals: incorrect scripture rendering on the default path, four unauthenticated
attack surfaces, a dead API endpoint, a test suite that reports on an architecture that no longer
exists, and ~2,240 LOC of dead code including a 1,351-LOC reader that returns zero rows.

## Solution Statement

Eight phases, strictly ordered by dependency (Phase 0 first — several items are prerequisites that
will *corrupt other work* if deferred). Domain correctness and unauthenticated access lead. Cleanup
and refactoring come last, deliberately, because deleting code before the swap-fixes land destroys
the only working implementations.

---

## Architecture Decision (user-directed, 2026-08-19): **the OSIS XML is the source of truth**

> *"when updating anything related to scripture, it updates the source, and the auto load of the xml
> will be correct. so the xml is the source of truth."*

This is now a binding constraint on the whole plan, and it **reverses one task and reframes several
others**. The model:

```
  assets/*.osis.xml   ──(projection: osis:import)──►   SQLite (verses, titles, red_letter_text, …)
   ↑ SOURCE OF TRUTH                                    ↑ DERIVED READ MODEL — disposable, rebuildable
   ↑ all scripture writes land here                     ↑ serves all reads (fast, indexed, FTS)
   ↑ versioned in git (already tracked)                 ↑ never written to directly by app features
```

**Reads still come from SQLite.** The directive makes the XML *canonical*, not the runtime read path
— reading OSIS directly at request time is the 422MB-RSS footgun (PERF-4) and is far slower. This is
a write-model / read-projection split: XML is authoritative, the database is a rebuildable cache.

### What this decision changes

| # | Consequence | Effect on plan |
|---|---|---|
| A1 | Scripture edits **must** write to the XML, then re-project | **Task 1.6 REVERSED** — it previously said "write to the `titles` table". That is now wrong |
| A2 | The **importer becomes the most critical code in the app** — it is no longer a run-once script but the mechanism that keeps the read model true | New Phase 1B; `ImportOsisCommand` moves from "excluded from coverage" to "must be tested" |
| A3 | Red letters **must** derive from `<q who="Jesus">` in the XML. A hardcoded PHP list is an out-of-band mutation that contradicts the source | Strengthens Task 1.2 — deleting `getJesusVerses()` is now *architecturally required*, not merely preferable. Missing red letters get fixed **in the XML** |
| A4 | The XML write path must be **atomic and locked** | New Task 1.5b. Currently `file_get_contents`+`preg_replace`+`file_put_contents` with no `flock` and no temp-rename [VERIFIED] |
| A5 | Versioning of the source belongs to **git**, which already tracks all three OSIS files [VERIFIED] | Replaces the 27MB `copy()`-per-save. Task 0.6 becomes "delete the backup writer, commit instead" |
| A6 | You need a way to prove the projection matches the source | New Task 1B.4 — `osis:verify` |
| A7 | The importer **cannot read contained-format** files (hardcoded `//osis:verse[@osisID][@sID]`), so `mao.osis.xml` can never be projected | Now a real limitation, not trivia — blocks any future translation in that format |
| A8 | `canonical="true"` on editorial headings pollutes the **canonical record**, not just a side-file | Task 1.6b raised in severity |

### ⚠️ The blocker this exposes

**The importer is `insert()`-only — no `upsert`, no `updateOrCreate`, no truncate [VERIFIED].**
Every write is a bare `DB::table(...)->insert(...)`, including `bible_versions` at `:129`. Re-running
`osis:import` on a populated database duplicates rows or trips the unique constraints — and a second
`bible_versions` row would activate the 8 version-ambiguous lookups documented in D5.

Its signature is `osis:import {file} {--chunk=100}` — **no book, chapter, or verse filter**, so the
only available operation is a full multi-hour rebuild.

**An architecture where the DB is a projection of the XML requires a projection step that is
idempotent and incremental. Neither property exists today.** That is Phase 1B, and it is the real
cost of this decision.

---

## Metadata

| Field | Value |
|---|---|
| Type | REFACTOR + BUG_FIX (audit-driven) |
| Complexity | HIGH |
| Systems Affected | OSIS import pipeline, render path, Livewire components, API, auth/routing, SQLite config, test suite, nginx + systemd deployment |
| Dependencies | laravel/framework 12.16.0, livewire/livewire **3.6.3 (vulnerable)**, php 8.4.23, nativephp/mobile 1.0.8 (private repo), tailwindcss 4.1.8, alpinejs 3.14.9, vite 6.3.5, pest 3.8 |
| Estimated Tasks | 70 across 8 phases |
| Audit basis | 11 agents; all critical claims re-verified by the lead |

---

## Evidence Standard Used In This Document

Findings are tagged so nothing is actioned on hearsay:

- **[VERIFIED]** — I reproduced it myself in this session (command + output captured).
- **[AGENT]** — reported by a specialist agent with file:line, consistent with other findings, not independently re-run by me.
- **[UNVERIFIABLE]** — claimed but could not be confirmed; explicitly **not** to be acted on or repeated.

---

## Corrections To Beliefs Held Earlier In This Engagement

Recording these because acting on the superseded versions would waste effort or cause harm.

| # | Earlier belief | Corrected finding |
|---|---|---|
| C1 | The 20 `kjv.osis.xml.backup.*` files came from the user hand-editing XML | **[VERIFIED]** `VerseTitleManager` writes one 27MB `copy()` per save (`:191-193`, `:234-236`). The page is **anonymous**, so this is a disk-fill DoS, not housekeeping |
| C2 | The 318KB HTML ships uncompressed; add gzip | **[VERIFIED]** gzip works: 318,887 → 38,561 (8.3×) via bare `gzip on;` at `nginx.conf:47`. The real gap is **CSS/JS uncompressed** (48,991 bytes, no `Content-Encoding`) — `gzip_types` commented at `nginx.conf:54` |
| C3 | `--max-time=3600` + `Restart=always` looks like an hourly crash-loop; consider `on-failure` | **[VERIFIED]** `queue:work --max-time` exits **0**; journal is clean, `NRestarts=0`. `Restart=on-failure` would be **actively wrong** — it would refuse to restart after the first clean exit and kill the queue within an hour. **Leave `Restart=always`** |
| C4 | The systemd unit needs `StandardOutput=journal` | **[VERIFIED]** Already correct by default (`StandardOutput=journal`, `StandardError=inherit`). No change |
| C5 | Alpine is missing on the chapter page (only CSS is `@vite`'d) | **[VERIFIED]** Inverted. Livewire 3 **bundles** Alpine (`livewire.js` contains 83 Alpine refs + the literal `"Alpine has already been"` string). The chapter page is fine; the **3 pages that DO load `app.js` run two Alpine instances** |
| C6 | `axios` is an unused dependency | **[VERIFIED]** It is **shipped to browsers** — present in `public/build/assets/app-Bf4POITK.js` (79,800 bytes) on 3 live pages. Unused *by code*, but a real client-side payload and advisory surface |
| C7 | Agent-supplied `CVE-2026-*` IDs looked date-implausible; treat all as suspect | **[AGENT, primary-sourced]** Two are **real and in-range**; one is unverifiable. See CVE table. My blanket scepticism was wrong; per-ID verification was the right call |
| C8 | `VerseTitleManager` writes titles to the database | **[VERIFIED]** It writes only to the **XML file**. The reader reads titles from the **DB**. Edits show "saved successfully" and never appear to readers until a full re-import |

---

## Findings Inventory

### P0 — Domain correctness: the app renders wrong scripture

**D1. False red-letter attribution — other speakers' words rendered as Christ's. [VERIFIED]**

`red_letter_text` = 1,467 rows: **1,142** from genuine OSIS `<q who="Jesus">`, **325** from a
hand-maintained PHP array. I checked the source XML for each verse:

```
John.3.16    source_has_q=no    renders=RED
John.4.9     source_has_q=no    renders=RED   ← the Samaritan woman speaking
Luke.5.8     source_has_q=no    renders=RED   ← Simon Peter speaking
John.6.42    source_has_q=no    renders=RED   ← the murmuring crowd speaking
```

Cause: `ImportOsisCommand::getJesusVerses()` (`:1613-1685`) is a ~450-entry hardcoded verse list;
`addComprehensiveRedLetters()` (`:1566`) inserts one row per listed verse with
`text_content = the entire verse`; `BibleChapter::applyRedLetterFormatting()` (`:691-695`) wraps the
whole verse in a red span. **[AGENT]** 324 verses render red with no `<q>` in source.

This is *affirmative false attribution* — theologically consequential and invisible to the reader.

**D2. 575 verses of Christ's words never render red (28%). [AGENT, method sound]**

Source ground truth: 2,035 verses contain words of Christ; 1,460 render. Per book:
Luke −363 (62% of Luke), Mark −119 (42%), John −85, Matt −5.

Three independent causes:
1. The hardcoded list stops mid-gospel — Matt ch 9, Mark ch 4, Luke ch 6 **[VERIFIED shape]**
2. **Root cause:** a regex that cannot match its own output. `extractContentUpToEndMarker`
   (`:746`) re-serializes through a fresh `DOMDocument`, which injects
   `xmlns="http://www.bibletechnologies.net/2003/OSIS/namespace"`. `formatRedLetterText`'s regex
   (`:1164`) is `/<q\s+who="Jesus"[^>]*>/` — requires whitespace-then-`who`, so it misses every
   namespaced tag. 279 verses store unconverted raw `<q>`
3. Continuation verses mid-discourse have no `<q>` open tag of their own

**Four competing red-letter implementations** with different rules: `ImportOsisCommand:1143`,
`DatabaseBibleReader:897`, `BibleChapter:596`, `BibleChapter:659`. The last self-defeats at
`:673-677` — returns `$text` unchanged with the comment *"Let the main XML processing handle this"*,
but on the Strong's-off path there is no subsequent processing.

Also: commit `5cc22b1`'s `$inJesusQuote` fix was applied to `ContainedOsisParser` but **not** to
`MilestoneOsisParser` — the parser the KJV actually selects.

**D3. Raw OSIS tags leak into visible scripture; KJV italics and small-caps LORD absent. [VERIFIED]**

`showStrongsNumbers` declares `= false` at `BibleChapter.php:43` but `mount()` sets
`session('show_strongs_numbers', true)` at `:123` — so **the default path for a new visitor is
`original_xml`**. That path (`parseEnhancedVerseText`) handles `<note>`, `<milestone>`, `<w>`, `<q>`
— and **not** `<transChange>` (KJV italics, 14,233 verses) or `<divineName>` (5,828 verses).

Rendered DOM, snapshot stripped, Psalm 23:1:

```
The <divineName>Lord</divineName><transChange type="added">is</transChange> my shepherd
```

Browsers render unknown inline elements as text content, so the reader sees:

> **"The Lordis my shepherd"** — no small caps, no italics, no space.

Genesis 2:4 renders **"Theseare the generations"**. `transChange` appears 112× and `divineName` 40×
in Gen 2's *rendered DOM* (not merely the snapshot). **[AGENT]** 2,558 verses show run-together
words. The KJV LORD/Lord distinction (YHWH vs Adonai) is destroyed in the default view.

**D4. Supporting domain defects. [AGENT]**

| Finding | Scale |
|---|---|
| Mis-attributed Strong's numbers on hover — regex `/<w([^>]*)>(.*?)<\/w>/` doesn't match self-closing `<w/>`, then matches greedily past them | 4,172 verses |
| `word_order`/`text_order` encode **nesting depth, not sequence** — only 2 distinct values across 355,143 rows (339,979 at 1; 15,164 at 2) | word order unreconstructable |
| `poetry_structure`: 4,939 rows, **all with empty `line_text`** — source has zero `<l>`/`<lg>`; rows are fabricated by a fallback that stores the empty `<verse sID/>` milestone's textContent | table is 100% noise |
| `divine_names.original_name` hardcoded `'YHWH'` for all 6,952 rows, incl. 308 where displayed text is "God" | data asserts falsehood |
| 1,250 of 1,530 title rows unreachable — `chapter`/`main` types keyed on `chapter_id`, nothing queries it | 82% dead |
| Editorial headings written `canonical="true"` — in OSIS that asserts the text *is* scripture | data wrong |
| `position_start`/`position_end` NULL in every row of 5 tables | forces reliance on `original_xml` |

**D5. Verse integrity is CLEAN — and the constraint concern was unfounded. [AGENT, well-evidenced]**

66 books, 1,189 chapters, 31,102 verses; **all 66 books at exact canonical KJV verse counts**;
0 empty text, 0 duplicate `osis_id`, 0 gaps, 0 orphans, paragraphs fully cover every chapter.
The 2025-06-03 migrations did **not** open a duplicate door — `UNIQUE(chapter_id, verse_number)`
was never dropped and `chapters` is `UNIQUE(book_id, version_id, chapter_number)`.

The *real* consequence: **8 read paths resolve verses by `osis_id` with no `version_id` filter**
(`DatabaseBibleReader` `:463, :649, :667, :683, :698, :760, :799, :483-500`). Correct with one
version; returns arbitrary translations' text the moment a second exists.

---

### P0 — Unauthenticated attack surfaces

**S1. `POST /_native/api/events` — unauthenticated object injection. [VERIFIED]**

```php
// vendor/nativephp/mobile/src/Http/Controllers/DispatchEventFromAppController.php
$event = $request->get('event');  $payload = $request->get('payload', []);
if (class_exists($event)) { $event = new $event(...$payload); event($event); }
```

`route:list` middleware column is **empty** — no auth, no CSRF, no throttle, not even the `api`
group. Reproduced:

```
POST /_native/api/events  event=Illuminate\Support\Stringable  payload[]=hello → {"success":true}
```

Attacker controls **class name and constructor arguments** of any autoloadable class across app +
84MB vendor tree; `class_exists()` triggers autoload. **[AGENT]** `NativeServiceProvider` calls
`->hasRoute('api')` **unconditionally**, while using `config('nativephp-internal.running')` as a
guard 20 lines later for something else — so the package *has* the flag it needed and didn't apply
it. **This is an upstream package bug, not misconfiguration.** `nativephp/mobile` is in `require`,
not `require-dev`. No official guidance exists for preventing web-side registration.

**S2. `/admin/verse-titles` — anonymous Scripture rewrite + disk-fill DoS. [VERIFIED]**

Returns **200 anonymously** (`routes/web.php:27`, no middleware). `saveTitle()`/`removeTitle()` are
public Livewire actions reachable via `POST /livewire/update`:

```php
$backupPath = $xmlPath . '.backup.' . time();
copy($xmlPath, $backupPath);          // 27MB copy, every save, unbounded
file_put_contents($xmlPath, $newXmlContent);
```

Anonymous visitors can deface canonical Bible text and exhaust the disk. This is the source of the
20 backup files (see C1).

**S3. livewire/livewire 3.6.3 — CVE-2025-54068, CVSS 9.2, unauthenticated RCE. [VERIFIED version; AGENT primary-sourced advisory]**

Affected ≤ 3.6.3; **fixed 3.6.4**. Advisory: *"Exploitation … does not require authentication or
user interaction"* and *"There is no known workaround at this time."* Attack vector is property-update
hydration — and this app has **24 unlocked public properties** on a publicly reachable component.
Public PoC exists.

**S4. Reflected XSS on the live `/searches` page. [VERIFIED]**

```
/searches?query=<svg/onload=alert(1)>
→ mock search result #1 containing the word <mark><svg/onload=alert(1)></mark>
```

`SearchComponent` (`#[Url] public $query`) interpolates the raw query into result text, rendered via
`{!! !!}` at `search-component.blade.php:89`.

**S5. `APP_DEBUG=true` + 6 `dd()` in live request paths. [VERIFIED]**

`ChapterController:25`, `BookController:34`, `BibleChapter:68`, `:96`, `BibleBook:42`, `:60`.
`/books/NoSuchBook` and `/books/Gen/chapters/abc` → **500** leaking absolute paths; `BookController:34`
is literally `dd(config('database.connections.sqlite'))`. The `/{bookOsisId}` catch-all funnels
*every* 404 into it.

**S6. No rate limiting on any of 8 public API routes. [VERIFIED empty middleware; AGENT 100/100→200]**

`bootstrap/app.php` `withMiddleware` is empty; Laravel 12's slim skeleton ships `'throttle:api'`
**commented out**. `throttleApi()` exists at `Middleware.php:719`. Measured cost is **bandwidth,
not CPU** — FTS5 keeps queries cheap, but 106–182KB per unauthenticated request with no
`Cache-Control`.

**CVE verdicts — verified individually, do not repeat the unverifiable one:**

| CVE | Package | Installed | Verdict |
|---|---|---|---|
| CVE-2025-54068 | livewire/livewire | 3.6.3 | **REAL, AFFECTED**, CVSS 9.2 → fix 3.6.4 |
| CVE-2026-48736 | symfony/http-foundation | 7.3.0 | **REAL, AFFECTED**, CVSS 8.6 → fix 7.4.13 (low practical risk here — no SSRF path) |
| CVE-2026-71488 | league/commonmark | 2.7.0 | **REAL, AFFECTED**, CVSS 7.5 → fix 2.9.0 (low practical risk — no user markdown) |
| GHSA-5vg9-5847-vvmq | laravel/framework | 12.16.0 | AFFECTED → fix 12.60.0 (no mail in app) |
| GHSA-crmm-hgp2-wgrp | laravel/framework | 12.16.0 | AFFECTED → fix 12.61.1 |
| CVE-2026-48019 | — | — | **[UNVERIFIABLE]** NVD "CVE ID Not Found". **Do not cite** |

npm: 11 advisories, all dev-toolchain, all semver-compatible fixes. **Exception:** `axios` is in
`devDependencies` but **ships in the browser bundle** [VERIFIED] — browser-relevant
prototype-pollution gadgets apply. Deleting the import removes 29 advisories and shrinks the bundle.

---

### P1 — Broken functionality

**F1. `/api/books/{book}` returns 500 for every book. [VERIFIED]**

```
Gen 500   Ps 500   Matt 500   Rev 500   John 500
→ "Call to a member function toArray() on array"   BibleService.php:140
```

```php
$book = $books->firstWhere('osis_id', $bookOsisId);
return $book ? $book->toArray() : null;   // getBooks() yields ARRAYS
```

Silent for months — 16 occurrences in the log — because **`catch (\Exception)` cannot catch
`\Error`**. All 12 API/service catches use `\Exception`, so the one bug that fires bypasses the
entire JSON contract and returns an HTML debug page on a JSON endpoint. One-line fix: `return $book;`.

**F2. Every API search result reports the wrong verse. [VERIFIED]**

```
verse=1  chapter=6   reference='Luke 6:32'
verse=1  chapter=4   reference='1 John 4:18'
verse=1  chapter=8   reference='Proverbs 8:17'
```

`SearchController.php:61` reads `$result['verse'] ?? 1`; the reader emits `verse_number`. The key
never exists, so the default fires on **100% of rows**. Self-contradicting within one JSON object.

**F3. The live search page serves 250 fabricated verses; the real implementation is dead. [VERIFIED]**

```
<livewire:bible-search>     → NONE — never mounted   (the REAL implementation)
<livewire:search-component> → livewire-search.blade.php:14, livewire-search-poc.blade.php:28  (MOCK)
```

`SearchComponent::mockSearchResults()` generates 250 fake verses (`"Book {$i}:1"`) with
`rand(10,100)` timings. `BibleSearch` calls the real `$bibleService->search()` and is mounted
nowhere. **The components are swapped.** Likely a one-line Blade change plus a `$query`→`$q` rename.

**F4. The XML reader returns zero rows for both English translations. [VERIFIED — my own probe]**

```
kjv  parser=MilestoneOsisParser   chapters(Gen)=0    verses(Gen.1)=0
asv  parser=MilestoneOsisParser   chapters(Gen)=0    verses(Gen.1)=0
mao  parser=ContainedOsisParser   chapters(Gen)=50   verses(Gen.1)=31
```

`OsisReader::createParser()` selects on **verse** encoding only; the KJV is a **hybrid** (milestone
verses, contained chapters), and `MilestoneOsisParser` looks chapters up by `@osisRef`/`@sID` which
the KJV lacks. **[AGENT]** It also returns *wrong scripture* for 559 verses — `getVerseText("Matt.5.3")`
returns the entire Sermon on the Mount block.

Dormant only because `config/bible.php` defaults `reader_type` to `database`. **1,351 LOC of
broken code that 52% of the test suite tests.**

**F5. Three routes 500 unconditionally. [VERIFIED]** `/dashboard` (imports `Inertia\Inertia`, not
installed), `/translation`, `/user-session` (views don't exist).

**F6. No visible chapter navigation. [VERIFIED]** Both prev/next buttons are wrapped in `@if(false)`
(`bible-chapter.blade.php:209`, `:224`). Desktop users have only an undocumented arrow key or the
book-selector modal.

**F7. FTS5 query-language injection → 500s. [VERIFIED]** `q=AND`, `q=OR`, `q=NOT`, `q=a(b` all 500;
`q=God` 200. `escapeFtsSearchTerm` (`:518-531`) only quotes terms containing `[0-9:.-]` or a space,
so bare operators reach FTS5 raw. Properly bound — **not SQLi** — a reliability + operator-exposure
bug. Searching for the English word "or" is a 500.

---

### P1 — Performance

**PERF-0. THE root cause: a query shape that forces a full table scan. [VERIFIED]**

`DatabaseBibleReader`'s `whereHas('chapter', …)` compiles to a correlated `EXISTS` that SQLite
cannot invert. Every chapter view full-scans the 31,102-row / 46.7MB `verses` table:

```
EXPLAIN QUERY PLAN  (current whereHas shape)
  SCAN verses                    ← full scan, 31,102 rows
  CORRELATED SCALAR SUBQUERY 1
  SEARCH chapters USING INTEGER PRIMARY KEY (rowid=?)
  USE TEMP B-TREE FOR ORDER BY

EXPLAIN QUERY PLAN  (two-step equivalent)
  SEARCH verses USING INDEX verses_chapter_id_verse_number_index (chapter_id=?)
```

Benchmarked on the live DB, 20 iterations: **21.938 ms → 0.028 ms — 779×**
(`perf-audit` measured 938×; same conclusion).

**The correct index already exists and is never used** — `verses_chapter_id_verse_number_index` is
present in `sqlite_master`. **This is a query-shape bug in ORM usage, not a missing index. Do not
write migrations; change the code.** Same defect at three more sites:

| Site | Defect | Measured [AGENT] |
|---|---|---|
| `DatabaseBibleReader:129-135` | `whereHas` → `SCAN verses` | 18.755 → 0.020 ms |
| `DatabaseBibleReader:219-224` | `whereHas` (paragraphs) | 4.126 → 0.012 ms |
| `DatabaseBibleReader:463-465` | `UPPER(osis_id)` defeats `verses_osis_id_index` → `SCAN verses` | 15.395 → 0.004 ms |
| `DatabaseBibleReader:93-95` | `UPPER(osis_id)` on books | 0.256 → 0.057 ms (minor) |

**Index adequacy verdict: the schema is FINE.** Every fixed query shape resolves to an existing
index with zero migration work. `word_elements` (355,143 rows / 58.4MB) is **never touched** on the
chapter path — Strong's markup comes from `verses.original_xml`.

Aggravating factor: `PRAGMA cache_size = -2000` (2MB) cannot hold a 46.7MB table, so it re-reads
from disk each time; and `ChapterController` renders/caches 2–3 chapters per request, so the scan
fires **2× for Gen 1** (60.6 of 69.3 ms DB time) and **3× for Matt 5** (101.8 of 111.9 ms).

**PERF-1. Measured web-path query counts. [AGENT]** This answers the question the API measurements
could not — the Livewire path is **not** the API's constant 15:

| Route | Queries | Wall | DB time | Bytes |
|---|---|---|---|---|
| Gen 1 (cold) | 27 | 102.6ms | 69.3ms | 318,887 |
| Gen 1 (steady) | 44 | 66–96ms | 36–61ms | 318,887 |
| Ps 119 | 30 | 122.6ms | 51.5ms | 755,175 |
| Matt 5 | 33 | 159.3ms | 111.9ms | 758,007 |
| Matt 5, Strong's **off** | **55** | 104.0ms | — | 571,661 |

**No N+1 on the default render** (eager loading is correct; Ps 119's 176 verses cost 30 queries vs
Gen 1's 27). **But there IS one real N+1**, on a toggle: `BibleChapter.php:684` runs
`DB::table('red_letter_text')->where('verse_id', $verseId)` inside the per-verse loop — 33 → 55
queries when Strong's is off.

Also: **6 `DatabaseBibleReader` instances constructed per request** (`BibleService::getCurrentReader()`
does `new` on every delegate call), 8–10 uncached `bible_versions` lookups (`TranslationService:114`,
and `LOWER()` defeats the index), and 26 `cache` SELECTs because `Cache::has()` + `Cache::get()` is
two queries per logical read.

**PERF-2. The cache never caches the chapter you are reading. [AGENT, strong evidence]**

`chapter_verses_Gen_1` **does not exist in the cache table and never will.** `ChapterController::show`
caches only 207 bytes of *metadata* for the current chapter and *verses* only for chapters ±1.
`BibleChapter::loadChapterVerses` then looks up `chapter_verses_Gen_1` — a **permanent miss**. So the
full scan runs on every request forever, and the cache layer's entire output is the prev/next payload
**which then inflates the 150KB snapshot**.

Post-PERF-0 this layer becomes **pure cost**: serialize+unserialize of a chapter payload measures
0.020 ms — *identical* to the fixed query — so you would pay a ~5 ms exclusive write lock on a 147MB
file to avoid a 0.02 ms indexed read. **Recommendation: delete the chapter cache layer, don't repair it.**

Compounding: `ChapterController:37` preloads adjacent chapters **synchronously in the request**
*and* `BibleChapter:160` dispatches `CacheAdjacentChaptersJob` to do the same work again. And
`last_visited_page` is written **twice per request** (`ChapterController:28` + `BibleChapter:81`) —
the entire measured write load of a pageview is a duplicated bookkeeping write.

**Nothing is ever invalidated.** The only `Cache::forget` calls are in `UserSessionController:40`
and a debug command.

**PERF-3. WAL contention, quantified. [AGENT]** Writer hammering cache rows while a reader fetches Gen 1:

| journal_mode | reader p50 | p99 | **MAX** |
|---|---|---|---|
| `delete` (current) | 0.04ms | 0.09ms | **629.27ms** |
| `wal` | 0.05ms | 0.13ms | **0.21ms** |

45KB cache write: `delete`+FULL 4.78ms → `wal`+NORMAL **0.09ms (53×)**. Live concurrency: Matt 5 at
concurrency 10 → p50 **520ms**, max 714ms.

**PERF-4. A 393MB RSS footgun. [AGENT]** `OsisReader:22` and `ImportOsisCommand:89` both
`DOMDocument->load()` the 26.8MB file: RSS 28MB → **422MB**. **libxml allocates via C malloc, so
`memory_get_usage()` reports 4MB and `memory_limit=128M` does not stop it** — the FPM child simply
grows to 420MB. With `pm.max_children=5` that is 2.1GB. Dormant (database reader), one env-var flip
from taking the box down. `VerseTitleManager::saveTitle` is the web-reachable cousin: **55.5MB peak
per save against a 128M limit**.

**Livewire payload — `/books/Gen/chapters/1`. [VERIFIED]**

| Metric | Value |
|---|---|
| Total response | 318,887 bytes / 113ms |
| `wire:snapshot` | **150,550 bytes — 47.2% of the page** |
| `original_xml` occurrences | 56 (raw OSIS, no client consumer) |

**[AGENT]** Per-property decoded breakdown, with view-usage confirmed — **four properties are in
state but read by no view at all**:

| Property | Bytes | Used by a view? |
|---|---|---|
| `verses` | 38,436 | yes (`original_xml` 19,007 of it) |
| `nextChapterVerses` | 32,905 | yes (`original_xml` 14,790) |
| `testamentBooks` | 7,847 | yes |
| `books` | 7,829 | **NO** — only `$testamentBooks` is referenced |
| `paragraphs` | 7,348 | **NO** — appears in zero views; costs 2 extra queries |
| `chapters` | 3,691 | yes |
| `capabilities` | 275 | **NO** |
| `combined_text` | 11,410 | **NO** — read only by an `@elseif` that cannot fire |

`original_xml` alone is **33,797 of the 99,772 snapshot bytes (34%)** — the "56 occurrences"
quantified. Only **7,638 bytes (2.4%)** of the 318,887-byte page is visible text.

**Round-trip measured by actually POSTing** `setFontSize('lg')` to `/livewire/update`:
**111,835 B up + 284,785 B down = 396,620 B on the wire to change a CSS class**, with 0 queries.

`original_xml` is 25.1MB DB-wide vs 4.3MB of text; Matt 26 is 109KB of XML for 8KB of scripture
(13×). Three chapters ship per page (prev/current/next).

**[AGENT]** **Zero `wire:key`** across all 12 loops, including a variable-length chapter grid — the
exact condition that mis-binds DOM nodes. Plausibly the true cause behind commit `aedec21`
*"fix: buttons not working"*.

**SQLite. [VERIFIED]** `journal_mode=delete` — **no WAL** — while FPM children + queue worker +
`CACHE_STORE=database` + `QUEUE_CONNECTION=database` all write one 147MB file, with a cache write on
**every pageview**. Mechanism confirmed in vendor source:

```php
// SQLiteConnector::configureJournalMode
if (! isset($config['journal_mode'])) { return; }
```

`config/database.php:40-42` sets all three pragmas to `null`; **`null` fails `isset()`, so no pragma
is ever issued.** In `delete` mode a writer takes an exclusive lock on the whole file and all readers
block.

**Logging. [VERIFIED]** 69,009 lines: **65,128 INFO** vs 63 ERROR. Top offenders per-*verse*:
`4810× "🔴 Processing red letters for XML"`, `3325× "🔴 Final processed XML"`. **[AGENT]** One Gen 1
request writes 29,588 bytes of log; one Ps 119 request writes **110,919 bytes**. No rotation
(`LOG_STACK=single`, no logrotate entry) ≈ 3.4GB/day at 1 req/s.

---

### P2 — Test suite

**[AGENT, method sound]** 113 failed / 133 passed / 9 risky / 246 total / 106.83s **[VERIFIED]**.
Root-caused: **98 rotted tests, 15 tests correctly reporting real breakage.**

| # | Cause | Count | Verdict |
|---|---|---|---|
| RC1 | `BibleControllerTest` targets methods deleted in the RESTful refactor | 28 | TEST |
| RC2 | URL scheme moved to `/books/{b}/chapters/{n}`; legacy URLs 302 | 35 | TEST |
| RC3 | `dd()` in 404 branches | 4 | **APP** |
| RC4 | API moved + responses now enveloped by `ApiResponseTrait` | 8 | TEST |
| RC5 | OSIS assets replaced after tests written | 4 | TEST |
| RC6 | XML reader broken for KJV+ASV (F4) | 11 | **APP** |
| RC7 | `ContainedOsisParserTest` loads `asv.osis.xml`, no longer contained-format | 18 | TEST |
| RC8 | `5cc22b1` added `dark:text-red-400`; tests assert pre-dark-mode strings | 5 | TEST |

**None of the confirmed app bugs (F1, F2, F3, F5) is covered by any test.** The suite fails loudly
about an architecture that no longer exists while staying silent about every bug actually found.

**Zero `Livewire::test()` calls exist.** 1,457 LOC across 5 components untested — and that is where
the app's behaviour now lives. **52% of test LOC (1,623) tests broken dead code; 0% tests the 1,766
LOC that serves every request.**

**The 9 "risky" tests are vacuous passes** — 0 assertions, all in `ContainedOsisParserTest`, using
`foreach` over empty collections and `if ($x->isNotEmpty())` guards. They are **green right now
against a parser that returns nothing** and would stay green if the parser body were deleted.

⚠️ **Two hazards to respect:**
1. **`dd()` ends in `exit(1)`.** Fixing test URLs *before* the `dd()`s will **kill the PHPUnit
   process mid-run** rather than fail a test. RC3 must precede RC2.
2. **`RefreshDatabase` only skips `migrate:fresh` because the DB is `:memory:`.** Remove that
   `phpunit.xml` line and the next run will `migrate:fresh` the real **147MB** database and destroy
   the only copy. There is no backup (see O5) and rebuilding takes hours.

**The three shell scripts are green-light generators.** `run-tests.sh` has no `set -e` and prints
*"Test suite completed!"* while steps 3–5 fail. `run-dev-tests.sh` prints
*"• Controller & services: ✅"* **unconditionally** while `BibleControllerTest` fails 28 of 29.
`run-unit-coverage.sh` needs a coverage driver that isn't installed.

---

### P2 — Architecture & dead code

**[AGENT]** ~2,240 LOC (~19%) + 509MB apparent / 142MB disk reclaimable.

Largest items: `StrongsService` 443 LOC **zero references** [VERIFIED]; the Strong's island
(+`StrongsLexicon`, `WordRelationship`, `SeedStrongsLexicon`) 995 LOC against two **empty** tables;
`OsisReader` + 2 parsers 1,351 LOC (broken per F4, but **test-referenced**); `/test-cache` 83-line
debug closure; `BibleLayout.vue` (no Vue toolchain); `UserFactory` (no User model).

**Architectural debt:** `ChapterController` holds **static cache methods** called from Livewire and
route closures — `BibleChapter.php:9` imports the HTTP layer. Four independent writers to one
undocumented cache key format; the Psalm-119 special case duplicated in **three** places.
`BibleReaderInterface` has no binding, one type-hint site, and its contract is punctured **8 times**
by `instanceof DatabaseBibleReader`. Canon data is duplicated across **7 files** (~400 LOC) in an
app with a `books` table.

**⚠️ Looks dead but is LIVE — do not delete:**
- **`search_results` view** [VERIFIED] — `verses_fts` is `content='search_results'`. Dropping it **breaks all search**
- **`BibleSearch`** — zero references, but it is the *only real* search implementation (F3)
- **`livewire-{book,chapter,search}.blade.php`** — not duplicates; they are the page shells that mount the components. Both halves required
- **`asv/mao.osis.xml`** — dead at runtime, but 4 test files read them via `base_path()`
- **`VerseTitleManager::updated*`** — Livewire magic hooks, invoked by the framework
- **`DatabaseBibleReader::{getVerseStrongsData,getTranslatorChanges,getDivineNames,getStudyNotes}`** — the `BibleService` wrappers are dead, but these are live via `getVerseWithDetails()`
- **`poetry_structure`** — 4,939 rows, read by a zero-caller method. Needs a *decision*, not a delete

**Tooling:** two competing formatters [VERIFIED] — Pint (no `pint.json`, alphabetical imports) vs
`.php-cs-fixer.dist.php` (@PSR12, imports by **length**). **69 of 111 files** fail `pint --test`
largely because the two disagree. Pick one before any sweep or diffs will thrash.

---

### P2 — Deployment (including my own work)

| # | Finding | Status |
|---|---|---|
| O1 | `location ^~ /build/` **strips both security headers** from every CSS/JS file (nginx has no `add_header` inheritance) + emits two conflicting `Cache-Control` values | **[VERIFIED]** — my bug |
| O2 | CSS/JS uncompressed; `gzip_types`/`gzip_vary` commented at `nginx.conf:49-54` | **[VERIFIED]** |
| O3 | **No `failed_jobs` table** and no migration — only `jobs`. With `--tries=3`, recording a failure throws → worker dies → `StartLimitBurst=5`/`10s` → **service sits dead, silently**. `MemoryMax=infinity`, CLI `memory_limit=-1` | **[VERIFIED]** — my gap |
| O4 | `composer install` **impossible** on a fresh clone/CI (401, no `auth.json`); `composer audit` **cannot run** → **zero PHP vulnerability visibility**. NativePHP is also *never initialised* — `config/nativephp.php:93` points at a nonexistent `NativeAppServiceProvider` | **[VERIFIED]** |
| O5 | **No backup story at all**; a 147MB SQLite in rollback-journal mode cannot be safely `cp`'d under concurrent writes | **[AGENT]** |
| O6 | `config:cache` **SAFE** (0 `env()` outside `config/`); `route:cache` **WILL FAIL** — 5 closure routes | **[VERIFIED]** |
| O7 | `.env.example` has `DB_CONNECTION=mysql`, `DB_DATABASE=bible_app`, `SESSION_DRIVER=database` — **all three unrunnable** (SQLite-only, FTS5 virtual tables, no `sessions` table) | **[AGENT]** |
| O8 | FPM pool `pm.max_children=5`, no `pm.max_requests`, **shared with 14 other vhosts** — 5 slow renders can take down the staging sites | **[AGENT]** |
| O9 | Repo hygiene good — DB/vendor/node_modules/build/.env all correctly ignored [VERIFIED]. But **509MB of untracked, un-ignored** `assets/*.backup.*` would be committed by `git add -A` | **[VERIFIED]** |
| O10 | `@variant` used where **`@custom-variant`** is required to *define* a variant; `app.css:8` is dead (second declaration wins) → **0 `prefers-color-scheme` rules** in compiled CSS, so first-time visitors on dark OS get a light page | **[VERIFIED]** |

**Accessibility [AGENT]:** the live chapter page has **0 `<h1>`–`<h4>`, 0 `<main>`/`<section>`/
`<article>`, 0 `<p>`, 0 `aria-*`, 0 `role`** across **702 `<span>`** of scripture. Red-letter text is
conveyed by **colour alone**. `viewport-fit=cover` is missing so all safe-area CSS is inert.
36×36px touch targets on 6 primary controls.

---

## Ordering Constraints — read before sequencing anything

These are **hard** dependencies. Violating them destroys work or data.

0. **Task 1B.1 (idempotent import) BEFORE any re-projection.** The importer is `insert()`-only; re-running it on a populated DB duplicates rows and creates a second `bible_versions` entry. Every Phase 1 scripture fix requires a re-import to take effect, so **this gates all of Phase 1's delivery**.
0b. **Task 1.5b (atomic XML write) BEFORE `/admin/*` is reopened to anyone.** The source of truth is currently written non-atomically with no lock; a concurrent or interrupted save corrupts it.
1. **`dd()` → `abort(404)` BEFORE any test-URL fix.** `dd()` calls `exit(1)`; the first invalid-book test would kill the PHPUnit process mid-run.
2. **Commit `assets/kjv.osis.xml` BEFORE deleting the 20 backups.** The working copy holds **8 real, uncommitted Gen 28–29 verse titles** [VERIFIED]. Backups are untracked → deletion is unrecoverable.
3. **Fix the search swap (F3) BEFORE deleting `SearchComponent`.** Deleting first removes the only mounted search.
4. **Move cache off the Bible SQLite file BEFORE enabling `throttleApi()`.** `RateLimiter` writes to the cache store; throttling every request against the 147MB file would worsen the contention it's meant to protect.
5. **Enable WAL BEFORE moving sessions to the database** (if ever).
6. **Fix the 5 closure routes BEFORE `route:cache`.**
7. **Never remove `phpunit.xml`'s `DB_DATABASE=:memory:`** without a verified backup — `RefreshDatabase` would `migrate:fresh` production data.
8. **Do not drop the `search_results` view** — it backs `verses_fts`.
9. **Pick one formatter, and run it LAST** — it touches all 111 files and will conflict with every in-flight branch.

---

## NOT Building (explicit scope limits)

- **No CSP.** Livewire 3 + Alpine need `'unsafe-eval'` unless switching to Alpine's CSP build. Its own task, not a quick header add.
- **No HSTS / TLS work.** Site is plain HTTP on a LAN. Add `fastcgi_param HTTPS` when TLS lands.
- **No multi-translation completion.** The 8 unscoped `osis_id` lookups, missing `books.version_id`, and the importer's inability to read contained-format files make this a project. Phase 6 only *documents* the blockers.
- **No `word_order`/`position_*` reconstruction.** Requires an importer rewrite; nothing reads them today.
- **No accessibility overhaul.** Phase 5 lands the structural minimum (`<h1>`, `<main>`, `<p>`, red-letter accessible name); a full WCAG pass is separate.
- **No migration squash.** Rewriting applied history desyncs the `migrations` table for anyone with an existing DB.
- **No API redesign / versioning.** Fix what's broken; `/api/v1` only if the API becomes a deliberate product surface (it has **zero** first-party consumers).
- **No `StrongsService` revival.** 443 LOC against empty tables — delete, don't test.

---

## Step-by-Step Tasks

### Phase 0 — Prerequisites & containment (do first, ~2h)

**Task 0.1 — Commit the uncommitted OSIS work**
- ACTION: commit `assets/kjv.osis.xml` alone
- WHY: 8 real Gen 28–29 verse titles; blocks Task 0.2
- VALIDATE: `git diff --stat assets/kjv.osis.xml` → empty

**Task 0.2 — Add backup ignore rule, then delete the 20 backups**
- ACTION: add `assets/*.osis.xml.backup.*` to `.gitignore`; then `rm assets/kjv.osis.xml.backup.*`
- EVIDENCE it is safe [AGENT, method sound]: title counts rise monotonically 52→142; set-difference vs working copy yields only 3 superseded capitalisation variants; newest backup differs from the working copy by 1 line
- VALIDATE: `git status --short assets/ | wc -l` → 0; `du -sh assets/` → ~30MB

**Task 0.3 — Upgrade Livewire past CVE-2025-54068**
- ACTION: `composer require livewire/livewire:^3.6.4`
- GOTCHA: requires the NativePHP credential or Task 0.4 first — `composer` cannot reach the private repo
- VALIDATE: `composer show livewire/livewire | grep versions` → ≥ 3.6.4

**Task 0.4 — Resolve the NativePHP blocker (decision required — see O-1)**
- ACTION (recommended): `composer remove nativephp/mobile` + drop the `repositories` block
- WHY: kills S1 at the root, unblocks `composer install`/`audit` on every machine, removes a never-initialised package. Nothing in `app/` references it
- FALLBACK if mobile is still planned: supply `COMPOSER_AUTH`, **and** deny at the edge (`location = /_native/api/events { return 404; }`) **and** a global middleware abort. Do not rely on the edge alone
- VALIDATE: `php artisan route:list | grep _native` → empty; `composer audit` runs

**Task 0.5 — Gate `/admin/*` behind auth**
- ACTION: wrap `routes/web.php:27` in `->middleware(['auth'])`; add `authorize()` to `saveTitle`/`removeTitle`
- GOTCHA: **there is no `User` model and no `users` table** — `middleware('auth')` is currently unsatisfiable. Either scaffold auth or, as an interim, restrict by IP in nginx. Do not leave it open
- VALIDATE: `curl -s -o /dev/null -w '%{http_code}' -H 'Host: bible.local' http://127.0.0.1/admin/verse-titles` → 302/403, not 200

**Task 0.6 — Remove the copy-on-every-save; let git version the source** *(reframed by A5)*
- ACTION: `VerseTitleManager.php:191-193`, `:234-236` — delete the `copy()` entirely
- WHY: **git already tracks all three OSIS files** [VERIFIED: `assets/kjv.osis.xml`, `asv`, `mao`].
  Now that the XML is the source of truth, its version history belongs in git — which gives diffs,
  attribution, and rollback — not in unbounded 27MB filesystem copies that are untracked and
  un-ignored. Deleting the writer is both the DoS fix and the correct versioning decision
- FOLLOW-ON: commit XML edits (Task 0.1 pattern) rather than snapshotting them
- VALIDATE: save a title; `ls assets/*.backup.* 2>/dev/null | wc -l` → 0

**Task 0.7 — Replace all 6 `dd()` with `abort(404)`**
- ACTION: `ChapterController:25`, `BookController:34`, `BibleChapter:68`, `:96`, `BibleBook:42`, `:60`
- **MUST PRECEDE Phase 4** (constraint 1)
- VALIDATE: `for p in /books/NoSuchBook /books/Gen/chapters/abc; do curl -s -o /dev/null -w "%{http_code} $p\n" -H 'Host: bible.local' http://127.0.0.1$p; done` → 404 404

**Task 0.8 — Delete the debug/POC routes**
- ACTION: remove `/test-cache` (`web.php:60-137`), `/livewire-search-poc`, `/dashboard` + `use Inertia\Inertia`
- VALIDATE: `php artisan route:list | grep -E 'test-cache|search-poc|dashboard'` → empty

**Task 0.9 — Create the missing queue tables and harden the unit**
- ACTION: `php artisan queue:failed-table && php artisan queue:batches-table && php artisan migrate`; add `StartLimitIntervalSec=0`, `-d memory_limit=256M`, `--memory=200`, `MemoryMax=512M`; replace `After=` with `RequiresMountsFor=`; add `ExecReload`
- KEEP: `Restart=always`, `User=www-data` (C3, C4)
- VALIDATE: `php -r '...sqlite_master...failed_jobs'` → exists; `systemctl show -p StartLimitBurst bible-app-queue` reflects the change

**Task 0.10 — Establish a backup before anything touches the DB**
- ACTION: cron `sqlite3 …/bible_app.sqlite ".backup /backups/bible-$(date +%F).sqlite" && gzip …`
- WHY: constraint 7; there is currently **no** backup and no safe `cp` under concurrent writes
- VALIDATE: run it; `sqlite3 <dump> "select count(*) from verses"` → 31102

---

### Phase 1 — Scripture correctness (the reason this project exists, ~3–5 days)

**Task 1.1 — Render `<transChange>` and `<divineName>` on the default path**
- ACTION: extend `BibleChapter::parseEnhancedVerseText` (`:497+`) to convert `<transChange type="added">` → `<em class="…italic">` and `<divineName>` → small-caps, **and** emit the missing space before `<transChange>`
- FIXES: 14,233 verses of lost italics, 5,828 of lost small caps, 2,558 run-together words, and raw tags in visible text
- VALIDATE: `curl -s -H 'Host: bible.local' http://127.0.0.1/books/Ps/chapters/23 | grep -c divineName` → 0 in rendered DOM; reader text reads `The LORD is my shepherd`
- ALSO: settle the `showStrongsNumbers` default — property says `false`, `mount()` says `true` (`:43` vs `:123`)

**Task 1.2 — Remove the false red-letter attribution**
- ACTION: delete `getJesusVerses()` (`:1613-1685`) and `addComprehensiveRedLetters()` (`:1566`); delete the 325 `comprehensive_import` rows
- WHY: it invents 324 false attributions to fix ~575 omissions — **it makes the data less trustworthy in both directions.** Fix the root cause instead (1.3)
- VALIDATE: `John.4.9`, `Luke.5.8`, `John.6.42` render **black**; `select count(*) from red_letter_text where attributes like '%comprehensive%'` → 0

**Task 1.2b — Where red letters are genuinely missing, fix the XML** *(A3)*
- ACTION: for the 575 verses with no `<q who="Jesus">` that should have one, the correction belongs
  **in `assets/kjv.osis.xml`**, committed to git — not in a PHP array. Most will be recovered
  automatically by Task 1.3 (they *are* in the source, the importer just can't see them); only the
  genuine source gaps need editing
- ORDER: run Task 1.3 first, re-project, re-measure. Only then hand-edit what remains
- VALIDATE: `osis:verify` (Task 1B.4) reports source `<q who="Jesus">` count == `red_letter_text` rows

**Task 1.3 — Fix the namespace-blind red-letter regex (root cause)**
- ACTION: `ImportOsisCommand:1164` — accept the namespaced form (`/<q\b[^>]*who="Jesus"/`), or stop re-serializing through a fresh `DOMDocument` in `extractContentUpToEndMarker` (`:746`)
- ALSO: handle continuation verses (a `<q>` open in verse N covering N+1…), and port `5cc22b1`'s `$inJesusQuote` fix to `MilestoneOsisParser` — the parser the KJV actually uses
- VALIDATE: after re-import, verses with words of Christ ≈ 2,035; spot-check Luke and Mark (currently −363 / −119)

**Task 1.4 — Consolidate four red-letter implementations into one**
- ACTION: single `RedLetterRenderer`; remove the self-defeating early return at `BibleChapter:673-677`
- VALIDATE: Beatitudes, John 3:16 (should be **black** — no `<q>` in source), Rev 1:8, Luke 23:34 (narrative `"Then said Jesus,"` must stay black)

**Task 1.5 — Fix the Strong's `<w>` regex**
- ACTION: `:532` — handle self-closing `<w …/>` and stop greedy over-matching
- VALIDATE: John 3:6 shows G1510 on "There was", not G3588

**Task 1.5b — Make the XML write atomic and locked** *(new — required by A4)*
- ACTION: `VerseTitleManager` — take an exclusive `flock`, write to a temp file, `rename()` into place (atomic on the same filesystem). Verify the parse succeeds **before** the rename; never leave a torn source
- WHY: currently `file_get_contents` → `preg_replace` → `file_put_contents` with no locking [VERIFIED]. Two concurrent saves lose an update or tear a 27MB file. Acceptable for a side-file; **unacceptable for the source of truth**
- ALSO: replace the whole-file read+regex (55.5MB peak against a 128MB FPM limit) with a targeted DOM/XPath edit or streaming write
- VALIDATE: two concurrent saves to different verses → both survive; kill the process mid-write → the XML still parses

**Task 1.6 — Keep scripture writes in the XML, then re-project** *(REVERSED — see A1)*
- **Supersedes the earlier version of this task**, which said to write to the `titles` table. Under
  the source-of-truth decision that would make the DB authoritative for titles and the XML stale —
  exactly backwards
- ACTION: `VerseTitleManager` continues to write the XML (it already does), and on success triggers
  a **targeted re-projection** of the affected verse/chapter (Task 1B.2). The success message becomes
  true because the projection has actually run — not because the file write returned non-false
- ACTION: surface projection state in the UI — "saved and published" vs "saved, publish pending" —
  so an editor can never again see success for a change no reader will get
- DEPENDS ON: Task 1B.2 (incremental projection). Until that exists, the honest interim is to say a
  re-import is required and show the pending state
- VALIDATE: save a title → reload the chapter as an anonymous reader → the title appears, with no
  manual command run

**Task 1.6b — Stop writing `canonical="true"` on editorial headings** *(raised in severity by A8)*
- ACTION: editorial section headings must not claim OSIS canonicity. Use `canonical="false"` (or omit)
- WHY: in OSIS, `canonical="true"` asserts the text **is scripture**. "The Creation" is not. This now
  corrupts the canonical record itself, not a derived copy
- ALSO: the 142 existing hand-added titles carry the wrong attribute and need correcting in the XML
- VALIDATE: `grep -c 'type="verse" canonical="true"' assets/kjv.osis.xml` → 0 for editorial headings

**Task 1.7 — Guard against silently-dropped verses**
- ACTION: after paragraph grouping, assert `paragraphs.flatMap(verses).count() === verses.count()`; throw/log loudly on mismatch. Add a null check at `DatabaseBibleReader:229`
- WHY: data is clean today (0 uncovered verses), but the code has no guard and Task 1.3's re-import is exactly when it would bite
- VALIDATE: a unit test with a deliberately gapped paragraph set raises

---

### Phase 1B — The projection pipeline (~1 week) — **required by the source-of-truth decision**

> This phase exists only because XML is now canonical. It converts `ImportOsisCommand` from a
> one-shot rebuild script into the mechanism that keeps the read model true. **Nothing in Phase 1
> can be safely re-projected until 1B.1 lands.**

**Task 1B.1 — Make the import idempotent (BLOCKER for every re-import in this plan)**
- ACTION: replace every bare `DB::table(...)->insert(...)` with `upsert()` keyed on the natural key
  (`osis_id`, or `verse_id`+`text_order` for child rows). `bible_versions` at `:129` must be
  `firstOrCreate` on `abbreviation` — today a second run creates a **duplicate KJV version row**,
  which activates the 8 version-ambiguous lookups in D5
- ACTION: wrap the projection in a transaction per book so a failure cannot leave a half-projected Bible
- WHY: the DB is now disposable and must be rebuildable **repeatedly**. Today re-running duplicates
  or trips unique constraints [VERIFIED — `insert()` everywhere, no truncate]
- VALIDATE: run `osis:import` twice on a populated DB → verse count stays 31,102, `bible_versions`
  stays 1 row, no constraint violations

**Task 1B.2 — Add targeted, incremental projection**
- ACTION: add `--book=`, `--chapter=`, `--verse=` filters. Signature today is
  `osis:import {file} {--chunk=100}` — **whole-file only** [VERIFIED], i.e. a multi-hour rebuild is
  the sole option
- WHY: Task 1.6 needs a save on `/admin/verse-titles` to re-project **one verse** in milliseconds
- VALIDATE: `php artisan osis:import --verse=Gen.28.1` completes in < 1s and updates exactly that
  verse's rows; `--book=Gen` touches only Genesis

**Task 1B.3 — Fix the projection's correctness bugs** *(these corrupt the read model)*
- `getJesusVerses()` / `addComprehensiveRedLetters()` — **delete** (Task 1.2). Red letters come from
  the XML or not at all (A3)
- The namespace-blind regex at `:1164` (Task 1.3) — the single largest source of projection loss
- `word_order`/`text_order` encode nesting depth, not sequence (`:824-832`) — only 2 distinct values
  across 355,143 rows
- `poetry_structure` — stop fabricating 4,939 rows with empty `line_text` from a source containing
  zero `<l>`/`<lg>`
- `divine_names.original_name` hardcoded `'YHWH'` for all 6,952 rows
- The `LIKE "%{$verseRef}%"` match at `:1577` (`'%Matt.5.3%'` also matches Matt.5.30–39)
- `@$fragment->appendXML(...)` at `:816` silently swallows failures
- VALIDATE: Task 1B.4 passes on a clean rebuild

**Task 1B.4 — Add `osis:verify` — prove the projection matches the source**
- ACTION: a command that streams the XML and asserts the DB agrees: verse count and text per book,
  `<q who="Jesus">` count vs `red_letter_text` rows, `<title>` count vs `titles` rows,
  `<transChange>`/`<divineName>` counts, paragraph milestones vs `paragraphs`
- WHY: with a derived read model you need a cheap, repeatable integrity check. This is also the
  regression test for every Phase 1 fix, and it is what makes the FTS-rebuild failure (below) visible
- VALIDATE: `php artisan osis:verify` exits 0 on a clean projection; deliberately corrupt one DB row
  → exits non-zero naming the verse

**Task 1B.5 — Make import failures loud**
- ACTION: `:1272-1279` catches an FTS rebuild failure, prints a warning, then prints
  `✅ FTS tables updated` two lines later and exits 0. Re-throw or `return self::FAILURE`
- WHY: `verses_fts` is the **only** search backend; a swallowed rebuild failure silently serves the
  previous import's index while CI and the operator see green
- VALIDATE: force the rebuild to fail → non-zero exit, no success tick

**Task 1B.6 — Decide the contained-format gap** *(A7)*
- The importer hardcodes `//osis:verse[@osisID][@sID]` (milestone only), so `mao.osis.xml` **cannot
  be projected at all** — it would create the version and 1,189 chapters, then zero verses, silently
- ACTION: either support both encodings in the projection, or fail loudly on an unsupported format.
  Do not leave the silent-zero-verses path
- VALIDATE: attempting to import `mao.osis.xml` either works or exits non-zero with a clear message

**Task 1B.7 — Automate the projection (the "auto load" in the directive)**
- ACTION: wire re-projection to the write. Options, cheapest first: (a) synchronous targeted
  re-projection on save (viable once 1B.2 makes it sub-second); (b) a queued job — the worker already
  exists and is healthy; (c) a `git post-merge`/deploy hook for out-of-band XML edits
- GOTCHA: **must** invalidate the chapter cache for the affected chapter — today *nothing is ever
  invalidated* (PERF-2), so a re-projected verse would stay stale for the full 24h TTL. If Task 6.x
  deletes the chapter cache layer, this concern disappears with it
- VALIDATE: edit a title in the admin UI → an anonymous reader sees it on next load, no manual step

---

### Phase 2 — Stop the bleeding on broken functionality (~1 day)

**Task 2.1** — `BibleService.php:140` → `return $book;` · VALIDATE: all 5 books → 200
**Task 2.2** — `SearchController.php:61` → `$result['verse_number']`; drop the `?? 1`/`?? ''` defaults on `:59-63` · VALIDATE: `verse` matches `reference` for every row
**Task 2.3** — `catch (\Exception)` → `catch (\Throwable)` across all 4 `Api/*Controller.php` **and log `$e`** (9 of 10 `Log::error` calls currently log only `getMessage()`) · VALIDATE: force an `\Error`; JSON envelope preserved, log has a stack
**Task 2.4** — Swap the search components: point `livewire-search.blade.php:14` at `<livewire:bible-search />`, rename `$query`→`$q` · **Precedes deletion (constraint 3)** · VALIDATE: `/searches?q=love` returns real verses, no `mock`
**Task 2.5** — `escapeFtsSearchTerm` → quote unconditionally: `'"'.str_replace('"','""',trim($term)).'"'` · VALIDATE: `q=AND`, `q=OR`, `q=NOT`, `q=a(b` all 200
**Task 2.6** — Add `->whereNumber(['chapterNumber','verseNumber'])` to `routes/api.php:26-27`; add `Route::fallback()` returning JSON 404; constrain the `web.php` catch-alls · VALIDATE: `/api/books/Gen/chapters/abc/verses` → 404 JSON; `/api/nope` → 404 not 302; `/api/books/Gen/chapters/1.5/verses` no longer silently serves chapter 1
**Task 2.7** — Re-enable the prev/next buttons (`@if(false)` at `:209`, `:224`) with `aria-label`s · VALIDATE: both render; clicking changes chapter
**Task 2.8** — Fix `{!! !!}` XSS: escape then re-introduce only `<mark>`; make `verse-paragraph.blade.php:58` match the allow-list used at `:19` · VALIDATE: `?query=<svg/onload=alert(1)>` renders escaped
**Task 2.9** — `APP_DEBUG=false`; add `errors/404.blade.php`, `errors/500.blade.php`; populate `withExceptions()` · VALIDATE: bad input → styled 404, no stack trace
**Task 2.10** — Delete `/translation` + `/user-session` `show` routes (keep `UserSessionController`'s **live statics**) · VALIDATE: `route:list` clean; `/` still redirects to last-visited

---

### Phase 3 — Performance & config (~2 days)

> Ordered by **measured** impact. Tasks 3.0 and 3.1 together are ~5 lines of change and account for
> most of the available win.

**Task 3.0 — Replace `whereHas` with direct `chapter_id` lookups (BIGGEST WIN, SMALLEST DIFF)**
- ACTION: `DatabaseBibleReader:129-135` and `:219-224` — resolve `chapter_id` first, then
  `where('chapter_id', $id)`. `:463-465` and `:93-95` — drop `UPPER()`/`LOWER()` wrappers that defeat
  the existing indexes (normalise the input instead)
- **NO MIGRATION.** `verses_chapter_id_verse_number_index` already exists and is simply never used
- MEASURED: 21.938 → 0.028 ms (**779×**) [VERIFIED]. Gen 1 DB time 69.3 → ~2 ms, wall ~103 → ~35 ms;
  Matt 5 wall 159 → ~48 ms
- ALSO: raise `cache_size` to `-64000` — 2MB cannot hold a 46.7MB table
- VALIDATE:
  `php -r '$d=new PDO("sqlite:database/bible_app.sqlite"); foreach($d->query("EXPLAIN QUERY PLAN <new sql>") as $r) echo $r["detail"],"\n";'`
  → must read `SEARCH verses USING INDEX verses_chapter_id_verse_number_index`, **never** `SCAN verses`

**Task 3.0b — Fix the one real N+1**
- ACTION: `BibleChapter.php:684` — eager-load `red_letter_text` or use a single `whereIn`
- VALIDATE: Matt 5 with Strong's **off** drops from 55 queries toward ~33

**Task 3.0c — Memoize the reader and version lookups**
- ACTION: `BibleService::getCurrentReader()` constructs a new `DatabaseBibleReader` on **every**
  delegate call (6 per request); `TranslationService:114` has no caching (8–10 `bible_versions`
  queries/request); replace `Cache::has()`+`Cache::get()` pairs with `Cache::remember()` (26 cache
  SELECTs/request)
- VALIDATE: steady-state Gen 1 query count drops from 44 toward ~15

**Task 3.1 — Enable the SQLite pragmas**
- ACTION: `config/database.php:40-42` → `busy_timeout => 10000`, `journal_mode => 'WAL'`, `synchronous => 'NORMAL'`
- MECHANISM: `null` fails the connector's `isset()` guard, so **no pragma is issued today** [VERIFIED]
- GOTCHAS: WAL requires local (non-NFS) storage; `busy_timeout` does **not** rescue `BEGIN DEFERRED` read-then-write transactions
- MEASURED [AGENT]: reader tail latency under a concurrent writer **629.27 ms → 0.21 ms**; 45KB cache write **4.78 ms → 0.09 ms (53×)**
- VALIDATE: `PRAGMA journal_mode` → `wal`; `-wal`/`-shm` appear (already covered by `database/.gitignore`)

**Task 3.2 — Split cache and queue onto separate SQLite files** · **Precedes 3.3 (constraint 4)** · VALIDATE: `cache`/`jobs` no longer in `bible_app.sqlite`
**Task 3.3 — Enable throttling**: `$middleware->throttleApi()` + `RateLimiter::for('api'…)`/`('search'…)` in `AppServiceProvider::boot()` · VALIDATE: rapid requests yield 429 + `X-RateLimit-*`
**Task 3.4 — Get `original_xml` and bulk arrays out of Livewire state**
- ACTION: `#[Computed]` for `verses`, `testamentBooks`, `chapters`, adjacent chapters; `#[Locked]` on `bookOsisId`, `chapterNumber`, `currentTranslation`; strip `original_xml` from the render payload (ideally pre-render OSIS→HTML at import)
- **DELETE OUTRIGHT** — measured as read by no view: `books` (7,829 B), `paragraphs` (7,348 B, plus 2 wasted queries), `capabilities` (275 B), `combined_text` (11,410 B)
- **Depends on Task 3.0**: re-deriving in `render()` only makes sense once the query costs 0.028 ms instead of 21.9 ms. After 3.0 the re-derive is *cheaper than the unserialize it replaces*
- VALIDATE: snapshot 150,579 → < 35,000 B; page 318,887 → < 200,000 B; round-trip 396,620 → < 100,000 B
**Task 3.5 — Add `wire:key` to all 12 loops** · VALIDATE: chapter grid and results list keep focus/state across updates
**Task 3.6 — Kill the log flood**: demote/delete per-verse `Log::info` (`BibleChapter:497-705`); `LOG_LEVEL=warning`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`; add logrotate · VALIDATE: one Ps 119 request writes < 1KB (from 110,919)
**Task 3.7 — Fix nginx**: repeat all headers with `always` inside `location ^~ /build/` (or use an included snippet); single `Cache-Control`; uncomment `gzip_types`/`gzip_vary`; add `try_files $uri =404;` to the PHP block; `client_max_body_size 2M`; drop `error_page 404 /index.php` · VALIDATE: `curl -I /build/assets/app-*.css` shows both security headers, one `Cache-Control`, and `Content-Encoding: gzip`
**Task 3.8 — Remove `app.js` from the 3 `@vite` calls** (`livewire-search`, `livewire-search-poc`, `admin/verse-titles`) — fixes double-Alpine **and** removes shipped `axios` in one edit · VALIDATE: each page loads only `livewire.js`; no Alpine warning
**Task 3.9 — `@variant` → `@custom-variant dark (&:where(.dark, .dark *))`**; honour OS preference in the init script · VALIDATE: `grep -c prefers-color-scheme` on compiled CSS > 0, or first-visit dark works
**Task 3.10 — Fix `.env.example`** (`sqlite`, correct `DB_DATABASE`, `SESSION_DRIVER=file`) + document the `BIBLE_*` keys · VALIDATE: a fresh clone using it boots

---

### Phase 4 — Test suite (~1 week) — **after Task 0.7**

**Task 4.1** — Delete the dead-code test mass: `BibleControllerTest`, `OsisReaderTest`, `OsisReaderIntegrationTest`, `ContainedOsisParserTest`, `ParserIntegrationTest`, `MilestoneOsisParserTest` (1,623 LOC, −84s). **Keep** `BibleConfigTest` + `TranslationServiceTest` · VALIDATE: suite ~3s / ~90 passing
**Task 4.2** — Repair Feature contracts: named routes, assert the **302** contract for the intentional legacy redirects, API URLs + `['data' => …]` nesting, and the 4 `assertNotFound()` cases Task 0.7 just made real
**Task 4.3** — Build a fixture: export Gen 1–3, Ps 23, John 3, Rev 22 (+ books/chapters/titles/word_elements/red_letter_text) to `tests/Fixtures/bible-test.sqlite` (a few hundred KB). **Never point tests at the 147MB file** (constraint 7) · VALIDATE: fixture-backed Feature tests assert real content
**Task 4.4** — Add `Livewire::test()` coverage for all 5 components — currently **zero** · Include a **snapshot-size regression assertion** so 318KB cannot silently return, plus `expectsDatabaseQueryCount()`
**Task 4.5** — Add API contract tests (would have caught F1 and F2)
**Task 4.5b** — **Un-exclude `app/Console` from coverage** (`phpunit.xml:20`) and test the projection engine. Under the source-of-truth decision the importer is no longer a run-once script — it is the mechanism keeping the read model true, and it is currently the largest untested file in the repo (1,687 LOC). Cover: idempotency (1B.1), targeted filters (1B.2), the red-letter/`transChange`/`divineName` extraction fixes (1B.3), and `osis:verify` (1B.4) against a small hand-written OSIS fixture exercising milestone verses, nested `<q who="Jesus">`, `<transChange>`, `<divineName>`, and a paragraph milestone
**Task 4.6** — Delete `run-tests.sh` and `run-dev-tests.sh` (they print success while failing); replace with `composer test` → `pest --parallel`. Install `pcov` or delete `run-unit-coverage.sh`
**Task 4.7** — Add GitHub Actions CI. **Nothing else prevents a repeat of this exact 14-month drift** (`git log -- tests/` shows 3 commits, last 2025-05-31, with 7 architecture commits after it)

---

### Phase 5 — Accessibility minimum (~2 days)

`<h1>` for the chapter reference; `<h2>` for section titles; `<main>` landmark; verse paragraphs as
`<p>`; accessible name for red-letter text (currently **colour-only**); `role="dialog"` +
`x-trap` + Escape on the book selector; `aria-expanded`/`aria-haspopup` on the translation dropdown;
`aria-live` on results; `viewport-fit=cover`; `p-3` for 44×44 touch targets; per-chapter `<title>`;
`bg-blue-700` for the 4.06:1 contrast failure.
VALIDATE: `grep -c '<h1\|<main\|role=' rendered` > 0; axe-core clean on the chapter page.

---

### Phase 6 — Cleanup & consolidation (~1 week) — **last**

**Task 6.1** — Delete Tier 1 (~920 LOC): `BibleLayout.vue`, `Api/Controller.php`, `UserFactory`, `MIGRATION_TEST_COMMANDS.md`, `bible-app.code-workspace`, `concurrently`, 9 unused trait methods, 10 uncalled methods, 3 unused imports, 3 dead config keys
**Task 6.2** — Delete the Strong's island (995 LOC) + `WordElement::strongsLexicon()`; both tables are empty
**Task 6.3** — **Decide** the XML reader's fate (F4): 1,351 LOC that returns zero rows for both English translations. Recommend **delete** (with `asv`/`mao` retained only if any test still needs them) unless `BIBLE_READER_TYPE=xml` is a real deployment mode
**Task 6.4** — Delete `SearchComponent` + its view — **only after Task 2.4**
**Task 6.5** — Extract `ChapterCacheService`: one owner for the key format, TTL (from `config('bible.database.cache_duration')`, currently read by nothing), and the Psalm-119 rule (3 copies). Removes the Livewire→HTTP dependency edge
**Task 6.6** — Extract `VerseRenderer` from `BibleChapter:497-723` (~230 LOC) — unit-testable without Livewire
**Task 6.7** — Extract `OsisHtmlFormatter` (~180 LOC, pure `string→string`) and `ParagraphAssembler` (~250 LOC, pure `Collection→Collection`) — best effort/risk ratio in the two fat classes
- **Urgency raised by the source-of-truth decision.** The earlier rating ("lowest urgency — a run-once command with no live blast radius") is **no longer true**: `ImportOsisCommand` is now the projection engine that every scripture edit flows through. Its blast radius is the entire read model. Extracting the pure `string→string` formatter is what makes Phase 1B's correctness fixes testable at all
**Task 6.8** — Consolidate canon data (7 duplicate encodings, ~400 LOC) onto the `Book` model
**Task 6.9** — **Pick one formatter** and run it once. **LAST** (constraint 9)
**Task 6.10** — Fix the 5 closure routes, then `config:cache` + `route:cache` + `event:cache` (constraint 6)
**Task 6.11** — Document the multi-translation blockers (8 unscoped lookups, no `books.version_id`, importer can't read contained format). **Do not attempt** — see NOT Building
**Task 6.12** — Decide `poetry_structure`: 4,939 rows, all `line_text` empty, read by a zero-caller method. Wire it up or stop extracting

---

## Validation Commands

```bash
# L1 static
./vendor/bin/pint --test                      # currently 69/111 fail — expect 0 after 6.9
php -l <changed files>

# L2 tests
php artisan test                              # baseline: 113 failed / 133 passed / 9 risky / 106.83s
./vendor/bin/pest --profile                   # 84 of 87s is XML fixture loading of dead code

# L3 build
npm run build && php artisan config:cache     # config:cache verified SAFE
php artisan route:cache                       # WILL FAIL until the 5 closure routes are fixed

# L4 live smoke (nginx)
for p in / /books/Gen/chapters/1 /books/Ps/chapters/23 /searches /api/books /api/books/Gen; do
  curl -s -o /dev/null -w "%{http_code} $p\n" -H 'Host: bible.local' "http://127.0.0.1$p"; done
# /api/books/Gen must become 200 (Task 2.1)

# L5 security regressions
curl -s -o /dev/null -w '%{http_code} admin\n'  -H 'Host: bible.local' http://127.0.0.1/admin/verse-titles   # want 302/403
php artisan route:list | grep _native                                                                        # want empty
curl -s -H 'Host: bible.local' 'http://127.0.0.1/searches?query=<svg/onload=alert(1)>' | grep -c 'onload='    # want 0

# L6 query-plan regression guard — the single most important perf check
php -r '$d=new PDO("sqlite:database/bible_app.sqlite");
  foreach($d->query("EXPLAIN QUERY PLAN select * from verses where chapter_id = 1 order by verse_number") as $r)
    echo $r["detail"],"\n";'   # must say SEARCH ... USING INDEX, never "SCAN verses"

# L6 payload + domain regressions
curl -s -H 'Host: bible.local' http://127.0.0.1/books/Gen/chapters/1 -o /dev/null -w 'bytes=%{size_download}\n'  # 318887 → <200000
php -r '$d=new PDO("sqlite:database/bible_app.sqlite");
  echo "false red letters: ",$d->query("select count(*) from red_letter_text where attributes like \"%comprehensive%\"")->fetchColumn(),"\n";'  # want 0
curl -s -H 'Host: bible.local' http://127.0.0.1/books/Ps/chapters/23 | grep -c divineName   # want 0 in rendered DOM
```

---

## Acceptance Criteria

- [ ] Psalm 23:1 reads **"The LORD is my shepherd"** — small caps, italics, correct spacing
- [ ] Genesis 2:4 reads **"These *are* the generations"** — not "Theseare"
- [ ] No verse renders red without `<q who="Jesus">` in source (John 4:9, Luke 5:8, John 6:42 black)
- [ ] Christ's words render red in ≈2,035 verses (from 1,460), **derived from `<q who="Jesus">` in the XML — no hardcoded verse list anywhere in the codebase**
- [ ] `osis:import` is idempotent — running it twice leaves 31,102 verses and 1 `bible_versions` row
- [ ] `osis:import --verse=Gen.28.1` completes in < 1s and updates only that verse
- [ ] `osis:verify` exits 0 on a clean projection and non-zero on a corrupted one
- [ ] Editing a title in `/admin/verse-titles` is visible to an anonymous reader with **no manual command**
- [ ] The XML write is atomic and locked — a killed save never leaves an unparseable source
- [ ] An FTS rebuild failure makes `osis:import` exit non-zero (no success tick after a caught error)
- [ ] `/admin/*` unreachable anonymously; `POST /_native/api/events` gone or denied
- [ ] `livewire/livewire` ≥ 3.6.4; `composer audit` runs and is clean
- [ ] `/api/books/{book}` 200 for all 66 books; API `verse` matches `reference`
- [ ] `/searches` returns real verses; no reflected XSS
- [ ] **No `SCAN verses` in any `EXPLAIN QUERY PLAN` on the chapter path** — indexed lookup only
- [ ] Gen 1 wall time < 40ms (from 103ms); Matt 5 < 55ms (from 159ms)
- [ ] Chapter page < 200KB with snapshot < 35KB; no `original_xml` client-side; round-trip < 100KB
- [ ] `PRAGMA journal_mode` = `wal`; cache/queue on separate files; API throttled
- [ ] `php artisan test` → 0 failures, 0 risky, with `Livewire::test()` coverage on all 5 components
- [ ] CI runs on every push
- [ ] `pint --test` → 0 failures with a single formatter configured
- [ ] `failed_jobs` exists; queue worker survives a failing job

---

## Risks and Mitigations

| Risk | L | I | Mitigation |
|---|---|---|---|
| **Re-import (Task 1.3) corrupts the only DB copy** | MED | **CRITICAL** | Task 0.10 backup **first**. Verify `count(*) from verses` = 31102 on the dump before importing |
| **Re-import duplicates every row / creates a 2nd `bible_versions`** | **HIGH** | **CRITICAL** | Constraint 0 — the importer is `insert()`-only [VERIFIED]. Task 1B.1 **must** land before any re-projection, or D5's 8 version-ambiguous lookups activate |
| **A corrupted XML destroys the source of truth** | MED | **CRITICAL** | Task 1.5b (atomic write + lock + parse-before-rename); git history is the recovery path (all 3 OSIS files tracked) |
| **A wrong projection silently corrupts scripture in the read model** | MED | **HIGH** | Task 1B.4 `osis:verify` as a gate after every import; Task 1B.5 makes failures loud |
| **Editors keep seeing "saved" for changes readers never get** | HIGH | MED | Task 1.6 ties the success message to the projection actually running; until 1B.2 exists, show "publish pending" honestly |
| **`migrate:fresh` destroys production data via a test run** | LOW | **CRITICAL** | Constraint 7. Never remove `DB_DATABASE=:memory:`; Task 4.3 uses a separate fixture file |
| Deleting the "dead" search component removes the only working search | MED | HIGH | Constraint 3 — Task 2.4 precedes Task 6.4 |
| Dropping `search_results` breaks all search | MED | HIGH | Documented in the do-not-delete list; it backs `verses_fts` |
| Fixing tests before `dd()` kills the PHPUnit process mid-run | HIGH | MED | Constraint 1 — Task 0.7 precedes Phase 4 |
| Throttling worsens SQLite contention | MED | MED | Constraint 4 — Task 3.2 precedes 3.3 |
| Formatter sweep conflicts with in-flight work | HIGH | LOW | Constraint 9 — Task 6.9 last |
| `middleware('auth')` unsatisfiable (no `User` model) | HIGH | HIGH | Task 0.5 gotcha — scaffold auth or restrict by IP; **do not leave it open** |
| Deleting `asv`/`mao` XML breaks the test suite | MED | MED | 4 test files read them via `base_path()`; resolve with Task 6.3 |
| Task 1.2 reduces visible red letters before 1.3 restores them | HIGH | MED | Ship 1.2 and 1.3 together, or accept fewer-but-correct in the interim. **Correct-and-fewer beats more-and-false** |

---

## Open Decisions For The User

| # | Decision | Recommendation |
|---|---|---|
| **O-1** | Keep `nativephp/mobile`? | **Remove it.** It is a never-initialised package whose only live effect is an unauthenticated object-injection endpoint, and it blocks `composer install`/`audit` everywhere. Re-add when mobile work actually starts |
| **O-2** | Keep the **runtime** XML reader (`OsisReader` + 2 parsers, 1,351 LOC, returns zero rows for KJV and ASV)? | **Still delete** — and the source-of-truth decision does **not** save it. Two different things parse XML here: the *runtime reader* (`BIBLE_READER_TYPE=xml`, broken, 422MB RSS) and the *projection builder* (`ImportOsisCommand`, which **bypasses the parser abstraction entirely** and hardcodes its own XPaths). The decision makes the **projection builder** critical (Phase 1B); the runtime reader stays deletable. Caveat: 4 test files read `asv`/`mao` via `base_path()` |
| **O-3** | Is the API a product surface? | It has **zero** first-party consumers. If not, fix F1/F2 and stop investing |
| **O-4** | Which formatter — Pint or php-cs-fixer? | **Pint** (Laravel-native, zero-config). Delete `.php-cs-fixer.dist.php`, whose config still references an unrelated ticket |
| **O-5** | Auth for `/admin/*` — scaffold, or IP-restrict? | Scaffold Laravel auth if verse-title editing continues; IP-restrict if it's a solo tool |
| **O-6** | Fix red letters via source XML, or curate the list properly? | **Source XML** (Task 1.3). The hardcoded list is unmaintainable and currently wrong in both directions |

---

## Notes

**What this audit did well:** independent re-verification caught three of my own wrong premises
(gzip, systemd restart semantics, Alpine direction), one agent's wrong premise (the queue worker
*is* running), and — most valuably — inverted two conclusions: the "dead" search component is the
working one, and the "missing" Alpine is actually double-loaded. A plan built on the unverified
versions would have deleted working code and "fixed" a non-bug.

**All 11 agents reported.** `perf-audit` landed last and closed the only substantive gap — index
adequacy is now **verified** (the schema is fine; it is a query-shape bug), and it supplied the
single highest-impact finding in the audit (PERF-0, 779× re-measured by me).

**Remaining gaps, stated honestly:**
- No sustained concurrent *load* test at scale; "no rate limiting" is proven and concurrency-10
  latency was measured (p50 520ms), but the throughput ceiling is not established.
- The Alpine double-registration *symptom* is inferred from load order + bundle contents, not
  observed in a browser. The double `Alpine.start()` itself is certain from the code.
- Red-letter counts (2,035 source verses, 575 missing, 324 false) are `[AGENT]` — I verified the
  *method* and spot-checked 5 verses against the source XML; I did not re-run the full corpus scan.
- Official NativePHP guidance on preventing web-side route registration **does not exist** — all
  mitigations in Task 0.4 are ours, not vendor-sanctioned.

**The single most important framing for whoever executes this:** the app's *infrastructure* is in
better shape than it looks (verse integrity is immaculate — all 66 books at exact canonical counts,
no gaps, no duplicates, no orphans; paragraph data fully covers all 1,189 chapters; the FTS setup is
textbook-correct). What is broken is the **presentation layer on the default path** and the
**periphery** — API, admin, error handling, tests. That is a much better position than it first
appears, and it means Phase 1 buys disproportionate value.

---

## Appendix: Source-of-Truth Invariants

Four rules that follow from the 2026-08-19 decision. Any future change that breaks one is a
regression, regardless of what else it improves.

1. **No application feature writes scripture data directly to the database.** Titles, red letters,
   verse text, paragraphs — all of it enters through `assets/*.osis.xml` and reaches SQLite only via
   `osis:import`. The database has no authoritative content of its own.
2. **No scripture content is generated in PHP.** The deleted `getJesusVerses()` array is the
   cautionary example: 450 hardcoded verse IDs that produced 324 false attributions and still missed
   575 real ones. If the source is wrong, fix the source.
3. **The database must be droppable.** `rm bible_app.sqlite && php artisan migrate && php artisan
   osis:import` must reproduce the exact read model. `osis:verify` is the proof. If a fact exists
   only in SQLite, it is a bug.
4. **The source must always parse.** Every XML write is atomic, locked, and validated before it
   replaces the file; git carries the history.

**Corollary — where "fix the scripture" now points:** a wrong verse, a missing red letter, a bad
title is fixed in `assets/kjv.osis.xml`, committed to git, and re-projected. Never with a SQL
`UPDATE`, and never with a PHP lookup table.
