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
        Schema::create('abbreviations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained('verses')->onDelete('cascade');
            $table->string('abbreviated_text', 100)->comment('e.g., St.');
            $table->text('expanded_text')->comment('e.g., Saint');
            $table->integer('position_start')->nullable()->comment('Start position in verse');
            $table->integer('position_end')->nullable()->comment('End position in verse');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id']);
            $table->index(['abbreviated_text']);
 // For abbreviation searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abbreviations');
    }
};
