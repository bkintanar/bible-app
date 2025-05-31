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
        Schema::create('study_note_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('study_notes')->onDelete('cascade');
            $table->string('element_type', 20)->comment('catchWord, rdg, foreign, abbr, w');
            $table->text('element_text')->comment('The element content');
            $table->integer('element_order')->comment('Order within the note');
            $table->json('attributes')->nullable()->comment('Element attributes as JSON');
            $table->timestamps();

            $table->index(['note_id', 'element_order']);
            $table->index(['element_type']);
 // For embedded content searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_note_elements');
    }
};
