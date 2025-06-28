<?php

namespace App\Providers;

use App\Services\CategoryService;
use App\Services\DashboardService;
use App\Services\LocationService;
use App\Services\ProductService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register service classes as singletons
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(LocationService::class);
        $this->app->singleton(DashboardService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
