<?php

namespace Tests\Feature;

use App\Models\MooraRun;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_can_be_imported_and_repeated_without_duplicate_transactions(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'staff')->first());
        foreach (['products', 'transactions'] as $kind) {
            $csv = $this->get(route('bulk.template', $kind))->assertOk()->getContent();
            for ($i = 0; $i < 2; $i++) {
                $this->post(route('bulk.store', $kind), ['file' => UploadedFile::fake()->createWithContent('import.csv', $csv)])->assertSessionHasNoErrors()->assertRedirect();
            }
        }
        $this->assertSame(3, StockTransaction::count());
        $this->assertSame(1, Product::where('code', 'CONTOH-001')->count());
    }

    public function test_invalid_row_rolls_back_products_and_reports_line_number(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'staff')->first());
        $this->post(route('bulk.store', 'products'), ['file' => UploadedFile::fake()->createWithContent('import.csv', "kode_barang,nama_barang,satuan\nNEW,Barang,pcs\nBAD,,pcs\n")])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('products', ['code' => 'NEW']);
    }

    public function test_excel_files_are_supported_for_both_imports(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'staff')->first());
        foreach (['products', 'transactions'] as $kind) {
            $csv = $this->get(route('bulk.template', $kind))->getContent();
            $book = new Spreadsheet;
            $book->getActiveSheet()->fromArray(array_map('str_getcsv', explode("\n", trim($csv))));
            $path = tempnam(sys_get_temp_dir(), 'bulk-xlsx-');
            try {
                (new Xlsx($book))->save($path);
                $this->post(route('bulk.store', $kind), ['file' => new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
                    ->assertSessionHasNoErrors()->assertRedirect();
            } finally {
                @unlink($path);
                $book->disconnectWorksheets();
            }
        }
        $this->assertSame(3, StockTransaction::count());
    }

    public function test_invalid_stock_import_rolls_back_and_conflicting_reference_is_rejected(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'staff')->first());
        $header = "referensi,kode_barang,tanggal,jenis,jumlah,total_penjualan,catatan\n";
        $this->post(route('bulk.store', 'transactions'), ['file' => UploadedFile::fake()->createWithContent('data.csv', $header."REF-1,GL01,2026-09-01,stok_awal,10,0,\nREF-2,GL01,2026-09-02,penjualan,11,30000,\n")])->assertSessionHasErrors('file');
        $this->assertSame(0, StockTransaction::count());
        $this->post(route('bulk.store', 'transactions'), ['file' => UploadedFile::fake()->createWithContent('data.csv', $header."REF-1,GL01,2026-09-01,stok_awal,10,0,\n")])->assertSessionHasNoErrors();
        $this->post(route('bulk.store', 'transactions'), ['file' => UploadedFile::fake()->createWithContent('data.csv', $header."REF-1,GL01,2026-09-01,stok_awal,20,0,\n")])->assertSessionHasErrors('file');
        $this->assertEquals(10, StockTransaction::first()->quantity);
    }

    public function test_purchase_confirmation_is_owner_only(): void
    {
        $this->seed();
        $run = MooraRun::first();
        $result = $run->results()->first();
        $staff = User::where('role', 'staff')->first();
        $this->actingAs($staff)->get(route('purchases.review'))->assertOk()->assertSee('Ajukan Pembelian')->assertDontSee('Konfirmasi Pembelian');
        $url = route('restock-actions.update', [$run, $result]);
        $this->put($url, ['status' => 'approved', 'approved_quantity' => 5])->assertForbidden();
        $this->put($url, ['status' => 'proposed', 'approved_quantity' => 5])->assertSessionHasNoErrors();
        $this->actingAs(User::where('role', 'owner')->first())->get(route('purchases.review'))->assertOk()->assertSee('Konfirmasi Pembelian');
        $this->put($url, ['status' => 'approved', 'approved_quantity' => 5])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restock_actions', ['moora_result_id' => $result->id, 'status' => 'approved']);
        $this->assertSame(0, StockTransaction::count());
    }
}
