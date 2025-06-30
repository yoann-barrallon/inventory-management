<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        $costPrice = $unitPrice * $this->faker->randomFloat(2, 0.5, 0.8); // Cost is 50-80% of unit price

        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{4}'),
            'barcode' => $this->faker->optional()->ean13(),
            'category_id' => Category::factory(),
            'supplier_id' => Supplier::factory(),
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'min_stock_level' => $this->faker->numberBetween(5, 50),
            'is_active' => $this->faker->randomElement([true, true, true, false]), // 75% chance of active
        ];
    }

    public function active(): self
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    public function withCategory(Category $category): self
    {
        return $this->state(['category_id' => $category->id]);
    }

    public function withSupplier(Supplier $supplier): self
    {
        return $this->state(['supplier_id' => $supplier->id]);
    }
} 