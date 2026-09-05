@extends('layouts.app')

@section('title', 'Stok Berjalan')
@section('breadcrumb', 'Stok Berjalan')

@section('content')
@php($lowStockCount = $allProducts->filter(fn ($product) => (float) $product->minimum_stock > 0 && (float) ($allSummary[$product->id]['on_hand'] ?? 0) < (float) $product->minimum_stock)->count())
@php($minimumStockUnconfiguredCount = $allProducts->filter(fn ($product) => (float) $product->minimum_stock <= 0)->count())
@php($incomingCount = $allProducts->filter(fn ($product) => (float) ($allSummary[$product->id]['incoming'] ?? 0) > 0)->count())
<div class="page-heading">
    <div>
        <h1>Stok Berjalan</h1>
        <p>Saldo ini berubah ketika barang diterima, stok opname dicatat, dan data operasional baru diselesaikan.</p>
    </div>
    <div class="heading-actions"><button class="button primary" type="button" data-dialog-open="stock-adjustment" aria-controls="stock-adjustment" aria-expanded="false">Catat Stok Opname</button></div>
</div>

<div class="stats-grid action-stats">
    <div class="stat-card"><span class="stat-icon">{{ $allProducts->count() }}</span><div><small>BARANG AKTIF</small><strong>{{ $allProducts->count() }} Barang</strong></div></div>
    <a class="stat-card actionable" href="{{ route('inventory.index', ['stock' => 'low']) }}"><div><small>DI BAWAH MINIMUM</small><strong>{{ $lowStockCount }} Barang</strong></div></a>
    <div class="stat-card"><span class="stat-icon blue">{{ $incomingCount }}</span><div><small>BARANG DALAM JALAN</small><strong>{{ $incomingCount }} Barang</strong></div></div>
    <div class="stat-card"><span class="stat-icon green">{{ $movements->total() }}</span><div><small>MUTASI DITEMUKAN</small><strong>{{ $movements->total() }} Catatan</strong></div></div>
</div>

@if($minimumStockUnconfiguredCount)
<div class="notice info"><strong>{{ $minimumStockUnconfiguredCount }} barang belum memiliki stok minimum.</strong><a href="{{ route('products.index', ['setup' => 'minimum']) }}">Lengkapi pengaturan stok minimum →</a></div>
@endif
@if(request('stock') === 'low')<p class="context-caption">Menampilkan stok di bawah minimum. <a href="{{ route('inventory.index') }}">Lihat semua barang</a></p>@endif
<section class="card">
    <div class="card-header"><div><h2>Posisi Persediaan</h2><small>Stok tersedia ditambah barang dari pesanan pembelian yang telah disetujui atau dikirim.</small></div><a class="button small" href="{{ route('purchase-orders.index') }}">Lihat Pesanan</a></div>
    <div class="card-body">
        <form method="GET" class="filter-row" style="margin-bottom:16px">
            @foreach(request()->except(['q', 'products_page']) as $key => $value)
                @if(! is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="sr-only" for="inventory-search">Cari barang pada stok berjalan</label><input id="inventory-search" type="search" name="q" value="{{ $search }}" placeholder="Cari kode atau nama barang">
            <button class="button small" type="submit">Cari</button>@if($search !== '')<a class="button small" href="{{ route('inventory.index', request()->except(['q', 'products_page'])) }}">Reset</a>@endif
        </form>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Barang</th><th>Supplier</th><th class="numeric">Stok Tersedia</th><th class="numeric">Dalam Perjalanan</th><th class="numeric">Stok Proyeksi</th><th class="numeric">Stok Minimum</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($products as $product)
                    @php($item = $summary[$product->id] ?? ['on_hand' => 0, 'incoming' => 0, 'projected' => 0])
                    @php($minimumConfigured = (float) $product->minimum_stock > 0)
                    @php($belowMinimum = $minimumConfigured && (float) $item['on_hand'] < (float) $product->minimum_stock)
                    <tr>
                        <td><strong>{{ $product->name }}</strong><br><small>{{ $product->code }} · {{ $product->unit }}</small></td>
                        <td>{{ $product->supplier?->name ?? 'Belum ditetapkan' }}</td>
                        <td class="numeric"><strong>{{ $product->formatQuantity($item['on_hand']) }}</strong></td>
                        <td class="numeric">{{ $product->formatQuantity($item['incoming']) }}</td>
                        <td class="numeric">{{ $product->formatQuantity($item['projected']) }}</td>
                        <td class="numeric">{{ $product->formatQuantity($product->minimum_stock) }}</td>
                        <td><x-pill :tone="$minimumConfigured ? ($belowMinimum ? 'red' : 'green') : 'gray'">{{ ! $minimumConfigured ? 'Atur minimum' : ($belowMinimum ? 'Perlu perhatian' : 'Aman') }}</x-pill></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-cell">Tidak ada barang yang sesuai dengan filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="table-scroll-hint" aria-hidden="true">Geser tabel ke kanan untuk melihat seluruh posisi stok.</p>
        @if($products->hasPages())<nav class="pagination" aria-label="Navigasi posisi persediaan">@if($products->onFirstPage())<span>‹</span>@else<a href="{{ $products->previousPageUrl() }}">‹</a>@endif<span class="active">{{ $products->currentPage() }}</span><span>dari {{ $products->lastPage() }}</span>@if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}">›</a>@else<span>›</span>@endif</nav>@endif
    </div>
</section>

<section class="card" style="margin-top:18px">
    <div class="card-header"><h2>Mutasi Stok</h2><small>Setiap perubahan stok dapat ditelusuri kembali ke sumbernya.</small></div>
    <div class="card-body">
        <form method="GET" class="filter-row" style="margin-bottom:16px">
            @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
            <label class="sr-only" for="movement-product">Filter barang mutasi</label><select id="movement-product" name="movement_product"><option value="">Semua barang</option>@foreach($allProducts as $product)<option value="{{ $product->id }}" @selected($movementProduct === $product->id)>{{ $product->name }}</option>@endforeach</select>
            <label class="sr-only" for="movement-type">Filter jenis mutasi</label><select id="movement-type" name="movement_type"><option value="">Semua jenis</option><option value="opening_balance" @selected($movementType === 'opening_balance')>Saldo awal</option><option value="sale" @selected($movementType === 'sale')>Penjualan</option><option value="receipt" @selected($movementType === 'receipt')>Penerimaan</option><option value="adjustment" @selected($movementType === 'adjustment')>Penyesuaian</option></select>
            <label class="filter-label" for="movement-from">Dari tanggal</label><input id="movement-from" type="date" name="movement_from" value="{{ $movementFrom }}">
            <label class="filter-label" for="movement-until">Sampai tanggal</label><input id="movement-until" type="date" name="movement_until" value="{{ $movementUntil }}">
            <button class="button small" type="submit">Terapkan</button>@if(request()->hasAny(['movement_product', 'movement_type', 'movement_from', 'movement_until']))<a class="button small" href="{{ route('inventory.index', $search !== '' ? ['q' => $search] : []) }}">Reset</a>@endif
        </form>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th class="numeric">Perubahan</th><th>Referensi</th><th>Dicatat oleh</th></tr></thead>
                <tbody>
                @forelse($movements as $movement)
                    @php($label = match($movement->movement_type) { 'opening_balance' => 'Saldo awal', 'sale' => 'Penjualan', 'receipt' => 'Penerimaan', default => 'Penyesuaian' })
                    <tr>
                        <td>{{ $movement->occurred_on->translatedFormat('d M Y') }}</td>
                        <td><strong>{{ $movement->product->name }}</strong></td>
                        <td>{{ $label }}</td>
                        <td class="numeric {{ (float) $movement->quantity_change >= 0 ? 'text-positive' : 'text-negative' }}"><strong>{{ (float) $movement->quantity_change >= 0 ? '+' : '' }}{{ $movement->product->formatQuantity($movement->quantity_change) }}</strong></td>
                        <td>{{ $movement->reference ?? '—' }}</td>
                        <td>{{ $movement->recorder?->name ?? 'Sistem' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-cell">Belum ada mutasi stok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())<nav class="pagination" aria-label="Navigasi mutasi stok">@if($movements->onFirstPage())<span>‹</span>@else<a href="{{ $movements->previousPageUrl() }}">‹</a>@endif<span class="active">{{ $movements->currentPage() }}</span><span>dari {{ $movements->lastPage() }}</span>@if($movements->hasMorePages())<a href="{{ $movements->nextPageUrl() }}">›</a>@else<span>›</span>@endif</nav>@endif
    </div>
</section>
@endsection

@push('dialogs')
<dialog id="stock-adjustment" aria-labelledby="stock-adjustment-title">
    <div class="dialog-header"><h2 id="stock-adjustment-title">Catat Stok Opname</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog stok opname">×</button></div>
    <form method="POST" action="{{ route('inventory.adjust') }}" data-unsaved-form novalidate>@csrf<input type="hidden" name="_form" value="stock-adjustment">
        <div class="dialog-body">
            <div class="notice info"><strong>Gunakan untuk stok fisik aktual.</strong>Sistem hanya mencatat selisih terhadap saldo stok berjalan dan menyimpan jejak auditnya.</div>
            <div class="form-grid">
                <label class="field full">BARANG<select name="product_id" required data-quantity-product-select="#counted-quantity"><option value="">Pilih barang</option>@foreach($allProducts as $product)<option value="{{ $product->id }}" data-quantity-step="{{ $product->quantityStep() }}" @selected(old('product_id') == $product->id)>{{ $product->name }} (stok sistem {{ $product->formatQuantity($allSummary[$product->id]['on_hand'] ?? 0, true) }})</option>@endforeach</select>@error('product_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="field">TANGGAL OPNAME<input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>@error('occurred_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="field">STOK FISIK<input id="counted-quantity" type="number" min="0" step="0.01" name="counted_quantity" value="{{ old('counted_quantity') }}" required>@error('counted_quantity')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="field full">CATATAN <small>(opsional)</small><textarea name="notes" maxlength="1000" placeholder="Contoh: selisih hasil stock opname rak utama">{{ old('notes') }}</textarea>@error('notes')<small class="field-error">{{ $message }}</small>@enderror</label>
            </div>
            <div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan Penyesuaian</button></div>
        </div>
    </form>
</dialog>
@endpush
