@extends('layouts.app')

@section('title', 'Arsip Laporan')
@section('breadcrumb', 'Arsip Laporan')

@section('content')
<div class="page-heading">
    <div><h1>Arsip Laporan</h1><p>Dokumen resmi dan riwayat rekomendasi yang telah dibuat untuk setiap data operasional.</p></div>

</div>

<section class="card">
    <div class="card-header"><h2>Riwayat Rekomendasi</h2><x-pill tone="blue">{{ $runs->total() }} rekomendasi tersimpan</x-pill></div>
    <div class="card-body">
        <form method="GET" class="filter-row" style="margin-bottom:16px">
            <label class="sr-only" for="report-search">Cari nama data</label><input id="report-search" type="search" name="q" value="{{ $search }}" placeholder="Cari nama data">
            <label class="filter-label" for="report-from">Periode mulai</label><input id="report-from" type="date" name="from" value="{{ $from }}">
            <label class="filter-label" for="report-until">Periode selesai</label><input id="report-until" type="date" name="until" value="{{ $until }}">
            <button class="button small" type="submit">Terapkan</button>@if(request()->hasAny(['q', 'from', 'until']))<a class="button small" href="{{ route('reports.index') }}">Reset</a>@endif
        </form>
        @if($runs->count())
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Data Operasional</th><th class="numeric">Barang</th><th>Dokumen Laporan</th><th></th></tr></thead>
                <tbody>
                @foreach($runs as $item)
                    <tr>
                        <td><strong>{{ $item->period->displayName() }}</strong><br><small>{{ $item->period->displayRange() }} · dibuat {{ $item->created_at->translatedFormat('d M Y H:i') }}</small></td>
                        <td class="numeric">{{ $item->total_alternatives }}</td>

                        <td><strong>{{ $item->report?->storage_path ? $item->report->document_name : 'Belum diarsipkan' }}</strong></td>
                        <td><div class="actions"><a class="button small" href="{{ route('calculations.results', $item) }}">Lihat Hasil</a><a class="button small" href="{{ route('exports.run', $item) }}" aria-label="Ekspor Excel {{ $item->period->displayName() }}">Excel</a><a class="button small primary" href="{{ route('reports.download', $item) }}" aria-label="Unduh PDF {{ $item->period->displayName() }}">Unduh PDF</a></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($runs->hasPages())
            <nav class="pagination" aria-label="Navigasi riwayat rekomendasi">
                @if($runs->onFirstPage())<span>‹</span>@else<a href="{{ $runs->previousPageUrl() }}">‹</a>@endif
                <span class="active">{{ $runs->currentPage() }}</span><span>dari {{ $runs->lastPage() }}</span>
                @if($runs->hasMorePages())<a href="{{ $runs->nextPageUrl() }}">›</a>@else<span>›</span>@endif
            </nav>
        @endif
        @else<x-empty-state title="Tidak ada laporan yang ditemukan" description="Ubah kata pencarian atau rentang tanggal. Laporan baru tersedia setelah rekomendasi dibuat." />@endif
    </div>
</section>

@if($run)
<section class="card pad" style="margin-top:16px">
        <h2 style="margin:3px 0 10px">{{ $run->period->displayName() }}</h2><p class="context-caption">{{ $run->period->displayRange() }} · Dibuat {{ $run->created_at->translatedFormat('d M Y H:i') }}</p>
        <div class="notice success" style="margin:16px 0"><strong>Rekomendasi tersimpan sebagai riwayat.</strong>Dokumen ini memuat dasar perhitungan, ranking, dan saran jumlah restock untuk {{ $run->total_alternatives }} barang.</div>
        @if($run->results->count())
            <div class="key-value"><span>Prioritas pertama</span><strong>{{ $run->results->first()->displayProductName() }}</strong></div>
            <div class="key-value"><span>Prioritas kedua</span><strong>{{ $run->results->get(1)?->displayProductName() ?? '—' }}</strong></div>
        @endif
        <div class="heading-actions" style="margin-top:16px"><a class="button" href="{{ route('exports.run', $run) }}" download>Ekspor Excel {{ $run->period->displayName() }}</a><a class="button primary" href="{{ route('reports.download', $run) }}" download>Unduh PDF {{ $run->period->displayName() }}</a></div>
        <div class="key-value"><span>Kriteria yang digunakan</span><strong>{{ collect($run->criteria_snapshot)->map(fn($criterion) => $criterion['name'])->join(' · ') }}</strong></div>
</section>
@endif
@endsection
