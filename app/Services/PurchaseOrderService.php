<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get paginated purchase orders with filters.
     */
    public function getPaginatedPurchaseOrders(Request $request): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['supplier', 'user']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Create a new purchase order.
     */
    public function createPurchaseOrder(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Create the purchase order
            $purchaseOrder = PurchaseOrder::create([
                'order_number' => PurchaseOrder::generateOrderNumber(),
                'supplier_id' => $data['supplier_id'],
                'status' => 'pending',
                'order_date' => $data['order_date'] ?? now(),
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => Auth::id(),
                'subtotal' => 0,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'tax_amount' => 0,
                'total_amount' => 0,
            ]);

            // Add order details if provided
            if (isset($data['details']) && is_array($data['details'])) {
                $this->addOrderDetails($purchaseOrder, $data['details']);
            }

            return [
                'success' => true,
                'message' => 'Purchase order created successfully.',
                'purchase_order' => $purchaseOrder->load(['supplier', 'details.product']),
            ];
        });
    }

    /**
     * Update an existing purchase order.
     */
    public function updatePurchaseOrder(PurchaseOrder $purchaseOrder, array $data): array
    {
        // Check if order can be modified
        if (!$this->canModifyOrder($purchaseOrder)) {
            return [
                'success' => false,
                'message' => 'Cannot modify order in current status.',
            ];
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            // Update order details
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'expected_date' => $data['expected_date'] ?? $purchaseOrder->expected_date,
                'notes' => $data['notes'] ?? $purchaseOrder->notes,
                'tax_rate' => $data['tax_rate'] ?? $purchaseOrder->tax_rate,
            ]);

            // Update order details if provided
            if (isset($data['details']) && is_array($data['details'])) {
                // Remove existing details
                $purchaseOrder->details()->delete();
                
                // Add new details
                $this->addOrderDetails($purchaseOrder, $data['details']);
            }

            return [
                'success' => true,
                'message' => 'Purchase order updated successfully.',
                'purchase_order' => $purchaseOrder->load(['supplier', 'details.product']),
            ];
        });
    }

    /**
     * Change purchase order status.
     */
    public function changeOrderStatus(PurchaseOrder $purchaseOrder, string $status, ?string $notes = null): array
    {
        if (!$this->isValidStatusTransition($purchaseOrder->status, $status)) {
            return [
                'success' => false,
                'message' => "Cannot change status from {$purchaseOrder->status} to {$status}.",
            ];
        }

        $purchaseOrder->update([
            'status' => $status,
            'notes' => $notes ? ($purchaseOrder->notes . "\n\n" . $notes) : $purchaseOrder->notes,
        ]);

        // If status is received, update stock levels
        if ($status === 'received') {
            $this->processReceivedOrder($purchaseOrder);
        }

        return [
            'success' => true,
            'message' => "Purchase order status changed to {$status}.",
        ];
    }

    /**
     * Get purchase order with all related data.
     */
    public function getPurchaseOrderWithRelations(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder->load([
            'supplier',
            'user',
            'details.product',
        ]);
    }

    /**
     * Get purchase order statistics.
     */
    public function getPurchaseOrderStatistics(PurchaseOrder $purchaseOrder): array
    {
        $details = $purchaseOrder->details;

        return [
            'total_items' => $details->count(),
            'total_quantity' => $details->sum('quantity'),
            'average_unit_price' => $details->avg('unit_price'),
            'highest_unit_price' => $details->max('unit_price'),
            'lowest_unit_price' => $details->min('unit_price'),
        ];
    }

    /**
     * Get form data for purchase order forms.
     */
    public function getFormData(): array
    {
        return [
            'suppliers' => Supplier::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'products' => Product::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'cost_price']),
        ];
    }

    /**
     * Get pending orders for dashboard.
     */
    public function getPendingOrders(int $limit = 10)
    {
        return PurchaseOrder::with(['supplier'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('order_date', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, Request $request): void
    {
        // Supplier filter
        if ($request->filled('supplier')) {
            $query->where('supplier_id', $request->input('supplier'));
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->input('date_to'));
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $search = $request->input('search');
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                      $supplierQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }
    }

    /**
     * Apply sorting to the query.
     */
    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', 'order_date');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
    }

    /**
     * Add order details to purchase order.
     */
    private function addOrderDetails(PurchaseOrder $purchaseOrder, array $details): void
    {
        $subtotal = 0;

        foreach ($details as $detail) {
            $lineTotal = $detail['quantity'] * $detail['unit_price'];
            $subtotal += $lineTotal;

            PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $detail['product_id'],
                'quantity' => $detail['quantity'],
                'unit_price' => $detail['unit_price'],
                'line_total' => $lineTotal,
            ]);
        }

        // Update totals
        $taxAmount = $subtotal * ($purchaseOrder->tax_rate / 100);
        $totalAmount = $subtotal + $taxAmount;

        $purchaseOrder->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Check if order can be modified.
     */
    private function canModifyOrder(PurchaseOrder $purchaseOrder): bool
    {
        return in_array($purchaseOrder->status, ['pending', 'confirmed']);
    }

    /**
     * Check if status transition is valid.
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['received', 'cancelled'],
            'received' => [],
            'cancelled' => [],
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Process received order by updating stock levels.
     */
    private function processReceivedOrder(PurchaseOrder $purchaseOrder): void
    {
        // This would typically integrate with StockTransactionService
        // to create stock-in transactions for each item received
        
        // For now, we'll add a comment about this integration point
        // In a real implementation, you would:
        // 1. Create stock transactions for each order detail
        // 2. Update stock levels at the specified location
        // 3. Log the receiving process
    }
}
