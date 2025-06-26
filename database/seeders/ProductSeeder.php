<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $electronics = Category::where('slug', 'electronics')->first();
        $officeSupplies = Category::where('slug', 'office-supplies')->first();
        $hardware = Category::where('slug', 'hardware')->first();
        $software = Category::where('slug', 'software')->first();
        $furniture = Category::where('slug', 'furniture')->first();

        $techWorld = Supplier::where('name', 'TechWorld Electronics')->first();
        $officePlus = Supplier::where('name', 'Office Plus Supplies')->first();
        $hardwareSolutions = Supplier::where('name', 'Hardware Solutions Inc')->first();
        $digitalSoftware = Supplier::where('name', 'Digital Software Corp')->first();
        $premiumFurniture = Supplier::where('name', 'Premium Furniture Co')->first();

        $products = [
            // Electronics
            [
                'name' => 'Wireless Mouse',
                'description' => 'Ergonomic wireless optical mouse',
                'sku' => 'ELEC-001',
                'barcode' => '1234567890123',
                'category_id' => $electronics->id,
                'supplier_id' => $techWorld->id,
                'unit_price' => 29.99,
                'cost_price' => 15.50,
                'min_stock_level' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'USB-C Cable',
                'description' => '2-meter USB-C charging cable',
                'sku' => 'ELEC-002',
                'barcode' => '1234567890124',
                'category_id' => $electronics->id,
                'supplier_id' => $techWorld->id,
                'unit_price' => 19.99,
                'cost_price' => 8.75,
                'min_stock_level' => 25,
                'is_active' => true,
            ],

            // Office Supplies
            [
                'name' => 'A4 Paper Ream',
                'description' => '500 sheets of premium A4 paper',
                'sku' => 'OFF-001',
                'barcode' => '1234567890125',
                'category_id' => $officeSupplies->id,
                'supplier_id' => $officePlus->id,
                'unit_price' => 8.99,
                'cost_price' => 4.50,
                'min_stock_level' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Blue Ink Pen',
                'description' => 'Ballpoint pen with blue ink',
                'sku' => 'OFF-002',
                'barcode' => '1234567890126',
                'category_id' => $officeSupplies->id,
                'supplier_id' => $officePlus->id,
                'unit_price' => 1.99,
                'cost_price' => 0.75,
                'min_stock_level' => 100,
                'is_active' => true,
            ],

            // Hardware
            [
                'name' => 'Cordless Drill',
                'description' => '18V cordless drill with battery',
                'sku' => 'HW-001',
                'barcode' => '1234567890127',
                'category_id' => $hardware->id,
                'supplier_id' => $hardwareSolutions->id,
                'unit_price' => 149.99,
                'cost_price' => 89.99,
                'min_stock_level' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Screwdriver Set',
                'description' => '12-piece precision screwdriver set',
                'sku' => 'HW-002',
                'barcode' => '1234567890128',
                'category_id' => $hardware->id,
                'supplier_id' => $hardwareSolutions->id,
                'unit_price' => 24.99,
                'cost_price' => 12.50,
                'min_stock_level' => 15,
                'is_active' => true,
            ],

            // Software
            [
                'name' => 'Office Suite License',
                'description' => 'Annual license for office productivity suite',
                'sku' => 'SW-001',
                'barcode' => '1234567890129',
                'category_id' => $software->id,
                'supplier_id' => $digitalSoftware->id,
                'unit_price' => 299.99,
                'cost_price' => 180.00,
                'min_stock_level' => 2,
                'is_active' => true,
            ],

            // Furniture
            [
                'name' => 'Office Chair',
                'description' => 'Ergonomic office chair with lumbar support',
                'sku' => 'FUR-001',
                'barcode' => '1234567890130',
                'category_id' => $furniture->id,
                'supplier_id' => $premiumFurniture->id,
                'unit_price' => 299.99,
                'cost_price' => 175.00,
                'min_stock_level' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Standing Desk',
                'description' => 'Adjustable height standing desk',
                'sku' => 'FUR-002',
                'barcode' => '1234567890131',
                'category_id' => $furniture->id,
                'supplier_id' => $premiumFurniture->id,
                'unit_price' => 599.99,
                'cost_price' => 350.00,
                'min_stock_level' => 2,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
