<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $products = $this->productService->getPaginatedProducts($request);
        $formData = $this->productService->getFormData();

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'categories' => $formData['categories'],
            'suppliers' => $formData['suppliers'],
            'filters' => [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'supplier' => $request->input('supplier'),
                'status' => $request->input('status'),
                'low_stock' => $request->input('low_stock'),
                'sort' => $request->input('sort', 'name'),
                'direction' => $request->input('direction', 'asc'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $formData = $this->productService->getFormData();

        return Inertia::render('Inventory/Products/Create', $formData);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $this->productService->createProduct($request->validated());

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): Response
    {
        $product = $this->productService->getProductWithRelations($product);
        $analysis = $this->productService->getProductAnalysis($product);

        return Inertia::render('Inventory/Products/Show', [
            'product' => $product,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product): Response
    {
        $formData = $this->productService->getFormData();

        return Inertia::render('Inventory/Products/Edit', [
            'product' => $product,
            ...$formData,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->productService->updateProduct($product, $request->validated());

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $result = $this->productService->deleteProduct($product);

        $redirectResponse = redirect()->route('inventory.products.index');

        if ($result['success']) {
            return $redirectResponse->with('success', $result['message']);
        }

        return $redirectResponse->with('error', $result['message']);
    }
}
