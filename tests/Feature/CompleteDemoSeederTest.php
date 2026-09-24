<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Product;
use App\Models\StockTransaction;
use Database\Seeders\CompleteDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_demo_on_empty_database_matches_expected_balances_and_is_repeatable(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 24));
        $this->seed(CompleteDemoSeeder::class);
        foreach (['GL01' => 41, '8993379256371' => 20, 'Minangrasa01' => 43, '089686010824' => 30, '8886008101053' => 75] as $code => $expected) {
            $product = Product::where('code', $code)->firstOrFail();
            $balance = StockTransaction::where('product_id', $product->id)->get()->sum(fn ($entry) => in_array($entry->type, ['sale', 'decrease']) ? -(float) $entry->quantity : (float) $entry->quantity);
            $this->assertEquals($expected, $balance);
        }
        $rice = Product::where('code', 'Minangrasa01')->firstOrFail();
        $this->assertEquals(25, MooraRun::latest('id')->first()->results()->where('product_id', $rice->id)->first()->raw_values['C1']);
        $count = StockTransaction::count();
        $runs = MooraRun::count();
        $this->seed(CompleteDemoSeeder::class);
        $this->assertSame($count, StockTransaction::count());
        $this->assertSame($runs, MooraRun::count());
    }
}
