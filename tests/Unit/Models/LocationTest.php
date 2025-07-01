<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Location;
use App\Models\Stock;
use App\Models\StockTransaction;
use Tests\TestCase;

class LocationTest extends TestCase
{
    /**
     * Test that a location can be created with valid data.
     */
    public function test_location_can_be_created_with_valid_data(): void
    {
        $locationData = [
            'name' => 'Main Warehouse',
            'description' => 'Primary storage facility',
            'address' => '456 Industrial Blvd, City, State 67890',
            'is_active' => true,
        ];

        $location = Location::create($locationData);

        $this->assertInstanceOf(Location::class, $location);
        $this->assertEquals('Main Warehouse', $location->name);
        $this->assertEquals('Primary storage facility', $location->description);
        $this->assertEquals('456 Industrial Blvd, City, State 67890', $location->address);
        $this->assertTrue($location->is_active);
        $this->assertDatabaseHas('locations', $locationData);
    }

    /**
     * Test location fillable attributes.
     */
    public function test_location_fillable_attributes(): void
    {
        $location = new Location();
        $expectedFillable = [
            'name',
            'description',
            'address',
            'is_active',
        ];

        $this->assertEquals($expectedFillable, $location->getFillable());
    }

    /**
     * Test location casts.
     */
    public function test_location_casts(): void
    {
        $location = Location::factory()->create(['is_active' => 1]);

        $this->assertTrue($location->is_active); // Should cast to boolean
    }

    /**
     * Test location has many stocks relationship.
     */
    public function test_location_has_many_stocks(): void
    {
        $location = Location::factory()->create();
        $stocks = Stock::factory()->count(3)->create(['location_id' => $location->id]);

        $this->assertCount(3, $location->stocks);
        $this->assertInstanceOf(Stock::class, $location->stocks->first());
        
        foreach ($stocks as $stock) {
            $this->assertTrue($location->stocks->contains($stock));
        }
    }

    /**
     * Test location has many stock transactions relationship.
     */
    public function test_location_has_many_stock_transactions(): void
    {
        $location = Location::factory()->create();
        $transactions = StockTransaction::factory()->count(2)->create(['location_id' => $location->id]);

        $this->assertCount(2, $location->stockTransactions);
        $this->assertInstanceOf(StockTransaction::class, $location->stockTransactions->first());
        
        foreach ($transactions as $transaction) {
            $this->assertTrue($location->stockTransactions->contains($transaction));
        }
    }

    /**
     * Test location can be inactive.
     */
    public function test_location_can_be_inactive(): void
    {
        $location = Location::factory()->create(['is_active' => false]);

        $this->assertFalse($location->is_active);
    }

    /**
     * Test location can exist without stocks.
     */
    public function test_location_can_exist_without_stocks(): void
    {
        $location = Location::factory()->create();

        $this->assertCount(0, $location->stocks);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $location->stocks);
    }

    /**
     * Test location updates work correctly.
     */
    public function test_location_can_be_updated(): void
    {
        $location = Location::factory()->create([
            'name' => 'Old Warehouse',
            'description' => 'Old Description',
            'is_active' => true,
        ]);

        $location->update([
            'name' => 'New Warehouse',
            'description' => 'New Description',
            'is_active' => false,
        ]);

        $this->assertEquals('New Warehouse', $location->fresh()->name);
        $this->assertEquals('New Description', $location->fresh()->description);
        $this->assertFalse($location->fresh()->is_active);
    }
} 