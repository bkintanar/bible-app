<?php

namespace App\Http\Controllers;

use App\Services\BibleService;

class VerseTitleController extends Controller
{
    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    public function index()
    {
        abort_unless(config('bible.editor_enabled', true), 503, 'The verse-title editor is temporarily disabled for maintenance.');

        return view('admin.verse-titles');
    }
}
