<?php

use App\Services\DatabaseBibleReader;

/*
 * Search covers scripture only (migration 2026_11_10_000001): words that occur only in study
 * notes or in title markup must not find a verse, and text recovered by the importer must be found.
 */

beforeEach(function () {
    projectFixture();
});

it('finds words the importer used to truncate', function () {
    $results = (new DatabaseBibleReader('kjv'))->searchVerses('all the disciples forsook him');

    expect($results->pluck('osis_id')->all())->toBe(['Matt.26.56']);
});

it('does not find verses by their study-note text', function () {
    // Gen 1:4 carries the note "Heb. between the light and between the darkness".
    expect((new DatabaseBibleReader('kjv'))->searchVerses('Heb')->count())->toBe(0);
});

it('does not find verses by title CSS', function () {
    expect((new DatabaseBibleReader('kjv'))->searchVerses('uppercase')->count())->toBe(0);
});

it('highlights the matched words in the verse text', function () {
    $hit = (new DatabaseBibleReader('kjv'))->searchVerses('shepherd')->firstWhere('osis_id', 'Ps.23.1');

    expect($hit)->not->toBeNull();
    expect($hit['text'])->toContain('<mark>shepherd</mark>');
});
