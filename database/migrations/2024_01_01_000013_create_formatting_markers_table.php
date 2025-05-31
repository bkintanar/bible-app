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
        Schema::create('formatting_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->nullable()->constrained('verses')->onDelete('cascade');
            $table->foreignId('chapter_id')->nullable()->constrained('chapters')->onDelete('cascade');
            $table->string('marker_type', 20)->comment('lb (line break), pb (page break)');
            $table->integer('position')->comment('Position within verse/chapter');
            $table->timestamps();

            $table->index(['verse_id']);
            $table->index(['chapter_id']);
            $table->index(['marker_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formatting_markers');
    }
};
