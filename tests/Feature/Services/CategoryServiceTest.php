<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DTOs\GenericFilterDto;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoryService();
    }

    public function test_get_paginated_categories_without_filters()
    {
        Category::factory()->count(15)->create();
        $filters = GenericFilterDto::fromArray([
            'perPage' => 10,
            'sortBy' => 'name',
            'sortDirection' => 'asc',
            'search' => null,
        ]);

        $result = $this->service->getPaginatedCategories($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(10, $result->items());
        $this->assertEquals(15, $result->total());
    }

    public function test_get_paginated_categories_with_search_filter()
    {
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Furniture']);
        $filters = GenericFilterDto::fromArray([
            'perPage' => 10,
            'sortBy' => 'name',
            'sortDirection' => 'asc',
            'search' => 'Electro',
        ]);

        $result = $this->service->getPaginatedCategories($filters);

        $this->assertCount(1, $result->items());
        $this->assertEquals('Electronics', $result->items()[0]->name);
    }

    public function test_create_category()
    {
        $data = [
            'name' => 'Test Category',
            'description' => 'A test category',
            'slug' => 'test-category',
        ];
        $category = $this->service->createCategory($data);

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
        $this->assertInstanceOf(Category::class, $category);
    }

    public function test_update_category()
    {
        $category = Category::factory()->create(['name' => 'Old Name']);
        $result = $this->service->updateCategory($category, ['name' => 'New Name']);

        $this->assertTrue($result);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_can_delete_category_returns_true_when_no_products()
    {
        $category = Category::factory()->create();
        $this->assertTrue($this->service->canDeleteCategory($category));
    }

    public function test_can_delete_category_returns_false_when_has_products()
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);
        $this->assertFalse($this->service->canDeleteCategory($category));
    }

    public function test_delete_category_success()
    {
        $category = Category::factory()->create();
        $result = $this->service->deleteCategory($category);
        $this->assertTrue($result);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_delete_category_fails_when_has_products()
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);
        $result = $this->service->deleteCategory($category);
        $this->assertFalse($result);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_get_category_with_relations_loads_products_and_suppliers()
    {
        $category = Category::factory()->create();
        $products = Product::factory()->count(3)->create(['category_id' => $category->id]);
        $category->refresh();
        $result = $this->service->getCategoryWithRelations($category);
        $this->assertTrue($result->relationLoaded('products'));
        $this->assertLessThanOrEqual(10, $result->products->count());
    }

    public function test_get_categories_for_select_returns_id_and_name_ordered()
    {
        Category::factory()->create(['name' => 'B']);
        Category::factory()->create(['name' => 'A']);
        $result = $this->service->getCategoriesForSelect();
        $this->assertEquals(['A', 'B'], $result->pluck('name')->toArray());
        $this->assertTrue($result->every(fn($cat) => isset($cat->id) && isset($cat->name)));
    }
} 