<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal users table for the verse-title editor.
 *
 * config/auth.php has always pointed at App\Models\User, but neither the model nor this
 * table existed - so middleware('auth') was unsatisfiable and /admin/verse-titles was
 * reachable by anyone. That mattered once the app went public.
 *
 * Deliberately minimal: no email verification and no password_reset_tokens, because
 * MAIL_MAILER=log means neither could actually deliver. Accounts are created with
 * `php artisan admin:create`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
