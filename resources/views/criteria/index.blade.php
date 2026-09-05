@extends('layouts.app')

@section('title', 'Kriteria dan Bobot')
@section('breadcrumb', 'Kriteria & Bobot')

@section('content')
<div class="page-heading">
    <div><h1>Kriteria dan Bobot</h1><p>Pengaturan jenis, bobot, dan sumber nilai yang digunakan untuk membuat rekomendasi.</p></div>
    @if(auth()->user()->isOwner())<button class="button primary" form="criteria-form" type="submit" data-criteria-submit>Simpan Konfigurasi</button>@endif
</div>

<form id="criteria-form" method="POST" action="{{ route('criteria.update') }}" @if(auth()->user()->isOwner()) data-criteria-form data-unsaved-form @endif>@csrf @method('PUT')<input type="hidden" name="_form" value="criteria"><input type="hidden" name="weight_unit" value="percent">
<section class="card">
    <div class="card-header"><h2>Konfigurasi Kriteria</h2><span class="pill {{ abs($criteria->where('active', true)->sum('weight') - 1) < .000001 ? 'green' : 'red' }}" data-criteria-status>{{ abs($criteria->where('active', true)->sum('weight') - 1) < .000001 ? 'Siap digunakan' : 'Periksa bobot' }}</span></div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Kode</th><th>Nama Kriteria</th><th>Jenis</th><th class="numeric">Bobot (%)</th><th>Sumber Nilai</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($criteria as $criterion)
                    <tr data-criterion-row>
                        <td><strong>{{ $criterion->code }}</strong></td>
                        <td><strong>{{ $criterion->name }}</strong></td>
                        <td>@if(auth()->user()->isOwner())<select aria-label="Jenis kriteria {{ $criterion->name }}" name="criteria[{{ $criterion->id }}][type]" data-criterion-type><option value="cost" @selected(old('criteria.'.$criterion->id.'.type', $criterion->type) === 'cost')>Cost</option><option value="benefit" @selected(old('criteria.'.$criterion->id.'.type', $criterion->type) === 'benefit')>Benefit</option></select>@else<x-pill :tone="$criterion->type === 'cost' ? 'red' : 'green'">{{ $criterion->type === 'cost' ? 'Cost' : 'Benefit' }}</x-pill>@endif</td>
                        <td class="numeric">@if(auth()->user()->isOwner())<input class="inline-input" aria-label="Bobot kriteria {{ $criterion->name }}" type="number" step="0.01" min="0.01" max="100" name="criteria[{{ $criterion->id }}][weight]" value="{{ old('criteria.'.$criterion->id.'.weight', (float)$criterion->weight * 100) }}" data-criterion-weight>@else<strong>{{ number_format((float) $criterion->weight * 100, 0) }}%</strong>@endif</td>
                        <td>{{ $criterion->source_description }}</td>
                        <td>@if(auth()->user()->isOwner())<input type="hidden" name="criteria[{{ $criterion->id }}][active]" value="0"><label class="checkbox"><input type="checkbox" name="criteria[{{ $criterion->id }}][active]" value="1" aria-label="Aktifkan kriteria {{ $criterion->name }}" data-criterion-active @checked(old('criteria.'.$criterion->id.'.active', $criterion->active))> Aktif</label>@else<x-pill :tone="$criterion->active ? 'green' : 'gray'">{{ $criterion->active ? 'Aktif' : 'Tidak aktif' }}</x-pill>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid-equal" style="margin-top:20px">
            <div class="notice success" style="margin:0" data-criteria-summary aria-live="polite"><strong>Total bobot = <span data-weight-total>{{ number_format($criteria->where('active', true)->sum('weight') * 100, 0) }}</span>%</strong><span data-weight-guidance>Konfigurasi hanya dapat digunakan jika total bobot kriteria aktif tepat 100%.</span></div>
            <div class="notice info" style="margin:0"><strong>{{ auth()->user()->isOwner() ? 'Arah penilaian rekomendasi' : 'Konfigurasi hanya-baca untuk Petugas' }}</strong>{{ auth()->user()->isOwner() ? 'Kriteria benefit meningkatkan prioritas, sedangkan kriteria cost menurunkannya. Rincian metode tersedia pada Detail Rekomendasi.' : 'Perubahan jenis, bobot, dan status kriteria dilakukan oleh Owner agar hasil rekomendasi tetap terkendali.' }}</div>
        </div>
    </div>
</section>
</form>
@endsection
