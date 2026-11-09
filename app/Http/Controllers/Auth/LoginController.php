<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Session login for the admin area.
 *
 * Hand-rolled rather than scaffolded: there is exactly one protected route group, no
 * registration, and no password reset (MAIL_MAILER=log could not deliver one). Breeze or
 * Fortify would add their own layout and a much larger surface for no benefit here.
 *
 * Authentication itself goes through Laravel's guard - no hand-rolled credential checking.
 */
class LoginController extends Controller
{
    /** Attempts allowed per email+IP before lockout, and the decay window in seconds. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    /** Failed logins to ONE account, from any address, per hour before attempts are spaced out. */
    private const ACCOUNT_MAX_FAILURES = 50;

    private const ACCOUNT_DECAY_SECONDS = 3600;

    /** Above that threshold, one attempt per account per this many seconds. */
    private const ACCOUNT_SLOW_SECONDS = 10;

    public static function ipKey(string $email, string $ip): string
    {
        return 'login:'.strtolower($email).'|'.$ip;
    }

    public static function accountKey(string $email): string
    {
        return 'login-account:'.sha1(strtolower($email));
    }

    public static function accountSlowKey(string $email): string
    {
        return 'login-account-slow:'.sha1(strtolower($email));
    }

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(route('admin.verse-titles'));
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower($credentials['email']);
        $ipKey = self::ipKey($email, (string) $request->ip());
        $accountKey = self::accountKey($email);
        // Never the address itself in logs: a hash still correlates attempts on one account.
        $context = ['ip' => $request->ip(), 'email_hash' => sha1($email)];

        // Throttle on email+IP. The site is public, so an unthrottled login form is a
        // standing invitation to credential stuffing.
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS)) {
            Log::warning('login_locked', $context + ['scope' => 'email+ip']);

            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.
                    ceil(RateLimiter::availableIn($ipKey) / 60).' minute(s).',
            ]);
        }

        // A guessing run spread over many addresses never trips the email+IP limiter. Past the
        // per-account threshold every attempt on the account is SPACED OUT rather than refused
        // outright: ~360 guesses an hour at most, while the owner is never locked out for more
        // than a few seconds (a hard lock would hand any attacker a denial of service).
        if (RateLimiter::tooManyAttempts($accountKey, self::ACCOUNT_MAX_FAILURES)
            && ! RateLimiter::attempt(self::accountSlowKey($email), 1, fn () => true, self::ACCOUNT_SLOW_SECONDS)) {
            Log::warning('login_locked', $context + ['scope' => 'account']);

            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in a few seconds.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);
            $failures = RateLimiter::hit($accountKey, self::ACCOUNT_DECAY_SECONDS);

            Log::warning('login_failed', $context);

            if ($failures === self::ACCOUNT_MAX_FAILURES) {
                Log::alert('login: sustained failures on one account - attempts are now spaced out', $context + [
                    'failures_last_hour' => $failures,
                    'reset' => 'php artisan admin:unlock <email>',
                ]);
            }

            // One generic message: distinguishing "no such user" from "wrong password"
            // confirms which addresses exist.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        // The account counter is NOT cleared on success: the owner logging in must not hand an
        // ongoing guessing run a fresh budget. It decays on its own after an hour.
        RateLimiter::clear($ipKey);

        // Rotate the session id so a token captured before login cannot be reused after it.
        $request->session()->regenerate();

        return redirect()->intended(route('admin.verse-titles'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('bible.index');
    }
}
