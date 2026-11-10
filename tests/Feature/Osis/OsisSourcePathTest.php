<?php

use App\Services\Osis\OsisSourcePath;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('bible_versions')->insert([
        'osis_work' => 'KJV', 'abbreviation' => 'KJV', 'title' => 'King James Version', 'language' => 'en',
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

it('resolves a projected translation inside the configured OSIS directory', function () {
    expect(OsisSourcePath::for('kjv'))->toBe(base_path('assets').'/kjv.osis.xml');
    expect(OsisSourcePath::for('KJV'))->toBe(base_path('assets').'/kjv.osis.xml');
    expect(OsisSourcePath::for(null))->toBe(base_path('assets').'/kjv.osis.xml'); // default key
});

it('honours an absolute osis_directory (tests point it at a temp copy)', function () {
    config(['bible.osis_directory' => '/tmp/osis-fixture/']);

    expect(OsisSourcePath::for('kjv'))->toBe('/tmp/osis-fixture/kjv.osis.xml');
});

it('rejects path traversal and malformed keys', function (string $key) {
    expect(fn () => OsisSourcePath::for($key))->toThrow(InvalidArgumentException::class);
})->with(['../kjv', 'kjv/../../etc/passwd', 'kjv.osis', '', ' ', 'k j v']);

it('rejects a translation that is not projected', function () {
    expect(fn () => OsisSourcePath::for('asv'))->toThrow(InvalidArgumentException::class, 'not projected');
});
