<?php

namespace App\Console\Commands;

use App\Jobs\PublishChapterProjection;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportOsisCommand extends Command
{
    protected $signature = 'osis:import
        {file=assets/kjv.osis.xml}
        {--chunk=100 : Number of verses to process per chunk}
        {--book= : Only project this book, by OSIS id (e.g. Gen)}
        {--chapter= : Only project this chapter, as book.chapter (e.g. Gen.28)}
        {--verse= : Only project this verse, as book.chapter.verse (e.g. Gen.28.1)}
        {--production : Required to write the database when APP_ENV=production}
        {--allow-deletions : Allow removing more than 50 verses that are no longer in the source}
        {--lock-wait=60 : Seconds to wait for a running chapter publish to finish}';

    protected $description = 'Import OSIS XML file into the comprehensive Bible database';

    private $versionId;

    private $books = [];

    private $chapters = [];

    private $verseCount = 0;

    private $wordElementCount = 0;

    private $translatorChangeCount = 0;

    private $divineNameCount = 0;

    private $redLetterCount = 0;

    private $titleCount = 0;

    private $poetryLineCount = 0;

    private $paragraphCount = 0;

    private $xpath;

    private $doc;

    /** @var array<string, list<string>> in-scope verses that could not be projected, by reason */
    private array $skipped = [];

    /** @var array<string, true> in-scope verse osisIDs projected in this run */
    private array $seenVerses = [];

    // Paragraph tracking
    private $currentParagraphStart = null;

    private $currentParagraphVerses = [];

    private $currentChapterId = null;

    public function handle()
    {
        // Artisan::call() and a long-lived queue worker reuse this command instance; every run
        // must start from scratch (a stale $purgedChapters made a second in-process run append
        // duplicate titles).
        $this->resetRunState();

        $file = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        $this->scope = $this->resolveScope();

        if ($this->scope === false) {
            return 1;
        }

        $this->info("🚀 Starting OSIS XML import from: {$file}");
        $this->info("📊 Chunk size: {$chunkSize} verses");

        if ($this->scope !== null) {
            $this->info("🎯 Scope: {$this->scope} (targeted re-projection)");
        }

        $connection = config('database.default');
        $this->line('🗄️  Target database: '.config("database.connections.{$connection}.database"));

        if (app()->environment('production') && ! $this->option('production')) {
            $this->error('Refusing to write the production database without --production.');

            return 1;
        }

        // One projection at a time: a queued chapter publish (PublishChapterProjection) rebuilds the
        // same FTS index. Its own osis:import subprocess inherits the job's lock instead.
        $lock = null;

        if (getenv(PublishChapterProjection::LOCK_HELD_ENV) !== '1') {
            $lock = Cache::lock(PublishChapterProjection::lockKey(), 900);

            try {
                $lock->block(max(0, (int) $this->option('lock-wait')));
            } catch (LockTimeoutException) {
                $this->error('Another projection (a queued chapter publish) is running. Try again when the queue is idle.');

                return 1;
            }
        }

        try {
            return $this->project($file, $chunkSize);
        } finally {
            $lock?->release();
        }
    }

    private function project(string $file, int $chunkSize): int
    {
        try {
            // Load and validate XML
            $this->loadXML($file);

            // Create Bible version record
            $this->createBibleVersion();

            // Import structure and content
            $this->importBooks();
            $this->importChapters();

            // Process book titles after chapters are available
            $this->processAllBookTitles();

            $this->importVerses($chunkSize);

            // Finalize any remaining paragraphs (chapter-wide; see scopeCoversWholeChapters)
            if ($this->scopeCoversWholeChapters()) {
                $this->finalizeParagraphs();
            }

            // The projection must not keep verses the source no longer has.
            $this->removeVersesMissingFromSource();

            // Update FTS tables
            $this->updateFTSTables();

            // Readers' caches derive from the old projection: invalidate all of them at once.
            \App\Support\ProjectionCache::bump();

            $this->displaySummary();

            // A run that skipped in-scope verses projected an incomplete Bible: say so, and exit
            // non-zero, instead of printing a success summary over missing scripture.
            if ($this->skipped !== []) {
                foreach ($this->skipped as $reason => $ids) {
                    $this->error(sprintf('❌ %d verse(s) not projected (%s): %s', count($ids), $reason, implode(', ', array_slice($ids, 0, 10))));
                }

                return 1;
            }
        } catch (\Throwable $e) {
            // Chapters committed before the failure changed the projection too.
            \App\Support\ProjectionCache::bump();
            $this->error('❌ Import failed: '.$e->getMessage());
            Log::error('OSIS Import Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return 1;
        }

        return 0;
    }

    private function loadXML($file)
    {
        $this->info('📖 Loading XML file...');

        $this->doc = new DOMDocument;
        // Keep the source's own whitespace and never pretty-print: stored fragments used to be
        // re-indented ("LORD ," in 307 verses) and whitespace-only text nodes - the spaces BETWEEN
        // words - were dropped, then guessed back by regexes that got it wrong ("( he said").
        $this->doc->preserveWhiteSpace = true;
        $this->doc->formatOutput = false;

        // Hold the editor's sidecar lock (shared) while reading, so a save cannot rename a new
        // version into place mid-read. Only around load(): holding it for the whole import would
        // make editor saves time out ("busy"). The lock file exists only for files the editor
        // writes; fixtures and copies are read without it.
        $lock = file_exists($file.'.lock') ? @fopen($file.'.lock', 'r') : false;
        if ($lock) {
            flock($lock, LOCK_SH);
        }

        try {
            libxml_use_internal_errors(true);
            if (! $this->doc->load($file)) {
                $errors = libxml_get_errors();
                throw new Exception('XML parsing failed: '.implode(', ', array_map(fn ($e) => $e->message, $errors)));
            }
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $this->xpath = new DOMXPath($this->doc);
        $this->xpath->registerNamespace('osis', 'http://www.bibletechnologies.net/2003/OSIS/namespace');

        if ($this->xpath->query('//osis:verse[@sID]')->length === 0 && $this->xpath->query('//osis:verse[@osisID]')->length > 0) {
            throw new Exception('Contained-format OSIS (<verse osisID="...">text</verse>) is not supported; only milestone verses (sID/eID). Nothing was projected.');
        }

        $this->info('✅ XML loaded successfully');
    }

    private function createBibleVersion()
    {
        $this->info('📚 Creating Bible version record...');

        // Extract version information from the OSIS file
        $versionInfo = $this->extractVersionInfo();

        if (! $versionInfo) {
            throw new Exception('Could not extract version information from OSIS file');
        }

        // Check if version already exists
        $existingVersion = DB::table('bible_versions')
            ->where('osis_work', $versionInfo['osis_work'])
            ->first();

        if ($existingVersion) {
            $this->warn("⚠️  Bible version '{$versionInfo['osis_work']}' already exists (ID: {$existingVersion->id})");
            $this->warn('    This import will re-process titles and other elements.');
            $this->versionId = $existingVersion->id;

            return;
        }

        $this->versionId = DB::table('bible_versions')->insertGetId([
            'osis_work' => $versionInfo['osis_work'],
            'abbreviation' => $versionInfo['abbreviation'],
            'title' => $versionInfo['title'],
            'language' => $versionInfo['language'],
            'description' => $versionInfo['description'],
            'publisher' => $versionInfo['publisher'],
            'canonical' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("✅ Bible version created (ID: {$this->versionId})");
    }

    /**
     * Extract version information from the OSIS XML file
     */
    private function extractVersionInfo()
    {
        // Get the osisText element to extract basic info
        $osisText = $this->xpath->query('//osis:osisText')->item(0);
        if (! $osisText) {
            return null;
        }

        $osisIDWork = $osisText->getAttribute('osisIDWork') ?: 'Unknown';
        $language = $osisText->getAttribute('xml:lang') ?: 'en';

        // Get work element for detailed information
        $workElement = $this->xpath->query('//osis:work[@osisWork]')->item(0);

        $title = 'Unknown Bible Version';
        $description = 'Bible version imported from OSIS XML';
        $publisher = 'Unknown';

        if ($workElement) {
            // Try to get title from work element
            $titleElement = $this->xpath->query('.//osis:title', $workElement)->item(0);
            if ($titleElement) {
                $title = trim($titleElement->textContent);
            }

            // Try to get description
            $descElement = $this->xpath->query('.//osis:description', $workElement)->item(0);
            if ($descElement) {
                $description = trim($descElement->textContent);
            }
        }

        // Set default values based on known versions
        $versionMappings = [
            'KJV' => [
                'title' => 'King James Version',
                'publisher' => 'Public Domain',
                'description' => 'King James Version imported from OSIS XML',
            ],
            'ASV' => [
                'title' => 'American Standard Version',
                'publisher' => 'Public Domain',
                'description' => 'American Standard Version imported from OSIS XML',
            ],
            'MAO' => [
                'title' => 'Maori Bible',
                'publisher' => 'Public Domain',
                'description' => 'Maori Bible imported from OSIS XML',
            ],
        ];

        if (isset($versionMappings[$osisIDWork])) {
            $mapping = $versionMappings[$osisIDWork];
            $title = $mapping['title'];
            $publisher = $mapping['publisher'];
            $description = $mapping['description'];
        }

        return [
            'osis_work' => 'Bible.'.strtolower($language).'.'.strtolower($osisIDWork),
            'abbreviation' => $osisIDWork,
            'title' => $title,
            'language' => $language,
            'description' => $description,
            'publisher' => $publisher,
        ];
    }

    private function importBooks()
    {
        $this->info('📚 Loading existing books...');

        // Load existing books from database instead of creating new ones
        // Books are shared across all Bible versions
        $existingBooks = DB::table('books')->get();

        foreach ($existingBooks as $book) {
            $this->books[$book->osis_id] = $book->id;
        }

        if ($this->books === []) {
            throw new Exception('No books in the database - seed them first (php artisan db:seed). Nothing was projected.');
        }

        // Verify that the OSIS file contains the expected books
        $bookDivs = $this->xpath->query('//osis:div[@type="book"]');
        $osisBooks = [];

        foreach ($bookDivs as $bookDiv) {
            $osisId = $bookDiv->getAttribute('osisID');
            if ($osisId) {
                $osisBooks[] = $osisId;
            }
        }

        // Check for any missing books in our database
        $missingBooks = [];
        foreach ($osisBooks as $osisBook) {
            if (! isset($this->books[$osisBook])) {
                $missingBooks[] = $osisBook;
            }
        }

        if (! empty($missingBooks)) {
            $this->warn('⚠️  Some books from OSIS are not in database: '.implode(', ', $missingBooks));
            $this->warn('    You may need to add these books to the database first.');
        }

        $foundBooks = array_intersect($osisBooks, array_keys($this->books));
        $this->info('✅ Books available for import: '.count($foundBooks).' (Total in OSIS: '.count($osisBooks).')');
    }

    private function importChapters()
    {
        $this->info('📖 Importing chapters...');

        // Get all chapters with namespace
        $chapterElements = $this->xpath->query('//osis:chapter[@osisID]');

        foreach ($chapterElements as $chapterElement) {
            $osisId = $chapterElement->getAttribute('osisID');
            if (! $osisId) {
                continue;
            }

            // Parse book and chapter (e.g., "Gen.1" -> "Gen", 1)
            $parts = explode('.', $osisId);
            if (count($parts) < 2) {
                continue;
            }

            $bookOsis = $parts[0];
            $chapterNumber = (int) $parts[1];

            $bookId = $this->books[$bookOsis] ?? null;
            if (! $bookId) {
                continue;
            }

            // Check if this chapter already exists for this version
            $existingChapter = DB::table('chapters')
                ->where('book_id', $bookId)
                ->where('version_id', $this->versionId)
                ->where('chapter_number', $chapterNumber)
                ->first();

            if ($existingChapter) {
                $this->chapters[$osisId] = $existingChapter->id;

                continue; // Skip if chapter already exists for this version
            }

            $chapterTitle = $chapterElement->getAttribute('chapterTitle');

            $chapterId = DB::table('chapters')->insertGetId([
                'book_id' => $bookId,
                'version_id' => $this->versionId,
                'chapter_number' => $chapterNumber,
                'osis_ref' => $osisId,
                'osis_id' => $osisId,
                'chapter_title' => $chapterTitle,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->chapters[$osisId] = $chapterId;

            // Process any title elements that immediately follow this chapter
            $this->processChapterTitles($chapterElement, $chapterId);
        }

        $this->info('✅ Chapters imported: '.count($this->chapters));
    }

    /**
     * Process titles that appear at the chapter level (like chapter headings)
     *
     * @param  mixed  $chapterElement
     * @param  mixed  $chapterId
     */
    private function processChapterTitles($chapterElement, $chapterId)
    {
        $current = $chapterElement->nextSibling;
        $order = 1;

        while ($current) {
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $current->localName ?: $current->nodeName;

                if ($nodeName === 'title') {
                    $titleType = $current->getAttribute('type') ?: 'main';

                    // Only process titles that should be at chapter level
                    if ($titleType === 'chapter') {
                        $this->importChapterTitle($current, $chapterId, $order);
                        $order++;
                    }
                } elseif ($nodeName === 'verse') {
                    // Stop when we reach the first verse
                    break;
                }
            }
            $current = $current->nextSibling;
        }
    }

    /**
     * Import a title specifically associated with a chapter
     *
     * @param  mixed  $element
     * @param  mixed  $chapterId
     * @param  mixed  $order
     */
    private function importChapterTitle($element, $chapterId, $order)
    {
        // Chapter titles carry verse_id = null, so purgeVerseProjection() cannot reach them.
        // Clear this chapter's chapter-scoped titles once per import before rewriting them.
        $this->purgeChapterProjectionOnce($chapterId);

        // Extract inner content and format to HTML (preserve formatting)
        $innerContent = '';
        foreach ($element->childNodes as $child) {
            $innerContent .= $this->doc->saveXML($child);
        }

        // Format the title content to HTML
        $formattedText = $this->formatToHTML($innerContent);

        $titleType = $element->getAttribute('type') ?: 'chapter';
        $canonical = $element->getAttribute('canonical') === 'true';

        // Store title in database
        $titleData = [
            'verse_id' => null, // Chapter titles are not associated with specific verses
            'chapter_id' => $chapterId,
            'title_type' => $titleType,
            'title_text' => trim($formattedText),
            'canonical' => $canonical,
            'placement' => 'before',
            'title_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('titles')->insert($titleData);
        $this->titleCount++;
    }

    private function importVerses($chunkSize)
    {
        $this->info('📝 Importing verses and content...');

        // Get all verse start markers with namespace
        $verses = $this->xpath->query('//osis:verse[@osisID][@sID]');
        $totalVerses = $verses->length;

        $this->info("📊 Total verses to import: {$totalVerses}");

        $bar = $this->output->createProgressBar($totalVerses);
        $bar->start();

        // One short IMMEDIATE transaction per chapter: a crash can no longer leave a chapter
        // half-purged, and readers (WAL) are never blocked for longer than one chapter's write.
        $processed = 0;
        $openChapter = null;

        try {
            foreach ($verses as $verseElement) {
                $osisId = $verseElement->getAttribute('osisID');
                if ($osisId && $this->inScope($osisId)) {
                    $chapterRef = implode('.', array_slice(explode('.', $osisId), 0, 2));
                    if ($chapterRef !== $openChapter) {
                        if ($openChapter !== null) {
                            DB::commit();
                        }
                        DB::beginTransaction();
                        $openChapter = $chapterRef;
                    }
                }

                $this->processVerse($verseElement);
                $processed++;

                if ($processed % 100 === 0) {
                    $bar->advance(100);
                }
            }

            if ($openChapter !== null) {
                DB::commit();
                $openChapter = null;
            }
        } catch (\Throwable $e) {
            if ($openChapter !== null) {
                DB::rollBack();
            }

            throw $e;
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * OSIS-id prefix limiting this run, or null for a full projection.
     *
     * The database is a projection of the XML, so an edit to one verse should re-project
     * that verse - not rebuild all 31,102. Combined with the idempotent purge, a scoped run
     * is safe to repeat.
     */
    private ?string $scope = null;

    /**
     * Turn --book/--chapter/--verse into a single OSIS-id prefix.
     *
     * @return string|null|false prefix, null for a full run, or false if the input is invalid
     */
    private function resolveScope(): string|null|false
    {
        $given = array_filter([
            'verse' => $this->option('verse'),
            'chapter' => $this->option('chapter'),
            'book' => $this->option('book'),
        ], fn ($v) => $v !== null && $v !== '');

        if (count($given) > 1) {
            $this->error('Use only one of --book, --chapter or --verse.');

            return false;
        }

        if ($given === []) {
            return null;
        }

        $kind = array_key_first($given);
        $value = trim($given[$kind]);
        $expected = ['book' => 1, 'chapter' => 2, 'verse' => 3][$kind];

        if (count(explode('.', $value)) !== $expected) {
            $this->error("--{$kind} expects ".$expected.' dot-separated part(s), e.g. '.
                ['book' => 'Gen', 'chapter' => 'Gen.28', 'verse' => 'Gen.28.1'][$kind].".  Got: {$value}");

            return false;
        }

        // --verse widens to its chapter deliberately.
        //
        // Some derived rows are chapter-scoped but produced from a verse position: a
        // <title type="chapter"> found at a verse is stored with verse_id = NULL (see
        // importVerseTitle), so it cannot be purged by verse. A true single-verse re-projection
        // therefore either duplicates that title (+1 per run, measured) or, if it purges by
        // chapter, deletes the contributions of the chapter's other verses. Widening to the
        // chapter avoids both and is still fast - 22 verses for Gen 28.
        if ($kind === 'verse') {
            $parts = explode('.', $value);
            $chapter = $parts[0].'.'.$parts[1];
            $this->line("ℹ️  --verse={$value} widened to --chapter={$chapter} (chapter-scoped derived rows require a whole-chapter pass).");

            return $chapter;
        }

        return $value;
    }

    /**
     * Does the current scope span at least one whole book?
     *
     * Book-level titles are attached to a book's FIRST chapter, and rebuilding them purges
     * that chapter's chapter-scoped titles. processAllBookTitles() is not driven by the verse
     * loop, so without this it ran for all 66 books on every import - including a run scoped
     * to a single chapter - deleting 61 chapter-type titles that nothing in that run would
     * re-insert (measured: 1,184 -> 1,123).
     */
    private function scopeCoversWholeBooks(): bool
    {
        return $this->scope === null || count(explode('.', $this->scope)) < 2;
    }

    /**
     * Does the current scope span at least one whole chapter?
     *
     * Paragraph structure is a chapter-wide property: the importer derives it by walking a
     * chapter's verses in order and closing the run at the next pilcrow. A single-verse scope
     * gives the state machine one verse and it emits a bogus one-verse paragraph with a NULL
     * end (measured: --verse=Gen.28.1 took paragraphs 4,159 -> 4,160). So paragraph work is
     * skipped unless the scope covers whole chapters.
     */
    private function scopeCoversWholeChapters(): bool
    {
        return $this->scope === null || count(explode('.', $this->scope)) < 3;
    }

    /**
     * Is this verse inside the requested scope?
     *
     * Compares on dot-separated segments so --book=Gen cannot also match Genesis-like ids
     * that merely share a string prefix.
     */
    private function inScope(string $verseOsisId): bool
    {
        if ($this->scope === null) {
            return true;
        }

        $want = explode('.', $this->scope);
        $have = array_slice(explode('.', $verseOsisId), 0, count($want));

        return $want === $have;
    }

    /**
     * Verse-scoped tables whose rows are derived from the OSIS source.
     *
     * Structure tables (bible_versions, books, chapters, verses, paragraphs) already handle
     * re-runs - they reuse, skip or update. These do not: they have no unique index and no
     * existence check, so every import run appended a fresh copy.
     */
    private const VERSE_PROJECTION_TABLES = [
        'word_elements',
        'translator_changes',
        'red_letter_text',
        'divine_names',
        'study_notes',
        'poetry_structure',
        'titles',
    ];

    /**
     * Chapter-scoped derived tables (book/chapter titles are keyed on chapter_id, not verse_id).
     */
    private const CHAPTER_PROJECTION_TABLES = [
        'titles',
    ];

    /** Chapter ids already purged during this run, so repeated titles in one chapter accumulate normally. */
    private array $purgedChapters = [];

    /**
     * Purge a chapter's chapter-scoped titles exactly once per import run.
     *
     * Guarded because a chapter legitimately has several chapter-scoped titles; purging on
     * every insert would leave only the last one.
     */
    private function purgeChapterProjectionOnce(int $chapterId): void
    {
        // Chapter-scoped titles are rebuilt by walking a whole chapter (and by the book-title
        // pass, which is not scope-filtered). Purging them during a single-verse run deleted
        // rows nothing in that run would re-insert - measured: chapter-type titles 1,184 ->
        // 1,123 after one --verse run. So this is a no-op for sub-chapter scopes.
        if (! $this->scopeCoversWholeChapters()) {
            return;
        }

        if (isset($this->purgedChapters[$chapterId])) {
            return;
        }
        $this->purgedChapters[$chapterId] = true;
        $this->purgeChapterProjection($chapterId);
    }

    /**
     * Drop the derived rows for one verse so they can be rebuilt from the source.
     */
    private function purgeVerseProjection(int $verseId): void
    {
        foreach (self::VERSE_PROJECTION_TABLES as $table) {
            DB::table($table)->where('verse_id', $verseId)->delete();
        }

        // A per-verse <title type="chapter"> is stored with verse_id = null and chapter_id set
        // (see importVerseTitle), so the verse_id delete above cannot reach it. Purge the
        // owning chapter's chapter-scoped titles instead - once per chapter per run, so the
        // contributions of sibling verses in the same chapter are not deleted in turn.
        $chapterId = DB::table('verses')->where('id', $verseId)->value('chapter_id');
        if ($chapterId) {
            $this->purgeChapterProjectionOnce((int) $chapterId);
        }
    }

    /**
     * Drop the derived rows attached directly to a chapter (not to one of its verses).
     */
    private function purgeChapterProjection(int $chapterId): void
    {
        // The book's main title also lives on chapter 1 with verse_id = NULL, but only the book
        // pass rebuilds it - a chapter-scoped run (every editor save) used to delete it for good.
        foreach (self::CHAPTER_PROJECTION_TABLES as $table) {
            DB::table($table)->where('chapter_id', $chapterId)->whereNull('verse_id')
                ->where(fn ($q) => $q->whereNull('title_type')->orWhere('title_type', '<>', 'main'))
                ->delete();
        }
    }

    private function processVerse($verseElement)
    {
        $osisId = $verseElement->getAttribute('osisID');
        $sId = $verseElement->getAttribute('sID');
        if (! $osisId || ! $sId) {
            return;
        }

        // Parse verse reference (e.g., "Gen.1.1")
        $parts = explode('.', $osisId);
        if (count($parts) < 3) {
            return;
        }

        if (! $this->inScope($osisId)) {
            return;
        }

        $bookOsis = $parts[0];
        $chapterNum = (int) $parts[1];
        $verseNum = (int) $parts[2];

        // Find chapter
        $chapterRef = $bookOsis.'.'.$chapterNum;
        $chapterId = $this->chapters[$chapterRef] ?? null;
        if (! $chapterId) {
            $this->skipped['no chapter in database'][] = $osisId;

            return;
        }

        // Handle paragraph tracking when switching chapters
        if ($this->scopeCoversWholeChapters() && $this->currentChapterId !== $chapterId) {
            // Save previous chapter's final paragraph if exists
            if ($this->currentChapterId && $this->currentParagraphStart !== null && ! empty($this->currentParagraphVerses)) {
                $this->saveParagraph($this->currentChapterId, $this->currentParagraphStart, $this->currentParagraphVerses);
            }

            // Paragraphs are rebuilt from the source for the whole chapter; drop the old ones so
            // a moved pilcrow cannot leave overlapping paragraphs behind.
            DB::table('paragraphs')->where('chapter_id', $chapterId)->delete();

            // Reset for new chapter
            $this->currentChapterId = $chapterId;
            $this->currentParagraphStart = null;
            $this->currentParagraphVerses = [];

            // Always start a paragraph at verse 1 of each chapter
            if ($verseNum === 1) {
                $this->currentParagraphStart = 1;
                $this->currentParagraphVerses = [1];
            }
        }

        // Check if this verse already exists in this chapter
        $existingVerse = DB::table('verses')
            ->where('chapter_id', $chapterId)
            ->where('verse_number', $verseNum)
            ->first();

        if ($existingVerse) {
            // Update the existing verse with ASV content instead of skipping
            $content = $this->extractVerseContent($verseElement);

            DB::table('verses')
                ->where('id', $existingVerse->id)
                ->update([
                    'text' => $content['text'],
                    'formatted_text' => $content['formatted'],
                    'original_xml' => $content['xml'],
                    'updated_at' => now(),
                ]);

            $verseId = $existingVerse->id;
        } else {
            // Extract verse content
            $content = $this->extractVerseContent($verseElement);

            // Insert new verse
            $verseId = DB::table('verses')->insertGetId([
                'chapter_id' => $chapterId,
                'verse_number' => $verseNum,
                'osis_id' => $osisId,
                'se_id' => $sId,
                'text' => $content['text'],
                'formatted_text' => $content['formatted'],
                'original_xml' => $content['xml'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->verseCount++;
        $this->seenVerses[$osisId] = true;

        if (! $content['ended']) {
            $this->skipped['no end marker before the chapter ended'][] = $osisId;
        }

        // Check for paragraph markers in verse content BEFORE adding to current paragraph
        $hasNewParagraphMarker = preg_match('/<milestone[^>]*type="x-p"[^>]*marker="¶"[^>]*>/', $content['xml']);

        if ($this->scopeCoversWholeChapters()) {
            if ($hasNewParagraphMarker) {
                $this->processParagraphMarkers($content['xml'], $verseNum);
            } else {
                // Add verse to current paragraph tracking only if it doesn't start a new paragraph
                if ($this->currentParagraphStart !== null && ! in_array($verseNum, $this->currentParagraphVerses)) {
                    $this->currentParagraphVerses[] = $verseNum;
                }
            }
        }

        // The database is a PROJECTION of the OSIS source, so re-projecting a verse must
        // replace its derived rows rather than add to them. These tables have no unique
        // index, so without this an import run duplicates every element: a second run took
        // word_elements 355,143 -> 710,286 and titles 1,530 -> 3,060 (measured).
        $this->purgeVerseProjection($verseId);

        // Check for titles that come before this verse
        $this->extractVerseTitles($verseElement, $verseId);

        // Extract poetry structure that contains this verse
        $this->extractPoetryStructure($verseElement, $verseId);

        // Process verse elements
        $this->processVerseElements($verseElement, $verseId, $content['xml']);
    }

    private function extractPoetryStructure($verseElement, $verseId)
    {
        // Check if this verse is contained within poetry structure (lg, l elements)
        $current = $verseElement->parentNode;
        $order = 1;

        // Get verse reference for book detection
        $verseRef = $verseElement->getAttribute('osisID');
        $parts = explode('.', $verseRef);
        $bookOsis = $parts[0] ?? '';

        // List of poetic books that should have poetry formatting
        $poeticBooks = ['Ps', 'Prov', 'Eccl', 'Song', 'Job', 'Lam'];
        $isPoetryBook = in_array($bookOsis, $poeticBooks);

        while ($current && $current->nodeType === XML_ELEMENT_NODE) {
            $nodeName = $current->localName ?: $current->nodeName;

            if ($nodeName === 'l') {
                // This verse is inside a poetry line
                $level = (int) ($current->getAttribute('level') ?: 1);
                $lineText = trim($current->textContent);

                DB::table('poetry_structure')->insert([
                    'verse_id' => $verseId,
                    'structure_type' => 'l',
                    'level' => $level,
                    'line_text' => $lineText,
                    'line_order' => $order,
                    'attributes' => json_encode($this->getElementAttributes($current)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->poetryLineCount++;
                $order++;
            } elseif ($nodeName === 'lg') {
                // This verse is inside a line group
                $groupText = trim($current->textContent);

                DB::table('poetry_structure')->insert([
                    'verse_id' => $verseId,
                    'structure_type' => 'lg',
                    'level' => 1,
                    'line_text' => $groupText,
                    'line_order' => $order,
                    'attributes' => json_encode($this->getElementAttributes($current)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->poetryLineCount++;
                $order++;
            } elseif ($nodeName === 'chapter') {
                // Stop when we reach the chapter level
                break;
            }

            $current = $current->parentNode;
        }

        // (No fallback row any more: for poetic books without <l>/<lg> markup it used to insert a
        // fabricated, empty "auto_generated" line per verse - 4,939 rows of noise. The KJV source
        // has no poetry markup, so there is nothing to project.)
    }

    private function extractVerseTitles($verseElement, $verseId)
    {
        // Look backwards from the verse to find any title elements
        $current = $verseElement->previousSibling;
        $order = 1;
        $foundTitles = [];

        while ($current) {
            if ($current->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $current->localName ?: $current->nodeName;

                if ($nodeName === 'title') {
                    // Collect titles but don't process yet (we want to process in forward order)
                    $foundTitles[] = $current;
                } elseif ($nodeName === 'verse') {
                    // Stop when we reach another verse (titles should be immediately before this verse)
                    break;
                } elseif ($nodeName === 'chapter') {
                    // Stop when we reach the chapter start
                    break;
                }
            }
            $current = $current->previousSibling;
        }

        // Process titles in forward order (reverse the array since we collected backwards)
        $foundTitles = array_reverse($foundTitles);
        foreach ($foundTitles as $titleElement) {
            $this->importTitle($titleElement, $verseId, $order);
            $order++;
        }
    }

    /**
     * Extract one milestone verse (<verse sID=X/> ... <verse eID=X/>) from the DOM.
     *
     * Verse markers are empty elements, so a verse is "everything between two markers" - and the
     * KJV nests those markers inside <q> containers (Christ's speech). Two consequences the old
     * sibling walk got wrong:
     *  - QUOTE OPEN AT THE START. If the start marker sits inside <q who="Jesus"> (a discourse that
     *    began in an earlier verse, e.g. John 3:10-21), the walk never sees the opening tag, so the
     *    verse lost its red letters (897 verses). Each such ancestor is re-opened here as a
     *    synthetic <q who="Jesus" marker=""> and closed where the real one closes.
     *  - QUOTE CLOSES MID-VERSE. When the walk ran out of siblings inside that container it simply
     *    stopped, truncating the verse (Matt 26:56 lost "Then all the disciples forsook him, and
     *    fled."). It now climbs out of the container and carries on to the end marker.
     * An inner Christ-quote inside an already-open one is unwrapped (children only), so fragments
     * never nest <q> in <q> - the renderer would emit an extra </span> and one verse would get two
     * red_letter_text rows.
     *
     * @return array{text: string, formatted: string, xml: string, ended: bool}
     */
    private function extractVerseContent($verseElement)
    {
        $osisId = $verseElement->getAttribute('osisID');

        $openQuotes = 0;
        for ($a = $verseElement->parentNode; $a && $a->nodeType === XML_ELEMENT_NODE; $a = $a->parentNode) {
            $name = $a->localName ?: $a->nodeName;
            if (in_array($name, ['chapter', 'div', 'osisText', 'osis'], true)) {
                break;
            }
            if ($name === 'q' && $a->getAttribute('who') === 'Jesus') {
                $openQuotes++;
            }
        }

        $content = str_repeat('<q who="Jesus" marker="">', $openQuotes);
        $current = $verseElement->nextSibling;
        $container = $verseElement->parentNode;
        $ended = false;

        while (true) {
            if ($current === null) {
                $containerName = $container ? ($container->localName ?: $container->nodeName) : null;
                if ($container === null || in_array($containerName, ['chapter', 'div', 'osisText', 'osis'], true)) {
                    break; // no end marker before the chapter ended: reported by the caller
                }
                if ($containerName === 'q' && $container->getAttribute('who') === 'Jesus' && $openQuotes > 0) {
                    $content .= '</q>';
                    $openQuotes--;
                }
                $current = $container->nextSibling;
                $container = $container->parentNode;

                continue;
            }

            if ($current->nodeType === XML_ELEMENT_NODE) {
                $name = $current->localName ?: $current->nodeName;

                if ($name === 'verse' && $current->getAttribute('eID') === $osisId) {
                    $ended = true;
                    break;
                }

                $isInnerQuote = $name === 'q' && $current->getAttribute('who') === 'Jesus' && $openQuotes > 0;

                if ($this->containsVerseEndMarker($current, $osisId)) {
                    $part = $this->extractContentUpToEndMarker($current, $osisId);
                    if ($isInnerQuote) {
                        $part = preg_replace('/^<q\b[^>]*>|<\/q>$/', '', $part);
                    }
                    $content .= $part;
                    $ended = true;
                    break;
                }

                $content .= $isInnerQuote ? $this->innerXml($current) : $this->doc->saveXML($current);
            } elseif ($current->nodeType === XML_TEXT_NODE) {
                $content .= $this->doc->saveXML($current); // escaped, unlike textContent
            }

            $current = $current->nextSibling;
        }

        $content .= str_repeat('</q>', $openQuotes);

        return [
            'text' => trim($this->extractPlainTextWithSpacing($content)),
            'formatted' => trim($this->formatToHTML($content)),
            'xml' => trim($content),
            'ended' => $ended,
        ];
    }

    private function innerXml(\DOMNode $node): string
    {
        $xml = '';
        foreach ($node->childNodes as $child) {
            $xml .= $this->doc->saveXML($child);
        }

        return $xml;
    }

    private function containsVerseEndMarker($element, $osisId)
    {
        // Relative XPath on the live document. The previous version deep-copied every sibling into
        // a temporary document per call - quadratic inside long discourses (Matt 5-7 is one <q>).
        return $this->xpath
            ->query('.//*[local-name()="verse"][@eID="'.$osisId.'"]', $element)
            ->length > 0;
    }

    private function extractContentUpToEndMarker($element, $osisId)
    {
        // Clone into a temporary document and cut the clone at the end marker
        $tempDoc = new DOMDocument;
        $tempElement = $tempDoc->importNode($element->cloneNode(true), true);
        $tempDoc->appendChild($tempElement);

        $endMarkers = (new DOMXPath($tempDoc))->query('.//*[local-name()="verse"][@eID="'.$osisId.'"]');

        if ($endMarkers->length > 0) {
            $endMarker = $endMarkers->item(0);

            // Remove everything after the end marker at every level up to the clone's root (the
            // marker may be nested), then the marker itself.
            for ($node = $endMarker; $node !== $tempElement; $node = $node->parentNode) {
                while ($node->nextSibling) {
                    $node->parentNode->removeChild($node->nextSibling);
                }
            }
            $endMarker->parentNode->removeChild($endMarker);
        }

        // A standalone document declares the OSIS default namespace on its root; the source
        // fragment never had it, and regexes such as /<q\s+who=/ then failed to match it.
        return preg_replace('/\s+xmlns(:\w+)?="[^"]*"/', '', $tempDoc->saveXML($tempElement));
    }

    /**
     * Scripture text only: study notes are apparatus, not text (they were appended to 5,844
     * verses and indexed for search). Spacing comes from the source itself (preserveWhiteSpace);
     * a self-closing <w/> is sometimes the only separator between two words, so it becomes a space.
     */
    private function extractPlainTextWithSpacing($content)
    {
        $text = $this->removeNotes($content);
        $text = preg_replace('/<w\b[^>]*\/>/', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        // ...but a <w/> right before punctuation or right after an opening bracket must not leave
        // a space there ("said , Who" Matt 5:31, "( Yea" Luke 2:35).
        $text = preg_replace('/\s+([,.;:!?)\]])/', '$1', $text);

        return preg_replace('/([(\[])\s+/', '$1', $text);
    }

    private function processVerseElements($verseElement, $verseId, $xmlContent)
    {
        // Create a document fragment for processing
        $fragment = $this->doc->createDocumentFragment();
        if (! empty($xmlContent)) {
            @$fragment->appendXML('<root>'.$xmlContent.'</root>');

            if ($fragment->firstChild) {
                $this->processElementsRecursively($fragment->firstChild, $verseId);
            }
        }
    }

    private function processElementsRecursively($element, $verseId, $order = 1)
    {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $this->processSpecificElement($child, $verseId, $order);

                // A <note> is apparatus: it becomes its own study_notes row, but the words inside
                // it (translator changes, divine names) are not verse text and must not become
                // verse-level rows. They were stored inconsistently (some notes' translator changes,
                // every note's divine names).
                if (($child->localName ?: $child->nodeName) === 'note') {
                    continue;
                }

                $this->processElementsRecursively($child, $verseId, $order + 1);
            }
        }
    }

    private function processSpecificElement($element, $verseId, $order)
    {
        $tagName = $element->nodeName;
        $text = $element->textContent;

        switch ($tagName) {
            case 'w':
                $this->importWordElement($element, $verseId, $order);
                break;

            case 'transChange':
                if ($element->getAttribute('type') === 'added') {
                    $this->importTranslatorChange($element, $verseId, $order);
                }
                break;

            case 'q':
                if ($element->getAttribute('who') === 'Jesus') {
                    $this->importRedLetterText($element, $verseId, $order);
                }
                break;

            case 'divineName':
                $this->importDivineName($element, $verseId);
                break;

            case 'title':
                $this->importTitle($element, $verseId, $order);
                break;

            case 'lg':
                $this->importLineGroup($element, $verseId, $order);
                break;

            case 'l':
                $this->importPoetryLine($element, $verseId, $order);
                break;

            case 'note':
                $this->importStudyNote($element, $verseId);
                break;
        }
    }

    private function importWordElement($element, $verseId, $order)
    {
        $lemma = $element->getAttribute('lemma');
        $morph = $element->getAttribute('morph');
        $text = $element->textContent;

        // Extract Strong's number
        $strongsNumber = null;
        if ($lemma && str_contains($lemma, 'strong:')) {
            preg_match('/strong:([HG]\d+)/', $lemma, $matches);
            $strongsNumber = $matches[1] ?? null;
        }

        DB::table('word_elements')->insert([
            'verse_id' => $verseId,
            'word_text' => $text,
            'strongs_number' => $strongsNumber,
            'morphology_code' => $morph,
            'lemma' => $lemma,
            'word_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->wordElementCount++;
    }

    private function importTranslatorChange($element, $verseId, $order)
    {
        $text = $element->textContent;

        DB::table('translator_changes')->insert([
            'verse_id' => $verseId,
            'change_type' => 'added',
            'text_content' => $text,
            'text_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->translatorChangeCount++;
    }

    private function importRedLetterText($element, $verseId, $order)
    {
        $text = $element->textContent;
        $speaker = $element->getAttribute('who') ?: 'Jesus';

        DB::table('red_letter_text')->insert([
            'verse_id' => $verseId,
            'speaker' => $speaker,
            'text_content' => $text,
            'text_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->redLetterCount++;
    }

    private function importDivineName($element, $verseId)
    {
        $text = $element->textContent;

        DB::table('divine_names')->insert([
            'verse_id' => $verseId,
            'displayed_text' => $text,
            // The source does not say which name underlies a given <divineName> ("LORD" vs "God"
            // vs "Lord's"); recording 'YHWH' for all 6,952 asserted a falsehood. Keep the type
            // attribute if the source has one, else leave it unknown.
            'original_name' => $element->getAttribute('type') ?: '',
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->divineNameCount++;
    }

    private function importTitle($element, $verseId, $order)
    {
        // Extract inner content and format to HTML (preserve formatting)
        $innerContent = '';
        foreach ($element->childNodes as $child) {
            $innerContent .= $this->doc->saveXML($child);
        }

        // Format the title content to HTML (like we do for verses)
        $formattedText = $this->formatToHTML($innerContent);

        $titleType = $element->getAttribute('type') ?: 'main';
        $canonical = $element->getAttribute('canonical') === 'true';

        // Validate title type and map to expected values
        $validTitleTypes = ['main', 'chapter', 'psalm', 'acrostic', 'sub', 'verse'];
        if (! in_array($titleType, $validTitleTypes)) {
            // Log unknown title type and default to 'main'
            $this->warn("Unknown title type '{$titleType}' found, defaulting to 'main'");
            $titleType = 'main';
        }

        // Determine chapter_id and verse_id based on title type
        $chapterId = null;
        $targetVerseId = null;

        if ($titleType === 'chapter') {
            // Chapter titles should be associated with the chapter, not the verse
            // Find the chapter this verse belongs to
            $verse = DB::table('verses')->where('id', $verseId)->first();
            if ($verse) {
                $chapterId = $verse->chapter_id;
                // For chapter titles, don't associate with a specific verse
                $targetVerseId = null;
            }
        } else {
            // All other titles (main, psalm, acrostic, sub) are associated with verses
            $targetVerseId = $verseId;
        }

        // Store title in database
        $titleData = [
            'verse_id' => $targetVerseId,
            'chapter_id' => $chapterId,
            'title_type' => $titleType,
            'title_text' => trim($formattedText),
            'canonical' => $canonical,
            'placement' => 'before',
            'title_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('titles')->insert($titleData);

        $this->titleCount++;

        // Log title type for debugging
        if ($this->titleCount % 100 === 0) {
            $this->line("   📝 Processed {$this->titleCount} titles...");
        }
    }

    private function importLineGroup($element, $verseId, $order)
    {
        // For line groups, we'll process their child elements individually
        // but also store the group info in poetry_structure
        $text = $element->textContent;

        DB::table('poetry_structure')->insert([
            'verse_id' => $verseId,
            'structure_type' => 'lg',
            'level' => 1,
            'line_text' => $text,
            'line_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->poetryLineCount++;
    }

    private function importPoetryLine($element, $verseId, $order)
    {
        $text = $element->textContent;
        $level = (int) ($element->getAttribute('level') ?: 1);

        DB::table('poetry_structure')->insert([
            'verse_id' => $verseId,
            'structure_type' => 'l',
            'level' => $level,
            'line_text' => $text,
            'line_order' => $order,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->poetryLineCount++;
    }

    private function importStudyNote($element, $verseId)
    {
        $type = $element->getAttribute('type') ?: 'study';
        $text = $element->textContent;

        DB::table('study_notes')->insert([
            'verse_id' => $verseId,
            'note_type' => $type,
            'note_text' => $text,
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function formatToHTML($content)
    {
        $html = $content;

        // A self-closing <w/> (Strong's number of an untranslated word) can be the only separator
        // between two words (Matt 12:31 "<w>but</w><w .../>the").
        $html = preg_replace('/<w\b[^>]*\/>/', ' ', $html);
        $html = preg_replace('/<milestone\b[^>]*\/?>/', '', $html);

        $html = $this->formatTitles($html);
        $html = $this->formatPoetry($html);
        $html = $this->formatCaesura($html);
        $html = $this->formatRedLetterText($html);
        $html = $this->formatTranslatorChanges($html);
        $html = $this->formatDivineNames($html);
        $html = $this->removeWordMarkup($html);
        $html = $this->addSpacing($html);
        $html = $this->removeNotes($html);
        // KJV sets inscriptions (Exod 28:36, Dan 5:25) in small capitals; <foreign>/<seg> only wrap text.
        $html = preg_replace('/<inscription\b[^>]*>/', '<span style="font-variant: small-caps;">', $html);
        $html = str_replace('</inscription>', '</span>', $html);
        $html = preg_replace('/<\/?(?:foreign|seg)\b[^>]*>/', '', $html);
        $html = $this->cleanupFormatting($html);

        return $html;
    }

    /**
     * Format title elements with appropriate styling
     */
    private function formatTitles($html)
    {
        $html = preg_replace('/<title\s+type="psalm"[^>]*>/', '<div class="psalm-title text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">', $html);
        $html = preg_replace('/<title\s+type="main"[^>]*>/', '<h2 class="main-title text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">', $html);
        $html = preg_replace('/<title\s+type="chapter"[^>]*>/', '<h3 class="chapter-title text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 text-center">', $html);
        $html = preg_replace('/<title\s+type="acrostic"[^>]*>/', '<div class="acrostic-title text-center text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2">', $html);
        $html = preg_replace('/<title\s+type="sub"[^>]*>/', '<h4 class="sub-title text-md font-medium text-gray-700 dark:text-gray-300 mb-2">', $html);
        $html = preg_replace('/<title[^>]*>/', '<div class="title text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">', $html);
        $html = str_replace('</title>', '</div>', $html);

        return $html;
    }

    /**
     * Format poetry elements with proper indentation
     */
    private function formatPoetry($html)
    {
        // Line groups
        $html = preg_replace('/<lg[^>]*>/', '<div class="line-group mb-2">', $html);
        $html = str_replace('</lg>', '</div>', $html);

        // Poetry lines with level-based indentation
        $html = preg_replace('/<l\s+level="1"[^>]*>/', '<div class="poetry-line indent-0 leading-relaxed">', $html);
        $html = preg_replace('/<l\s+level="2"[^>]*>/', '<div class="poetry-line indent-4 leading-relaxed ml-4">', $html);
        $html = preg_replace('/<l\s+level="3"[^>]*>/', '<div class="poetry-line indent-8 leading-relaxed ml-8">', $html);
        $html = preg_replace('/<l\s+level="4"[^>]*>/', '<div class="poetry-line indent-12 leading-relaxed ml-12">', $html);
        $html = preg_replace('/<l[^>]*>/', '<div class="poetry-line indent-0 leading-relaxed">', $html);
        $html = str_replace('</l>', '</div>', $html);

        return $html;
    }

    /**
     * Format caesura (poetry pause) elements
     */
    private function formatCaesura($html)
    {
        $html = str_replace('<caesura/>', '<span class="caesura text-gray-400 mx-2">‖</span>', $html);
        $html = str_replace('<caesura>', '<span class="caesura text-gray-400 mx-2">‖</span>', $html);

        return $html;
    }

    /**
     * Format red letter text (Jesus's words) and handle nested translator changes
     */
    private function formatRedLetterText($html)
    {
        // (The Matt 9:6 special case that lived here is gone: the extractor now closes and re-opens
        // quotes correctly, so every fragment carries balanced <q> pairs.)
        // Handle normal Jesus quotes (this will process any remaining <q who="Jesus"> tags)
        // \b...\bwho=: matches whatever attribute order/namespace declaration the tag carries
        // (279 verses stored a raw <q xmlns=... who="Jesus"> the old /<q\s+who=/ could not match).
        $html = preg_replace_callback('/<q\b[^>]*\bwho="Jesus"[^>]*>(.*?)<\/q>/s', function ($matches) {
            return $this->processJesusQuoteContent($matches[1]);
        }, $html);

        return $html;
    }

    /**
     * Process content within Jesus quotes, converting translator changes to red italics
     */
    private function processJesusQuoteContent($jesusContent)
    {
        // Replace transChange within Jesus quotes with red italics
        $jesusContent = str_replace('<transChange type="added">', '<em class="text-red-500 dark:text-red-300 font-normal italic opacity-80">', $jesusContent);
        $jesusContent = str_replace('</transChange>', '</em>', $jesusContent);

        return '<span class="text-red-600 dark:text-red-400 font-medium">'.$jesusContent.'</span>';
    }

    /**
     * Format remaining translator changes outside Jesus quotes (gray italics)
     */
    private function formatTranslatorChanges($html)
    {
        $html = str_replace('<transChange type="added">', '<em class="text-gray-600 dark:text-gray-400 font-normal italic">', $html);
        $html = str_replace('</transChange>', '</em>', $html);

        return $html;
    }

    /**
     * Format divine names with small caps styling
     */
    private function formatDivineNames($html)
    {
        $html = str_replace('<divineName>', '<span style="font-variant: small-caps; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; font-size: 0.95em;">', $html);
        $html = str_replace('</divineName>', '</span>', $html);

        return $html;
    }

    /**
     * Remove word markup while preserving spacing
     */
    private function removeWordMarkup($html)
    {
        $html = preg_replace('/<w[^>]*>/', '', $html);
        // Add space after </w> only if followed by actual text content (not punctuation or whitespace).
        // Dashes count as punctuation: Exod 32:32 "their sin</w>—;" read "sin —;" in the stored HTML.
        // /u so the multi-byte dashes are single characters, not bytes (which would also have
        // matched the lead bytes of curly quotes).
        $html = preg_replace('/<\/w>(?=[^\s.,:;!?\'")\]\-\x{2013}\x{2014}])/u', '</w> ', $html);
        $html = str_replace('</w>', '', $html); // Clean up remaining </w> tags

        return $html;
    }

    /**
     * Add proper spacing around elements
     */
    private function addSpacing($html)
    {
        // Add space after </em> only if followed by actual text content
        $html = preg_replace('/<\/em>(?=[^\s.,:;!?\'")\]\-])/', '</em> ', $html);

        // Add space after red letter text </span> only if followed by actual text content
        $html = preg_replace('/<\/span>(?=[^\s.,:;!?\'")\]\-])/', '</span> ', $html);

        return $html;
    }

    /**
     * Remove study notes and other non-display elements
     */
    private function removeNotes($html)
    {
        // Remove notes for basic display
        $html = preg_replace('/<note[^>]*>.*?<\/note>/s', '', $html);

        return $html;
    }

    /**
     * Final cleanup of formatting
     */
    private function cleanupFormatting($html)
    {
        // Remove pilcrow symbols - they should not be displayed to users
        $html = str_replace('¶', '', $html);

        // Clean up multiple spaces
        $html = preg_replace('/\s+/', ' ', $html);
        // No space before punctuation or after an opening bracket, even across closing tags
        // (a <w/> separator becomes a space; see formatToHTML).
        $html = preg_replace('/\s+((?:<\/(?:span|em)>)*)([,.;:!?)\]])/', '$1$2', $html);

        return preg_replace('/([(\[])\s+/', '$1', $html);
    }

    private function getElementAttributes($element)
    {
        $attributes = [];
        if ($element->attributes) {
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }

        return $attributes;
    }

    private function updateFTSTables()
    {
        $this->info('🔍 Updating FTS search tables...');

        // verses_fts is the ONLY search backend (DatabaseBibleReader::searchVerses queries it
        // exclusively), so a swallowed rebuild failure silently serves the previous import's
        // index. This used to warn and then print a success tick two lines later, exiting 0 -
        // so an operator or CI saw a fully green import over a stale index.
        DB::statement("INSERT INTO verses_fts(verses_fts) VALUES('rebuild')");

        $this->info('✅ FTS tables updated');
    }

    private function getBookGroupId($osisId)
    {
        $otBooks = ['Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth', '1Sam', '2Sam', '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh', 'Esth', 'Job', 'Ps', 'Prov', 'Eccl', 'Song', 'Isa', 'Jer', 'Lam', 'Ezek', 'Dan', 'Hos', 'Joel', 'Amos', 'Obad', 'Jonah', 'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal'];

        if (in_array($osisId, $otBooks)) {
            return DB::table('book_groups')->where('name', 'Old Testament')->value('id');
        }

        return DB::table('book_groups')->where('name', 'New Testament')->value('id');

    }

    private function getBookNumber($osisId)
    {
        $bookNumbers = [
            'Gen' => 1, 'Exod' => 2, 'Lev' => 3, 'Num' => 4, 'Deut' => 5,
            'Josh' => 6, 'Judg' => 7, 'Ruth' => 8, '1Sam' => 9, '2Sam' => 10,
            '1Kgs' => 11, '2Kgs' => 12, '1Chr' => 13, '2Chr' => 14, 'Ezra' => 15,
            'Neh' => 16, 'Esth' => 17, 'Job' => 18, 'Ps' => 19, 'Prov' => 20,
            'Eccl' => 21, 'Song' => 22, 'Isa' => 23, 'Jer' => 24, 'Lam' => 25,
            'Ezek' => 26, 'Dan' => 27, 'Hos' => 28, 'Joel' => 29, 'Amos' => 30,
            'Obad' => 31, 'Jonah' => 32, 'Mic' => 33, 'Nah' => 34, 'Hab' => 35,
            'Zeph' => 36, 'Hag' => 37, 'Zech' => 38, 'Mal' => 39, 'Matt' => 40,
            'Mark' => 41, 'Luke' => 42, 'John' => 43, 'Acts' => 44, 'Rom' => 45,
            '1Cor' => 46, '2Cor' => 47, 'Gal' => 48, 'Eph' => 49, 'Phil' => 50,
            'Col' => 51, '1Thess' => 52, '2Thess' => 53, '1Tim' => 54, '2Tim' => 55,
            'Titus' => 56, 'Phlm' => 57, 'Heb' => 58, 'Jas' => 59, '1Pet' => 60,
            '2Pet' => 61, '1John' => 62, '2John' => 63, '3John' => 64, 'Jude' => 65,
            'Rev' => 66,
        ];

        return $bookNumbers[$osisId] ?? 999;
    }

    private function getBookName($osisId)
    {
        $bookNames = [
            'Gen' => 'Genesis', 'Exod' => 'Exodus', 'Lev' => 'Leviticus', 'Num' => 'Numbers', 'Deut' => 'Deuteronomy',
            'Josh' => 'Joshua', 'Judg' => 'Judges', 'Ruth' => 'Ruth', '1Sam' => '1 Samuel', '2Sam' => '2 Samuel',
            '1Kgs' => '1 Kings', '2Kgs' => '2 Kings', '1Chr' => '1 Chronicles', '2Chr' => '2 Chronicles', 'Ezra' => 'Ezra',
            'Neh' => 'Nehemiah', 'Esth' => 'Esther', 'Job' => 'Job', 'Ps' => 'Psalms', 'Prov' => 'Proverbs',
            'Eccl' => 'Ecclesiastes', 'Song' => 'Song of Solomon', 'Isa' => 'Isaiah', 'Jer' => 'Jeremiah', 'Lam' => 'Lamentations',
            'Ezek' => 'Ezekiel', 'Dan' => 'Daniel', 'Hos' => 'Hosea', 'Joel' => 'Joel', 'Amos' => 'Amos',
            'Obad' => 'Obadiah', 'Jonah' => 'Jonah', 'Mic' => 'Micah', 'Nah' => 'Nahum', 'Hab' => 'Habakkuk',
            'Zeph' => 'Zephaniah', 'Hag' => 'Haggai', 'Zech' => 'Zechariah', 'Mal' => 'Malachi', 'Matt' => 'Matthew',
            'Mark' => 'Mark', 'Luke' => 'Luke', 'John' => 'John', 'Acts' => 'Acts', 'Rom' => 'Romans',
            '1Cor' => '1 Corinthians', '2Cor' => '2 Corinthians', 'Gal' => 'Galatians', 'Eph' => 'Ephesians', 'Phil' => 'Philippians',
            'Col' => 'Colossians', '1Thess' => '1 Thessalonians', '2Thess' => '2 Thessalonians', '1Tim' => '1 Timothy', '2Tim' => '2 Timothy',
            'Titus' => 'Titus', 'Phlm' => 'Philemon', 'Heb' => 'Hebrews', 'Jas' => 'James', '1Pet' => '1 Peter',
            '2Pet' => '2 Peter', '1John' => '1 John', '2John' => '2 John', '3John' => '3 John', 'Jude' => 'Jude',
            'Rev' => 'Revelation',
        ];

        return $bookNames[$osisId] ?? $osisId;
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info('🎉 Import completed successfully!');
        $this->newLine();

        $this->line('📊 <info>Import Summary:</info>');
        $this->line('   📚 Books: '.count($this->books));
        $this->line('   📖 Chapters: '.count($this->chapters));
        $this->line('   📝 Verses: '.$this->verseCount);
        $this->line('   📄 Paragraphs: '.$this->paragraphCount);
        $this->line('   🔤 Word Elements: '.$this->wordElementCount);
        $this->line('   ✒️  Translator Changes: '.$this->translatorChangeCount);
        $this->line('   👑 Divine Names: '.$this->divineNameCount);
        $this->line('   🔴 Red Letter Text: '.$this->redLetterCount);
        $this->line('   📚 Titles: '.$this->titleCount);

        // Show title type breakdown
        $titleBreakdown = DB::table('titles')
            ->select('title_type', DB::raw('count(*) as count'))
            ->groupBy('title_type')
            ->orderBy('count', 'desc')
            ->get();

        if ($titleBreakdown->count() > 0) {
            $this->line('       Title breakdown:');
            foreach ($titleBreakdown as $titleType) {
                $this->line("       - {$titleType->title_type}: {$titleType->count}");
            }
        }

        $this->line('   📝 Poetry Lines: '.$this->poetryLineCount);

        $this->newLine();
        $this->line('✅ Database is ready for biblical scholarship!');
    }

    /**
     * Process all book titles after chapters have been imported
     */
    private function resetRunState(): void
    {
        $this->versionId = null;
        $this->books = [];
        $this->chapters = [];
        $this->verseCount = $this->wordElementCount = $this->translatorChangeCount = $this->divineNameCount = 0;
        $this->redLetterCount = $this->titleCount = $this->poetryLineCount = $this->paragraphCount = 0;
        $this->xpath = null;
        $this->doc = null; // frees the previous run's DOM (~400MB for the full KJV)
        $this->currentParagraphStart = null;
        $this->currentParagraphVerses = [];
        $this->currentChapterId = null;
        $this->purgedChapters = [];
        $this->scope = null;
        $this->skipped = [];
        $this->seenVerses = [];
    }

    /**
     * Delete verses (and their derived rows) that are in scope but no longer in the source.
     *
     * Without this a verse removed from the XML stayed in the database for ever. Guarded: a run
     * that saw no verses in scope (wrong file, wrong scope) touches nothing, and removing more
     * than 50 verses needs --allow-deletions.
     */
    private function removeVersesMissingFromSource(): void
    {
        if ($this->seenVerses === []) {
            throw new Exception('No verses in scope were found in the source file; refusing to touch the database.');
        }

        $query = DB::table('verses')
            ->join('chapters', 'chapters.id', '=', 'verses.chapter_id')
            ->where('chapters.version_id', $this->versionId);
        if ($this->scope !== null) {
            $query->where('verses.osis_id', 'like', $this->scope.'.%');
        }

        $stale = $query->pluck('verses.id', 'verses.osis_id')
            ->reject(fn ($id, $osisId) => isset($this->seenVerses[$osisId]));

        if ($stale->isEmpty()) {
            return;
        }

        if ($stale->count() > 50 && ! $this->option('allow-deletions')) {
            throw new Exception("{$stale->count()} verses in scope are not in the source (e.g. {$stale->keys()->take(5)->implode(', ')}); re-run with --allow-deletions if that is intended.");
        }

        DB::transaction(function () use ($stale) {
            foreach (array_chunk($stale->values()->all(), 500) as $ids) {
                foreach (self::VERSE_PROJECTION_TABLES as $table) {
                    DB::table($table)->whereIn('verse_id', $ids)->delete();
                }
                DB::table('paragraphs')->whereIn('start_verse_id', $ids)->delete();
                DB::table('paragraphs')->whereIn('end_verse_id', $ids)->delete();
                DB::table('verses')->whereIn('id', $ids)->delete();
            }
        });

        $this->warn("🗑️  Removed {$stale->count()} verse(s) no longer in the source: ".$stale->keys()->take(10)->implode(', '));
    }

    private function processAllBookTitles()
    {
        if (! $this->scopeCoversWholeBooks()) {
            $this->line('📑 Skipping book titles (scope is narrower than a book).');

            return;
        }

        $this->info('📑 Processing book titles...');

        // Get all book divisions with namespace
        $bookDivs = $this->xpath->query('//osis:div[@type="book"]');

        foreach ($bookDivs as $bookDiv) {
            $osisId = $bookDiv->getAttribute('osisID');
            if (! $osisId) {
                continue;
            }

            $bookId = $this->books[$osisId] ?? null;
            if (! $bookId) {
                continue;
            }

            // Respect a --book scope so an unrelated book's titles are left alone.
            if ($this->scope !== null && $osisId !== $this->scope) {
                continue;
            }

            // Process any main titles that appear in this book div
            $this->processBookTitles($bookDiv, $bookId);
        }
    }

    /**
     * Process main book titles that appear at the book level
     *
     * @param  mixed  $bookDiv
     * @param  mixed  $bookId
     */
    private function processBookTitles($bookDiv, $bookId)
    {
        // Look for title elements that are direct children of the book div
        foreach ($bookDiv->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $nodeName = $child->localName ?: $child->nodeName;

                if ($nodeName === 'title') {
                    $titleType = $child->getAttribute('type') ?: 'main';

                    // Only process main titles at book level
                    if ($titleType === 'main') {
                        $this->importBookTitle($child, $bookId);
                    }
                }
            }
        }
    }

    /**
     * Import a title specifically associated with a book
     *
     * @param  mixed  $element
     * @param  mixed  $bookId
     */
    private function importBookTitle($element, $bookId)
    {
        // Extract inner content and format to HTML (preserve formatting)
        $innerContent = '';
        foreach ($element->childNodes as $child) {
            $innerContent .= $this->doc->saveXML($child);
        }

        // Format the title content to HTML
        $formattedText = $this->formatToHTML($innerContent);

        $titleType = $element->getAttribute('type') ?: 'main';
        $canonical = $element->getAttribute('canonical') === 'true';

        // For book titles, we'll associate them with the first chapter of the book
        $firstChapter = DB::table('chapters')
            ->where('book_id', $bookId)
            ->where('version_id', $this->versionId)
            ->orderBy('chapter_number')
            ->first();

        $chapterId = $firstChapter ? $firstChapter->id : null;

        // Book titles also carry verse_id = null; purge this chapter's chapter-scoped titles
        // once so a re-run replaces them instead of appending.
        if ($chapterId) {
            $this->purgeChapterProjectionOnce($chapterId);
            // The chapter purge deliberately keeps 'main' titles (see purgeChapterProjection), so
            // replace this book's own main title here.
            DB::table('titles')->where('chapter_id', $chapterId)->whereNull('verse_id')->where('title_type', $titleType)->delete();
        }

        // Store title in database
        $titleData = [
            'verse_id' => null, // Book titles are not associated with specific verses
            'chapter_id' => $chapterId, // Associate with first chapter if available
            'title_type' => $titleType,
            'title_text' => trim($formattedText),
            'canonical' => $canonical,
            'placement' => 'before',
            'title_order' => 1, // Book titles come first
            'attributes' => json_encode($this->getElementAttributes($element)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('titles')->insert($titleData);
        $this->titleCount++;
    }

    /**
     * Process paragraph markers found in verse content
     *
     * @param  mixed  $xmlContent
     * @param  mixed  $verseNum
     */
    private function processParagraphMarkers($xmlContent, $verseNum)
    {
        // Check for OSIS paragraph markers
        if (preg_match('/<milestone[^>]*type="x-p"[^>]*marker="¶"[^>]*>/', $xmlContent)) {
            // Found a paragraph marker - save current paragraph and start new one
            if ($this->currentParagraphStart !== null && ! empty($this->currentParagraphVerses)) {
                // End the current paragraph at the verse BEFORE this new paragraph marker
                $previousParagraphVerses = array_filter($this->currentParagraphVerses, function ($v) use ($verseNum) {
                    return $v < $verseNum;
                });

                if (! empty($previousParagraphVerses)) {
                    $this->saveParagraph($this->currentChapterId, $this->currentParagraphStart, $previousParagraphVerses);
                }
            }

            // Start new paragraph at this verse
            $this->currentParagraphStart = $verseNum;
            $this->currentParagraphVerses = [$verseNum];
        }
    }

    /**
     * Save a paragraph to the database
     *
     * @param  mixed  $chapterId
     * @param  mixed  $startVerse
     * @param  mixed  $verses
     */
    private function saveParagraph($chapterId, $startVerse, $verses)
    {
        $endVerse = max($verses);

        // Get verse IDs
        $startVerseId = DB::table('verses')
            ->where('chapter_id', $chapterId)
            ->where('verse_number', $startVerse)
            ->value('id');

        $endVerseId = null;
        if ($endVerse !== $startVerse) {
            $endVerseId = DB::table('verses')
                ->where('chapter_id', $chapterId)
                ->where('verse_number', $endVerse)
                ->value('id');
        }

        if ($startVerseId) {
            // Get combined text content
            $textContent = DB::table('verses')
                ->where('chapter_id', $chapterId)
                ->whereIn('verse_number', $verses)
                ->orderBy('verse_number')
                ->pluck('formatted_text')
                ->implode(' ');

            // Check if this paragraph already exists
            $existingParagraph = DB::table('paragraphs')
                ->where('chapter_id', $chapterId)
                ->where('start_verse_id', $startVerseId)
                ->where('end_verse_id', $endVerseId)
                ->first();

            if (! $existingParagraph) {
                DB::table('paragraphs')->insert([
                    'chapter_id' => $chapterId,
                    'start_verse_id' => $startVerseId,
                    'end_verse_id' => $endVerseId,
                    'paragraph_type' => 'normal',
                    'text_content' => $textContent,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->paragraphCount++;
            }
        }
    }

    /**
     * Finalize any remaining paragraphs at the end of import
     */
    private function finalizeParagraphs()
    {
        // Save any remaining paragraphs
        if ($this->currentParagraphStart !== null && ! empty($this->currentParagraphVerses)) {
            $this->saveParagraph($this->currentChapterId, $this->currentParagraphStart, $this->currentParagraphVerses);
        }
    }
}
