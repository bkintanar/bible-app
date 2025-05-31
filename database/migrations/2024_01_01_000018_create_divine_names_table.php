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
        Schema::create('divine_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained('verses')->onDelete('cascade');
            $table->text('displayed_text')->comment('e.g., LORD, GOD');
            $table->string('original_name', 20)->default('YHWH')->comment('YHWH, Elohim, Adonai');
            $table->integer('position_start')->nullable()->comment('Start position in verse');
            $table->integer('position_end')->nullable()->comment('End position in verse');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id']);
            $table->index(['original_name']);
 // For divine name searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divine_names');
    }
};
