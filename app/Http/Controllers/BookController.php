<?php

namespace App\Http\Controllers;

use App\Support\ResumePosition;
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
        // Case variants redirect to the one canonical URL (/books/john -> /books/John).
        $canonical = $this->bibleService->canonicalBookId($bookOsisId);
        abort_if($canonical === null, 404);

        if ($canonical !== $bookOsisId) {
            return redirect()->route('books.show', ['bookOsisId' => $canonical], 301);
        }

        ResumePosition::remember($bookOsisId);

        return view('livewire-book', compact('bookOsisId'));
    }
}
