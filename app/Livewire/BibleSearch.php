<?php

namespace App\Livewire;

use App\Support\ResumePosition;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use App\Services\BibleService;

class BibleSearch extends Component
{
    /** Results per page, and the most "load more" may reach (each step is one FTS query). */
    public const PAGE = 50;

    public const MAX_LIMIT = 250;

    #[Url]
    public $q = '';

    #[Url]
    public $limit = self::PAGE;

    #[Locked]
    public $results = [];
    #[Locked]
    public $searchInfo = [];
    #[Locked]
    public $hasMoreResults = false;
    #[Locked]
    public $books = [];
    #[Locked]
    public $testamentBooks = [];
    #[Locked]
    public $currentTranslation = null;
    #[Locked]
    public $availableTranslations = [];
    #[Locked]
    public $capabilities = [];

    protected $bibleService;

    public function boot(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    public function mount()
    {
        // Old links used ?query= (the mock search component's parameter).
        $legacy = request()->query('query');
        if (($this->q === '' || $this->q === null) && is_string($legacy)) {
            $this->q = $legacy;
        }

        // Both come from the URL: ?q[]=x or ?limit=abc must not reach the search as-is.
        $this->q = is_string($this->q) ? mb_substr(trim($this->q), 0, 200) : '';
        $this->limit = self::clampLimit($this->limit);

        // Get Bible data using the service
        $this->books = $this->bibleService->getBooks();
        $this->testamentBooks = $this->getTestamentBooks($this->books);
        $this->capabilities = $this->bibleService->getCapabilities();

        // Get current translation from session
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        if ($this->q !== '') {
            $this->performSearch();
        }
    }

    public static function clampLimit(mixed $limit): int
    {
        return is_numeric($limit) ? max(1, min(self::MAX_LIMIT, (int) $limit)) : self::PAGE;
    }

    public function updatedLimit($value): void
    {
        $this->limit = self::clampLimit($value);
    }

    public function updatedQ($value): void
    {
        $this->q = is_string($value) ? mb_substr(trim($value), 0, 200) : '';
    }

    public function search()
    {
        $this->limit = self::PAGE; // a new search starts from the first page
        $this->performSearch();
    }

    public function loadMore()
    {
        $this->limit = self::clampLimit($this->limit + self::PAGE);
        $this->performSearch();
    }

    private function performSearch()
    {
        if (empty($this->q)) {
            $this->results = [];
            $this->searchInfo = [];
            $this->hasMoreResults = false;
            return;
        }

        $this->resetErrorBag('q');

        if (preg_match('/[\x00-\x1F\x7F]/u', $this->q) || ! mb_check_encoding($this->q, 'UTF-8')) {
            $this->addError('q', 'Please remove control characters from your search.');
            $this->results = [];
            $this->searchInfo = [];
            $this->hasMoreResults = false;

            return;
        }

        try {
            $searchResults = $this->bibleService->search($this->q, $this->limit);
        } catch (\InvalidArgumentException $e) {
            $this->addError('q', $e->getMessage());
            $this->results = [];
            $this->searchInfo = [];
            $this->hasMoreResults = false;

            return;
        }

        if ($searchResults) {
            $this->results = $searchResults['results'] ?? [];
            $this->hasMoreResults = ($searchResults['has_more_results'] ?? false) && $this->limit < self::MAX_LIMIT;
            $this->searchInfo = [
                'count' => $searchResults['total_found'] ?? 0,
                'time_ms' => $searchResults['search_time_ms'] ?? 0,
            ];
        } else {
            $this->results = [];
            $this->searchInfo = ['count' => 0, 'time_ms' => 0];
            $this->hasMoreResults = false;
        }
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

        // Re-search with new translation if we have a query
        if ($this->q) {
            $this->performSearch();
        }

        // Emit an event to notify other components of the translation change
        $this->dispatch('translation-changed', $translationKey);
    }

    public function clearLastVisited()
    {
        ResumePosition::forget();
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

    public function render()
    {
        return view('livewire.bible-search');
    }
}
