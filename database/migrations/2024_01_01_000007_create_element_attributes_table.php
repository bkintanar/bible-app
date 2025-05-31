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
        Schema::create('element_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained('text_elements')->onDelete('cascade');
            $table->string('attribute_name', 50)->comment('type, who, level, marker');
            $table->string('attribute_value')->comment('added, Jesus, 1, etc.');
            $table->timestamps();

            $table->index(['element_id']);
            $table->index(['attribute_name', 'attribute_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('element_attributes');
    }
};
