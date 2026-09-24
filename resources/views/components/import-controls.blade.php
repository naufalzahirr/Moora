@props(['kind'])
<div class="heading-actions" style="margin:12px 0">
<button class="button" type="button" data-dialog-open="bulk-import">Impor Excel / CSV</button>
<a class="button" href="{{ route('bulk.template', $kind) }}">Unduh Format CSV</a>
</div>
@push('dialogs')
<dialog id="bulk-import" aria-labelledby="bulk-import-title"><div class="dialog-header"><h2 id="bulk-import-title">Impor {{ $kind === 'products' ? 'Data Barang' : 'Transaksi Barang' }}</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup">×</button></div>
<form method="POST" enctype="multipart/form-data" action="{{ route('bulk.store', $kind) }}">@csrf<input type="hidden" name="_form" value="bulk-import"><div class="dialog-body">
<p>Unduh format di sebelah tombol impor. Buka di Excel, isi data, lalu simpan sebagai CSV, XLS, atau XLSX. Hapus baris contoh sebelum memasukkan data sendiri.</p>
@if($kind === 'transactions')<p>Gunakan satu referensi unik untuk setiap baris transaksi. Tanggal: YYYY-MM-DD. Angka tanpa Rp atau pemisah ribuan (contoh: 15000). Urutkan tanggal dari paling lama; stok awal harus mendahului penjualan. Mengimpor referensi yang sama tidak menggandakan transaksi.</p>@else<p>Kode barang harus unik. Barang yang sudah sama dilewati; impor tidak menimpa nama atau satuan barang lama.</p>@endif
<p>Jika satu baris tidak valid, seluruh impor dibatalkan. Maksimal 10 MB dan 10.000 baris termasuk judul.</p>
<label class="field">BERKAS<input type="file" name="file" accept=".csv,.xls,.xlsx" required></label><div class="form-footer"><button class="button primary" type="submit">Impor Data</button></div></div></form></dialog>
@endpush
