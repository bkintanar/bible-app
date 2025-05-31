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
        Schema::create('catch_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained('verses')->onDelete('cascade');
            $table->text('catch_word')->comment('The catchWord text for cross-reference');
            $table->integer('position_start')->nullable()->comment('Start position in verse');
            $table->integer('position_end')->nullable()->comment('End position in verse');
            $table->foreignId('note_id')->nullable()->constrained('study_notes')->onDelete('cascade');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id']);
            $table->index(['note_id']);
 // For cross-reference searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catch_words');
    }
};
