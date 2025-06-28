<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $categories = $this->categoryService->getPaginatedCategories($request);

        return Inertia::render('Inventory/Categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search'),
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
        return Inertia::render('Inventory/Categories/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->categoryService->createCategory($request->validated());

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): Response
    {
        $category = $this->categoryService->getCategoryWithRelations($category);

        return Inertia::render('Inventory/Categories/Show', [
            'category' => $category,
            'productsCount' => $category->products()->count(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category): Response
    {
        return Inertia::render('Inventory/Categories/Edit', [
            'category' => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->updateCategory($category, $request->validated());

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if (!$this->categoryService->deleteCategory($category)) {
            return redirect()
                ->route('inventory.categories.index')
                ->with('error', 'Cannot delete category that has products associated with it.');
        }

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
