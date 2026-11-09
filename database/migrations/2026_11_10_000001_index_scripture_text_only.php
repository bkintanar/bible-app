<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Search indexes scripture only.
 *
 * search_results folded word lists, translator changes, STUDY NOTES and raw TITLE HTML into an
 * indexed searchable_content column, so a search found verses by words that are not in them
 * ("midriff" only occurs in notes; "uppercase" is CSS inside title markup). The index now has one
 * searchable column, the verse text (column 4 as before, so snippet(verses_fts, 4, ...) and the
 * unqualified MATCH in DatabaseBibleReader::searchVerses are unchanged).
 *
 * The insert/update/delete triggers are dropped: for an external-content FTS5 table a 'delete'
 * must pass the OLD indexed values, which the triggers did not (they passed ''), and the importer
 * already ends every run with a full 'rebuild' - the only thing that kept the index correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['verses_fts_insert', 'verses_fts_update', 'verses_fts_delete'] as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }
        DB::statement('DROP TABLE IF EXISTS verses_fts');
        DB::statement('DROP VIEW IF EXISTS search_results');

        DB::statement(<<<'SQL'
            CREATE VIEW search_results AS
            SELECT
                v.id AS verse_id,
                v.osis_id,
                v.text,
                v.formatted_text,
                b.name || ' ' || c.chapter_number || ':' || v.verse_number AS reference,
                b.name AS book_name,
                c.chapter_number,
                v.verse_number,
                c.version_id,
                bv.abbreviation AS version
            FROM verses v
            JOIN chapters c ON v.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            JOIN bible_versions bv ON c.version_id = bv.id
            SQL);

        DB::statement(<<<'SQL'
            CREATE VIRTUAL TABLE verses_fts USING fts5(
                verse_id UNINDEXED,
                osis_id UNINDEXED,
                reference UNINDEXED,
                book_name UNINDEXED,
                text,
                content='search_results',
                content_rowid='verse_id'
            )
            SQL);

        // An external-content index starts empty: fill it now, or search returns nothing until
        // the next import.
        DB::statement("INSERT INTO verses_fts(verses_fts) VALUES('rebuild')");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS verses_fts');
        DB::statement('DROP VIEW IF EXISTS search_results');

        DB::statement(<<<'SQL'
            CREATE VIEW search_results AS
            SELECT
                v.id AS verse_id, v.osis_id, v.text, v.formatted_text,
                b.name || ' ' || c.chapter_number || ':' || v.verse_number AS reference,
                b.name AS book_name, c.chapter_number, v.verse_number, c.version_id, bv.abbreviation AS version,
                v.text || ' ' ||
                COALESCE((SELECT group_concat(we.word_text, ' ') FROM word_elements we WHERE we.verse_id = v.id), '') || ' ' ||
                COALESCE((SELECT group_concat(tc.text_content, ' ') FROM translator_changes tc WHERE tc.verse_id = v.id), '') || ' ' ||
                COALESCE((SELECT group_concat(sn.note_text, ' ') FROM study_notes sn WHERE sn.verse_id = v.id), '') || ' ' ||
                COALESCE((SELECT group_concat(t.title_text, ' ') FROM titles t WHERE t.verse_id = v.id), '') AS searchable_content
            FROM verses v
            JOIN chapters c ON v.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            JOIN bible_versions bv ON c.version_id = bv.id
            SQL);

        DB::statement(<<<'SQL'
            CREATE VIRTUAL TABLE verses_fts USING fts5(
                verse_id UNINDEXED, osis_id UNINDEXED, reference UNINDEXED, book_name UNINDEXED,
                text, searchable_content, content='search_results', content_rowid='verse_id'
            )
            SQL);
        DB::statement("INSERT INTO verses_fts(verses_fts) VALUES('rebuild')");
    }
};
