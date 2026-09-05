@extends('layouts.app')

@section('title', 'Tindak Lanjut Restock')
@section('breadcrumb', 'Tindak Lanjut')

@section('content')
<div class="page-heading">
    <div>
        <h1>Tindak Lanjut Restock</h1>
        <p>Petugas menyiapkan usulan; Owner menetapkan keputusan sebelum pesanan pembelian dibuat.</p>
    </div>
    @if($run)
        <div class="heading-actions">
            <a class="button primary" href="{{ route('calculations.results', $run) }}">Lihat Hasil Rekomendasi</a>
        </div>
    @endif
</div>

@if($runs->isNotEmpty())
<div class="filter-row" style="margin-bottom:14px">
    <form class="context-switcher" method="GET"><label class="filter-label" for="restock-action-run">Rekomendasi yang ditindaklanjuti</label><select id="restock-action-run" name="run" data-auto-submit>@foreach($runs as $item)<option value="{{ $item->id }}" @selected($run?->id === $item->id)>{{ $item->period->displayName() }} · {{ $item->period->displayRange() }} · dibuat {{ $item->created_at->translatedFormat('d M Y') }}</option>@endforeach</select></form>
</div>
@endif

@if($run)
@php($actions = $run->results->pluck('restockAction')->filter())
@php($pendingCount = $run->total_alternatives - $actions->whereIn('status', ['approved', 'ordered', 'received', 'skipped'])->count())
@php($readyForPurchaseOrder = $actions->filter(fn($action) => $action->status === 'approved' && ! $action->purchase_order_id)->count())
<div class="stats-grid action-stats">
    <div class="stat-card"><span class="stat-icon">{{ $run->total_alternatives }}</span><div><small>REKOMENDASI</small><strong>{{ $run->total_alternatives }} Barang</strong></div></div>
    <div class="stat-card"><span class="stat-icon">{{ $pendingCount }}</span><div><small>BELUM DITINJAU</small><strong>{{ $pendingCount }} Barang</strong></div></div>
    <div class="stat-card"><span class="stat-icon green">{{ $actions->where('status', 'ordered')->count() }}</span><div><small>SUDAH DIPESAN</small><strong>{{ $actions->where('status', 'ordered')->count() }} Barang</strong></div></div>
    <div class="stat-card"><span class="stat-icon green">{{ $actions->where('status', 'received')->count() }}</span><div><small>SUDAH DITERIMA</small><strong>{{ $actions->where('status', 'received')->count() }} Barang</strong></div></div>
</div>

<section class="card">
    <div class="card-header"><div><h2>Daftar Tindak Lanjut</h2><small>Pilih beberapa barang untuk menyimpan atau menyetujui keputusan sekaligus.</small></div>@if($readyForPurchaseOrder && auth()->user()->role === 'owner')<form method="POST" action="{{ route('purchase-orders.from-run', $run) }}" data-confirm="Buat draft pesanan pembelian dari seluruh keputusan yang telah disetujui? Pesanan akan dikelompokkan berdasarkan supplier." data-confirm-accept="Buat Draft Pesanan">@csrf<button class="button primary" type="submit">Buat {{ $readyForPurchaseOrder }} Pesanan Siap Diproses</button></form>@endif</div>
    <div class="card-body">
        <div class="notice info"><strong>{{ auth()->user()->role === 'owner' ? 'Owner menetapkan keputusan akhir.' : 'Petugas hanya dapat mengirim usulan restock.' }}</strong>{{ auth()->user()->role === 'owner' ? ' Setujui atau tolak usulan sebelum membuat draft pesanan per supplier.' : ' Owner akan meninjau dan menyetujui usulan sebelum pesanan pembelian dapat dibuat.' }} Penerimaan hanya dicatat melalui Pesanan Pembelian agar stok berjalan otomatis bertambah.</div>
        <form id="bulk-action-form" method="POST" action="{{ route('restock-actions.bulk-update', $run) }}" class="bulk-actions">@csrf @method('PUT')
            @if(auth()->user()->role === 'owner')
                <button class="button small" type="submit" name="status" value="approved" data-confirm="Setujui barang yang dipilih menggunakan jumlah yang sudah tersimpan atau saran sistem?" data-confirm-accept="Setujui Terpilih">Setujui Terpilih</button>
                <button class="button small success" type="submit" name="status" value="approved" data-bulk-submit-all data-confirm="Setujui semua barang yang belum masuk pesanan menggunakan saran sistem?" data-confirm-accept="Setujui Semua">Setujui Semua sesuai Saran</button><input type="hidden" name="all" value="1" disabled data-bulk-all>
            @else
                <button class="button small primary" type="submit" name="status" value="proposed">Kirim Usulan Terpilih</button>
                <button class="button small" type="submit" name="status" value="proposed" data-bulk-submit-all data-confirm="Kirim usulan semua barang yang belum masuk pesanan kepada Owner?" data-confirm-accept="Kirim Semua">Kirim Semua sesuai Saran</button><input type="hidden" name="all" value="1" disabled data-bulk-all>
            @endif
            <span class="bulk-selection" data-bulk-selection>Belum ada barang dipilih</span>
        </form>
        <div class="table-wrap">
            <table class="action-table">
                <thead><tr><th><label class="sr-only" for="select-all-restock">Pilih semua barang yang dapat diproses</label><input id="select-all-restock" type="checkbox" data-select-all></th><th>Rank</th><th>Barang</th><th class="numeric">Saran Sistem</th><th>Keputusan</th><th>Catatan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($run->results as $result)
                    @php($action = $result->restockAction)
                    <tr>
                        <td><label class="sr-only" for="select-restock-{{ $result->id }}">Pilih {{ $result->displayProductName() }}</label><input id="select-restock-{{ $result->id }}" type="checkbox" name="result_ids[]" value="{{ $result->id }}" form="bulk-action-form" data-bulk-item @disabled($action?->purchase_order_id)></td>
                        <td><span class="rank">{{ $result->rank_system }}</span></td>
                        <td><strong>{{ $result->displayProductName() }}</strong><br><small>{{ $result->displayProductCode() }} · {{ $result->product?->supplier?->name ?? 'Supplier belum ditetapkan' }}</small></td>
                        <td class="numeric"><strong>{{ $result->product?->formatQuantity($result->restock_quantity, true) ?? '—' }}</strong></td>
                        @if($action?->purchase_order_id || (auth()->user()->role !== 'owner' && in_array($action?->status, ['approved', 'skipped'], true)))
                            <td class="numeric"><strong>{{ $result->product?->formatQuantity($action->approved_quantity, true) ?? '—' }}</strong></td>
                            <td>{{ $action->notes ?: '—' }}</td>
                            <td><x-pill :tone="$action->tone()">{{ $action->label() }}</x-pill></td>
                            <td>@if($action->purchase_order_id)<a class="button small" href="{{ route('purchase-orders.index', ['order' => $action->purchase_order_id]) }}">{{ $action->purchaseOrder?->order_number ?? 'Lihat Pesanan' }}</a>@else<span class="pill gray">Keputusan Owner</span>@endif</td>
                        @else
                            <td>
                                <form id="action-form-{{ $result->id }}" class="action-form" method="POST" action="{{ route('restock-actions.update', [$run, $result]) }}">@csrf @method('PUT')</form>
                                <label class="sr-only" for="quantity-{{ $result->id }}">Jumlah {{ auth()->user()->role === 'owner' ? 'keputusan' : 'usulan' }} {{ $result->displayProductName() }}</label>
                                @php($quantity = old('approved_quantity', $action?->approved_quantity ?? $result->restock_quantity))
                                <input id="quantity-{{ $result->id }}" class="inline-input" type="number" min="0" step="{{ $result->product?->quantityStep() ?? '0.01' }}" name="approved_quantity" form="action-form-{{ $result->id }}" value="{{ $result->product?->usesWholeUnits() ? number_format((float) $quantity, 0, '.', '') : $quantity }}">
                            </td>
                            <td>
                                <label class="sr-only" for="notes-{{ $result->id }}">Catatan {{ $result->displayProductName() }}</label>
                                <input id="notes-{{ $result->id }}" class="action-note" name="notes" form="action-form-{{ $result->id }}" maxlength="1000" value="{{ old('notes', $action?->notes) }}" placeholder="Opsional">
                            </td>
                            <td>
                                <label class="sr-only" for="status-{{ $result->id }}">Status tindak lanjut {{ $result->displayProductName() }}</label>
                                @php($selectedStatus = old('status', $action?->status ?? 'pending'))
                                <select id="status-{{ $result->id }}" class="action-status" name="status" form="action-form-{{ $result->id }}">
                                    <option value="pending" @selected($selectedStatus === 'pending')>{{ auth()->user()->role === 'owner' ? 'Belum ditinjau' : 'Simpan sebagai draft' }}</option>
                                    @if(auth()->user()->role === 'owner')
                                        @if($selectedStatus === 'proposed')<option value="proposed" selected disabled>Usulan petugas — pilih keputusan</option>@endif
                                        <option value="approved" @selected($selectedStatus === 'approved')>Setujui untuk dipesan</option>
                                        <option value="skipped" @selected($selectedStatus === 'skipped')>Tidak dipesan</option>
                                    @else
                                        <option value="proposed" @selected($selectedStatus === 'proposed')>Kirim usulan ke Owner</option>
                                    @endif
                                </select>
                            </td>
                            <td><button class="button small primary" type="submit" form="action-form-{{ $result->id }}">{{ auth()->user()->role === 'owner' ? 'Simpan Keputusan' : 'Simpan Usulan' }}</button></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@else
<section class="card"><x-empty-state title="Belum ada rekomendasi" description="Lengkapi Data Operasional lalu buat rekomendasi untuk menyiapkan tindak lanjut restock." /></section>
@endif
@endsection
