<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// Inventory System Routes - Requires authentication and inventory access
Route::middleware(['auth', 'verified', 'inventory.access'])->prefix('inventory')->name('inventory.')->group(function () {

    // Main Inventory Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Categories - CRUD with permissions
    Route::middleware('permission:view categories')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['destroy']);
    });
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('permission:delete categories')
        ->name('categories.destroy');

    // Locations - CRUD with permissions  
    Route::middleware('permission:view locations')->group(function () {
        Route::resource('locations', LocationController::class)->except(['destroy']);
    });
    Route::delete('locations/{location}', [LocationController::class, 'destroy'])
        ->middleware('permission:delete locations')
        ->name('locations.destroy');

    // Products - CRUD with permissions
    Route::middleware('permission:view products')->group(function () {
        Route::resource('products', ProductController::class)->except(['store', 'update', 'destroy']);
    });
    Route::post('products', [ProductController::class, 'store'])
        ->middleware('permission:create products')
        ->name('products.store');
    Route::put('products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:edit products')
        ->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:delete products')
        ->name('products.destroy');

    // Stock Management - Requires specific permissions
    Route::get('/stock', function () {
        return Inertia::render('inventory/stock/index');
    })->middleware('permission:view stock')->name('stock.index');

    Route::post('/stock/transactions', function () {
        return response()->json(['message' => 'Stock transaction created']);
    })->middleware('permission:create stock transactions')->name('stock.transactions.store');

    // Admin Only Routes
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', function () {
            return Inertia::render('inventory/admin/users');
        })->name('users.index');

        Route::get('/settings', function () {
            return Inertia::render('inventory/admin/settings');
        })->name('settings');
    });

    // Manager and Admin Routes (flexible permission check)
    Route::middleware('role_or_permission:admin|manage stock')->group(function () {
        Route::get('/reports', function () {
            return Inertia::render('inventory/reports/index');
        })->name('reports.index');

        Route::get('/purchase-orders', function () {
            return Inertia::render('inventory/purchase-orders/index');
        })->name('purchase-orders.index');
    });

    // Stock Manager and Admin Routes
    Route::middleware('role:stock_manager|admin')->group(function () {
        Route::get('/suppliers', function () {
            return Inertia::render('inventory/suppliers/index');
        })->name('suppliers.index');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
