<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BibleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UserSessionController;

// =======================================
// MAIN APPLICATION ROUTES
// =======================================

// Home/Index route
Route::get('/', [BibleController::class, 'index'])->name('bible.index');

// Authentication routes removed for testing

// Authentication. No registration route by design: this is a single-editor app on a public
// domain, so accounts are created on the box with `php artisan admin:create`.
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Verse title management (Livewire component). WRITES THE OSIS SOURCE OF TRUTH, and the site
// is public, so it is behind auth. auth.session logs out every other session when the password
// changes (admin:create); AppServiceProvider makes it apply to Livewire round-trips too.
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/admin/verse-titles', [App\Http\Controllers\VerseTitleController::class, 'index'])->name('admin.verse-titles');
});

// Search. GET only: the page is a Livewire component, so there was never anything to POST.
// /search (old links, and BibleBook used to send readers there) must be registered before the
// single-segment catch-all below, which would read "search" as a book.
Route::get('/searches', [SearchController::class, 'index'])->name('searches.index');
Route::get('/search', [SearchController::class, 'legacy'])->name('searches.legacy');

// RESTful Translation routes (singleton resource)
Route::singleton('translation', TranslationController::class)->only(['show', 'update']);

// RESTful User Session routes (singleton resource)
Route::singleton('user-session', UserSessionController::class)->only(['show', 'update']);
Route::delete('user-session', [UserSessionController::class, 'destroy'])->name('user-session.destroy');

// =======================================
// RESTFUL RESOURCE ROUTES
// =======================================

// Books resource routes
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{bookOsisId}', [BookController::class, 'show'])->name('books.show');

// Chapters resource routes (nested under books)
Route::get('/books/{bookOsisId}/chapters/{chapterNumber}', [ChapterController::class, 'show'])->whereNumber('chapterNumber')->name('chapters.show');

// =======================================
// TESTING ROUTES (must be before dynamic routes)
// =======================================

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
