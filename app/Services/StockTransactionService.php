<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\StockTransactionFilterDto;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransactionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get paginated stock transactions with filters.
     */
    public function getPaginatedTransactions(StockTransactionFilterDto $filters): LengthAwarePaginator
    {
        $query = StockTransaction::with(['product', 'location', 'user']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($filters->perPage);
    }

    /**
     * Process a stock transaction (in, out, or adjustment).
     */
    public function processTransaction(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Validate the transaction
            $validationResult = $this->validateTransaction($data);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                ];
            }

            // Get or create stock record
            $stock = $this->getOrCreateStock($data['product_id'], $data['location_id']);
            
            // Calculate new quantities
            $newQuantity = $this->calculateNewQuantity($stock, $data['type'], $data['quantity']);
            
            if ($newQuantity < 0) {
                return [
                    'success' => false,
                    'message' => 'Insufficient stock. Available: ' . $stock->quantity,
                ];
            }

            // Create the transaction record
            $transaction = StockTransaction::create([
                'product_id' => $data['product_id'],
                'location_id' => $data['location_id'],
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => Auth::id(),
            ]);

            // Update stock quantity
            $stock->update(['quantity' => $newQuantity]);

            return [
                'success' => true,
                'message' => 'Stock transaction processed successfully.',
                'transaction' => $transaction->load(['product', 'location']),
            ];
        });
    }

    /**
     * Transfer stock between locations.
     */
    public function transferStock(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $fromLocationId = $data['from_location_id'];
            $toLocationId = $data['to_location_id'];
            $productId = $data['product_id'];
            $quantity = $data['quantity'];

            // Get source stock
            $fromStock = Stock::where('product_id', $productId)
                ->where('location_id', $fromLocationId)
                ->first();

            if (!$fromStock || $fromStock->quantity < $quantity) {
                return [
                    'success' => false,
                    'message' => 'Insufficient stock at source location.',
                ];
            }

            // Process outbound transaction
            $outResult = $this->processTransaction([
                'product_id' => $productId,
                'location_id' => $fromLocationId,
                'type' => 'out',
                'quantity' => $quantity,
                'reference' => $data['reference'] ?? 'Transfer',
                'notes' => "Transfer to " . Location::find($toLocationId)->name,
            ]);

            if (!$outResult['success']) {
                return $outResult;
            }

            // Process inbound transaction
            $inResult = $this->processTransaction([
                'product_id' => $productId,
                'location_id' => $toLocationId,
                'type' => 'in',
                'quantity' => $quantity,
                'reference' => $data['reference'] ?? 'Transfer',
                'notes' => "Transfer from " . Location::find($fromLocationId)->name,
            ]);

            if (!$inResult['success']) {
                // Rollback would happen automatically due to DB transaction
                return $inResult;
            }

            return [
                'success' => true,
                'message' => 'Stock transferred successfully.',
                'transactions' => [
                    'out' => $outResult['transaction'],
                    'in' => $inResult['transaction'],
                ],
            ];
        });
    }

    /**
     * Get transaction history for a product.
     */
    public function getProductTransactionHistory(Product $product, int $limit = 20)
    {
        return StockTransaction::with(['location', 'user'])
            ->where('product_id', $product->id)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get transaction history for a location.
     */
    public function getLocationTransactionHistory(Location $location, int $limit = 20)
    {
        return StockTransaction::with(['product', 'user'])
            ->where('location_id', $location->id)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get form data for transaction forms.
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
        ];
    }

    /**
     * Get stock levels for transfer validation.
     */
    public function getStockLevels(int $productId, int $locationId): array
    {
        $stock = Stock::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->first();

        return [
            'quantity' => $stock ? $stock->quantity : 0,
            'reserved_quantity' => $stock ? $stock->reserved_quantity : 0,
            'available_quantity' => $stock ? ($stock->quantity - $stock->reserved_quantity) : 0,
        ];
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, StockTransactionFilterDto $filters): void
    {
        // Product filter
        if ($filters->hasProduct()) {
            $query->where('product_id', $filters->product);
        }

        // Location filter
        if ($filters->hasLocation()) {
            $query->where('location_id', $filters->location);
        }

        // User filter
        if ($filters->hasUser()) {
            $query->where('user_id', $filters->user);
        }

        // Type filter
        if ($filters->hasType()) {
            $query->where('type', $filters->type);
        }

        // Date range filter
        if ($filters->hasDateRange()) {
            if ($filters->dateFrom) {
                $query->whereDate('created_at', '>=', $filters->dateFrom);
            }

            if ($filters->dateTo) {
                $query->whereDate('created_at', '<=', $filters->dateTo);
            }
        }

        // Search filter
        if ($filters->hasSearch()) {
            $query->where(function ($q) use ($filters) {
                $q->where('reference', 'like', "%{$filters->search}%")
                  ->orWhere('notes', 'like', "%{$filters->search}%")
                  ->orWhereHas('product', function ($productQuery) use ($filters) {
                      $productQuery->where('name', 'like', "%{$filters->search}%")
                                  ->orWhere('sku', 'like', "%{$filters->search}%");
                  });
            });
        }
    }

    /**
     * Apply sorting to the query.
     */
    private function applySorting($query, StockTransactionFilterDto $filters): void
    {
        $query->orderBy($filters->sortBy, $filters->sortDirection);
    }

    /**
     * Validate transaction data.
     */
    private function validateTransaction(array $data): array
    {
        if ($data['quantity'] <= 0) {
            return [
                'valid' => false,
                'message' => 'Quantity must be greater than zero.',
            ];
        }

        if (!Product::find($data['product_id'])) {
            return [
                'valid' => false,
                'message' => 'Product not found.',
            ];
        }

        if (!Location::find($data['location_id'])) {
            return [
                'valid' => false,
                'message' => 'Location not found.',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get or create stock record for product and location.
     */
    private function getOrCreateStock(int $productId, int $locationId): Stock
    {
        return Stock::firstOrCreate(
            [
                'product_id' => $productId,
                'location_id' => $locationId,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]
        );
    }

    /**
     * Calculate new quantity based on transaction type.
     */
    private function calculateNewQuantity(Stock $stock, string $type, int $quantity): int
    {
        return match ($type) {
            'in' => $stock->quantity + $quantity,
            'out' => $stock->quantity - $quantity,
            'adjustment' => $quantity, // For adjustments, quantity is the new absolute value
            default => $stock->quantity,
        };
    }
}
