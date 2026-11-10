<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use App\Support\ResumePosition;
use Illuminate\Http\RedirectResponse;

class BibleController extends Controller
{
    /**
     * GET / - back to where the reader left off (session, then the `resume` cookie), else Genesis 1.
     * The remembered position is client-controlled, so it is only followed when it exists.
     */
    public function index(BibleService $bible): RedirectResponse
    {
        $position = ResumePosition::get();

        if ($position !== null && $position['chapter'] !== null && $bible->chapterExists($position['book'], $position['chapter'])) {
            return redirect()->route('chapters.show', ['bookOsisId' => $position['book'], 'chapterNumber' => $position['chapter']]);
        }

        if ($position !== null && $position['chapter'] === null && $bible->bookExists($position['book'])) {
            return redirect()->route('books.show', ['bookOsisId' => $position['book']]);
        }

        return redirect()->route('chapters.show', ['bookOsisId' => 'Gen', 'chapterNumber' => 1]);
    }
}
