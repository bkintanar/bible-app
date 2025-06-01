<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BibleController extends Controller
{
    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Store the last visited page in cache
     */
    private function storeLastVisitedPage(string $route, array $parameters = []): void
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        $pageData = [
            'route' => $route,
            'parameters' => $parameters,
            'url' => request()->url(),
            'timestamp' => now()
        ];

        // Store for 30 days
        Cache::put($cacheKey, $pageData, now()->addDays(30));
    }

    /**
     * Get the last visited page from cache
     */
    private function getLastVisitedPage(): ?array
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        return Cache::get($cacheKey);
    }

    /**
     * Display the Bible home page with list of books or redirect to last visited page
     */
    public function index(Request $request): View|RedirectResponse
    {
        // Check if user wants to force showing the index page (e.g., ?fresh=1)
        $forceIndex = $request->has('fresh');

        // Check if there's a last visited page and we're not forcing index
        if (!$forceIndex) {
            $lastPage = $this->getLastVisitedPage();

            if ($lastPage && isset($lastPage['route']) && $lastPage['route'] !== 'bible.index') {
                // Validate that the route still exists and parameters are valid
                try {
                    if ($lastPage['route'] === 'bible.book' && isset($lastPage['parameters']['bookOsisId'])) {
                        return redirect()->route('bible.book', $lastPage['parameters']['bookOsisId']);
                    } elseif ($lastPage['route'] === 'bible.chapter' && isset($lastPage['parameters']['bookOsisId'], $lastPage['parameters']['chapterNumber'])) {
                        return redirect()->route('bible.chapter', [
                            $lastPage['parameters']['bookOsisId'],
                            $lastPage['parameters']['chapterNumber']
                        ]);
                    } elseif ($lastPage['route'] === 'bible.verse' && isset($lastPage['parameters']['bookOsisId'], $lastPage['parameters']['chapterNumber'], $lastPage['parameters']['verseNumber'])) {
                        return redirect()->route('bible.verse', [
                            $lastPage['parameters']['bookOsisId'],
                            $lastPage['parameters']['chapterNumber'],
                            $lastPage['parameters']['verseNumber']
                        ]);
                    }
                } catch (\Exception $e) {
                    // If redirect fails, redirect to Genesis 1 as default
                    return redirect()->route('bible.chapter', ['Gen', 1]);
                }
            } else {
                // No cached page found, redirect to Genesis 1 as default starting point
                return redirect()->route('bible.chapter', ['Gen', 1]);
            }
        }

        $bibleInfo = $this->bibleService->getBibleInfo();
        $books = $this->bibleService->getBooks();
        $currentTranslation = $this->bibleService->getCurrentTranslation();
        $availableTranslations = $this->bibleService->getAvailableTranslations();
        $capabilities = $this->bibleService->getCapabilities();

        return view('bible.index', compact(
            'bibleInfo',
            'books',
            'currentTranslation',
            'availableTranslations',
            'capabilities'
        ));
    }

    /**
     * Display chapters for a specific book
     */
    public function book(string $bookOsisId): View
    {
        $books = $this->bibleService->getBooks();
        $currentBook = $books->firstWhere('osis_id', $bookOsisId);

        if (!$currentBook) {
            abort(404, 'Book not found');
        }

        // Store this page as the last visited
        $this->storeLastVisitedPage('bible.book', ['bookOsisId' => $bookOsisId]);

        $chapters = $this->bibleService->getChapters($bookOsisId);
        $currentTranslation = $this->bibleService->getCurrentTranslation();
        $availableTranslations = $this->bibleService->getAvailableTranslations();
        $capabilities = $this->bibleService->getCapabilities();

        return view('bible.book', compact(
            'currentBook',
            'chapters',
            'books',
            'currentTranslation',
            'availableTranslations',
            'capabilities'
        ));
    }

    /**
     * Display verses for a specific chapter
     */
    public function chapter(string $bookOsisId, int $chapterNumber, Request $request): View
    {
        $books = $this->bibleService->getBooks();
        $currentBook = $books->firstWhere('osis_id', $bookOsisId);

        if (!$currentBook) {
            abort(404, 'Book not found');
        }

        // Store this page as the last visited
        $this->storeLastVisitedPage('bible.chapter', [
            'bookOsisId' => $bookOsisId,
            'chapterNumber' => $chapterNumber
        ]);

        // Get available chapters for navigation
        $chapters = $this->bibleService->getChapters($bookOsisId);

        // Determine format style (paragraph or verse)
        $formatStyle = $request->get('style', 'paragraph'); // Default to paragraph style

        $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;

        // Get verses based on format style
        if ($formatStyle === 'paragraph') {
            $verses = $this->bibleService->getVersesParagraphStyle($chapterOsisRef);
            $paragraphs = $verses; // For paragraph style, verses are already grouped
        } else {
            $verses = $this->bibleService->getVerses($chapterOsisRef);
            $paragraphs = null; // No paragraph grouping for verse style
        }

        if ($verses->isEmpty()) {
            abort(404, 'Chapter not found');
        }

        $currentTranslation = $this->bibleService->getCurrentTranslation();
        $availableTranslations = $this->bibleService->getAvailableTranslations();
        $capabilities = $this->bibleService->getCapabilities();

        return view('bible.chapter', compact(
            'currentBook',
            'chapterNumber',
            'verses',
            'paragraphs',
            'formatStyle',
            'chapters',
            'books',
            'currentTranslation',
            'availableTranslations',
            'capabilities'
        ));
    }

    /**
     * Display a specific verse with enhanced details
     */
    public function verse(string $bookOsisId, int $chapterNumber, int $verseNumber): View
    {
        $books = $this->bibleService->getBooks();
        $currentBook = $books->firstWhere('osis_id', $bookOsisId);

        if (!$currentBook) {
            abort(404, 'Book not found');
        }

        // Store this page as the last visited
        $this->storeLastVisitedPage('bible.verse', [
            'bookOsisId' => $bookOsisId,
            'chapterNumber' => $chapterNumber,
            'verseNumber' => $verseNumber
        ]);

        $verseOsisId = $bookOsisId . '.' . $chapterNumber . '.' . $verseNumber;

        // Get comprehensive verse details if database reader is available
        if ($this->bibleService->hasEnhancedFeatures()) {
            $verseDetails = $this->bibleService->getVerseWithDetails($verseOsisId);
        } else {
            $verse = $this->bibleService->getVerseByReference($bookOsisId, $chapterNumber, $verseNumber);
            $verseDetails = $verse ? ['verse' => $verse] : [];
        }

        if (empty($verseDetails)) {
            abort(404, 'Verse not found');
        }

        $currentTranslation = $this->bibleService->getCurrentTranslation();
        $availableTranslations = $this->bibleService->getAvailableTranslations();
        $capabilities = $this->bibleService->getCapabilities();

        return view('bible.verse', compact(
            'currentBook',
            'chapterNumber',
            'verseNumber',
            'verseDetails',
            'books',
            'currentTranslation',
            'availableTranslations',
            'capabilities'
        ));
    }

    /**
     * Handle translation switching
     */
    public function switchTranslation(Request $request): RedirectResponse
    {
        $translationKey = $request->input('translation');

        if (!$this->bibleService->translationExists($translationKey)) {
            return back()->withErrors(['translation' => 'Invalid translation selected']);
        }

        $this->bibleService->setCurrentTranslation($translationKey);

        return back();
    }

    /**
     * Search for verses
     */
    public function search(Request $request): View
    {
        $searchTerm = $request->get('q', '');
        $limit = (int) $request->get('limit', 50);
        $searchType = $request->get('type', 'text'); // text, strongs, reference

        $results = collect();
        $searchInfo = [
            'term' => $searchTerm,
            'type' => $searchType,
            'count' => 0,
            'time_ms' => 0
        ];

        $groupedResults = collect();
        $totalFound = 0;
        $hasMoreResults = false;

        if (!empty($searchTerm)) {
            $startTime = microtime(true);

            // Auto-detect verse references if search type is 'text'
            if ($searchType === 'text') {
                $parsedRef = $this->bibleService->parseVerseReference($searchTerm);
                if ($parsedRef) {
                    // This looks like a verse reference, handle it as such
                    $searchType = 'reference';
                    $searchInfo['type'] = 'reference (auto-detected)';
                }
            }

            switch ($searchType) {
                case 'strongs':
                    if ($this->bibleService->hasEnhancedFeatures()) {
                        $results = $this->bibleService->searchByStrongsNumber($searchTerm, $limit);
                    }
                    break;

                case 'reference':
                    $parsedRef = $this->bibleService->parseVerseReference($searchTerm);
                    if ($parsedRef) {
                        if ($parsedRef['type'] === 'verse') {
                            $verse = $this->bibleService->getVerseByReference(
                                $parsedRef['book_osis_id'],
                                $parsedRef['chapter'],
                                $parsedRef['verse']
                            );
                            if ($verse) {
                                // Normalize verse result structure
                                $normalizedVerse = $this->normalizeVerseResult($verse, 'reference');
                                $results = collect([$normalizedVerse]);
                            }
                        } elseif ($parsedRef['type'] === 'chapter') {
                            $chapterOsisRef = $parsedRef['book_osis_id'] . '.' . $parsedRef['chapter'];
                            $verses = $this->bibleService->getVerses($chapterOsisRef);
                            // Normalize chapter verses
                            $results = $verses->map(function ($verse) {
                                return $this->normalizeVerseResult($verse, 'chapter');
                            });
                        }
                    }
                    break;

                case 'text':
                default:
                    $results = $this->bibleService->searchVerses($searchTerm, $limit + 1); // +1 to detect if there are more results
                    $hasMoreResults = $results->count() > $limit;
                    if ($hasMoreResults) {
                        $results = $results->take($limit); // Remove the extra result
                    }
                    break;
            }

            $searchInfo['time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $searchInfo['count'] = $results->count();
            $totalFound = $results->count();

            // Group results by book for better presentation
            if ($results->isNotEmpty()) {
                $groupedResults = $this->groupSearchResultsByBook($results);
            }
        }

        $currentTranslation = $this->bibleService->getCurrentTranslation();
        $availableTranslations = $this->bibleService->getAvailableTranslations();
        $capabilities = $this->bibleService->getCapabilities();

        return view('bible.search', compact(
            'searchTerm',
            'searchType',
            'results',
            'searchInfo',
            'groupedResults',
            'totalFound',
            'hasMoreResults',
            'limit',
            'currentTranslation',
            'availableTranslations',
            'capabilities'
        ));
    }

    /**
     * Normalize verse result to have consistent structure
     */
    private function normalizeVerseResult(array $verse, string $source = 'search'): array
    {
        // Determine book information
        $bookOsisId = $verse['book_osis_id'] ?? $verse['book_id'] ?? null;
        $bookName = $verse['book_name'] ?? null;

        // Get book name if not provided
        if (!$bookName && $bookOsisId) {
            $books = $this->bibleService->getBooks()->keyBy('osis_id');
            $book = $books->get($bookOsisId);
            $bookName = $book['name'] ?? $bookOsisId;
        }

        $chapter = $verse['chapter'] ?? 1;
        $verseNumber = $verse['verse_number'] ?? $verse['verse'] ?? 1;

        return [
            'osis_id' => $verse['osis_id'],
            'verse_number' => (int) $verseNumber,
            'text' => $verse['text'],
            'book_name' => $bookName,
            'book_osis_id' => $bookOsisId,
            'chapter' => (int) $chapter,
            'reference' => $verse['reference'] ?? "{$bookName} {$chapter}:{$verseNumber}"
        ];
    }

    /**
     * Group search results by book for better presentation
     */
    private function groupSearchResultsByBook($results): \Illuminate\Support\Collection
    {
        $books = $this->bibleService->getBooks()->keyBy('osis_id');

        return $results->groupBy('book_osis_id')->map(function ($verses, $bookOsisId) use ($books) {
            $book = $books->get($bookOsisId);

            return [
                'book' => [
                    'osis_id' => $bookOsisId,
                    'name' => $book['name'] ?? $bookOsisId,
                    'short_name' => $this->getShortBookName($book['name'] ?? $bookOsisId),
                    'testament' => $this->getTestament($bookOsisId)
                ],
                'verses' => $verses->map(function ($verse) {
                    return [
                        'book_id' => $verse['book_osis_id'],
                        'chapter' => $verse['chapter'],
                        'verse' => $verse['verse_number'],
                        'context' => $verse['text'], // This will have FTS5 highlighting
                        'reference' => $verse['reference']
                    ];
                })
            ];
        });
    }

    /**
     * Get short book name for display
     */
    private function getShortBookName(string $bookName): string
    {
        $shortNames = [
            'Genesis' => 'Gen', 'Exodus' => 'Exod', 'Leviticus' => 'Lev', 'Numbers' => 'Num',
            'Deuteronomy' => 'Deut', 'Joshua' => 'Josh', 'Judges' => 'Judg', 'Ruth' => 'Ruth',
            '1 Samuel' => '1 Sam', '2 Samuel' => '2 Sam', '1 Kings' => '1 Kgs', '2 Kings' => '2 Kgs',
            '1 Chronicles' => '1 Chr', '2 Chronicles' => '2 Chr', 'Ezra' => 'Ezra', 'Nehemiah' => 'Neh',
            'Esther' => 'Esth', 'Job' => 'Job', 'Psalms' => 'Ps', 'Proverbs' => 'Prov',
            'Ecclesiastes' => 'Eccl', 'Song of Solomon' => 'Song', 'Isaiah' => 'Isa', 'Jeremiah' => 'Jer',
            'Lamentations' => 'Lam', 'Ezekiel' => 'Ezek', 'Daniel' => 'Dan', 'Hosea' => 'Hos',
            'Joel' => 'Joel', 'Amos' => 'Amos', 'Obadiah' => 'Obad', 'Jonah' => 'Jonah',
            'Micah' => 'Mic', 'Nahum' => 'Nah', 'Habakkuk' => 'Hab', 'Zephaniah' => 'Zeph',
            'Haggai' => 'Hag', 'Zechariah' => 'Zech', 'Malachi' => 'Mal', 'Matthew' => 'Matt',
            'Mark' => 'Mark', 'Luke' => 'Luke', 'John' => 'John', 'Acts' => 'Acts',
            'Romans' => 'Rom', '1 Corinthians' => '1 Cor', '2 Corinthians' => '2 Cor', 'Galatians' => 'Gal',
            'Ephesians' => 'Eph', 'Philippians' => 'Phil', 'Colossians' => 'Col', '1 Thessalonians' => '1 Thess',
            '2 Thessalonians' => '2 Thess', '1 Timothy' => '1 Tim', '2 Timothy' => '2 Tim', 'Titus' => 'Titus',
            'Philemon' => 'Phlm', 'Hebrews' => 'Heb', 'James' => 'Jas', '1 Peter' => '1 Pet',
            '2 Peter' => '2 Pet', '1 John' => '1 John', '2 John' => '2 John', '3 John' => '3 John',
            'Jude' => 'Jude', 'Revelation' => 'Rev'
        ];

        return $shortNames[$bookName] ?? $bookName;
    }

    /**
     * Get testament for a book
     */
    private function getTestament(string $bookOsisId): string
    {
        $otBooks = ['Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth', '1Sam', '2Sam',
                   '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh', 'Esth', 'Job', 'Ps', 'Prov',
                   'Eccl', 'Song', 'Isa', 'Jer', 'Lam', 'Ezek', 'Dan', 'Hos', 'Joel', 'Amos',
                   'Obad', 'Jonah', 'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal'];

        return in_array($bookOsisId, $otBooks) ? 'Old Testament' : 'New Testament';
    }

    // API Methods

    /**
     * Get all books (API)
     */
    public function apiBooks(): JsonResponse
    {
        $books = $this->bibleService->getBooks();
        return response()->json($books);
    }

    /**
     * Get chapters for a book (API)
     */
    public function apiChapters(string $bookOsisId): JsonResponse
    {
        $chapters = $this->bibleService->getChapters($bookOsisId);

        if ($chapters->isEmpty()) {
            return response()->json(['error' => 'Book not found'], 404);
        }

        return response()->json($chapters);
    }

    /**
     * Get verses for a chapter (API)
     */
    public function apiVerses(string $bookOsisId, int $chapterNumber): JsonResponse
    {
        $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;
        $verses = $this->bibleService->getVerses($chapterOsisRef);

        if ($verses->isEmpty()) {
            return response()->json(['error' => 'Chapter not found'], 404);
        }

        return response()->json($verses);
    }

    /**
     * Search verses (API)
     */
    public function apiSearch(Request $request): JsonResponse
    {
        $searchTerm = $request->get('q', '');
        $limit = (int) $request->get('limit', 100);
        $searchType = $request->get('type', 'text');

        if (empty($searchTerm)) {
            return response()->json(['error' => 'Search term is required'], 400);
        }

        $startTime = microtime(true);

        switch ($searchType) {
            case 'strongs':
                if (!$this->bibleService->hasEnhancedFeatures()) {
                    return response()->json(['error' => 'Strong\'s search not available'], 400);
                }
                $results = $this->bibleService->searchByStrongsNumber($searchTerm, $limit);
                break;

            case 'text':
            default:
                $results = $this->bibleService->searchVerses($searchTerm, $limit);
                break;
        }

        $timeMs = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'results' => $results,
            'meta' => [
                'search_term' => $searchTerm,
                'search_type' => $searchType,
                'count' => $results->count(),
                'time_ms' => $timeMs,
                'reader_type' => $this->bibleService->getReaderType()
            ]
        ]);
    }

    /**
     * Get verse with enhanced details (API)
     */
    public function apiVerseDetails(string $bookOsisId, int $chapterNumber, int $verseNumber): JsonResponse
    {
        $verseOsisId = $bookOsisId . '.' . $chapterNumber . '.' . $verseNumber;

        if ($this->bibleService->hasEnhancedFeatures()) {
            $verseDetails = $this->bibleService->getVerseWithDetails($verseOsisId);
        } else {
            $verse = $this->bibleService->getVerseByReference($bookOsisId, $chapterNumber, $verseNumber);
            $verseDetails = $verse ? ['verse' => $verse] : [];
        }

        if (empty($verseDetails)) {
            return response()->json(['error' => 'Verse not found'], 404);
        }

        return response()->json($verseDetails);
    }

    /**
     * Get Bible capabilities (API)
     */
    public function apiCapabilities(): JsonResponse
    {
        return response()->json($this->bibleService->getCapabilities());
    }

    /**
     * Clear the last visited page from cache
     */
    public function clearLastVisited(): RedirectResponse
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        Cache::forget($cacheKey);

        return redirect()->route('bible.index')->with('success', 'Reading history cleared');
    }
}
