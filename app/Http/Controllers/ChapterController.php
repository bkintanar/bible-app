<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use App\Support\ResumePosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChapterController extends Controller
{
    public function __construct(private BibleService $bibleService)
    {
    }

    /**
     * GET /books/{bookOsisId}/chapters/{chapterNumber}
     *
     * The chapter and its neighbours are read straight from the projection (indexed reads,
     * ~0.02 ms). The chapter cache and the background job that filled it are gone: they
     * existed to feed the neighbours into the Livewire snapshot, which no longer carries them.
     */
    public function show(string $bookOsisId, int $chapterNumber): View|RedirectResponse
    {
        // One URL per chapter: /books/gen/chapters/1 -> /books/Gen/chapters/1 (permanent).
        $canonical = $this->bibleService->canonicalBookId($bookOsisId);
        abort_if($canonical === null, 404);

        if ($canonical !== $bookOsisId) {
            return redirect()->route('chapters.show', ['bookOsisId' => $canonical, 'chapterNumber' => $chapterNumber], 301);
        }

        abort_unless($this->bibleService->chapterExists($bookOsisId, $chapterNumber), 404);

        ResumePosition::remember($bookOsisId, $chapterNumber);

        return view('livewire-chapter', compact('bookOsisId', 'chapterNumber'));
    }
}
