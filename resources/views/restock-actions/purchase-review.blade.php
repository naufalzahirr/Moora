@extends('layouts.app')
@section('title', 'Pembelian')
@section('breadcrumb', 'Pembelian')
@section('content')
<div class="page-heading"><div><h1>Pembelian</h1><p>Petugas mengajukan jumlah pembelian. Owner memeriksa dan mengonfirmasi keputusan.</p></div></div>
<div class="notice info"><strong>Persetujuan tidak menambah stok.</strong>Setelah pembelian benar-benar diterima, catat sebagai Barang masuk di <a href="{{ route('transactions.index') }}">Transaksi Barang</a>. Saran jumlah memakai perhitungan kebutuhan stok terpisah dari nilai Yi MOORA. Petugas dapat menyesuaikan jumlah sebelum diajukan.</div>
@if($run)
<form method="GET" class="filter-row"><label class="field">Hasil penilaian<select name="run" data-auto-submit>@foreach($runs as $item)<option value="{{ $item->id }}" @selected($item->id === $run->id)>{{ $item->period->monthlyLabel() }} · {{ $item->created_at->format('d-m-Y H:i') }}</option>@endforeach</select></label></form>
<section class="card"><div class="card-header"><h2>Pengajuan Pembelian</h2><a href="{{ route('calculations.results', $run) }}">Lihat hasil MOORA</a></div><div class="card-body">
@foreach($results as $result)
@php
    $action = $result->restockAction;
    $basis = $result->restock_basis ?? [];
    $suggested = $result->restock_quantity;
    $editable = !$action || $action->canBeEditedBy(auth()->user());
@endphp
<form method="POST" action="{{ route('restock-actions.update', [$run, $result]) }}" style="padding:18px 0;border-bottom:1px solid #d9e5ee">@csrf @method('PUT')
<strong>{{ $result->rank_system }}. {{ $result->displayProductName() }}</strong>
<p>Status: {{ $action?->label() ?? 'Belum diajukan' }}</p>
<div class="notice info"><strong>Saran jumlah pembelian: {{ $result->product?->formatQuantity($suggested, true) ?? 'Belum tersedia' }}</strong>
@if($suggested !== null && (float) $suggested <= 0)Stok pada saat analisis masih mencukupi target. Tidak perlu mengajukan pembelian berdasarkan perhitungan ini.@endif
Saran memakai data sampai {{ $run->period->end_date->format('d-m-Y') }} dan pengaturan saat dihitung. Jika stok, transaksi, atau pengaturan berubah, hitung MOORA ulang sebelum mengajukan.
</div>
@if(isset($basis['average_daily_demand']))
<details style="margin:12px 0"><summary>Bagaimana jumlah ini dihitung?</summary>
<p>1. Rata-rata penjualan = jumlah terjual ÷ {{ $basis['period_days'] }} hari = <b>{{ number_format($basis['average_daily_demand'], 4, ',', '.') }}</b> {{ $result->product?->unit }}/hari.</p>
<p>2. Kebutuhan = rata-rata × (waktu tunggu {{ $basis['lead_time_days'] }} hari + jarak pemesanan {{ $basis['review_period_days'] }} hari) + stok cadangan {{ $basis['safety_stock'] }}.</p>
<p>3. Target mengambil nilai terbesar antara kebutuhan, stok minimum {{ $basis['minimum_stock'] }}, dan target manual {{ $basis['configured_target_stock'] ?? 0 }} = <b>{{ $result->product?->formatQuantity($result->restock_target, true) }}</b>.</p>
<p>4. Kekurangan = maksimum(0, target − stok {{ $basis['on_hand'] }} − pesanan berjalan {{ $basis['incoming'] }}) = {{ number_format(max(0, (float) $result->restock_target - $basis['on_hand'] - $basis['incoming']), 2, ',', '.') }}.</p>
<p>5. Jika ada kekurangan, penuhi minimum pemesanan {{ $basis['minimum_order_quantity'] }} dan bulatkan ke atas sesuai kelipatan {{ $basis['order_multiple'] }}. Hasil: <b>{{ $result->product?->formatQuantity($suggested, true) }}</b>.</p>
<p>Pengajuan pada halaman ini belum dihitung sebagai pesanan berjalan. Periksa pembelian lain yang sudah disetujui agar tidak memesan ganda.</p>
<a href="{{ route('products.index', ['search' => $result->displayProductCode()]) }}">Atur kebutuhan pembelian di Data Barang → Ubah</a>
</details>
@endif
@if($editable)
<div class="form-grid"><label class="field">JUMLAH PEMBELIAN ({{ $result->product?->unit }})<input type="number" name="approved_quantity" min="{{ $result->product?->quantityStep() ?? '0.01' }}" step="{{ $result->product?->quantityStep() ?? '0.01' }}" value="{{ $action?->approved_quantity ?? ((float) $suggested > 0 ? $suggested : null) }}" required></label><label class="field">CATATAN<input name="notes" maxlength="1000" value="{{ $action?->notes }}" placeholder="Alasan pembelian"></label></div>
<div class="heading-actions" style="margin-top:12px">@if(auth()->user()->isOwner())<button class="button primary" name="status" value="approved">Konfirmasi Pembelian</button><button class="button" name="status" value="skipped" formnovalidate>Tidak Disetujui</button>@else<button class="button primary" name="status" value="proposed">Ajukan Pembelian</button>@endif</div>
@else<p>Jumlah: {{ $result->product?->formatQuantity($action->approved_quantity, true) }} · {{ $action->notes }}. Keputusan sudah dikunci.</p>@endif
</form>
@endforeach
<x-pagination :paginator="$results" />
</div></section>
@else<section class="card"><x-empty-state title="Belum ada hasil penilaian" description="Hitung MOORA terlebih dahulu, kemudian ajukan pembelian dari hasilnya." /></section>@endif
@endsection
