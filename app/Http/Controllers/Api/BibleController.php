<?php

namespace App\Http\Controllers\Api;

use App\Services\BibleService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;

class BibleController extends Controller
{
    use ApiResponseTrait;

    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Get Bible capabilities and metadata
     * GET /api/capabilities
     */
    public function capabilities(): JsonResponse
    {
        try {
            $capabilities = $this->bibleService->getCapabilities();

            return $this->successResponse($capabilities, 'Capabilities retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve capabilities', 500);
        }
    }
}
