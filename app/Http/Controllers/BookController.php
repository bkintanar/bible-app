<?php

namespace App\Http\Controllers;

use App\Services\BibleService;

class BookController extends Controller
{
    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Display a listing of all books (redirect to Genesis 1)
     * GET /books
     */
    public function index()
    {
        // Redirect to Genesis 1 since we no longer have a books listing page
        return redirect()->route('chapters.show', ['bookOsisId' => 'Gen', 'chapterNumber' => 1]);
    }

    /**
     * Display a specific book overview
     * GET /books/{bookOsisId}
     */
    public function show(string $bookOsisId)
    {
        // Validate the book exists
        if (! $this->bibleService->bookExists($bookOsisId)) {
            abort(404, 'Book not found');
        }

        UserSessionController::storeLastVisitedPage('books.show', [
            'bookOsisId' => $bookOsisId,
        ]);

        return view('livewire-book', compact('bookOsisId'));
    }
}
