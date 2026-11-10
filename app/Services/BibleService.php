<?php

namespace App\Services;

use Illuminate\Support\Collection;

class BibleService
{
    /** @var array<string, DatabaseBibleReader> */
    private array $readers = [];

    private TranslationService $translationService;

    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }

    /**
     * Get the current Bible reader based on configuration
     */
    public function getCurrentReader(?string $translationKey = null): DatabaseBibleReader
    {
        $translationKey = $translationKey ?: $this->translationService->getCurrentTranslationKey();

        // One reader per translation per request: every call used to construct a new one, and
        // each constructor looked its bible_versions row up again (8 per chapter view).
        return $this->readers[$translationKey] ??= new DatabaseBibleReader($translationKey);
    }

    /**
     * Get all available translations
     */
    public function getAvailableTranslations(): Collection
    {
        return $this->translationService->getAvailableTranslations();
    }

    /**
     * Get translation configuration by key
     */
    public function getTranslation(string $translationKey): ?array
    {
        return $this->translationService->getTranslation($translationKey);
    }

    /**
     * Get current translation key
     */
    public function getCurrentTranslationKey(): string
    {
        return $this->translationService->getCurrentTranslationKey();
    }

    /**
     * Set current translation
     */
    public function setCurrentTranslation(string $translationKey): void
    {
        $this->translationService->setCurrentTranslation($translationKey);
    }

    /**
     * Get current translation configuration
     */
    public function getCurrentTranslation(): array
    {
        return $this->translationService->getCurrentTranslation();
    }

    /**
     * Check if translation exists
     */
    public function translationExists(string $translationKey): bool
    {
        return $this->translationService->translationExists($translationKey);
    }

    /**
     * Check if a book exists
     */
    /** The stored spelling of a book id, matched case-insensitively ("gen" -> "Gen"), or null. */
    public function canonicalBookId(string $bookOsisId): ?string
    {
        $book = $this->getBooks()->first(fn ($b) => strcasecmp($b['osis_id'], $bookOsisId) === 0);

        return $book['osis_id'] ?? null;
    }

    public function bookExists(string $bookOsisId): bool
    {
        $books = $this->getBooks();

        return $books->contains('osis_id', $bookOsisId);
    }

    /**
     * Check if a chapter exists for a given book
     */
    public function chapterExists(string $bookOsisId, int $chapterNumber): bool
    {
        if (! $this->bookExists($bookOsisId)) {
            return false;
        }

        $chapters = $this->getChapters($bookOsisId);

        return $chapters->contains('chapter_number', $chapterNumber);
    }

    /**
     * Delegate methods to current reader
     */
    public function getBooks(): Collection
    {
        return $this->getCurrentReader()->getBooks();
    }

    public function getBook(string $bookOsisId): ?array
    {
        $books = $this->getBooks();
        $book = $books->firstWhere('osis_id', $bookOsisId);

        return $book ?: null;
    }

    public function getChapters(string $bookOsisId): Collection
    {
        return $this->getCurrentReader()->getChapters($bookOsisId);
    }

    /** @return array{title: ?string, colophon: ?string} */
    public function getBookFrame(string $bookOsisId): array
    {
        return $this->getCurrentReader()->getBookFrame($bookOsisId);
    }

    public function getVerses(string $chapterOsisRef): Collection
    {
        return $this->getCurrentReader()->getVerses($chapterOsisRef);
    }

    public function getVersesParagraphStyle(string $chapterOsisRef): Collection
    {
        return $this->getCurrentReader()->getVersesParagraphStyle($chapterOsisRef);
    }

    public function getVerseText(string $verseOsisId): string
    {
        return $this->getCurrentReader()->getVerseText($verseOsisId);
    }

    public function searchVerses(string $searchTerm, int $limit = 100): Collection
    {
        return $this->getCurrentReader()->searchVerses($searchTerm, $limit);
    }

    /**
     * Search for verses and return formatted results for Livewire
     */
    public function search(string $searchTerm, int $limit = 100): array
    {
        $startTime = microtime(true);

        $results = $this->searchVerses($searchTerm, $limit);

        $endTime = microtime(true);
        $searchTimeMs = ($endTime - $startTime) * 1000;

        // Format results for Livewire component
        $formattedResults = $results->map(function ($result) {
            return [
                'book_osis_id' => $result['book_osis_id'] ?? '',
                'chapter' => $result['chapter'] ?? 1,
                // The reader returns verse_number; reading 'verse' made every result verse 1.
                'verse' => $result['verse_number'] ?? $result['verse'] ?? 1,
                'reference' => $result['reference'] ?? '',
                'text' => $result['text'] ?? '',
            ];
        })->toArray();

        return [
            'results' => $formattedResults,
            'total_found' => count($formattedResults),
            'has_more_results' => count($formattedResults) >= $limit,
            'search_time_ms' => round($searchTimeMs, 2),
        ];
    }

    public function getVerseByReference(string $bookOsisId, int $chapter, int $verse): ?array
    {
        return $this->getCurrentReader()->getVerseByReference($bookOsisId, $chapter, $verse);
    }

    /**
     * Enhanced database-specific methods (only available when using database reader)
     */
    public function getVerseStrongsData(string $verseOsisId): Collection
    {
        return $this->getCurrentReader()->getVerseStrongsData($verseOsisId);
    }

    public function getTranslatorChanges(string $verseOsisId): Collection
    {
        return $this->getCurrentReader()->getTranslatorChanges($verseOsisId);
    }

    public function getDivineNames(string $verseOsisId): Collection
    {
        return $this->getCurrentReader()->getDivineNames($verseOsisId);
    }

    public function getStudyNotes(string $verseOsisId): Collection
    {
        return $this->getCurrentReader()->getStudyNotes($verseOsisId);
    }

    public function searchByStrongsNumber(string $strongsNumber, int $limit = 100): Collection
    {
        return $this->getCurrentReader()->searchByStrongsNumber($strongsNumber, $limit);
    }

    public function getVerseWithDetails(string $verseOsisId): array
    {
        return $this->getCurrentReader()->getVerseWithDetails($verseOsisId);
    }

    /**
     * Check if enhanced features are available
     */
    public function hasEnhancedFeatures(): bool
    {
        return true; // the database reader is the only reader
    }

    /**
     * Get reader capabilities
     */
    public function getCapabilities(): array
    {
        $baseCapabilities = [
            'books',
            'chapters',
            'verses',
            'search',
            'verse_references',
            'bible_info',
        ];

        $enhancedCapabilities = [
            'strongs_concordance',
            'translator_changes',
            'divine_names',
            'study_notes',
            'full_text_search',
            'morphological_analysis',
        ];

        return [
            'base' => $baseCapabilities,
            'enhanced' => $this->hasEnhancedFeatures() ? $enhancedCapabilities : [],
            'reader_type' => 'database',
        ];
    }

    /**
     * OSIS source file for the current translation (one file holds every book).
     *
     * @throws \InvalidArgumentException when the translation key is malformed or not projected
     */
    public function getXmlPath(string $bookOsisId): string
    {
        return \App\Services\Osis\OsisSourcePath::for($this->getCurrentTranslation()['key'] ?? null);
    }
}
