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
        Schema::create('verses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('chapters')->onDelete('cascade');
            $table->integer('verse_number');
            $table->string('osis_id', 50)->unique()->comment('e.g., Gen.1.1');
            $table->string('se_id', 100)->nullable()->comment('Start/End ID for milestone tracking');
            $table->text('text')->comment('Plain text for search');
            $table->text('formatted_text')->comment('HTML formatted text');
            $table->text('original_xml')->nullable()->comment('Original OSIS XML for complex verses');
            $table->timestamps();

            $table->unique(['chapter_id', 'verse_number']);
            $table->index(['osis_id']);
            $table->index(['chapter_id', 'verse_number']);
            // Note: Full-text search will be handled via FTS5 virtual tables
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verses');
    }
};
