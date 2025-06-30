<?php

namespace App\Providers;

use App\Services\CategoryService;
use App\Services\DashboardService;
use App\Services\LocationService;
use App\Services\ProductService;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use App\Services\StockTransactionService;
use App\Services\SupplierService;
use App\Services\UserService;
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
        $this->app->singleton(SupplierService::class);
        $this->app->singleton(StockService::class);
        $this->app->singleton(StockTransactionService::class);
        $this->app->singleton(PurchaseOrderService::class);
        $this->app->singleton(UserService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
