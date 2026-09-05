@extends('layouts.app')

@section('title', 'Pesanan Pembelian')
@section('breadcrumb', 'Pesanan Pembelian')

@section('content')
<div class="page-heading">
    <div><h1>Pesanan Pembelian</h1><p>Kelola persetujuan, pengiriman ke supplier, dan penerimaan barang dari rekomendasi restock.</p></div>
    <div class="heading-actions"><a class="button" href="{{ route('restock-actions.index') }}">Tindak Lanjut Restock</a><a class="button primary" href="{{ route('inventory.index') }}">Lihat Stok Berjalan</a></div>
</div>

@if($orders->isNotEmpty())
<div class="filter-row page-filter"><form method="GET"><label class="sr-only" for="purchase-order">Pilih pesanan pembelian</label><select id="purchase-order" name="order" data-auto-submit>@foreach($orders as $item)<option value="{{ $item->id }}" @selected($order?->id === $item->id)>{{ $item->order_number }} · {{ $item->supplier->name }} · {{ $item->label() }}</option>@endforeach</select></form></div>
@endif

@if($order)
<div class="stats-grid action-stats">
    <div class="stat-card"><span class="stat-icon">PO</span><div><small>NOMOR PESANAN</small><strong>{{ $order->order_number }}</strong></div></div>
    <div class="stat-card"><span class="stat-icon blue">{{ $order->items->count() }}</span><div><small>BARIS PESANAN</small><strong>{{ $order->items->count() }} Barang</strong></div></div>
    <div class="stat-card"><span class="stat-icon">{{ $order->supplier->lead_time_days }}</span><div><small>LEAD TIME SUPPLIER</small><strong>{{ $order->supplier->lead_time_days }} Hari</strong></div></div>
    <div class="stat-card"><span class="stat-icon {{ $order->status === 'received' ? 'green' : 'blue' }}">{{ $order->status === 'received' ? '✓' : 'PO' }}</span><div><small>STATUS</small><strong>{{ $order->label() }}</strong></div></div>
</div>

<section class="card">
    <div class="card-header"><div><h2>{{ $order->order_number }}</h2><small>{{ $order->supplier->name }} · Estimasi tiba {{ $order->expected_at?->translatedFormat('d M Y') ?? 'belum ditetapkan' }}</small></div><x-pill :tone="$order->tone()">{{ $order->label() }}</x-pill></div>
    <div class="card-body">
        <div class="notice info"><strong>Alur pesanan:</strong> Draft dibuat dari barang yang disetujui → Owner menyetujui → petugas mengirim pesanan ke supplier → penerimaan barang memperbarui stok berjalan.</div>
        @if($order->status === 'draft' && auth()->user()->role === 'owner')
            <form method="POST" action="{{ route('purchase-orders.status', $order) }}" class="inline-form" data-confirm="Setujui pesanan ini? Setelah disetujui, petugas dapat mengirimkannya ke supplier." data-confirm-accept="Setujui Pesanan">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="button primary" type="submit">Setujui Pesanan</button></form>
        @elseif($order->status === 'approved')
            <form method="POST" action="{{ route('purchase-orders.status', $order) }}" class="inline-form">@csrf @method('PUT')<input type="hidden" name="status" value="ordered"><button class="button primary" type="submit">Tandai Sudah Dipesan</button></form>
        @endif
        @if(in_array($order->status, ['draft', 'approved']) && auth()->user()->role === 'owner')
            <form method="POST" action="{{ route('purchase-orders.status', $order) }}" class="inline-form" data-confirm="Batalkan pesanan ini? Barang akan dikembalikan ke daftar siap dipesan.">@csrf @method('PUT')<input type="hidden" name="status" value="cancelled"><button class="button small danger" type="submit">Batalkan Pesanan</button></form>
        @endif
        <div class="table-wrap" style="margin-top:18px">
            <table>
                <thead><tr><th>Barang</th><th class="numeric">Saran Sistem</th><th class="numeric">Dipesan</th><th class="numeric">Diterima</th><th class="numeric">Sisa</th></tr></thead>
                <tbody>@foreach($order->items as $item)<tr><td><strong>{{ $item->product->name }}</strong><br><small>{{ $item->product->code }} · {{ $item->product->unit }}</small></td><td class="numeric">{{ $item->suggested_quantity !== null ? $item->product->formatQuantity($item->suggested_quantity) : '—' }}</td><td class="numeric"><strong>{{ $item->product->formatQuantity($item->ordered_quantity) }}</strong></td><td class="numeric">{{ $item->product->formatQuantity($item->received_quantity) }}</td><td class="numeric">{{ $item->product->formatQuantity($item->remainingQuantity()) }}</td></tr>@endforeach</tbody>
            </table>
        </div>

        @if(in_array($order->status, ['ordered', 'partial']))
            <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" style="margin-top:20px">@csrf @method('PUT')
                <div class="card-header" style="padding:0 0 12px"><div><h2 style="font-size:16px">Catat Penerimaan Barang</h2><small>Masukkan jumlah yang diterima pada penerimaan kali ini. Stok berjalan akan bertambah sebesar angka tersebut.</small></div></div>
                <div class="table-wrap"><table><thead><tr><th>Barang</th><th class="numeric">Dipesan</th><th class="numeric">Sudah Diterima</th><th class="numeric">Sisa</th><th class="numeric">Diterima Kali Ini</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td><strong>{{ $item->product->name }}</strong></td><td class="numeric">{{ $item->product->formatQuantity($item->ordered_quantity) }}</td><td class="numeric">{{ $item->product->formatQuantity($item->received_quantity) }}</td><td class="numeric">{{ $item->product->formatQuantity($item->remainingQuantity()) }}</td><td class="numeric"><label class="sr-only" for="received-{{ $item->id }}">Diterima kali ini {{ $item->product->name }}</label><input id="received-{{ $item->id }}" class="inline-input" type="number" min="0" max="{{ $item->remainingQuantity() }}" step="{{ $item->product->quantityStep() }}" name="items[{{ $item->id }}]" value="{{ old('items.'.$item->id) }}" placeholder="0"></td></tr>@endforeach</tbody></table></div>
                <div class="form-footer"><button class="button primary" type="submit">Simpan Penerimaan</button></div>
            </form>
        @endif
    </div>
</section>
@else
<section class="card"><x-empty-state title="Belum ada pesanan pembelian" description="Setujui jumlah restock pada Tindak Lanjut, lalu buat pesanan yang dikelompokkan berdasarkan supplier." /></section>
@endif
@endsection
