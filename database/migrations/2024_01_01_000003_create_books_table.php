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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('osis_id', 20)->unique()->comment('e.g., Gen, Matt');
            $table->foreignId('book_group_id')->constrained('book_groups');
            $table->integer('number')->comment('1-66 for canonical books');
            $table->string('name', 100)->comment('Genesis, Matthew');
            $table->string('full_title')->nullable()->comment('The First Book of Moses, called Genesis');
            $table->string('short_name', 20)->nullable()->comment('Genesis');
            $table->boolean('canonical')->default(true);
            $table->integer('sort_order');
            $table->timestamps();

            $table->index(['osis_id']);
            $table->index(['sort_order']);
            $table->index(['canonical']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
