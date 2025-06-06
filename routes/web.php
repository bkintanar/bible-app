<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\BookController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UserSessionController;

// =======================================
// MAIN APPLICATION ROUTES
// =======================================

// Home/Index route
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Authentication routes removed for testing

// Verse title management (Livewire component)
Route::get('/admin/verse-titles', [App\Http\Controllers\VerseTitleController::class, 'index'])->name('admin.verse-titles');

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
// TESTING ROUTES (must be before dynamic routes)
// =======================================

// Test caching performance
Route::get('/test-cache/{bookOsisId?}', function (string $bookOsisId = 'Gen') {
    $controller = new \App\Http\Controllers\ChapterController(app(\App\Services\BibleService::class));

    echo '<h1>📚 Chapter Caching System Test</h1>';
    echo '<h2>Book: ' . strtoupper($bookOsisId) . '</h2>';

    // Test caching performance
    echo '<h3>🔄 Testing Chapter Navigation Performance</h3>';

    // Simulate navigation to chapter 1
    $startTime = microtime(true);
    $cached1 = \App\Http\Controllers\ChapterController::getCachedChapterVerses($bookOsisId, 1);
    $time1 = round((microtime(true) - $startTime) * 1000, 2);

    echo '<p><strong>Chapter 1 Cache Check:</strong> ' . ($cached1 ? 'HIT' : 'MISS') . ' (' . $time1 . 'ms)</p>';

    // Navigate to chapter 1 (will cache adjacent chapters)
    $startTime = microtime(true);
    // Simulate the controller call
    if (! $cached1) {
        $response = app()->call([\App\Http\Controllers\ChapterController::class, 'show'], [
            'bookOsisId' => $bookOsisId,
            'chapterNumber' => 1,
        ]);
    }
    $navTime1 = round((microtime(true) - $startTime) * 1000, 2);
    echo '<p><strong>Chapter 1 Navigation:</strong> ' . $navTime1 . 'ms</p>';

    // Check if chapter 2 was preloaded
    $startTime = microtime(true);
    $cached2 = \App\Http\Controllers\ChapterController::getCachedChapterVerses($bookOsisId, 2);
    $time2 = round((microtime(true) - $startTime) * 1000, 2);

    echo '<p><strong>Chapter 2 Cache Check (after preload):</strong> ' . ($cached2 ? 'HIT' : 'MISS') . ' (' . $time2 . 'ms)</p>';

    // Navigate to chapter 2 (should be from cache)
    $startTime = microtime(true);
    if ($cached2) {
        $verses = $cached2['verses'];
        $verseCount = is_countable($verses) ? count($verses) : 0;
        echo '<p><strong>Chapter 2 from Cache:</strong> ' . $verseCount . ' paragraphs loaded in ' . round((microtime(true) - $startTime) * 1000, 2) . 'ms ⚡</p>';
    }

    // Cache stats
    echo '<h3>📊 Cache Statistics</h3>';
    $stats = \App\Http\Controllers\ChapterController::getCacheStats();
    echo '<ul>';
    foreach ($stats as $key => $value) {
        echo '<li><strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</li>';
    }
    echo '</ul>';

    // Cache warmup test
    echo '<h3>🔥 Cache Warmup Test</h3>';
    echo '<p><em>Warming up cache for ' . strtoupper($bookOsisId) . '...</em></p>';

    $warmupResult = $controller->warmupBookCache($bookOsisId);
    echo '<p><strong>Warmup Results:</strong></p>';
    echo '<ul>';
    echo '<li>Total Chapters: ' . $warmupResult['total_chapters'] . '</li>';
    echo '<li>Cached Chapters: ' . $warmupResult['cached_chapters'] . '</li>';
    echo '<li>Success Rate: ' . $warmupResult['success_rate'] . '%</li>';
    echo '<li>Duration: ' . $warmupResult['duration_ms'] . 'ms</li>';
    echo '</ul>';

    if (! empty($warmupResult['errors'])) {
        echo '<p><strong>Errors:</strong></p>';
        echo '<ul>';
        foreach ($warmupResult['errors'] as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
    }

    echo '<hr>';
    echo '<p><a href="/Gen/1">← Test Genesis 1</a> | <a href="/test-cache/Mat">Test Matthew</a> | <a href="/test-cache/Ps">Test Psalms</a></p>';

})->name('test.cache');

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
