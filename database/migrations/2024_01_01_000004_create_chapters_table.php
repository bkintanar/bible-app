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
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->foreignId('version_id')->constrained('bible_versions')->onDelete('cascade');
            $table->integer('chapter_number');
            $table->string('osis_ref', 50)->comment('e.g., Gen.1');
            $table->string('osis_id', 100)->nullable()->comment('Full OSIS ID from sID/eID');
            $table->string('chapter_title')->nullable()->comment('Chapter title if present');
            $table->string('se_id', 100)->nullable()->comment('Start/End ID for milestone tracking');
            $table->timestamps();

            $table->unique(['book_id', 'version_id', 'chapter_number']);
            $table->index(['book_id', 'chapter_number']);
            $table->index(['osis_ref']);
            $table->index(['osis_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
