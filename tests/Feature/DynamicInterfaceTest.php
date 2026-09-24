<?php

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DynamicInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_guides_the_latest_month_even_before_calculation(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        $period = Period::create([
            'name' => 'Periode Dinamis Tanpa Run',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'draft',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('dashboard', ['period' => $period]))
            ->assertOk()
            ->assertDontSee('Periode Dinamis Tanpa Run')
            ->assertViewHas('period', fn ($current) => $current->id === $period->id)
            ->assertViewHas('run', null)
            ->assertSee('Tambah Transaksi')
            ->assertSee('5 Barang')
            ->assertSee('Hasil Penilaian Terbaru')
            ->assertDontSee('Saran Restock')
            ->assertSee(route('transactions.index'), false);
    }

    public function test_operational_interface_uses_dynamic_data_defaults_without_exposing_the_seed_date_as_dashboard_copy(): void
    {
        Carbon::setTestNow('2030-04-12 10:00:00');
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Catat transaksi harian')
            ->assertDontSee('Ringkasan rekomendasi restock untuk periode')
            ->assertSee('Buka arsip data lama');

        $this->actingAs($owner)->get(route('datasets.index'))
            ->assertOk()
            ->assertSee('Data Bulanan')
            ->assertSee('1. Data Barang')->assertSee('2. Transaksi Barang')->assertSee('3. Penilaian MOORA')->assertSee('4. Pembelian')->assertSee('5. Laporan')
            ->assertSee('value="2030-04"', false)
            ->assertSee('max="2030-04"', false)
            ->assertDontSee('Data uji stok akhir')
            ->assertDontSee('Yi Manual');

        Carbon::setTestNow();
    }

    public function test_criterion_labels_and_navigation_counts_are_database_driven(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();
        Criterion::where('code', 'C1')->update(['name' => 'Persediaan Tersisa']);
        Criterion::where('code', 'C3')->update(['active' => false]);

        $this->actingAs($owner)->get(route('datasets.index'))
            ->assertOk()
            ->assertSee('Persediaan Tersisa')->assertDontSee('C1 · Persediaan Tersisa')
            ->assertSee('aria-label="Persediaan tersisa Gula Pasir 1kg"', false)
            ->assertSee('data-dataset-form', false);

        $this->actingAs($owner)->get(route('criteria.index'))
            ->assertOk()
            ->assertSee('Konfigurasi Kriteria')
            ->assertSee('data-criteria-form', false);
    }

    public function test_operational_data_cannot_be_created_with_future_dates(): void
    {
        Carbon::setTestNow('2030-04-12 10:00:00');
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();

        $this->actingAs($owner)->post(route('datasets.periods.store'), [
            'name' => 'Data Masa Depan',
            'start_date' => '2030-04-12',
            'end_date' => '2030-04-13',
        ])->assertSessionHasErrors('end_date');

        Carbon::setTestNow();
    }

    public function test_product_page_is_a_master_data_page_with_search_controls(): void
    {
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();

        $this->actingAs($owner)->get(route('products.index', ['search' => 'Gula']))
            ->assertOk()
            ->assertDontSee('Pilih data penjualan yang ditampilkan')
            ->assertDontSee('Nilai Kriteria')
            ->assertSee('name="search"', false)
            ->assertSee('1 barang ditemukan')
            ->assertSee('Satuan');
    }
}
