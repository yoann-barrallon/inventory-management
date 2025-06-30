<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderDetail>
 */
class PurchaseOrderDetailFactory extends Factory
{
    protected $model = PurchaseOrderDetail::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 50);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        $receivedQuantity = $this->faker->numberBetween(0, $quantity);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'received_quantity' => $receivedQuantity,
        ];
    }

    public function fullyReceived(): self
    {
        return $this->state(function (array $attributes) {
            return ['received_quantity' => $attributes['quantity']];
        });
    }

    public function notReceived(): self
    {
        return $this->state(['received_quantity' => 0]);
    }
} 