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
                    'verse' => $result['verse_number'] ?? null,
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
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('API failure', ['route' => request()->path(), 'exception' => $e]);
            return $this->serverErrorResponse('Search failed');
        }
    }
}
