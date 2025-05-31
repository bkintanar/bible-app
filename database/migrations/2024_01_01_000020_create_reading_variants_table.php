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
        Schema::create('reading_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained('verses')->onDelete('cascade');
            $table->string('variant_type', 20)->comment('x-literal, alternate, variant');
            $table->text('text_content')->comment('The variant text');
            $table->integer('position_start')->nullable()->comment('Start position in verse');
            $table->integer('position_end')->nullable()->comment('End position in verse');
            $table->text('manuscript_support')->nullable()->comment('Manuscript evidence for variant');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id', 'variant_type']);
            $table->index(['variant_type']);
 // For variant searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_variants');
    }
};
