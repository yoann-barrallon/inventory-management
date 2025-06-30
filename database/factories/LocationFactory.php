<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' ' . $this->faker->randomElement(['Warehouse', 'Storage', 'Office']),
            'description' => $this->faker->optional()->paragraph(),
            'address' => $this->faker->optional()->address(),
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
} 