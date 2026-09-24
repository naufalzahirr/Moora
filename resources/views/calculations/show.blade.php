@extends('layouts.app')

@section('title', 'Detail Penilaian MOORA')
@section('breadcrumb', 'Hasil Penilaian / Detail')

@section('content')
<div class="page-heading">
    <div><h1>Detail Penilaian MOORA</h1><p>Nilai awal, normalisasi, pembobotan, dan dasar peringkat rekomendasi.</p></div>
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
            <div class="metric"><small>PERINGKAT</small><strong>{{ $result->rank_system }} dari {{ $run->total_alternatives }}</strong></div>
        </div>
        <a class="button wide" style="margin-top:10px" href="{{ route('calculations.results', $run) }}">Kembali ke Hasil</a>
    </aside>
</div>
@endsection
