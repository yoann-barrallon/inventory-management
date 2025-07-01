<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
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
    Route::middleware('permission:view stock')->group(function () {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/valuation', [StockController::class, 'valuation'])->name('stock.valuation');
        Route::get('/stock/alerts', [StockController::class, 'alerts'])->name('stock.alerts');
        Route::get('/stock/aging', [StockController::class, 'aging'])->name('stock.aging');
        Route::get('/stock/movements', [StockController::class, 'movements'])->name('stock.movements');
        Route::get('/stock/product/{product}', [StockController::class, 'product'])->name('stock.product');
        Route::get('/stock/location/{location}', [StockController::class, 'location'])->name('stock.location');
        
        // AJAX endpoints for stock data
        Route::get('/api/stock/product/{product}', [StockController::class, 'getProductStock'])->name('stock.api.product');
        Route::get('/api/stock/location/{location}', [StockController::class, 'getLocationStock'])->name('stock.api.location');
        Route::get('/api/stock/low-alerts', [StockController::class, 'getLowStockAlerts'])->name('stock.api.low-alerts');
        Route::get('/api/stock/overstock-alerts', [StockController::class, 'getOverstockAlerts'])->name('stock.api.overstock-alerts');
        Route::get('/api/stock/valuation', [StockController::class, 'getValuation'])->name('stock.api.valuation');
        Route::get('/api/stock/by-category', [StockController::class, 'getStockByCategory'])->name('stock.api.by-category');
        Route::get('/api/stock/by-location', [StockController::class, 'getStockByLocation'])->name('stock.api.by-location');
        Route::get('/api/stock/slow-moving', [StockController::class, 'getSlowMovingProducts'])->name('stock.api.slow-moving');
        Route::get('/api/stock/export-csv', [StockController::class, 'exportCsv'])->name('stock.api.export-csv');
    });

    // Stock Transactions - Requires specific permissions
    Route::middleware('permission:view stock transactions')->group(function () {
        Route::get('/stock-transactions', [StockTransactionController::class, 'index'])->name('stock-transactions.index');
    });
    Route::middleware('permission:create stock transactions')->group(function () {
        Route::get('/stock-transactions/create/stock-in', [StockTransactionController::class, 'createStockIn'])->name('stock-transactions.create.stock-in');
        Route::get('/stock-transactions/create/stock-out', [StockTransactionController::class, 'createStockOut'])->name('stock-transactions.create.stock-out');
        Route::get('/stock-transactions/create/adjustment', [StockTransactionController::class, 'createAdjustment'])->name('stock-transactions.create.adjustment');
        Route::get('/stock-transactions/create/transfer', [StockTransactionController::class, 'createTransfer'])->name('stock-transactions.create.transfer');
        Route::post('/stock-transactions', [StockTransactionController::class, 'store'])->name('stock-transactions.store');
        Route::post('/stock-transactions/transfer', [StockTransactionController::class, 'transfer'])->name('stock-transactions.transfer');
        
        // AJAX endpoints for stock transactions
        Route::get('/api/stock-transactions/levels', [StockTransactionController::class, 'getStockLevels'])->name('stock-transactions.api.levels');
        Route::get('/api/stock-transactions/product-history', [StockTransactionController::class, 'getProductHistory'])->name('stock-transactions.api.product-history');
        Route::get('/api/stock-transactions/location-history', [StockTransactionController::class, 'getLocationHistory'])->name('stock-transactions.api.location-history');
    });

    // Admin Only Routes
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        // User management - Full CRUD
        Route::resource('users', UserController::class);
        Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])->name('users.assign-role');
        Route::delete('users/{user}/remove-role', [UserController::class, 'removeRole'])->name('users.remove-role');
        
        // AJAX endpoints for user management
        Route::get('/api/users/by-role', [UserController::class, 'getUsersByRole'])->name('users.api.by-role');
        Route::get('/api/users/search', [UserController::class, 'search'])->name('users.api.search');
        Route::get('/api/users/{user}/activity', [UserController::class, 'getActivity'])->name('users.api.activity');

        Route::get('/settings', function () {
            return Inertia::render('inventory/admin/settings');
        })->name('settings');
    });

    // User Profile Routes (accessible to all authenticated users)
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');

    // Manager and Admin Routes (flexible permission check)
    Route::middleware('role_or_permission:admin|manage stock')->group(function () {
        Route::get('/reports', function () {
            return Inertia::render('inventory/reports/index');
        })->name('reports.index');

        // Purchase Orders - Full CRUD with special actions
        Route::resource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchaseOrder}/change-status', [PurchaseOrderController::class, 'changeStatus'])->name('purchase-orders.change-status');
        Route::post('purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->name('purchase-orders.confirm');
        Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
        Route::post('purchase-orders/{purchaseOrder}/duplicate', [PurchaseOrderController::class, 'duplicate'])->name('purchase-orders.duplicate');
    });

    // Stock Manager and Admin Routes
    Route::middleware('role:stock_manager|admin')->group(function () {
        // Suppliers - Full CRUD
        Route::resource('suppliers', SupplierController::class);
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
