<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutomaticCriteriaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_data_populates_all_criteria_and_calculates_without_manual_input(): void
    {
        $this->seed();
        Storage::fake('local');
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
        $csv = $this->get(route('datasets.template.download'))->assertOk()->getContent();
        $this->post(route('datasets.import'), [
            'month' => '2026-09',
            'file' => UploadedFile::fake()->createWithContent('data.csv', $csv),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $period = Period::latest('id')->firstOrFail();
        $this->assertSame('ready', $period->status);
        $this->assertSame(20.0, (float) $period->stockMovements()->first()->ending_stock);
        $this->post(route('calculations.store'), ['period_id' => $period->id])->assertSessionHasNoErrors()->assertRedirect();
        $result = MooraRun::latest('id')->first()->results()->first();
        $this->assertEquals(['C1' => 20, 'C2' => 100, 'C3' => 300000], $result->raw_values);
    }

    public function test_blank_stock_stays_incomplete_and_zero_stock_is_preserved(): void
    {
        $this->seed();
        Storage::fake('local');
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
        $this->post(route('datasets.import'), [
            'month' => '2026-09',
            'file' => UploadedFile::fake()->createWithContent('data.csv', "kode_barang,nama_barang,stok_akhir,jumlah_terjual,nilai_penjualan\nNEW-1,Barang Satu,,10,10000\nNEW-2,Barang Dua,0,10,10000\n"),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $period = Period::latest('id')->firstOrFail();
        $this->assertSame('draft', $period->status);
        $this->assertSame(1, $period->stockMovements()->count());
        $this->assertSame(0.0, (float) $period->stockMovements()->first()->ending_stock);
        $this->post(route('calculations.store'), ['period_id' => $period->id])->assertSessionHasErrors('dataset');
    }

    public function test_invalid_stock_rejects_entire_import(): void
    {
        $this->seed();
        Storage::fake('local');
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
        $count = Period::count();
        $this->post(route('datasets.import'), [
            'month' => '2026-09',
            'file' => UploadedFile::fake()->createWithContent('data.csv', "kode_barang,nama_barang,stok_akhir,jumlah_terjual,nilai_penjualan\nNEW-1,Barang Satu,20,10,10000\nNEW-2,Barang Dua,-1,10,10000\n"),
        ])->assertSessionHasErrors('file');
        $this->assertSame($count, Period::count());
        $this->assertDatabaseMissing('products', ['code' => 'NEW-1']);
    }
}
