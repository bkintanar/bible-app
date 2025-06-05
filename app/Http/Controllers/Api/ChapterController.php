<?php

namespace App\Http\Controllers\Api;

use App\Services\BibleService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;

class ChapterController extends Controller
{
    use ApiResponseTrait;

    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Get verses for a specific chapter
     * GET /api/books/{bookOsisId}/chapters/{chapterNumber}/verses
     */
    public function verses(string $bookOsisId, int $chapterNumber): JsonResponse
    {
        try {
            if (! $this->bibleService->chapterExists($bookOsisId, $chapterNumber)) {
                return $this->notFoundResponse('Chapter not found');
            }

            $chapterOsisRef = "{$bookOsisId}.{$chapterNumber}";
            $verses = $this->bibleService->getVerses($chapterOsisRef);

            $data = [
                'book_osis_id' => $bookOsisId,
                'chapter_number' => $chapterNumber,
                'chapter_reference' => $chapterOsisRef,
                'verses' => $verses->toArray(),
                'total_verses' => $verses->count(),
            ];

            return $this->successResponse($data, 'Verses retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve verses', 500);
        }
    }

    /**
     * Get a specific verse
     * GET /api/books/{bookOsisId}/chapters/{chapterNumber}/verses/{verseNumber}
     */
    public function verse(string $bookOsisId, int $chapterNumber, int $verseNumber): JsonResponse
    {
        try {
            $verseOsisId = "{$bookOsisId}.{$chapterNumber}.{$verseNumber}";

            if ($this->bibleService->hasEnhancedFeatures()) {
                $verseDetails = $this->bibleService->getVerseWithDetails($verseOsisId);
            } else {
                $verse = $this->bibleService->getVerseByReference($bookOsisId, $chapterNumber, $verseNumber);
                $verseDetails = $verse ? ['verse' => $verse] : null;
            }

            if (! $verseDetails) {
                return $this->notFoundResponse('Verse not found');
            }

            return $this->successResponse($verseDetails, 'Verse retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve verse', 500);
        }
    }
}
