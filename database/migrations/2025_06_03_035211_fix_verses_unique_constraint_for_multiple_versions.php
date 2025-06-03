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
        Schema::table('verses', function (Blueprint $table) {
            // Add a composite unique constraint on osis_id + chapter_id to ensure uniqueness within each version
            // This prevents duplicate verses within the same chapter while allowing the same verse across different versions
            $table->unique(['osis_id', 'chapter_id'], 'verses_osis_id_chapter_id_unique');
        });

        // Update the views to include version information for better querying
        DB::statement('DROP VIEW IF EXISTS verse_details');
        DB::statement("
            CREATE VIEW verse_details AS
            SELECT
                v.id as verse_id,
                v.osis_id,
                v.verse_number,
                v.text,
                v.formatted_text,
                c.chapter_number,
                c.version_id,
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

        // Update search results view to include version filtering
        DB::statement('DROP VIEW IF EXISTS search_results');
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
                c.version_id,
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verses', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('verses_osis_id_chapter_id_unique');
        });

        // Restore original views (will be recreated by the original views migration if needed)
        DB::statement('DROP VIEW IF EXISTS verse_details');
        DB::statement('DROP VIEW IF EXISTS search_results');
    }
};
