<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{


    /**
     * Get all dashboard statistics.
     */
    public function getDashboardStats(): array
    {
        return [
            'stats' => $this->getBasicStats(),
            'lowStockProducts' => $this->getLowStockProducts(),
            'recentActivity' => $this->getRecentActivity(),
            'stockByCategory' => $this->getStockByCategory(),
            'stockByLocation' => $this->getStockByLocation(),
            'monthlyTransactions' => $this->getMonthlyTransactionChart(),
            'purchaseOrdersStats' => $this->getPurchaseOrdersStats(),
        ];
    }

    /**
     * Get basic statistics for dashboard cards.
     */
    public function getBasicStats(): array
    {
        $totalProducts = Product::count();
        $totalCategories = Category::count();
        $totalSuppliers = Supplier::count();
        $totalLocations = Location::count();

        // Total stock value calculation
        $totalStockValue = Product::join('stocks', 'products.id', '=', 'stocks.product_id')
            ->sum(DB::raw('stocks.quantity * products.cost_price'));

        // Low stock count
        $lowStockCount = Product::whereRaw('(
            SELECT COALESCE(SUM(quantity), 0) 
            FROM stocks 
            WHERE stocks.product_id = products.id
        ) <= products.min_stock_level')->count();

        // Recent transactions count (last 7 days)
        $recentTransactionsCount = StockTransaction::where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // Pending purchase orders
        $pendingPurchaseOrders = PurchaseOrder::whereIn('status', ['pending', 'confirmed'])
            ->count();

        return [
            'total_products' => $totalProducts,
            'total_categories' => $totalCategories,
            'total_suppliers' => $totalSuppliers,
            'total_locations' => $totalLocations,
            'total_stock_value' => number_format($totalStockValue, 2),
            'low_stock_count' => $lowStockCount,
            'recent_transactions' => $recentTransactionsCount,
            'pending_purchase_orders' => $pendingPurchaseOrders,
        ];
    }

    /**
     * Get products with low stock levels.
     */
    public function getLowStockProducts(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with(['category', 'stocks.location'])
            ->whereRaw('(
                SELECT COALESCE(SUM(quantity), 0) 
                FROM stocks 
                WHERE stocks.product_id = products.id
            ) <= products.min_stock_level')
            ->withSum('stocks', 'quantity')
            ->orderBy('stocks_sum_quantity', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Get recent activity from stock transactions.
     */
    public function getRecentActivity(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return StockTransaction::with(['product', 'location', 'user'])
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'product_name' => $transaction->product->name,
                    'location_name' => $transaction->location->name,
                    'quantity' => $transaction->quantity,
                    'user_name' => $transaction->user->name,
                    'created_at' => $transaction->created_at,
                    'description' => $this->getActivityDescription($transaction),
                ];
            });
    }

    /**
     * Get stock distribution by category.
     */
    public function getStockByCategory(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::withSum('products.stocks', 'quantity')
            ->having('products_stocks_sum_quantity', '>', 0)
            ->orderBy('products_stocks_sum_quantity', 'desc')
            ->get(['id', 'name'])
            ->map(function ($category) {
                return [
                    'name' => $category->name,
                    'quantity' => $category->products_stocks_sum_quantity ?? 0,
                ];
            });
    }

    /**
     * Get stock distribution by location.
     */
    public function getStockByLocation(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::withSum('stocks', 'quantity')
            ->having('stocks_sum_quantity', '>', 0)
            ->orderBy('stocks_sum_quantity', 'desc')
            ->get(['id', 'name'])
            ->map(function ($location) {
                return [
                    'name' => $location->name,
                    'quantity' => $location->stocks_sum_quantity ?? 0,
                ];
            });
    }

    /**
     * Get monthly transaction chart data.
     */
    public function getMonthlyTransactionChart(): array
    {
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $transactions = StockTransaction::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month', 'type')
            ->orderBy('month')
            ->get();

        $months = [];
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            $monthKey = $current->format('Y-m');
            $months[$monthKey] = [
                'month' => $current->format('M Y'),
                'in' => 0,
                'out' => 0,
                'adjustment' => 0,
            ];
            $current->addMonth();
        }

        foreach ($transactions as $transaction) {
            if (isset($months[$transaction->month])) {
                $months[$transaction->month][$transaction->type] = $transaction->count;
            }
        }

        return array_values($months);
    }

    /**
     * Get purchase orders statistics.
     */
    public function getPurchaseOrdersStats(): array
    {
        $stats = PurchaseOrder::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => $stats['pending'] ?? 0,
            'confirmed' => $stats['confirmed'] ?? 0,
            'received' => $stats['received'] ?? 0,
            'cancelled' => $stats['cancelled'] ?? 0,
        ];
    }

    /**
     * Get top performing categories by stock value.
     */
    public function getTopCategoriesByValue(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Category::select('categories.name')
            ->join('products', 'categories.id', '=', 'products.category_id')
            ->join('stocks', 'products.id', '=', 'stocks.product_id')
            ->selectRaw('SUM(stocks.quantity * products.cost_price) as total_value')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_value', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get activity description for transaction.
     */
    private function getActivityDescription(StockTransaction $transaction): string
    {
        $action = match ($transaction->type) {
            'in' => 'received',
            'out' => 'issued',
            'adjustment' => 'adjusted',
            default => 'processed',
        };

        return "{$transaction->quantity} units {$action} at {$transaction->location->name}";
    }
}
