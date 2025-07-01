<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreatePurchaseOrderDto;
use App\DTOs\PurchaseOrderDetailDto;
use App\DTOs\PurchaseOrderFilterDto;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Supplier;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

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
    public function getPaginatedPurchaseOrders(PurchaseOrderFilterDto $filters): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['supplier', 'user']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($filters->perPage);
    }

    /**
     * Create a new purchase order.
     */
    public function createPurchaseOrder(CreatePurchaseOrderDto $data): array
    {
        return DB::transaction(function () use ($data) {
            // Validate supplier exists and is active
            $supplier = Supplier::where('id', $data->supplierId)
                ->where('is_active', true)
                ->first();
            
            if (!$supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Selected supplier is not available.'
                ]);
            }

            // Create the purchase order
            $purchaseOrder = PurchaseOrder::create([
                'order_number' => PurchaseOrder::generateOrderNumber(),
                'supplier_id' => $data->supplierId,
                'status' => 'pending',
                'order_date' => $data->orderDate ?? now(),
                'expected_date' => $data->expectedDate,
                'notes' => $data->notes,
                'user_id' => Auth::id(),
                'subtotal' => 0,
                'tax_rate' => $data->taxRate ?? config('inventory.default_tax_rate', 0),
                'tax_amount' => 0,
                'total_amount' => 0,
            ]);

            // Add order details if provided
            if ($data->hasDetails()) {
                $this->addOrderDetails($purchaseOrder, $data->details);
            }

            // Log the creation
            Log::info('Purchase order created', [
                'order_id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'user_id' => Auth::id(),
            ]);

            // Fire event
            event('purchase-order.created', [$purchaseOrder]);

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

            // Log the update
            Log::info('Purchase order updated', [
                'order_id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'user_id' => Auth::id(),
            ]);

            // Fire event
            event('purchase-order.updated', [$purchaseOrder]);

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

        $oldStatus = $purchaseOrder->status;

        $purchaseOrder->update([
            'status' => $status,
            'notes' => $notes ? ($purchaseOrder->notes . "\n\n" . $notes) : $purchaseOrder->notes,
        ]);

        // Log status change
        Log::info('Purchase order status changed', [
            'order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'user_id' => Auth::id(),
        ]);

                    // Fire event
            event('purchase-order.status-changed', [$purchaseOrder, $oldStatus, $status]);

        return [
            'success' => true,
            'message' => "Purchase order status changed to {$status}.",
        ];
    }

    /**
     * Receive purchase order items (supports partial receiving).
     */
    public function receiveOrderItems(PurchaseOrder $purchaseOrder, array $receivedItems, int $locationId, ?string $notes = null): array
    {
        // Validate purchase order can be received
        if (!in_array($purchaseOrder->status, ['confirmed'])) {
            return [
                'success' => false,
                'message' => 'Purchase order must be confirmed before receiving items.',
            ];
        }

        // Validate location exists
        $location = Location::find($locationId);
        if (!$location) {
            return [
                'success' => false,
                'message' => 'Invalid location specified.',
            ];
        }

        return DB::transaction(function () use ($purchaseOrder, $receivedItems, $location, $notes) {
            $receivedDetails = [];
            $totalReceived = 0;
            $totalOrdered = $purchaseOrder->details->sum('quantity');

            foreach ($receivedItems as $item) {
                $orderDetail = $purchaseOrder->details()
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$orderDetail) {
                    throw ValidationException::withMessages([
                        'product_id' => "Product {$item['product_id']} is not in this purchase order."
                    ]);
                }

                $receivedQty = (int) $item['received_quantity'];
                
                if ($receivedQty <= 0) {
                    continue; // Skip items with zero or negative quantities
                }

                if ($receivedQty > $orderDetail->quantity) {
                    throw ValidationException::withMessages([
                        'received_quantity' => "Cannot receive more than ordered quantity for product {$orderDetail->product->name}."
                    ]);
                }

                // Create stock transaction
                StockTransaction::create([
                    'product_id' => $item['product_id'],
                    'location_id' => $location->id,
                    'type' => 'in',
                    'quantity' => $receivedQty,
                    'reason' => 'Purchase order received' . ($notes ? " - {$notes}" : ''),
                    'reference' => $purchaseOrder->order_number,
                    'user_id' => Auth::id(),
                ]);

                // Update stock levels
                $this->updateStockLevel($item['product_id'], $location->id, $receivedQty);

                // Track received details
                $receivedDetails[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $orderDetail->product->name,
                    'ordered_quantity' => $orderDetail->quantity,
                    'received_quantity' => $receivedQty,
                ];

                $totalReceived += $receivedQty;
            }

            // Determine new status based on received vs ordered quantities
            $newStatus = $this->determineOrderStatusAfterReceiving($purchaseOrder, $receivedItems);
            
            if ($newStatus !== $purchaseOrder->status) {
                $purchaseOrder->update(['status' => $newStatus]);
            }

            // Log the receiving
            Log::info('Purchase order items received', [
                'order_id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'location_id' => $location->id,
                'location_name' => $location->name,
                'received_items' => $receivedDetails,
                'total_received' => $totalReceived,
                'total_ordered' => $totalOrdered,
                'new_status' => $newStatus,
                'user_id' => Auth::id(),
            ]);

            // Fire event
            event('purchase-order.items-received', [$purchaseOrder, $receivedDetails, $location]);

            return [
                'success' => true,
                'message' => "Successfully received {$totalReceived} items to {$location->name}.",
                'received_details' => $receivedDetails,
                'new_status' => $newStatus,
            ];
        });
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
            'locations' => Location::orderBy('name')
                ->get(['id', 'name']),
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
     * Get overdue orders.
     */
    public function getOverdueOrders(int $limit = 10)
    {
        return PurchaseOrder::with(['supplier'])
            ->whereIn('status', ['confirmed'])
            ->where('expected_date', '<', now())
            ->orderBy('expected_date', 'asc')
            ->take($limit)
            ->get();
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, PurchaseOrderFilterDto $filters): void
    {
        // Supplier filter
        if ($filters->hasSupplier()) {
            $query->where('supplier_id', $filters->supplier);
        }

        // Status filter
        if ($filters->hasStatus()) {
            $query->where('status', $filters->status);
        }

        // Date range filter
        if ($filters->hasDateRange()) {
            if ($filters->dateFrom) {
                $query->whereDate('order_date', '>=', $filters->dateFrom);
            }

            if ($filters->dateTo) {
                $query->whereDate('order_date', '<=', $filters->dateTo);
            }
        }

        // Search filter
        if ($filters->hasSearch()) {
            $query->where(function ($q) use ($filters) {
                $q->where('order_number', 'like', "%{$filters->search}%")
                  ->orWhere('notes', 'like', "%{$filters->search}%")
                  ->orWhereHas('supplier', function ($supplierQuery) use ($filters) {
                      $supplierQuery->where('name', 'like', "%{$filters->search}%");
                  });
            });
        }
    }

    /**
     * Apply sorting to the query.
     */
    private function applySorting($query, PurchaseOrderFilterDto $filters): void
    {
        $query->orderBy($filters->sortBy, $filters->sortDirection);
    }

    /**
     * Add order details to purchase order.
     *
     * @param PurchaseOrderDetailDto[] $details
     */
    private function addOrderDetails(PurchaseOrder $purchaseOrder, array $details): void
    {
        $subtotal = 0;

        foreach ($details as $detail) {
            // Validate product exists and is active
            $product = Product::where('id', $detail->productId)
                ->where('is_active', true)
                ->first();
                
            if (!$product) {
                throw ValidationException::withMessages([
                    'product_id' => "Product {$detail->productId} is not available."
                ]);
            }

            $lineTotal = $detail->getLineTotal();
            $subtotal += $lineTotal;

            PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $detail->productId,
                'quantity' => $detail->quantity,
                'unit_price' => $detail->unitPrice,
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
            'confirmed' => ['received', 'partially_received', 'cancelled'],
            'partially_received' => ['received', 'cancelled'],
            'received' => [],
            'cancelled' => [],
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Update stock level for a product at a location.
     */
    private function updateStockLevel(int $productId, int $locationId, int $quantity): void
    {
        $stock = Stock::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();

        if ($stock) {
            // Update existing stock record
            $stock->increment('quantity', $quantity);
        } else {
            // Create new stock record
            Stock::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
            ]);
        }
    }

    /**
     * Determine order status after receiving items.
     */
    private function determineOrderStatusAfterReceiving(PurchaseOrder $purchaseOrder, array $receivedItems): string
    {
        $totalOrdered = $purchaseOrder->details->sum('quantity');
        $totalReceived = collect($receivedItems)->sum('received_quantity');
        
        // Check if all items have been fully received
        $allItemsFullyReceived = true;
        foreach ($purchaseOrder->details as $detail) {
            $receivedForThisProduct = collect($receivedItems)
                ->where('product_id', $detail->product_id)
                ->sum('received_quantity');
                
            if ($receivedForThisProduct < $detail->quantity) {
                $allItemsFullyReceived = false;
                break;
            }
        }

        if ($allItemsFullyReceived) {
            return 'received';
        } elseif ($totalReceived > 0) {
            return 'partially_received';
        } else {
            return $purchaseOrder->status; // No change
        }
    }
}
