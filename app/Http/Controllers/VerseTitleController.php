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
        return view('admin.verse-titles');
    }
}
