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
        Schema::create('foreign_text', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained('verses')->onDelete('cascade');
            $table->text('foreign_text')->comment('Hebrew/Greek text');
            $table->string('language_code', 10)->nullable()->comment('he, gr, ar');
            $table->text('transliteration')->nullable()->comment('English transliteration');
            $table->integer('position_start')->nullable()->comment('Start position in verse');
            $table->integer('position_end')->nullable()->comment('End position in verse');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id']);
            $table->index(['language_code']);
 // For foreign text searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foreign_text');
    }
};
