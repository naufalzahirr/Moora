<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SpreadsheetFixture;
use Tests\TestCase;

class ImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefixed_xlsx_is_imported_atomically_through_the_http_flow(): void
    {
        $this->seed();
        Storage::fake('local');
        $path = SpreadsheetFixture::xlsx(prefixed: true);
        $file = new UploadedFile($path, 'valid-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        try {
            $response = $this->actingAs(User::where('role', 'staff')->firstOrFail())->post(route('datasets.import'), [
                'name' => 'Impor XLSX QA',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-02',
                'file' => $file,
            ]);

            $period = Period::where('name', 'Impor XLSX QA')->firstOrFail();
            $response->assertRedirect(route('datasets.index', ['period' => $period]));
            $this->assertSame(2, $period->sales()->count());
            $this->assertDatabaseHas('products', ['code' => 'QA-XLSX-001', 'name' => 'Barang Shared String']);
            $this->assertNotNull($period->source_path);
            $this->assertNotNull($period->source_sha256);
            Storage::disk('local')->assertExists($period->source_path);
            $this->actingAs(User::where('role', 'staff')->firstOrFail())
                ->get(route('datasets.source.download', $period))
                ->assertOk()
                ->assertDownload('valid-import.xlsx');
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_numeric_csv_is_rejected_without_creating_a_period_or_products(): void
    {
        $this->seed();
        $periodCount = Period::count();
        $path = tempnam(sys_get_temp_dir(), 'h2-invalid-import-');
        file_put_contents($path, "Kode Barang,Nama Barang,Jumlah Terjual,Nilai Penjualan\nQA-INC-001,Barang Kosong,,1000\nQA-INC-002,Barang Teks,12,bukan-angka\n");

        try {
            $response = $this->actingAs(User::where('role', 'staff')->firstOrFail())->from(route('datasets.index'))->post(route('datasets.import'), [
                'name' => 'Impor Rusak QA',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-02',
                'file' => new UploadedFile($path, 'incomplete-values.csv', 'text/csv', null, true),
            ]);

            $response->assertRedirect(route('datasets.index'))->assertSessionHasErrors('file');
            $this->assertSame($periodCount, Period::count());
            $this->assertDatabaseMissing('products', ['code' => 'QA-INC-001']);
            $this->assertDatabaseMissing('products', ['code' => 'QA-INC-002']);
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_file_type_and_oversized_file_have_indonesian_messages(): void
    {
        $this->seed();
        $staff = User::where('role', 'staff')->firstOrFail();
        $payload = [
            'name' => 'Validasi Berkas',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
        ];

        $this->actingAs($staff)->post(route('datasets.import'), [
            ...$payload,
            'file' => UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
        ])->assertSessionHasErrors(['file' => 'Format berkas harus CSV, XLS, atau XLSX.']);

        $this->actingAs($staff)->post(route('datasets.import'), [
            ...$payload,
            'file' => UploadedFile::fake()->create('besar.csv', 10241, 'text/csv'),
        ])->assertSessionHasErrors(['file' => 'Ukuran berkas laporan maksimal 10 MB.']);
    }

    public function test_import_keeps_an_existing_product_name_and_records_the_source_file(): void
    {
        $this->seed();
        Storage::fake('local');
        $product = Product::where('code', 'GL01')->firstOrFail();
        $originalName = $product->name;
        $path = tempnam(sys_get_temp_dir(), 'h2-name-mismatch-');
        file_put_contents($path, "Kode Barang,Nama Barang,Jumlah Terjual,Nilai Penjualan\nGL01,Nama Berbeda Dari Master,12,120000\n");

        try {
            $response = $this->actingAs(User::where('role', 'staff')->firstOrFail())->post(route('datasets.import'), [
                'name' => 'Impor Nama Berbeda',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-02',
                'file' => new UploadedFile($path, 'nama-berbeda.csv', 'text/csv', null, true),
            ]);

            $response->assertSessionHas('success');
            $this->assertSame($originalName, $product->fresh()->name);
            $period = Period::where('name', 'Impor Nama Berbeda')->firstOrFail();
            Storage::disk('local')->assertExists($period->source_path);
        } finally {
            @unlink($path);
        }
    }

    public function test_import_template_can_be_downloaded(): void
    {
        $this->seed();

        $this->actingAs(User::where('role', 'staff')->firstOrFail())
            ->get(route('datasets.template.download'))
            ->assertOk()
            ->assertDownload('template-impor-penjualan.csv')
            ->assertSee('kode_barang,nama_barang,jumlah_terjual,nilai_penjualan');
    }
}
