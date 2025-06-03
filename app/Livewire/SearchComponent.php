<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;

class SearchComponent extends Component
{
    #[Url]
    public $query = '';

    #[Url]
    public $limit = 50;

    public $results = [];
    public $searchInfo = [];
    public $hasMoreResults = false;
    public $scrollToIndex = null;

    public function mount()
    {
        if ($this->query) {
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
        $this->scrollToIndex = $this->limit - 10; // Set scroll position before loading more
        $this->limit += 50;
        $this->performSearch();
    }

    private function performSearch()
    {
        if (empty($this->query)) {
            $this->results = [];
            $this->searchInfo = [];
            $this->hasMoreResults = false;
            return;
        }

        // Mock search logic - replace with your actual search implementation
        $allResults = $this->mockSearchResults();

        $this->results = array_slice($allResults, 0, $this->limit);
        $this->hasMoreResults = count($allResults) > $this->limit;
        $this->searchInfo = [
            'count' => count($allResults),
            'time_ms' => rand(10, 100)
        ];
    }

    private function mockSearchResults()
    {
        // Mock data - replace with your actual search logic
        $mockResults = [];
        for ($i = 1; $i <= 250; $i++) {
            $mockResults[] = [
                'osis_id' => "verse_$i",
                'reference' => "Book {$i}:1",
                'book_osis_id' => 'book1',
                'chapter' => 1,
                'text' => "This is mock search result #$i containing the word <mark>{$this->query}</mark> for testing purposes."
            ];
        }
        return $mockResults;
    }

    public function render()
    {
        return view('livewire.search-component');
    }
}
