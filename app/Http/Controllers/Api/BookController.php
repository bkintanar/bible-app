<?php

namespace App\Http\Controllers\Api;

use App\Services\BibleService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;

class BookController extends Controller
{
    use ApiResponseTrait;

    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Get all books
     * GET /api/books
     */
    public function index(): JsonResponse
    {
        try {
            $books = $this->bibleService->getBooks();

            return $this->successResponse($books->toArray(), 'Books retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve books', 500);
        }
    }

    /**
     * Get a specific book with its chapters
     * GET /api/books/{bookOsisId}
     */
    public function show(string $bookOsisId): JsonResponse
    {
        try {
            if (! $this->bibleService->bookExists($bookOsisId)) {
                return $this->notFoundResponse('Book not found');
            }

            $book = $this->bibleService->getBook($bookOsisId);
            $chapters = $this->bibleService->getChapters($bookOsisId);

            $data = [
                'book' => $book,
                'chapters' => $chapters->toArray(),
                'total_chapters' => $chapters->count(),
            ];

            return $this->successResponse($data, 'Book retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve book', 500);
        }
    }

    /**
     * Get chapters for a specific book
     * GET /api/books/{bookOsisId}/chapters
     */
    public function chapters(string $bookOsisId): JsonResponse
    {
        try {
            if (! $this->bibleService->bookExists($bookOsisId)) {
                return $this->notFoundResponse('Book not found');
            }

            $chapters = $this->bibleService->getChapters($bookOsisId);

            return $this->successResponse($chapters->toArray(), 'Chapters retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve chapters', 500);
        }
    }
}
