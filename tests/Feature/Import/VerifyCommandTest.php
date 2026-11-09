<?php

use Illuminate\Support\Facades\DB;

/*
 * osis:verify --content is the gate for every re-projection: it must pass on a correct projection
 * and name the verse when one is wrong.
 */

function verifyFixture(array $options = ['--content' => true]): \Illuminate\Testing\PendingCommand
{
    return test()->artisan('osis:verify', array_merge(['file' => base_path('tests/Fixtures/mini-kjv.osis.xml')], $options));
}

it('passes on a fresh projection, counts and every verse', function () {
    projectFixture();

    verifyFixture()->expectsOutputToContain('Projection matches the source')->assertExitCode(0);
});

it('fails and names the verse when its text was truncated', function () {
    projectFixture();
    DB::table('verses')->where('osis_id', 'Matt.26.56')->update(['text' => 'But all this was done, that the scriptures of the prophets might be fulfilled.']);

    verifyFixture()->expectsOutputToContain('Matt.26.56')->assertExitCode(1);
});

it('fails when a verse has lost its red letters', function () {
    projectFixture();
    $id = DB::table('verses')->where('osis_id', 'John.3.16')->value('id');
    DB::table('red_letter_text')->where('verse_id', $id)->delete();

    verifyFixture()->expectsOutputToContain('John.3.16 (should be red)')->assertExitCode(1);
});

it('fails on an unreadable source instead of reporting counts of zero', function () {
    projectFixture();
    $tmp = tempnam(sys_get_temp_dir(), 'osis') . '.osis.xml';
    file_put_contents($tmp, '<osis><osisText osisIDWork="KJV"><div type="book" osisID="Gen">');

    test()->artisan('osis:verify', ['file' => $tmp, '--content' => true])->assertExitCode(1);
    @unlink($tmp);
});
