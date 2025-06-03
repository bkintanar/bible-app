<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
     * Display the Livewire Bible home page with list of books or redirect to last visited page
     */
    public function livewireIndex(Request $request)
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
                        return redirect()->route('bible.book', ['bookOsisId' => $lastPage['parameters']['bookOsisId']]);
                    } elseif ($lastPage['route'] === 'bible.chapter' && isset($lastPage['parameters']['bookOsisId'], $lastPage['parameters']['chapterNumber'])) {
                        return redirect()->route('bible.chapter', [
                            'bookOsisId' => $lastPage['parameters']['bookOsisId'],
                            'chapterNumber' => $lastPage['parameters']['chapterNumber']
                        ]);
                    }
                } catch (\Exception $e) {
                    // If redirect fails, redirect to Genesis 1 as default
                    return redirect()->route('bible.chapter', ['bookOsisId' => 'Gen', 'chapterNumber' => 1]);
                }
            } else {
                // No cached page found, redirect to Genesis 1 as default starting point
                return redirect()->route('bible.chapter', ['bookOsisId' => 'Gen', 'chapterNumber' => 1]);
            }
        }

        // Force index or fallback - show the Livewire index page
        return view('livewire-index');
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
            return response()->json([
                'success' => false,
                'message' => 'Search term is required'
            ], 400);
        }

        $startTime = microtime(true);

        switch ($searchType) {
            case 'strongs':
                if (!$this->bibleService->hasEnhancedFeatures()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Strong\'s search not available'
                    ], 400);
                }
                $results = $this->bibleService->searchByStrongsNumber($searchTerm, $limit);
                break;

            case 'text':
            default:
                $results = $this->bibleService->searchVerses($searchTerm, $limit + 1);
                $hasMoreResults = $results->count() > $limit;
                if ($hasMoreResults) {
                    $results = $results->take($limit);
                }
                break;
        }

        $timeMs = round((microtime(true) - $startTime) * 1000, 2);

        // Format results to match what Livewire expects
        $formattedResults = $results->map(function ($result) {
            return [
                'book_osis_id' => $result['book_osis_id'] ?? '',
                'chapter' => $result['chapter'] ?? 1,
                'verse' => $result['verse'] ?? 1,
                'reference' => $result['reference'] ?? '',
                'text' => $result['text'] ?? '',
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $formattedResults,
                'total_found' => count($formattedResults),
                'has_more_results' => $hasMoreResults ?? false,
                'search_time_ms' => $timeMs,
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
        return response()->json([
            'success' => true,
            'data' => $this->bibleService->getCapabilities()
        ]);
    }

    /**
     * Clear the last visited page from cache
     */
    public function clearLastVisited(): RedirectResponse
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        Cache::forget($cacheKey);

        return redirect()->route('bible.index');
    }

    /**
     * Display the Livewire search page
     */
    public function livewireSearch()
    {
        return view('livewire-search');
    }

    /**
     * Display a specific book chapter using Livewire
     */
    public function livewireChapter(string $bookOsisId, int $chapterNumber)
    {
        // Store this page as the last visited
        $this->storeLastVisitedPage('bible.chapter', [
            'bookOsisId' => $bookOsisId,
            'chapterNumber' => $chapterNumber
        ]);

        return view('livewire-chapter', compact('bookOsisId', 'chapterNumber'));
    }

    /**
     * Display a specific book using Livewire
     */
    public function livewireBook(string $bookOsisId)
    {
        // Store this page as the last visited
        $this->storeLastVisitedPage('bible.book', [
            'bookOsisId' => $bookOsisId
        ]);

        return view('livewire-book', compact('bookOsisId'));
    }
}
