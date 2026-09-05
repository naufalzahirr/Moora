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

    public function test_dashboard_prioritizes_the_latest_recommendation_over_a_draft_period(): void
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
            ->assertSee('Ada data operasional baru yang belum dihitung.')
            ->assertSee('Tindak Lanjut Restock')
            ->assertSee('5 Barang')
            ->assertSee('Prioritas Restock Saat Ini')
            ->assertSee('Saran Restock')
            ->assertSee(route('datasets.index', ['period' => $period]), false);
    }

    public function test_operational_interface_uses_dynamic_data_defaults_without_exposing_the_seed_date_as_dashboard_copy(): void
    {
        Carbon::setTestNow('2030-04-12 10:00:00');
        $this->seed();
        $owner = User::where('role', 'owner')->firstOrFail();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pantau prioritas restock, kelengkapan data, dan rekomendasi terbaru dalam satu tempat.')
            ->assertDontSee('Ringkasan rekomendasi restock untuk periode')
            ->assertDontSee('01 Jun 2026 - 23 Agt 2026');

        $this->actingAs($owner)->get(route('datasets.index'))
            ->assertOk()
            ->assertSee('Data Operasional')
            ->assertSee('Data Penjualan April 2030')
            ->assertSee('value="2030-04-01"', false)
            ->assertSee('value="2030-04-12"', false)
            ->assertSee('max="2030-04-12"', false)
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
            ->assertSee('C1 · Persediaan Tersisa')
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
            ->assertSee('data-debounced-submit', false)
            ->assertSee('1 barang ditemukan')
            ->assertSee('Stok Minimum');
    }
}
