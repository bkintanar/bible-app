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
        Schema::create('bible_versions', function (Blueprint $table) {
            $table->id();
            $table->string('osis_work', 100)->unique()->comment('e.g., Bible.en.kjv');
            $table->string('abbreviation', 10)->comment('e.g., KJV, ASV');
            $table->string('title')->comment('e.g., King James Version');
            $table->string('language', 10)->comment('e.g., en');
            $table->text('description')->nullable();
            $table->string('publisher')->nullable();
            $table->text('rights')->nullable();
            $table->boolean('canonical')->default(true);
            $table->json('revision_history')->nullable()->comment('OSIS revision descriptions');
            $table->timestamps();

            $table->index(['abbreviation']);
            $table->index(['language']);
            $table->index(['canonical']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bible_versions');
    }
};
