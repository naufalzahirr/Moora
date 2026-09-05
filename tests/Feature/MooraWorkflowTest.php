<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MooraWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_every_primary_mockup_page(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        $run = MooraRun::with('results')->firstOrFail();

        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('Dashboard Restock')->assertSee('Prioritas Restock Saat Ini');
        $this->actingAs($owner)->get('/data-barang')->assertOk()->assertSee('Gula Pasir 1kg');
        $this->actingAs($owner)->get('/supplier')->assertOk()->assertSee('Supplier dan Lead Time');
        $this->actingAs($owner)->get('/kriteria-bobot')->assertOk()->assertSee('Stok Akhir');
        $this->actingAs($owner)->get('/data-uji')->assertOk()->assertSee('Ringkasan Penjualan per Barang.xlsx');
        $this->actingAs($owner)->get('/proses-moora')->assertRedirect(route('datasets.index'));
        $this->actingAs($owner)->get(route('calculations.results', $run))->assertOk()->assertSee('Hasil Rekomendasi Restock');
        $this->actingAs($owner)->get(route('calculations.show', [$run, $run->results->first()]))->assertOk()->assertSee('Detail Rekomendasi MOORA');
        $this->actingAs($owner)->get('/stok-berjalan')->assertOk()->assertSee('Stok Berjalan');
        $this->actingAs($owner)->get('/pesanan-pembelian')->assertOk()->assertSee('Belum ada pesanan pembelian');
        $this->actingAs($owner)->get('/laporan')->assertOk()->assertSee('Arsip Laporan')->assertSee('Riwayat Rekomendasi');
        $this->actingAs($owner)->get(route('activity-logs.index'))->assertOk()->assertSee('Log Aktivitas');
    }

    public function test_completed_period_is_locked_and_can_be_copied_as_a_revision(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();
        $period = Period::firstOrFail();

        $this->actingAs($staff)->post('/proses-moora', ['period_id' => $period->id])
            ->assertSessionHasErrors('period');

        $this->assertDatabaseCount('moora_runs', 1);
        $this->assertDatabaseCount('moora_results', 5);

        $this->actingAs($staff)->post(route('datasets.revise', $period))
            ->assertRedirect();

        $revision = Period::where('name', 'Data Penjualan Tercatat (Pembaruan 1)')->firstOrFail();
        $this->assertSame('draft', $revision->status);
        $this->assertSame(5, $revision->sales()->count());
        $this->assertSame(5, $revision->stockMovements()->count());

        $this->actingAs($staff)->post(route('datasets.revise', $period))
            ->assertRedirect(route('datasets.index', ['period' => $revision]));
        $this->assertDatabaseCount('periods', 2);

        $this->actingAs($staff)->delete(route('datasets.revise.discard', $revision))->assertForbidden();
        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner)->delete(route('datasets.revise.discard', $revision))
            ->assertRedirect(route('datasets.index', ['period' => $period]));
        $this->assertDatabaseMissing('periods', ['id' => $revision->id]);
    }

    public function test_operational_data_can_be_saved_and_used_to_create_a_recommendation_in_one_step(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();
        $source = Period::firstOrFail();

        $this->actingAs($staff)->post(route('datasets.revise', $source))->assertRedirect();
        $draft = Period::where('revision_of_id', $source->id)->firstOrFail();
        $draft->load(['sales', 'stockMovements']);
        $stocks = $draft->stockMovements->keyBy('product_id');
        $rows = $draft->sales->mapWithKeys(function ($sale) use ($stocks): array {
            return [$sale->product_id => [
                'sold_quantity' => $sale->sold_quantity,
                'sales_value' => $sale->sales_value,
                'ending_stock' => $stocks->get($sale->product_id)->ending_stock,
                'manual_yi' => $sale->manual_yi,
            ]];
        })->all();

        $response = $this->actingAs($staff)->put(route('datasets.update'), [
            'period_id' => $draft->id,
            'rows' => $rows,
            'next' => 'calculate',
        ]);

        $run = MooraRun::latest('id')->firstOrFail();
        $response->assertRedirect(route('calculations.results', $run))
            ->assertSessionHas('success', 'Data disimpan dan rekomendasi restock berhasil dibuat.');
        $this->assertSame($draft->id, $run->period_id);
        $this->assertDatabaseHas('periods', ['id' => $draft->id, 'status' => 'completed']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'moora.executed']);
    }

    public function test_staff_cannot_change_criteria_configuration(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();

        $this->actingAs($staff)->put('/kriteria-bobot', ['criteria' => []])->assertForbidden();
        $this->actingAs($staff)->get(route('activity-logs.index'))->assertForbidden();
    }

    public function test_results_page_guides_a_new_installation_without_any_recommendation(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get(route('calculations.results'))
            ->assertOk()
            ->assertSee('Belum ada rekomendasi')
            ->assertSee('Lengkapi Data Operasional');
    }
}
