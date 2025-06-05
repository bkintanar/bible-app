<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BibleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UserSessionController;

// =======================================
// MAIN APPLICATION ROUTES
// =======================================

// Home/Index route
Route::get('/', [BibleController::class, 'index'])->name('bible.index');

// RESTful Search routes
Route::resource('searches', SearchController::class)->only(['index', 'store']);

// RESTful Translation routes (singleton resource)
Route::singleton('translation', TranslationController::class)->only(['show', 'update']);

// RESTful User Session routes (singleton resource)
Route::singleton('user-session', UserSessionController::class)->only(['show', 'update']);
Route::delete('user-session', [UserSessionController::class, 'destroy'])->name('user-session.destroy');

// POC route for testing search component
Route::get('/livewire-search-poc', function () {
    return view('livewire-search-poc');
})->name('bible.search.poc');

// =======================================
// RESTFUL RESOURCE ROUTES
// =======================================

// Books resource routes
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{bookOsisId}', [BookController::class, 'show'])->name('books.show');

// Chapters resource routes (nested under books)
Route::get('/books/{bookOsisId}/chapters/{chapterNumber}', [ChapterController::class, 'show'])->name('chapters.show');

// =======================================
// LEGACY ROUTES (for backward compatibility)
// =======================================

// Short URLs - redirect to RESTful routes
Route::get('/{bookOsisId}/{chapterNumber}', function ($bookOsisId, $chapterNumber) {
    return redirect()->route('chapters.show', ['bookOsisId' => $bookOsisId, 'chapterNumber' => $chapterNumber]);
})->name('bible.chapter');

Route::get('/{bookOsisId}', function ($bookOsisId) {
    return redirect()->route('books.show', ['bookOsisId' => $bookOsisId]);
})->name('bible.book');
