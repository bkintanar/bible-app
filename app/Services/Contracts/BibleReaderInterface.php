<?php

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

interface BibleReaderInterface
{
    /**
     * Get all books in the Bible
     */
    public function getBooks(): Collection;

    /**
     * Get chapters for a specific book
     */
    public function getChapters(string $bookOsisId): Collection;

    /**
     * Get verses for a specific chapter
     */
    public function getVerses(string $chapterOsisRef): Collection;

    /**
     * Get verses for a specific chapter grouped by paragraphs
     */
    public function getVersesParagraphStyle(string $chapterOsisRef): Collection;

    /**
     * Get the text content of a specific verse
     */
    public function getVerseText(string $verseOsisId): string;

    /**
     * Search for verses containing specific text
     */
    public function searchVerses(string $searchTerm, int $limit = 100): Collection;

    /**
     * Get Bible metadata
     */
    public function getBibleInfo(): array;

    /**
     * Parse a verse reference and return verse details if valid
     */
    public function parseVerseReference(string $input): ?array;

    /**
     * Get a specific verse by reference
     */
    public function getVerseByReference(string $bookOsisId, int $chapter, int $verse): ?array;
}
