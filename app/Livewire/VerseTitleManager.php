<?php

namespace App\Livewire;

use App\Jobs\PublishChapterProjection;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Services\BibleService;
use App\Services\Osis\OsisSourcePath;
use App\Services\Osis\OsisSourceWriter;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;

class VerseTitleManager extends Component
{
    public $selectedBook = '';

    public $selectedChapter = '';

    public $selectedVerse = '';

    public $verseTitle = '';

    public $message = '';

    public $messageType = 'success';

    // Server-owned state. #[Locked]: a client cannot set these through /livewire/update -
    // currentTranslation['key'] used to reach the importer's file argument ("../x").
    #[Locked]
    public $books = [];

    #[Locked]
    public $chapters = [];

    #[Locked]
    public $verses = [];

    #[Locked]
    public $currentTranslation = null;

    #[Locked]
    public $availableTranslations = [];

    protected $rules = [
        'selectedBook' => ['required', 'string', 'regex:/^[1-3]?[A-Za-z]+$/'],
        'selectedChapter' => 'required|integer|min:1|max:150',
        'selectedVerse' => 'required|integer|min:1|max:176',
        // No control characters: they make the source unparseable (the write is then refused).
        'verseTitle' => ['required', 'string', 'max:255', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
    ];

    protected $messages = [
        'selectedBook.required' => 'Please select a book.',
        'selectedChapter.required' => 'Please select a chapter.',
        'selectedVerse.required' => 'Please select a verse.',
        'verseTitle.required' => 'Please enter a title.',
        'verseTitle.max' => 'Title cannot be longer than 255 characters.',
        'verseTitle.regex' => 'Title cannot contain control characters (line breaks, tabs).',
    ];

    /**
     * Runs on the initial render AND on every /livewire/update round-trip, so a page that was
     * already open cannot keep writing the source of truth after the editor is frozen.
     */
    public function boot(): void
    {
        abort_unless(config('bible.editor_enabled', true), 503, 'The verse-title editor is temporarily disabled for maintenance.');
    }

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
        $verseOsisId = $this->selectedBook.'.'.$this->selectedChapter.'.'.$this->selectedVerse;

        try {
            $xmlPath = app(BibleService::class)->getXmlPath($this->selectedBook);

            if (! file_exists($xmlPath)) {
                Log::warning('OSIS source not found', ['path' => $xmlPath]);
                $this->setMessage('The OSIS source file for this translation was not found.', 'error');

                return;
            }

            // Decoded: an "&amp;" in the file is "&" to the editor (re-saving used to double-encode it).
            $this->verseTitle = OsisSourceWriter::readVerseTitle((string) file_get_contents($xmlPath), $verseOsisId) ?? '';
        } catch (\Throwable $e) {
            Log::error('Error loading existing title', ['osis_id' => $verseOsisId, 'exception' => $e]);
            $this->setMessage('Could not load the current title.', 'error');
        }
    }

    /**
     * Map an OsisSourceWriter::update() status onto a message for the editor.
     */
    private function osisWriteFailureMessage(string $status): string
    {
        return match ($status) {
            'busy' => 'Another save is in progress. Please try again in a moment.',
            'lock_failed', 'write_failed' => 'Could not write the OSIS source file (logged with the reason).',
            'read_failed' => 'Could not read the OSIS source file.',
            'invalid_xml' => 'The edit would have produced invalid XML, so nothing was written.',
            'unchanged' => 'Nothing to change: the title is already set to this value.',
            'transform_failed' => 'Error processing XML content.',
            default => 'Failed to save XML file.',
        };
    }

    /**
     * The OSIS file backing the current translation - the source of truth we just wrote to.
     */
    private function getXmlPathForCurrentTranslation(): string
    {
        // Same resolver as the writer (BibleService::getXmlPath), so the file written and the
        // file re-projected can never differ; it also rejects a tampered key such as "../x".
        return \App\Services\Osis\OsisSourcePath::for($this->currentTranslation['key'] ?? null);
    }

    /**
     * Publish an edited chapter to readers: the XML is the source of truth, readers read the
     * projection. Queued (PublishChapterProjection) - the import DOM-loads the whole source
     * (~400MB), which must not happen inside a web request.
     */
    private function publishChapter(): void
    {
        PublishChapterProjection::dispatch(
            $this->currentTranslation['key'] ?? OsisSourcePath::defaultKey(),
            $this->selectedBook.'.'.(int) $this->selectedChapter,
            auth()->id(),
        );
    }

    private function audit(string $action, string $verseOsisId, ?string $old, ?string $new, string $status): void
    {
        Log::channel('audit')->info("verse title {$action}", [
            'user' => auth()->id(), 'osis_id' => $verseOsisId, 'old' => $old, 'new' => $new, 'status' => $status,
        ]);
    }

    /**
     * Apply a title transform to the source of truth, report the outcome, and publish on success.
     *
     * @param  callable(string): ?string  $transform
     */
    private function writeTitle(string $action, string $verseOsisId, callable $transform): bool
    {
        $missing = false;
        $old = null;

        $status = app(OsisSourceWriter::class)->update(
            app(BibleService::class)->getXmlPath($this->selectedBook),
            function (string $xml) use ($transform, $verseOsisId, &$missing, &$old): ?string {
                if (! OsisSourceWriter::hasVerse($xml, $verseOsisId)) {
                    $missing = true;

                    return $xml;
                }
                $old = OsisSourceWriter::readVerseTitle($xml, $verseOsisId);

                return $transform($xml);
            }
        );

        $this->audit($action, $verseOsisId, $old, $action === 'set' ? $this->verseTitle : null, $missing ? 'verse_not_found' : $status);

        if ($missing) {
            $this->setMessage("{$verseOsisId} was not found in the OSIS source.", 'error');

            return false;
        }

        if ($status !== 'ok') {
            if ($status !== 'unchanged') {
                Log::warning('Verse title write refused', ['action' => $action, 'osis_id' => $verseOsisId, 'status' => $status, 'user' => auth()->id()]);
            }
            $this->setMessage($action === 'remove' && $status === 'unchanged' ? 'This verse has no title to remove.' : $this->osisWriteFailureMessage($status), $status === 'unchanged' ? 'info' : 'error');

            return false;
        }

        $this->publishChapter();

        return true;
    }

    public function saveTitle()
    {
        $this->validate();
        $verseOsisId = $this->selectedBook.'.'.(int) $this->selectedChapter.'.'.(int) $this->selectedVerse;

        try {
            if ($this->writeTitle('set', $verseOsisId, fn (string $xml) => OsisSourceWriter::setVerseTitle($xml, $verseOsisId, $this->verseTitle))) {
                $this->setMessage('Saved. Publishing to readers - it appears on the chapter page within a minute.', 'success');
            }
        } catch (\Throwable $e) {
            Log::error('Error saving verse title', ['osis_id' => $verseOsisId, 'user' => auth()->id(), 'exception' => $e]);
            $this->setMessage('An error occurred while saving the title (logged).', 'error');
        }
    }

    public function removeTitle()
    {
        if (! $this->selectedBook || ! $this->selectedChapter || ! $this->selectedVerse) {
            $this->setMessage('Please select a verse first.', 'error');

            return;
        }

        $verseOsisId = $this->selectedBook.'.'.(int) $this->selectedChapter.'.'.(int) $this->selectedVerse;

        try {
            if ($this->writeTitle('remove', $verseOsisId, fn (string $xml) => OsisSourceWriter::removeVerseTitle($xml, $verseOsisId))) {
                $this->verseTitle = '';
                $this->setMessage('Removed. Publishing to readers - the chapter page updates within a minute.', 'success');
            }
        } catch (\Throwable $e) {
            Log::error('Error removing verse title', ['osis_id' => $verseOsisId, 'user' => auth()->id(), 'exception' => $e]);
            $this->setMessage('An error occurred while removing the title (logged).', 'error');
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
        // Only translations projected into the database can be edited (writing an unprojected
        // source and "publishing" it would create a second, mostly empty bible version).
        if (! is_string($translationKey) || ! preg_match('/^[a-z0-9_-]+$/i', $translationKey) || ! OsisSourcePath::isProjected($translationKey)) {
            $this->setMessage('That translation is not available for editing.', 'error');

            return;
        }

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

        $this->setMessage('Translation switched to '.$this->currentTranslation['name'], 'success');
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
