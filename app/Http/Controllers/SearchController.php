<?php

namespace App\Http\Controllers;

class SearchController extends Controller
{
    /**
     * Display the search page
     * GET /searches
     */
    public function index()
    {
        return view('livewire-search');
    }

    /**
     * Store a new search (perform search)
     * POST /searches
     */
    public function store()
    {
        // This would handle search form submission if needed
        // For now, searches are handled by Livewire components
        return redirect()->route('searches.index');
    }
}
