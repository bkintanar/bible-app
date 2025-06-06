<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Services\Contracts\BibleReaderInterface;

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
    public function getCurrentReader(?string $translationKey = null): ?BibleReaderInterface
    {
        $translationKey = $translationKey ?: $this->translationService->getCurrentTranslationKey();
        $readerType = Config::get('bible.reader_type', 'database'); // 'xml' or 'database'

        switch ($readerType) {
            case 'database':
                return new DatabaseBibleReader($translationKey);

            case 'xml':
                return $this->translationService->createReader($translationKey);

            default:
                return new DatabaseBibleReader($translationKey);
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
     * Check if a book exists
     */
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
     * Get current reader type (xml or database)
     */
    public function getReaderType(): string
    {
        return Config::get('bible.reader_type', 'database');
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

    public function getBook(string $bookOsisId): ?array
    {
        $books = $this->getBooks();
        $book = $books->firstWhere('osis_id', $bookOsisId);
        return $book ? $book->toArray() : null;
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
                'verse' => $result['verse'] ?? 1,
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
            'reader_type' => $this->getReaderType(),
        ];
    }

    /**
     * Get chapter title for a specific chapter
     */
    public function getChapterTitle(string $chapterOsisRef): ?array
    {
        // Only try to get titles from database if we're using the database reader
        if ($this->getReaderType() !== 'database') {
            return null;
        }

        // Get the first verse of the chapter to find associated title
        $firstVerse = $this->getVerses($chapterOsisRef)->first();
        if (! $firstVerse || ! isset($firstVerse['id'])) {
            return null;
        }

        $title = DB::table('titles')
            ->where('verse_id', $firstVerse['id'])
            ->where('title_type', 'chapter')
            ->where('placement', 'before')
            ->first();

        return $title ? (array) $title : null;
    }

    /**
     * Get paragraph information for a chapter
     */
    public function getChapterParagraphs(string $chapterOsisRef): Collection
    {
        // Only try to get paragraphs from database if we're using the database reader
        if ($this->getReaderType() !== 'database') {
            return collect();
        }

        // Get chapter info
        list($bookOsisId, $chapterNumber) = explode('.', $chapterOsisRef);

        $chapterInfo = DB::table('chapters')
            ->join('books', 'chapters.book_id', '=', 'books.id')
            ->where('books.osis_id', $bookOsisId)
            ->where('chapters.chapter_number', $chapterNumber)
            ->first();

        if (! $chapterInfo) {
            return collect();
        }

        return DB::table('paragraphs')
            ->where('chapter_id', $chapterInfo->id)
            ->orderBy('start_verse_id')
            ->get();
    }

    /**
     * Get XML file path for a given book
     */
    public function getXmlPath(string $bookOsisId): string
    {
        $currentTranslation = $this->getCurrentTranslation();
        $translationKey = $currentTranslation['key'] ?? 'asv';

        // For now, we'll use the full OSIS XML files in assets directory
        // In a real implementation, you might want to split these by book
        return base_path("assets/{$translationKey}.osis.xml");
    }
}
