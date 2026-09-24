@php
    $snapshotCriteria = collect($run->criteria_snapshot);
    $fmt = fn($value) => number_format((float) $value, 6, ',', '.');
@endphp
<div class="notice info"><strong>Cara membaca perhitungan</strong>Ikuti langkah 1–5 di bawah. Semua angka memakai data dan bobot yang tersimpan saat perhitungan ini dibuat. Tampilan dibulatkan 6 desimal; perhitungan memakai presisi penuh. Penyebut selalu memakai seluruh {{ $calculationRows->count() }} barang, termasuk saat tabel ranking difilter.</div>
<section class="card" style="margin:18px 0"><div class="card-header"><h2>1. Data Awal dan Bobot Kriteria</h2></div><div class="card-body">
<p>Baris adalah alternatif (barang), kolom adalah kriteria. Cost dikurangkan; benefit dijumlahkan.</p>
<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Kriteria</th><th>Jenis</th><th>Bobot</th></tr></thead><tbody>@foreach($snapshotCriteria as $criterion)<tr><td>{{ $criterion['code'] }}</td><td>{{ $criterion['name'] }}</td><td>{{ $criterion['type'] }}</td><td>{{ $fmt($criterion['weight']) }} ({{ number_format($criterion['weight'] * 100, 2, ',', '.') }}%)</td></tr>@endforeach</tbody></table></div>
<div class="table-wrap"><table><thead><tr><th>Alternatif</th><th>Barang</th>@foreach($snapshotCriteria as $criterion)<th>{{ $criterion['code'] }} · {{ $criterion['name'] }}</th>@endforeach</tr></thead><tbody>@foreach($calculationRows as $row)<tr><td>{{ $row->alternative_code }}</td><td>{{ $row->displayProductName() }}</td>@foreach($snapshotCriteria as $criterion)<td>{{ number_format($row->raw_values[$criterion['code']], 2, ',', '.') }}</td>@endforeach</tr>@endforeach</tbody></table></div>
</div></section>
<section class="card" style="margin:18px 0"><div class="card-header"><h2>2. Normalisasi Matriks</h2></div><div class="card-body">
<div class="formula-box"><strong>rᵢⱼ = xᵢⱼ ÷ √(Σₖ xₖⱼ²)</strong><span>i = barang, j = kriteria, k = seluruh barang. Jumlahkan kuadrat nilai pada kolom yang sama, lalu ambil akar kuadratnya.</span></div>
@foreach($snapshotCriteria as $criterion)
@php
$code = $criterion['code'];
@endphp

<details style="margin:14px 0"><summary><b>Penyebut {{ $code }} = {{ $fmt($run->normalization_divisors[$code]) }}</b> · lihat substitusi angka</summary><p style="overflow-wrap:anywhere">√({{ $calculationRows->map(fn($row) => '('.$fmt($row->raw_values[$code]).')²')->join(' + ') }}) = {{ $fmt($run->normalization_divisors[$code]) }}</p></details>
@endforeach
<div class="table-wrap"><table><thead><tr><th>Barang</th>@foreach($snapshotCriteria as $criterion)<th>{{ $criterion['code'] }}: nilai ÷ penyebut = normalisasi</th>@endforeach</tr></thead><tbody>@foreach($calculationRows as $row)<tr><td>{{ $row->alternative_code }} · {{ $row->displayProductName() }}</td>@foreach($snapshotCriteria as $criterion)@php
$code = $criterion['code'];
@endphp
<td>{{ $fmt($row->raw_values[$code]) }} ÷ {{ $fmt($run->normalization_divisors[$code]) }} = <b>{{ $fmt($row->normalized_values[$code]) }}</b></td>@endforeach</tr>@endforeach</tbody></table></div>
</div></section>
<section class="card" style="margin:18px 0"><div class="card-header"><h2>3. Pembobotan</h2></div><div class="card-body"><div class="formula-box"><strong>vᵢⱼ = wⱼ × rᵢⱼ</strong><span>Kalikan nilai normalisasi dengan bobot kriteria, sekali saja.</span></div>
<div class="table-wrap"><table><thead><tr><th>Barang</th>@foreach($snapshotCriteria as $criterion)<th>{{ $criterion['code'] }}: bobot × normalisasi = terbobot</th>@endforeach</tr></thead><tbody>@foreach($calculationRows as $row)<tr><td>{{ $row->alternative_code }} · {{ $row->displayProductName() }}</td>@foreach($snapshotCriteria as $criterion)@php
$code = $criterion['code'];
@endphp
<td>{{ $fmt($criterion['weight']) }} × {{ $fmt($row->normalized_values[$code]) }} = <b>{{ $fmt($row->weighted_values[$code]) }}</b></td>@endforeach</tr>@endforeach</tbody></table></div>
</div></section>
<section class="card" style="margin:18px 0"><div class="card-header"><h2>4. Nilai Optimasi Yi</h2></div><div class="card-body"><div class="formula-box"><strong>Yi = Σ v benefit − Σ v cost</strong><span>Nilai negatif sah. Hasil ini bukan probabilitas atau persentase kebutuhan restock.</span></div>
<div class="table-wrap"><table><thead><tr><th>Barang</th><th>Substitusi angka benefit − cost</th><th>Yi</th></tr></thead><tbody>@foreach($calculationRows as $row)
@php
$benefits = $snapshotCriteria->where('type', 'benefit')->map(fn($c) => $fmt($row->weighted_values[$c['code']]))->join(' + ');
$costs = $snapshotCriteria->where('type', 'cost')->map(fn($c) => $fmt($row->weighted_values[$c['code']]))->join(' + ');
@endphp
<tr><td>{{ $row->alternative_code }} · {{ $row->displayProductName() }}</td><td>({{ $benefits ?: '0' }}) − ({{ $costs ?: '0' }})</td><td><b>{{ $fmt($row->yi_system) }}</b></td></tr>@endforeach</tbody></table></div>
<p>Langkah berikutnya: urutkan Yi dari terbesar ke terkecil. Jika selisih Yi tidak lebih dari 0,0000000001, aplikasi memakai urutan kode barang sebagai pembeda urutan, bukan perbedaan kelayakan.</p>
</div></section>
