<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Jobs\CacheAdjacentChaptersJob;

class TestBackgroundCachingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:test-cache {book=Gen} {chapter=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test background caching for adjacent chapters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $book = $this->argument('book');
        $chapter = (int) $this->argument('chapter');

        $this->info("🧪 Testing background caching for {$book} {$chapter}");

        // Clear existing cache for these chapters
        $this->line('🗑️ Clearing existing cache...');
        Cache::forget("chapter_verses_{$book}_" . ($chapter - 1));
        Cache::forget("chapter_verses_{$book}_{$chapter}");
        Cache::forget("chapter_verses_{$book}_" . ($chapter + 1));

        // Dispatch the background job
        $this->line('🔄 Dispatching background job...');
        CacheAdjacentChaptersJob::dispatch($book, $chapter);

        // Show queue status
        $this->info('✅ Background job dispatched!');
        $this->line('💡 To process the queue:');
        $this->line('   php artisan queue:work');
        $this->line('');
        $this->line('💡 To check cache status after processing:');
        $this->line("   Cache for {$book} " . ($chapter - 1) . ': ' . (Cache::has("chapter_verses_{$book}_" . ($chapter - 1)) ? '✅ Cached' : '❌ Not cached'));
        $this->line("   Cache for {$book} " . ($chapter + 1) . ': ' . (Cache::has("chapter_verses_{$book}_" . ($chapter + 1)) ? '✅ Cached' : '❌ Not cached'));

        return 0;
    }
}
