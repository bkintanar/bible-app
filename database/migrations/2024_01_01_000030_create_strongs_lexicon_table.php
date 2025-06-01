<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('strongs_lexicon', function (Blueprint $table) {
            $table->id();
            $table->string('strongs_number', 20)->unique()->comment('e.g., H07225, G2316');
            $table->string('language', 10)->comment('Hebrew, Greek, Aramaic');
            $table->text('original_word')->comment('Hebrew/Greek text');
            $table->text('transliteration')->nullable()->comment('English transliteration');
            $table->text('pronunciation')->nullable()->comment('Phonetic pronunciation');
            $table->text('short_definition')->comment('Brief definition (1-2 words)');
            $table->text('detailed_definition')->comment('Complete definition with variants');
            $table->text('outline_definition')->nullable()->comment('Outline of Biblical usage');
            $table->string('part_of_speech', 50)->nullable()->comment('noun, verb, adjective, etc.');
            $table->text('etymology')->nullable()->comment('Word origin and derivation');
            $table->integer('occurrence_count')->default(0)->comment('Total occurrences in Bible');
            $table->text('related_words')->nullable()->comment('JSON array of related Strong\'s numbers');
            $table->text('variants')->nullable()->comment('JSON array of spelling variants');
            $table->text('synonyms')->nullable()->comment('JSON array of synonyms');
            $table->text('antonyms')->nullable()->comment('JSON array of antonyms');
            $table->text('derived_words')->nullable()->comment('JSON array of derived words');
            $table->text('root_words')->nullable()->comment('JSON array of root words');
            $table->timestamps();

            $table->index(['strongs_number']);
            $table->index(['language']);
            $table->index(['part_of_speech']);
            // Note: Full-text search will be handled via the existing FTS tables in the bible database
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strongs_lexicon');
    }
};
