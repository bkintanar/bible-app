<?php

namespace App\Jobs;

use App\Services\Osis\OsisSourcePath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Re-project one chapter after an editor save: osis:import --chapter, off the web request.
 *
 * Why a job, and why a SUBPROCESS:
 *  - osis:import DOM-loads the whole 27MB source (~400MB RSS) whatever the scope. Inside an FPM
 *    request that grew a worker shared by ~10 sites to 400MB, for good (no pm.max_requests).
 *  - Inside the long-lived queue worker, Artisan::call would keep that DOM alive in the cached
 *    command instance, and queue:work --memory cannot see libxml memory to recycle the worker.
 *    A subprocess returns all of it when it exits.
 *
 * ShouldBeUniqueUntilProcessing (not ShouldBeUnique): the unique lock is released when the job
 * STARTS, so a second save to the same chapter while a publish is running queues another publish
 * - with ShouldBeUnique it was dropped and that edit never reached readers.
 * WithoutOverlapping(LOCK) is global: every import rebuilds the whole FTS index, so two
 * projections never run at once. A manual `osis:import` takes the same lock itself; the job's own
 * subprocess is told (LOCK_HELD_ENV) that its parent already holds it, or it would wait on itself.
 */
class PublishChapterProjection implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** The one lock every projection of the database takes (publish jobs and osis:import). */
    public const LOCK = 'osis-projection';

    /** Set on the osis:import subprocess: its parent job already holds LOCK. */
    public const LOCK_HELD_ENV = 'BIBLE_PROJECTION_LOCK_HELD';

    /** Retry for up to 30 minutes (waiting behind another publish releases the job, which would
     *  burn a flat $tries), but give up after 3 genuine failures. */
    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /** Below the queue's retry_after (config/queue.php), or a slow publish is run twice. */
    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public string $translationKey,
        public string $chapterRef,
        public ?int $userId = null,
    ) {
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function uniqueId(): string
    {
        return $this->translationKey . '|' . $this->chapterRef;
    }

    public function middleware(): array
    {
        return [self::overlapMiddleware()->releaseAfter(30)->expireAfter(900)];
    }

    /** The cache-lock key WithoutOverlapping uses, so osis:import can take the very same lock. */
    public static function lockKey(): string
    {
        return self::overlapMiddleware()->getLockKey(null);
    }

    private static function overlapMiddleware(): WithoutOverlapping
    {
        return (new WithoutOverlapping(self::LOCK))->shared();
    }

    public function handle(): void
    {
        $file = OsisSourcePath::for($this->translationKey);

        $result = Process::path(base_path())
            ->timeout(280)
            ->env([self::LOCK_HELD_ENV => '1'])
            ->run([PHP_BINARY, 'artisan', 'osis:import', $file, '--chapter=' . $this->chapterRef, '--production']);

        if ($result->failed()) {
            Log::error('Chapter publish failed', [
                'chapter' => $this->chapterRef,
                'translation' => $this->translationKey,
                'exit_code' => $result->exitCode(),
                'output' => mb_substr($result->output() . $result->errorOutput(), -4000),
                'attempt' => $this->attempts(),
            ]);

            throw new RuntimeException("osis:import exited {$result->exitCode()} for {$this->chapterRef}");
        }

        // osis:import bumps the projection cache version itself.
        Log::channel('audit')->info('chapter published', ['chapter' => $this->chapterRef, 'translation' => $this->translationKey, 'user' => $this->userId]);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('Chapter publish gave up - the OSIS source and the database now differ for this chapter', [
            'chapter' => $this->chapterRef,
            'translation' => $this->translationKey,
            'exception' => $e,
            'fix' => "php artisan osis:import <source> --chapter={$this->chapterRef} --production",
        ]);
    }
}
