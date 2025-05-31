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
        Schema::create('book_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('Old Testament, New Testament');
            $table->boolean('canonical')->default(true);
            $table->string('sub_type', 20)->nullable()->comment('x-OT, x-NT from OSIS');
            $table->integer('sort_order');
            $table->timestamps();

            $table->index(['sort_order']);
            $table->index(['canonical']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_groups');
    }
};
