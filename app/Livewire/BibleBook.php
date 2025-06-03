<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\BibleService;
use Illuminate\Support\Facades\Cache;

class BibleBook extends Component
{
    public $bookOsisId;
    public $currentBook;
    public $chapters = [];
    public $books = [];
    public $testamentBooks = [];
    public $currentTranslation = null;
    public $availableTranslations = [];
    public $capabilities = [];

    // UI state
    public $searchQuery = '';
    public $showSearch = false;

    protected $bibleService;

    public function boot(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    public function mount($bookOsisId)
    {
        $this->bookOsisId = $bookOsisId;

        // Get Bible data using the service
        $this->books = $this->bibleService->getBooks();
        $this->currentBook = $this->books->first(function ($book) use ($bookOsisId) {
            return strtolower($book['osis_id']) === strtolower($bookOsisId);
        });

        if (!$this->currentBook) {
            dd([
                'error' => 'Book not found',
                'requested_book_osis_id' => $bookOsisId,
                'available_books' => $this->books->pluck('osis_id', 'name')->toArray(),
                'database_query_attempted' => "Looking for book with osis_id matching: {$bookOsisId}",
                'service_method' => 'BibleService::getBooks()',
                'books_count' => $this->books->count(),
                'db_path' => database_path('bible_app.sqlite'),
            ]);
        }

        // Store this page as the last visited
        $this->storeLastVisitedPage();

        // Get chapters for this book
        $this->chapters = $this->bibleService->getChapters($bookOsisId);

        if ($this->chapters->isEmpty()) {
            dd([
                'error' => 'No chapters found for this book',
                'book_osis_id' => $bookOsisId,
                'book_name' => $this->currentBook['name'] ?? 'Unknown',
                'database_query_attempted' => "Looking for chapters in book: {$bookOsisId}",
                'service_method' => 'BibleService::getChapters()',
                'chapters_result' => $this->chapters,
                'current_translation' => $this->bibleService->getCurrentTranslation(),
                'db_path' => database_path('bible_app.sqlite'),
            ]);
        }

        $this->testamentBooks = $this->getTestamentBooks($this->books);
        $this->capabilities = $this->bibleService->getCapabilities();

        // Get current translation from session
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();
    }

    public function switchTranslation($translationKey)
    {
        // Switch translation using the service
        $this->bibleService->setCurrentTranslation($translationKey);
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();

        // Refresh books data
        $this->books = $this->bibleService->getBooks();
        $this->testamentBooks = $this->getTestamentBooks($this->books);
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        // Reload chapters with new translation
        $this->chapters = $this->bibleService->getChapters($this->bookOsisId);

        // Emit an event to notify other components of the translation change
        $this->dispatch('translation-changed', $translationKey);
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

    public function clearLastVisited()
    {
        session()->forget('last_visited');
    }

    private function storeLastVisitedPage(): void
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        $pageData = [
            'route' => 'bible.book',
            'parameters' => [
                'bookOsisId' => $this->bookOsisId,
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
            if ($book['testament'] === 'OT') {
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

    public function getPopularChapters(): array
    {
        // Return popular chapters for specific books
        return match($this->bookOsisId) {
            'Ps' => [23, 91, 139, 1, 46, 121],
            'Prov' => [31, 3, 27, 16, 1, 8],
            'John' => [3, 14, 15, 1, 6, 17],
            'Rom' => [8, 12, 3, 6, 1, 5],
            '1Cor' => [13, 15, 10, 2, 7, 12],
            'Matt' => [5, 6, 7, 28, 1, 24],
            'Gen' => [1, 2, 3, 22, 28, 37],
            'Isa' => [53, 40, 55, 9, 61, 6],
            default => []
        };
    }

    public function render()
    {
        return view('livewire.bible-book');
    }
}
