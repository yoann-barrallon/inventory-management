<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\CreateStockTransactionDto;
use App\DTOs\StockTransactionFilterDto;
use App\Http\Requests\StockTransactionRequest;
use App\Http\Requests\StockTransferRequest;
use App\Http\Requests\StockLevelsRequest;
use App\Http\Requests\ProductHistoryRequest;
use App\Http\Requests\LocationHistoryRequest;
use App\Services\StockTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransactionController extends Controller
{
    public function __construct(
        private readonly StockTransactionService $stockTransactionService
    ) {}

    /**
     * Display a listing of stock transactions.
     */
    public function index(Request $request): Response
    {
        $filters = StockTransactionFilterDto::fromArray($request->all());
        $transactions = $this->stockTransactionService->getPaginatedTransactions($filters);
        $formData = $this->stockTransactionService->getFormData();

        return Inertia::render('Inventory/StockTransactions/Index', [
            'transactions' => $transactions,
            'products' => $formData['products'],
            'locations' => $formData['locations'],
            'filters' => $filters->toArray(),
        ]);
    }

    /**
     * Show the form for creating a stock-in transaction.
     */
    public function createStockIn(): Response
    {
        $formData = $this->stockTransactionService->getFormData();

        return Inertia::render('Inventory/StockTransactions/CreateStockIn', $formData);
    }

    /**
     * Show the form for creating a stock-out transaction.
     */
    public function createStockOut(): Response
    {
        $formData = $this->stockTransactionService->getFormData();

        return Inertia::render('Inventory/StockTransactions/CreateStockOut', $formData);
    }

    /**
     * Show the form for creating a stock adjustment.
     */
    public function createAdjustment(): Response
    {
        $formData = $this->stockTransactionService->getFormData();

        return Inertia::render('Inventory/StockTransactions/CreateAdjustment', $formData);
    }

    /**
     * Show the form for transferring stock between locations.
     */
    public function createTransfer(): Response
    {
        $formData = $this->stockTransactionService->getFormData();

        return Inertia::render('Inventory/StockTransactions/CreateTransfer', $formData);
    }

    /**
     * Process a stock transaction.
     */
    public function store(StockTransactionRequest $request): RedirectResponse
    {
        $dto = CreateStockTransactionDto::fromArray($request->validated());
        $result = $this->stockTransactionService->processTransaction($dto);

        $redirectResponse = redirect()->route('inventory.stock-transactions.index');

        if ($result['success']) {
            return $redirectResponse->with('success', $result['message']);
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Process a stock transfer between locations.
     */
    public function transfer(StockTransferRequest $request): RedirectResponse
    {
        $result = $this->stockTransactionService->transferStock($request->validated());

        $redirectResponse = redirect()->route('inventory.stock-transactions.index');

        if ($result['success']) {
            return $redirectResponse->with('success', $result['message']);
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Get stock levels for a product at a location (AJAX endpoint).
     */
    public function getStockLevels(StockLevelsRequest $request): JsonResponse
    {
        $stockLevels = $this->stockTransactionService->getStockLevels(
            $request->integer('product_id'),
            $request->integer('location_id')
        );

        return response()->json($stockLevels);
    }

    /**
     * Get transaction history for a product (AJAX endpoint).
     */
    public function getProductHistory(ProductHistoryRequest $request): JsonResponse
    {
        $product = \App\Models\Product::findOrFail($request->integer('product_id'));
        $limit = $request->integer('limit', 20);

        $history = $this->stockTransactionService->getProductTransactionHistory($product, $limit);

        return response()->json($history);
    }

    /**
     * Get transaction history for a location (AJAX endpoint).
     */
    public function getLocationHistory(LocationHistoryRequest $request): JsonResponse
    {
        $location = \App\Models\Location::findOrFail($request->integer('location_id'));
        $limit = $request->integer('limit', 20);

        $history = $this->stockTransactionService->getLocationTransactionHistory($location, $limit);

        return response()->json($history);
    }
}
