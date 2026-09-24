@extends('layouts.app')

@section('title', 'Hasil Penilaian Restock')
@section('breadcrumb', 'Hasil Penilaian')

@section('content')
<div class="page-heading">
    <div><h1>Hasil Penilaian Restock</h1><p>Nilai dan ranking MOORA sebagai bahan pertimbangan pemilik toko. Ranking bukan keputusan perlu atau tidak perlu restock.</p></div>
    @if($run)<div class="heading-actions">
        <form class="period-switcher" method="GET" action="{{ route('calculations.results') }}">
            <label class="filter-label" for="result-run">Rentang / riwayat penilaian</label>
            <select id="result-run" name="run" data-auto-submit>
                @foreach($runs as $item)<option value="{{ $item->id }}" @selected($item->id === $run->id)>{{ $item->period->monthlyLabel() }}</option>@endforeach
            </select>
        </form>
        <a class="button" href="{{ route('reports.index', ['run' => $run]) }}">Laporan</a>
        <a class="button primary" href="{{ route('reports.download', $run) }}">Cetak Laporan PDF</a>
    </div>@else<a class="button primary" href="{{ route('transactions.analysis') }}">Mulai Penilaian</a>@endif
</div>

<x-workflow :step="2" :run="$run" />
@if($run)
<div class="notice info"><strong>{{ $run->period->monthlyLabel() }}</strong> Data {{ $run->period->displayRange() }} · dihitung {{ $run->created_at->translatedFormat('d M Y H:i') }}.</div>
@if(str_starts_with($run->notes ?? '', 'Barang dilewati:'))<div class="notice info"><strong>Cakupan analisis</strong>{{ $run->notes }}</div>@endif
@if($needsRecalculation)<div class="notice error">Ada data baru setelah perhitungan ini. Hasil ini belum mencerminkan perubahan; <a href="{{ $run->period->source_type === 'transactions' ? route('transactions.analysis') : route('datasets.index', ['period' => $currentPeriod]) }}">buka data dan hitung ulang</a>.</div>@endif

@include('calculations.steps')
<section class="card" id="ranking">
    <div class="card-header"><h2>5. Ranking Hasil Penilaian</h2><small>Diurutkan dari nilai MOORA tertinggi</small></div>
    <div class="card-body">
        <form method="GET" class="filter-row page-filter" action="{{ route('calculations.results', $run) }}"><label class="field">Cari barang<input type="search" name="q" value="{{ request('q') }}" placeholder="Kode atau nama barang"></label><button class="button" type="submit">Cari</button>@if(request('q'))<a class="button" href="{{ route('calculations.results', $run) }}">Reset</a>@endif</form>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Ranking</th><th>Nama Barang</th><th class="numeric">Nilai MOORA (Yi)</th><th></th></tr></thead>
                <tbody>
                @forelse($results as $result)
                    <tr>
                        <td><span class="rank">{{ $result->rank_system }}</span></td>
                        <td><strong>{{ $result->displayProductName() }}</strong><br><small>{{ $result->displayProductCode() }}</small></td>

                        <td class="numeric">{{ number_format((float) $result->yi_system, 6, ',', '.') }}</td>
                        <td><a class="button small" href="{{ route('calculations.show', [$run, $result]) }}">Detail Perhitungan</a></td>
                    </tr>
                @empty<tr><td colspan="4" class="empty-cell">Tidak ada barang yang sesuai dengan pencarian.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$results" />
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;margin-top:22px;color:var(--muted);font-size:11px"><span>Keputusan akhir restock tetap berada pada owner H2 Asia Swalayan.</span><x-pill tone="blue">{{ $run->period->monthlyLabel() }}</x-pill></div>
    </div>
</section>
@else
<section class="card"><x-empty-state title="Belum ada penilaian" description="Catat transaksi, lalu pilih rentang analisis dan hitung MOORA." /></section>
@endif
@endsection
