<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Throttle on email+IP. The site is public, so an unthrottled login form is a
        // standing invitation to credential stuffing.
        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.
                    ceil(RateLimiter::availableIn($key) / 60).' minute(s).',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            // One generic message: distinguishing "no such user" from "wrong password"
            // confirms which addresses exist.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);

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
