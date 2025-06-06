<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Verse;
use App\Models\Chapter;
use App\Models\Paragraph;
use App\Models\BibleVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\Contracts\BibleReaderInterface;

class DatabaseBibleReader implements BibleReaderInterface
{
    private string $versionKey;
    private ?BibleVersion $bibleVersion = null;

    public function __construct(string $versionKey = 'kjv')
    {
        $this->versionKey = $versionKey;
        $this->bibleVersion = $this->getBibleVersion();
    }

    /**
     * Get bible version from database
     */
    private function getBibleVersion(): ?BibleVersion
    {
        $cacheKey = "bible_version_model_{$this->versionKey}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $bibleVersion = BibleVersion::where('abbreviation', strtoupper($this->versionKey))->first();
        Cache::put($cacheKey, $bibleVersion, 3600);

        return $bibleVersion;
    }

    /**
     * Get all books in the Bible
     */
    public function getBooks(): Collection
    {
        $cacheKey = "books_{$this->versionKey}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $books = Book::with('bookGroup')
            ->canonical()
            ->ordered()
            ->get()
            ->map(function ($book) {
                return [
                    'osis_id' => $book->osis_id,
                    'name' => $book->name,
                    'short_name' => $book->short_name ?? $book->name,
                    'testament' => $book->bookGroup->name ?? 'Unknown',
                    'book_order' => $book->sort_order,
                ];
            });

        Cache::put($cacheKey, $books, 3600);

        return $books;
    }

    /**
     * Get chapters for a specific book
     */
    public function getChapters(string $bookOsisId): Collection
    {
        $normalizedBookOsisId = strtoupper($bookOsisId);
        $cacheKey = "chapters_{$normalizedBookOsisId}_{$this->versionKey}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (! $this->bibleVersion) {
            $emptyCollection = collect();
            Cache::put($cacheKey, $emptyCollection, 3600);
            return $emptyCollection;
        }

        $chapters = Chapter::with('book')
            ->whereHas('book', function ($query) use ($bookOsisId) {
                $query->whereRaw('UPPER(osis_id) = UPPER(?)', [$bookOsisId]);
            })
            ->where('version_id', $this->bibleVersion->id)
            ->withCount('verses')
            ->orderBy('chapter_number')
            ->get()
            ->map(function ($chapter) {
                return [
                    'osis_ref' => $chapter->osis_id,
                    'chapter_number' => $chapter->chapter_number,
                    'verse_count' => $chapter->verses_count,
                ];
            });

        Cache::put($cacheKey, $chapters, 3600);

        return $chapters;
    }

    /**
     * Get verses for a specific chapter
     */
    public function getVerses(string $chapterOsisRef): Collection
    {
        if (! $this->bibleVersion) {
            return collect();
        }

        // Convert to proper case for database lookup
        $properCaseRef = ucfirst(strtolower($chapterOsisRef));
        if (strpos($properCaseRef, '.') !== false) {
            $parts = explode('.', $properCaseRef);
            $properCaseRef = ucfirst($parts[0]) . '.' . $parts[1];
        }

        $verses = Verse::with('titles')
            ->whereHas('chapter', function ($query) use ($properCaseRef) {
                $query->where('osis_id', $properCaseRef)
                    ->where('version_id', $this->bibleVersion->id);
            })
            ->orderBy('verse_number')
            ->get();

        return $verses->map(function ($verse) {
            // Separate chapter titles from verse titles
            $chapterTitleHtml = '';
            $verseTitleHtml = '';

            foreach ($verse->titles as $title) {
                if ($title->placement === 'before') {
                    $titleClass = match($title->title_type) {
                        'psalm' => 'psalm-title text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2',
                        'main' => 'main-title text-center text-xl font-bold text-gray-900 dark:text-gray-100 mb-4',
                        'acrostic' => 'acrostic-title text-center text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2',
                        'chapter' => 'chapter-title text-center text-lg font-bold text-gray-900 dark:text-gray-100 mb-4',
                        'sub' => 'sub-title text-center text-base font-semibold text-gray-800 dark:text-gray-200 mb-3',
                        'verse' => 'verse-title text-sm font-medium text-blue-600 dark:text-blue-400 italic mb-2',
                        default => 'title text-center text-sm font-medium text-gray-700 dark:text-gray-300 mb-2'
                    };

                    // Separate verse titles from chapter titles
                    if ($title->title_type === 'verse') {
                        $verseTitleHtml .= '<div class="' . $titleClass . '">' . $title->title_text . '</div>';
                    } else {
                        $chapterTitleHtml .= '<div class="' . $titleClass . '">' . $title->title_text . '</div>';
                    }
                }
            }

            return [
                'osis_id' => $verse->osis_id,
                'verse_number' => $verse->verse_number,
                'text' => $this->enhanceVerseText($verse->formatted_text, $verse->osis_id, false),
                'chapter_titles' => $chapterTitleHtml,
                'verse_titles' => $verseTitleHtml,
            ];
        });
    }

    /**
     * Get verses for a specific chapter grouped by paragraphs
     */
    public function getVersesParagraphStyle(string $chapterOsisRef): Collection
    {
        \Log::info("DatabaseBibleReader: getVersesParagraphStyle called with '{$chapterOsisRef}'");

        $verses = $this->getVerses($chapterOsisRef);

        // Debug: Log what was retrieved
        if (! empty($verses) && isset($verses[0]['text'])) {
            $firstText = substr(strip_tags($verses[0]['text']), 0, 30);
            \Log::info("DatabaseBibleReader: First verse retrieved: {$firstText}...");
        } else {
            \Log::info("DatabaseBibleReader: No verses retrieved for {$chapterOsisRef}");
        }

        // Try to get actual paragraph data from database first
        $paragraphData = $this->getParagraphDataFromDatabase($chapterOsisRef);

        if ($paragraphData->isNotEmpty()) {
            return $this->groupVersesByParagraphData($verses, $paragraphData);
        }

        // Fallback: Extract paragraph markers from OSIS text
        $paragraphs = $this->extractParagraphsFromOsis($verses);

        if ($paragraphs->isNotEmpty()) {
            return $paragraphs;
        }

        // Final fallback: use artificial grouping
        return $this->createArtificialParagraphs($verses);
    }

    /**
     * Get paragraph data from database if available
     */
    private function getParagraphDataFromDatabase(string $chapterOsisRef): Collection
    {
        if (! $this->bibleVersion) {
            return collect();
        }

        return Paragraph::with(['startVerse', 'endVerse'])
            ->whereHas('chapter', function ($query) use ($chapterOsisRef) {
                $query->where('osis_id', $chapterOsisRef)
                    ->where('version_id', $this->bibleVersion->id);
            })
            ->orderBy('start_verse_id')
            ->get()
            ->map(function ($paragraph) {
                return [
                    'paragraph_type' => $paragraph->paragraph_type,
                    'start_verse' => $paragraph->startVerse->verse_number,
                    'end_verse' => $paragraph->endVerse ? $paragraph->endVerse->verse_number : $paragraph->startVerse->verse_number,
                    'text_content' => $paragraph->text_content,
                ];
            });
    }

    /**
     * Group verses by stored paragraph data, but respect verse titles by splitting paragraphs when needed
     */
    private function groupVersesByParagraphData(Collection $verses, Collection $paragraphData): Collection
    {
        $paragraphs = collect();

        foreach ($paragraphData as $paragraph) {
            $startVerse = $paragraph['start_verse'];
            $endVerse = $paragraph['end_verse'];

            $paragraphVerses = $verses->filter(function ($verse) use ($startVerse, $endVerse) {
                return $verse['verse_number'] >= $startVerse && $verse['verse_number'] <= $endVerse;
            })->values();

            if ($paragraphVerses->isNotEmpty()) {
                // Check if any verses within this paragraph have verse titles (except the first verse)
                $firstVerseNumber = $paragraphVerses->first()['verse_number'];
                $versesWithTitles = $paragraphVerses->filter(function ($verse) use ($firstVerseNumber) {
                    return $verse['verse_number'] > $firstVerseNumber
                           && isset($verse['verse_titles'])
                           && !empty($verse['verse_titles']);
                });

                if ($versesWithTitles->isNotEmpty()) {
                    // Split the paragraph at each verse title
                    $this->splitParagraphAtVerseTitles($paragraphVerses, $paragraph, $paragraphs);
                } else {
                    // No verse titles found, use the original paragraph structure
                    $combinedText = $paragraphVerses->pluck('text')->implode(' ');

                    $paragraphs->push([
                        'verses' => $paragraphVerses->toArray(),
                        'combined_text' => $combinedText,
                        'type' => $paragraph['paragraph_type'],
                        'start_verse' => $startVerse,
                        'end_verse' => $endVerse,
                        'has_paragraph_marker' => true,
                    ]);
                }
            }
        }

        return $paragraphs;
    }

    /**
     * Split a stored paragraph at verse titles while preserving paragraph metadata
     */
    private function splitParagraphAtVerseTitles(Collection $paragraphVerses, array $originalParagraph, Collection &$paragraphs): void
    {
        $currentParagraphVerses = collect();

        foreach ($paragraphVerses as $verse) {
            $hasVerseTitle = isset($verse['verse_titles']) && !empty($verse['verse_titles']);
            $isFirstVerse = $currentParagraphVerses->isEmpty();

            // If this verse has a title and it's not the first verse of the current sub-paragraph
            if ($hasVerseTitle && !$isFirstVerse) {
                // Finish the current sub-paragraph
                if ($currentParagraphVerses->isNotEmpty()) {
                    $combinedText = $currentParagraphVerses->pluck('text')->implode(' ');

                    $paragraphs->push([
                        'verses' => $currentParagraphVerses->toArray(),
                        'combined_text' => $combinedText,
                        'type' => $originalParagraph['paragraph_type'],
                        'start_verse' => $currentParagraphVerses->first()['verse_number'],
                        'end_verse' => $currentParagraphVerses->last()['verse_number'],
                        'has_paragraph_marker' => true,
                        'split_from_original' => true, // Mark as split from original paragraph
                    ]);
                }

                // Start new sub-paragraph with the verse that has the title
                $currentParagraphVerses = collect([$verse]);
            } else {
                // Add verse to current sub-paragraph
                $currentParagraphVerses->push($verse);
            }
        }

        // Add the final sub-paragraph
        if ($currentParagraphVerses->isNotEmpty()) {
            $combinedText = $currentParagraphVerses->pluck('text')->implode(' ');

            $paragraphs->push([
                'verses' => $currentParagraphVerses->toArray(),
                'combined_text' => $combinedText,
                'type' => $originalParagraph['paragraph_type'],
                'start_verse' => $currentParagraphVerses->first()['verse_number'],
                'end_verse' => $currentParagraphVerses->last()['verse_number'],
                'has_paragraph_marker' => true,
                'split_from_original' => true, // Mark as split from original paragraph
            ]);
        }
    }

    /**
     * Extract paragraphs from OSIS markup in verse text
     */
    private function extractParagraphsFromOsis(Collection $verses): Collection
    {
        $paragraphs = collect();
        $currentParagraph = [];
        $currentText = '';

        foreach ($verses as $verse) {
            // Check if this verse starts a new paragraph
            $hasNewParagraphMarker = $this->hasNewParagraphMarker($verse);

            // If we have verses in current paragraph and this verse starts a new one
            if (! empty($currentParagraph) && $hasNewParagraphMarker) {
                // Finish the current paragraph
                $paragraphs->push([
                    'verses' => $currentParagraph,
                    'combined_text' => trim($currentText),
                    'type' => 'paragraph',
                    'has_paragraph_marker' => true,
                ]);

                // Start new paragraph
                $currentParagraph = [$verse];
                $currentText = $verse['text'] . ' ';
            } else {
                // Add to current paragraph
                $currentParagraph[] = $verse;
                $currentText .= $verse['text'] . ' ';
            }
        }

        // Add the final paragraph
        if (! empty($currentParagraph)) {
            $paragraphs->push([
                'verses' => $currentParagraph,
                'combined_text' => trim($currentText),
                'type' => 'paragraph',
                'has_paragraph_marker' => true,
            ]);
        }

        return $paragraphs;
    }

    /**
     * Check if a verse contains a new paragraph marker
     */
    private function hasNewParagraphMarker(array $verse): bool
    {
        // Look for OSIS paragraph markers in the verse text or titles
        $textToCheck = $verse['text'] . ' ' . ($verse['chapter_titles'] ?? '');

        // Check for common paragraph indicators:
        // 1. ¶ symbol (pilcrow)
        // 2. Milestone markers that weren't cleaned up
        // 3. Strong verse breaks (multiple sentences ending)

        if (str_contains($textToCheck, '¶')) {
            return true;
        }

        // Check for milestone markers in formatted text
        if (preg_match('/<milestone[^>]*type="x-p"[^>]*>/', $textToCheck)) {
            return true;
        }

        // For verse 1 of any chapter (always start a new paragraph)
        if ($verse['verse_number'] === 1) {
            return true;
        }

        // Check if this verse has a verse title (non-first verse with verse title should start new paragraph)
        if ($verse['verse_number'] > 1 && isset($verse['verse_titles']) && !empty($verse['verse_titles'])) {
            return true;
        }

        return false;
    }

    /**
     * Create artificial paragraphs as fallback
     */
    private function createArtificialParagraphs(Collection $verses): Collection
    {
        $paragraphs = collect();
        $currentParagraph = [];
        $currentText = '';
        $verseCount = 0;

        foreach ($verses as $verse) {
            $currentParagraph[] = $verse;
            $currentText .= $verse['text'] . ' ';
            $verseCount++;

            // Create a new paragraph every 3-5 verses or at specific verse patterns
            if ($verseCount >= 3 && ($verseCount >= 5 || $this->shouldBreakParagraph($verse))) {
                $paragraphs->push([
                    'verses' => $currentParagraph,
                    'combined_text' => trim($currentText),
                    'type' => 'paragraph',
                    'artificial' => true,
                ]);

                $currentParagraph = [];
                $currentText = '';
                $verseCount = 0;
            }
        }

        // Add remaining verses as final paragraph
        if (! empty($currentParagraph)) {
            $paragraphs->push([
                'verses' => $currentParagraph,
                'combined_text' => trim($currentText),
                'type' => 'paragraph',
                'artificial' => true,
            ]);
        }

        return $paragraphs;
    }

    /**
     * Get the text content of a specific verse
     */
    public function getVerseText(string $verseOsisId): string
    {
        $verse = DB::table('verses')
            ->whereRaw('UPPER(osis_id) = UPPER(?)', [$verseOsisId])
            ->first(['formatted_text', 'osis_id']);

        if (! $verse) {
            return '';
        }

        return $this->enhanceVerseText($verse->formatted_text, $verse->osis_id);
    }

    /**
     * Search for verses containing specific text using FTS5
     */
    public function searchVerses(string $searchTerm, int $limit = 100): Collection
    {
        // Escape and prepare search term for FTS5
        $escapedSearchTerm = $this->escapeFtsSearchTerm($searchTerm);

        // Use FTS5 for high-performance search
        $results = DB::select("
            SELECT
                v.osis_id,
                v.verse_number,
                v.formatted_text as text,
                b.name as book_name,
                b.osis_id as book_osis_id,
                c.chapter_number,
                snippet(verses_fts, 4, '<mark>', '</mark>', '...', 32) as highlighted_text,
                rank
            FROM verses_fts
            JOIN verses v ON verses_fts.rowid = v.id
            JOIN chapters c ON v.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            WHERE verses_fts MATCH ?
            ORDER BY rank
            LIMIT ?
        ", [$escapedSearchTerm, $limit]);

        return collect($results)->map(function ($result) {
            return [
                'osis_id' => $result->osis_id,
                'verse_number' => (int) $result->verse_number,
                'text' => $result->highlighted_text ?: $result->text,
                'book_name' => $result->book_name,
                'book_osis_id' => $result->book_osis_id,
                'chapter' => (int) $result->chapter_number,
                'reference' => "{$result->book_name} {$result->chapter_number}:{$result->verse_number}",
            ];
        });
    }

    /**
     * Escape search term for FTS5 to prevent SQL errors
     */
    private function escapeFtsSearchTerm(string $searchTerm): string
    {
        $searchTerm = trim($searchTerm);

        // If the search term contains special characters or looks like it might cause issues,
        // wrap it in double quotes for exact phrase matching
        if (preg_match('/[0-9:.-]/', $searchTerm) || str_contains($searchTerm, ' ')) {
            // Escape any double quotes in the search term
            $searchTerm = str_replace('"', '""', $searchTerm);
            // Wrap in quotes for exact phrase matching
            return '"' . $searchTerm . '"';
        }

        return $searchTerm;
    }

    /**
     * Get Bible metadata
     */
    public function getBibleInfo(): array
    {
        if (! $this->bibleVersion) {
            return [
                'title' => 'Unknown',
                'description' => '',
                'publisher' => '',
                'language' => 'English',
            ];
        }

        return [
            'title' => $this->bibleVersion->title,
            'description' => $this->bibleVersion->description ?: '',
            'publisher' => $this->bibleVersion->publisher ?: '',
            'language' => $this->bibleVersion->language ?: 'English',
        ];
    }

    /**
     * Parse a verse reference and return verse details if valid
     */
    public function parseVerseReference(string $input): ?array
    {
        $input = trim($input);

        $patterns = [
            '/^([a-zA-Z\s]+)\s*(\d+):(\d+)-(\d+)$/i', // "Acts 2:38-47"
            '/^([a-zA-Z]+)(\d+):(\d+)-(\d+)$/i',     // "acts2:38-47"
            '/^([a-zA-Z\s]+)\s*(\d+):(\d+)$/i',      // "Acts 2:38"
            '/^([a-zA-Z]+)(\d+):(\d+)$/i',           // "acts2:38"
            '/^([a-zA-Z\s]+)\s*(\d+)$/i',            // "Acts 2"
            '/^([a-zA-Z]+)(\d+)$/i',                  // "acts2"
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                $bookName = trim($matches[1]);
                $chapter = (int) $matches[2];
                $startVerse = isset($matches[3]) ? (int) $matches[3] : null;
                $endVerse = isset($matches[4]) ? (int) $matches[4] : null;

                $osisId = $this->findBookOsisId($bookName);
                if ($osisId) {
                    if ($startVerse && $endVerse) {
                        return [
                            'book_osis_id' => $osisId,
                            'chapter' => $chapter,
                            'start_verse' => $startVerse,
                            'end_verse' => $endVerse,
                            'type' => 'verse_range',
                        ];
                    } elseif ($startVerse) {
                        return [
                            'book_osis_id' => $osisId,
                            'chapter' => $chapter,
                            'verse' => $startVerse,
                            'type' => 'verse',
                        ];
                    }
                    return [
                        'book_osis_id' => $osisId,
                        'chapter' => $chapter,
                        'type' => 'chapter',
                    ];

                }
            }
        }

        return null;
    }

    /**
     * Get a specific verse by reference
     */
    public function getVerseByReference(string $bookOsisId, int $chapter, int $verse): ?array
    {
        $verseOsisId = $bookOsisId . '.' . $chapter . '.' . $verse;
        $verseText = $this->getVerseText($verseOsisId);

        if (empty($verseText)) {
            return null;
        }

        return [
            'osis_id' => $verseOsisId,
            'book_id' => $bookOsisId,
            'chapter' => $chapter,
            'verse' => $verse,
            'text' => $verseText,
        ];
    }

    /**
     * Enhanced database-specific methods
     */

    /**
     * Get Strong's concordance data for a verse
     */
    public function getVerseStrongsData(string $verseOsisId): Collection
    {
        return DB::table('word_elements as we')
            ->join('verses as v', 'we.verse_id', '=', 'v.id')
            ->select([
                'we.word_text',
                'we.strongs_number',
                'we.morphology_code',
                'we.lemma',
                'we.word_order',
            ])
            ->where('v.osis_id', $verseOsisId)
            ->whereNotNull('we.strongs_number')
            ->orderBy('we.word_order')
            ->get();
    }

    /**
     * Get translator changes (italicized text) for a verse
     */
    public function getTranslatorChanges(string $verseOsisId): Collection
    {
        return DB::table('translator_changes as tc')
            ->join('verses as v', 'tc.verse_id', '=', 'v.id')
            ->select([
                'tc.text_content',
                'tc.change_type',
                'tc.text_order',
            ])
            ->where('v.osis_id', $verseOsisId)
            ->orderBy('tc.text_order')
            ->get();
    }

    /**
     * Get divine names (YHWH/LORD) for a verse
     */
    public function getDivineNames(string $verseOsisId): Collection
    {
        return DB::table('divine_names as dn')
            ->join('verses as v', 'dn.verse_id', '=', 'v.id')
            ->select([
                'dn.displayed_text',
                'dn.original_name',
            ])
            ->where('v.osis_id', $verseOsisId)
            ->get();
    }

    /**
     * Get study notes for a verse
     */
    public function getStudyNotes(string $verseOsisId): Collection
    {
        return DB::table('study_notes as sn')
            ->join('verses as v', 'sn.verse_id', '=', 'v.id')
            ->select([
                'sn.note_type',
                'sn.note_text',
            ])
            ->where('v.osis_id', $verseOsisId)
            ->get();
    }

    /**
     * Search by Strong's number
     */
    public function searchByStrongsNumber(string $strongsNumber, int $limit = 100): Collection
    {
        return DB::table('word_elements as we')
            ->join('verses as v', 'we.verse_id', '=', 'v.id')
            ->join('chapters as c', 'v.chapter_id', '=', 'c.id')
            ->join('books as b', 'c.book_id', '=', 'b.id')
            ->select([
                'v.osis_id',
                'v.verse_number',
                'v.text',
                'b.name as book_name',
                'b.osis_id as book_osis_id',
                'c.chapter_number',
                'we.word_text',
                'we.strongs_number',
            ])
            ->where('we.strongs_number', $strongsNumber)
            ->orderBy('b.sort_order')
            ->orderBy('c.chapter_number')
            ->orderBy('v.verse_number')
            ->limit($limit)
            ->get()
            ->map(function ($result) {
                return [
                    'osis_id' => $result->osis_id,
                    'verse_number' => (int) $result->verse_number,
                    'text' => $result->text,
                    'book_name' => $result->book_name,
                    'book_osis_id' => $result->book_osis_id,
                    'chapter' => (int) $result->chapter_number,
                    'word_text' => $result->word_text,
                    'strongs_number' => $result->strongs_number,
                    'reference' => "{$result->book_name} {$result->chapter_number}:{$result->verse_number}",
                ];
            });
    }

    /**
     * Get verse with comprehensive data (Strong's, notes, etc.)
     */
    public function getVerseWithDetails(string $verseOsisId): array
    {
        $verse = DB::table('verses as v')
            ->join('chapters as c', 'v.chapter_id', '=', 'c.id')
            ->join('books as b', 'c.book_id', '=', 'b.id')
            ->select([
                'v.osis_id',
                'v.verse_number',
                'v.text',
                'v.formatted_text',
                'v.original_xml',
                'b.name as book_name',
                'b.osis_id as book_osis_id',
                'c.chapter_number',
            ])
            ->where('v.osis_id', $verseOsisId)
            ->first();

        if (! $verse) {
            return [];
        }

        return [
            'verse' => [
                'osis_id' => $verse->osis_id,
                'verse_number' => (int) $verse->verse_number,
                'text' => $verse->text,
                'formatted_text' => $verse->formatted_text,
                'book_name' => $verse->book_name,
                'book_osis_id' => $verse->book_osis_id,
                'chapter' => (int) $verse->chapter_number,
                'reference' => "{$verse->book_name} {$verse->chapter_number}:{$verse->verse_number}",
            ],
            'strongs_data' => $this->getVerseStrongsData($verseOsisId),
            'translator_changes' => $this->getTranslatorChanges($verseOsisId),
            'divine_names' => $this->getDivineNames($verseOsisId),
            'study_notes' => $this->getStudyNotes($verseOsisId),
        ];
    }

    /**
     * Get titles for a specific verse
     */
    public function getTitles(string $verseOsisId): Collection
    {
        return DB::table('titles as t')
            ->join('verses as v', 't.verse_id', '=', 'v.id')
            ->select([
                't.title_type',
                't.title_text',
                't.canonical',
                't.placement',
                't.title_order',
            ])
            ->whereRaw('UPPER(v.osis_id) = UPPER(?)', [$verseOsisId])
            ->orderBy('t.title_order')
            ->get()
            ->map(function ($title) {
                return [
                    'type' => $title->title_type,
                    'text' => $title->title_text,
                    'canonical' => $title->canonical,
                    'placement' => $title->placement,
                    'order' => (int) $title->title_order,
                ];
            });
    }

    /**
     * Get poetry structure for a specific verse
     */
    public function getPoetryStructure(string $verseOsisId): Collection
    {
        return DB::table('poetry_structure as ps')
            ->join('verses as v', 'ps.verse_id', '=', 'v.id')
            ->select([
                'ps.structure_type',
                'ps.level',
                'ps.line_text',
                'ps.line_order',
            ])
            ->whereRaw('UPPER(v.osis_id) = UPPER(?)', [$verseOsisId])
            ->orderBy('ps.line_order')
            ->get()
            ->map(function ($structure) {
                return [
                    'type' => $structure->structure_type,
                    'level' => (int) $structure->level,
                    'text' => $structure->line_text,
                    'order' => (int) $structure->line_order,
                ];
            });
    }

    /**
     * Private helper methods
     */

    /**
     * Enhance verse text with additional formatting and information
     */
    private function enhanceVerseText(string $text, string $verseOsisId, bool $includeTitles = true): string
    {
        $enhancedText = $text;

        // Remove OSIS markup but preserve paragraph markers
        $enhancedText = $this->cleanOsisMarkup($enhancedText, true);

        // Add titles if requested
        if ($includeTitles) {
            $titles = $this->getTitles($verseOsisId);
            $titleHtml = '';

            foreach ($titles as $title) {
                if ($title['placement'] === 'before') {
                    $titleClass = match($title['type']) {
                        'psalm' => 'psalm-title text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2',
                        'main' => 'main-title text-center text-xl font-bold text-gray-900 dark:text-gray-100 mb-4',
                        'acrostic' => 'acrostic-title text-center text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2',
                        'chapter' => 'chapter-title text-center text-lg font-bold text-gray-900 dark:text-gray-100 mb-4',
                        'sub' => 'sub-title text-center text-base font-semibold text-gray-800 dark:text-gray-200 mb-3',
                        'verse' => 'verse-title text-sm font-medium text-blue-600 dark:text-blue-400 italic mb-2',
                        default => 'title text-center text-sm font-medium text-gray-700 dark:text-gray-300 mb-2'
                    };
                    $titleHtml .= '<div class="' . $titleClass . '">' . $title['text'] . '</div>';
                }
            }

            if ($titleHtml) {
                $enhancedText = $titleHtml . $enhancedText;
            }
        }

        // Add enhanced formatting
        $enhancedText = $this->addTextFormatting($enhancedText);

        return $enhancedText;
    }

    /**
     * Clean OSIS markup from text while optionally preserving paragraph markers
     */
    private function cleanOsisMarkup(string $text, bool $preserveParagraphMarkers = false): string
    {
        $cleanText = $text;

        if ($preserveParagraphMarkers) {
            // Replace paragraph milestone markers with pilcrow symbol for detection
            $cleanText = preg_replace('/<milestone[^>]*type="x-p"[^>]*>/i', '¶', $cleanText);
        }

        // Handle Red Letter text (Jesus' words) - convert to styled spans
        $cleanText = preg_replace('/<q[^>]*who="Jesus"[^>]*>/i', '<span class="text-red-600 dark:text-red-400 font-medium">', $cleanText);
        $cleanText = str_replace('</q>', '</span>', $cleanText);

        // Remove other OSIS tags but keep content
        $cleanText = preg_replace('/<w[^>]*>(.*?)<\/w>/i', '$1', $cleanText);
        $cleanText = preg_replace('/<transChange[^>]*>(.*?)<\/transChange>/i', '$1', $cleanText);
        $cleanText = preg_replace('/<divineName[^>]*>(.*?)<\/divineName>/i', '$1', $cleanText);
        $cleanText = preg_replace('/<note[^>]*>.*?<\/note>/i', '', $cleanText);
        $cleanText = preg_replace('/<verse[^>]*\/?>/i', '', $cleanText);
        $cleanText = preg_replace('/<chapter[^>]*\/?>/i', '', $cleanText);

        if (! $preserveParagraphMarkers) {
            $cleanText = preg_replace('/<milestone[^>]*>/i', '', $cleanText);
        }

        // Always remove pilcrow symbols from display text - they should not be shown to users
        $cleanText = str_replace('¶', '', $cleanText);

        // Clean up whitespace
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
        $cleanText = trim($cleanText);

        return $cleanText;
    }

    /**
     * Add enhanced text formatting
     */
    private function addTextFormatting(string $text): string
    {
        // Add basic text enhancements
        $formatted = $text;

        // Highlight divine names (already handled in cleanOsisMarkup)
        // Could add more formatting here as needed

        return $formatted;
    }

    /**
     * Determine if paragraph should break at this verse
     */
    private function shouldBreakParagraph(array $verse): bool
    {
        // Break paragraphs at certain verse numbers that typically start new thoughts
        $breakVerses = [1, 10, 20, 30]; // Common paragraph break points
        return in_array($verse['verse_number'], $breakVerses);
    }

    /**
     * Find book OSIS ID from various name formats
     */
    private function findBookOsisId(string $bookName): ?string
    {
        $bookName = strtolower(trim($bookName));

        // Try direct database lookup first
        $book = DB::table('books')
            ->whereRaw('LOWER(name) = ?', [$bookName])
            ->orWhereRaw('LOWER(osis_id) = ?', [$bookName])
            ->first(['osis_id']);

        if ($book) {
            return $book->osis_id;
        }

        // Fallback to comprehensive mapping
        $bookMappings = [
            // Old Testament
            'genesis' => 'Gen', 'gen' => 'Gen', 'ge' => 'Gen',
            'exodus' => 'Exod', 'exod' => 'Exod', 'ex' => 'Exod', 'exo' => 'Exod',
            'leviticus' => 'Lev', 'lev' => 'Lev', 'le' => 'Lev',
            'numbers' => 'Num', 'num' => 'Num', 'nu' => 'Num',
            'deuteronomy' => 'Deut', 'deut' => 'Deut', 'de' => 'Deut', 'dt' => 'Deut',
            'joshua' => 'Josh', 'josh' => 'Josh', 'jos' => 'Josh',
            'judges' => 'Judg', 'judg' => 'Judg', 'jdg' => 'Judg',
            'ruth' => 'Ruth', 'ru' => 'Ruth',
            '1 samuel' => '1Sam', '1samuel' => '1Sam', '1sam' => '1Sam', '1sa' => '1Sam',
            '2 samuel' => '2Sam', '2samuel' => '2Sam', '2sam' => '2Sam', '2sa' => '2Sam',
            '1 kings' => '1Kgs', '1kings' => '1Kgs', '1kgs' => '1Kgs', '1ki' => '1Kgs',
            '2 kings' => '2Kgs', '2kings' => '2Kgs', '2kgs' => '2Kgs', '2ki' => '2Kgs',
            '1 chronicles' => '1Chr', '1chronicles' => '1Chr', '1chr' => '1Chr', '1ch' => '1Chr',
            '2 chronicles' => '2Chr', '2chronicles' => '2Chr', '2chr' => '2Chr', '2ch' => '2Chr',
            'ezra' => 'Ezra', 'ezr' => 'Ezra',
            'nehemiah' => 'Neh', 'neh' => 'Neh', 'ne' => 'Neh',
            'esther' => 'Esth', 'esth' => 'Esth', 'est' => 'Esth',
            'job' => 'Job', 'jb' => 'Job',
            'psalms' => 'Ps', 'psalm' => 'Ps', 'ps' => 'Ps', 'psa' => 'Ps',
            'proverbs' => 'Prov', 'prov' => 'Prov', 'pr' => 'Prov', 'pro' => 'Prov',
            'ecclesiastes' => 'Eccl', 'eccl' => 'Eccl', 'ecc' => 'Eccl', 'ec' => 'Eccl',
            'song of solomon' => 'Song', 'song' => 'Song', 'sos' => 'Song', 'so' => 'Song',
            'isaiah' => 'Isa', 'isa' => 'Isa', 'is' => 'Isa',
            'jeremiah' => 'Jer', 'jer' => 'Jer', 'je' => 'Jer',
            'lamentations' => 'Lam', 'lam' => 'Lam', 'la' => 'Lam',
            'ezekiel' => 'Ezek', 'ezek' => 'Ezek', 'eze' => 'Ezek',
            'daniel' => 'Dan', 'dan' => 'Dan', 'da' => 'Dan',
            'hosea' => 'Hos', 'hos' => 'Hos', 'ho' => 'Hos',
            'joel' => 'Joel', 'joe' => 'Joel',
            'amos' => 'Amos', 'am' => 'Amos',
            'obadiah' => 'Obad', 'obad' => 'Obad', 'ob' => 'Obad',
            'jonah' => 'Jonah', 'jon' => 'Jonah',
            'micah' => 'Mic', 'mic' => 'Mic', 'mi' => 'Mic',
            'nahum' => 'Nah', 'nah' => 'Nah', 'na' => 'Nah',
            'habakkuk' => 'Hab', 'hab' => 'Hab', 'hb' => 'Hab',
            'zephaniah' => 'Zeph', 'zeph' => 'Zeph', 'zep' => 'Zeph',
            'haggai' => 'Hag', 'hag' => 'Hag', 'hg' => 'Hag',
            'zechariah' => 'Zech', 'zech' => 'Zech', 'zec' => 'Zech',
            'malachi' => 'Mal', 'mal' => 'Mal', 'ml' => 'Mal',

            // New Testament
            'matthew' => 'Matt', 'matt' => 'Matt', 'mt' => 'Matt',
            'mark' => 'Mark', 'mr' => 'Mark', 'mk' => 'Mark',
            'luke' => 'Luke', 'lk' => 'Luke', 'lu' => 'Luke',
            'john' => 'John', 'jn' => 'John', 'joh' => 'John',
            'acts' => 'Acts', 'act' => 'Acts', 'ac' => 'Acts',
            'romans' => 'Rom', 'rom' => 'Rom', 'ro' => 'Rom',
            '1 corinthians' => '1Cor', '1corinthians' => '1Cor', '1cor' => '1Cor', '1co' => '1Cor',
            '2 corinthians' => '2Cor', '2corinthians' => '2Cor', '2cor' => '2Cor', '2co' => '2Cor',
            'galatians' => 'Gal', 'gal' => 'Gal', 'ga' => 'Gal',
            'ephesians' => 'Eph', 'eph' => 'Eph', 'ep' => 'Eph',
            'philippians' => 'Phil', 'phil' => 'Phil', 'php' => 'Phil',
            'colossians' => 'Col', 'col' => 'Col', 'cl' => 'Col',
            '1 thessalonians' => '1Thess', '1thessalonians' => '1Thess', '1thess' => '1Thess', '1th' => '1Thess',
            '2 thessalonians' => '2Thess', '2thessalonians' => '2Thess', '2thess' => '2Thess', '2th' => '2Thess',
            '1 timothy' => '1Tim', '1timothy' => '1Tim', '1tim' => '1Tim', '1ti' => '1Tim',
            '2 timothy' => '2Tim', '2timothy' => '2Tim', '2tim' => '2Tim', '2ti' => '2Tim',
            'titus' => 'Titus', 'tit' => 'Titus', 'ti' => 'Titus',
            'philemon' => 'Phlm', 'phlm' => 'Phlm', 'phm' => 'Phlm',
            'hebrews' => 'Heb', 'heb' => 'Heb', 'he' => 'Heb',
            'james' => 'Jas', 'jas' => 'Jas', 'ja' => 'Jas', 'jm' => 'Jas',
            '1 peter' => '1Pet', '1peter' => '1Pet', '1pet' => '1Pet', '1pe' => '1Pet',
            '2 peter' => '2Pet', '2peter' => '2Pet', '2pet' => '2Pet', '2pe' => '2Pet',
            '1 john' => '1John', '1john' => '1John', '1jn' => '1John', '1jo' => '1John',
            '2 john' => '2John', '2john' => '2John', '2jn' => '2John', '2jo' => '2John',
            '3 john' => '3John', '3john' => '3John', '3jn' => '3John', '3jo' => '3John',
            'jude' => 'Jude', 'jud' => 'Jude', 'ju' => 'Jude',
            'revelation' => 'Rev', 'rev' => 'Rev', 're' => 'Rev', 'revelations' => 'Rev',
        ];

        return $bookMappings[$bookName] ?? null;
    }
}
