<?php

namespace App\Providers;

use App\Services\BibleService;
use App\Services\TranslationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }
}
