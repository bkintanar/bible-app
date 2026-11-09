<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use Illuminate\Support\Facades\Cache;

class ChapterController extends Controller
{
    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Display a specific chapter
     * GET /books/{bookOsisId}/chapters/{chapterNumber}
     */
    public function show(string $bookOsisId, int $chapterNumber)
    {
        // Validate the book and chapter exist
        if (! $this->bibleService->chapterExists($bookOsisId, $chapterNumber)) {
            abort(404, "Chapter not found: {$bookOsisId} {$chapterNumber}");
        }

        UserSessionController::storeLastVisitedPage('chapters.show', [
            'bookOsisId' => $bookOsisId,
            'chapterNumber' => $chapterNumber,
        ]);

        // Cache the current chapter data
        $this->cacheChapterData($bookOsisId, $chapterNumber);

        // Preload and cache adjacent chapters in background
        $this->preloadAdjacentChapters($bookOsisId, $chapterNumber);

        return view('livewire-chapter', compact('bookOsisId', 'chapterNumber'));
    }

    /**
     * Cache chapter data for faster loading
     */
    private function cacheChapterData(string $bookOsisId, int $chapterNumber): void
    {
        $cacheKey = "chapter_data_{$bookOsisId}_{$chapterNumber}";

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, [
                'bookOsisId' => $bookOsisId,
                'chapterNumber' => $chapterNumber,
                'cached_at' => now(),
            ], now()->addHours(24)); // Cache for 24 hours

            \Log::debug("📚 Cached chapter data: {$bookOsisId} {$chapterNumber}");
        }
    }

    /**
     * Preload and cache previous and next chapters for faster navigation
     */
    private function preloadAdjacentChapters(string $bookOsisId, int $chapterNumber): void
    {
        // Get max chapter number for this book
        $chapters = $this->bibleService->getChapters($bookOsisId);
        $maxChapterNumber = $chapters->max('chapter_number') ?? 0;

        // Cache previous chapter if it exists
        if ($chapterNumber > 1) {
            $this->cacheChapterVerses($bookOsisId, $chapterNumber - 1);
        }

        // Cache next chapter if it exists
        if ($chapterNumber < $maxChapterNumber) {
            $this->cacheChapterVerses($bookOsisId, $chapterNumber + 1);
        }

        \Log::debug("🔄 Preloaded adjacent chapters for {$bookOsisId} {$chapterNumber}");
    }

    /**
     * Cache chapter verses data for a specific chapter
     */
    private function cacheChapterVerses(string $bookOsisId, int $chapterNumber): void
    {
        $cacheKey = "chapter_verses_{$bookOsisId}_{$chapterNumber}";

        if (! Cache::has($cacheKey)) {
            try {
                $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;

                // Get verses - use same logic as BibleChapter component
                if (strtolower($bookOsisId) === 'ps' && $chapterNumber == 119) {
                    // Get individual verses for Psalm 119 to show acrostic titles
                    $individualVerses = $this->bibleService->getVerses($chapterOsisRef);
                    $verses = $individualVerses->map(function ($verse) {
                        return [
                            'verses' => [$verse],
                            'type' => 'individual_verse',
                        ];
                    });
                } else {
                    // Get verses grouped by paragraphs for better reading experience
                    $verses = $this->bibleService->getVersesParagraphStyle($chapterOsisRef);
                }

                // Never cache an empty result (see getCachedChapterVerses).
                if ($verses->isEmpty()) {
                    return;
                }

                // Cache the verses data
                Cache::put($cacheKey, [
                    'verses' => $verses,
                    'bookOsisId' => $bookOsisId,
                    'chapterNumber' => $chapterNumber,
                    'cached_at' => now(),
                ], now()->addHours(24)); // Cache for 24 hours

                \Log::debug("📖 Cached verses for {$bookOsisId} {$chapterNumber}");

            } catch (\Exception $e) {
                \Log::error("❌ Failed to cache verses for {$bookOsisId} {$chapterNumber}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get cached chapter verses if available
     */
    public static function getCachedChapterVerses(string $bookOsisId, int $chapterNumber): ?array
    {
        $cacheKey = "chapter_verses_{$bookOsisId}_{$chapterNumber}";
        $cached = Cache::get($cacheKey);

        // An empty payload is never a valid chapter. Treat it as a miss: the reader trusts this
        // cache for 24h, so a cached empty result made a real chapter 404 for a day (it is how
        // 19 chapters of 1 Samuel .. 1 John stayed 404 after the lookup bug itself was fixed).
        if (! is_array($cached) || collect($cached['verses'] ?? [])->isEmpty()) {
            return null;
        }

        return $cached;
    }

    /**
     * Clear chapter cache for a specific book or all chapters
     */
    public static function clearChapterCache(?string $bookOsisId = null): void
    {
        if ($bookOsisId) {
            // Clear cache for specific book
            $pattern = "chapter_*_{$bookOsisId}_*";
            // Note: Laravel doesn't have pattern-based cache clearing by default
            // This would need a more sophisticated cache tagging system
            \Log::debug("🗑️ Requested cache clear for book: {$bookOsisId}");
        } else {
            // Clear all chapter cache
            Cache::flush();
            \Log::debug('🗑️ Cleared all chapter cache');
        }
    }

    /**
     * Get cache statistics and performance info
     */
    public static function getCacheStats(): array
    {
        // This is a simple implementation - for production you'd want more sophisticated metrics
        return [
            'cache_driver' => config('cache.default'),
            'cache_enabled' => Cache::getStore() !== null,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Warm up cache for a specific book by preloading all chapters
     */
    public function warmupBookCache(string $bookOsisId): array
    {
        $startTime = microtime(true);
        $chapters = $this->bibleService->getChapters($bookOsisId);
        $totalChapters = $chapters->count();
        $cachedChapters = 0;
        $errors = [];

        \Log::debug("🔥 Starting cache warmup for {$bookOsisId} ({$totalChapters} chapters)");

        foreach ($chapters as $chapter) {
            try {
                $this->cacheChapterVerses($bookOsisId, $chapter['chapter_number']);
                $cachedChapters++;
            } catch (\Exception $e) {
                $errors[] = "Chapter {$chapter['chapter_number']}: " . $e->getMessage();
                \Log::error("❌ Warmup failed for {$bookOsisId} {$chapter['chapter_number']}: " . $e->getMessage());
            }
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $result = [
            'book' => $bookOsisId,
            'total_chapters' => $totalChapters,
            'cached_chapters' => $cachedChapters,
            'errors' => $errors,
            'duration_ms' => $duration,
            'success_rate' => round(($cachedChapters / $totalChapters) * 100, 1),
        ];

        \Log::debug("🔥 Cache warmup completed for {$bookOsisId}: {$cachedChapters}/{$totalChapters} chapters in {$duration}ms");

        return $result;
    }
}
