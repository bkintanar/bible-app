<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('has no empty legacy tables, unused FTS indexes or views, or runtime tables in the scripture DB', function () {
    foreach (['abbreviations', 'catch_words', 'element_attributes', 'foreign_text', 'formatting_markers', 'milestones',
        'reading_variants', 'study_note_elements', 'strongs_lexicon', 'word_relationships', 'notes_fts', 'strongs_fts',
        'cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse($table);
    }

    $views = collect(DB::select("select name from sqlite_master where type = 'view'"))->pluck('name')->all();
    expect($views)->toBe(['search_results']);

    expect(Schema::hasColumn('word_elements', 'position_start'))->toBeFalse()
        ->and(Schema::hasColumn('red_letter_text', 'text_order'))->toBeTrue(); // still used for ordering
});

it('still projects and reads the fixture after the cleanup', function () {
    projectFixture();

    $this->withoutVite()->get('/books/John/chapters/3')->assertOk()->assertSeeText('For God so loved the world');
    expect(DB::table('word_elements')->count())->toBeGreaterThan(0);
});
