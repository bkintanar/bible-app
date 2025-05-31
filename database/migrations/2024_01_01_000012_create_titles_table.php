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
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->nullable()->constrained('verses')->onDelete('cascade');
            $table->foreignId('chapter_id')->nullable()->constrained('chapters')->onDelete('cascade');
            $table->string('title_type', 20)->default('main')->comment('main, sub, acrostic, psalm');
            $table->text('title_text')->comment('The title content');
            $table->boolean('canonical')->default(true)->comment('Whether title is canonical');
            $table->string('placement', 20)->default('before')->comment('before, after, inline');
            $table->integer('title_order')->default(1)->comment('Order for multiple titles');
            $table->json('attributes')->nullable()->comment('Other OSIS attributes as JSON');
            $table->timestamps();

            $table->index(['verse_id', 'title_type']);
            $table->index(['chapter_id', 'title_type']);
            $table->index(['title_type']);
            $table->index(['canonical']);
 // For title searches
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titles');
    }
};
