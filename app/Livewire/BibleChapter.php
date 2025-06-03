<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\BibleService;
use Illuminate\Support\Facades\Cache;

class BibleChapter extends Component
{
    public $bookOsisId;
    public $chapterNumber;
    public $currentBook;
    public $verses = [];
    public $chapters = [];
    public $books = [];
    public $testamentBooks = [];
    public $currentTranslation = null;
    public $availableTranslations = [];
    public $capabilities = [];
    public $chapterTitle = null;
    public $paragraphs = [];

    // UI state
    public $fontSize = 'base'; // sm, base, lg, xl, 2xl
    public $searchQuery = '';
    public $showSearch = false;
    public $showBookSelector = false;
    public $selectedBookForChapters = null;
    public $selectorMode = 'books'; // 'books' or 'chapters'

    protected $bibleService;

    public function boot(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    public function mount($bookOsisId, $chapterNumber)
    {
        $this->bookOsisId = $bookOsisId;
        $this->chapterNumber = (int) $chapterNumber;

        // Get Bible data using the service
        $this->books = $this->bibleService->getBooks();
        $this->currentBook = $this->books->first(function ($book) use ($bookOsisId) {
            return strtolower($book['osis_id']) === strtolower($bookOsisId);
        });

        if (!$this->currentBook) {
            dd([
                'error' => 'Book not found',
                'requested_book_osis_id' => $bookOsisId,
                'requested_chapter_number' => $chapterNumber,
                'available_books' => $this->books->pluck('osis_id', 'name')->toArray(),
                'database_query_attempted' => "Looking for book with osis_id matching: {$bookOsisId}",
                'service_method' => 'BibleService::getBooks()',
                'books_count' => $this->books->count(),
                'db_path' => database_path('bible_app.sqlite'),
            ]);
        }

        // Store this page as the last visited
        $this->storeLastVisitedPage();

        // Get chapters and verses - use individual verses for Psalm 119 to show acrostic titles properly
        $this->chapters = $this->bibleService->getChapters($bookOsisId);
        $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;

        // For Psalm 119, use individual verse format to properly display acrostic titles
        // For other chapters, use paragraph format for better reading
        if (strtolower($bookOsisId) === 'ps' && $chapterNumber == 119) {
            // Get individual verses for Psalm 119 to show acrostic titles
            $individualVerses = $this->bibleService->getVerses($chapterOsisRef);
            $this->verses = $individualVerses->map(function ($verse) {
                return [
                    'verses' => [$verse],
                    'type' => 'individual_verse'
                ];
            });
        } else {
            // Get verses grouped by paragraphs for better reading experience
            $this->verses = $this->bibleService->getVersesParagraphStyle($chapterOsisRef);
        }

        if ($this->verses->isEmpty()) {
            dd([
                'error' => 'Chapter not found',
                'book_osis_id' => $bookOsisId,
                'chapter_number' => $chapterNumber,
                'chapter_osis_ref' => $chapterOsisRef,
                'book_name' => $this->currentBook['name'] ?? 'Unknown',
                'database_query_attempted' => "Looking for verses in chapter: {$chapterOsisRef}",
                'service_method' => strtolower($bookOsisId) === 'ps' && $chapterNumber == 119 ? 'BibleService::getVerses()' : 'BibleService::getVersesParagraphStyle()',
                'available_chapters' => $this->chapters->pluck('chapter_number')->toArray(),
                'verses_result' => $this->verses,
                'current_translation' => $this->bibleService->getCurrentTranslation(),
                'is_psalm_119' => strtolower($bookOsisId) === 'ps' && $chapterNumber == 119,
                'db_path' => database_path('bible_app.sqlite'),
            ]);
        }

        $this->testamentBooks = $this->getTestamentBooks($this->books);
        $this->capabilities = $this->bibleService->getCapabilities();

        // Get current translation from session
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        // Load font size from session or default
        $this->fontSize = session('font_size', 'base');

        // Extract chapter title from first paragraph's first verse if available
        $firstParagraph = $this->verses->first();
        if ($firstParagraph && isset($firstParagraph['verses']) && !empty($firstParagraph['verses'])) {
            $firstVerse = $firstParagraph['verses'][0];
            if (isset($firstVerse['chapter_titles']) && !empty($firstVerse['chapter_titles'])) {
                // Extract the title text from the HTML
                if (preg_match('/<div[^>]*>(.*?)<\/div>/', $firstVerse['chapter_titles'], $matches)) {
                    $this->chapterTitle = ['title_text' => $matches[1]];
                }
            }
        }

        $this->paragraphs = $this->bibleService->getChapterParagraphs($chapterOsisRef);
    }

    public function switchTranslation($translationKey)
    {
        // Switch translation using the service
        $this->bibleService->setCurrentTranslation($translationKey);
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();

        // Refresh books and chapters data
        $this->books = $this->bibleService->getBooks();
        $this->chapters = $this->bibleService->getChapters($this->bookOsisId);
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        // Reload verses with new translation
        $chapterOsisRef = $this->bookOsisId . '.' . $this->chapterNumber;

        // For Psalm 119, use individual verse format to properly display acrostic titles
        // For other chapters, use paragraph format for better reading
        if (strtolower($this->bookOsisId) === 'ps' && $this->chapterNumber == 119) {
            // Get individual verses for Psalm 119 to show acrostic titles
            $individualVerses = $this->bibleService->getVerses($chapterOsisRef);
            $this->verses = $individualVerses->map(function ($verse) {
                return [
                    'verses' => [$verse],
                    'type' => 'individual_verse'
                ];
            });
        } else {
            // Get verses grouped by paragraphs for better reading experience
            $this->verses = $this->bibleService->getVersesParagraphStyle($chapterOsisRef);
        }

        // Extract chapter title from first paragraph's first verse if available
        $firstParagraph = $this->verses->first();
        if ($firstParagraph && isset($firstParagraph['verses']) && !empty($firstParagraph['verses'])) {
            $firstVerse = $firstParagraph['verses'][0];
            if (isset($firstVerse['chapter_titles']) && !empty($firstVerse['chapter_titles'])) {
                // Extract the title text from the HTML
                if (preg_match('/<div[^>]*>(.*?)<\/div>/', $firstVerse['chapter_titles'], $matches)) {
                    $this->chapterTitle = ['title_text' => $matches[1]];
                }
            }
        } else {
            $this->chapterTitle = null;
        }

        // Emit an event to notify other components of the translation change
        $this->dispatch('translation-changed', $translationKey);
    }

    public function setFontSize($size)
    {
        $validSizes = ['sm', 'base', 'lg', 'xl', '2xl'];
        if (in_array($size, $validSizes)) {
            $this->fontSize = $size;
            session(['font_size' => $size]);
        }
    }

    public function toggleSearch()
    {
        $this->showSearch = !$this->showSearch;
        if (!$this->showSearch) {
            $this->searchQuery = '';
        }
    }

    public function search()
    {
        if (!empty($this->searchQuery)) {
            return redirect("/search?q=" . urlencode($this->searchQuery));
        }
    }

    public function goToPreviousChapter()
    {
        if ($this->chapterNumber > 1) {
            return redirect("/{$this->bookOsisId}/" . ($this->chapterNumber - 1));
        }
    }

    public function goToNextChapter()
    {
        if ($this->chapterNumber < count($this->chapters)) {
            return redirect("/{$this->bookOsisId}/" . ($this->chapterNumber + 1));
        }
    }

    public function clearLastVisited()
    {
        session()->forget('last_visited');
    }

    public function openBookSelector()
    {
        $this->showBookSelector = true;
        $this->selectorMode = 'books';
        $this->selectedBookForChapters = null;
    }

    public function hideBookSelector()
    {
        $this->showBookSelector = false;
        $this->selectorMode = 'books';
        $this->selectedBookForChapters = null;
    }

    public function selectBookForChapters($bookOsisId)
    {
        $this->selectedBookForChapters = $this->books->first(function ($book) use ($bookOsisId) {
            return strtolower($book['osis_id']) === strtolower($bookOsisId);
        });
        $this->selectorMode = 'chapters';

        // Load chapters for the selected book
        if ($this->selectedBookForChapters) {
            $this->selectedBookForChapters['chapters'] = $this->bibleService->getChapters($bookOsisId);
        }
    }

    public function goToChapter($bookOsisId, $chapterNumber)
    {
        $this->hideBookSelector();
        return redirect("/{$bookOsisId}/{$chapterNumber}");
    }

    public function backToBooks()
    {
        $this->selectorMode = 'books';
        $this->selectedBookForChapters = null;
    }

    private function storeLastVisitedPage(): void
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        $pageData = [
            'route' => 'bible.chapter',
            'parameters' => [
                'bookOsisId' => $this->bookOsisId,
                'chapterNumber' => $this->chapterNumber
            ],
            'url' => request()->url(),
            'timestamp' => now()
        ];

        // Store for 30 days
        Cache::put($cacheKey, $pageData, now()->addDays(30));
    }

    private function getTestamentBooks($books): array
    {
        $oldTestament = [];
        $newTestament = [];

        foreach ($books as $book) {
            if ($book['testament'] === 'Old Testament') {
                $oldTestament[] = $book;
            } else {
                $newTestament[] = $book;
            }
        }

        return [
            'oldTestament' => $oldTestament,
            'newTestament' => $newTestament,
        ];
    }

    public function getFontSizeClass()
    {
        return match($this->fontSize) {
            'sm' => 'text-sm',
            'base' => 'text-base',
            'lg' => 'text-lg',
            'xl' => 'text-xl',
            '2xl' => 'text-2xl',
            default => 'text-base'
        };
    }

    public function render()
    {
        return view('livewire.bible-chapter');
    }
}
