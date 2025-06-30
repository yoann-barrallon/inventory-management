<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(0, 100);
        $reservedQuantity = $this->faker->numberBetween(0, min($quantity, 20));

        return [
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
        ];
    }

    public function withProduct(Product $product): self
    {
        return $this->state(['product_id' => $product->id]);
    }

    public function withLocation(Location $location): self
    {
        return $this->state(['location_id' => $location->id]);
    }

    public function withQuantity(int $quantity): self
    {
        return $this->state(['quantity' => $quantity]);
    }

    public function withReservedQuantity(int $reserved): self
    {
        return $this->state(['reserved_quantity' => $reserved]);
    }
} 