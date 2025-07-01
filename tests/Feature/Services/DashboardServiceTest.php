<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Carbon\Carbon;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Category::query()->delete();
        Supplier::query()->delete();
        Location::query()->delete();
        Product::query()->delete();
        $this->service = new DashboardService();
    }

    public function test_get_basic_stats_returns_correct_counts()
    {
        $categories = Category::factory()->count(2)->create();
        $suppliers = Supplier::factory()->count(3)->create();
        $locations = Location::factory()->count(2)->create();
        $products = Product::factory()->count(5)->sequence(
            ['category_id' => $categories[0]->id, 'supplier_id' => $suppliers[0]->id],
            ['category_id' => $categories[1]->id, 'supplier_id' => $suppliers[1]->id],
            ['category_id' => $categories[0]->id, 'supplier_id' => $suppliers[2]->id],
            ['category_id' => $categories[1]->id, 'supplier_id' => $suppliers[0]->id],
            ['category_id' => $categories[0]->id, 'supplier_id' => $suppliers[1]->id],
        )->create();

        foreach (Product::all() as $product) {
            $minStock = $product->min_stock_level ?? 0;
            Stock::factory()->create([
                'product_id' => $product->id,
                'quantity' => $minStock + 1,
                'location_id' => $locations->random()->id,
                'reserved_quantity' => 0,
            ]);
        }

        $stats = $this->service->getBasicStats();

        $this->assertEquals(5, $stats['total_products']);
        $this->assertEquals(2, $stats['total_categories']);
        $this->assertEquals(3, $stats['total_suppliers']);
        $this->assertEquals(2, $stats['total_locations']);
        $expectedStockValue = Product::with('stocks')->get()->map(function ($product) {
            $stock = $product->stocks->sum('quantity');
            return $stock * $product->cost_price;
        })->sum();
        $this->assertEquals(number_format($expectedStockValue, 2), $stats['total_stock_value']);
        $this->assertEquals(0, $stats['low_stock_count']);
        $this->assertEquals(0, $stats['recent_transactions']);
        $this->assertEquals(0, $stats['pending_purchase_orders']);
    }

    public function test_get_low_stock_products_returns_expected()
    {
        $product = Product::factory()->create(['min_stock_level' => 10]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity' => 5]);
        $result = $this->service->getLowStockProducts();
        $this->assertTrue($result->contains('id', $product->id));
    }

    public function test_get_recent_activity_returns_transactions()
    {
        $transaction = StockTransaction::factory()->create();
        $result = $this->service->getRecentActivity();
        $this->assertNotEmpty($result);
        $this->assertEquals($transaction->id, $result->first()['id']);
    }

    public function test_get_stock_by_category_returns_correct_quantities()
    {
        $category = Category::factory()->create(['name' => 'Cat1']);
        $product = Product::factory()->create(['category_id' => $category->id]);
        Stock::factory()->create(['product_id' => $product->id, 'quantity' => 7]);
        $result = $this->service->getStockByCategory();
        $this->assertTrue($result->contains(fn($row) => $row['name'] === 'Cat1' && $row['quantity'] === 7));
    }

    public function test_get_stock_by_location_returns_correct_quantities()
    {
        $location = Location::factory()->create(['name' => 'Loc1']);
        $product = Product::factory()->create();
        Stock::factory()->create(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => 4]);
        $result = $this->service->getStockByLocation();
        $this->assertTrue($result->contains(fn($row) => $row['name'] === 'Loc1' && $row['quantity'] === 4));
    }

    public function test_get_monthly_transaction_chart_returns_expected_structure()
    {
        $now = Carbon::now();
        StockTransaction::factory()->create([
            'type' => 'in',
            'created_at' => $now->copy()->subMonths(1),
        ]);
        $result = $this->service->getMonthlyTransactionChart();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('month', $result[0]);
        $this->assertArrayHasKey('in', $result[0]);
        $this->assertArrayHasKey('out', $result[0]);
        $this->assertArrayHasKey('adjustment', $result[0]);
    }

    public function test_get_purchase_orders_stats_returns_status_counts()
    {
        PurchaseOrder::factory()->create(['status' => 'pending']);
        PurchaseOrder::factory()->create(['status' => 'confirmed']);
        PurchaseOrder::factory()->create(['status' => 'received']);
        PurchaseOrder::factory()->create(['status' => 'cancelled']);
        $result = $this->service->getPurchaseOrdersStats();
        $this->assertEquals(1, $result['pending']);
        $this->assertEquals(1, $result['confirmed']);
        $this->assertEquals(1, $result['received']);
        $this->assertEquals(1, $result['cancelled']);
    }

    public function test_get_top_categories_by_value_returns_ordered()
    {
        $cat1 = Category::factory()->create(['name' => 'Cat1']);
        $cat2 = Category::factory()->create(['name' => 'Cat2']);
        $prod1 = Product::factory()->create(['category_id' => $cat1->id, 'cost_price' => 10]);
        $prod2 = Product::factory()->create(['category_id' => $cat2->id, 'cost_price' => 20]);
        Stock::factory()->create(['product_id' => $prod1->id, 'quantity' => 2]); // 20
        Stock::factory()->create(['product_id' => $prod2->id, 'quantity' => 1]); // 20
        $result = $this->service->getTopCategoriesByValue();
        $this->assertTrue($result->contains('name', 'Cat1'));
        $this->assertTrue($result->contains('name', 'Cat2'));
    }

    public function test_get_dashboard_stats_returns_all_keys()
    {
        $result = $this->service->getDashboardStats();
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('lowStockProducts', $result);
        $this->assertArrayHasKey('recentActivity', $result);
        $this->assertArrayHasKey('stockByCategory', $result);
        $this->assertArrayHasKey('stockByLocation', $result);
        $this->assertArrayHasKey('monthlyTransactions', $result);
        $this->assertArrayHasKey('purchaseOrdersStats', $result);
    }
} 