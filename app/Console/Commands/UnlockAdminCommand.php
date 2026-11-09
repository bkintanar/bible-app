<?php

namespace App\Console\Commands;

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Clear the login throttles for one account (after an attack, or a forgotten password storm).
 *
 * The per-account counters are keyed by a hash of the email, so they can be cleared directly. The
 * email+IP lockout is keyed per address and decays in 5 minutes on its own; pass --ip to clear it
 * for a specific address now.
 */
class UnlockAdminCommand extends Command
{
    protected $signature = 'admin:unlock
        {email : The account to unlock}
        {--ip= : Also clear the email+IP lockout for this address}';

    protected $description = 'Clear the login rate limits for an admin account';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        RateLimiter::clear(LoginController::accountKey($email));
        RateLimiter::clear(LoginController::accountSlowKey($email));
        $this->info('Cleared the per-account login limits.');

        if ($ip = $this->option('ip')) {
            RateLimiter::clear(LoginController::ipKey($email, $ip));
            $this->info("Cleared the email+IP lockout for {$ip}.");
        } else {
            $this->line('The email+IP lockout (if any) expires within 5 minutes; use --ip=<address> to clear it now.');
        }

        return self::SUCCESS;
    }
}
