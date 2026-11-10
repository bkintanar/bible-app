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
Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:search')->name('api.search');

// General Bible endpoints
Route::get('/capabilities', [BibleController::class, 'capabilities'])->name('api.capabilities');

// Books API
Route::get('/books', [BookController::class, 'index'])->name('api.books.index');
Route::get('/books/{bookOsisId}', [BookController::class, 'show'])->name('api.books.show');
Route::get('/books/{bookOsisId}/chapters', [BookController::class, 'chapters'])->name('api.books.chapters');

// Chapters API (nested under books)
Route::get('/books/{bookOsisId}/chapters/{chapterNumber}/verses', [ChapterController::class, 'verses'])->whereNumber('chapterNumber')->name('api.chapters.verses');
Route::get('/books/{bookOsisId}/chapters/{chapterNumber}/verses/{verseNumber}', [ChapterController::class, 'verse'])->whereNumber(['chapterNumber', 'verseNumber'])->name('api.chapters.verse');
