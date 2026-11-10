<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema cleanup (audit 9.2). Nothing here holds data the app reads:
 *  - 10 tables that were always empty (the importer never wrote them);
 *  - the notes and Strong's FTS indexes and three views nothing queries;
 *  - two indexes that duplicate a unique index on the same columns;
 *  - position_start/position_end, never written (always NULL);
 *  - the cache/queue tables in the SCRIPTURE database - they moved to runtime.sqlite (4.3).
 *
 * Kept on purpose: word_order/text_order (the reader orders by them), poetry_structure (the reader
 * still references it), divine_names (the API's verse details use it).
 *
 * down() restores the indexes and the cache/queue tables; the dropped empty tables, views and FTS
 * indexes are not recreated (restore the pre-migration backup if they are ever needed).
 */
return new class extends Migration
{
    private const EMPTY_TABLES = [
        'abbreviations', 'catch_words', 'element_attributes', 'foreign_text', 'formatting_markers',
        'milestones', 'reading_variants', 'study_note_elements', 'strongs_lexicon', 'word_relationships',
    ];

    private const RUNTIME_TABLES = ['cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches'];

    public function up(): void
    {
        foreach (['verse_details', 'cross_references', 'strongs_concordance'] as $view) {
            DB::statement("DROP VIEW IF EXISTS {$view}");
        }

        // Virtual FTS5 tables: dropping them drops their shadow tables too.
        DB::statement('DROP TABLE IF EXISTS notes_fts');
        DB::statement('DROP TABLE IF EXISTS strongs_fts');

        Schema::disableForeignKeyConstraints();
        foreach (array_merge(self::EMPTY_TABLES, self::RUNTIME_TABLES) as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        DB::statement('DROP INDEX IF EXISTS verses_chapter_id_verse_number_index'); // = verses_chapter_id_verse_number_unique
        DB::statement('DROP INDEX IF EXISTS books_osis_id_index');                  // = books_osis_id_unique

        foreach (['word_elements', 'translator_changes', 'red_letter_text'] as $table) {
            $columns = array_values(array_filter(['position_start', 'position_end'], fn ($c) => Schema::hasColumn($table, $c)));
            if ($columns !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($columns));
            }
        }
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS verses_chapter_id_verse_number_index ON verses (chapter_id, verse_number)');
        DB::statement('CREATE INDEX IF NOT EXISTS books_osis_id_index ON books (osis_id)');

        foreach (['word_elements', 'translator_changes', 'red_letter_text'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'position_start')) {
                    $t->integer('position_start')->nullable();
                    $t->integer('position_end')->nullable();
                }
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', fn (Blueprint $t) => [$t->string('key')->primary(), $t->mediumText('value'), $t->integer('expiration')]);
            Schema::create('cache_locks', fn (Blueprint $t) => [$t->string('key')->primary(), $t->string('owner'), $t->integer('expiration')]);
        }
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('queue')->index();
                $t->longText('payload');
                $t->unsignedTinyInteger('attempts');
                $t->unsignedInteger('reserved_at')->nullable();
                $t->unsignedInteger('available_at');
                $t->unsignedInteger('created_at');
            });
        }
    }
};
