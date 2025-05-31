<?php

namespace App\Services;

use App\Services\Contracts\BibleReaderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DatabaseBibleReader implements BibleReaderInterface
{
    private string $versionKey;
    private ?int $versionId = null;

    public function __construct(string $versionKey = 'kjv')
    {
        $this->versionKey = $versionKey;
        $this->versionId = $this->getVersionId();
    }

    /**
     * Get version ID from database
     */
    private function getVersionId(): ?int
    {
        return Cache::remember("bible_version_{$this->versionKey}", 3600, function () {
            return DB::table('bible_versions')
                ->where('abbreviation', strtoupper($this->versionKey))
                ->value('id');
        });
    }

    /**
     * Get all books in the Bible
     */
    public function getBooks(): Collection
    {
        return Cache::remember("books_{$this->versionKey}", 3600, function () {
            return DB::table('books as b')
                ->join('book_groups as bg', 'b.book_group_id', '=', 'bg.id')
                ->select([
                    'b.osis_id',
                    'b.name',
                    'b.name as short_name', // Use same name for consistency
                    'bg.name as testament',
                    'b.sort_order as book_order'
                ])
                ->where('b.canonical', true)
                ->orderBy('b.sort_order')
                ->get()
                ->map(function ($book) {
                    return [
                        'osis_id' => $book->osis_id,
                        'name' => $book->name,
                        'short_name' => $book->short_name,
                        'testament' => $book->testament,
                        'book_order' => $book->book_order
                    ];
                });
        });
    }

    /**
     * Get chapters for a specific book
     */
    public function getChapters(string $bookOsisId): Collection
    {
        return Cache::remember("chapters_{$bookOsisId}_{$this->versionKey}", 3600, function () use ($bookOsisId) {
            return DB::table('chapters as c')
                ->join('books as b', 'c.book_id', '=', 'b.id')
                ->leftJoin('verses as v', 'c.id', '=', 'v.chapter_id')
                ->select([
                    'c.osis_id as osis_ref',
                    'c.chapter_number',
                    DB::raw('COUNT(v.id) as verse_count')
                ])
                ->where('b.osis_id', $bookOsisId)
                ->where('c.version_id', $this->versionId)
                ->groupBy('c.id', 'c.osis_id', 'c.chapter_number')
                ->orderBy('c.chapter_number')
                ->get()
                ->map(function ($chapter) {
                    return [
                        'osis_ref' => $chapter->osis_ref,
                        'chapter_number' => (int) $chapter->chapter_number,
                        'verse_count' => (int) $chapter->verse_count
                    ];
                });
        });
    }

    /**
     * Get verses for a specific chapter
     */
    public function getVerses(string $chapterOsisRef): Collection
    {
        $verses = DB::table('verses as v')
            ->join('chapters as c', 'v.chapter_id', '=', 'c.id')
            ->select([
                'v.osis_id',
                'v.verse_number',
                'v.formatted_text as text'
            ])
            ->where('c.osis_id', $chapterOsisRef)
            ->orderBy('v.verse_number')
            ->get()
            ->map(function ($verse) {
                return [
                    'osis_id' => $verse->osis_id,
                    'verse_number' => (int) $verse->verse_number,
                    'text' => $this->enhanceVerseText($verse->text, $verse->osis_id, false) // Don't include titles in verse text
                ];
            });

        // Add chapter titles to the first verse if it exists
        if ($verses->isNotEmpty()) {
            $firstVerseOsisId = $verses->first()['osis_id'];
            $titles = $this->getTitles($firstVerseOsisId);
            $titleHtml = '';

            foreach ($titles as $title) {
                if ($title['placement'] === 'before') {
                    $titleClass = match($title['type']) {
                        'psalm' => 'psalm-title text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2',
                        'main' => 'main-title text-center text-xl font-bold text-gray-900 dark:text-gray-100 mb-4',
                        'acrostic' => 'acrostic-title text-center text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2',
                        default => 'title text-center text-sm font-medium text-gray-700 dark:text-gray-300 mb-2'
                    };
                    $titleHtml .= '<div class="' . $titleClass . '">' . $title['text'] . '</div>';
                }
            }

            // Add titles as a separate field to the collection
            $verses = $verses->map(function ($verse, $index) use ($titleHtml) {
                if ($index === 0) {
                    $verse['chapter_titles'] = $titleHtml;
                } else {
                    $verse['chapter_titles'] = '';
                }
                return $verse;
            });
        }

        return $verses;
    }

    /**
     * Get verses for a specific chapter grouped by paragraphs
     */
    public function getVersesParagraphStyle(string $chapterOsisRef): Collection
    {
        $verses = $this->getVerses($chapterOsisRef);

        // For now, group verses into paragraphs of 3-5 verses each
        // This can be enhanced with actual paragraph markup from the database
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
                    'type' => 'paragraph'
                ]);

                $currentParagraph = [];
                $currentText = '';
                $verseCount = 0;
            }
        }

        // Add remaining verses as final paragraph
        if (!empty($currentParagraph)) {
            $paragraphs->push([
                'verses' => $currentParagraph,
                'combined_text' => trim($currentText),
                'type' => 'paragraph'
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
            ->where('osis_id', $verseOsisId)
            ->first(['formatted_text', 'osis_id']);

        if (!$verse) {
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
                'reference' => "{$result->book_name} {$result->chapter_number}:{$result->verse_number}"
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
        $version = DB::table('bible_versions')
            ->where('id', $this->versionId)
            ->first();

        if (!$version) {
            return [
                'title' => 'Unknown',
                'description' => '',
                'publisher' => '',
                'language' => 'English'
            ];
        }

        return [
            'title' => $version->title,
            'description' => $version->description ?: '',
            'publisher' => $version->publisher ?: '',
            'language' => $version->language ?: 'English'
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
            '/^([a-zA-Z]+)(\d+)$/i'                  // "acts2"
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
                            'type' => 'verse_range'
                        ];
                    } elseif ($startVerse) {
                        return [
                            'book_osis_id' => $osisId,
                            'chapter' => $chapter,
                            'verse' => $startVerse,
                            'type' => 'verse'
                        ];
                    } else {
                        return [
                            'book_osis_id' => $osisId,
                            'chapter' => $chapter,
                            'type' => 'chapter'
                        ];
                    }
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
            'text' => $verseText
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
                'we.word_order'
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
                'tc.text_order'
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
                'dn.original_name'
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
                'sn.note_text'
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
                'we.strongs_number'
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
                    'reference' => "{$result->book_name} {$result->chapter_number}:{$result->verse_number}"
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
                'c.chapter_number'
            ])
            ->where('v.osis_id', $verseOsisId)
            ->first();

        if (!$verse) {
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
                'reference' => "{$verse->book_name} {$verse->chapter_number}:{$verse->verse_number}"
            ],
            'strongs_data' => $this->getVerseStrongsData($verseOsisId),
            'translator_changes' => $this->getTranslatorChanges($verseOsisId),
            'divine_names' => $this->getDivineNames($verseOsisId),
            'study_notes' => $this->getStudyNotes($verseOsisId)
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
                't.title_order'
            ])
            ->where('v.osis_id', $verseOsisId)
            ->orderBy('t.title_order')
            ->get()
            ->map(function ($title) {
                return [
                    'type' => $title->title_type,
                    'text' => $title->title_text,
                    'canonical' => $title->canonical,
                    'placement' => $title->placement,
                    'order' => (int) $title->title_order
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
                'ps.line_order'
            ])
            ->where('v.osis_id', $verseOsisId)
            ->orderBy('ps.line_order')
            ->get()
            ->map(function ($structure) {
                return [
                    'type' => $structure->structure_type,
                    'level' => (int) $structure->level,
                    'text' => $structure->line_text,
                    'order' => (int) $structure->line_order
                ];
            });
    }

    /**
     * Private helper methods
     */

    /**
     * Enhance verse text with additional features when using database reader
     */
    private function enhanceVerseText(string $text, string $verseOsisId, bool $includeTitles = true): string
    {
        $enhancedText = $text;

        // Add titles that come before this verse
        if ($includeTitles) {
            $titles = $this->getTitles($verseOsisId);
            $titleHtml = '';
            foreach ($titles as $title) {
                if ($title['placement'] === 'before') {
                    $titleClass = match($title['type']) {
                        'psalm' => 'psalm-title text-center text-sm font-medium text-gray-700 dark:text-gray-300 italic mb-3 border-b border-gray-200 dark:border-gray-600 pb-2',
                        'main' => 'main-title text-center text-xl font-bold text-gray-900 dark:text-gray-100 mb-4',
                        'acrostic' => 'acrostic-title text-center text-lg font-semibold text-blue-700 dark:text-blue-400 mb-2',
                        default => 'title text-center text-sm font-medium text-gray-700 dark:text-gray-300 mb-2'
                    };
                    // Title text is already formatted HTML, so use it directly
                    $titleHtml .= '<div class="' . $titleClass . '">' . $title['text'] . '</div>';
                }
            }

            // Check if this verse has poetry structure
            $poetryStructure = $this->getPoetryStructure($verseOsisId);
            if ($poetryStructure->isNotEmpty()) {
                $poetryHtml = '';
                foreach ($poetryStructure as $structure) {
                    if ($structure['type'] === 'lg') {
                        $poetryHtml .= '<div class="line-group mb-2">';
                    } elseif ($structure['type'] === 'l') {
                        $indentClass = match($structure['level']) {
                            2 => 'ml-4',
                            3 => 'ml-8',
                            4 => 'ml-12',
                            default => ''
                        };

                        // Use the structure text if available, otherwise use the original verse text
                        $lineText = !empty($structure['text']) ? $structure['text'] : $enhancedText;
                        $poetryHtml .= '<div class="poetry-line leading-relaxed text-gray-900 dark:text-gray-100 ' . $indentClass . '">' . $lineText . '</div>';
                    }
                }
                if (str_contains($poetryHtml, 'line-group')) {
                    $poetryHtml .= '</div>'; // Close line-group
                }

                // If we have poetry structure, use that instead of regular text
                if ($poetryHtml) {
                    $enhancedText = $poetryHtml;
                }
            }

            $enhancedText = $titleHtml . $enhancedText;
        }

        return $enhancedText;
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
