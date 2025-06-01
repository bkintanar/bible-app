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
        Schema::create('word_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('source_strongs', 20)->comment('Source Strong\'s number');
            $table->string('target_strongs', 20)->comment('Related Strong\'s number');
            $table->string('relationship_type', 50)->comment('synonym, antonym, derivative, root, variant, cognate');
            $table->text('description')->nullable()->comment('Description of the relationship');
            $table->integer('strength')->default(1)->comment('Relationship strength 1-10');
            $table->timestamps();

            $table->index(['source_strongs', 'relationship_type']);
            $table->index(['target_strongs', 'relationship_type']);
            $table->unique(['source_strongs', 'target_strongs', 'relationship_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_relationships');
    }
};
