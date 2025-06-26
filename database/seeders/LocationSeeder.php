<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Main Warehouse',
                'description' => 'Primary storage facility',
                'address' => '123 Main Street, Industrial Zone',
                'is_active' => true,
            ],
            [
                'name' => 'Secondary Warehouse',
                'description' => 'Backup storage facility',
                'address' => '456 Industrial Ave, Warehouse District',
                'is_active' => true,
            ],
            [
                'name' => 'Office Storage',
                'description' => 'Office building storage room',
                'address' => '789 Business Blvd, Office Complex',
                'is_active' => true,
            ],
            [
                'name' => 'Damaged Goods Area',
                'description' => 'Storage for damaged or returned items',
                'address' => 'Section D, Main Warehouse',
                'is_active' => true,
            ],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
