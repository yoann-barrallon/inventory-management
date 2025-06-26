<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Location;
use App\Models\Stock;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $locations = Location::where('is_active', true)->get();

        foreach ($products as $product) {
            foreach ($locations as $location) {
                // Create random stock for each product-location combination
                $quantity = match ($product->sku) {
                    'ELEC-001' => $location->name === 'Main Warehouse' ? 50 : 10,
                    'ELEC-002' => $location->name === 'Main Warehouse' ? 100 : 25,
                    'OFF-001' => $location->name === 'Main Warehouse' ? 200 : 50,
                    'OFF-002' => $location->name === 'Main Warehouse' ? 500 : 100,
                    'HW-001' => $location->name === 'Main Warehouse' ? 15 : 2,
                    'HW-002' => $location->name === 'Main Warehouse' ? 30 : 5,
                    'SW-001' => $location->name === 'Office Storage' ? 10 : 0,
                    'FUR-001' => $location->name === 'Main Warehouse' ? 8 : 1,
                    'FUR-002' => $location->name === 'Main Warehouse' ? 5 : 0,
                    default => rand(0, 20),
                };

                if ($quantity > 0) {
                    Stock::create([
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                        'quantity' => $quantity,
                        'reserved_quantity' => rand(0, min(5, $quantity)),
                    ]);
                }
            }
        }
    }
}
