<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'supplier']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Filter by supplier
        if ($request->filled('supplier')) {
            $query->where('supplier_id', $request->input('supplier'));
        }

        // Filter by active status
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by low stock
        if ($request->filled('low_stock') && $request->input('low_stock') === 'true') {
            $query->whereHas('stocks', function ($q) {
                $q->havingRaw('SUM(quantity) <= products.min_stock_level');
            });
        }

        // Sorting
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Add stock information
        $products = $query->withSum('stocks', 'quantity')
            ->withSum('stocks', 'reserved_quantity')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'supplier' => $request->input('supplier'),
                'status' => $request->input('status'),
                'low_stock' => $request->input('low_stock'),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Inventory/Products/Create', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        Product::create($data);

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): Response
    {
        $product->load([
            'category',
            'supplier',
            'stocks.location',
            'stockTransactions' => function ($query) {
                $query->with(['location', 'user'])
                    ->orderBy('created_at', 'desc')
                    ->take(20);
            }
        ]);

        return Inertia::render('Inventory/Products/Show', [
            'product' => $product,
            'totalStock' => $product->total_stock,
            'availableStock' => $product->available_stock,
            'isLowStock' => $product->total_stock <= $product->min_stock_level,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('Inventory/Products/Edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        // Check if product has stock
        if ($product->stocks()->sum('quantity') > 0) {
            return redirect()
                ->route('inventory.products.index')
                ->with('error', 'Cannot delete product with existing stock. Please remove all stock first.');
        }

        // Check if product has pending purchase orders
        if ($product->purchaseOrderDetails()->whereHas('purchaseOrder', function ($query) {
            $query->whereIn('status', ['pending', 'confirmed']);
        })->exists()) {
            return redirect()
                ->route('inventory.products.index')
                ->with('error', 'Cannot delete product with pending purchase orders.');
        }

        $product->delete();

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product deleted successfully.');
    }
}
