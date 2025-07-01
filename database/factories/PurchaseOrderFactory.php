<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $orderDate = $this->faker->dateTimeBetween('-30 days', 'now');
        $expectedDate = $this->faker->dateTimeBetween($orderDate, '+30 days');

        return [
            'order_number' => $this->faker->unique()->bothify('PO##########'),
            'supplier_id' => Supplier::factory(),
            'user_id' => User::factory(),
            'order_date' => $orderDate,
            'expected_date' => $expectedDate,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'received', 'cancelled']),
            'total_amount' => $this->faker->randomFloat(2, 100, 5000),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => 'pending']);
    }

    public function confirmed(): self
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function received(): self
    {
        return $this->state(['status' => 'received']);
    }

    public function cancelled(): self
    {
        return $this->state(['status' => 'cancelled']);
    }
} 