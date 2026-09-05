<?php

namespace Tests\Unit;

use App\Services\SalesImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\SpreadsheetFixture;
use Tests\TestCase;

class SalesImportServiceTest extends TestCase
{
    public function test_it_reads_the_actual_report_column_aliases_from_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'h2-sales-');
        file_put_contents($path, "No. Barang;Deskripsi Barang;Kts. Standar;Nilai barang\nGL01;Gula Pasir 1kg;484;Rp7.159.600\n");

        try {
            $file = new UploadedFile($path, 'Ringkasan Penjualan per Barang.csv', 'text/csv', null, true);
            $rows = app(SalesImportService::class)->parse($file);

            $this->assertSame('GL01', $rows[0]['code']);
            $this->assertSame('Gula Pasir 1kg', $rows[0]['name']);
            $this->assertSame(484.0, $rows[0]['sold_quantity']);
            $this->assertSame(7159600.0, $rows[0]['sales_value']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_reads_prefixed_xlsx_with_shared_and_inline_strings(): void
    {
        $path = SpreadsheetFixture::xlsx(prefixed: true);

        try {
            $file = new UploadedFile($path, 'valid-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $rows = app(SalesImportService::class)->parse($file);

            $this->assertCount(2, $rows);
            $this->assertSame([
                'code' => 'QA-XLSX-001',
                'name' => 'Barang Shared String',
                'sold_quantity' => 25.0,
                'sales_value' => 125000.0,
            ], $rows[0]);
            $this->assertSame('Barang Inline String', $rows[1]['name']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_reads_xlsx_with_a_default_namespace(): void
    {
        $path = SpreadsheetFixture::xlsx(prefixed: false);

        try {
            $file = new UploadedFile($path, 'default-namespace.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->assertCount(2, app(SalesImportService::class)->parse($file));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_reads_spreadsheet_xml_xls(): void
    {
        $path = SpreadsheetFixture::spreadsheetXml();

        try {
            $file = new UploadedFile($path, 'report.xls', 'application/vnd.ms-excel', null, true);
            $rows = app(SalesImportService::class)->parse($file);
            $this->assertSame('QA-XLS-001', $rows[0]['code']);
            $this->assertSame(40000.0, $rows[0]['sales_value']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_reads_legacy_binary_xls(): void
    {
        $path = SpreadsheetFixture::binaryXls();

        try {
            $rows = app(SalesImportService::class)->parse(
                new UploadedFile($path, 'legacy-binary.xls', 'application/vnd.ms-excel', null, true)
            );

            $this->assertSame('QA-BINARY-XLS-001', $rows[0]['code']);
            $this->assertSame('Barang XLS Biner', $rows[0]['name']);
            $this->assertSame(54000.0, $rows[0]['sales_value']);
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_or_corrupt_xlsx_is_returned_as_a_validation_error(): void
    {
        $empty = SpreadsheetFixture::xlsx(emptySheet: true);
        $corrupt = tempnam(sys_get_temp_dir(), 'h2-corrupt-');
        file_put_contents($corrupt, 'not a zip archive');

        try {
            foreach ([[$empty, 'empty.xlsx'], [$corrupt, 'corrupt.xlsx']] as [$path, $name]) {
                try {
                    app(SalesImportService::class)->parse(new UploadedFile($path, $name, null, null, true));
                    $this->fail("{$name} seharusnya ditolak.");
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('file', $exception->errors());
                }
            }
        } finally {
            @unlink($empty);
            @unlink($corrupt);
        }
    }

    public function test_it_rejects_empty_and_non_numeric_csv_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'h2-sales-invalid-');
        file_put_contents($path, "Kode Barang,Nama Barang,Jumlah Terjual,Nilai Penjualan\nQA-1,Barang Kosong,,1000\nQA-2,Barang Teks,12,bukan-angka\n");

        try {
            $file = new UploadedFile($path, 'incomplete-values.csv', 'text/csv', null, true);

            app(SalesImportService::class)->parse($file);
            $this->fail('CSV dengan angka kosong atau teks seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $messages = implode(' ', $exception->errors()['file']);
            $this->assertStringContainsString('Baris 2: jumlah terjual wajib diisi.', $messages);
            $this->assertStringContainsString("Baris 3: nilai penjualan 'bukan-angka' bukan angka yang valid.", $messages);
        } finally {
            @unlink($path);
        }
    }
}
