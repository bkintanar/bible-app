<?php

use App\Http\Controllers\BibleController;
use App\Http\Controllers\StrongsController;
use Illuminate\Support\Facades\Route;

// Bible routes (main application)
Route::get('/', [BibleController::class, 'index'])->name('bible.index');
Route::get('/search', [BibleController::class, 'search'])->name('bible.search');
Route::post('/switch-translation', [BibleController::class, 'switchTranslation'])->name('bible.switch-translation');

// Strong's Concordance routes
Route::prefix('strongs')->name('strongs.')->group(function () {
    Route::get('/', [StrongsController::class, 'index'])->name('index');
    Route::get('/stats', [StrongsController::class, 'stats'])->name('stats');
    Route::get('/compare', [StrongsController::class, 'compare'])->name('compare');
    Route::get('/export', [StrongsController::class, 'export'])->name('export');
    Route::get('/random', [StrongsController::class, 'random'])->name('random');
    Route::get('/{strongsNumber}', [StrongsController::class, 'show'])->name('show');
    Route::get('/{strongsNumber}/family', [StrongsController::class, 'family'])->name('family');
    Route::get('/{strongsNumber}/verses', [StrongsController::class, 'verses'])->name('verses');
    Route::get('/{strongsNumber}/relationships', [StrongsController::class, 'relationships'])->name('relationships');
});

Route::get('/{bookOsisId}', [BibleController::class, 'book'])->name('bible.book');
Route::get('/{bookOsisId}/{chapterNumber}', [BibleController::class, 'chapter'])->name('bible.chapter');
Route::get('/{bookOsisId}/{chapterNumber}/{verseNumber}', [BibleController::class, 'verse'])->name('bible.verse');

// API routes for Bible data
Route::prefix('api')->group(function () {
    Route::get('/books', [BibleController::class, 'apiBooks'])->name('api.bible.books');
    Route::get('/capabilities', [BibleController::class, 'apiCapabilities'])->name('api.bible.capabilities');
    Route::get('/{bookOsisId}/chapters', [BibleController::class, 'apiChapters'])->name('api.bible.chapters');
    Route::get('/{bookOsisId}/{chapterNumber}/verses', [BibleController::class, 'apiVerses'])->name('api.bible.verses');
    Route::get('/{bookOsisId}/{chapterNumber}/{verseNumber}', [BibleController::class, 'apiVerseDetails'])->name('api.bible.verse.details');
    Route::get('/search', [BibleController::class, 'apiSearch'])->name('api.bible.search');

    // Strong's API routes
    Route::prefix('strongs')->name('api.strongs.')->group(function () {
        Route::get('/search', [StrongsController::class, 'search'])->name('search');
        Route::get('/{strongsNumber}/verses', [StrongsController::class, 'verses'])->name('verses');
        Route::get('/{strongsNumber}/relationships', [StrongsController::class, 'relationships'])->name('relationships');
    });
});
