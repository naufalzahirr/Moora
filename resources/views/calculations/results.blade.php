@extends('layouts.app')

@section('title', 'Hasil Rekomendasi Restock')
@section('breadcrumb', 'Hasil Rekomendasi')

@section('content')
<div class="page-heading">
    <div><h1>Hasil Rekomendasi Restock</h1><p>Prioritas restock dan jumlah pengadaan yang disarankan untuk setiap barang.</p></div>
    @if($run)<div class="heading-actions">
        <form class="period-switcher" method="GET" action="{{ route('calculations.results') }}">
            <label class="filter-label" for="result-run">Riwayat rekomendasi</label>
            <select id="result-run" name="run" data-auto-submit>
                @foreach($runs as $item)<option value="{{ $item->id }}" @selected($item->id === $run->id)>{{ $item->period->displayName() }} · dibuat {{ $item->created_at->translatedFormat('d M Y H:i') }}</option>@endforeach
            </select>
        </form>
        <a class="button" href="{{ route('reports.index', ['run' => $run]) }}">Buka Arsip Laporan</a>
        <a class="button primary" href="{{ route('restock-actions.index', ['run' => $run]) }}">Tindak Lanjut</a>
    </div>@else<a class="button primary" href="{{ route('datasets.index') }}">Lengkapi Data Operasional</a>@endif
</div>

<x-workflow :step="2" :run="$run" />
@if($run)
@php($freshness = $run->period->freshness())
<div class="notice {{ $freshness['is_stale'] ? 'error' : 'info' }}"><strong>{{ $freshness['is_stale'] ? 'Cakupan data ini sudah melewati batas pembaruan.' : 'Cakupan data masih mutakhir.' }}</strong> Data penjualan dan stok sampai {{ $run->period->end_date->translatedFormat('d M Y') }} ({{ $freshness['days_old'] }} hari lalu); perhitungan dibuat {{ $run->created_at->translatedFormat('d M Y H:i') }}.</div>

<section class="card">
    <div class="card-header"><h2>Prioritas Rekomendasi</h2><small>Diurutkan dari prioritas tertinggi</small></div>
    <div class="card-body">
        <form method="GET" class="filter-row page-filter" action="{{ route('calculations.results', $run) }}"><label class="field">Cari barang<input type="search" name="q" value="{{ request('q') }}" placeholder="Kode atau nama barang"></label><button class="button" type="submit">Cari</button>@if(request('q'))<a class="button" href="{{ route('calculations.results', $run) }}">Reset</a>@endif</form>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Prioritas</th><th>Nama Barang</th><th class="numeric">Saran Restock</th><th>Tindak Lanjut</th><th></th></tr></thead>
                <tbody>
                @forelse($results as $result)
                    <tr>
                        <td><span class="rank">{{ $result->rank_system }}</span></td>
                        <td><strong>{{ $result->displayProductName() }}</strong><br><small>{{ $result->displayProductCode() }}</small></td>

                        <td class="numeric">@if($result->restock_quantity !== null)<strong>{{ $result->product?->formatQuantity($result->restock_quantity, true) ?? '—' }}</strong><br><small>Target {{ $result->product?->formatQuantity($result->restock_target) ?? '—' }} · tersedia {{ $result->product?->formatQuantity($result->onHandAtCalculation($run->criteria_snapshot)) ?? '—' }}</small>@else—@endif</td>
                        <td>@if($result->restockAction)<x-pill :tone="$result->restockAction->tone()">{{ $result->restockAction->label() }}</x-pill>@else<x-pill tone="gray">Belum ditinjau</x-pill>@endif</td>
                        <td><a class="button small" href="{{ route('calculations.show', [$run, $result]) }}">Detail</a></td>
                    </tr>
                @empty<tr><td colspan="5" class="empty-cell">Tidak ada barang yang sesuai dengan pencarian.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$results" />
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;margin-top:22px;color:var(--muted);font-size:11px"><span>Keputusan akhir restock tetap berada pada owner H2 Asia Swalayan.</span><x-pill tone="blue">{{ $run->period->displayName() }}</x-pill></div>
    </div>
</section>
@else
<section class="card"><x-empty-state title="Belum ada rekomendasi" description="Lengkapi data operasional, lalu pilih Simpan & Buat Rekomendasi untuk melihat prioritas restock di sini." /></section>
@endif
@endsection
