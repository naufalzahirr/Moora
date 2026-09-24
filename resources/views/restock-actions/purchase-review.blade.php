@extends('layouts.app')
@section('title', 'Pembelian')
@section('breadcrumb', 'Pembelian')
@section('content')
<div class="page-heading"><div><h1>Pembelian</h1><p>Petugas mengajukan jumlah pembelian. Owner memeriksa dan mengonfirmasi keputusan.</p></div></div>
<div class="notice info"><strong>Persetujuan tidak menambah stok.</strong>Setelah pembelian benar-benar diterima, catat sebagai Barang masuk di <a href="{{ route('transactions.index') }}">Transaksi Barang</a>. Jumlah pembelian ditentukan pengguna, bukan nilai Yi MOORA.</div>
@if($run)
<form method="GET" class="filter-row"><label class="field">Hasil penilaian<select name="run" data-auto-submit>@foreach($runs as $item)<option value="{{ $item->id }}" @selected($item->id === $run->id)>{{ $item->period->monthlyLabel() }} · {{ $item->created_at->format('d-m-Y H:i') }}</option>@endforeach</select></label></form>
<section class="card"><div class="card-header"><h2>Pengajuan Pembelian</h2><a href="{{ route('calculations.results', $run) }}">Lihat hasil MOORA</a></div><div class="card-body">
@foreach($results as $result)
@php
    $action = $result->restockAction;
    $editable = !$action || $action->canBeEditedBy(auth()->user());
@endphp
<form method="POST" action="{{ route('restock-actions.update', [$run, $result]) }}" style="padding:18px 0;border-bottom:1px solid #d9e5ee">@csrf @method('PUT')
<strong>{{ $result->rank_system }}. {{ $result->displayProductName() }}</strong>
<p>Status: {{ $action?->label() ?? 'Belum diajukan' }}</p>
@if($editable)
<div class="form-grid"><label class="field">JUMLAH PEMBELIAN ({{ $result->product?->unit }})<input type="number" name="approved_quantity" min="{{ $result->product?->quantityStep() ?? '0.01' }}" step="{{ $result->product?->quantityStep() ?? '0.01' }}" value="{{ $action?->approved_quantity }}" required></label><label class="field">CATATAN<input name="notes" maxlength="1000" value="{{ $action?->notes }}" placeholder="Alasan pembelian"></label></div>
<div class="heading-actions" style="margin-top:12px">@if(auth()->user()->isOwner())<button class="button primary" name="status" value="approved">Konfirmasi Pembelian</button><button class="button" name="status" value="skipped" formnovalidate>Tidak Disetujui</button>@else<button class="button primary" name="status" value="proposed">Ajukan Pembelian</button>@endif</div>
@else<p>Jumlah: {{ $result->product?->formatQuantity($action->approved_quantity, true) }} · {{ $action->notes }}. Keputusan sudah dikunci.</p>@endif
</form>
@endforeach
<x-pagination :paginator="$results" />
</div></section>
@else<section class="card"><x-empty-state title="Belum ada hasil penilaian" description="Hitung MOORA terlebih dahulu, kemudian ajukan pembelian dari hasilnya." /></section>@endif
@endsection
