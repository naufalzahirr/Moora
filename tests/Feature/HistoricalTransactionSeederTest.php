<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\TransactionService;
use Database\Seeders\HistoricalTransactionDemoSeeder;
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
    }
}
