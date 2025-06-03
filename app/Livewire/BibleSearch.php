<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Services\BibleService;

class BibleSearch extends Component
{
    #[Url]
    public $q = '';

    #[Url]
    public $limit = 50;

    public $results = [];
    public $searchInfo = [];
    public $hasMoreResults = false;
    public $scrollToIndex = null;
    public $books = [];
    public $testamentBooks = [];
    public $currentTranslation = null;
    public $availableTranslations = [];
    public $capabilities = [];

    protected $bibleService;

    public function boot(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    public function mount()
    {
        // Get Bible data using the service
        $this->books = $this->bibleService->getBooks();
        $this->testamentBooks = $this->getTestamentBooks($this->books);
        $this->capabilities = $this->bibleService->getCapabilities();

        // Get current translation from session
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        if ($this->q) {
            $this->performSearch();
        }
    }

    public function search()
    {
        $this->limit = 50; // Reset limit when doing new search
        $this->scrollToIndex = null;
        $this->performSearch();
    }

    public function loadMore()
    {
        $this->scrollToIndex = $this->limit - 10;
        $this->limit += 50;
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

        // Perform search using the service
        $searchResults = $this->bibleService->search($this->q, $this->limit);

        if ($searchResults) {
            $this->results = $searchResults['results'] ?? [];
            $this->hasMoreResults = $searchResults['has_more_results'] ?? false;
            $this->searchInfo = [
                'count' => $searchResults['total_found'] ?? 0,
                'time_ms' => $searchResults['search_time_ms'] ?? 0
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
        session()->forget('last_visited');
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
