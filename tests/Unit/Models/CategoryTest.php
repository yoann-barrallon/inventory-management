<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    /**
     * Test that a category can be created with valid data.
     */
    public function test_category_can_be_created_with_valid_data(): void
    {
        $categoryData = [
            'name' => 'Electronics',
            'description' => 'Electronic devices and components',
            'slug' => 'electronics',
        ];

        $category = Category::create($categoryData);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Electronics', $category->name);
        $this->assertEquals('Electronic devices and components', $category->description);
        $this->assertEquals('electronics', $category->slug);
        $this->assertDatabaseHas('categories', $categoryData);
    }

    /**
     * Test category fillable attributes.
     */
    public function test_category_fillable_attributes(): void
    {
        $category = new Category();
        $expectedFillable = [
            'name',
            'description',
            'slug',
        ];

        $this->assertEquals($expectedFillable, $category->getFillable());
    }

    /**
     * Test category has many products relationship.
     */
    public function test_category_has_many_products(): void
    {
        $category = Category::factory()->create();
        $products = Product::factory()->count(3)->create(['category_id' => $category->id]);

        $this->assertCount(3, $category->products);
        $this->assertInstanceOf(Product::class, $category->products->first());
        
        foreach ($products as $product) {
            $this->assertTrue($category->products->contains($product));
        }
    }

    /**
     * Test category can exist without products.
     */
    public function test_category_can_exist_without_products(): void
    {
        $category = Category::factory()->create();

        $this->assertCount(0, $category->products);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $category->products);
    }

    /**
     * Test category updates work correctly.
     */
    public function test_category_can_be_updated(): void
    {
        $category = Category::factory()->create([
            'name' => 'Old Name',
            'description' => 'Old Description',
            'slug' => 'old-name',
        ]);

        $category->update([
            'name' => 'New Name',
            'description' => 'New Description',
            'slug' => 'new-name',
        ]);

        $this->assertEquals('New Name', $category->fresh()->name);
        $this->assertEquals('New Description', $category->fresh()->description);
        $this->assertEquals('new-name', $category->fresh()->slug);
    }
} 