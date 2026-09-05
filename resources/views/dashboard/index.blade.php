@extends('layouts.app')

@section('title', 'Dashboard Restock')
@section('breadcrumb', 'Dashboard')

@section('content')
<div class="page-heading">
    <div>
        <h1>Dashboard Restock</h1>
        <p>Pantau prioritas restock, kelengkapan data, dan rekomendasi terbaru dalam satu tempat.</p>
    </div>
    <div class="heading-actions">
        <a class="button primary" href="{{ $run ? route('restock-actions.index', ['run' => $run]) : route('datasets.index', $period ? ['period' => $period] : []) }}">{{ $run ? 'Tindak Lanjut Restock' : ($period ? 'Lengkapi Data' : 'Mulai Input Data') }}</a>
    </div>
</div>

@if($pendingPeriod && $run)
    <div class="notice info"><strong>Ada data operasional baru yang belum dihitung.</strong> Lengkapi dan periksa data terbaru sebelum membuat rekomendasi berikutnya. <a href="{{ route('datasets.index', ['period' => $pendingPeriod]) }}"><b>Lengkapi Data</b></a>.</div>
@endif
@if($period)
    <div class="notice {{ $freshness['is_stale'] ? 'error' : 'info' }}"><strong>{{ $freshness['is_stale'] ? 'Data perlu diperbarui.' : 'Data masih mutakhir.' }}</strong> Cakupan data sampai {{ $period->end_date->translatedFormat('d M Y') }} ({{ $freshness['days_old'] }} hari lalu){{ $run ? ' · rekomendasi dibuat '.$run->created_at->translatedFormat('d M Y H:i') : '' }}.</div>
@endif

<div class="stats-grid">
    <div class="stat-card"><span class="stat-icon">BRG</span><div><small>BARANG TERCATAT</small><strong>{{ $productCount }} Barang</strong></div></div>
    <div class="stat-card"><span class="stat-icon">KRT</span><div><small>KRITERIA DIGUNAKAN</small><strong>{{ $dashboardCriteria->count() }} Kriteria</strong></div></div>
    @if($minimumStockUnconfiguredCount)
        <div class="stat-card"><span class="stat-icon red">{{ $minimumStockUnconfiguredCount }}</span><div><small>MINIMUM BELUM DIATUR</small><strong>{{ $minimumStockUnconfiguredCount }} Barang</strong></div></div>
    @else
        <div class="stat-card"><span class="stat-icon {{ $lowStockCount ? 'red' : 'green' }}">{{ $lowStockCount }}</span><div><small>STOK DI BAWAH MINIMUM</small><strong>{{ $lowStockCount }} Barang</strong></div></div>
    @endif
    <div class="stat-card"><span class="stat-icon blue">{{ $incomingProductCount }}</span><div><small>BARANG DALAM JALAN</small><strong>{{ $incomingProductCount }} Barang</strong></div></div>
</div>

<section class="card dashboard-recommendations">
        <div class="card-header"><div><h2>Prioritas Restock Saat Ini</h2><small>Barang yang paling perlu ditindaklanjuti</small></div>@if($run)<a class="button small" href="{{ route('calculations.results', $run) }}">Lihat Semua Rekomendasi</a>@endif</div>
        @if($run)
            <div class="card-body">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Prioritas</th><th>Nama Barang</th><th class="numeric">Saran Restock</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($run->results->take(5) as $result)
                            <tr>
                                <td><span class="rank">{{ $result->rank_system }}</span></td>
                                <td><strong>{{ $result->displayProductName() }}</strong></td>
                                <td class="numeric">@if($result->restock_quantity !== null)<strong>{{ $result->product?->formatQuantity($result->restock_quantity, true) ?? '—' }}</strong><br><small>Target {{ $result->product?->formatQuantity($result->restock_target) ?? '—' }} · tersedia {{ $result->product?->formatQuantity($result->onHandAtCalculation($run->criteria_snapshot)) ?? '—' }}</small>@else—@endif</td>
                                <td>@if($result->restockAction)<x-pill :tone="$result->restockAction->tone()">{{ $result->restockAction->label() }}</x-pill>@else<x-pill tone="gray">Belum ditinjau</x-pill>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            <div class="summary-box">
                <strong>Butuh tindak lanjut?</strong>
                Buka seluruh rekomendasi untuk menyiapkan usulan, persetujuan, dan pesanan pembelian per supplier.
            </div>
            </div>
        @else
            <x-empty-state title="Belum ada rekomendasi" description="Lengkapi Data Operasional lalu buat rekomendasi restock." />
        @endif
    </section>
@endsection
