<?php

use Illuminate\Support\Facades\DB;

/*
 * The importer projects the OSIS source of truth into SQLite. These tests run the REAL command
 * against tests/Fixtures/mini-kjv.osis.xml (whole chapters sliced byte-for-byte from the KJV source,
 * see build_mini_kjv.py) into the in-memory test database.
 */

function redRows(string $osisId): int
{
    $verse = verseRow($osisId);

    return $verse ? DB::table('red_letter_text')->where('verse_id', $verse->id)->count() : 0;
}

function plain(?string $text): string
{
    return trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
}

// --- text completeness (DOM-3 / DATA-1) -------------------------------------------------------

it('keeps the words after a quote that closes mid-verse (Matt 26:56)', function () {
    projectFixture();

    expect(plain(verseRow('Matt.26.56')->text))->toEndWith('Then all the disciples forsook him, and fled.');
});

it('keeps the rest of Matt 21:31 after the quote closes', function () {
    projectFixture();

    expect(plain(verseRow('Matt.21.31')->text))
        ->toContain('They say unto him, The first.')
        ->toContain('the publicans and the harlots go into the kingdom of God before you.');
});

it('keeps the parenthesis in Mark 2:10', function () {
    projectFixture();

    expect(plain(verseRow('Mark.2.10')->text))->toContain('(he saith to the sick of the palsy,)');
});

// --- notes are not scripture (DOM-7 / DATA-2) ---------------------------------------------------

it('does not append study-note text to the verse text', function () {
    projectFixture();

    expect(verseRow('Gen.1.4')->text)->not->toContain('Heb.');
    expect(plain(verseRow('Gen.1.4')->text))->toEndWith('God divided the light from the darkness.');
});

// --- red letters across verse boundaries (DOM-2) ------------------------------------------------

it('marks a continuation verse inside a multi-verse quote as Christ\'s words (John 3:16)', function () {
    projectFixture();

    expect(redRows('John.3.16'))->toBe(1);
    expect(redRows('John.3.21'))->toBe(1);
    expect(verseRow('John.3.16')->formatted_text)->toContain('text-red-600');
});

it('writes exactly one red row for a verse with nested quotes (Matt 5:4)', function () {
    projectFixture();

    expect(redRows('Matt.5.4'))->toBe(1);
});

it('keeps stored fragments well-formed and free of injected namespaces and milestones', function () {
    projectFixture();

    foreach (['John.3.16', 'Matt.26.56', 'Matt.21.31', 'Matt.5.4', 'Matt.9.6'] as $osisId) {
        $verse = verseRow($osisId);
        $doc = new DOMDocument();
        expect(@$doc->loadXML('<x>' . $verse->original_xml . '</x>'))->toBeTrue("{$osisId} original_xml is not well-formed");
        expect($verse->formatted_text)->not->toContain('xmlns')->not->toContain('<milestone');
    }
});

it('does not let Christ\'s red span in Matt 9:6 bleed into 9:7', function () {
    projectFixture();

    expect(verseRow('Matt.9.7')->formatted_text)->not->toContain('text-red-600');
    expect(substr_count(verseRow('Matt.9.6')->formatted_text, '<span'))
        ->toBe(substr_count(verseRow('Matt.9.6')->formatted_text, '</span>'));
});

// --- idempotency and scope (TEST-2, TEST-4, PIPE-10/11) -----------------------------------------

it('is idempotent across separate runs', function () {
    projectFixture();
    $before = ['verses' => DB::table('verses')->count(), 'titles' => DB::table('titles')->count(), 'red' => DB::table('red_letter_text')->count(), 'paragraphs' => DB::table('paragraphs')->count()];

    importOsis(base_path('tests/Fixtures/mini-kjv.osis.xml'));

    expect(['verses' => DB::table('verses')->count(), 'titles' => DB::table('titles')->count(), 'red' => DB::table('red_letter_text')->count(), 'paragraphs' => DB::table('paragraphs')->count()])->toBe($before);
});

it('is idempotent when called twice in the same process without a fresh command instance', function () {
    projectFixture();
    $titles = DB::table('titles')->count();

    // No setArtisan(null): this is what a queue worker or Artisan::call() in a web request does.
    test()->artisan('osis:import', ['file' => base_path('tests/Fixtures/mini-kjv.osis.xml')])->assertExitCode(0);

    expect(DB::table('titles')->count())->toBe($titles);
});

it('keeps the book\'s main title when re-projecting chapter 1 only', function () {
    projectFixture();
    expect(DB::table('titles')->where('title_type', 'main')->count())->toBe(6);

    importOsis(base_path('tests/Fixtures/mini-kjv.osis.xml'), ['--chapter' => 'Gen.1']);

    expect(DB::table('titles')->where('title_type', 'main')->count())->toBe(6);
});

it('removes a verse that no longer exists in the source', function () {
    projectFixture();
    $src = file_get_contents(base_path('tests/Fixtures/mini-kjv.osis.xml'));
    $edited = preg_replace('#<verse osisID="Ps\.23\.6" sID="Ps\.23\.6"/>.*?<verse eID="Ps\.23\.6"/>#s', '', $src, 1, $n);
    expect($n)->toBe(1);
    $tmp = tempnam(sys_get_temp_dir(), 'osis') . '.osis.xml';
    file_put_contents($tmp, $edited);

    importOsis($tmp, ['--chapter' => 'Ps.23']);
    @unlink($tmp);

    expect(verseRow('Ps.23.6'))->toBeNull();
    expect(verseRow('Ps.23.5'))->not->toBeNull();
});

// --- loud failures (TEST-5, PIPE-7) -------------------------------------------------------------

it('fails when no books are seeded instead of projecting nothing', function () {
    importOsis(base_path('tests/Fixtures/mini-kjv.osis.xml'), [], 1);
});

it('fails on a contained-format OSIS file instead of projecting nothing', function () {
    seedBooks();
    $tmp = tempnam(sys_get_temp_dir(), 'osis') . '.osis.xml';
    file_put_contents($tmp, '<?xml version="1.0" encoding="UTF-8"?><osis xmlns="http://www.bibletechnologies.net/2003/OSIS/namespace"><osisText osisIDWork="MAO"><header><work osisWork="MAO"/></header><div type="book" osisID="Gen"><chapter osisID="Gen.1"><verse osisID="Gen.1.1">I te timatanga</verse></chapter></div></osisText></osis>');

    importOsis($tmp, [], 1);
    @unlink($tmp);
});
