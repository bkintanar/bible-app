<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Services\BibleService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CacheAdjacentChaptersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $bookOsisId;
    public $chapterNumber;
    public $timeout = 60; // 1 minute timeout
    public $tries = 3; // Retry up to 3 times

    /**
     * Create a new job instance.
     */
    public function __construct(string $bookOsisId, int $chapterNumber)
    {
        $this->bookOsisId = $bookOsisId;
        $this->chapterNumber = $chapterNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(BibleService $bibleService): void
    {
        \Log::info("🔄 Background job: Caching adjacent chapters for {$this->bookOsisId} {$this->chapterNumber}");

        try {
            // Get chapters to determine bounds
            $chapters = $bibleService->getChapters($this->bookOsisId);
            $maxChapterNumber = $chapters->max('chapter_number') ?? 0;

            // Cache previous chapter if it exists
            if ($this->chapterNumber > 1) {
                $this->cacheChapterVerses($bibleService, $this->bookOsisId, $this->chapterNumber - 1);
                \Log::info("📖 Cached previous chapter: {$this->bookOsisId} " . ($this->chapterNumber - 1));
            }

            // Cache next chapter if it exists
            if ($this->chapterNumber < $maxChapterNumber) {
                $this->cacheChapterVerses($bibleService, $this->bookOsisId, $this->chapterNumber + 1);
                \Log::info("📖 Cached next chapter: {$this->bookOsisId} " . ($this->chapterNumber + 1));
            }

            \Log::info("✅ Background job completed: Adjacent chapters cached for {$this->bookOsisId} {$this->chapterNumber}");

        } catch (\Exception $e) {
            \Log::error("❌ Background job failed for {$this->bookOsisId} {$this->chapterNumber}: " . $e->getMessage());
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Cache chapter verses for a specific chapter
     */
    private function cacheChapterVerses(BibleService $bibleService, string $bookOsisId, int $chapterNumber): void
    {
        $cacheKey = "chapter_verses_{$bookOsisId}_{$chapterNumber}";

        // Skip if already cached
        if (\Cache::has($cacheKey)) {
            \Log::info("📋 Chapter already cached: {$bookOsisId} {$chapterNumber}");
            return;
        }

        try {
            $chapterOsisRef = $bookOsisId . '.' . $chapterNumber;

            // Get verses - use same logic as BibleChapter component
            if (strtolower($bookOsisId) === 'ps' && $chapterNumber == 119) {
                // Get individual verses for Psalm 119 to show acrostic titles
                $individualVerses = $bibleService->getVerses($chapterOsisRef);
                $verses = $individualVerses->map(function ($verse) {
                    return [
                        'verses' => [$verse],
                        'type' => 'individual_verse',
                    ];
                });
            } else {
                // Get verses grouped by paragraphs for better reading experience
                $verses = $bibleService->getVersesParagraphStyle($chapterOsisRef);
            }

            // Cache the verses data for 24 hours
            \Cache::put($cacheKey, [
                'verses' => $verses,
                'bookOsisId' => $bookOsisId,
                'chapterNumber' => $chapterNumber,
                'cached_at' => now(),
            ], now()->addHours(24));

            \Log::info("📖 Successfully cached verses for {$bookOsisId} {$chapterNumber} (" . $verses->count() . ' paragraphs)');

        } catch (\Exception $e) {
            \Log::error("❌ Failed to cache verses for {$bookOsisId} {$chapterNumber}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error("💥 Background job permanently failed for {$this->bookOsisId} {$this->chapterNumber}: " . $exception->getMessage());
    }
}
