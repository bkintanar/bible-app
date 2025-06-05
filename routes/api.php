<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BibleController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ChapterController;

// =======================================
// BIBLE API ROUTES
// =======================================

// Search endpoints
Route::get('/search', [SearchController::class, 'search'])->name('api.search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('api.search.suggestions');
Route::get('/search/history', [SearchController::class, 'history'])->name('api.search.history');

// General Bible endpoints
Route::get('/capabilities', [BibleController::class, 'capabilities'])->name('api.capabilities');

// Books API
Route::get('/books', [BookController::class, 'index'])->name('api.books.index');
Route::get('/books/{bookOsisId}', [BookController::class, 'show'])->name('api.books.show');
Route::get('/books/{bookOsisId}/chapters', [BookController::class, 'chapters'])->name('api.books.chapters');

// Chapters API (nested under books)
Route::get('/books/{bookOsisId}/chapters/{chapterNumber}/verses', [ChapterController::class, 'verses'])->name('api.chapters.verses');
Route::get('/books/{bookOsisId}/chapters/{chapterNumber}/verses/{verseNumber}', [ChapterController::class, 'verse'])->name('api.chapters.verse');
