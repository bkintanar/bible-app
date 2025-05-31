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
        Schema::create('paragraphs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained('chapters')->onDelete('cascade');
            $table->foreignId('start_verse_id')->constrained('verses', 'id')->onDelete('cascade');
            $table->foreignId('end_verse_id')->nullable()->constrained('verses', 'id')->onDelete('cascade');
            $table->string('paragraph_type', 20)->default('normal')->comment('normal, poetry, title');
            $table->text('text_content')->nullable()->comment('Combined paragraph text');
            $table->timestamps();

            $table->index(['chapter_id']);
            $table->index(['start_verse_id']);
            $table->index(['paragraph_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paragraphs');
    }
};
