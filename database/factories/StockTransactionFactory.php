<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    protected $model = StockTransaction::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['in', 'out', 'adjustment', 'transfer']),
            'quantity' => $this->faker->numberBetween(1, 50),
            'reference' => $this->faker->optional()->regexify('[A-Z]{2}[0-9]{6}'),

        ];
    }

    public function inbound(): self
    {
        return $this->state(['type' => 'in']);
    }

    public function outbound(): self
    {
        return $this->state(['type' => 'out']);
    }

    public function adjustment(): self
    {
        return $this->state(['type' => 'adjustment']);
    }

    public function transfer(): self
    {
        return $this->state(['type' => 'transfer']);
    }
} 