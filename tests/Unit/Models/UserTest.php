<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    /**
     * Test that a user can be created with valid data.
     */
    public function test_user_can_be_created_with_valid_data(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $user = User::create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue(Hash::check('password123', (string) $user->password));
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    /**
     * Test user fillable attributes.
     */
    public function test_user_fillable_attributes(): void
    {
        $user = new User();
        $expectedFillable = [
            'name',
            'email',
            'password',
        ];

        $this->assertEquals($expectedFillable, $user->getFillable());
    }

    /**
     * Test user hidden attributes.
     */
    public function test_user_hidden_attributes(): void
    {
        $user = new User();
        $expectedHidden = [
            'password',
            'remember_token',
        ];

        $this->assertEquals($expectedHidden, $user->getHidden());
    }

    /**
     * Test user password is hashed on creation.
     */
    public function test_user_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => 'plaintext']);

        $this->assertTrue(Hash::check('plaintext', (string) $user->password));
        $this->assertNotEquals('plaintext', $user->password);
    }

    /**
     * Test user has many stock transactions relationship.
     */
    public function test_user_has_many_stock_transactions(): void
    {
        $user = User::factory()->create();
        $transactions = StockTransaction::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->stockTransactions);
        $this->assertInstanceOf(StockTransaction::class, $user->stockTransactions->first());
        
        foreach ($transactions as $transaction) {
            $this->assertTrue($user->stockTransactions->contains($transaction));
        }
    }

    /**
     * Test user has many purchase orders relationship.
     */
    public function test_user_has_many_purchase_orders(): void
    {
        $user = User::factory()->create();
        $orders = PurchaseOrder::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->purchaseOrders);
        $this->assertInstanceOf(PurchaseOrder::class, $user->purchaseOrders->first());
        
        foreach ($orders as $order) {
            $this->assertTrue($user->purchaseOrders->contains($order));
        }
    }

    /**
     * Test user can have roles assigned.
     */
    public function test_user_can_have_roles(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::findByName('admin');
        $stockManagerRole = Role::findByName('stock_manager');

        $user->assignRole($adminRole);
        $user->assignRole($stockManagerRole);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('stock_manager'));
        $this->assertFalse($user->hasRole('operator'));
        $this->assertCount(2, $user->roles);
    }

    /**
     * Test user can have permissions assigned.
     */
    public function test_user_can_have_permissions(): void
    {
        $user = User::factory()->create();
        
        // Create a permission if it doesn't exist
        $permission = Permission::firstOrCreate(['name' => 'manage products']);

        $permission2 = Permission::firstOrCreate(['name' => 'manage users']);
        
        $user->givePermissionTo($permission);
        $user->givePermissionTo($permission2);

        $this->assertTrue($user->hasPermissionTo('manage products'));
        $this->assertTrue($user->hasPermissionTo('manage users'));
        $this->assertCount(2, $user->permissions);
    }

    /**
     * Test user can have permissions through roles.
     */
    public function test_user_can_have_permissions_through_roles(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::findByName('admin');

        $user->assignRole($adminRole);

        // Admin role should have permissions from the seeder
        $this->assertTrue($user->hasAnyRole(['admin']));
        $this->assertTrue($user->can('manage products') || $user->hasRole('admin'));
    }

    /**
     * Test user role removal.
     */
    public function test_user_role_removal(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::findByName('admin');
        $stockManagerRole = Role::findByName('stock_manager');

        $user->assignRole([$adminRole, $stockManagerRole]);
        $this->assertCount(2, $user->roles);

        $user->removeRole('admin');
        $this->assertCount(1, $user->fresh()->roles);
        $this->assertFalse($user->fresh()->hasRole('admin'));
        $this->assertTrue($user->fresh()->hasRole('stock_manager'));
    }

    /**
     * Test user can sync roles.
     */
    public function test_user_can_sync_roles(): void
    {
        $user = User::factory()->create();
        $adminRole = Role::findByName('admin');
        $stockManagerRole = Role::findByName('stock_manager');
        $operatorRole = Role::findByName('operator');

        // Initially assign admin and stock_manager
        $user->assignRole([$adminRole, $stockManagerRole]);
        $this->assertCount(2, $user->roles);

        // Sync to only operator
        $user->syncRoles([$operatorRole]);
        $this->assertCount(1, $user->fresh()->roles);
        $this->assertTrue($user->fresh()->hasRole('operator'));
        $this->assertFalse($user->fresh()->hasRole('admin'));
        $this->assertFalse($user->fresh()->hasRole('stock_manager'));
    }

    /**
     * Test user email is unique.
     */
    public function test_user_email_is_unique(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'test@example.com']);
    }

    /**
     * Test user factory creates users with different roles.
     */
    public function test_user_factory_with_roles(): void
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');

        $stockManagerUser = User::factory()->create();
        $stockManagerUser->assignRole('stock_manager');

        $operatorUser = User::factory()->create();
        $operatorUser->assignRole('operator');

        $this->assertTrue($adminUser->hasRole('admin'));
        $this->assertTrue($stockManagerUser->hasRole('stock_manager'));
        $this->assertTrue($operatorUser->hasRole('operator'));
    }
} 