<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the main inventory dashboard.
     */
    public function index(Request $request): Response
    {
        // Basic statistics
        $stats = [
            'total_products' => Product::where('is_active', true)->count(),
            'total_categories' => Category::count(),
            'total_suppliers' => Supplier::where('is_active', true)->count(),
            'total_locations' => Location::where('is_active', true)->count(),
            'low_stock_products' => $this->getLowStockCount(),
            'total_stock_value' => $this->getTotalStockValue(),
        ];

        // Recent stock transactions
        $recentTransactions = StockTransaction::with(['product', 'location', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Low stock products
        $lowStockProducts = $this->getLowStockProducts();

        // Purchase orders by status
        $purchaseOrderStats = PurchaseOrder::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Top categories by product count
        $topCategories = Category::withCount('products')
            ->orderBy('products_count', 'desc')
            ->take(5)
            ->get();

        // Stock levels by location
        $stockByLocation = Location::with(['stocks' => function ($query) {
            $query->select('location_id', DB::raw('SUM(quantity) as total_quantity'))
                ->groupBy('location_id');
        }])->where('is_active', true)->get();

        // Recent activity timeline
        $recentActivity = $this->getRecentActivity();

        // Monthly transaction trends (last 6 months)
        $transactionTrends = $this->getTransactionTrends();

        return Inertia::render('Inventory/Dashboard', [
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'lowStockProducts' => $lowStockProducts,
            'purchaseOrderStats' => $purchaseOrderStats,
            'topCategories' => $topCategories,
            'stockByLocation' => $stockByLocation,
            'recentActivity' => $recentActivity,
            'transactionTrends' => $transactionTrends,
        ]);
    }

    /**
     * Get count of products with low stock.
     */
    private function getLowStockCount(): int
    {
        return Product::whereHas('stocks', function ($query) {
            $query->havingRaw('SUM(quantity) <= products.min_stock_level');
        })->count();
    }

    /**
     * Get total stock value.
     */
    private function getTotalStockValue(): float
    {
        return Product::join('stocks', 'products.id', '=', 'stocks.product_id')
            ->sum(DB::raw('stocks.quantity * products.cost_price'));
    }

    /**
     * Get products with low stock levels.
     */
    private function getLowStockProducts()
    {
        return Product::with(['category', 'stocks.location'])
            ->whereHas('stocks', function ($query) {
                $query->havingRaw('SUM(quantity) <= products.min_stock_level');
            })
            ->withSum('stocks', 'quantity')
            ->orderBy('stocks_sum_quantity', 'asc')
            ->take(10)
            ->get();
    }

    /**
     * Get recent activity across the system.
     */
    private function getRecentActivity()
    {
        $activities = collect();

        // Recent stock transactions
        $transactions = StockTransaction::with(['product', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($transaction) {
                return [
                    'type' => 'stock_transaction',
                    'description' => "{$transaction->user->name} {$transaction->type} {$transaction->quantity} units of {$transaction->product->name}",
                    'created_at' => $transaction->created_at,
                    'user' => $transaction->user->name,
                ];
            });

        // Recent purchase orders
        $purchaseOrders = PurchaseOrder::with(['supplier', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'type' => 'purchase_order',
                    'description' => "{$order->user->name} created purchase order #{$order->order_number} for {$order->supplier->name}",
                    'created_at' => $order->created_at,
                    'user' => $order->user->name,
                ];
            });

        return $activities->concat($transactions)
            ->concat($purchaseOrders)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }

    /**
     * Get transaction trends for the last 6 months.
     */
    private function getTransactionTrends(): array
    {
        $trends = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');
            $monthName = $date->format('M Y');

            $inCount = StockTransaction::where('type', 'in')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $outCount = StockTransaction::where('type', 'out')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $adjustmentCount = StockTransaction::where('type', 'adjustment')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $trends[] = [
                'month' => $monthName,
                'in' => $inCount,
                'out' => $outCount,
                'adjustment' => $adjustmentCount,
                'total' => $inCount + $outCount + $adjustmentCount,
            ];
        }

        return $trends;
    }
}
