<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\GenericFilterDto;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierService
{
    /**
     * Get paginated suppliers with filters.
     */
    public function getPaginatedSuppliers(GenericFilterDto $filters): LengthAwarePaginator
    {
        $query = Supplier::query();

        // Apply search filter
        if ($filters->hasSearch()) {
            $this->applySearchFilter($query, $filters->search);
        }

        // Apply status filter
        if ($filters->hasStatus()) {
            $this->applyStatusFilter($query, $filters->status);
        }

        // Apply sorting
        $query->orderBy($filters->sortBy, $filters->sortDirection);

        return $query->withCount('products')
            ->paginate($filters->perPage);
    }

    /**
     * Create a new supplier.
     */
    public function createSupplier(array $data): Supplier
    {
        $data['is_active'] = $data['is_active'] ?? true;
        
        return Supplier::create($data);
    }

    /**
     * Update an existing supplier.
     */
    public function updateSupplier(Supplier $supplier, array $data): bool
    {
        return $supplier->update($data);
    }

    /**
     * Check if supplier can be deleted.
     */
    public function canDeleteSupplier(Supplier $supplier): bool
    {
        return $supplier->products()->count() === 0 && 
               $supplier->purchaseOrders()->count() === 0;
    }

    /**
     * Delete a supplier.
     */
    public function deleteSupplier(Supplier $supplier): array
    {
        if (!$this->canDeleteSupplier($supplier)) {
            return [
                'success' => false,
                'message' => 'Cannot delete supplier that has products or purchase orders. Please remove them first.',
            ];
        }

        $supplier->delete();

        return [
            'success' => true,
            'message' => 'Supplier deleted successfully.',
        ];
    }

    /**
     * Get supplier with related data for detailed view.
     */
    public function getSupplierWithRelations(Supplier $supplier): Supplier
    {
        return $supplier->load([
            'products' => function ($query) {
                $query->with('category')
                      ->where('is_active', true)
                      ->orderBy('name')
                      ->take(10);
            },
            'purchaseOrders' => function ($query) {
                $query->orderBy('created_at', 'desc')
                      ->take(5);
            }
        ]);
    }

    /**
     * Get supplier statistics.
     */
    public function getSupplierStatistics(Supplier $supplier): array
    {
        $products = $supplier->products()->with('stocks')->get();
        $purchaseOrders = $supplier->purchaseOrders;

        return [
            'total_products' => $products->count(),
            'total_purchase_orders' => $purchaseOrders->count(),
            'pending_orders' => $purchaseOrders->where('status', 'pending')->count(),
            'total_order_value' => $purchaseOrders->where('status', '!=', 'cancelled')->sum('total_amount'),
            'products_in_stock' => $products->filter(function ($product) {
                return $product->total_stock > 0;
            })->count(),
        ];
    }

    /**
     * Get suppliers for dropdown/select options.
     */
    public function getSuppliersForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Supplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Apply search filter to query.
     */
    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('contact_person', 'like', "%{$search}%");
        });
    }

    /**
     * Apply status filter to query.
     */
    private function applyStatusFilter($query, string $status): void
    {
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }
}
