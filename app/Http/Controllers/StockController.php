<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    /**
     * Display current stock levels across all locations.
     */
    public function index(Request $request): Response
    {
        $stockLevels = $this->stockService->getPaginatedStockLevels($request);
        $formData = $this->stockService->getFormData();

        return Inertia::render('Inventory/Stock/Index', [
            'stockLevels' => $stockLevels,
            'products' => $formData['products'],
            'locations' => $formData['locations'],
            'categories' => $formData['categories'],
            'filters' => [
                'search' => $request->input('search'),
                'product' => $request->input('product'),
                'location' => $request->input('location'),
                'category' => $request->input('category'),
                'stock_level' => $request->input('stock_level'),
                'sort' => $request->input('sort', 'products.name'),
                'direction' => $request->input('direction', 'asc'),
            ],
        ]);
    }

    /**
     * Display stock valuation report.
     */
    public function valuation(): Response
    {
        $valuation = $this->stockService->getStockValuation();
        $stockByCategory = $this->stockService->getStockByCategory();
        $stockByLocation = $this->stockService->getStockByLocation();
        $topProducts = $this->stockService->getTopProductsByValue(20);

        return Inertia::render('Inventory/Stock/Valuation', [
            'valuation' => $valuation,
            'stockByCategory' => $stockByCategory,
            'stockByLocation' => $stockByLocation,
            'topProducts' => $topProducts,
        ]);
    }

    /**
     * Display stock alerts (low stock, overstock, zero stock).
     */
    public function alerts(): Response
    {
        $lowStockAlerts = $this->stockService->getLowStockAlerts();
        $overstockAlerts = $this->stockService->getOverstockAlerts();
        $zeroStockProducts = $this->stockService->getZeroStockProducts();
        $slowMovingProducts = $this->stockService->getSlowMovingProducts();

        return Inertia::render('Inventory/Stock/Alerts', [
            'lowStockAlerts' => $lowStockAlerts,
            'overstockAlerts' => $overstockAlerts,
            'zeroStockProducts' => $zeroStockProducts,
            'slowMovingProducts' => $slowMovingProducts,
        ]);
    }

    /**
     * Display stock aging report.
     */
    public function aging(): Response
    {
        $agingReport = $this->stockService->getStockAgingReport();

        return Inertia::render('Inventory/Stock/Aging', [
            'agingReport' => $agingReport,
        ]);
    }

    /**
     * Display stock movement summary.
     */
    public function movements(Request $request): Response
    {
        $days = $request->integer('days', 30);
        $movementSummary = $this->stockService->getStockMovementSummary($days);

        return Inertia::render('Inventory/Stock/Movements', [
            'movementSummary' => $movementSummary,
            'selectedDays' => $days,
        ]);
    }

    /**
     * Display stock levels for a specific product.
     */
    public function product(Product $product): Response
    {
        $stockLevels = $this->stockService->getProductStockLevels($product);

        return Inertia::render('Inventory/Stock/Product', [
            'product' => $product->load('category'),
            'stockLevels' => $stockLevels,
            'totalStock' => $stockLevels->sum('quantity'),
            'locationCount' => $stockLevels->count(),
        ]);
    }

    /**
     * Display stock levels for a specific location.
     */
    public function location(Location $location): Response
    {
        $stockLevels = $this->stockService->getLocationStockLevels($location);

        return Inertia::render('Inventory/Stock/Location', [
            'location' => $location,
            'stockLevels' => $stockLevels,
            'totalQuantity' => $stockLevels->sum('quantity'),
            'productCount' => $stockLevels->count(),
            'totalValue' => $stockLevels->sum(function ($stock) {
                return $stock->quantity * $stock->product->cost_price;
            }),
        ]);
    }

    /**
     * Get stock levels for a product across all locations (AJAX).
     */
    public function getProductStock(Product $product): JsonResponse
    {
        $stockLevels = $this->stockService->getProductStockLevels($product);

        return response()->json([
            'product' => $product->load('category'),
            'stockLevels' => $stockLevels,
            'totalStock' => $stockLevels->sum('quantity'),
            'availableStock' => $stockLevels->sum(function ($stock) {
                return $stock->quantity - $stock->reserved_quantity;
            }),
        ]);
    }

    /**
     * Get stock levels for a location (AJAX).
     */
    public function getLocationStock(Location $location): JsonResponse
    {
        $stockLevels = $this->stockService->getLocationStockLevels($location);

        return response()->json([
            'location' => $location,
            'stockLevels' => $stockLevels,
            'summary' => [
                'total_quantity' => $stockLevels->sum('quantity'),
                'product_count' => $stockLevels->count(),
                'total_value' => $stockLevels->sum(function ($stock) {
                    return $stock->quantity * $stock->product->cost_price;
                }),
            ],
        ]);
    }

    /**
     * Get low stock alerts (AJAX).
     */
    public function getLowStockAlerts(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 50);
        $alerts = $this->stockService->getLowStockAlerts($limit);

        return response()->json($alerts);
    }

    /**
     * Get overstock alerts (AJAX).
     */
    public function getOverstockAlerts(Request $request): JsonResponse
    {
        $multiplier = $request->integer('multiplier', 3);
        $limit = $request->integer('limit', 50);
        $alerts = $this->stockService->getOverstockAlerts($multiplier, $limit);

        return response()->json($alerts);
    }

    /**
     * Get stock valuation data (AJAX).
     */
    public function getValuation(): JsonResponse
    {
        $valuation = $this->stockService->getStockValuation();

        return response()->json($valuation);
    }

    /**
     * Get stock distribution by category (AJAX).
     */
    public function getStockByCategory(): JsonResponse
    {
        $distribution = $this->stockService->getStockByCategory();

        return response()->json($distribution);
    }

    /**
     * Get stock distribution by location (AJAX).
     */
    public function getStockByLocation(): JsonResponse
    {
        $distribution = $this->stockService->getStockByLocation();

        return response()->json($distribution);
    }

    /**
     * Get slow moving products (AJAX).
     */
    public function getSlowMovingProducts(Request $request): JsonResponse
    {
        $days = $request->integer('days', 90);
        $products = $this->stockService->getSlowMovingProducts($days);

        return response()->json($products);
    }

    /**
     * Export stock levels to CSV.
     */
    public function exportCsv(Request $request)
    {
        // This would typically generate and download a CSV file
        // For now, we'll return the data that would be exported
        $stockLevels = $this->stockService->getPaginatedStockLevels($request);

        return response()->json([
            'message' => 'CSV export functionality would be implemented here',
            'data_count' => $stockLevels->total(),
        ]);
    }
}
