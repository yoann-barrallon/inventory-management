<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles and permissions first
        $this->call(RoleAndPermissionSeeder::class);

        // Create base data
        $this->call([
            CategorySeeder::class,
            LocationSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            StockSeeder::class,
        ]);

        // Create test user with admin role
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@inventory.test',
        ]);
        $adminUser->assignRole('admin');

        // Create test user with stock manager role
        $stockManagerUser = User::factory()->create([
            'name' => 'Stock Manager',
            'email' => 'manager@inventory.test',
        ]);
        $stockManagerUser->assignRole('stock_manager');

        // Create test user with operator role
        $operatorUser = User::factory()->create([
            'name' => 'Operator',
            'email' => 'operator@inventory.test',
        ]);
        $operatorUser->assignRole('operator');
    }
}
