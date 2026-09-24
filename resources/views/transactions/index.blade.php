@extends('layouts.app')
@section('title', 'Transaksi Barang')
@section('breadcrumb', 'Transaksi Barang')
@section('content')
<div class="page-heading"><div><h1>Transaksi Barang</h1><p>Tambahkan setiap penjualan atau barang masuk. Stok otomatis berubah, tanpa mengisi ulang rekap.</p></div><a class="button primary" href="{{ route('transactions.analysis') }}">Lanjut ke Penilaian MOORA →</a></div>
<div class="notice info"><strong>Mulai sekali, lalu lanjutkan setiap hari.</strong>Catat stok awal setiap barang pada awal hari mulai pencatatan (boleh 0). Setelah itu gunakan Penjualan atau Barang masuk. Data rekap lama tetap tersedia di <a href="{{ route('datasets.index') }}">arsip data lama</a>; stok transaksi dimulai dari stok awal yang Anda catat di sini.</div>
<section class="card"><div class="card-header"><h2>Tambah Transaksi</h2></div><div class="card-body">
@if($products->isEmpty())<p>Tambahkan barang terlebih dahulu di <a href="{{ route('products.index') }}">1. Data Barang</a>.</p>@else
<form method="POST" action="{{ route('transactions.store') }}" data-unsaved-form>@csrf
<input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Illuminate\Support\Str::uuid()) }}">
<div class="form-grid">
<label class="field">BARANG<select name="product_id" required><option value="">Pilih barang</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} · {{ $balances->has($product->id) ? 'stok '.$product->formatQuantity($balances[$product->id], true) : 'belum ada stok awal' }}</option>@endforeach</select></label>
<label class="field">JENIS TRANSAKSI<select name="type" required>@foreach(['opening' => 'Stok awal (sekali per barang)', 'sale' => 'Penjualan', 'receipt' => 'Barang masuk', 'increase' => 'Koreksi stok bertambah', 'decrease' => 'Koreksi stok berkurang'] as $value => $label)<option value="{{ $value }}" @selected(old('type', 'sale') === $value)>{{ $label }}</option>@endforeach</select></label>
<label class="field">TANGGAL<input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required><small>Catat berurutan sesuai tanggal untuk setiap barang.</small></label>
<label class="field">JUMLAH<input type="number" name="quantity" min="0" step="0.01" value="{{ old('quantity') }}" required><small>Gunakan satuan barang. Isi jumlah yang masuk atau keluar, bukan stok akhir.</small></label>
<label class="field">TOTAL PENJUALAN (RP)<input type="number" name="sales_value" min="0" step="0.01" value="{{ old('sales_value') }}"><small>Wajib untuk Penjualan. Contoh 5 pcs × Rp3.000 = isi 15000. Jenis lainnya tidak memakai nilai ini.</small></label>
<label class="field">CATATAN<input name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Nomor nota atau alasan koreksi"></label>
</div><div class="form-footer"><button class="button primary" type="submit">Tambah Transaksi</button></div>
</form>@endif
</div></section>
<section class="card" style="margin-top:20px"><div class="card-header"><h2>Riwayat Transaksi</h2><small>{{ $transactions->total() }} transaksi</small></div><div class="card-body"><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th class="numeric">Jumlah</th><th class="numeric">Nilai Penjualan</th><th>Catatan</th></tr></thead><tbody>
@forelse($transactions as $entry)<tr><td>{{ $entry->occurred_on->format('d-m-Y') }}</td><td>{{ $entry->product->name }}</td><td>{{ $entry->label() }}</td><td class="numeric">{{ in_array($entry->type, ['sale','decrease']) ? '−' : '+' }}{{ $entry->product->formatQuantity($entry->quantity, true) }}</td><td class="numeric">{{ $entry->type === 'sale' ? 'Rp'.number_format((float) $entry->sales_value, 0, ',', '.') : '—' }}</td><td>{{ $entry->notes ?? '—' }}</td></tr>@empty<tr><td colspan="6">Belum ada transaksi. Mulai dengan mencatat stok awal.</td></tr>@endforelse
</tbody></table></div><x-pagination :paginator="$transactions" /></div></section>
@endsection
