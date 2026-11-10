<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Create or update the admin account.
 *
 * There is no public registration route by design - this is a single-editor app on a public
 * domain, so the only way to get an account is here, on the box.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {--email= : Account email}
        {--name= : Display name}
        {--password= : Password (omit to be prompted without echo)}';

    protected $description = 'Create or update an admin account for the verse-title editor';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email');
        $name = $this->option('name') ?: $this->ask('Name', 'Admin');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'name' => ['required', 'string', 'max:255'],
                // Longer than Laravel's default 8: this guards a public admin surface and
                // there is no second factor.
                'password' => ['required', Password::min(12)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        // A new remember token voids every "remember me" cookie issued before this change, and
        // auth.session logs the other open sessions out on their next request. (Not fillable,
        // so set directly: through updateOrCreate it would be silently dropped.)
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->info($existing
            ? "✅ Password updated for {$user->email}"
            : "✅ Admin created: {$user->email}");

        return self::SUCCESS;
    }
}
