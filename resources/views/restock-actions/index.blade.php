@extends('layouts.app')
@section('title', 'Tindak Lanjut Restock')
@section('breadcrumb', 'Pembelian')

@section('content')
@php($isOwner = auth()->user()->isOwner())
<div class="page-heading">
    <div><h1>Tindak Lanjut Restock</h1><p>{{ $isOwner ? 'Tinjau jumlah dan setujui barang yang akan dipesan.' : 'Sesuaikan jumlah, lalu kirim usulan untuk ditinjau Owner.' }}</p></div>
    @if($run)<a class="button" href="{{ route('purchase-orders.index', ['run' => $run]) }}">Lihat Pesanan</a>@endif
</div>
<x-workflow :step="3" :run="$run" />
@if($run)
<form class="filter-row page-filter" method="GET">
    <label class="field context-switcher">Rekomendasi<select name="run" data-auto-submit>@foreach($runs as $item)<option value="{{ $item->id }}" @selected($run->id === $item->id)>{{ $item->period->displayName() }} · {{ $item->created_at->translatedFormat('d M Y H:i') }}</option>@endforeach</select></label>
</form>
@php($freshness = $run->period->freshness())
<div class="context-summary {{ $freshness['is_stale'] ? 'is-stale' : '' }}">
    <strong>{{ $run->period->displayName() }}</strong><span>Data sampai {{ $run->period->end_date->translatedFormat('d M Y') }} · {{ $freshness['days_old'] }} hari lalu</span>
    @if($freshness['is_stale'])<span>Periksa kembali kebutuhan dan stok sebelum menyetujui.</span>@endif
</div>
<div class="status-summary" aria-label="Ringkasan tindak lanjut">
    <span><b>{{ $statusCounts->get('pending', 0) }}</b> Belum ditinjau</span>
    <span><b>{{ $statusCounts->get('proposed', 0) }}</b> Menunggu persetujuan</span>
    <span><b>{{ $statusCounts->get('approved', 0) }}</b> Disetujui</span>
    <span><b>{{ $statusCounts->get('ordered', 0) }}</b> Dipesan</span>
    <span><b>{{ $statusCounts->get('received', 0) }}</b> Diterima</span>
</div>
@if($isOwner && $purchaseGroups->isNotEmpty())
<section class="card purchase-ready">
    <div><h2>Siap dibuatkan pesanan</h2><p>{{ $purchaseGroups->flatten(1)->count() }} barang · {{ $purchaseGroups->except('missing')->count() }} supplier @if($purchaseGroups->has('missing')) · {{ $purchaseGroups->get('missing')->count() }} barang belum memiliki supplier @endif</p></div>
    <button class="button primary" type="button" data-dialog-open="prepare-orders">Buat Draft Pesanan</button>
</section>
@endif
<section class="card">
    <div class="card-header"><div><h2>Daftar Tindak Lanjut</h2><small>{{ $results->total() }} barang ditemukan. Perubahan disimpan untuk barang yang dipilih.</small></div></div>
    <div class="card-body">
        <form method="GET" class="filter-row page-filter">
            <input type="hidden" name="run" value="{{ $run->id }}">
            <label class="field">Cari barang<input type="search" name="q" value="{{ $search }}" placeholder="Kode atau nama barang"></label>
            <label class="field">Status<select name="status"><option value="">Semua status</option>@foreach(['pending' => 'Belum ditinjau', 'proposed' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'ordered' => 'Dipesan', 'received' => 'Diterima', 'skipped' => 'Tidak dipesan'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field">Supplier<select name="supplier"><option value="">Semua supplier</option>@foreach($suppliers as $item)<option value="{{ $item->id }}" @selected($supplier === $item->id)>{{ $item->name }}</option>@endforeach</select></label>
            <button class="button" type="submit">Terapkan</button>@if($search || $status || $supplier)<a class="button" href="{{ route('restock-actions.index', ['run' => $run]) }}">Reset</a>@endif
        </form>
        <form id="bulk-action-form" method="POST" action="{{ route('restock-actions.bulk-update', $run) }}" data-restock-form data-unsaved-form novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="restock-actions"><input type="hidden" name="input_mode" value="edited">
            <div class="bulk-toolbar">
                <label class="checkbox"><input type="checkbox" data-select-all> Pilih semua di halaman ini</label>
                <span class="bulk-selection" data-bulk-selection role="status">Belum ada barang dipilih</span>
            </div>
            <div class="table-wrap">
                <table class="action-table">
                    <thead><tr><th>Pilih</th><th>Barang / Prioritas</th><th class="numeric">Saran Sistem</th><th>Jumlah {{ $isOwner ? 'Keputusan' : 'Usulan' }}</th><th>Catatan</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($results as $result)
                        @php($action = $result->restockAction)
                        @php($editable = !$action || $action->canBeEditedBy(auth()->user()))
                        @php($field = 'rows.'.$result->id)
                        <tr data-restock-row data-action-status="{{ $action?->status ?? 'pending' }}" data-product-name="{{ $result->displayProductName() }}" data-unit="{{ $result->product?->unit }}">
                            <td data-label="Pilih"><input id="select-restock-{{ $result->id }}" type="checkbox" name="result_ids[]" value="{{ $result->id }}" data-bulk-item aria-label="Pilih {{ $result->displayProductName() }}" @disabled(!$editable) @checked($editable && in_array($result->id, old('result_ids', [])))></td>
                            <td data-label="Barang" class="identity-cell"><strong>{{ $result->displayProductName() }}</strong><small>{{ $result->displayProductCode() }} · Prioritas {{ $result->rank_system }}</small><small>{{ $result->product?->supplier?->name ?? 'Supplier belum ditetapkan' }}</small></td>
                            <td data-label="Saran" class="numeric"><strong>{{ $result->product?->formatQuantity($result->restock_quantity, true) ?? '—' }}</strong></td>
                            @if($editable)
                                <td data-label="Jumlah">
                                    <label class="sr-only" for="quantity-{{ $result->id }}">Jumlah {{ $result->displayProductName() }}</label>
                                    <input id="quantity-{{ $result->id }}" class="inline-input" type="number" min="0" step="{{ $result->product?->quantityStep() ?? '0.01' }}" name="rows[{{ $result->id }}][approved_quantity]" value="{{ old($field.'.approved_quantity', $result->product?->usesWholeUnits() ? number_format((float) ($action?->approved_quantity ?? $result->restock_quantity), 0, '.', '') : ($action?->approved_quantity ?? $result->restock_quantity)) }}" data-restock-quantity>
                                    @error($field.'.approved_quantity')<small class="field-error">{{ $message }}</small>@enderror
                                    <button class="text-button" type="button" data-use-suggestion="{{ $result->restock_quantity }}">Gunakan saran</button>
                                </td>
                                <td data-label="Catatan"><label class="sr-only" for="notes-{{ $result->id }}">Catatan {{ $result->displayProductName() }}</label><input id="notes-{{ $result->id }}" class="action-note" name="rows[{{ $result->id }}][notes]" maxlength="1000" value="{{ old($field.'.notes', $action?->notes) }}" placeholder="Opsional">@error($field.'.notes')<small class="field-error">{{ $message }}</small>@enderror</td>
                            @else
                                <td data-label="Jumlah" class="numeric"><strong>{{ $result->product?->formatQuantity($action->approved_quantity, true) ?? '—' }}</strong></td>
                                <td data-label="Catatan">{{ $action->notes ?: '—' }}</td>
                            @endif
                            <td data-label="Status"><x-pill :tone="$action?->tone() ?? 'gray'">{{ $action?->label() ?? 'Belum ditinjau' }}</x-pill>
                                @if($action?->purchase_order_id)<a class="text-button" href="{{ route('purchase-orders.index', ['order' => $action->purchase_order_id]) }}">Lihat Pesanan</a>@elseif(!$editable)<small class="cell-note">Keputusan Owner</small>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">Tidak ada barang yang sesuai dengan filter.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="bulk-actions sticky-actions">
                <button class="button" type="submit" name="status" value="pending" data-requires-selection>Simpan Draft Terpilih</button>
                @if($isOwner)
                    <button class="button primary" type="submit" name="status" value="approved" data-requires-selection data-confirm-selection data-confirm="Simpan jumlah yang tertera dan setujui barang terpilih?" data-confirm-accept="Simpan & Setujui">Simpan & Setujui Terpilih</button>
                    <button class="button danger" type="submit" name="status" value="skipped" data-requires-selection data-confirm-selection data-confirm="Tandai barang terpilih sebagai tidak dipesan?" data-confirm-accept="Tidak Dipesan">Tidak Dipesan</button>
                @else
                    <button class="button primary" type="submit" name="status" value="proposed" data-requires-selection data-confirm-selection data-confirm="Simpan jumlah yang tertera dan kirim usulan barang terpilih ke Owner?" data-confirm-accept="Kirim Usulan">Kirim Usulan ke Owner</button>
                @endif
                <span class="save-state" data-save-state role="status"></span>
            </div>
        </form>
        <x-pagination :paginator="$results" />
    </div>
</section>
@else
<section class="card"><x-empty-state title="Belum ada rekomendasi" description="Lengkapi Data Operasional lalu buat rekomendasi untuk menyiapkan tindak lanjut restock." /></section>
@endif
@endsection

@push('dialogs')
@if($run && $isOwner && $purchaseGroups->isNotEmpty())
<dialog id="prepare-orders" aria-labelledby="prepare-orders-title">
    <div class="dialog-header"><h2 id="prepare-orders-title">Tinjau Draft Pesanan</h2><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup tinjauan pesanan">×</button></div>
    <div class="dialog-body">
        <p>Barang yang telah disetujui akan dikelompokkan menjadi satu pesanan untuk setiap supplier.</p>
        @foreach($purchaseGroups as $supplierId => $items)
            <div class="order-group"><h3>{{ $supplierId === 'missing' ? 'Supplier belum ditetapkan' : $items->first()->product->supplier->name }}</h3>
                <ul>@foreach($items as $item)<li>{{ $item->displayProductName() }} <strong>{{ $item->product?->formatQuantity($item->restockAction->approved_quantity, true) }}</strong></li>@endforeach</ul>
            </div>
        @endforeach
        @if($purchaseGroups->has('missing'))
            <p class="field-error">Lengkapi supplier sebelum membuat pesanan.</p><a class="button primary" href="{{ route('products.index', ['setup' => 'supplier']) }}">Lengkapi Supplier Barang</a>
        @else
            <form method="POST" action="{{ route('purchase-orders.from-run', $run) }}">@csrf<div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Buat {{ $purchaseGroups->count() }} Draft Pesanan</button></div></form>
        @endif
    </div>
</dialog>
@endif
@endpush
