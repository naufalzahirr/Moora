@extends('layouts.app')

@section('title', 'Detail Rekomendasi MOORA')
@section('breadcrumb', 'Hasil Rekomendasi / Detail')

@section('content')
<div class="page-heading">
    <div><h1>Detail Rekomendasi MOORA</h1><p>Nilai awal, normalisasi, pembobotan, dan dasar peringkat rekomendasi.</p></div>
    <div class="heading-actions"><button class="button primary" type="button" data-print-page>Cetak Detail</button></div>
</div>

<div class="grid-main">
    <section class="card">
        <div class="card-header"><h2>Alternatif {{ $result->alternative_code }} · {{ $result->displayProductName() }}</h2><small>Kode {{ $result->displayProductCode() }}</small></div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Kode</th><th>Kriteria</th><th class="numeric">Nilai Awal</th><th class="numeric">Normalisasi</th><th class="numeric">Bobot</th><th class="numeric">Nilai Terbobot</th><th>Jenis</th></tr></thead>
                    <tbody>
                    @foreach($criteria as $code => $criterion)
                        <tr>
                            <td><strong>{{ $code }}</strong></td><td><strong>{{ $criterion['name'] }}</strong></td>
                            <td class="numeric">{{ $criterion['source'] === 'sales_value' ? 'Rp'.number_format($result->raw_values[$code], 0, ',', '.') : number_format($result->raw_values[$code], 2, ',', '.') }}</td>
                            <td class="numeric">{{ number_format($result->normalized_values[$code], 4, ',', '.') }}</td>
                            <td class="numeric">{{ number_format($criterion['weight'], 2, ',', '.') }}</td>
                            <td class="numeric"><strong>{{ number_format($result->weighted_values[$code], 4, ',', '.') }}</strong></td>
                            <td><x-pill :tone="$criterion['type'] === 'cost' ? 'red' : 'green'">{{ $criterion['type'] }}</x-pill></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @php($benefit = $criteria->where('type', 'benefit')->keys()->map(fn($code) => $result->weighted_values[$code]))
            @php($cost = $criteria->where('type', 'cost')->keys()->map(fn($code) => $result->weighted_values[$code]))
            <div class="formula-box"><small>PERHITUNGAN NILAI OPTIMASI</small><strong>Yi = Σ Benefit − Σ Cost</strong><strong>Yi = ({{ $benefit->map(fn($v) => number_format($v, 4, ',', '.'))->join(' + ') }}) − ({{ $cost->map(fn($v) => number_format($v, 4, ',', '.'))->join(' + ') }}) = {{ number_format((float)$result->yi_system, 4, ',', '.') }}</strong></div>
            <div class="notice info" style="margin:18px 0 0"><strong>Keterangan perhitungan</strong>Kriteria benefit dijumlahkan. Kriteria cost dikurangkan dari total benefit untuk memperoleh nilai optimasi Yi.</div>
        </div>
    </section>

    <aside class="card pad">
        <h2 style="margin:3px 0 18px">Hasil Alternatif</h2>
        <div class="result-stack">
            <div class="metric"><small>NILAI YI</small><strong>{{ number_format((float)$result->yi_system, 4, ',', '.') }}</strong></div>
            <div class="metric"><small>SARAN RESTOCK</small><strong>{{ $result->product?->formatQuantity($result->restock_quantity, true) ?? '—' }}</strong></div>
            <div class="metric"><small>PERINGKAT</small><strong>{{ $result->rank_system }} dari {{ $run->total_alternatives }}</strong></div>
        </div>
        @if($result->restock_basis)
            <div class="notice info" style="margin:14px 0">
                <strong>Dasar jumlah restock</strong>
                @if(($result->restock_basis['source'] ?? null) === 'historical_period_snapshot')
                    Rekomendasi historis ini memakai stok akhir yang tersimpan pada data operasional: {{ $result->product?->formatQuantity($result->onHandAtCalculation($run->criteria_snapshot), true) ?? '—' }}. Data stok berjalan saat ini tidak mengubah hasil perhitungan lama.
                @else
                    Rata-rata {{ number_format((float) ($result->restock_basis['average_daily_demand'] ?? 0), $result->product?->usesWholeUnits() ? 0 : 2, ',', '.') }} {{ $result->product?->unit }}/hari · stok tersedia {{ $result->product?->formatQuantity($result->onHandAtCalculation($run->criteria_snapshot)) ?? '—' }} · pesanan masuk {{ $result->product?->formatQuantity((float) ($result->restock_basis['incoming'] ?? 0)) ?? '—' }}.<br>
                    Target menghitung lead time {{ $result->restock_basis['lead_time_days'] ?? 0 }} hari, masa tinjau {{ $result->restock_basis['review_period_days'] ?? 0 }} hari, dan safety stock {{ $result->product?->formatQuantity((float) ($result->restock_basis['safety_stock'] ?? 0)) ?? '—' }}.
                @endif
            </div>
        @endif
        <div class="notice info" style="margin:14px 0"><strong>Status tindak lanjut: {{ $result->restockAction?->label() ?? 'Belum ditinjau' }}</strong>{{ $result->restockAction?->notes ?: 'Tentukan jumlah keputusan dan status pesanan pada menu Tindak Lanjut Restock.' }}</div>
        <a class="button primary wide" href="{{ route('restock-actions.index', ['run' => $run]) }}">Tindak Lanjut Restock</a>
        <a class="button wide" style="margin-top:10px" href="{{ route('calculations.results', $run) }}">Kembali ke Hasil</a>
    </aside>
</div>
@endsection
