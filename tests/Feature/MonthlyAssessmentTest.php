<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_input_creates_blank_data_and_rejects_duplicate_or_future_months(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $this->seed();
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
        $this->post(route('datasets.periods.store'), ['month' => '2026-09'])->assertRedirect();
        $period = Period::latest('id')->firstOrFail();
        $this->assertSame('2026-09-01', $period->start_date->toDateString());
        $this->assertSame('2026-09-30', $period->end_date->toDateString());
        $this->assertSame(5, $period->sales()->count());
        $this->assertNull($period->sales()->first()->sold_quantity);
        $this->post(route('datasets.periods.store'), ['month' => '2026-09'])->assertSessionHasErrors();
        $this->post(route('datasets.periods.store'), ['month' => '2026-11'])->assertSessionHasErrors('month');
        $this->post(route('datasets.periods.store'), ['month' => '2026-10'])->assertRedirect();
        $this->assertSame('2026-10-15', Period::latest('id')->first()->end_date->toDateString());
    }

    public function test_edit_reuses_working_copy_hides_versions_and_preserves_previous_result(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
        $run = MooraRun::firstOrFail();
        $oldValues = $run->results()->first()->raw_values;
        $this->post(route('datasets.revise', $run->period))->assertRedirect();
        $draft = Period::latest('id')->firstOrFail();
        $draft->update(['status' => 'ready']);
        $this->post(route('datasets.revise', $run->period))->assertRedirect(route('datasets.index', ['period' => $draft]));
        $this->assertDatabaseCount('periods', 2);
        $this->get(route('datasets.index', ['period' => $run->period]))->assertOk()
            ->assertViewHas('period', fn ($period) => $period->id === $draft->id)
            ->assertViewHas('periods', fn ($periods) => $periods->count() === 1)
            ->assertSee('1. Data Barang')->assertSee('2. Transaksi Barang')->assertSee('3. Penilaian MOORA')->assertSee('4. Laporan')->assertDontSee('Pembaruan 1');
        $this->get(route('calculations.results', $run))->assertOk()->assertViewHas('needsRecalculation', true);
        $rows = $draft->sales->mapWithKeys(fn ($sale) => [$sale->product_id => [
            'sold_quantity' => 50, 'sales_value' => 100000, 'ending_stock' => 10,
        ]])->all();
        $this->put(route('datasets.update'), ['period_id' => $draft->id, 'rows' => $rows, 'next' => 'calculate'])->assertRedirect();
        $this->assertSame($oldValues, $run->results()->first()->fresh()->raw_values);
        $newRun = MooraRun::latest('id')->firstOrFail();
        $this->assertNotSame($run->id, $newRun->id);
        $this->get(route('calculations.results', $newRun))->assertOk()
            ->assertViewHas('needsRecalculation', false)
            ->assertViewHas('runs', fn ($runs) => $runs->count() === 1)
            ->assertSee('Nilai MOORA (Yi)')->assertDontSee('Saran Restock');
    }
}
