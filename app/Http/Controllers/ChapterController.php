<?php

namespace App\Http\Controllers;

use App\Services\BibleService;

class ChapterController extends Controller
{
    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Display a specific chapter
     * GET /books/{bookOsisId}/chapters/{chapterNumber}
     */
    public function show(string $bookOsisId, int $chapterNumber)
    {
        // Validate the book and chapter exist
        if (! $this->bibleService->chapterExists($bookOsisId, $chapterNumber)) {
            dd(config('database.connections.sqlite'));
        }

        UserSessionController::storeLastVisitedPage('chapters.show', [
            'bookOsisId' => $bookOsisId,
            'chapterNumber' => $chapterNumber,
        ]);

        return view('livewire-chapter', compact('bookOsisId', 'chapterNumber'));
    }
}
