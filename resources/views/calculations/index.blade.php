@extends('layouts.app')

@section('title', 'Analisis Rekomendasi MOORA')
@section('breadcrumb', 'Analisis MOORA')

@section('content')
<div class="page-heading">
    <div><h1>Siapkan Rekomendasi Restock</h1><p>Periksa kelengkapan data penjualan dan stok sebelum membuat prioritas restock.</p></div>
    <div class="heading-actions">
        @if($period?->isLocked())
            <x-pill tone="green">Tersimpan</x-pill>
        @elseif($period && $preview)
            <form method="POST" action="{{ route('calculations.store') }}">@csrf<input type="hidden" name="period_id" value="{{ $period->id }}"><button class="button success" type="submit">Buat Rekomendasi</button></form>
        @else<x-pill tone="gray">Lengkapi data</x-pill>@endif
    </div>
</div>

<div class="filter-row" style="margin-bottom:14px">
    <form method="GET"><label class="sr-only" for="calculation-period">Pilih data untuk dianalisis</label><select id="calculation-period" name="period" data-auto-submit>@foreach($periods as $item)<option value="{{ $item->id }}" @selected($period?->id === $item->id)>{{ $item->name }}</option>@endforeach</select></form>
</div>

<section class="card">
    <div class="card-header"><div><h2>Kesiapan Data</h2><small>{{ $period?->name ?? 'Belum ada data operasional yang dipilih' }}</small></div>@if($period?->isLocked())<x-pill tone="green">Rekomendasi tersimpan</x-pill>@elseif($preview)<x-pill tone="green">Siap dihitung</x-pill>@else<x-pill tone="gray">Perlu dilengkapi</x-pill>@endif</div>
    <div class="card-body">
        <div class="metrics">
            <div class="metric"><small>BARANG SIAP DINILAI</small><strong>{{ $preview ? $preview['rows']->count() : 0 }}</strong></div>
            <div class="metric"><small>KRITERIA AKTIF</small><strong class="green">{{ $criteria->count() }}</strong></div>
        </div>
    @if($period?->isLocked())
        <div class="notice info" style="margin:18px 0 0"><strong>Rekomendasi sudah tersimpan.</strong> Untuk memakai data baru, buat pembaruan melalui Data Operasional.</div>
    @elseif($preview)
        <div class="notice success" style="margin:18px 0 0"><strong>Data lengkap dan siap dihitung.</strong> {{ $preview['rows']->count() }} barang dan {{ $criteria->count() }} kriteria akan digunakan. Setelah dibuat, rekomendasi tersimpan sebagai riwayat baru.</div>
    @elseif($validationMessage)
        <div class="notice error" style="margin:18px 0 0"><strong>Data belum siap dihitung.</strong> {{ $validationMessage }} <a href="{{ route('datasets.index', $period ? ['period' => $period] : []) }}"><b>Buka Data Operasional →</b></a></div>
    @else
        <div class="notice error" style="margin:18px 0 0"><strong>Belum ada data.</strong> Masukkan data pada halaman Data Operasional.</div>
    @endif
    </div>
</section>
@endsection
