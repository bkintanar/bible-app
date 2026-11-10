<?php

use App\Services\TranslationService;
use Illuminate\Support\Facades\DB;

/*
 * The database-backed translation list (the reader the app uses). The config/XML path belongs to
 * the XML reader, which is dead code (plan 9.1).
 */

beforeEach(function () {
    projectFixture(); // projects the fixture as KJV
    $this->service = app(TranslationService::class);
});

it('offers exactly the projected translations', function () {
    $keys = $this->service->getAvailableTranslations()->pluck('key')->all();

    expect($keys)->toBe(['kjv']);
});

it('defaults to KJV and looks translations up case-insensitively', function () {
    expect($this->service->getDefaultTranslationKey())->toBe('kjv')
        ->and($this->service->getTranslation('KJV')['short_name'])->toBe('KJV')
        ->and($this->service->getTranslation('asv'))->toBeNull()
        ->and($this->service->translationExists('nope'))->toBeFalse();
});

it('remembers a valid current translation and ignores an unprojected one', function () {
    $this->service->setCurrentTranslation('kjv');
    expect(session('current_translation'))->toBe('kjv');

    $this->service->setCurrentTranslation('asv');
    expect(session('current_translation'))->toBe('kjv')
        ->and($this->service->getCurrentTranslation()['key'])->toBe('kjv');
});

it('falls back to the default when the session holds a translation that is not projected', function () {
    session(['current_translation' => 'mao']);

    expect($this->service->getCurrentTranslation()['key'])->toBe('kjv');
});

it('asks the database once per request, however often it is consulted', function () {
    DB::enableQueryLog();

    foreach (range(1, 5) as $i) {
        $this->service->getCurrentTranslation();
        $this->service->translationExists('kjv');
        $this->service->getDefaultTranslationKey();
    }

    $versionQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'bible_versions'));
    expect($versionQueries)->toHaveCount(1);
});
