@extends('layouts.app')

@section('title', 'Pesanan Pembelian')
@section('breadcrumb', 'Pesanan Pembelian')

@section('content')
<div class="page-heading">
    <div><h1>Pesanan Pembelian</h1><p>Kelola persetujuan, pengiriman ke supplier, dan penerimaan barang dari rekomendasi restock.</p></div>
    <div class="heading-actions"><a class="button" href="{{ route('restock-actions.index', $run ? ['run' => $run] : []) }}">Tindak Lanjut Restock</a></div>
</div>
<x-workflow :step="4" :run="$run" />
<section class="card order-list">
    <div class="card-header"><h2>Daftar Pesanan</h2><small>{{ $orders->total() }} pesanan ditemukan</small></div>
    <div class="card-body">
        <form method="GET" class="filter-row page-filter">
            @if(request('run'))<input type="hidden" name="run" value="{{ request('run') }}">@endif
            <label class="field">Cari pesanan<input type="search" name="q" value="{{ $search }}" placeholder="Nomor atau supplier"></label>
            <label class="field">Status<select name="status"><option value="">Semua status</option>@foreach(['pending' => 'Perlu diproses', 'receiving' => 'Menunggu penerimaan', 'draft' => 'Draft', 'approved' => 'Disetujui', 'ordered' => 'Dipesan', 'partial' => 'Diterima sebagian', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="button" type="submit">Terapkan</button>@if(request()->hasAny(['q', 'status', 'run']))<a class="button" href="{{ route('purchase-orders.index') }}">Lihat Semua</a>@endif
        </form>
        <div class="table-wrap"><table class="responsive-table"><thead><tr><th>Pesanan / Supplier</th><th>Barang</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($orders as $item)
            <tr @if($order?->id === $item->id) class="selected-row" @endif>
                <td data-label="Pesanan"><strong>{{ $item->order_number }}</strong><small class="cell-note">{{ $item->supplier->name }}</small></td><td data-label="Barang">{{ $item->items_count }} barang</td><td data-label="Status"><x-pill :tone="$item->tone()">{{ $item->label() }}</x-pill></td>
                <td data-label="Aksi"><a class="button small" href="{{ route('purchase-orders.index', array_merge(request()->except('order'), ['order' => $item->id])) }}#order-detail" @if($order?->id === $item->id) aria-current="true" @endif>Buka Pesanan</a></td>
            </tr>
        @empty<tr><td colspan="4" class="empty-cell">Tidak ada pesanan yang sesuai dengan filter.</td></tr>@endforelse
        </tbody></table></div>
        <x-pagination :paginator="$orders" />
    </div>
</section>
@if($order)
<section class="card" id="order-detail">
    <div class="card-header"><div><h2>{{ $order->order_number }}</h2><small>{{ $order->supplier->name }} · Estimasi tiba {{ $order->expected_at?->translatedFormat('d M Y') ?? 'belum ditetapkan' }}</small></div><x-pill :tone="$order->tone()">{{ $order->label() }}</x-pill></div>
    <div class="card-body">
        <p class="context-caption">Rekomendasi: {{ $order->run->period->displayName() }} · {{ $order->run->period->displayRange() }}</p>
        @if($order->status === 'draft')<p>Jumlah barang telah disetujui pada Tindak Lanjut. Periksa pengelompokan supplier sebelum menyetujui pesanan ini.</p>
        @elseif($order->status === 'approved')<p>Kirim pesanan kepada supplier, lalu pilih <b>Tandai Sudah Dipesan</b> untuk mencatat pengirimannya.</p>
        @elseif(in_array($order->status, ['ordered', 'partial']))<p>Catat barang yang tiba pada formulir penerimaan di bawah. Estimasi kedatangan: <b>{{ $order->expected_at?->translatedFormat('d M Y') ?? 'belum ditetapkan' }}</b>.</p>@endif
        @if($order->status === 'draft' && auth()->user()->role === 'owner')
            <form method="POST" action="{{ route('purchase-orders.status', $order) }}" class="inline-form" data-confirm="Setujui pesanan ini? Setelah disetujui, petugas dapat mengirimkannya ke supplier." data-confirm-accept="Setujui Pesanan">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="button primary" type="submit">Setujui Pesanan</button></form>
        @elseif($order->status === 'approved')
            <form method="POST" action="{{ route('purchase-orders.status', $order) }}" class="inline-form">@csrf @method('PUT')<input type="hidden" name="status" value="ordered"><button class="button primary" type="submit">Tandai Sudah Dipesan</button></form>
        @endif
        @if(in_array($order->status, ['draft', 'approved']) && auth()->user()->role === 'owner')
            <form method="POST" action="{{ route('purchase-orders.status', $order) }}" class="inline-form" data-confirm="Batalkan pesanan ini? Barang akan dikembalikan ke daftar siap dipesan.">@csrf @method('PUT')<input type="hidden" name="status" value="cancelled"><button class="button small danger" type="submit">Batalkan Pesanan</button></form>
        @endif
        <div class="table-wrap" style="margin-top:18px">
            <table class="responsive-table">
                <thead><tr><th>Barang</th><th class="numeric">Saran Sistem</th><th class="numeric">Dipesan</th><th class="numeric">Diterima</th><th class="numeric">Sisa</th></tr></thead>
                <tbody>@foreach($order->items as $item)<tr><td><strong>{{ $item->product->name }}</strong><br><small>{{ $item->product->code }} · {{ $item->product->unit }}</small></td><td class="numeric">{{ $item->suggested_quantity !== null ? $item->product->formatQuantity($item->suggested_quantity) : '—' }}</td><td class="numeric"><strong>{{ $item->product->formatQuantity($item->ordered_quantity) }}</strong></td><td class="numeric">{{ $item->product->formatQuantity($item->received_quantity) }}</td><td class="numeric">{{ $item->product->formatQuantity($item->remainingQuantity()) }}</td></tr>@endforeach</tbody>
            </table>
        </div>

        @if(in_array($order->status, ['ordered', 'partial']))
            <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" data-unsaved-form style="margin-top:20px">@csrf @method('PUT')
                <div class="card-header" style="padding:0 0 12px"><div><h2 style="font-size:16px">Catat Penerimaan Barang</h2><small>Masukkan jumlah yang diterima pada penerimaan kali ini. Stok berjalan akan bertambah sebesar angka tersebut.</small></div></div>
                <div class="table-wrap"><table class="responsive-table"><thead><tr><th>Barang</th><th class="numeric">Dipesan</th><th class="numeric">Sudah Diterima</th><th class="numeric">Sisa</th><th class="numeric">Diterima Kali Ini</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td><strong>{{ $item->product->name }}</strong></td><td class="numeric">{{ $item->product->formatQuantity($item->ordered_quantity) }}</td><td class="numeric">{{ $item->product->formatQuantity($item->received_quantity) }}</td><td class="numeric">{{ $item->product->formatQuantity($item->remainingQuantity()) }}</td><td class="numeric"><label class="sr-only" for="received-{{ $item->id }}">Diterima kali ini {{ $item->product->name }}</label><input id="received-{{ $item->id }}" class="inline-input" type="number" min="0" max="{{ $item->remainingQuantity() }}" step="{{ $item->product->quantityStep() }}" name="items[{{ $item->id }}]" value="{{ old('items.'.$item->id) }}" placeholder="0"></td></tr>@endforeach</tbody></table></div>
                <div class="form-footer"><button class="button primary" type="submit">Simpan Penerimaan</button></div>
            </form>
        @endif
    </div>
</section>
@else
<section class="card"><x-empty-state :title="request()->hasAny(['q', 'status', 'run', 'order']) ? 'Tidak ada pesanan yang dipilih' : 'Belum ada pesanan pembelian'" description="Buka pesanan dari daftar di atas, ubah filter, atau buat draft melalui Tindak Lanjut." /></section>
@endif
@endsection
