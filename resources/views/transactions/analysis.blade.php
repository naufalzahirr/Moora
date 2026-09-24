@extends('layouts.app')
@section('title', 'Penilaian MOORA')
@section('breadcrumb', 'Penilaian MOORA')
@section('content')
<div class="page-heading"><div><h1>Penilaian MOORA</h1><p>Pilih rentang analisis. Stok akhir, jumlah terjual, dan nilai penjualan diambil otomatis dari transaksi.</p></div><a class="button" href="{{ route('calculations.results') }}">Riwayat Hasil</a></div>
<section class="card pad"><h2>Rentang Analisis</h2><form method="POST" action="{{ route('transactions.calculate') }}" data-date-range>@csrf
<div class="form-grid"><label class="field">TANGGAL AWAL<input type="date" name="start_date" value="{{ old('start_date', now()->startOfMonth()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label><label class="field">TANGGAL AKHIR<input type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label></div>
<div class="notice info"><strong>Tidak perlu mengetik C1–C3.</strong>Stok akhir dihitung dari seluruh mutasi sampai tanggal akhir. Jumlah terjual dan nilai penjualan hanya dijumlahkan selama rentang yang dipilih. Barang aktif yang sudah memiliki stok awal pada atau sebelum tanggal awal dianalisis. Barang baru yang belum memenuhi rentang tersebut dilewati dan disebutkan pada hasil; tidak menghalangi barang lainnya.</div>
<div class="form-footer"><a class="button" href="{{ route('transactions.index') }}">Tambah Transaksi</a><button class="button primary" type="submit">Hitung MOORA dari Transaksi</button></div></form></section>
@endsection
