<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get paginated products with filters and relationships.
     */
    public function getPaginatedProducts(Request $request): LengthAwarePaginator
    {
        $query = Product::with(['category', 'supplier']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->withSum('stocks', 'quantity')
            ->withSum('stocks', 'reserved_quantity')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Create a new product.
     */
    public function createProduct(array $data): Product
    {
        $data['is_active'] = $data['is_active'] ?? true;
        
        return Product::create($data);
    }

    /**
     * Update an existing product.
     */
    public function updateProduct(Product $product, array $data): bool
    {
        return $product->update($data);
    }

    /**
     * Check if product can be deleted.
     */
    public function canDeleteProduct(Product $product): array
    {
        $hasStock = $product->stocks()->sum('quantity') > 0;
        $hasPendingOrders = $product->purchaseOrderDetails()
            ->whereHas('purchaseOrder', function ($query) {
                $query->whereIn('status', ['pending', 'confirmed']);
            })->exists();

        return [
            'can_delete' => !$hasStock && !$hasPendingOrders,
            'has_stock' => $hasStock,
            'has_pending_orders' => $hasPendingOrders,
        ];
    }

    /**
     * Delete a product.
     */
    public function deleteProduct(Product $product): array
    {
        $deletionCheck = $this->canDeleteProduct($product);
        
        if (!$deletionCheck['can_delete']) {
            return [
                'success' => false,
                'message' => $this->getDeletionErrorMessage($deletionCheck),
            ];
        }

        $product->delete();

        return [
            'success' => true,
            'message' => 'Product deleted successfully.',
        ];
    }

    /**
     * Get product with all relations for detailed view.
     */
    public function getProductWithRelations(Product $product): Product
    {
        return $product->load([
            'category',
            'supplier',
            'stocks.location',
            'stockTransactions' => function ($query) {
                $query->with(['location', 'user'])
                      ->orderBy('created_at', 'desc')
                      ->take(20);
            }
        ]);
    }

    /**
     * Get product analysis data.
     */
    public function getProductAnalysis(Product $product): array
    {
        return [
            'total_stock' => $product->total_stock,
            'available_stock' => $product->available_stock,
            'is_low_stock' => $product->total_stock <= $product->min_stock_level,
            'stock_value' => $product->total_stock * $product->cost_price,
            'stock_locations' => $product->stocks()->with('location')->get(),
        ];
    }

    /**
     * Get low stock products.
     */
    public function getLowStockProducts(int $limit = 10)
    {
        return Product::with(['category', 'stocks.location'])
            ->whereHas('stocks', function ($query) {
                $query->havingRaw('SUM(quantity) <= products.min_stock_level');
            })
            ->withSum('stocks', 'quantity')
            ->orderBy('stocks_sum_quantity', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Get form data for create/edit forms.
     */
    public function getFormData(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Apply all filters to the query.
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        // Search filter
        if ($request->filled('search')) {
            $this->applySearchFilter($query, $request->input('search'));
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Supplier filter
        if ($request->filled('supplier')) {
            $query->where('supplier_id', $request->input('supplier'));
        }

        // Active status filter
        if ($request->filled('status')) {
            $this->applyStatusFilter($query, $request->input('status'));
        }

        // Low stock filter
        if ($request->filled('low_stock') && $request->input('low_stock') === 'true') {
            $this->applyLowStockFilter($query);
        }
    }

    /**
     * Apply search filter to query.
     */
    private function applySearchFilter(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Apply status filter to query.
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }

    /**
     * Apply low stock filter to query.
     */
    private function applyLowStockFilter(Builder $query): void
    {
        $query->whereHas('stocks', function ($q) {
            $q->havingRaw('SUM(quantity) <= products.min_stock_level');
        });
    }

    /**
     * Apply sorting to query.
     */
    private function applySorting(Builder $query, Request $request): void
    {
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
    }

    /**
     * Get deletion error message based on deletion check.
     */
    private function getDeletionErrorMessage(array $deletionCheck): string
    {
        if ($deletionCheck['has_stock']) {
            return 'Cannot delete product with existing stock. Please remove all stock first.';
        }

        if ($deletionCheck['has_pending_orders']) {
            return 'Cannot delete product with pending purchase orders.';
        }

        return 'Cannot delete this product.';
    }
}
