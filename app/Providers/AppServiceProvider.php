<?php

namespace App\Providers;

use App\Services\BibleService;
use App\Services\TranslationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One TranslationService per request, shared with BibleService (it memoises translation
        // lookups; separate instances each queried bible_versions again).
        $this->app->singleton(TranslationService::class);

        // Register BibleService as a singleton
        $this->app->singleton(BibleService::class, function ($app) {
            return new BibleService($app->make(TranslationService::class));
        });

        // Alternative: Auto-resolve as singleton
        // $this->app->singleton(BibleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // /up is the uptime probe: it must fail when the Bible cannot be read, not just when PHP is
        // up. Laravel answers 500 if a DiagnosingHealth listener throws.
        Event::listen(DiagnosingHealth::class, function () {
            DB::select('select 1 from verses limit 1');
            DB::connection('runtime')->select('select 1');
        });

        // throttleApi() in bootstrap/app.php resolves the 'api' limiter defined here.
        // Laravel 12's slim skeleton ships 'throttle:api' COMMENTED OUT, so the eight public
        // API routes were entirely unthrottled - and this origin is now internet-facing.
        //
        // Keyed on the real client IP, which requires the tunnel peer (10.0.10.31) to be a
        // trusted proxy (config/trustedproxy.php); otherwise every public request would key on
        // the tunnel's address and all readers would share one bucket.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Search is the expensive endpoint (FTS5 over 31,102 verses, limit up to 500),
        // so it gets a tighter bucket of its own.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Every Livewire round-trip (actions, wire:model updates) goes through one endpoint that
        // the web middleware alone left unthrottled. 120/min/IP is far above what reading needs.
        RateLimiter::for('livewire', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)
            ->middleware(['web', 'throttle:livewire']));

        // Livewire re-applies only "persistent" middleware from the page's route to its update
        // requests, and its default list has Jetstream's AuthenticateSession, not the framework's.
        // Without this, a session opened before a password change could keep saving titles.
        Livewire::addPersistentMiddleware([\Illuminate\Session\Middleware\AuthenticateSession::class]);
    }
}
