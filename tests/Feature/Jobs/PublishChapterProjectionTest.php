<?php

use App\Jobs\PublishChapterProjection;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Process\PendingProcess;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    DB::table('bible_versions')->insert(['osis_work' => 'KJV', 'abbreviation' => 'KJV', 'title' => 'King James Version', 'language' => 'en', 'created_at' => now(), 'updated_at' => now()]);
});

it('re-projects the chapter in a subprocess, with --production', function () {
    Process::fake(['*' => Process::result(output: 'ok', exitCode: 0)]);

    (new PublishChapterProjection('kjv', 'Gen.1'))->handle();

    Process::assertRan(function (PendingProcess $process) {
        $cmd = implode(' ', (array) $process->command);

        return str_contains($cmd, 'artisan osis:import')
            && str_contains($cmd, base_path('assets') . '/kjv.osis.xml')
            && str_contains($cmd, '--chapter=Gen.1')
            && str_contains($cmd, '--production');
    });
});

it('fails loudly when the import fails, so the queue retries it', function () {
    Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

    expect(fn () => (new PublishChapterProjection('kjv', 'Gen.1'))->handle())->toThrow(RuntimeException::class);
});

it('is unique only until processing, and never overlaps another publish', function () {
    $job = new PublishChapterProjection('kjv', 'Gen.1');

    expect($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
    expect($job->uniqueId())->toBe('kjv|Gen.1');
    expect($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class);
    expect($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'));
});
