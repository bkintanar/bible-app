<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\BibleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ChapterController;
use App\Jobs\CacheAdjacentChaptersJob;

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

    // Adjacent chapter preloading for book flip effect
    public $previousChapterVerses = [];
    public $nextChapterVerses = [];

    // UI state
    public $fontSize = 'base'; // sm, base, lg, xl, 2xl
    public $searchQuery = '';
    public $showSearch = false;
    public $showBookSelector = false;
    public $selectedBookForChapters = null;
    public $selectorMode = 'books'; // 'books' or 'chapters'
    public $searchResults = [];
    public $isSearching = false;
    public $searchStats = [];
    public $showSearchResults = false;
    public $returnToChapter = []; // Store chapter info to return to

    protected $bibleService;

    public function boot(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    public function mount($bookOsisId, $chapterNumber)
    {
        // Initialize collections first
        $this->previousChapterVerses = collect();
        $this->nextChapterVerses = collect();

        $this->bookOsisId = $bookOsisId;
        $this->chapterNumber = (int) $chapterNumber;

        // Get Bible data using the service
        $this->books = $this->bibleService->getBooks();
        $this->currentBook = $this->books->first(function ($book) use ($bookOsisId) {
            return strtolower($book['osis_id']) === strtolower($bookOsisId);
        });

        if (! $this->currentBook) {
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

        // Get chapters and verses - check cache first
        $this->chapters = $this->bibleService->getChapters($bookOsisId);

        // Try to load current chapter from cache first
        $this->verses = $this->loadChapterVerses($bookOsisId, $chapterNumber);
        \Log::info("📖 Loaded main chapter {$bookOsisId} {$chapterNumber} with " . $this->verses->count() . ' paragraphs');

        // DEBUG: Log verse titles for Genesis 1 in mount


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
        if ($firstParagraph && isset($firstParagraph['verses']) && ! empty($firstParagraph['verses'])) {
            $firstVerse = $firstParagraph['verses'][0];
            if (isset($firstVerse['chapter_titles']) && ! empty($firstVerse['chapter_titles'])) {
                // Extract the title text from the HTML
                if (preg_match('/<div[^>]*>(.*?)<\/div>/', $firstVerse['chapter_titles'], $matches)) {
                    $this->chapterTitle = ['title_text' => $matches[1]];
                }
            }
        }

        // Load paragraphs for the current chapter
        $chapterOsisRef = $this->bookOsisId . '.' . $this->chapterNumber;
        $this->paragraphs = $this->bibleService->getChapterParagraphs($chapterOsisRef);

        // Preload adjacent chapters for book flip effect
        $this->loadAdjacentChapters($bookOsisId, $chapterNumber);
    }

    /**
     * Load adjacent chapters for book flip preloading
     * Uses cached data when available, dispatches background job for missing cache
     * @param mixed $bookOsisId
     * @param mixed $chapterNumber
     */
    private function loadAdjacentChapters($bookOsisId, $chapterNumber)
    {
        \Log::info("📋 Loading adjacent chapters for {$bookOsisId} chapter {$chapterNumber}");

        // Try to load cached adjacent chapters
        $loadedFromCache = $this->loadCachedAdjacentChapters($bookOsisId, $chapterNumber);

        // If not fully cached, dispatch background job to cache them
        if (! $loadedFromCache) {
            \Log::info("🔄 Dispatching background job to cache adjacent chapters for {$bookOsisId} {$chapterNumber}");
            CacheAdjacentChaptersJob::dispatch($bookOsisId, $chapterNumber);
        }

        \Log::info("✅ Adjacent chapters loading completed for {$bookOsisId} {$chapterNumber}");
    }

    /**
     * Try to load adjacent chapters from cache
     * @param string $bookOsisId
     * @param int $chapterNumber
     * @return bool True if both adjacent chapters were loaded from cache
     */
    private function loadCachedAdjacentChapters($bookOsisId, $chapterNumber): bool
    {
        $bothCached = true;

        // Try to load previous chapter from cache
        if ($chapterNumber > 1) {
            $cached = ChapterController::getCachedChapterVerses($bookOsisId, $chapterNumber - 1);
            if ($cached && isset($cached['verses'])) {
                $this->previousChapterVerses = $cached['verses'];
                \Log::info('📋 Loaded previous chapter ' . ($chapterNumber - 1) . ' from cache');
            } else {
                $this->previousChapterVerses = collect();
                $bothCached = false;
                \Log::info('🌐 Previous chapter ' . ($chapterNumber - 1) . ' not cached, will load in background');
            }
        } else {
            $this->previousChapterVerses = collect();
            \Log::info('📋 No previous chapter (at chapter 1)');
        }

        // Try to load next chapter from cache
        $maxChapterNumber = $this->chapters->max('chapter_number') ?? 0;
        if ($chapterNumber < $maxChapterNumber) {
            $cached = ChapterController::getCachedChapterVerses($bookOsisId, $chapterNumber + 1);
            if ($cached && isset($cached['verses'])) {
                $this->nextChapterVerses = $cached['verses'];
                \Log::info('📋 Loaded next chapter ' . ($chapterNumber + 1) . ' from cache');
            } else {
                $this->nextChapterVerses = collect();
                $bothCached = false;
                \Log::info('🌐 Next chapter ' . ($chapterNumber + 1) . ' not cached, will load in background');
            }
        } else {
            $this->nextChapterVerses = collect();
            \Log::info('📋 No next chapter (at last chapter)');
        }

        return $bothCached;
    }

    /**
     * Load chapter verses, checking cache first, then falling back to database
     * @param mixed $bookOsisId
     * @param mixed $chapterNumber
     */
    private function loadChapterVerses($bookOsisId, $chapterNumber)
    {
        // Try to get from cache first
        $cached = ChapterController::getCachedChapterVerses($bookOsisId, $chapterNumber);

        if ($cached && isset($cached['verses'])) {
            \Log::info("📋 Using cached verses for {$bookOsisId} {$chapterNumber}");
            return $cached['verses'];
        }

        // Cache miss - load from database
        \Log::info("🌐 Loading verses from database for {$bookOsisId} {$chapterNumber}");

        $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;

        try {
            // Use the same logic as the main chapter for consistency
            if (strtolower($bookOsisId) === 'ps' && $chapterNumber == 119) {
                $individualVerses = $this->bibleService->getVerses($chapterOsisRef);
                return $individualVerses->map(function ($verse) {
                    return [
                        'verses' => [$verse],
                        'type' => 'individual_verse',
                    ];
                });
            } else {
                $verses = $this->bibleService->getVersesParagraphStyle($chapterOsisRef);

                return $verses;
            }
        } catch (\Exception $e) {
            \Log::error("❌ Error loading verses for {$bookOsisId} {$chapterNumber}: " . $e->getMessage());
            return collect();
        }
    }

    /**
     * Log the result of chapter loading for debugging
     * @param mixed $direction
     * @param mixed $chapterNumber
     * @param mixed $verses
     */
    private function logChapterLoadResult($direction, $chapterNumber, $verses)
    {
        if (! empty($verses) && isset($verses[0]['verses'][0]['text'])) {
            $firstText = substr(strip_tags($verses[0]['verses'][0]['text']), 0, 30);
            \Log::info("{$direction}: Loaded chapter {$chapterNumber} - First verse: {$firstText}...");
        } else {
            \Log::info("{$direction}: No verses loaded for chapter {$chapterNumber}");
        }
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
                    'type' => 'individual_verse',
                ];
            });
        } else {
            // Get verses grouped by paragraphs for better reading experience
            $this->verses = $this->bibleService->getVersesParagraphStyle($chapterOsisRef);
        }

        // Extract chapter title from first paragraph's first verse if available
        $firstParagraph = $this->verses->first();
        if ($firstParagraph && isset($firstParagraph['verses']) && ! empty($firstParagraph['verses'])) {
            $firstVerse = $firstParagraph['verses'][0];
            if (isset($firstVerse['chapter_titles']) && ! empty($firstVerse['chapter_titles'])) {
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

        // Reload adjacent chapters with new translation
        $this->loadAdjacentChapters($this->bookOsisId, $this->chapterNumber);
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
        $this->showSearch = ! $this->showSearch;
        if (! $this->showSearch) {
            $this->searchQuery = '';
            $this->searchResults = [];
            $this->searchStats = [];
            $this->isSearching = false;
            $this->showSearchResults = false;
        }
    }

    public function search()
    {
        if (! empty($this->searchQuery)) {
            $this->isSearching = true;

            // Store current chapter info for returning
            $this->returnToChapter = [
                'book_osis_id' => $this->bookOsisId,
                'chapter_number' => $this->chapterNumber,
                'book_name' => $this->currentBook['name'] ?? $this->bookOsisId,
            ];

            // Use the BibleService to search for verses
            $searchData = $this->bibleService->search($this->searchQuery, 50);

            $this->searchResults = $searchData['results'] ?? [];
            $this->searchStats = [
                'total_found' => $searchData['total_found'] ?? 0,
                'search_time_ms' => $searchData['search_time_ms'] ?? 0,
                'query' => $this->searchQuery,
            ];

            $this->isSearching = false;

            // Switch to search results view
            $this->showSearchResults = true;
            $this->showSearch = false;
        }
    }

    public function backToChapter()
    {
        $this->showSearchResults = false;
        $this->searchResults = [];
        $this->searchStats = [];
        $this->searchQuery = '';
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
                'chapterNumber' => $this->chapterNumber,
            ],
            'url' => request()->url(),
            'timestamp' => now(),
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
