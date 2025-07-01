<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\CreatePurchaseOrderDto;
use App\DTOs\PurchaseOrderFilterDto;
use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Requests\PurchaseOrderStatusRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): Response
    {
        $filters = PurchaseOrderFilterDto::fromArray($request->all());
        $purchaseOrders = $this->purchaseOrderService->getPaginatedPurchaseOrders($filters);
        $formData = $this->purchaseOrderService->getFormData();

        return Inertia::render('Inventory/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers' => $formData['suppliers'],
            'filters' => $filters->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new purchase order.
     */
    public function create(): Response
    {
        $formData = $this->purchaseOrderService->getFormData();

        return Inertia::render('Inventory/PurchaseOrders/Create', $formData);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $dto = CreatePurchaseOrderDto::fromArray($request->validated());
        $result = $this->purchaseOrderService->createPurchaseOrder($dto);

        if ($result['success']) {
            return redirect()
                ->route('inventory.purchase-orders.show', $result['purchase_order'])
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('inventory.purchase-orders.index')
            ->with('error', $result['message']);
    }

    /**
     * Display the specified purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder = $this->purchaseOrderService->getPurchaseOrderWithRelations($purchaseOrder);
        $statistics = $this->purchaseOrderService->getPurchaseOrderStatistics($purchaseOrder);

        return Inertia::render('Inventory/PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Show the form for editing the specified purchase order.
     */
    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        $formData = $this->purchaseOrderService->getFormData();

        return Inertia::render('Inventory/PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder->load(['details.product']),
            ...$formData,
        ]);
    }

    /**
     * Update the specified purchase order.
     */
    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $result = $this->purchaseOrderService->updatePurchaseOrder($purchaseOrder, $request->validated());

        $redirectResponse = redirect()->route('inventory.purchase-orders.show', $purchaseOrder);

        if ($result['success']) {
            return $redirectResponse->with('success', $result['message']);
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Change the status of a purchase order.
     */
    public function changeStatus(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $result = $this->purchaseOrderService->changeOrderStatus(
            $purchaseOrder,
            $request->input('status'),
            $request->input('notes')
        );

        $redirectResponse = redirect()->route('inventory.purchase-orders.show', $purchaseOrder);

        if ($result['success']) {
            return $redirectResponse->with('success', $result['message']);
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Confirm a purchase order.
     */
    public function confirm(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $result = $this->purchaseOrderService->changeOrderStatus($purchaseOrder, 'confirmed');

        $redirectResponse = redirect()->route('inventory.purchase-orders.show', $purchaseOrder);

        if ($result['success']) {
            return $redirectResponse->with('success', 'Purchase order confirmed successfully.');
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Mark purchase order as received.
     */
    public function receive(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $result = $this->purchaseOrderService->changeOrderStatus(
            $purchaseOrder,
            'received',
            $request->input('notes')
        );

        $redirectResponse = redirect()->route('inventory.purchase-orders.show', $purchaseOrder);

        if ($result['success']) {
            return $redirectResponse->with('success', 'Purchase order marked as received.');
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(PurchaseOrderStatusRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $result = $this->purchaseOrderService->changeOrderStatus(
            $purchaseOrder,
            'cancelled',
            $request->input('notes', 'Order cancelled by user.')
        );

        $redirectResponse = redirect()->route('inventory.purchase-orders.show', $purchaseOrder);

        if ($result['success']) {
            return $redirectResponse->with('success', 'Purchase order cancelled.');
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Duplicate a purchase order.
     */
    public function duplicate(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $originalOrder = $this->purchaseOrderService->getPurchaseOrderWithRelations($purchaseOrder);

        $duplicateData = [
            'supplier_id' => $originalOrder->supplier_id,
            'order_date' => now()->format('Y-m-d'),
            'expected_date' => $originalOrder->expected_date,
            'notes' => 'Duplicated from order #' . $originalOrder->order_number,
            'tax_rate' => $originalOrder->tax_rate,
            'details' => $originalOrder->details->map(function ($detail) {
                return [
                    'product_id' => $detail->product_id,
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                ];
            })->toArray(),
        ];

        $dto = CreatePurchaseOrderDto::fromArray($duplicateData);
        $result = $this->purchaseOrderService->createPurchaseOrder($dto);

        if ($result['success']) {
            return redirect()
                ->route('inventory.purchase-orders.show', $result['purchase_order'])
                ->with('success', 'Purchase order duplicated successfully.');
        }

        return redirect()
            ->route('inventory.purchase-orders.index')
            ->with('error', 'Failed to duplicate purchase order.');
    }
}
