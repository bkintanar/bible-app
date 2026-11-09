<?php

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutVite();
    User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
});

function loginFrom(string $ip, string $password = 'wrong-password', string $email = 'admin@example.com')
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from('/login')
        ->post('/login', ['email' => $email, 'password' => $password]);
}

it('locks one address out after five failures, with a log line that hides the email', function () {
    Log::spy();

    foreach (range(1, 5) as $i) {
        loginFrom('198.51.100.7')->assertSessionHasErrors(['email' => 'Those credentials do not match our records.']);
    }

    loginFrom('198.51.100.7', 'correct-horse-battery')->assertSessionHasErrors('email');
    $this->assertGuest();

    Log::shouldHaveReceived('warning')->with('login_failed', Mockery::on(fn ($c) => $c['email_hash'] === sha1('admin@example.com') && ! in_array('admin@example.com', $c, true)))->times(5);
    Log::shouldHaveReceived('warning')->with('login_locked', Mockery::on(fn ($c) => $c['scope'] === 'email+ip'))->once();

    // Another address is unaffected.
    loginFrom('198.51.100.8', 'correct-horse-battery')->assertRedirect(route('admin.verse-titles'));
});

it('spaces out attempts on an account attacked from many addresses, without locking the owner out', function () {
    Log::spy();

    foreach (range(1, 50) as $i) {
        loginFrom('203.0.113.' . $i); // 50 addresses, one failure each: the email+IP limiter never trips
    }

    Log::shouldHaveReceived('alert')->once();

    // Over the threshold: one attempt per 10 s for the account, from any address.
    loginFrom('192.0.2.1')->assertSessionHasErrors(['email' => 'Those credentials do not match our records.']);
    loginFrom('192.0.2.2', 'correct-horse-battery')->assertSessionHasErrors(['email' => 'Too many attempts. Try again in a few seconds.']);
    $this->assertGuest();

    // The owner gets in a few seconds later.
    $this->travel(11)->seconds();
    loginFrom('192.0.2.2', 'correct-horse-battery')->assertRedirect(route('admin.verse-titles'));
    $this->assertAuthenticated();
});

it('admin:unlock clears the per-account limits (and the email+IP lockout with --ip)', function () {
    foreach (range(1, 50) as $i) {
        loginFrom('203.0.113.' . $i);
    }
    foreach (range(1, 5) as $i) {
        loginFrom('198.51.100.7');
    }

    expect(RateLimiter::tooManyAttempts(LoginController::accountKey('admin@example.com'), 50))->toBeTrue();

    $this->artisan('admin:unlock', ['email' => 'Admin@Example.com', '--ip' => '198.51.100.7'])->assertSuccessful();

    expect(RateLimiter::tooManyAttempts(LoginController::accountKey('admin@example.com'), 50))->toBeFalse();
    loginFrom('198.51.100.7', 'correct-horse-battery')->assertRedirect(route('admin.verse-titles'));
});
