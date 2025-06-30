<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\GenericFilterDto;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    /**
     * Get paginated categories with filters.
     */
    public function getPaginatedCategories(GenericFilterDto $filters): LengthAwarePaginator
    {
        $query = Category::query();

        // Apply search filter
        if ($filters->hasSearch()) {
            $this->applySearchFilter($query, $filters->search);
        }

        // Apply sorting
        $query->orderBy($filters->sortBy, $filters->sortDirection);

        return $query->paginate($filters->perPage);
    }

    /**
     * Create a new category.
     */
    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update an existing category.
     */
    public function updateCategory(Category $category, array $data): bool
    {
        return $category->update($data);
    }

    /**
     * Check if category can be deleted.
     */
    public function canDeleteCategory(Category $category): bool
    {
        return $category->products()->count() === 0;
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(Category $category): bool
    {
        if (!$this->canDeleteCategory($category)) {
            return false;
        }

        return $category->delete();
    }

    /**
     * Get category with related data for showing.
     */
    public function getCategoryWithRelations(Category $category): Category
    {
        return $category->load(['products' => function ($query) {
            $query->with('supplier')
                  ->orderBy('name')
                  ->take(10);
        }]);
    }

    /**
     * Get categories for dropdown/select options.
     */
    public function getCategoriesForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Apply search filter to query.
     */
    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
