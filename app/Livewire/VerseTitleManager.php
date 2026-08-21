<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\Verse;
use App\Models\Chapter;
use Livewire\Component;
use App\Services\BibleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

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
            $versePattern = '/<title type="verse" canonical="true">([^<]*)<\/title>\s*<verse osisID="' .
                           preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*"/';

            if (preg_match($versePattern, $xmlContent, $matches)) {
                $this->verseTitle = $matches[1];
            }
        } catch (\Exception $e) {
            Log::error('Error loading existing title: ' . $e->getMessage());
        }
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

            $xmlContent = file_get_contents($xmlPath);
            $verseOsisId = $this->selectedBook . '.' . $this->selectedChapter . '.' . $this->selectedVerse;

            // Remove existing title if present (handle sID format)
            $existingTitlePattern = '/<title type="verse" canonical="true">[^<]*<\/title>\s*(?=<verse osisID="' .
                                   preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*")/';
            $xmlContent = preg_replace($existingTitlePattern, '', $xmlContent);

            // Add new title before the verse (handle sID format)
            $versePattern = '/(<verse osisID="' . preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*"[^>]*>)/';
            $replacement = '<title type="verse" canonical="true">' . htmlspecialchars($this->verseTitle) . '</title>$1';

            $newXmlContent = preg_replace($versePattern, $replacement, $xmlContent);

            if ($newXmlContent === null) {
                $this->setMessage('Error processing XML content.', 'error');
                return;
            }

            if ($newXmlContent === $xmlContent) {
                $this->setMessage('Verse not found in XML file.', 'error');
                return;
            }

            // No file-copy backup: assets/*.osis.xml is the source of truth and is
            // versioned in git. The previous copy-per-save wrote a full ~27MB snapshot on
            // every save from an unauthenticated page - an unbounded disk-fill vector that
            // had already accumulated 20 untracked files (~500MB).

            // Save the modified XML
            if (file_put_contents($xmlPath, $newXmlContent) !== false) {
                // The XML is the source of truth; the reader reads the projection. Publish it.
                if ($this->reprojectVerse($verseOsisId)) {
                    $this->setMessage('Title saved and published.', 'success');
                } else {
                    $this->setMessage('Saved to the OSIS source, but publishing to the database failed. Readers will not see it until osis:import runs.', 'error');
                }
                Log::info("Verse title saved for {$verseOsisId}: {$this->verseTitle}");
            } else {
                $this->setMessage('Failed to save XML file.', 'error');
            }

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

            $xmlContent = file_get_contents($xmlPath);
            $verseOsisId = $this->selectedBook . '.' . $this->selectedChapter . '.' . $this->selectedVerse;

            // Remove title if present (handle sID format)
            $titlePattern = '/<title type="verse" canonical="true">[^<]*<\/title>\s*(?=<verse osisID="' .
                           preg_quote($verseOsisId, '/') . '"[^>]*sID="[^"]*")/';
            $newXmlContent = preg_replace($titlePattern, '', $xmlContent);

            if ($newXmlContent !== $xmlContent) {
                // No file-copy backup - see saveTitle(). Source of truth is versioned in git.

                // Save the modified XML
                if (file_put_contents($xmlPath, $newXmlContent) !== false) {
                    $this->verseTitle = '';
                    if ($this->reprojectVerse($verseOsisId)) {
                        $this->setMessage('Title removed and published.', 'success');
                    } else {
                        $this->setMessage('Removed from the OSIS source, but publishing to the database failed. Readers will still see it until osis:import runs.', 'error');
                    }
                    Log::info("Verse title removed for {$verseOsisId}");
                } else {
                    $this->setMessage('Failed to save XML file.', 'error');
                }
            } else {
                $this->setMessage('No title found to remove.', 'error');
            }

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
