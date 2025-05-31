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
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('bible_versions')->onDelete('cascade');
            $table->string('milestone_type', 50)->comment('verse, chapter, q');
            $table->string('osis_id', 100)->comment('Full OSIS identifier');
            $table->string('start_id', 100)->nullable()->comment('sID attribute');
            $table->string('end_id', 100)->nullable()->comment('eID attribute');
            $table->integer('start_position')->nullable()->comment('Position in document');
            $table->integer('end_position')->nullable();
            $table->json('attributes_json')->nullable()->comment('JSON storage for all attributes');
            $table->timestamps();

            $table->index(['milestone_type', 'version_id']);
            $table->index(['osis_id']);
            $table->index(['start_id']);
            $table->index(['end_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
