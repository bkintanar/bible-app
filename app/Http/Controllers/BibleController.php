<?php

namespace App\Http\Controllers;

use App\Services\OsisReader;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BibleController extends Controller
{
    private TranslationService $translationService;

    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }

    /**
     * Display the Bible home page with list of books
     */
    public function index(): View
    {
        $osisReader = $this->translationService->getCurrentReader();

        if (!$osisReader) {
            abort(500, 'Bible translation not available');
        }

        $bibleInfo = $osisReader->getBibleInfo();
        $books = $osisReader->getBooks();
        $currentTranslation = $this->translationService->getCurrentTranslation();
        $availableTranslations = $this->translationService->getAvailableTranslations();

        return view('bible.index', compact('bibleInfo', 'books', 'currentTranslation', 'availableTranslations'));
    }

    /**
     * Display chapters for a specific book
     */
    public function book(string $bookOsisId): View
    {
        $osisReader = $this->translationService->getCurrentReader();

        if (!$osisReader) {
            abort(500, 'Bible translation not available');
        }

        $books = $osisReader->getBooks();
        $currentBook = $books->firstWhere('osis_id', $bookOsisId);

        if (!$currentBook) {
            abort(404, 'Book not found');
        }

        $chapters = $osisReader->getChapters($bookOsisId);
        $currentTranslation = $this->translationService->getCurrentTranslation();
        $availableTranslations = $this->translationService->getAvailableTranslations();

        return view('bible.book', compact('currentBook', 'chapters', 'books', 'currentTranslation', 'availableTranslations'));
    }

    /**
     * Display verses for a specific chapter
     */
    public function chapter(string $bookOsisId, int $chapterNumber, Request $request): View
    {
        $osisReader = $this->translationService->getCurrentReader();

        if (!$osisReader) {
            abort(500, 'Bible translation not available');
        }

        $books = $osisReader->getBooks();
        $currentBook = $books->firstWhere('osis_id', $bookOsisId);

        if (!$currentBook) {
            abort(404, 'Book not found');
        }

        $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;
        $formatStyle = $request->get('style', 'paragraph'); // Default to paragraph style

        if ($formatStyle === 'verse') {
            // Line-by-line verse formatting
            $verses = $osisReader->getVerses($chapterOsisRef);
            $paragraphs = null;

            if ($verses->isEmpty()) {
                abort(404, 'Chapter not found');
            }
        } else {
            // Paragraph-style formatting
            $paragraphs = $osisReader->getVersesParagraphStyle($chapterOsisRef);
            $verses = null;

            if ($paragraphs->isEmpty()) {
                abort(404, 'Chapter not found');
            }
        }

        $chapters = $osisReader->getChapters($bookOsisId);
        $currentTranslation = $this->translationService->getCurrentTranslation();
        $availableTranslations = $this->translationService->getAvailableTranslations();

        return view('bible.chapter', compact('currentBook', 'chapterNumber', 'verses', 'paragraphs', 'chapters', 'books', 'formatStyle', 'currentTranslation', 'availableTranslations'));
    }

    /**
     * Search for verses or handle verse references
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'limit' => 'integer|min:10|max:500'
        ]);

        $searchTerm = $request->input('q');
        $limit = $request->input('limit', 100);

        $osisReader = $this->translationService->getCurrentReader();

        if (!$osisReader) {
            abort(500, 'Bible translation not available');
        }

        // First, check if this is a verse reference (e.g., "Acts 2:38")
        $verseRef = $osisReader->parseVerseReference($searchTerm);

        if ($verseRef) {
            // This is a verse reference - redirect to the appropriate page
            if ($verseRef['type'] === 'verse') {
                // Redirect to the specific chapter with verse anchor
                return redirect()->route('bible.chapter', [
                    $verseRef['book_osis_id'],
                    $verseRef['chapter']
                ])->with('highlightVerse', $verseRef['verse']);
            } elseif ($verseRef['type'] === 'verse_range') {
                // Redirect to chapter with verse range highlighting
                return redirect()->route('bible.chapter', [
                    $verseRef['book_osis_id'],
                    $verseRef['chapter']
                ])->with([
                    'highlightVerseRange' => [
                        'start' => $verseRef['start_verse'],
                        'end' => $verseRef['end_verse']
                    ]
                ]);
            } else {
                // Chapter reference - redirect to chapter
                return redirect()->route('bible.chapter', [
                    $verseRef['book_osis_id'],
                    $verseRef['chapter']
                ]);
            }
        }

        // Not a verse reference, proceed with text search
        try {
            $results = $osisReader->searchVerses($searchTerm, $limit);
            $books = $osisReader->getBooks();

            // Group results by book for better display
            $groupedResults = $results->groupBy('book_id')->map(function ($bookResults, $bookId) use ($books) {
                $book = $books->firstWhere('osis_id', $bookId);
                return [
                    'book' => $book,
                    'verses' => $bookResults
                ];
            });

            $totalFound = $results->count();
            $hasMoreResults = $totalFound >= $limit;
            $currentTranslation = $this->translationService->getCurrentTranslation();
            $availableTranslations = $this->translationService->getAvailableTranslations();

            return view('bible.search', compact('searchTerm', 'groupedResults', 'books', 'totalFound', 'hasMoreResults', 'limit', 'currentTranslation', 'availableTranslations'));

        } catch (\Exception $e) {
            // Log the error and show a user-friendly message
            \Log::error('Bible search error: ' . $e->getMessage());

            $currentTranslation = $this->translationService->getCurrentTranslation();
            $availableTranslations = $this->translationService->getAvailableTranslations();

            return view('bible.search', [
                'searchTerm' => $searchTerm,
                'groupedResults' => collect(),
                'books' => $osisReader->getBooks(),
                'error' => 'Search temporarily unavailable. Please try a more specific search term.',
                'totalFound' => 0,
                'hasMoreResults' => false,
                'limit' => $limit,
                'currentTranslation' => $currentTranslation,
                'availableTranslations' => $availableTranslations
            ]);
        }
    }

    /**
     * Switch to a different translation
     */
    public function switchTranslation(Request $request): RedirectResponse
    {
        $request->validate([
            'translation' => 'required|string'
        ]);

        $translationKey = $request->input('translation');

        if ($this->translationService->translationExists($translationKey)) {
            $this->translationService->setCurrentTranslation($translationKey);

            // Redirect back to the same page if possible
            return redirect()->back()->with('success', 'Translation switched successfully');
        }

        return redirect()->back()->with('error', 'Translation not available');
    }

    /**
     * API endpoint to get books
     */
    public function apiBooks()
    {
        $osisReader = $this->translationService->getCurrentReader();

        if (!$osisReader) {
            return response()->json(['error' => 'Translation not available'], 500);
        }

        return response()->json($osisReader->getBooks());
    }

    /**
     * API endpoint to get chapters
     */
    public function apiChapters(string $bookOsisId)
    {
        $osisReader = $this->translationService->getCurrentReader();

        if (!$osisReader) {
            return response()->json(['error' => 'Translation not available'], 500);
        }

        return response()->json($osisReader->getChapters($bookOsisId));
    }

    /**
     * API endpoint to get verses
     */
    public function apiVerses(string $bookOsisId, int $chapterNumber)
    {
        $osisReader = $this->translationService->getCurrentReader();

        if (!$osisReader) {
            return response()->json(['error' => 'Translation not available'], 500);
        }

        $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;
        return response()->json($osisReader->getVerses($chapterOsisRef));
    }
}
