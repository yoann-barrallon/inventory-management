<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use Tests\TestCase;

class StockTest extends TestCase
{
    /**
     * Test that a stock can be created with valid data.
     */
    public function test_stock_can_be_created_with_valid_data(): void
    {
        $product = Product::factory()->create();
        $location = Location::factory()->create();

        $stockData = [
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => 100,
            'reserved_quantity' => 20,
        ];

        $stock = Stock::create($stockData);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals(100, $stock->quantity);
        $this->assertEquals(20, $stock->reserved_quantity);
        $this->assertEquals($product->id, $stock->product_id);
        $this->assertEquals($location->id, $stock->location_id);
        $this->assertDatabaseHas('stocks', $stockData);
    }

    /**
     * Test stock fillable attributes.
     */
    public function test_stock_fillable_attributes(): void
    {
        $stock = new Stock();
        $expectedFillable = [
            'product_id',
            'location_id',
            'quantity',
            'reserved_quantity',
        ];

        $this->assertEquals($expectedFillable, $stock->getFillable());
    }

    /**
     * Test stock belongs to product relationship.
     */
    public function test_stock_belongs_to_product(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product']);
        $stock = Stock::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $stock->product);
        $this->assertEquals('Test Product', $stock->product->name);
        $this->assertEquals($product->id, $stock->product->id);
    }

    /**
     * Test stock belongs to location relationship.
     */
    public function test_stock_belongs_to_location(): void
    {
        $location = Location::factory()->create(['name' => 'Main Warehouse']);
        $stock = Stock::factory()->create(['location_id' => $location->id]);

        $this->assertInstanceOf(Location::class, $stock->location);
        $this->assertEquals('Main Warehouse', $stock->location->name);
        $this->assertEquals($location->id, $stock->location->id);
    }

    /**
     * Test getAvailableQuantityAttribute calculates correctly.
     */
    public function test_get_available_quantity_attribute(): void
    {
        $stock = Stock::factory()->create([
            'quantity' => 100,
            'reserved_quantity' => 30,
        ]);

        $this->assertEquals(70, $stock->available_quantity);
    }

    /**
     * Test getAvailableQuantityAttribute with no reserved quantity.
     */
    public function test_get_available_quantity_attribute_with_no_reserved(): void
    {
        $stock = Stock::factory()->create([
            'quantity' => 50,
            'reserved_quantity' => 0,
        ]);

        $this->assertEquals(50, $stock->available_quantity);
    }

    /**
     * Test getAvailableQuantityAttribute when fully reserved.
     */
    public function test_get_available_quantity_attribute_fully_reserved(): void
    {
        $stock = Stock::factory()->create([
            'quantity' => 100,
            'reserved_quantity' => 100,
        ]);

        $this->assertEquals(0, $stock->available_quantity);
    }

    /**
     * Test getAvailableQuantityAttribute when over-reserved (should return negative).
     */
    public function test_get_available_quantity_attribute_over_reserved(): void
    {
        $stock = Stock::factory()->create([
            'quantity' => 100,
            'reserved_quantity' => 120,
        ]);

        $this->assertEquals(-20, $stock->available_quantity);
    }

    /**
     * Test that stock can have zero quantity.
     */
    public function test_stock_can_have_zero_quantity(): void
    {
        $stock = Stock::factory()->create([
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertEquals(0, $stock->available_quantity);
    }

    /**
     * Test that stock updates work correctly.
     */
    public function test_stock_can_be_updated(): void
    {
        $stock = Stock::factory()->create([
            'quantity' => 100,
            'reserved_quantity' => 10,
        ]);

        $stock->update([
            'quantity' => 150,
            'reserved_quantity' => 25,
        ]);

        $this->assertEquals(150, $stock->fresh()->quantity);
        $this->assertEquals(25, $stock->fresh()->reserved_quantity);
        $this->assertEquals(125, $stock->fresh()->available_quantity);
    }
} 