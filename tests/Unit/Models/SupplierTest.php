<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    /**
     * Test that a supplier can be created with valid data.
     */
    public function test_supplier_can_be_created_with_valid_data(): void
    {
        $supplierData = [
            'name' => 'ABC Electronics',
            'contact_person' => 'John Smith',
            'email' => 'john@abcelectronics.com',
            'phone' => '+1-555-123-4567',
            'address' => '123 Main St, City, State 12345',
            'is_active' => true,
        ];

        $supplier = Supplier::create($supplierData);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertEquals('ABC Electronics', $supplier->name);
        $this->assertEquals('John Smith', $supplier->contact_person);
        $this->assertEquals('john@abcelectronics.com', $supplier->email);
        $this->assertEquals('+1-555-123-4567', $supplier->phone);
        $this->assertTrue($supplier->is_active);
        $this->assertDatabaseHas('suppliers', $supplierData);
    }

    /**
     * Test supplier fillable attributes.
     */
    public function test_supplier_fillable_attributes(): void
    {
        $supplier = new Supplier();
        $expectedFillable = [
            'name',
            'contact_person',
            'email',
            'phone',
            'address',
            'is_active',
        ];

        $this->assertEquals($expectedFillable, $supplier->getFillable());
    }

    /**
     * Test supplier casts.
     */
    public function test_supplier_casts(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => 1]);

        $this->assertTrue($supplier->is_active); // Should cast to boolean
    }

    /**
     * Test supplier has many products relationship.
     */
    public function test_supplier_has_many_products(): void
    {
        $supplier = Supplier::factory()->create();
        $products = Product::factory()->count(3)->create(['supplier_id' => $supplier->id]);

        $this->assertCount(3, $supplier->products);
        $this->assertInstanceOf(Product::class, $supplier->products->first());
        
        foreach ($products as $product) {
            $this->assertTrue($supplier->products->contains($product));
        }
    }

    /**
     * Test supplier has many purchase orders relationship.
     */
    public function test_supplier_has_many_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create();
        $orders = PurchaseOrder::factory()->count(2)->create(['supplier_id' => $supplier->id]);

        $this->assertCount(2, $supplier->purchaseOrders);
        $this->assertInstanceOf(PurchaseOrder::class, $supplier->purchaseOrders->first());
        
        foreach ($orders as $order) {
            $this->assertTrue($supplier->purchaseOrders->contains($order));
        }
    }

    /**
     * Test supplier can be inactive.
     */
    public function test_supplier_can_be_inactive(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => false]);

        $this->assertFalse($supplier->is_active);
    }

    /**
     * Test supplier updates work correctly.
     */
    public function test_supplier_can_be_updated(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        $supplier->update([
            'name' => 'New Name',
            'email' => 'new@example.com',
            'is_active' => false,
        ]);

        $this->assertEquals('New Name', $supplier->fresh()->name);
        $this->assertEquals('new@example.com', $supplier->fresh()->email);
        $this->assertFalse($supplier->fresh()->is_active);
    }
} 