<?php

use App\Http\Controllers\BibleController;
use Illuminate\Support\Facades\Route;

// Bible routes (main application)
Route::get('/', [BibleController::class, 'livewireIndex'])->name('bible.index');
Route::get('/search', [BibleController::class, 'livewireSearch'])->name('bible.search');
Route::post('/switch-translation', [BibleController::class, 'switchTranslation'])->name('bible.switch-translation');
Route::post('/clear-last-visited', [BibleController::class, 'clearLastVisited'])->name('bible.clear-last-visited');

// Bible chapter reading route (more specific - must come first)
Route::get('/{bookOsisId}/{chapterNumber}', [BibleController::class, 'livewireChapter'])->name('bible.chapter');

// Bible book viewing route (less specific - comes after)
Route::get('/{bookOsisId}', [BibleController::class, 'livewireBook'])->name('bible.book');

// API routes for Bible data
Route::prefix('api')->group(function () {
    Route::get('/books', [BibleController::class, 'apiBooks'])->name('api.bible.books');
    Route::get('/capabilities', [BibleController::class, 'apiCapabilities'])->name('api.bible.capabilities');
    Route::get('/search', [BibleController::class, 'apiSearch'])->name('api.bible.search');
    Route::get('/{bookOsisId}/chapters', [BibleController::class, 'apiChapters'])->name('api.bible.chapters');
    Route::get('/{bookOsisId}/{chapterNumber}/verses', [BibleController::class, 'apiVerses'])->name('api.bible.verses');
    Route::get('/{bookOsisId}/{chapterNumber}/{verseNumber}', [BibleController::class, 'apiVerseDetails'])->name('api.bible.verse.details');
});
