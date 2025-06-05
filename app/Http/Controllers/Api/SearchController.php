<?php

namespace App\Http\Controllers\Api;

use App\Services\BibleService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Traits\ApiResponseTrait;

class SearchController extends Controller
{
    use ApiResponseTrait;

    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Search verses
     * GET /api/search
     */
    public function search(SearchRequest $request): JsonResponse
    {
        try {
            $params = $request->getSearchParameters();
            $searchTerm = $params['query'];
            $limit = $params['limit'];
            $searchType = $params['type'];

            $startTime = microtime(true);

            switch ($searchType) {
                case 'strongs':
                    if (! $this->bibleService->hasEnhancedFeatures()) {
                        return $this->errorResponse('Strong\'s search not available', 400);
                    }
                    $results = $this->bibleService->searchByStrongsNumber($searchTerm, $limit);
                    $hasMoreResults = false;
                    break;

                case 'text':
                default:
                    $results = $this->bibleService->searchVerses($searchTerm, $limit + 1);
                    $hasMoreResults = $results->count() > $limit;
                    if ($hasMoreResults) {
                        $results = $results->take($limit);
                    }
                    break;
            }

            $timeMs = round((microtime(true) - $startTime) * 1000, 2);

            $formattedResults = $results->map(function ($result) {
                return [
                    'book_osis_id' => $result['book_osis_id'] ?? '',
                    'chapter' => $result['chapter'] ?? 1,
                    'verse' => $result['verse'] ?? 1,
                    'reference' => $result['reference'] ?? '',
                    'text' => $result['text'] ?? '',
                ];
            })->toArray();

            $data = [
                'query' => $searchTerm,
                'type' => $searchType,
                'results' => $formattedResults,
                'total_found' => count($formattedResults),
                'has_more_results' => $hasMoreResults,
                'search_time_ms' => $timeMs,
                'limit' => $limit,
            ];

            return $this->successResponse($data, 'Search completed successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Search failed');
        }
    }

    /**
     * Search suggestions/autocomplete
     * GET /api/search/suggestions
     */
    public function suggestions(SearchRequest $request): JsonResponse
    {
        try {
            $params = $request->getSearchParameters();
            $query = $params['query'];

            // This could be expanded to provide search suggestions
            // For now, return basic suggestions based on common terms
            $suggestions = $this->generateSearchSuggestions($query);

            return $this->successResponse([
                'query' => $query,
                'suggestions' => $suggestions,
            ], 'Suggestions retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve suggestions');
        }
    }

    /**
     * Get search history (if implemented)
     * GET /api/search/history
     */
    public function history(): JsonResponse
    {
        try {
            // This could be implemented to return user's search history
            // For now, return empty array
            return $this->successResponse([
                'history' => [],
            ], 'Search history retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve search history');
        }
    }

    /**
     * Generate search suggestions based on query
     */
    private function generateSearchSuggestions(string $query): array
    {
        // Simple suggestion logic - could be expanded with actual database lookups
        $commonTerms = [
            'love', 'faith', 'hope', 'peace', 'joy', 'grace', 'truth', 'light',
            'salvation', 'forgiveness', 'mercy', 'wisdom', 'strength', 'prayer',
        ];

        return array_filter($commonTerms, function ($term) use ($query) {
            return stripos($term, $query) !== false;
        });
    }
}
