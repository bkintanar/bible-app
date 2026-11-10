<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * GET /searches - the search page (Livewire BibleSearch; reads ?q=, and legacy ?query=).
     */
    public function index(): View
    {
        return view('livewire-search');
    }

    /**
     * GET /search - old address. Route::redirect() would drop the query string (?q=love).
     */
    public function legacy(Request $request): RedirectResponse
    {
        return redirect()->route('searches.index', $request->query(), 301);
    }
}
