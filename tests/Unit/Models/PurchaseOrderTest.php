<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    /**
     * Test that a purchase order can be created with valid data.
     */
    public function test_purchase_order_can_be_created_with_valid_data(): void
    {
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create();

        $orderData = [
            'order_number' => 'PO20240101001',
            'supplier_id' => $supplier->id,
            'order_date' => '2024-01-01',
            'expected_date' => '2024-01-15',
            'status' => 'pending',
            'total_amount' => 1500.00,
            'user_id' => $user->id,
            'notes' => 'Test order notes',
        ];

        $purchaseOrder = PurchaseOrder::create($orderData);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals('PO20240101001', $purchaseOrder->order_number);
        $this->assertEquals('pending', $purchaseOrder->status);
        $this->assertEquals(1500.00, $purchaseOrder->total_amount);
        $this->assertDatabaseHas('purchase_orders', $orderData);
    }

    /**
     * Test purchase order fillable attributes.
     */
    public function test_purchase_order_fillable_attributes(): void
    {
        $purchaseOrder = new PurchaseOrder();
        $expectedFillable = [
            'order_number',
            'supplier_id',
            'order_date',
            'expected_date',
            'status',
            'total_amount',
            'user_id',
            'notes',
        ];

        $this->assertEquals($expectedFillable, $purchaseOrder->getFillable());
    }

    /**
     * Test purchase order casts.
     */
    public function test_purchase_order_casts(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'order_date' => '2024-01-01',
            'expected_date' => '2024-01-15',
            'total_amount' => 1234.567,
        ]);

        $this->assertInstanceOf(Carbon::class, $purchaseOrder->order_date);
        $this->assertInstanceOf(Carbon::class, $purchaseOrder->expected_date);
        $this->assertEquals(1234.57, $purchaseOrder->total_amount); // Should cast to 2 decimal places
    }

    /**
     * Test purchase order belongs to supplier relationship.
     */
    public function test_purchase_order_belongs_to_supplier(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
        $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

        $this->assertInstanceOf(Supplier::class, $purchaseOrder->supplier);
        $this->assertEquals('Test Supplier', $purchaseOrder->supplier->name);
        $this->assertEquals($supplier->id, $purchaseOrder->supplier->id);
    }

    /**
     * Test purchase order belongs to user relationship.
     */
    public function test_purchase_order_belongs_to_user(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $purchaseOrder = PurchaseOrder::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $purchaseOrder->user);
        $this->assertEquals('Test User', $purchaseOrder->user->name);
        $this->assertEquals($user->id, $purchaseOrder->user->id);
    }

    /**
     * Test purchase order has many details relationship.
     */
    public function test_purchase_order_has_many_details(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $details = PurchaseOrderDetail::factory()->count(3)->create(['purchase_order_id' => $purchaseOrder->id]);

        $this->assertCount(3, $purchaseOrder->details);
        $this->assertInstanceOf(PurchaseOrderDetail::class, $purchaseOrder->details->first());
        
        foreach ($details as $detail) {
            $this->assertTrue($purchaseOrder->details->contains($detail));
        }
    }

    /**
     * Test generateOrderNumber creates correct format.
     */
    public function test_generate_order_number_format(): void
    {
        // Mock the current date
        Carbon::setTestNow('2024-01-01 12:00:00');

        $orderNumber = PurchaseOrder::generateOrderNumber();

        $this->assertStringStartsWith('PO20240101', $orderNumber);
        $this->assertEquals(14, strlen($orderNumber)); // PO + 8 digits date + 4 digits sequence
        $this->assertMatchesRegularExpression('/^PO\d{8}\d{4}$/', $orderNumber);
    }

    /**
     * Test generateOrderNumber increments sequence correctly.
     */
    public function test_generate_order_number_increments_sequence(): void
    {
        // Mock the current date
        Carbon::setTestNow('2024-01-01 12:00:00');

        // Create some existing orders for the same date
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010001']);
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010002']);
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010003']);

        $orderNumber = PurchaseOrder::generateOrderNumber();

        $this->assertEquals('PO202401010004', $orderNumber);
    }

    /**
     * Test generateOrderNumber starts from 0001 when no existing orders.
     */
    public function test_generate_order_number_starts_from_0001(): void
    {
        // Mock the current date
        Carbon::setTestNow('2024-02-15 10:00:00');

        $orderNumber = PurchaseOrder::generateOrderNumber();

        $this->assertEquals('PO202402150001', $orderNumber);
    }

    /**
     * Test generateOrderNumber handles different dates correctly.
     */
    public function test_generate_order_number_different_dates(): void
    {
        // Create orders for a previous date
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010001']);
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010002']);

        // Mock a new date
        Carbon::setTestNow('2024-02-01 12:00:00');

        $orderNumber = PurchaseOrder::generateOrderNumber();

        // Should start from 0001 for the new date
        $this->assertEquals('PO202402010001', $orderNumber);
    }

    /**
     * Test generateOrderNumber handles high sequence numbers correctly.
     */
    public function test_generate_order_number_high_sequence(): void
    {
        // Mock the current date
        Carbon::setTestNow('2024-01-01 12:00:00');

        // Create an order with a high sequence number
        PurchaseOrder::factory()->create(['order_number' => 'PO202401019999']);

        $orderNumber = PurchaseOrder::generateOrderNumber();

        $this->assertEquals('PO2024010110000', $orderNumber);
    }

    /**
     * Test generateOrderNumber with non-sequential existing orders.
     */
    public function test_generate_order_number_non_sequential_orders(): void
    {
        // Mock the current date
        Carbon::setTestNow('2024-01-01 12:00:00');

        // Create orders with gaps in sequence
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010001']);
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010005']);
        PurchaseOrder::factory()->create(['order_number' => 'PO202401010003']);

        $orderNumber = PurchaseOrder::generateOrderNumber();

        // Should take the highest number and increment
        $this->assertEquals('PO202401010006', $orderNumber);
    }

    /**
     * Test purchase order creation with generated order number.
     */
    public function test_purchase_order_creation_with_generated_number(): void
    {
        Carbon::setTestNow('2024-01-01 12:00:00');
        
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create();

        $purchaseOrder = PurchaseOrder::create([
            'order_number' => PurchaseOrder::generateOrderNumber(),
            'supplier_id' => $supplier->id,
            'order_date' => now(),
            'expected_date' => now()->addDays(14),
            'status' => 'pending',
            'total_amount' => 1000.00,
            'user_id' => $user->id,
        ]);

        $this->assertEquals('PO202401010001', $purchaseOrder->order_number);
        $this->assertDatabaseHas('purchase_orders', [
            'order_number' => 'PO202401010001',
        ]);
    }
} 