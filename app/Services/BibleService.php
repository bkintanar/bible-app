<?php

namespace App\Services;

use App\Services\Contracts\BibleReaderInterface;
use App\Services\OsisReader;
use App\Services\DatabaseBibleReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class BibleService
{
    private TranslationService $translationService;

    public function __construct(TranslationService $translationService)
    {
        $this->translationService = $translationService;
    }

    /**
     * Get the current Bible reader based on configuration
     */
    public function getCurrentReader(string $translationKey = null): ?BibleReaderInterface
    {
        $translationKey = $translationKey ?: $this->translationService->getCurrentTranslationKey();
        $readerType = Config::get('bible.reader_type', 'xml'); // 'xml' or 'database'

        switch ($readerType) {
            case 'database':
                return new DatabaseBibleReader($translationKey);

            case 'xml':
            default:
                return $this->translationService->createReader($translationKey);
        }
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
     * Get current reader type (xml or database)
     */
    public function getReaderType(): string
    {
        return Config::get('bible.reader_type', 'xml');
    }

    /**
     * Set reader type
     */
    public function setReaderType(string $readerType): void
    {
        if (in_array($readerType, ['xml', 'database'])) {
            Config::set('bible.reader_type', $readerType);
        }
    }

    /**
     * Delegate methods to current reader
     */

    public function getBooks(): Collection
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->getBooks() : collect();
    }

    public function getChapters(string $bookOsisId): Collection
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->getChapters($bookOsisId) : collect();
    }

    public function getVerses(string $chapterOsisRef): Collection
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->getVerses($chapterOsisRef) : collect();
    }

    public function getVersesParagraphStyle(string $chapterOsisRef): Collection
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->getVersesParagraphStyle($chapterOsisRef) : collect();
    }

    public function getVerseText(string $verseOsisId): string
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->getVerseText($verseOsisId) : '';
    }

    public function searchVerses(string $searchTerm, int $limit = 100): Collection
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->searchVerses($searchTerm, $limit) : collect();
    }

    public function getBibleInfo(): array
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->getBibleInfo() : [];
    }

    public function parseVerseReference(string $input): ?array
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->parseVerseReference($input) : null;
    }

    public function getVerseByReference(string $bookOsisId, int $chapter, int $verse): ?array
    {
        $reader = $this->getCurrentReader();
        return $reader ? $reader->getVerseByReference($bookOsisId, $chapter, $verse) : null;
    }

    /**
     * Enhanced database-specific methods (only available when using database reader)
     */

    public function getVerseStrongsData(string $verseOsisId): Collection
    {
        $reader = $this->getCurrentReader();

        if ($reader instanceof DatabaseBibleReader) {
            return $reader->getVerseStrongsData($verseOsisId);
        }

        return collect();
    }

    public function getTranslatorChanges(string $verseOsisId): Collection
    {
        $reader = $this->getCurrentReader();

        if ($reader instanceof DatabaseBibleReader) {
            return $reader->getTranslatorChanges($verseOsisId);
        }

        return collect();
    }

    public function getDivineNames(string $verseOsisId): Collection
    {
        $reader = $this->getCurrentReader();

        if ($reader instanceof DatabaseBibleReader) {
            return $reader->getDivineNames($verseOsisId);
        }

        return collect();
    }

    public function getStudyNotes(string $verseOsisId): Collection
    {
        $reader = $this->getCurrentReader();

        if ($reader instanceof DatabaseBibleReader) {
            return $reader->getStudyNotes($verseOsisId);
        }

        return collect();
    }

    public function searchByStrongsNumber(string $strongsNumber, int $limit = 100): Collection
    {
        $reader = $this->getCurrentReader();

        if ($reader instanceof DatabaseBibleReader) {
            return $reader->searchByStrongsNumber($strongsNumber, $limit);
        }

        return collect();
    }

    public function getVerseWithDetails(string $verseOsisId): array
    {
        $reader = $this->getCurrentReader();

        if ($reader instanceof DatabaseBibleReader) {
            return $reader->getVerseWithDetails($verseOsisId);
        }

        // Fallback for XML reader - basic verse info only
        $verse = $this->getVerseByReference(
            explode('.', $verseOsisId)[0],
            (int) explode('.', $verseOsisId)[1],
            (int) explode('.', $verseOsisId)[2]
        );

        return $verse ? ['verse' => $verse] : [];
    }

    /**
     * Check if enhanced features are available
     */
    public function hasEnhancedFeatures(): bool
    {
        return $this->getReaderType() === 'database';
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
            'bible_info'
        ];

        $enhancedCapabilities = [
            'strongs_concordance',
            'translator_changes',
            'divine_names',
            'study_notes',
            'full_text_search',
            'morphological_analysis'
        ];

        return [
            'base' => $baseCapabilities,
            'enhanced' => $this->hasEnhancedFeatures() ? $enhancedCapabilities : [],
            'reader_type' => $this->getReaderType()
        ];
    }
}
