<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockTransaction;
use App\Services\ActivityLogger;
use App\Services\SalesImportService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkImportController extends Controller
{
    public function template(string $kind)
    {
        abort_unless(in_array($kind, ['products', 'transactions']), 404);
        $csv = $kind === 'products'
            ? "kode_barang,nama_barang,satuan\nCONTOH-001,Contoh Barang,pcs\n"
            : "referensi,kode_barang,tanggal,jenis,jumlah,total_penjualan,catatan\nCONTOH-AWAL-001,CONTOH-001,2026-09-01,stok_awal,20,0,Contoh stok awal\nCONTOH-JUAL-001,CONTOH-001,2026-09-02,penjualan,2,6000,Contoh penjualan\nCONTOH-MASUK-001,CONTOH-001,2026-09-03,barang_masuk,10,0,Contoh barang masuk\n";

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="format-'.$kind.'.csv"']);
    }

    public function store(Request $request, string $kind, SalesImportService $reader, TransactionService $service, ActivityLogger $logger)
    {
        abort_unless(in_array($kind, ['products', 'transactions']), 404);
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xls,xlsx']]);
        $rows = $reader->readRows($request->file('file'));
        $headers = array_map(fn ($cell) => strtolower(trim(ltrim((string) $cell, "\xEF\xBB\xBF"))), array_shift($rows) ?? []);
        $required = $kind === 'products' ? ['kode_barang', 'nama_barang', 'satuan'] : ['referensi', 'kode_barang', 'tanggal', 'jenis', 'jumlah', 'total_penjualan', 'catatan'];
        if (count($headers) !== count(array_unique($headers)) || array_diff($required, $headers)) {
            throw ValidationException::withMessages(['file' => 'Kolom tidak sesuai. Unduh format dan gunakan judul kolomnya tanpa perubahan.']);
        }
        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($rows, $headers, $kind, $service, $request, &$created, &$skipped): void {
            // The same lock order as transaction analysis prevents partial stock changes.
            Product::orderBy('id')->lockForUpdate()->get();
            foreach ($rows as $index => $cells) {
                if (collect($cells)->every(fn ($value) => trim((string) $value) === '')) {
                    continue;
                }
                $row = [];
                foreach ($headers as $column => $header) {
                    $row[$header] = trim((string) ($cells[$column] ?? ''));
                }
                try {
                    if ($kind === 'products') {
                        validator($row, ['kode_barang' => 'required|string|max:80', 'nama_barang' => 'required|string|max:255', 'satuan' => 'required|string|max:30'])->validate();
                        $product = Product::where('code', $row['kode_barang'])->first();
                        if ($product) {
                            if ($product->name !== $row['nama_barang'] || $product->unit !== $row['satuan']) {
                                throw ValidationException::withMessages(['file' => 'Kode '.$row['kode_barang'].' sudah ada dengan nama/satuan berbeda. Gunakan Ubah Barang untuk koreksi.']);
                            }
                            $skipped++;

                            continue;
                        }
                        Product::create(['code' => $row['kode_barang'], 'name' => $row['nama_barang'], 'unit' => $row['satuan'], 'active' => true]);
                    } else {
                        validator($row, [
                            'referensi' => 'required|string|max:100', 'kode_barang' => 'required|string|max:80',
                            'tanggal' => 'required|date_format:Y-m-d|before_or_equal:today',
                            'jenis' => 'required|in:stok_awal,penjualan,barang_masuk,koreksi_tambah,koreksi_kurang',
                            'jumlah' => ['required', 'numeric', 'decimal:0,2', 'max:999999999999', $row['jenis'] === 'stok_awal' ? 'min:0' : 'gt:0'],
                            'total_penjualan' => [$row['jenis'] === 'penjualan' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
                            'catatan' => 'nullable|string|max:350',
                        ])->validate();
                        $product = Product::where('code', $row['kode_barang'])->first();
                        if (! $product) {
                            throw ValidationException::withMessages(['file' => 'Kode barang '.$row['kode_barang'].' belum tersedia. Impor Data Barang dahulu.']);
                        }
                        $hash = md5('transaction-import-v1/'.$row['referensi']);
                        $key = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20);
                        $type = ['stok_awal' => 'opening', 'penjualan' => 'sale', 'barang_masuk' => 'receipt', 'koreksi_tambah' => 'increase', 'koreksi_kurang' => 'decrease'][$row['jenis']];
                        $value = $type === 'sale' ? (float) $row['total_penjualan'] : 0;
                        $existing = StockTransaction::where('submission_key', $key)->first();
                        if ($existing) {
                            if ($existing->product_id !== $product->id || $existing->type !== $type || $existing->occurred_on->toDateString() !== $row['tanggal'] || (float) $existing->quantity !== (float) $row['jumlah'] || (float) $existing->sales_value !== (float) $value) {
                                throw ValidationException::withMessages(['file' => 'Referensi '.$row['referensi'].' sudah digunakan dengan data berbeda.']);
                            }
                            $skipped++;

                            continue;
                        }
                        $service->record(['submission_key' => $key, 'product_id' => $product->id, 'type' => $type,
                            'occurred_on' => $row['tanggal'], 'quantity' => $row['jumlah'], 'sales_value' => $value,
                            'notes' => 'Impor '.$row['referensi'].' · '.$row['catatan']], $request->user());
                    }
                    $created++;
                } catch (ValidationException $error) {
                    throw ValidationException::withMessages(['file' => 'Baris '.($index + 2).': '.collect($error->errors())->flatten()->join(' ')]);
                }
            }
            if ($created + $skipped === 0) {
                throw ValidationException::withMessages(['file' => 'Berkas tidak memiliki data.']);
            }
        });
        $logger->log($request->user(), 'bulk.imported', 'Impor '.$kind, ['created' => $created, 'skipped' => $skipped]);

        return redirect()->route($kind === 'products' ? 'products.index' : 'transactions.index')->with('success', $created.' data ditambahkan; '.$skipped.' data yang sudah sama dilewati.');
    }
}
