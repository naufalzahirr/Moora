<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $report->document_name }}</title>
<style>
    @page { size: A4 landscape; margin: 32px 38px; }
    body { font-family: DejaVu Sans, sans-serif; color:#172136; font-size:9px; }
    h1 { margin:0; color:#0b3852; font-size:21px; }
    h2 { margin:20px 0 8px; color:#0b3852; font-size:13px; }
    p { margin:4px 0; color:#566a80; }
    .header { border-bottom:3px solid #1681bf; padding-bottom:12px; margin-bottom:14px; }
    .brand { float:right; text-align:right; color:#0b3852; font-weight:bold; font-size:15px; }
    .meta { width:100%; margin:12px 0; border-collapse:collapse; }
    .meta td { width:25%; padding:8px; border:1px solid #cfdeeb; }
    .meta small { display:block; color:#60718d; text-transform:uppercase; font-size:7px; }
    .meta strong { display:block; margin-top:4px; font-size:10px; }
    table.data { width:100%; border-collapse:collapse; }
    table.data th { background:#e8f0f6; color:#344e65; font-size:7px; text-transform:uppercase; padding:7px 5px; border:1px solid #cfdeeb; }
    table.data td { padding:7px 5px; border:1px solid #d9e5ee; }
    .num { text-align:right; }
    .center { text-align:center; }
    .good { color:#00775d; font-weight:bold; }
    .bad { color:#a43a40; font-weight:bold; }
    .note { margin-top:14px; padding:10px; border:1px solid #a9e2d3; background:#e5f8f3; color:#00735b; }
    .footer { margin-top:10px; text-align:center; color:#8293a4; font-size:7px; }
    caption { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
</style>
</head>
<body>
<main>
<div class="header"><div class="brand">H2 ASIA<br><span style="font-size:8px">MOORA DECISION SUPPORT</span></div><h1>Laporan Penilaian Restock MOORA</h1><p>Sistem Pendukung Keputusan Penentuan Restock Barang</p></div>
<table class="meta"><caption>Identitas laporan</caption><tr><td><small>Rentang Data</small><strong>{{ $run->period->displayRange() }}</strong></td><td><small>Perhitungan</small><strong>#{{ $run->id }} - {{ $run->created_at->translatedFormat('d M Y H:i') }}</strong></td><td><small>Alternatif</small><strong>{{ $run->total_alternatives }} barang</strong></td><td><small>Status</small><strong>Selesai dihitung</strong></td></tr></table>

<h2>Matriks Keputusan, Normalisasi, dan Pembobotan</h2>
<table class="data">
    <caption>Matriks keputusan, normalisasi, dan pembobotan setiap alternatif</caption>
    <thead><tr><th scope="col">Alt.</th><th scope="col">Nama Barang</th>@foreach($run->criteria_snapshot as $criterion)<th scope="col">{{ $criterion['code'] }} Awal</th><th scope="col">{{ $criterion['code'] }} Norm.</th><th scope="col">{{ $criterion['code'] }} Bobot</th>@endforeach</tr></thead>
    <tbody>@foreach($run->results->sortBy('alternative_code') as $result)<tr><th scope="row" class="center">{{ $result->alternative_code }}</th><td>{{ $result->displayProductName() }}</td>@foreach($run->criteria_snapshot as $criterion)<td class="num">{{ number_format($result->raw_values[$criterion['code']], $criterion['source'] === 'sales_value' ? 0 : 2, ',', '.') }}</td><td class="num">{{ number_format($result->normalized_values[$criterion['code']], 4, ',', '.') }}</td><td class="num">{{ number_format($result->weighted_values[$criterion['code']], 4, ',', '.') }}</td>@endforeach</tr>@endforeach</tbody>
</table>

<h2>Nilai Yi dan Ranking</h2>
<table class="data">
    <caption>Nilai optimasi dan peringkat MOORA</caption>
    <thead><tr><th scope="col">Rank</th><th scope="col">Alt.</th><th scope="col">Nama Barang</th><th scope="col">Yi Sistem</th></tr></thead>
    <tbody>@foreach($run->results->sortBy('rank_system') as $result)<tr><th scope="row" class="center">{{ $result->rank_system }}</th><td class="center">{{ $result->alternative_code }}</td><td>{{ $result->displayProductName() }}</td><td class="num">{{ number_format((float)$result->yi_system, 4, ',', '.') }}</td></tr>@endforeach</tbody>
</table>

<div class="note"><strong>Keterangan:</strong> Nilai Yi adalah jumlah nilai benefit terbobot dikurangi jumlah nilai cost terbobot. Ranking diurutkan dari nilai Yi tertinggi. Ranking merupakan bahan pertimbangan restock, bukan keputusan otomatis perlu atau tidak perlu restock. Keputusan akhir berada pada pemilik toko.</div>
<div class="footer">Dihasilkan oleh SPK Restock H2 Asia pada {{ $report->generated_at->translatedFormat('d F Y H:i') }} · {{ $report->document_name }}</div>
</main>
</body>
</html>
