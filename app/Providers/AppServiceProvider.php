<?php

namespace App\Providers;

use App\Services\BibleService;
use App\Services\TranslationService;
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
        //
    }
}
