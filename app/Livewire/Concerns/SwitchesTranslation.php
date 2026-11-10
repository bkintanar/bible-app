<?php

namespace App\Livewire\Concerns;

/**
 * Translation switching and the testament grouping, shared by the reader components (they each had
 * a copy; two compared the testament with 'OT' while the stored name is 'Old Testament', so they
 * filed every book under the New Testament).
 *
 * The using component provides $bibleService, the public $currentTranslation and
 * $availableTranslations, and translationSwitched() to reload what it derives from the translation.
 */
trait SwitchesTranslation
{
    public function switchTranslation($translationKey): void
    {
        if (! is_string($translationKey)) {
            return; // a browser-supplied argument; the service ignores unknown keys itself
        }

        $this->bibleService->setCurrentTranslation($translationKey);
        $this->currentTranslation = $this->bibleService->getCurrentTranslation();
        $this->availableTranslations = $this->bibleService->getAvailableTranslations();

        $this->translationSwitched();
    }

    /** Reload whatever the component derives from the current translation. */
    abstract protected function translationSwitched(): void;

    /**
     * @return array{oldTestament: list<array>, newTestament: list<array>}
     */
    protected function getTestamentBooks($books): array
    {
        $grouped = ['oldTestament' => [], 'newTestament' => []];

        foreach ($books as $book) {
            match ($book['testament'] ?? null) {
                'Old Testament' => $grouped['oldTestament'][] = $book,
                'New Testament' => $grouped['newTestament'][] = $book,
                default => null, // e.g. Apocrypha: not offered
            };
        }

        return $grouped;
    }
}
