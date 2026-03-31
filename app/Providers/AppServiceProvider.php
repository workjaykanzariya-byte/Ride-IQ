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
        if (config('services.location.mock')) {
            $this->app->bind(LocationServiceInterface::class, MockLocationService::class);

            return;
        }

        $this->app->bind(LocationServiceInterface::class, GoogleLocationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
