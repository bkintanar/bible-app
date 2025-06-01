<?php

namespace App\Services;

use App\Models\StrongsLexicon;
use App\Models\WordElement;
use App\Models\WordRelationship;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StrongsService
{
    /**
     * Get detailed Strong's information
     */
    public function getStrongsDetails(string $strongsNumber): ?StrongsLexicon
    {
        return Cache::remember("strongs_details_{$strongsNumber}", 3600, function () use ($strongsNumber) {
            return StrongsLexicon::where('strongs_number', $strongsNumber)->first();
        });
    }

    /**
     * Search Strong's lexicon
     */
    public function searchLexicon(string $query, int $limit = 50): Collection
    {
        return StrongsLexicon::search($query, $limit);
    }

    /**
     * Get all verses containing a Strong's number
     */
    public function getVersesWithStrongs(string $strongsNumber, int $limit = 100): array
    {
        $wordElements = WordElement::where('strongs_number', $strongsNumber)
            ->with(['verse.chapter.book'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $wordElements->map(function ($element) {
            $verse = $element->verse;
            $chapter = $verse->chapter;
            $book = $chapter->book;

            return [
                'verse_id' => $verse->id,
                'osis_id' => $verse->osis_id,
                'book_name' => $book->name,
                'chapter' => $chapter->chapter_number,
                'verse_number' => $verse->verse_number,
                'text' => $verse->text,
                'formatted_text' => $verse->formatted_text,
                'word_text' => $element->word_text,
                'word_order' => $element->word_order,
                'reference' => "{$book->name} {$chapter->chapter_number}:{$verse->verse_number}"
            ];
        })->toArray();
    }

    /**
     * Get word study data for a Strong's number
     */
    public function getWordStudy(string $strongsNumber): array
    {
        $lexicon = $this->getStrongsDetails($strongsNumber);

        if (!$lexicon) {
            return [];
        }

        // Get usage statistics
        $usage = $this->getUsageStatistics($strongsNumber);

        // Get related words
        $relationships = $this->getWordRelationships($strongsNumber);

        // Get verses with context
        $verses = $this->getVersesWithStrongs($strongsNumber, 50);

        // Get morphological analysis
        $morphology = $this->getMorphologicalAnalysis($strongsNumber);

        return [
            'lexicon' => $lexicon,
            'usage' => $usage,
            'relationships' => $relationships,
            'verses' => $verses,
            'morphology' => $morphology,
            'study_notes' => $this->generateStudyNotes($lexicon, $usage, $relationships)
        ];
    }

    /**
     * Get usage statistics for a Strong's number
     */
    public function getUsageStatistics(string $strongsNumber): array
    {
        $stats = Cache::remember("strongs_stats_{$strongsNumber}", 1800, function () use ($strongsNumber) {
            // Total occurrences
            $totalOccurrences = WordElement::where('strongs_number', $strongsNumber)->count();

            // Book distribution
            $bookDistribution = DB::table('word_elements as we')
                ->join('verses as v', 'we.verse_id', '=', 'v.id')
                ->join('chapters as c', 'v.chapter_id', '=', 'c.id')
                ->join('books as b', 'c.book_id', '=', 'b.id')
                ->where('we.strongs_number', $strongsNumber)
                ->select('b.name', 'b.osis_id', DB::raw('COUNT(*) as count'))
                ->groupBy('b.id', 'b.name', 'b.osis_id')
                ->orderBy('count', 'desc')
                ->get()
                ->toArray();

            // Testament distribution
            $testamentStats = DB::table('word_elements as we')
                ->join('verses as v', 'we.verse_id', '=', 'v.id')
                ->join('chapters as c', 'v.chapter_id', '=', 'c.id')
                ->join('books as b', 'c.book_id', '=', 'b.id')
                ->join('book_groups as bg', 'b.book_group_id', '=', 'bg.id')
                ->where('we.strongs_number', $strongsNumber)
                ->select('bg.name', DB::raw('COUNT(*) as count'))
                ->groupBy('bg.id', 'bg.name')
                ->get()
                ->toArray();

            // Word variations
            $wordVariations = WordElement::where('strongs_number', $strongsNumber)
                ->select('word_text', DB::raw('COUNT(*) as count'))
                ->groupBy('word_text')
                ->orderBy('count', 'desc')
                ->limit(20)
                ->get()
                ->toArray();

            return [
                'total_occurrences' => $totalOccurrences,
                'book_distribution' => $bookDistribution,
                'testament_distribution' => $testamentStats,
                'word_variations' => $wordVariations
            ];
        });

        return $stats;
    }

    /**
     * Get word relationships
     */
    public function getWordRelationships(string $strongsNumber): array
    {
        return Cache::remember("strongs_relationships_{$strongsNumber}", 3600, function () use ($strongsNumber) {
            $relationships = [];

            // Get all relationships where this is the source
            $sourceRels = WordRelationship::where('source_strongs', $strongsNumber)
                ->with('targetLexicon')
                ->get()
                ->groupBy('relationship_type');

            // Get all relationships where this is the target
            $targetRels = WordRelationship::where('target_strongs', $strongsNumber)
                ->with('sourceLexicon')
                ->get()
                ->groupBy('relationship_type');

            foreach (WordRelationship::getRelationshipTypes() as $type => $label) {
                $relationships[$type] = [
                    'label' => $label,
                    'outgoing' => $sourceRels->get($type, collect())->map(function ($rel) {
                        return $rel->targetLexicon;
                    })->filter()->values(),
                    'incoming' => $targetRels->get($type, collect())->map(function ($rel) {
                        return $rel->sourceLexicon;
                    })->filter()->values()
                ];
            }

            return $relationships;
        });
    }

    /**
     * Get morphological analysis
     */
    public function getMorphologicalAnalysis(string $strongsNumber): array
    {
        return Cache::remember("strongs_morphology_{$strongsNumber}", 3600, function () use ($strongsNumber) {
            $morphData = WordElement::where('strongs_number', $strongsNumber)
                ->whereNotNull('morphology_code')
                ->select('morphology_code', DB::raw('COUNT(*) as count'))
                ->groupBy('morphology_code')
                ->orderBy('count', 'desc')
                ->get();

            return $morphData->map(function ($item) {
                // Create a dummy WordElement to use the morphology parsing
                $element = new WordElement(['morphology_code' => $item->morphology_code]);

                return [
                    'code' => $item->morphology_code,
                    'count' => $item->count,
                    'info' => $element->morphology_info
                ];
            })->toArray();
        });
    }

    /**
     * Generate study notes
     */
    private function generateStudyNotes(StrongsLexicon $lexicon, array $usage, array $relationships): array
    {
        $notes = [];

        // Usage note
        if ($usage['total_occurrences'] > 0) {
            $notes[] = [
                'type' => 'usage',
                'title' => 'Biblical Usage',
                'content' => "This word appears {$usage['total_occurrences']} times in the Bible."
            ];
        }

        // Testament distribution note
        if (!empty($usage['testament_distribution'])) {
            $testamentInfo = collect($usage['testament_distribution'])->map(function ($item) {
                return "{$item->name}: {$item->count} times";
            })->join(', ');

            $notes[] = [
                'type' => 'distribution',
                'title' => 'Testament Distribution',
                'content' => $testamentInfo
            ];
        }

        // Most frequent books
        if (!empty($usage['book_distribution'])) {
            $topBooks = array_slice($usage['book_distribution'], 0, 3);
            $bookInfo = collect($topBooks)->map(function ($book) {
                return "{$book->name} ({$book->count})";
            })->join(', ');

            $notes[] = [
                'type' => 'frequency',
                'title' => 'Most Frequent in',
                'content' => $bookInfo
            ];
        }

        // Etymology note
        if (!empty($lexicon->etymology)) {
            $notes[] = [
                'type' => 'etymology',
                'title' => 'Etymology',
                'content' => $lexicon->etymology
            ];
        }

        // Related words note
        $synonymCount = count($relationships['synonym']['outgoing'] ?? []);
        $antonymCount = count($relationships['antonym']['outgoing'] ?? []);

        if ($synonymCount > 0 || $antonymCount > 0) {
            $relatedInfo = [];
            if ($synonymCount > 0) $relatedInfo[] = "{$synonymCount} synonyms";
            if ($antonymCount > 0) $relatedInfo[] = "{$antonymCount} antonyms";

            $notes[] = [
                'type' => 'relationships',
                'title' => 'Word Relationships',
                'content' => 'This word has ' . implode(' and ', $relatedInfo) . ' in the lexicon.'
            ];
        }

        return $notes;
    }

    /**
     * Get word family (root + derivatives)
     */
    public function getWordFamily(string $strongsNumber): array
    {
        $family = [
            'root' => null,
            'siblings' => [],
            'children' => [],
            'variants' => []
        ];

        // Find root word
        $rootRel = WordRelationship::where('source_strongs', $strongsNumber)
            ->where('relationship_type', 'root')
            ->with('targetLexicon')
            ->first();

        if ($rootRel) {
            $family['root'] = $rootRel->targetLexicon;

            // Find siblings (other words with same root)
            $siblings = WordRelationship::where('target_strongs', $rootRel->target_strongs)
                ->where('relationship_type', 'root')
                ->where('source_strongs', '!=', $strongsNumber)
                ->with('sourceLexicon')
                ->get()
                ->pluck('sourceLexicon');

            $family['siblings'] = $siblings;
        }

        // Find derivatives
        $derivatives = WordRelationship::where('target_strongs', $strongsNumber)
            ->where('relationship_type', 'derivative')
            ->with('sourceLexicon')
            ->get()
            ->pluck('sourceLexicon');

        $family['children'] = $derivatives;

        // Find variants
        $variants = WordRelationship::where('source_strongs', $strongsNumber)
            ->where('relationship_type', 'variant')
            ->with('targetLexicon')
            ->get()
            ->pluck('targetLexicon');

        $family['variants'] = $variants;

        return $family;
    }

    /**
     * Get thematic word groups
     */
    public function getThematicGroups(array $strongsNumbers): array
    {
        $groups = [];

        foreach ($strongsNumbers as $number) {
            $lexicon = $this->getStrongsDetails($number);
            if (!$lexicon) continue;

            // Group by part of speech
            $pos = $lexicon->part_of_speech ?? 'unknown';
            if (!isset($groups['part_of_speech'][$pos])) {
                $groups['part_of_speech'][$pos] = [];
            }
            $groups['part_of_speech'][$pos][] = $lexicon;

            // Group by semantic field (based on short definition keywords)
            $keywords = explode(' ', strtolower($lexicon->short_definition));
            foreach ($keywords as $keyword) {
                if (strlen($keyword) > 3) { // Skip short words
                    if (!isset($groups['semantic'][$keyword])) {
                        $groups['semantic'][$keyword] = [];
                    }
                    $groups['semantic'][$keyword][] = $lexicon;
                }
            }
        }

        return $groups;
    }

    /**
     * Generate lexicon export data
     */
    public function exportLexiconData(string $format = 'json'): string
    {
        $data = StrongsLexicon::with(['sourceRelationships', 'targetRelationships'])
            ->get()
            ->map(function ($lexicon) {
                return [
                    'strongs_number' => $lexicon->strongs_number,
                    'language' => $lexicon->language,
                    'original_word' => $lexicon->original_word,
                    'transliteration' => $lexicon->transliteration,
                    'pronunciation' => $lexicon->pronunciation,
                    'short_definition' => $lexicon->short_definition,
                    'detailed_definition' => $lexicon->detailed_definition,
                    'part_of_speech' => $lexicon->part_of_speech,
                    'occurrence_count' => $lexicon->occurrence_count,
                    'relationships' => [
                        'outgoing' => $lexicon->sourceRelationships->map(function ($rel) {
                            return [
                                'target' => $rel->target_strongs,
                                'type' => $rel->relationship_type,
                                'strength' => $rel->strength
                            ];
                        }),
                        'incoming' => $lexicon->targetRelationships->map(function ($rel) {
                            return [
                                'source' => $rel->source_strongs,
                                'type' => $rel->relationship_type,
                                'strength' => $rel->strength
                            ];
                        })
                    ]
                ];
            });

        return match($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => $this->arrayToCsv($data->toArray()),
            default => json_encode($data)
        };
    }

    /**
     * Convert array to CSV
     */
    private function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write header
        fputcsv($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
