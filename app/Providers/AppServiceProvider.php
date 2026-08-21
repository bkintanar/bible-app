<?php

namespace App\Providers;

use App\Services\BibleService;
use App\Services\TranslationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Keyed on the real client IP, which requires the Cloudflare proxy ranges in
        // config/trustedproxy.php; without those every request would key on a Cloudflare
        // edge address and a handful of clients would share one bucket.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Search is the expensive endpoint (FTS5 over 31,102 verses, limit up to 500),
        // so it gets a tighter bucket of its own.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
