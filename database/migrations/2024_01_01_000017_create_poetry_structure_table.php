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
        Schema::create('poetry_structure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained('verses')->onDelete('cascade');
            $table->string('structure_type', 20)->comment('line, lg, l, caesura');
            $table->integer('level')->default(1)->comment('Indentation level (1-4)');
            $table->text('line_text')->comment('The poetry line content');
            $table->integer('line_order')->comment('Order within the verse');
            $table->string('se_id', 100)->nullable()->comment('Start/End ID for complex poetry');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id', 'structure_type']);
            $table->index(['verse_id', 'line_order']);
            $table->index(['structure_type', 'level']);
 // For poetry searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poetry_structure');
    }
};
