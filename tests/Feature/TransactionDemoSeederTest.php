<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Product;
use App\Models\StockTransaction;
use Database\Seeders\TransactionDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_has_balanced_stock_consistent_sales_and_is_repeatable_without_duplicates(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 24));
        $this->seed();
        $this->seed(TransactionDemoSeeder::class);
        $this->assertSame(114, StockTransaction::count());
        $run = MooraRun::latest('id')->firstOrFail();
        $this->assertSame(5, $run->results()->count());
        foreach (Product::all() as $product) {
            $balance = 0;
            $price = null;
            foreach (StockTransaction::where('product_id', $product->id)->orderBy('occurred_on')->orderBy('id')->get() as $entry) {
                $balance += $entry->type === 'sale' ? -(float) $entry->quantity : (float) $entry->quantity;
                $this->assertGreaterThanOrEqual(0, $balance);
                if ($entry->type === 'sale') {
                    $unitPrice = (float) $entry->sales_value / (float) $entry->quantity;
                    $price ??= $unitPrice;
                    $this->assertEquals($price, $unitPrice);
                }
                $this->assertStringContainsString('SIMULASI', $entry->notes);
            }
            $this->assertEquals($balance, $run->results()->where('product_id', $product->id)->first()->raw_values['C1']);
        }
        $this->seed(TransactionDemoSeeder::class);
        $this->assertSame(114, StockTransaction::count());
        $this->assertSame(2, MooraRun::count());
    }
}
