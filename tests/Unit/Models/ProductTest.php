<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrderDetail;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use Tests\TestCase;

class ProductTest extends TestCase
{
    /**
     * Test that a product can be created with valid data.
     */
    public function test_product_can_be_created_with_valid_data(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();

        $productData = [
            'name' => 'Test Product',
            'description' => 'A test product description',
            'sku' => 'TEST-001',
            'barcode' => '1234567890123',
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 99.99,
            'cost_price' => 59.99,
            'min_stock_level' => 10,
            'is_active' => true,
        ];

        $product = Product::create($productData);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('TEST-001', $product->sku);
        $this->assertEquals(99.99, $product->unit_price);
        $this->assertEquals(59.99, $product->cost_price);
        $this->assertEquals(10, $product->min_stock_level);
        $this->assertTrue($product->is_active);
        $this->assertDatabaseHas('products', $productData);
    }

    /**
     * Test product fillable attributes.
     */
    public function test_product_fillable_attributes(): void
    {
        $product = new Product();
        $expectedFillable = [
            'name',
            'description',
            'sku',
            'barcode',
            'category_id',
            'supplier_id',
            'unit_price',
            'cost_price',
            'min_stock_level',
            'is_active',
        ];

        $this->assertEquals($expectedFillable, $product->getFillable());
    }

    /**
     * Test product casts.
     */
    public function test_product_casts(): void
    {
        $product = Product::factory()->create([
            'unit_price' => 10.123,
            'cost_price' => 5.789,
            'is_active' => 1,
        ]);

        $this->assertEquals(10.12, $product->unit_price); // Should cast to 2 decimal places
        $this->assertEquals(5.79, $product->cost_price); // Should cast to 2 decimal places
        $this->assertTrue($product->is_active); // Should cast to boolean
    }

    /**
     * Test product belongs to category relationship.
     */
    public function test_product_belongs_to_category(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertEquals('Electronics', $product->category->name);
        $this->assertEquals($category->id, $product->category->id);
    }

    /**
     * Test product belongs to supplier relationship.
     */
    public function test_product_belongs_to_supplier(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $this->assertInstanceOf(Supplier::class, $product->supplier);
        $this->assertEquals('Test Supplier', $product->supplier->name);
        $this->assertEquals($supplier->id, $product->supplier->id);
    }

    /**
     * Test product has many stocks relationship.
     */
    public function test_product_has_many_stocks(): void
    {
        $product = Product::factory()->create();
        $stocks = Stock::factory()->count(3)->create(['product_id' => $product->id]);

        $this->assertCount(3, $product->stocks);
        $this->assertInstanceOf(Stock::class, $product->stocks->first());
        
        foreach ($stocks as $stock) {
            $this->assertTrue($product->stocks->contains($stock));
        }
    }

    /**
     * Test product has many stock transactions relationship.
     */
    public function test_product_has_many_stock_transactions(): void
    {
        $product = Product::factory()->create();
        $transactions = StockTransaction::factory()->count(2)->create(['product_id' => $product->id]);

        $this->assertCount(2, $product->stockTransactions);
        $this->assertInstanceOf(StockTransaction::class, $product->stockTransactions->first());
        
        foreach ($transactions as $transaction) {
            $this->assertTrue($product->stockTransactions->contains($transaction));
        }
    }

    /**
     * Test product has many purchase order details relationship.
     */
    public function test_product_has_many_purchase_order_details(): void
    {
        $product = Product::factory()->create();
        $details = PurchaseOrderDetail::factory()->count(2)->create(['product_id' => $product->id]);

        $this->assertCount(2, $product->purchaseOrderDetails);
        $this->assertInstanceOf(PurchaseOrderDetail::class, $product->purchaseOrderDetails->first());
        
        foreach ($details as $detail) {
            $this->assertTrue($product->purchaseOrderDetails->contains($detail));
        }
    }

    /**
     * Test getTotalStockAttribute calculates correctly.
     */
    public function test_get_total_stock_attribute(): void
    {
        $product = Product::factory()->create();
        
        // Create stocks in different locations
        Stock::factory()->create(['product_id' => $product->id, 'quantity' => 100]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity' => 50]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity' => 25]);

        $this->assertEquals(175, $product->total_stock);
    }

    /**
     * Test getTotalStockAttribute returns zero when no stocks.
     */
    public function test_get_total_stock_attribute_with_no_stocks(): void
    {
        $product = Product::factory()->create();

        $this->assertEquals(0, $product->total_stock);
    }

    /**
     * Test getAvailableStockAttribute calculates correctly.
     */
    public function test_get_available_stock_attribute(): void
    {
        $product = Product::factory()->create();
        
        // Create stocks with reserved quantities
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'reserved_quantity' => 20,
        ]);
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity' => 50,
            'reserved_quantity' => 10,
        ]);

        // Total stock: 150, Total reserved: 30, Available: 120
        $this->assertEquals(120, $product->available_stock);
    }

    /**
     * Test getAvailableStockAttribute with no reserved quantities.
     */
    public function test_get_available_stock_attribute_with_no_reserved(): void
    {
        $product = Product::factory()->create();
        
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $this->assertEquals(100, $product->available_stock);
    }

    /**
     * Test getAvailableStockAttribute when all stock is reserved.
     */
    public function test_get_available_stock_attribute_fully_reserved(): void
    {
        $product = Product::factory()->create();
        
        Stock::factory()->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'reserved_quantity' => 100,
        ]);

        $this->assertEquals(0, $product->available_stock);
    }
} 