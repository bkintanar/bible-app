<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create comprehensive verse view with all related data (SQLite compatible)
        DB::statement("
            CREATE VIEW verse_details AS
            SELECT
                v.id as verse_id,
                v.osis_id,
                v.verse_number,
                v.text,
                v.formatted_text,
                c.chapter_number,
                b.name as book_name,
                b.osis_id as book_osis_id,
                bg.name as book_group,
                bv.abbreviation as version,
                bv.title as version_title,

                -- Strong's and linguistic data (as JSON)
                (SELECT json_group_array(
                    json_object(
                        'word_text', we.word_text,
                        'strongs_number', we.strongs_number,
                        'morphology', we.morphology_code,
                        'word_order', we.word_order
                    )
                ) FROM word_elements we WHERE we.verse_id = v.id) as word_elements,

                -- Translator additions (italics)
                (SELECT json_group_array(
                    json_object(
                        'text', tc.text_content,
                        'type', tc.change_type,
                        'order', tc.text_order
                    )
                ) FROM translator_changes tc WHERE tc.verse_id = v.id) as translator_changes,

                -- Study notes
                (SELECT json_group_array(
                    json_object(
                        'note_text', sn.note_text,
                        'note_type', sn.note_type
                    )
                ) FROM study_notes sn WHERE sn.verse_id = v.id) as study_notes,

                -- Red letter text (Jesus' words)
                (SELECT json_group_array(
                    json_object(
                        'text', rlt.text_content,
                        'speaker', rlt.speaker,
                        'order', rlt.text_order
                    )
                ) FROM red_letter_text rlt WHERE rlt.verse_id = v.id) as red_letter_text,

                -- Divine names (LORD/YHWH)
                (SELECT json_group_array(
                    json_object(
                        'displayed_text', dn.displayed_text,
                        'original_name', dn.original_name
                    )
                ) FROM divine_names dn WHERE dn.verse_id = v.id) as divine_names

            FROM verses v
            JOIN chapters c ON v.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            JOIN book_groups bg ON b.book_group_id = bg.id
            JOIN bible_versions bv ON c.version_id = bv.id
        ");

        // Create search results view for optimized searches (SQLite compatible)
        DB::statement("
            CREATE VIEW search_results AS
            SELECT
                v.id as verse_id,
                v.osis_id,
                v.text,
                v.formatted_text,
                b.name || ' ' || c.chapter_number || ':' || v.verse_number as reference,
                b.name as book_name,
                c.chapter_number,
                v.verse_number,
                bv.abbreviation as version,

                -- Include searchable content from related tables (SQLite style)
                v.text || ' ' ||
                COALESCE((SELECT group_concat(we.word_text, ' ') FROM word_elements we WHERE we.verse_id = v.id), '') || ' ' ||
                COALESCE((SELECT group_concat(tc.text_content, ' ') FROM translator_changes tc WHERE tc.verse_id = v.id), '') || ' ' ||
                COALESCE((SELECT group_concat(sn.note_text, ' ') FROM study_notes sn WHERE sn.verse_id = v.id), '') || ' ' ||
                COALESCE((SELECT group_concat(t.title_text, ' ') FROM titles t WHERE t.verse_id = v.id), '')
                as searchable_content

            FROM verses v
            JOIN chapters c ON v.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            JOIN bible_versions bv ON c.version_id = bv.id
        ");

        // Create Strong's concordance view
        DB::statement("
            CREATE VIEW strongs_concordance AS
            SELECT
                we.strongs_number,
                we.word_text,
                we.morphology_code,
                COUNT(*) as occurrence_count,
                v.osis_id,
                b.name || ' ' || c.chapter_number || ':' || v.verse_number as reference,
                v.text as verse_text
            FROM word_elements we
            JOIN verses v ON we.verse_id = v.id
            JOIN chapters c ON v.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            WHERE we.strongs_number IS NOT NULL
            GROUP BY we.strongs_number, we.word_text, we.morphology_code, v.id
            ORDER BY we.strongs_number, occurrence_count DESC
        ");

        // Create cross-reference view
        DB::statement("
            CREATE VIEW cross_references AS
            SELECT
                cw.catch_word,
                v.osis_id as source_verse,
                b.name || ' ' || c.chapter_number || ':' || v.verse_number as source_reference,
                sn.note_text as related_note,
                COUNT(*) as reference_count
            FROM catch_words cw
            JOIN verses v ON cw.verse_id = v.id
            JOIN chapters c ON v.chapter_id = c.id
            JOIN books b ON c.book_id = b.id
            LEFT JOIN study_notes sn ON cw.note_id = sn.id
            GROUP BY cw.catch_word, v.id
            ORDER BY reference_count DESC
        ");

        // Create FTS5 virtual table for verse search
        DB::statement("
            CREATE VIRTUAL TABLE verses_fts USING fts5(
                verse_id UNINDEXED,
                osis_id UNINDEXED,
                reference UNINDEXED,
                book_name UNINDEXED,
                text,
                searchable_content,
                content='search_results',
                content_rowid='verse_id'
            )
        ");

        // Create FTS5 virtual table for Strong's numbers
        DB::statement("
            CREATE VIRTUAL TABLE strongs_fts USING fts5(
                strongs_number UNINDEXED,
                word_text,
                morphology_code UNINDEXED,
                verse_references UNINDEXED
            )
        ");

        // Create FTS5 virtual table for study notes
        DB::statement("
            CREATE VIRTUAL TABLE notes_fts USING fts5(
                note_id UNINDEXED,
                verse_id UNINDEXED,
                note_type UNINDEXED,
                note_text
            )
        ");

        // Create triggers to keep FTS tables in sync with main tables
        DB::statement("
            CREATE TRIGGER verses_fts_insert AFTER INSERT ON verses BEGIN
                INSERT INTO verses_fts(rowid, verse_id, osis_id, reference, book_name, text, searchable_content)
                SELECT
                    NEW.id,
                    NEW.id,
                    NEW.osis_id,
                    b.name || ' ' || c.chapter_number || ':' || NEW.verse_number,
                    b.name,
                    NEW.text,
                    NEW.text
                FROM chapters c
                JOIN books b ON c.book_id = b.id
                WHERE c.id = NEW.chapter_id;
            END
        ");

        DB::statement("
            CREATE TRIGGER verses_fts_delete AFTER DELETE ON verses BEGIN
                INSERT INTO verses_fts(verses_fts, rowid, verse_id, osis_id, reference, book_name, text, searchable_content)
                VALUES('delete', OLD.id, OLD.id, OLD.osis_id, '', '', '', '');
            END
        ");

        DB::statement("
            CREATE TRIGGER verses_fts_update AFTER UPDATE ON verses BEGIN
                INSERT INTO verses_fts(verses_fts, rowid, verse_id, osis_id, reference, book_name, text, searchable_content)
                VALUES('delete', OLD.id, OLD.id, OLD.osis_id, '', '', '', '');
                INSERT INTO verses_fts(rowid, verse_id, osis_id, reference, book_name, text, searchable_content)
                SELECT
                    NEW.id,
                    NEW.id,
                    NEW.osis_id,
                    b.name || ' ' || c.chapter_number || ':' || NEW.verse_number,
                    b.name,
                    NEW.text,
                    NEW.text
                FROM chapters c
                JOIN books b ON c.book_id = b.id
                WHERE c.id = NEW.chapter_id;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::statement('DROP TRIGGER IF EXISTS verses_fts_update');
        DB::statement('DROP TRIGGER IF EXISTS verses_fts_delete');
        DB::statement('DROP TRIGGER IF EXISTS verses_fts_insert');

        // Drop FTS tables
        DB::statement('DROP TABLE IF EXISTS notes_fts');
        DB::statement('DROP TABLE IF EXISTS strongs_fts');
        DB::statement('DROP TABLE IF EXISTS verses_fts');

        // Drop views
        DB::statement('DROP VIEW IF EXISTS cross_references');
        DB::statement('DROP VIEW IF EXISTS strongs_concordance');
        DB::statement('DROP VIEW IF EXISTS search_results');
        DB::statement('DROP VIEW IF EXISTS verse_details');
    }
};
