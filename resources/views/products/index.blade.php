@extends('layouts.app')

@section('title', 'Data Barang')
@section('breadcrumb', 'Data Barang')

@section('content')
<div class="page-heading">
    <div><h1>Data Barang</h1><p>Kelola informasi barang dan pengaturan restock yang digunakan pada proses operasional.</p></div>
    <div class="heading-actions">
        <button class="button primary" type="button" data-dialog-open="add-product" aria-controls="add-product" aria-expanded="false">+ Tambah Barang</button>
    </div>
</div>

<section class="card">
    <div class="card-header">
        <div><h2>Daftar Barang</h2><small>{{ $products->total() }} barang ditemukan</small></div>
        <form class="filter-row" method="GET">
            <label class="sr-only" for="product-search">Cari kode atau nama barang</label>
            <input id="product-search" type="search" name="search" value="{{ request('search') }}" placeholder="Cari kode atau nama barang">

            <button class="button small" type="submit">Cari</button>
            @if(request('search') || request('setup'))<a class="button small" href="{{ route('products.index') }}">Reset</a>@endif
        </form>
    </div>
    <div class="card-body">
        @if($products->count())
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Barang</th><th>Satuan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($products as $product)
                    <tr>
                        <td class="identity-cell"><strong>{{ $product->name }}</strong><small>{{ $product->code }} · {{ $product->unit }}</small><small>{{ $product->category?->name ?? 'Tanpa kategori' }}</small></td>
                        <td>{{ $product->unit }}</td>
                        <td><x-pill :tone="$product->active ? 'green' : 'gray'">{{ $product->active ? 'Aktif' : 'Nonaktif' }}</x-pill></td>
                        <td><div class="actions"><button class="button small" type="button" data-dialog-open="edit-product-{{ $product->id }}" aria-controls="edit-product-{{ $product->id }}" aria-expanded="false">Ubah</button>@if($product->active)<form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Nonaktifkan barang ini? Riwayat perhitungan tetap disimpan.">@csrf @method('DELETE')<button class="button small danger" type="submit">Nonaktifkan</button></form>@endif</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
            <x-empty-state title="Data barang tidak ditemukan" description="Tambahkan barang atau ubah kata pencarian." />
        @endif
        @if($products->hasPages())
            <nav class="pagination">
                @if($products->onFirstPage())<span>‹</span>@else<a href="{{ $products->previousPageUrl() }}">‹</a>@endif
                <span class="active">{{ $products->currentPage() }}</span><span>dari {{ $products->lastPage() }}</span>
                @if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}">›</a>@else<span>›</span>@endif
            </nav>
        @endif
    </div>
</section>
@endsection

@push('dialogs')
<dialog id="add-product" aria-labelledby="add-product-title">
    <div class="dialog-header"><h2 id="add-product-title">Tambah Data Barang</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog tambah barang">×</button></div>
    <form method="POST" action="{{ route('products.store') }}" data-unsaved-form novalidate>@csrf
        <div class="dialog-body">
            @include('products.partials.form', ['product' => null])
            <div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan Barang</button></div>
        </div>
    </form>
</dialog>
@foreach($products as $product)
<dialog id="edit-product-{{ $product->id }}" aria-labelledby="edit-product-title-{{ $product->id }}">
    <div class="dialog-header"><h2 id="edit-product-title-{{ $product->id }}">Ubah Data Barang: {{ $product->name }}</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog ubah {{ $product->name }}">×</button></div>
    <form method="POST" action="{{ route('products.update', $product) }}" data-unsaved-form novalidate>@csrf @method('PUT')
        <div class="dialog-body">
            @include('products.partials.form', ['product' => $product])
            <div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan Perubahan</button></div>
        </div>
    </form>
</dialog>
@endforeach
@endpush
