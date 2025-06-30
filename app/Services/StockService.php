<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get paginated stock levels with filters.
     */
    public function getPaginatedStockLevels(Request $request): LengthAwarePaginator
    {
        $query = Stock::with(['product.category', 'location'])
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->select('stocks.*');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Get stock levels for a specific product across all locations.
     */
    public function getProductStockLevels(Product $product)
    {
        return Stock::with('location')
            ->where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'desc')
            ->get();
    }

    /**
     * Get stock levels for a specific location.
     */
    public function getLocationStockLevels(Location $location)
    {
        return Stock::with(['product.category'])
            ->where('location_id', $location->id)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'desc')
            ->get();
    }

    /**
     * Get low stock alert data.
     */
    public function getLowStockAlerts(int $limit = 50)
    {
        return Stock::with(['product.category', 'location'])
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->whereRaw('stocks.quantity <= products.min_stock_level')
            ->where('stocks.quantity', '>', 0)
            ->orderBy('stocks.quantity', 'asc')
            ->select('stocks.*')
            ->take($limit)
            ->get();
    }

    /**
     * Get overstock alerts (products with excessive stock).
     */
    public function getOverstockAlerts(int $multiplier = 3, int $limit = 50)
    {
        return Stock::with(['product.category', 'location'])
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->whereRaw("stocks.quantity > (products.min_stock_level * {$multiplier})")
            ->orderBy('stocks.quantity', 'desc')
            ->select('stocks.*')
            ->take($limit)
            ->get();
    }

    /**
     * Get zero stock products.
     */
    public function getZeroStockProducts()
    {
        return Product::with('category')
            ->whereDoesntHave('stocks', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get stock valuation report.
     */
    public function getStockValuation(): array
    {
        $valuation = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->selectRaw('
                SUM(stocks.quantity * products.cost_price) as total_cost_value,
                SUM(stocks.quantity * products.selling_price) as total_selling_value,
                SUM(stocks.quantity) as total_quantity,
                COUNT(DISTINCT stocks.product_id) as unique_products,
                COUNT(DISTINCT stocks.location_id) as active_locations
            ')
            ->first();

        $profit_margin = $valuation->total_selling_value > 0 
            ? (($valuation->total_selling_value - $valuation->total_cost_value) / $valuation->total_selling_value) * 100 
            : 0;

        return [
            'total_cost_value' => $valuation->total_cost_value ?? 0,
            'total_selling_value' => $valuation->total_selling_value ?? 0,
            'potential_profit' => ($valuation->total_selling_value ?? 0) - ($valuation->total_cost_value ?? 0),
            'profit_margin_percentage' => round($profit_margin, 2),
            'total_quantity' => $valuation->total_quantity ?? 0,
            'unique_products' => $valuation->unique_products ?? 0,
            'active_locations' => $valuation->active_locations ?? 0,
        ];
    }

    /**
     * Get stock distribution by category.
     */
    public function getStockByCategory()
    {
        return DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', 
                DB::raw('SUM(stocks.quantity) as total_quantity'),
                DB::raw('SUM(stocks.quantity * products.cost_price) as total_value'),
                DB::raw('COUNT(DISTINCT products.id) as product_count')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_quantity', 'desc')
            ->get();
    }

    /**
     * Get stock distribution by location.
     */
    public function getStockByLocation()
    {
        return DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('locations', 'stocks.location_id', '=', 'locations.id')
            ->select('locations.name',
                DB::raw('SUM(stocks.quantity) as total_quantity'),
                DB::raw('SUM(stocks.quantity * products.cost_price) as total_value'),
                DB::raw('COUNT(DISTINCT products.id) as product_count')
            )
            ->groupBy('locations.id', 'locations.name')
            ->orderBy('total_quantity', 'desc')
            ->get();
    }

    /**
     * Get stock movement summary for a time period.
     */
    public function getStockMovementSummary(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $movements = DB::table('stock_transactions')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                type,
                COUNT(*) as transaction_count,
                SUM(quantity) as total_quantity
            ')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return [
            'stock_in' => [
                'count' => $movements->get('in')->transaction_count ?? 0,
                'quantity' => $movements->get('in')->total_quantity ?? 0,
            ],
            'stock_out' => [
                'count' => $movements->get('out')->transaction_count ?? 0,
                'quantity' => $movements->get('out')->total_quantity ?? 0,
            ],
            'adjustments' => [
                'count' => $movements->get('adjustment')->transaction_count ?? 0,
                'quantity' => $movements->get('adjustment')->total_quantity ?? 0,
            ],
            'period_days' => $days,
        ];
    }

    /**
     * Get top products by stock value.
     */
    public function getTopProductsByValue(int $limit = 10)
    {
        return Stock::with(['product.category'])
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->selectRaw('
                stocks.*,
                (stocks.quantity * products.cost_price) as stock_value
            ')
            ->orderBy('stock_value', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get products with no movement in specified days.
     */
    public function getSlowMovingProducts(int $days = 90)
    {
        return Product::with(['category', 'stocks'])
            ->whereHas('stocks', function ($query) {
                $query->where('quantity', '>', 0);
            })
            ->whereDoesntHave('stockTransactions', function ($query) use ($days) {
                $query->where('created_at', '>=', now()->subDays($days));
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get stock aging report (how long products have been in stock).
     */
    public function getStockAgingReport()
    {
        // This would typically require tracking when stock was received
        // For now, we'll return stocks with their last transaction date
        return Stock::with(['product', 'location'])
            ->leftJoin('stock_transactions', function ($join) {
                $join->on('stocks.product_id', '=', 'stock_transactions.product_id')
                     ->on('stocks.location_id', '=', 'stock_transactions.location_id')
                     ->where('stock_transactions.type', '=', 'in');
            })
            ->select('stocks.*', 
                DB::raw('MAX(stock_transactions.created_at) as last_received_at'),
                DB::raw('DATEDIFF(NOW(), MAX(stock_transactions.created_at)) as days_in_stock')
            )
            ->groupBy('stocks.id', 'stocks.product_id', 'stocks.location_id', 'stocks.quantity', 'stocks.reserved_quantity')
            ->orderBy('days_in_stock', 'desc')
            ->get();
    }

    /**
     * Get form data for stock views.
     */
    public function getFormData(): array
    {
        return [
            'products' => Product::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'locations' => Location::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => \App\Models\Category::orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /**
     * Apply filters to the stock query.
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // Product filter
        if ($request->filled('product')) {
            $query->where('stocks.product_id', $request->input('product'));
        }

        // Location filter
        if ($request->filled('location')) {
            $query->where('stocks.location_id', $request->input('location'));
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('products.category_id', $request->input('category'));
        }

        // Stock level filter
        if ($request->filled('stock_level')) {
            $level = $request->input('stock_level');
            if ($level === 'low') {
                $query->whereRaw('stocks.quantity <= products.min_stock_level');
            } elseif ($level === 'zero') {
                $query->where('stocks.quantity', 0);
            } elseif ($level === 'positive') {
                $query->where('stocks.quantity', '>', 0);
            }
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Apply sorting to the stock query.
     */
    private function applySorting(Builder $query, Request $request): void
    {
        $sortField = $request->input('sort', 'products.name');
        $sortDirection = $request->input('direction', 'asc');
        
        // Handle special sorting cases
        if ($sortField === 'stock_value') {
            $query->orderByRaw('(stocks.quantity * products.cost_price) ' . $sortDirection);
        } else {
            $query->orderBy($sortField, $sortDirection);
        }
    }
}
