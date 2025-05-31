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
        Schema::create('word_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained('verses')->onDelete('cascade');
            $table->text('word_text')->comment('The actual word/phrase text');
            $table->string('strongs_number', 20)->nullable()->comment('e.g., H07225, G2316');
            $table->string('morphology_code', 50)->nullable()->comment('e.g., strongMorph:TH8799');
            $table->text('lemma')->nullable()->comment('Multiple Strong\'s references separated by space');
            $table->integer('position_start')->nullable()->comment('Start position in verse text');
            $table->integer('position_end')->nullable()->comment('End position in verse text');
            $table->integer('word_order')->comment('Order of word in verse (1-based)');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id', 'word_order']);
            $table->index(['strongs_number']);
            $table->index(['morphology_code']);
 // For word searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_elements');
    }
};
