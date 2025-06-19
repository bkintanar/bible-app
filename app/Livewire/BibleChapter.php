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
    public $showStrongsNumbers = false; // Enable/disable Strong's numbers display

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

        // Create chapter OSIS reference for error messages
        $chapterOsisRef = $this->bookOsisId . '.' . $this->chapterNumber;

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

        // Load Strong's numbers setting from session
        $this->showStrongsNumbers = session('show_strongs_numbers', true);

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

    public function toggleStrongsNumbers()
    {
        $this->showStrongsNumbers = !$this->showStrongsNumbers;
        session(['show_strongs_numbers' => $this->showStrongsNumbers]);
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
            'route' => 'chapters.show',
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

    public function getFontSizeClass(): string
    {
        $sizeMap = [
            'sm' => 'text-lg',
            'base' => 'text-xl',
            'lg' => 'text-2xl',
            'xl' => 'text-3xl',
            '2xl' => 'text-4xl',
        ];

        return $sizeMap[$this->fontSize] ?? 'text-xl';
    }

    /**
     * Parse the original XML markup and convert it to enhanced HTML with Strong's styling
     */
        public function parseEnhancedVerseText($verse): string
    {
        $osisId = $verse['osis_id'] ?? 'unknown';
        $verseNum = $verse['verse_number'] ?? 'unknown';

                                // Let the XML processing handle red letters - don't override with manual mappings

        // If Strong's numbers are disabled, use regular text but still handle red letters
        if (!$this->showStrongsNumbers) {
            $basicText = strip_tags($verse['text'], '<em><strong><sup><sub><span>');
            \Log::info("📖 Strong's disabled, using basic text path for {$osisId}");
            // Still need to handle red letters even when Strong's are disabled
            return $this->applyRedLetterFormatting($basicText, $verse);
        }

        // Fallback to regular text if no original_xml
        if (!isset($verse['original_xml']) || empty($verse['original_xml'])) {
            $basicText = strip_tags($verse['text'], '<em><strong><sup><sub><span>');
            \Log::info("📖 No original_xml, using basic text path for {$osisId}");
            return $this->applyRedLetterFormatting($basicText, $verse);
        }

        $xml = $verse['original_xml'];
        \Log::info("📖 Processing XML path for {$osisId}, XML length: " . strlen($xml));

        // First, handle red letter text (Jesus's words) before processing other elements
        $xml = $this->processRedLetterText($xml);
        \Log::info("📖 After processRedLetterText for {$osisId}");

        // Remove note tags and other non-essential elements
        $xml = preg_replace('/<note[^>]*>.*?<\/note>/i', '', $xml); // Remove study notes
        $xml = preg_replace('/<milestone[^>]*\/?>/i', '', $xml); // Remove milestone markers

        // Parse the XML and convert <w> tags to enhanced spans
        $enhanced = preg_replace_callback(
            '/<w([^>]*)>(.*?)<\/w>/',
            function ($matches) {
                $attributes = $matches[1];
                $text = $matches[2];

                $classes = ['strong-word'];
                $title = '';
                $dataAttrs = '';

                // Extract Strong's numbers
                if (preg_match('/lemma="([^"]*)"/', $attributes, $lemmaMatch)) {
                    $lemmas = $lemmaMatch[1];
                    if (strpos($lemmas, 'strong:') !== false) {
                        preg_match_all('/strong:([HG]\d+)/', $lemmas, $strongMatches);
                        if (!empty($strongMatches[1])) {
                            $strongNumbers = $strongMatches[1];
                            $classes[] = 'has-strongs';
                            $title = 'Strong\'s: ' . implode(', ', $strongNumbers);
                            $dataAttrs = 'data-strongs="' . implode(',', $strongNumbers) . '"';

                            // Add different styling for Hebrew vs Greek
                            if (strpos($strongNumbers[0], 'H') === 0) {
                                $classes[] = 'hebrew-word';
                            } else {
                                $classes[] = 'greek-word';
                            }
                        }
                    }
                }

                // Extract morphology
                if (preg_match('/morph="([^"]*)"/', $attributes, $morphMatch)) {
                    $morph = $morphMatch[1];
                    if (strpos($morph, 'strongMorph:') !== false) {
                        $classes[] = 'has-morph';
                        $morphCode = str_replace('strongMorph:', '', $morph);
                        $dataAttrs .= ' data-morph="' . $morphCode . '"';
                        $title .= $title ? ' | Morph: ' . $morphCode : 'Morph: ' . $morphCode;
                    }
                }

                $classStr = implode(' ', $classes);
                $titleAttr = $title ? 'title="' . htmlspecialchars($title) . '"' : '';

                return "<span class=\"{$classStr}\" {$titleAttr} {$dataAttrs}>{$text}</span>";
            },
            $xml
        );

        // Fix spacing issues: add spaces between words that don't have punctuation between them
        $enhanced = preg_replace('/><span class="strong-word/', '> <span class="strong-word', $enhanced);

        // Clean up extra spaces and fix spacing around punctuation
        $enhanced = preg_replace('/\s+/', ' ', $enhanced); // Multiple spaces to single space
        $enhanced = preg_replace('/\s+([,.;:!?])/', '$1', $enhanced); // Remove space before punctuation
        $enhanced = trim($enhanced);

        return $enhanced;
    }

    /**
     * Process red letter text (Jesus's words) in the XML markup
     * Handles both contained and milestone-style markup with proper nesting
     */
    private function processRedLetterText($xml): string
    {
        \Log::info("🔴 Processing red letters for XML: " . substr($xml, 0, 100) . '...');

        // First, handle simple contained markup: <q who="Jesus">content</q>
        $xml = preg_replace('/<q[^>]*who="Jesus"[^>]*>(.*?)<\/q>/is', '<span class="text-red-600 dark:text-red-400 font-medium">$1</span>', $xml);

        // Handle milestone-style red letter markup more carefully
        // Look for Jesus speech markers and track their state
        $redLetterOpen = false;
        $result = '';
        $pos = 0;

        while ($pos < strlen($xml)) {
            // Look for <q who="Jesus" markers
            if (preg_match('/<q[^>]*who="Jesus"[^>]*>/i', $xml, $matches, PREG_OFFSET_CAPTURE, $pos)) {
                $match = $matches[0];
                $matchStart = $match[1];

                // Add content before the match
                $result .= substr($xml, $pos, $matchStart - $pos);

                // Check if this is opening or closing a red letter section
                if (strpos($match[0], 'marker=""') !== false || strpos($match[0], 'sID=') !== false) {
                    // Opening marker
                    if (!$redLetterOpen) {
                        $result .= '<span class="text-red-600 dark:text-red-400 font-medium">';
                        $redLetterOpen = true;
                        \Log::info("🔴 Opening red letter span");
                    }
                } else if (strpos($match[0], 'eID=') !== false) {
                    // Closing marker
                    if ($redLetterOpen) {
                        $result .= '</span>';
                        $redLetterOpen = false;
                        \Log::info("🔴 Closing red letter span");
                    }
                }

                $pos = $matchStart + strlen($match[0]);
            } else {
                // No more Jesus markers, add remaining content
                $result .= substr($xml, $pos);
                break;
            }
        }

        // Clean up any remaining </q> tags that weren't properly handled
        $result = preg_replace('/<\/q>/i', $redLetterOpen ? '</span>' : '', $result);

        // If we ended with an open red letter span, close it
        if ($redLetterOpen) {
            $result .= '</span>';
            \Log::info("🔴 Force closing red letter span at end");
        }

        \Log::info("🔴 Final processed XML: " . substr($result, 0, 100) . '...');
        return $result;
    }

        /**
     * Apply red letter formatting using the red_letter_text table data
     */
    private function applyRedLetterFormatting($text, $verse): string
    {
        $osisId = $verse['osis_id'] ?? 'unknown';
        \Log::info("🔤 applyRedLetterFormatting called for {$osisId}");

        // This method now relies primarily on the database red_letter_text entries
        // Run: php artisan bible:fix-red-letters to populate missing red letter entries

        // Check if we have original XML with red letter markup first
        if (isset($verse['original_xml']) && !empty($verse['original_xml'])) {
            $xml = $verse['original_xml'];
            \Log::info("🔤 Found original_xml for {$osisId}, checking for red letter markup");

            // Check if XML contains Jesus speech markers
            if (preg_match('/<q[^>]*who="Jesus"[^>]*>/i', $xml)) {
                \Log::info("🔤 Found Jesus markup in XML for {$osisId}, processing with XML parser");
                // Let the main XML processing handle this
                return $text;
            }
        }

        // If we have red letter data for this verse in the database, apply it
        $verseId = $verse['id'] ?? null;
        if ($verseId) {
            \Log::info("🔤 Checking red_letter_text table for verse ID {$verseId}");
            $redLetterEntries = \DB::table('red_letter_text')
                ->where('verse_id', $verseId)
                ->orderBy('text_order')
                ->get();

            \Log::info("🔤 Found " . $redLetterEntries->count() . " red letter entries for {$osisId}");

            if ($redLetterEntries->count() > 0) {
                // For verses with red letter entries, wrap the entire verse in red letter styling
                // This handles cases where the verse text has HTML markup that doesn't match exactly
                \Log::info("🔤 Applying red letter styling to entire verse {$osisId}");
                $text = '<span class="text-red-600 dark:text-red-400 font-medium">' . $text . '</span>';

                // Convert any gray translator changes to red italics for consistency
                $text = $this->applyRedLetterTranslatorChanges($text);
            }
        } else {
            \Log::info("🔤 No verse ID available for {$osisId}, skipping database red letters");
        }

        return $text;
    }



    /**
     * Convert gray translator changes to red italics for red letter verses
     */
    private function applyRedLetterTranslatorChanges($text): string
    {
        // Replace gray translator change styling with red italics for Jesus's words
        $text = str_replace(
            'class="text-gray-600 dark:text-gray-400 font-normal italic"',
            'class="text-red-500 dark:text-red-300 font-normal italic opacity-80"',
            $text
        );

        return $text;
    }

    public function render()
    {
        return view('livewire.bible-chapter');
    }
}
