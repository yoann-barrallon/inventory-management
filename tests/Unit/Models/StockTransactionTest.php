<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use Tests\TestCase;

class StockTransactionTest extends TestCase
{
    /**
     * Test that a stock transaction can be created with valid data.
     */
    public function test_stock_transaction_can_be_created_with_valid_data(): void
    {
        $product = Product::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create();

        $transactionData = [
            'product_id' => $product->id,
            'location_id' => $location->id,
            'type' => 'in',
            'quantity' => 50,
            'reason' => 'Purchase receipt',
            'reference' => 'PO202401010001',
            'user_id' => $user->id,
        ];

        $transaction = StockTransaction::create($transactionData);

        $this->assertInstanceOf(StockTransaction::class, $transaction);
        $this->assertEquals('in', $transaction->type);
        $this->assertEquals(50, $transaction->quantity);
        $this->assertEquals('Purchase receipt', $transaction->reason);
        $this->assertEquals('PO202401010001', $transaction->reference);
        $this->assertDatabaseHas('stock_transactions', $transactionData);
    }

    /**
     * Test stock transaction fillable attributes.
     */
    public function test_stock_transaction_fillable_attributes(): void
    {
        $transaction = new StockTransaction();
        $expectedFillable = [
            'product_id',
            'location_id',
            'type',
            'quantity',
            'reason',
            'reference',
            'user_id',
        ];

        $this->assertEquals($expectedFillable, $transaction->getFillable());
    }

    /**
     * Test stock transaction belongs to product relationship.
     */
    public function test_stock_transaction_belongs_to_product(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product']);
        $transaction = StockTransaction::factory()->create(['product_id' => $product->id]);

        $this->assertInstanceOf(Product::class, $transaction->product);
        $this->assertEquals('Test Product', $transaction->product->name);
        $this->assertEquals($product->id, $transaction->product->id);
    }

    /**
     * Test stock transaction belongs to location relationship.
     */
    public function test_stock_transaction_belongs_to_location(): void
    {
        $location = Location::factory()->create(['name' => 'Main Warehouse']);
        $transaction = StockTransaction::factory()->create(['location_id' => $location->id]);

        $this->assertInstanceOf(Location::class, $transaction->location);
        $this->assertEquals('Main Warehouse', $transaction->location->name);
        $this->assertEquals($location->id, $transaction->location->id);
    }

    /**
     * Test stock transaction belongs to user relationship.
     */
    public function test_stock_transaction_belongs_to_user(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $transaction = StockTransaction::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $transaction->user);
        $this->assertEquals('Test User', $transaction->user->name);
        $this->assertEquals($user->id, $transaction->user->id);
    }

    /**
     * Test different transaction types.
     */
    public function test_different_transaction_types(): void
    {
        $inboundTransaction = StockTransaction::factory()->create(['type' => 'in']);
        $outboundTransaction = StockTransaction::factory()->create(['type' => 'out']);
        $adjustmentTransaction = StockTransaction::factory()->create(['type' => 'adjustment']);
        $transferTransaction = StockTransaction::factory()->create(['type' => 'transfer']);

        $this->assertEquals('in', $inboundTransaction->type);
        $this->assertEquals('out', $outboundTransaction->type);
        $this->assertEquals('adjustment', $adjustmentTransaction->type);
        $this->assertEquals('transfer', $transferTransaction->type);
    }

    /**
     * Test transaction with negative quantity for outbound.
     */
    public function test_transaction_with_negative_quantity(): void
    {
        $transaction = StockTransaction::factory()->create([
            'type' => 'out',
            'quantity' => -25,
        ]);

        $this->assertEquals(-25, $transaction->quantity);
        $this->assertEquals('out', $transaction->type);
    }

    /**
     * Test transaction updates work correctly.
     */
    public function test_stock_transaction_can_be_updated(): void
    {
        $transaction = StockTransaction::factory()->create([
            'type' => 'in',
            'quantity' => 50,
            'reason' => 'Old reason',
        ]);

        $transaction->update([
            'type' => 'adjustment',
            'quantity' => 75,
            'reason' => 'New reason',
        ]);

        $this->assertEquals('adjustment', $transaction->fresh()->type);
        $this->assertEquals(75, $transaction->fresh()->quantity);
        $this->assertEquals('New reason', $transaction->fresh()->reason);
    }

    /**
     * Test transaction with reference.
     */
    public function test_transaction_with_reference(): void
    {
        $transaction = StockTransaction::factory()->create([
            'reference' => 'PO202401010001',
            'reason' => 'Purchase order receipt',
        ]);

        $this->assertEquals('PO202401010001', $transaction->reference);
        $this->assertEquals('Purchase order receipt', $transaction->reason);
    }

    /**
     * Test transaction without reference.
     */
    public function test_transaction_without_reference(): void
    {
        $transaction = StockTransaction::factory()->create([
            'reference' => null,
            'reason' => 'Manual adjustment',
        ]);

        $this->assertNull($transaction->reference);
        $this->assertEquals('Manual adjustment', $transaction->reason);
    }
} 