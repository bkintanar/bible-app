<?php

use App\Support\ResumePosition;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutVite();
});

it('keeps cache, rate limiter, queue and failed jobs off the scripture database', function () {
    expect(config('cache.stores.database.connection'))->toBe('runtime')
        ->and(config('cache.stores.database.lock_connection'))->toBe('runtime')
        ->and(config('queue.connections.database.connection'))->toBe('runtime')
        ->and(config('queue.failed.database'))->toBe('runtime')
        ->and(config('queue.batching.database'))->toBe('runtime');

    foreach (['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
        expect(Schema::connection('runtime')->hasTable($table))->toBeTrue("runtime.$table");
    }
});

it('sends / back to the chapter read last in this session', function () {
    projectFixture();

    $this->get('/books/John/chapters/3')->assertOk()->assertCookie(ResumePosition::COOKIE);
    $this->get('/')->assertRedirect(route('chapters.show', ['bookOsisId' => 'John', 'chapterNumber' => 3]));
});

it('resumes from the long-lived cookie when the session is gone', function () {
    projectFixture();

    $this->withCookie(ResumePosition::COOKIE, 'Matt/5')->get('/')
        ->assertRedirect(route('chapters.show', ['bookOsisId' => 'Matt', 'chapterNumber' => 5]));
});

it('ignores a resume cookie that is malformed or points nowhere', function (string $value) {
    projectFixture();

    $this->withCookie(ResumePosition::COOKIE, $value)->get('/')
        ->assertRedirect(route('chapters.show', ['bookOsisId' => 'Gen', 'chapterNumber' => 1]));
})->with(['Gen/999', 'Nope/1', '../etc/passwd', 'https://evil.example/', '']);
