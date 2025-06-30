<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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
    public function getPaginatedTransactions(Request $request): LengthAwarePaginator
    {
        $query = StockTransaction::with(['product', 'location', 'user']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate(15)->withQueryString();
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
    private function applyFilters($query, Request $request): void
    {
        // Product filter
        if ($request->filled('product')) {
            $query->where('product_id', $request->input('product'));
        }

        // Location filter
        if ($request->filled('location')) {
            $query->where('location_id', $request->input('location'));
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $search = $request->input('search');
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($productQuery) use ($search) {
                      $productQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }
    }

    /**
     * Apply sorting to the query.
     */
    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
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
