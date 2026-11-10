<?php

use App\Services\BibleService;
use Illuminate\Support\Facades\DB;

/*
 * "John.3.16" exists once per projected translation. Every reader lookup must stay inside the
 * reader's own translation, or a second translation would leak into the KJV's search and verses.
 */
beforeEach(function () {
    projectFixture();

    $versionId = DB::table('bible_versions')->insertGetId([
        'osis_work' => 'OTHER', 'abbreviation' => 'OTH', 'title' => 'Other', 'language' => 'en',
        'canonical' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $chapterId = DB::table('chapters')->insertGetId([
        'book_id' => DB::table('books')->where('osis_id', 'John')->value('id'), 'version_id' => $versionId,
        'chapter_number' => 3, 'osis_id' => 'John.3', 'osis_ref' => 'John.3', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('verses')->insert([
        'chapter_id' => $chapterId, 'verse_number' => 16, 'osis_id' => 'John.3.16',
        'text' => 'zebrastripe only in the other translation', 'formatted_text' => 'zebrastripe only in the other translation',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('translator_changes')->insert([
        'verse_id' => DB::table('verses')->where('chapter_id', $chapterId)->value('id'),
        'change_type' => 'added', 'text_content' => 'zebrastripe', 'text_order' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::statement("INSERT INTO verses_fts(verses_fts) VALUES('rebuild')");
});

it('keeps search inside the current translation', function () {
    expect(app(BibleService::class)->searchVerses('zebrastripe'))->toHaveCount(0)
        ->and(app(BibleService::class)->searchVerses('so loved the world'))->not->toBeEmpty();
});

it('keeps verse lookups inside the current translation', function () {
    $details = app(BibleService::class)->getVerseWithDetails('John.3.16');

    expect(json_encode($details))->not->toContain('zebrastripe')->toContain('loved');
    expect(app(BibleService::class)->getVerseText('John.3.16'))->not->toContain('zebrastripe');

    // A collection lookup returns rows from EVERY translation unless scoped.
    expect(json_encode(app(BibleService::class)->getTranslatorChanges('John.3.16')))->not->toContain('zebrastripe');
});
