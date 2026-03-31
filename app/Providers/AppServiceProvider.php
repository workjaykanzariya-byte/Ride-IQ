<?php

namespace App\Providers;

use App\Services\GoogleLocationService;
use App\Services\LocationServiceInterface;
use App\Services\MockLocationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LocationServiceInterface::class, function ($app) {
            if (config('services.location.mock')) {
                return $app->make(MockLocationService::class);
            }

            return $app->make(GoogleLocationService::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
