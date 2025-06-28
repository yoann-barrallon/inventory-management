<?php

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

    // Dashboard - Available to all inventory users
    Route::get('/', function () {
        return Inertia::render('inventory/dashboard');
    })->name('dashboard');

    // Products - Basic view for all, management for stock_manager and admin
    Route::get('/products', function () {
        return Inertia::render('inventory/products/index');
    })->middleware('permission:view products')->name('products.index');

    Route::get('/products/create', function () {
        return Inertia::render('inventory/products/create');
    })->middleware('permission:create products')->name('products.create');

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
