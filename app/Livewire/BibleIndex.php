<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\BibleService;

class BibleIndex extends Component
{
    public $books = [];
    public $testamentBooks = [];
    public $currentTranslation = null;
    public $availableTranslations = [];
    public $capabilities = [];
    public $lastVisited = null;

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
        $this->lastVisited = session('last_visited');
    }

    public function switchTranslation($translationKey)
    {
        // Switch translation using the service
        $this->bibleService->setCurrentTranslation($translationKey);
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();

        // Refresh the books and other data with the new translation
        $this->books = $this->bibleService->getBooks();
        $this->testamentBooks = $this->getTestamentBooks($this->books);
        $this->capabilities = $this->bibleService->getCapabilities();
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        // Emit an event to notify other components of the translation change
        $this->dispatch('translation-changed', $translationKey);
    }

    public function clearLastVisited()
    {
        session()->forget('last_visited');
        $this->lastVisited = null;
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

    public function render()
    {
        return view('livewire.bible-index');
    }
}
