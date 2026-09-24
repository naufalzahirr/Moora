@extends('layouts.app')
@section('title', 'Dashboard Penilaian Restock')
@section('breadcrumb', 'Dashboard')
@section('content')
<div class="page-heading">
    <div><h1>Penilaian Restock</h1><p>Catat transaksi harian. Stok dan penjualan otomatis menjadi bahan penilaian MOORA.</p></div>
    <a class="button primary" href="{{ route('transactions.index') }}">Tambah Transaksi</a>
</div>
<x-workflow :step="$run ? 2 : 1" :period="$period" :run="$run" />
<div class="stats-grid">
<a class="stat-card actionable" href="{{ route('products.index') }}"><div><small>BARANG AKTIF</small><strong>{{ $productCount }} Barang</strong><span>Kelola daftar barang →</span></div></a>
<a class="stat-card actionable" href="{{ route('transactions.index') }}"><div><small>STOK AWAL TERCATAT</small><strong>{{ $openingCount }} / {{ $productCount }}</strong><span>Lengkapi sekali untuk setiap barang →</span></div></a>
<a class="stat-card actionable" href="{{ route('transactions.index') }}"><div><small>TRANSAKSI TERSIMPAN</small><strong>{{ $transactionCount }}</strong><span>Tambah penjualan atau barang masuk →</span></div></a>
</div>
<div class="notice info"><strong>Ikuti urutan menu di sidebar.</strong>1. Daftarkan barang. 2. Catat stok awal sekali, lalu tambahkan transaksi. 3. Pilih rentang tanggal dan hitung MOORA. 4. Unduh laporan. Tidak perlu membuat rekap manual. <a href="{{ route('datasets.index') }}">Buka arsip data lama</a>.</div>
<section class="card">
    <div class="card-header"><h2>Hasil Penilaian Terbaru</h2>@if($run)<a class="button small" href="{{ route('calculations.results', $run) }}">Lihat Semua Hasil</a>@endif</div>
    @if($run)<div class="card-body"><div class="table-wrap"><table><thead><tr><th>Ranking</th><th>Barang</th><th class="numeric">Nilai MOORA (Yi)</th></tr></thead><tbody>
    @foreach($results as $result)<tr><td>{{ $result->rank_system }}</td><td>{{ $result->displayProductName() }}</td><td class="numeric">{{ number_format((float) $result->yi_system, 6, ',', '.') }}</td></tr>@endforeach
    </tbody></table></div><p class="context-caption">Ranking menjadi bahan pertimbangan. Keputusan restock tetap ditentukan pemilik toko.</p></div>
    @else<x-empty-state title="Belum ada hasil untuk data ini" description="Catat transaksi lalu buka menu Penilaian MOORA." />@endif
</section>
@endsection
