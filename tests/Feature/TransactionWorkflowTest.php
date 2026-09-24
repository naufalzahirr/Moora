<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 24));
        $this->seed();
        Product::query()->update(['active' => false]);
        $this->product = Product::create(['code' => 'TX-1', 'name' => 'Barang Transaksi', 'unit' => 'pcs', 'active' => true]);
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
    }

    private function entry(string $type, float $quantity, string $date = '2026-09-01', ?float $value = null, ?string $key = null)
    {
        return $this->post(route('transactions.store'), [
            'submission_key' => $key ?? (string) Str::uuid(), 'product_id' => $this->product->id,
            'type' => $type, 'quantity' => $quantity, 'occurred_on' => $date, 'sales_value' => $value,
        ]);
    }

    public function test_transactions_accumulate_and_analysis_uses_end_date_stock_and_only_sales_in_range(): void
    {
        $this->entry('opening', 100)->assertSessionHasNoErrors();
        $this->entry('sale', 5, '2026-09-02', 15000)->assertSessionHasNoErrors();
        $this->entry('receipt', 20, '2026-09-03')->assertSessionHasNoErrors();
        $this->entry('sale', 3, '2026-09-04', 9000)->assertSessionHasNoErrors();
        $this->entry('sale', 10, '2026-09-10', 30000)->assertSessionHasNoErrors();
        $this->get(route('transactions.index'))->assertOk()->assertViewHas('balances', fn ($b) => (float) $b[$this->product->id] === 102.0);
        $this->post(route('transactions.calculate'), ['start_date' => '2026-09-03', 'end_date' => '2026-09-04'])->assertSessionHasNoErrors()->assertRedirect();
        $period = Period::latest('id')->first();
        $this->assertSame('transactions', $period->source_type);
        $result = $period->runs()->first()->results()->first();
        $this->assertEquals(['C1' => 112, 'C2' => 3, 'C3' => 9000], $result->raw_values);
        $this->post(route('datasets.revise', $period))->assertForbidden();
        $this->assertEquals(['C1' => 112, 'C2' => 3, 'C3' => 9000], $result->fresh()->raw_values);
    }

    public function test_opening_is_required_once_and_stock_cannot_go_negative(): void
    {
        $this->entry('sale', 1, value: 100)->assertSessionHasErrors('type');
        $this->entry('opening', 0)->assertSessionHasNoErrors();
        $this->entry('opening', 20)->assertSessionHasErrors('type');
        $this->entry('sale', 1, value: 100)->assertSessionHasErrors('quantity');
        $this->entry('receipt', 10)->assertSessionHasNoErrors();
        $this->entry('sale', 1.5, value: 100)->assertSessionHasErrors('quantity');
        $this->entry('decrease', 11)->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('stock_transactions', 2);
    }

    public function test_duplicate_submission_does_not_duplicate_stock_or_sales(): void
    {
        $this->entry('opening', 20)->assertSessionHasNoErrors();
        $key = (string) Str::uuid();
        $this->entry('sale', 5, value: 15000, key: $key)->assertSessionHasNoErrors();
        $this->entry('sale', 5, value: 15000, key: $key)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_transactions', 2);
    }

    public function test_invalid_dates_and_missing_sales_value_are_rejected(): void
    {
        $this->entry('opening', 20, '2026-09-10')->assertSessionHasNoErrors();
        $this->entry('receipt', 10, '2026-09-09')->assertSessionHasErrors('occurred_on');
        $this->entry('receipt', 10, '2026-09-25')->assertSessionHasErrors('occurred_on');
        $this->entry('sale', 2, '2026-09-10')->assertSessionHasErrors('sales_value');
        $count = Period::count();
        $this->post(route('transactions.calculate'), ['start_date' => '2026-09-01', 'end_date' => '2026-09-24'])->assertSessionHasErrors('transactions');
        $this->assertSame($count, Period::count());
    }

    public function test_new_transactions_mark_results_stale_without_changing_snapshot(): void
    {
        $this->entry('opening', 20);
        $this->entry('sale', 2, value: 6000);
        $this->post(route('transactions.calculate'), ['start_date' => '2026-09-01', 'end_date' => '2026-09-24'])->assertSessionHasNoErrors();
        $run = Period::latest('id')->first()->runs()->first();
        $this->get(route('calculations.results', $run))->assertOk()->assertViewHas('needsRecalculation', false);
        $this->entry('sale', 3, '2026-09-02', 9000);
        $this->get(route('calculations.results', $run))->assertOk()->assertViewHas('needsRecalculation', true);
        $this->assertEquals(2, $run->results()->first()->raw_values['C2']);
        $this->get(route('transactions.analysis'))->assertOk()->assertSee('Tidak perlu mengetik C1');
    }
}
