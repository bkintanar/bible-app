<?php

namespace App\Http\Controllers;

use App\Services\StrongsService;
use App\Models\StrongsLexicon;
use App\Models\WordRelationship;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Illuminate\Http\Response;

class StrongsController extends Controller
{
    public function __construct(
        private StrongsService $strongsService
    ) {}

    /**
     * Display the Strong's Concordance index
     */
    public function index(Request $request): Response
    {
        $searchQuery = $request->get('q', '');
        $language = $request->get('language', '');
        $partOfSpeech = $request->get('part_of_speech', '');
        $limit = min((int) $request->get('limit', 50), 100);

        $lexiconEntries = $this->strongsService->searchLexicon(
            $searchQuery,
            $limit,
            $language,
            $partOfSpeech
        );

        // Get available filters
        $languages = StrongsLexicon::select('language')
            ->whereNotNull('language')
            ->groupBy('language')
            ->orderBy('language')
            ->pluck('language');

        $partsOfSpeech = StrongsLexicon::select('part_of_speech')
            ->whereNotNull('part_of_speech')
            ->groupBy('part_of_speech')
            ->orderBy('part_of_speech')
            ->pluck('part_of_speech');

        return Inertia::render('Strongs/Index', [
            'lexiconEntries' => $lexiconEntries,
            'searchQuery' => $searchQuery,
            'language' => $language,
            'partOfSpeech' => $partOfSpeech,
            'languages' => $languages,
            'partsOfSpeech' => $partsOfSpeech,
            'limit' => $limit,
        ]);
    }

    /**
     * Display detailed Strong's number study
     */
    public function show(string $strongsNumber): Response
    {
        $lexicon = $this->strongsService->getStrongsDetails($strongsNumber);

        if (!$lexicon) {
            abort(404, "Strong's number {$strongsNumber} not found");
        }

        // Start with empty data structures to avoid type errors
        $usageStats = [
            'total_occurrences' => 0,
            'by_book' => [],
            'by_testament' => []
        ];

        // Get usage statistics
        try {
            $rawUsageStats = $this->strongsService->getUsageStatistics($strongsNumber);
            if ($rawUsageStats) {
                $usageStats['total_occurrences'] = $rawUsageStats['total_occurrences'] ?? 0;

                // Convert book distribution to expected format
                if (isset($rawUsageStats['book_distribution']) && is_iterable($rawUsageStats['book_distribution'])) {
                    foreach ($rawUsageStats['book_distribution'] as $book) {
                        if (is_object($book) && isset($book->name, $book->count)) {
                            $usageStats['by_book'][$book->name] = $book->count;
                        }
                    }
                }

                // Convert testament distribution
                if (isset($rawUsageStats['testament_distribution']) && is_iterable($rawUsageStats['testament_distribution'])) {
                    foreach ($rawUsageStats['testament_distribution'] as $testament) {
                        if (is_object($testament) && isset($testament->name, $testament->count)) {
                            $testamentKey = str_contains(strtolower($testament->name), 'new') ? 'NT' : 'OT';
                            $usageStats['by_testament'][$testamentKey] = ($usageStats['by_testament'][$testamentKey] ?? 0) + $testament->count;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error getting usage stats for {$strongsNumber}: " . $e->getMessage());
        }

        $relatedWords = collect();
        $morphologyAnalysis = ['forms' => []];
        $sampleVerses = collect();

        // Get sample verses with the Strong's word
        try {
            $verses = $this->strongsService->getVersesWithStrongs($strongsNumber, 10);
            if (is_iterable($verses)) {
                foreach ($verses as $verse) {
                    if (is_array($verse)) {
                        $sampleVerses->push((object)[
                            'verse_id' => $verse['verse_id'] ?? '',
                            'osis_id' => $verse['osis_id'] ?? '',
                            'book_name' => $verse['book_name'] ?? '',
                            'chapter' => $verse['chapter'] ?? '',
                            'verse_number' => $verse['verse_number'] ?? '',
                            'text' => $verse['text'] ?? '',
                            'word_text' => $verse['word_text'] ?? '',
                            'reference' => $verse['reference'] ?? ($verse['book_name'] ?? '') . ' ' . ($verse['chapter'] ?? '') . ':' . ($verse['verse_number'] ?? ''),
                            'morphology' => $verse['morphology_code'] ?? ''
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error getting verses for {$strongsNumber}: " . $e->getMessage());
        }

        return Inertia::render('Strongs/Show', [
            'lexicon' => $lexicon,
            'usageStats' => $usageStats,
            'relatedWords' => $relatedWords,
            'morphologyAnalysis' => $morphologyAnalysis,
            'sampleVerses' => $sampleVerses,
            'strongsNumber' => $strongsNumber,
        ]);
    }

    /**
     * Get emoji for relationship type
     */
    private function getRelationshipEmoji(string $type): string
    {
        return match(strtolower($type)) {
            'synonym' => '🔄',
            'antonym' => '⚡',
            'related' => '🔗',
            'root' => '🌱',
            'derivative' => '🌿',
            default => '��'
        };
    }

    /**
     * Get verses for a Strong's number (AJAX)
     */
    public function verses(string $strongsNumber, Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 25);
        $verses = $this->strongsService->getVersesWithStrongs($strongsNumber, $limit);

        return response()->json([
            'verses' => $verses,
            'count' => count($verses),
            'strongs_number' => $strongsNumber
        ]);
    }

    /**
     * Search Strong's lexicon (AJAX)
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = (int) $request->get('limit', 20);

        if (empty($query)) {
            return response()->json(['results' => []]);
        }

        $results = $this->strongsService->searchLexicon($query, $limit);

        return response()->json([
            'results' => $results->map(function ($lexicon) {
                return [
                    'strongs_number' => $lexicon->strongs_number,
                    'original_word' => $lexicon->original_word,
                    'transliteration' => $lexicon->transliteration,
                    'short_definition' => $lexicon->short_definition,
                    'language' => $lexicon->language,
                    'language_emoji' => $lexicon->language_emoji,
                    'part_of_speech' => $lexicon->part_of_speech,
                    'part_of_speech_emoji' => $lexicon->part_of_speech_emoji,
                    'occurrence_count' => $lexicon->occurrence_count,
                    'url' => route('strongs.show', $lexicon->strongs_number)
                ];
            })
        ]);
    }

    /**
     * Get word relationships (AJAX)
     */
    public function relationships(string $strongsNumber): JsonResponse
    {
        $relationships = $this->strongsService->getWordRelationships($strongsNumber);

        return response()->json([
            'strongs_number' => $strongsNumber,
            'relationships' => $relationships
        ]);
    }

    /**
     * Compare multiple Strong's numbers
     */
    public function compare(Request $request): Response
    {
        $strongsNumbers = $request->get('numbers', []);

        if (is_string($strongsNumbers)) {
            $strongsNumbers = explode(',', $strongsNumbers);
        }

        $strongsNumbers = array_map('trim', $strongsNumbers);
        $strongsNumbers = array_filter($strongsNumbers);
        $strongsNumbers = array_slice($strongsNumbers, 0, 5); // Limit to 5

        $comparisons = [];
        foreach ($strongsNumbers as $number) {
            $study = $this->strongsService->getWordStudy($number);
            if (!empty($study)) {
                $comparisons[$number] = $study;
            }
        }

        // Get thematic groups if multiple words
        $thematicGroups = [];
        if (count($strongsNumbers) > 1) {
            $thematicGroups = $this->strongsService->getThematicGroups($strongsNumbers);
        }

        return Inertia::render('Strongs/Compare', [
            'comparisons' => $comparisons,
            'thematicGroups' => $thematicGroups,
            'strongsNumbers' => $strongsNumbers,
        ]);
    }

    /**
     * Word family tree view
     */
    public function family(string $strongsNumber): Response
    {
        $wordFamily = $this->strongsService->getWordFamily($strongsNumber);
        $lexicon = $this->strongsService->getStrongsDetails($strongsNumber);

        if (!$lexicon) {
            abort(404, "Strong's number {$strongsNumber} not found");
        }

        return Inertia::render('Strongs/Family', [
            'wordFamily' => $wordFamily,
            'lexicon' => $lexicon,
            'strongsNumber' => $strongsNumber,
        ]);
    }

    /**
     * Export lexicon data
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $format = $request->get('format', 'json');

        $data = $this->strongsService->exportLexiconData($format);

        $filename = 'strongs_lexicon_' . date('Y-m-d');

        $response = response($data);

        if ($format === 'csv') {
            $response->header('Content-Type', 'text/csv');
            $response->header('Content-Disposition', "attachment; filename=\"{$filename}.csv\"");
        } else {
            $response->header('Content-Type', 'application/json');
            $response->header('Content-Disposition', "attachment; filename=\"{$filename}.json\"");
        }

        return $response;
    }

    /**
     * Random Strong's number for study
     */
    public function random(): JsonResponse
    {
        $lexicon = StrongsLexicon::inRandomOrder()->first();

        if (!$lexicon) {
            return response()->json(['error' => 'No lexicon entries found'], 404);
        }

        return response()->json([
            'strongs_number' => $lexicon->strongs_number,
            'url' => route('strongs.show', $lexicon->strongs_number),
            'preview' => [
                'original_word' => $lexicon->original_word,
                'transliteration' => $lexicon->transliteration,
                'short_definition' => $lexicon->short_definition,
                'language' => $lexicon->language
            ]
        ]);
    }

    /**
     * Statistics dashboard
     */
    public function stats(): Response
    {
        $stats = [
            'total_entries' => StrongsLexicon::count(),
            'hebrew_count' => StrongsLexicon::where('language', 'Hebrew')->count(),
            'greek_count' => StrongsLexicon::where('language', 'Greek')->count(),
            'aramaic_count' => StrongsLexicon::where('language', 'Aramaic')->count(),
            'with_pronunciation' => StrongsLexicon::whereNotNull('pronunciation')->count(),
            'total_relationships' => WordRelationship::count(),
            'relationship_types' => WordRelationship::select('relationship_type')
                ->groupBy('relationship_type')
                ->orderBy('relationship_type')
                ->get()
                ->map(function ($item) {
                    return [
                        'type' => $item->relationship_type,
                        'count' => WordRelationship::where('relationship_type', $item->relationship_type)->count()
                    ];
                })
        ];

        $topWords = StrongsLexicon::orderBy('occurrence_count', 'desc')
            ->limit(20)
            ->get();

        $languageDistribution = StrongsLexicon::select('language')
            ->groupBy('language')
            ->get()
            ->map(function ($item) {
                return [
                    'language' => $item->language,
                    'count' => StrongsLexicon::where('language', $item->language)->count()
                ];
            });

        return Inertia::render('Strongs/Stats', [
            'stats' => $stats,
            'topWords' => $topWords,
            'languageDistribution' => $languageDistribution,
        ]);
    }
}
