<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\TransactionService;
use Database\Seeders\HistoricalTransactionDemoSeeder;
use Database\Seeders\ThesisStockCorrectionSeeder;
use Database\Seeders\TransactionDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalTransactionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_totals_match_and_september_balances_remain_unchanged(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 24));
        $this->seed();
        $old = MooraRun::first()->results->keyBy('product_id');
        $this->seed(TransactionDemoSeeder::class);
        $september = MooraRun::latest('id')->first()->results->keyBy('product_id');
        $this->seed(HistoricalTransactionDemoSeeder::class);
        foreach (MooraRun::latest('id')->first()->results as $result) {
            $this->assertEquals($old[$result->product_id]->raw_values, $result->raw_values);
        }
        $new = app(TransactionService::class)->calculate('2026-09-01', '2026-09-21', User::where('role', 'owner')->first());
        foreach ($new->results as $result) {
            $this->assertEquals($september[$result->product_id]->raw_values, $result->raw_values);
        }
        $count = StockTransaction::count();
        $this->seed(HistoricalTransactionDemoSeeder::class);
        $this->assertSame($count, StockTransaction::count());
        // Recreate the old 22-unit historical balance and preserve September with an offset.
        $rice = Product::where('code', 'Minangrasa01')->firstOrFail();
        $opening = StockTransaction::where('product_id', $rice->id)->where('type', 'opening')->first();
        $opening->decrement('quantity', 3);
        StockTransaction::where('product_id', $rice->id)->whereDate('occurred_on', '2026-09-01')->where('type', 'increase')->increment('quantity', 3);
        $this->seed(ThesisStockCorrectionSeeder::class);
        $corrected = MooraRun::latest('id')->first()->results()->where('product_id', $rice->id)->first();
        $this->assertEquals(25, $corrected->raw_values['C1']);
        $count = StockTransaction::count();
        $this->seed(ThesisStockCorrectionSeeder::class);
        $this->assertSame($count, StockTransaction::count());
        $again = app(TransactionService::class)->calculate('2026-09-01', '2026-09-21', User::where('role', 'owner')->first());
        $this->assertEquals($september[$rice->id]->raw_values, $again->results->firstWhere('product_id', $rice->id)->raw_values);
    }
}
