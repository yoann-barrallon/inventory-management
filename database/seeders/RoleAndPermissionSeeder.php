<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Product permissions
            'view products',
            'create products',
            'edit products',
            'delete products',

            // Category permissions
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',

            // Stock permissions
            'view stock',
            'manage stock',
            'view stock transactions',
            'create stock transactions',

            // Supplier permissions
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'delete suppliers',

            // Purchase order permissions
            'view purchase orders',
            'create purchase orders',
            'edit purchase orders',
            'delete purchase orders',
            'approve purchase orders',

            // Location permissions
            'view locations',
            'create locations',
            'edit locations',
            'delete locations',

            // User management permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage roles',

            // Dashboard and reports
            'view dashboard',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Admin role - has all permissions
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Stock Manager role
        $stockManagerRole = Role::create(['name' => 'stock_manager']);
        $stockManagerRole->givePermissionTo([
            'view products',
            'create products',
            'edit products',
            'view categories',
            'create categories',
            'edit categories',
            'view stock',
            'manage stock',
            'view stock transactions',
            'create stock transactions',
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'view purchase orders',
            'create purchase orders',
            'edit purchase orders',
            'view locations',
            'view dashboard',
            'view reports'
        ]);

        // Operator role
        $operatorRole = Role::create(['name' => 'operator']);
        $operatorRole->givePermissionTo([
            'view products',
            'view categories',
            'view stock',
            'view stock transactions',
            'create stock transactions',
            'view suppliers',
            'view purchase orders',
            'view locations',
            'view dashboard'
        ]);
    }
}
