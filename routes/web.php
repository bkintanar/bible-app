<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BibleController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\NoIndex;
use Illuminate\Support\Facades\Route;

// =======================================
// MAIN APPLICATION ROUTES
// =======================================

// Home/Index route
Route::get('/', [BibleController::class, 'index'])->name('bible.index');

// Authentication routes removed for testing

// Authentication. No registration route by design: this is a single-editor app on a public
// domain, so accounts are created on the box with `php artisan admin:create`.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/login', [LoginController::class, 'show'])->middleware(NoIndex::class)->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Verse title management (Livewire component). WRITES THE OSIS SOURCE OF TRUTH, and the site
// is public, so it is behind auth. auth.session logs out every other session when the password
// changes (admin:create); AppServiceProvider makes it apply to Livewire round-trips too.
Route::middleware(['auth', 'auth.session', NoIndex::class])->group(function () {
    Route::get('/admin/verse-titles', [App\Http\Controllers\VerseTitleController::class, 'index'])->name('admin.verse-titles');
});

// Search. GET only: the page is a Livewire component, so there was never anything to POST.
// /search (old links, and BibleBook used to send readers there) must be registered before the
// single-segment catch-all below, which would read "search" as a book.
Route::get('/searches', [SearchController::class, 'index'])->name('searches.index');
Route::get('/search', [SearchController::class, 'legacy'])->name('searches.legacy');

// =======================================
// RESTFUL RESOURCE ROUTES
// =======================================

// Books resource routes
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{bookOsisId}', [BookController::class, 'show'])->name('books.show');

// Chapters resource routes (nested under books)
Route::get('/books/{bookOsisId}/chapters/{chapterNumber}', [ChapterController::class, 'show'])->where('chapterNumber', '[0-9]{1,3}')->name('chapters.show');

// =======================================
// TESTING ROUTES (must be before dynamic routes)
// =======================================

// =======================================
// LEGACY ROUTES (for backward compatibility)
// =======================================

// Short URLs (/Gen/1, /John) -> the RESTful routes, permanently, and only for real books.
// The book pattern excludes "api" so /api and /api/... reach the JSON 404 instead. (No ^/$ anchors:
// Symfony strips them from requirements, and an unanchored (?!api$) would still let /api/nope in.)
Route::get('/{bookOsisId}/{chapterNumber}', [LegacyRedirectController::class, 'chapter'])
    ->where(['bookOsisId' => '(?!api\b)[1-3]?[A-Za-z]+', 'chapterNumber' => '[0-9]{1,3}'])
    ->name('bible.chapter');

Route::get('/{bookOsisId}', [LegacyRedirectController::class, 'book'])
    ->where('bookOsisId', '(?!api\b)[1-3]?[A-Za-z]+')
    ->name('bible.book');
