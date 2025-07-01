<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Tests\TestCase;

class PurchaseOrderDetailTest extends TestCase
{
    /**
     * Test that a purchase order detail can be created with valid data.
     */
    public function test_purchase_order_detail_can_be_created_with_valid_data(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $product = Product::factory()->create();

        $detailData = [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 15.50,
            'total_price' => 1550.00,
            'received_quantity' => 50,
        ];

        $detail = PurchaseOrderDetail::create($detailData);

        $this->assertInstanceOf(PurchaseOrderDetail::class, $detail);
        $this->assertEquals(100, $detail->quantity);
        $this->assertEquals(15.50, $detail->unit_price);
        $this->assertEquals(1550.00, $detail->total_price);
        $this->assertEquals(50, $detail->received_quantity);
        $this->assertDatabaseHas('purchase_order_details', $detailData);
    }

    /**
     * Test purchase order detail fillable attributes.
     */
    public function test_purchase_order_detail_fillable_attributes(): void
    {
        $detail = new PurchaseOrderDetail();
        $expectedFillable = [
            'purchase_order_id',
            'product_id',
            'quantity',
            'unit_price',
            'total_price',
            'received_quantity',
        ];

        $this->assertEquals($expectedFillable, $detail->getFillable());
    }

    /**
     * Test purchase order detail casts.
     */
    public function test_purchase_order_detail_casts(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'unit_price' => 12.345,
            'total_price' => 123.456,
        ]);

        $this->assertEquals(12.35, $detail->unit_price); // Should cast to 2 decimal places
        $this->assertEquals(123.46, $detail->total_price); // Should cast to 2 decimal places
    }

    /**
     * Test purchase order detail belongs to purchase order relationship.
     */
    public function test_purchase_order_detail_belongs_to_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['order_number' => 'PO202401010001']);
        $detail = PurchaseOrderDetail::factory()->create(['purchase_order_id' => $purchaseOrder->id]);

        $this->assertInstanceOf(PurchaseOrder::class, $detail->purchaseOrder);
        $this->assertEquals('PO202401010001', $detail->purchaseOrder->order_number);
        $this->assertEquals($purchaseOrder->id, $detail->purchaseOrder->id);
    }

    /**
     * Test purchase order detail belongs to product relationship.
     */
    public function test_purchase_order_detail_belongs_to_product(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product']);
        $detail = PurchaseOrderDetail::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $detail->product);
        $this->assertEquals('Test Product', $detail->product->name);
        $this->assertEquals($product->id, $detail->product->id);
    }

    /**
     * Test getRemainingQuantityAttribute calculates correctly.
     */
    public function test_get_remaining_quantity_attribute(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 30,
        ]);

        $this->assertEquals(70, $detail->remaining_quantity);
    }

    /**
     * Test getRemainingQuantityAttribute with no received quantity.
     */
    public function test_get_remaining_quantity_attribute_with_no_received(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 50,
            'received_quantity' => 0,
        ]);

        $this->assertEquals(50, $detail->remaining_quantity);
    }

    /**
     * Test getRemainingQuantityAttribute when fully received.
     */
    public function test_get_remaining_quantity_attribute_fully_received(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 100,
        ]);

        $this->assertEquals(0, $detail->remaining_quantity);
    }

    /**
     * Test getRemainingQuantityAttribute when over-received.
     */
    public function test_get_remaining_quantity_attribute_over_received(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 120,
        ]);

        $this->assertEquals(-20, $detail->remaining_quantity);
    }

    /**
     * Test getIsFullyReceivedAttribute returns false when not fully received.
     */
    public function test_get_is_fully_received_attribute_not_received(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 50,
        ]);

        $this->assertFalse($detail->is_fully_received);
    }

    /**
     * Test getIsFullyReceivedAttribute returns true when fully received.
     */
    public function test_get_is_fully_received_attribute_fully_received(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 100,
        ]);

        $this->assertTrue($detail->is_fully_received);
    }

    /**
     * Test getIsFullyReceivedAttribute returns true when over-received.
     */
    public function test_get_is_fully_received_attribute_over_received(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 120,
        ]);

        $this->assertTrue($detail->is_fully_received);
    }

    /**
     * Test getIsFullyReceivedAttribute with zero quantities.
     */
    public function test_get_is_fully_received_attribute_zero_quantities(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 0,
            'received_quantity' => 0,
        ]);

        $this->assertTrue($detail->is_fully_received);
    }

    /**
     * Test that detail updates work correctly.
     */
    public function test_purchase_order_detail_can_be_updated(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 20,
            'unit_price' => 10.00,
        ]);

        $detail->update([
            'received_quantity' => 75,
            'unit_price' => 12.50,
        ]);

        $this->assertEquals(75, $detail->fresh()->received_quantity);
        $this->assertEquals(12.50, $detail->fresh()->unit_price);
        $this->assertEquals(25, $detail->fresh()->remaining_quantity);
        $this->assertFalse($detail->fresh()->is_fully_received);
    }

    /**
     * Test business logic when receiving partial shipment.
     */
    public function test_partial_shipment_business_logic(): void
    {
        $detail = PurchaseOrderDetail::factory()->create([
            'quantity' => 100,
            'received_quantity' => 0,
            'unit_price' => 15.00,
            'total_price' => 1500.00,
        ]);

        // Receive partial shipment
        $detail->update(['received_quantity' => 40]);

        $this->assertEquals(40, $detail->fresh()->received_quantity);
        $this->assertEquals(60, $detail->fresh()->remaining_quantity);
        $this->assertFalse($detail->fresh()->is_fully_received);

        // Receive remaining shipment
        $detail->update(['received_quantity' => 100]);

        $this->assertEquals(100, $detail->fresh()->received_quantity);
        $this->assertEquals(0, $detail->fresh()->remaining_quantity);
        $this->assertTrue($detail->fresh()->is_fully_received);
    }
} 