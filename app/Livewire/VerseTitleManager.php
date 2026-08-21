<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\Verse;
use App\Models\Chapter;
use Livewire\Component;
use App\Services\BibleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class VerseTitleManager extends Component
{
    public $selectedBook = '';
    public $selectedChapter = '';
    public $selectedVerse = '';
    public $verseTitle = '';
    public $message = '';
    public $messageType = 'success';

    public $books = [];
    public $chapters = [];
    public $verses = [];
    public $currentTranslation = null;
    public $availableTranslations = [];

    protected $rules = [
        'selectedBook' => 'required',
        'selectedChapter' => 'required',
        'selectedVerse' => 'required',
        'verseTitle' => 'required|string|max:255',
    ];

    protected $messages = [
        'selectedBook.required' => 'Please select a book.',
        'selectedChapter.required' => 'Please select a chapter.',
        'selectedVerse.required' => 'Please select a verse.',
        'verseTitle.required' => 'Please enter a title.',
        'verseTitle.max' => 'Title cannot be longer than 255 characters.',
    ];

    public function mount()
    {
        $this->loadTranslations();
        $this->loadBooks();
    }

    public function loadTranslations()
    {
        $bibleService = app(BibleService::class);
        $this->currentTranslation = $bibleService->getCurrentTranslation();
        $this->availableTranslations = $bibleService->getAvailableTranslations();
    }

    public function loadBooks()
    {
        $this->books = Book::ordered()->get();
    }

    public function updatedSelectedBook($value)
    {
        $this->selectedChapter = '';
        $this->selectedVerse = '';
        $this->verseTitle = '';
        $this->chapters = [];
        $this->verses = [];
        $this->clearMessage();

        if ($value) {
            $this->loadChapters($value);
        }
    }

    public function updatedSelectedChapter($value)
    {
        $this->selectedVerse = '';
        $this->verseTitle = '';
        $this->verses = [];
        $this->clearMessage();

        if ($value && $this->selectedBook) {
            $this->loadVerses($this->selectedBook, $value);
        }
    }

    public function updatedSelectedVerse($value)
    {
        $this->verseTitle = '';
        $this->clearMessage();

        if ($value && $this->selectedBook && $this->selectedChapter) {
            $this->loadExistingTitle();
        }
    }

    public function loadChapters($bookOsisId)
    {
        $book = Book::where('osis_id', $bookOsisId)->first();
        if ($book) {
            $versionId = $this->getVersionId();

            $this->chapters = Chapter::where('book_id', $book->id)
                ->where('version_id', $versionId)
                ->orderBy('chapter_number')
                ->get();
        }
    }

    public function loadVerses($bookOsisId, $chapterNumber)
    {
        $book = Book::where('osis_id', $bookOsisId)->first();
        if ($book) {
            $versionId = $this->getVersionId();

            $chapter = Chapter::where('book_id', $book->id)
                ->where('version_id', $versionId)
                ->where('chapter_number', $chapterNumber)
                ->first();

            if ($chapter) {
                $this->verses = Verse::where('chapter_id', $chapter->id)
                    ->orderBy('verse_number')
                    ->get();
            }
        }
    }

    public function loadExistingTitle()
    {
        try {
            $bibleService = app(BibleService::class);
            $xmlPath = $bibleService->getXmlPath($this->selectedBook);

            if (! file_exists($xmlPath)) {
                Log::warning("XML file not found: {$xmlPath}");
                return;
            }

            $xmlContent = file_get_contents($xmlPath);
            $verseOsisId = $this->selectedBook . '.' . $this->selectedChapter . '.' . $this->selectedVerse;

            // Look for existing title before verse with sID format
            $versePattern = '/<title type="verse" canonical="(?:true|false)">([^<]*)<\/title>\s*<verse osisID="' .
                           preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*"/';

            if (preg_match($versePattern, $xmlContent, $matches)) {
                $this->verseTitle = $matches[1];
            }
        } catch (\Exception $e) {
            Log::error('Error loading existing title: ' . $e->getMessage());
        }
    }

    /**
     * Drop the reader-facing cache for a chapter and its neighbours.
     *
     * Neighbours matter because ChapterController pre-caches chapters +/- 1 for the page-flip
     * preview, so a stale copy of the edited chapter can survive inside the adjacent chapters'
     * payloads too.
     */
    private function forgetChapterCache(string $chapterOsisRef): void
    {
        [$book, $chapter] = array_pad(explode('.', $chapterOsisRef), 2, null);

        if ($book === null || $chapter === null) {
            return;
        }

        foreach ([(int) $chapter - 1, (int) $chapter, (int) $chapter + 1] as $n) {
            if ($n < 1) {
                continue;
            }

            Cache::forget("chapter_verses_{$book}_{$n}");
            Cache::forget("chapter_data_{$book}_{$n}");
        }
    }

    /**
     * Read-modify-write the OSIS source under an exclusive lock, replacing it atomically.
     *
     * The XML is the source of truth, which raises the bar on this write considerably. The
     * previous shape - file_get_contents, preg_replace, file_put_contents - had three defects:
     *
     *  - LOST UPDATES. Two concurrent saves both read the old content and the second clobbered
     *    the first. The transform therefore runs INSIDE the lock, not around it.
     *  - TORN FILE. file_put_contents truncates in place, so an interrupted write left a
     *    partial 27MB source. We write a sibling temp file and rename() it over the original;
     *    rename is atomic within a filesystem, so a reader sees either the old file or the new
     *    one, never a half-written one.
     *  - NO VALIDATION. A bad regex could persist unparseable XML. The temp file is parsed
     *    before it is promoted.
     *
     * The lock lives in a sidecar .lock file rather than on the source itself: rename() replaces
     * the inode, so a writer waiting on a lock held against the ORIGINAL inode would wake up
     * holding a lock on an unlinked file and read stale content. The sidecar is never replaced.
     *
     * Serialising writers also bounds memory. A single save peaks around 55MB on the 27MB file
     * (string + regex copy) against a 128MB FPM limit, so two concurrent saves could OOM the
     * worker. Under the lock they cannot overlap.
     *
     * @param  callable(string): ?string  $transform  current contents -> new contents, or null to abort
     * @return string one of: ok, busy, lock_failed, read_failed, transform_failed, unchanged,
     *                invalid_xml, write_failed
     */
    private function updateOsisSourceAtomically(string $path, callable $transform): string
    {
        $lockPath = $path.'.lock';
        $lock = @fopen($lockPath, 'c');

        // The lock is shared between whoever writes: artisan (often root) and PHP-FPM
        // (www-data). Whoever creates it first would otherwise own it with a umask that
        // locks the other out - which is exactly how a root-created lock blocked www-data.
        if ($lock !== false) {
            @chmod($lockPath, 0664);
        }

        if ($lock === false) {
            Log::error('Cannot open OSIS lock file', ['lock' => $lockPath]);

            return 'lock_failed';
        }

        // Bounded wait rather than blocking forever: a stuck writer must not pin an FPM worker.
        $deadline = microtime(true) + 10.0;
        $acquired = false;

        while (microtime(true) < $deadline) {
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                $acquired = true;
                break;
            }
            usleep(100_000);
        }

        if (! $acquired) {
            fclose($lock);

            return 'busy';
        }

        $tmp = null;

        try {
            $current = @file_get_contents($path);

            if ($current === false) {
                return 'read_failed';
            }

            $new = $transform($current);

            if ($new === null) {
                return 'transform_failed';
            }

            if ($new === $current) {
                return 'unchanged';
            }

            // Temp file must be a sibling so rename() stays within the filesystem.
            $tmp = $path.'.tmp.'.getmypid();

            // Capture the original's mode and ownership BEFORE replacing it. An atomic
            // rename() gives the new file the CREATING PROCESS's uid/gid and umask, not the
            // original's - so a write by root silently reset a root:www-data 664 source back
            // to root:root 644 and locked www-data out again. A replace-in-place must preserve
            // what it replaces.
            $original = @stat($path);

            if (@file_put_contents($tmp, $new) === false) {
                return 'write_failed';
            }

            if ($original !== false) {
                @chmod($tmp, $original['mode'] & 0777);

                // chown/chgrp only succeed as root; harmless no-ops otherwise.
                @chgrp($tmp, $original['gid']);
                @chown($tmp, $original['uid']);
            }

            if (! $this->fileParsesAsXml($tmp)) {
                return 'invalid_xml';
            }

            // Durability before promotion, so a crash cannot leave a renamed-but-empty file.
            if ($fh = @fopen($tmp, 'r+')) {
                fflush($fh);
                if (function_exists('fsync')) {
                    @fsync($fh);
                }
                fclose($fh);
            }

            if (! @rename($tmp, $path)) {
                return 'write_failed';
            }

            $tmp = null;

            return 'ok';
        } finally {
            if ($tmp !== null && file_exists($tmp)) {
                @unlink($tmp);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Does this file parse as XML?
     *
     * Streamed with XMLReader on purpose. DOMDocument->load() on the 27MB source takes RSS from
     * ~29MB to ~422MB, and because libxml allocates through C malloc, memory_limit does not stop
     * it - the FPM worker simply grows. Validation must not be more dangerous than the bug it
     * guards against.
     */
    private function fileParsesAsXml(string $path): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $reader = new \XMLReader();

        if (! @$reader->open($path)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return false;
        }

        while (@$reader->read()) {
            // Streaming to the end is the parse check; nothing to collect.
        }

        $errors = libxml_get_errors();
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($errors as $error) {
            if ($error->level === LIBXML_ERR_FATAL) {
                Log::error('OSIS source failed validation before promotion', [
                    'line' => $error->line,
                    'message' => trim($error->message),
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Map an updateOsisSourceAtomically() status onto a message for the editor.
     */
    private function osisWriteFailureMessage(string $status): string
    {
        return match ($status) {
            'busy' => 'Another save is in progress. Please try again in a moment.',
            'lock_failed', 'write_failed' => 'Could not write the OSIS source file. Check that the web server user can write assets/ (it currently cannot).',
            'read_failed' => 'Could not read the OSIS source file.',
            'invalid_xml' => 'The edit would have produced invalid XML, so nothing was written.',
            'unchanged' => 'Verse not found in XML file.',
            'transform_failed' => 'Error processing XML content.',
            default => 'Failed to save XML file.',
        };
    }

    /**
     * The OSIS file backing the current translation - the source of truth we just wrote to.
     */
    private function getXmlPathForCurrentTranslation(): string
    {
        $key = $this->currentTranslation['key'] ?? 'kjv';

        return base_path("assets/{$key}.osis.xml");
    }

    /**
     * Re-project one verse from the XML into the database.
     *
     * assets/*.osis.xml is the source of truth and the database is a derived read model, so a
     * write to the XML is not visible to readers until it is projected. Before this, an editor
     * saw "Title saved successfully!" while the reader kept serving the old text until someone
     * remembered to re-run osis:import over the whole file.
     *
     * osis:import --verse is idempotent and sub-second, so this runs inline. Returns true when
     * the projection succeeded, so the caller can tell the editor the truth either way.
     */
    private function reprojectVerse(string $verseOsisId): bool
    {
        try {
            // Re-project the whole CHAPTER, not just the verse. Chapter-scoped derived rows
            // (chapter titles, paragraph structure) can only be rebuilt correctly by walking a
            // full chapter, so a single-verse scope deliberately skips them. Chapter scope is
            // still fast - 22 verses for Gen 28 - and it is provably complete.
            $chapterOsisRef = $this->selectedBook.'.'.$this->selectedChapter;

            $exit = Artisan::call('osis:import', [
                'file' => $this->getXmlPathForCurrentTranslation(),
                '--chapter' => $chapterOsisRef,
            ]);

            if ($exit !== 0) {
                Log::error('Verse re-projection failed', [
                    'verse' => $verseOsisId,
                    'exit_code' => $exit,
                    'output' => Artisan::output(),
                ]);

                return false;
            }

            // Publishing is not finished until the reader's cache is dropped. Chapter payloads
            // are cached for 24h and nothing else in the app ever invalidates them, so without
            // this an edit was correct in the source AND the database and still invisible to
            // readers for up to a day - which is the same broken promise, one layer further on.
            $this->forgetChapterCache($chapterOsisRef);

            return true;
        } catch (\Throwable $e) {
            Log::error('Verse re-projection threw', ['verse' => $verseOsisId, 'exception' => $e]);

            return false;
        }
    }

    public function saveTitle()
    {
        $this->validate();

        try {
            $bibleService = app(BibleService::class);
            $xmlPath = $bibleService->getXmlPath($this->selectedBook);

            if (! file_exists($xmlPath)) {
                $this->setMessage('XML file not found for this book.', 'error');
                return;
            }

            $verseOsisId = $this->selectedBook . '.' . $this->selectedChapter . '.' . $this->selectedVerse;

            // No file-copy backup: assets/*.osis.xml is the source of truth and is versioned in
            // git. The previous copy-per-save wrote a full ~27MB snapshot on every save.
            $status = $this->updateOsisSourceAtomically($xmlPath, function (string $xmlContent) use ($verseOsisId): ?string {
                // Remove existing title if present (handle sID format)
                $existingTitlePattern = '/<title type="verse" canonical="(?:true|false)">[^<]*<\/title>\s*(?=<verse osisID="' .
                                       preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*")/';
                $xmlContent = preg_replace($existingTitlePattern, '', $xmlContent);

                if ($xmlContent === null) {
                    return null;
                }

                // Add new title before the verse (handle sID format)
                $versePattern = '/(<verse osisID="' . preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*"[^>]*>)/';
                // OSIS canonical="true" asserts the text IS scripture. A Psalm superscription
                // qualifies; an editorial section heading such as "The Creation" does not.
                $replacement = '<title type="verse" canonical="false">' . htmlspecialchars($this->verseTitle) . '</title>$1';

                return preg_replace($versePattern, $replacement, $xmlContent);
            });

            if ($status !== 'ok') {
                $this->setMessage($this->osisWriteFailureMessage($status), 'error');

                return;
            }

            // The XML is the source of truth; the reader reads the projection. Publish it.
            if ($this->reprojectVerse($verseOsisId)) {
                $this->setMessage('Title saved and published.', 'success');
            } else {
                $this->setMessage('Saved to the OSIS source, but publishing to the database failed. Readers will not see it until osis:import runs.', 'error');
            }
            Log::info("Verse title saved for {$verseOsisId}: {$this->verseTitle}");

        } catch (\Exception $e) {
            Log::error('Error saving verse title: ' . $e->getMessage());
            $this->setMessage('An error occurred while saving the title.', 'error');
        }
    }

    public function removeTitle()
    {
        if (! $this->selectedBook || ! $this->selectedChapter || ! $this->selectedVerse) {
            $this->setMessage('Please select a verse first.', 'error');
            return;
        }

        try {
            $bibleService = app(BibleService::class);
            $xmlPath = $bibleService->getXmlPath($this->selectedBook);

            if (! file_exists($xmlPath)) {
                $this->setMessage('XML file not found for this book.', 'error');
                return;
            }

            $verseOsisId = $this->selectedBook . '.' . $this->selectedChapter . '.' . $this->selectedVerse;

            // No file-copy backup - see saveTitle(). Source of truth is versioned in git.
            $status = $this->updateOsisSourceAtomically($xmlPath, function (string $xmlContent) use ($verseOsisId): ?string {
                // Remove title if present (handle sID format)
                $titlePattern = '/<title type="verse" canonical="(?:true|false)">[^<]*<\/title>\s*(?=<verse osisID="' .
                               preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*")/';

                return preg_replace($titlePattern, '', $xmlContent);
            });

            if ($status === 'unchanged') {
                $this->setMessage('No title found to remove.', 'error');

                return;
            }

            if ($status !== 'ok') {
                $this->setMessage($this->osisWriteFailureMessage($status), 'error');

                return;
            }

            $this->verseTitle = '';

            if ($this->reprojectVerse($verseOsisId)) {
                $this->setMessage('Title removed and published.', 'success');
            } else {
                $this->setMessage('Removed from the OSIS source, but publishing to the database failed. Readers will still see it until osis:import runs.', 'error');
            }
            Log::info("Verse title removed for {$verseOsisId}");

        } catch (\Exception $e) {
            Log::error('Error removing verse title: ' . $e->getMessage());
            $this->setMessage('An error occurred while removing the title.', 'error');
        }
    }

    private function setMessage($message, $type = 'success')
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    private function clearMessage()
    {
        $this->message = '';
    }

    private function getVersionId()
    {
        // Get version ID from database based on current translation key
        $translationKey = $this->currentTranslation['key'] ?? 'kjv';

        $version = \App\Models\BibleVersion::whereRaw('LOWER(abbreviation) = ?', [strtolower($translationKey)])
            ->where('canonical', true)
            ->first();

        return $version ? $version->id : 1; // Default to ID 1 if not found
    }

    public function switchTranslation($translationKey)
    {
        // Switch translation using the service
        $bibleService = app(BibleService::class);
        $bibleService->setCurrentTranslation($translationKey);

        // Update local translation data
        $this->currentTranslation = $bibleService->getCurrentTranslation();
        $this->availableTranslations = $bibleService->getAvailableTranslations();

        // Reset selections and data since we're switching versions
        $this->selectedChapter = '';
        $this->selectedVerse = '';
        $this->verseTitle = '';
        $this->chapters = [];
        $this->verses = [];
        $this->clearMessage();

        // If a book was selected, reload its chapters for the new version
        if ($this->selectedBook) {
            $this->loadChapters($this->selectedBook);
        }

        $this->setMessage('Translation switched to ' . $this->currentTranslation['name'], 'success');
    }

    public function getBookName($osisId)
    {
        $book = collect($this->books)->firstWhere('osis_id', $osisId);
        return $book ? $book->name : $osisId;
    }

    public function render()
    {
        return view('livewire.verse-title-manager')
            ->layout('layouts.app', ['title' => 'Verse Title Manager']);
    }
}
