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
        Schema::table('verses', function (Blueprint $table) {
            // Drop the unique constraint on osis_id to allow multiple versions
            $table->dropUnique(['osis_id']);

            // Keep the regular index for search performance
            // (the index already exists, so we don't need to add it)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verses', function (Blueprint $table) {
            // Restore the unique constraint (this might fail if there are duplicates)
            $table->unique('osis_id');
        });
    }
};
