@extends('layouts.app')

@section('title', 'Data Operasional')
@section('breadcrumb', 'Data Operasional')

@section('content')
<div class="page-heading">
    <div><h1>Data Operasional</h1><p>Impor penjualan terbaru, lengkapi stok akhir, lalu buat rekomendasi restock.</p></div>
    <div class="heading-actions"><button class="button" type="button" data-dialog-open="new-period" aria-controls="new-period" aria-expanded="false">+ Input Manual</button><button class="button primary" type="button" data-dialog-open="import-dataset" aria-controls="import-dataset" aria-expanded="false">Impor Data Penjualan</button></div>
</div>

<x-workflow :step="1" :period="$period" :run="$period?->runs()->latest('id')->first()" />
@if($period)
@php($isLocked = $period->isLocked())
<div class="filter-row" style="margin-bottom:14px">
    <form class="context-switcher" method="GET"><label class="filter-label" for="dataset-period">Data operasional</label><select id="dataset-period" name="period" data-auto-submit>@foreach($periods as $item)<option value="{{ $item->id }}" @selected($item->id === $period->id)>{{ $item->displayName() }} · {{ $item->displayRange() }}</option>@endforeach</select></form>
</div>

<section class="card source-card">
    <span class="file-icon">{{ $period->source_file ? strtoupper(pathinfo($period->source_file, PATHINFO_EXTENSION)) : 'FORM' }}</span>
    @php($reportCriteria = $criteria->whereIn('value_source', ['sold_quantity', 'sales_value'])->pluck('code')->join(' dan '))
    <div class="source-copy"><strong>{{ $period->source_file ?? 'Input data secara manual' }}</strong><small>Rentang data {{ $period->displayRange() }} · Nilai {{ $reportCriteria }} {{ $period->source_file ? 'berhasil dibaca' : 'diisi pengguna' }}</small></div>
    <div class="source-actions"><x-pill :tone="$period->status === 'draft' ? 'gray' : 'green'">{{ $isLocked ? 'Tersimpan' : ($period->status === 'draft' ? 'Perlu dilengkapi' : 'Siap dibuatkan rekomendasi') }}</x-pill>@if($period->source_path)<a class="button small" href="{{ route('datasets.source.download', $period) }}">Unduh Berkas Sumber</a>@endif @if($isLocked)<form method="POST" action="{{ route('datasets.revise', $period) }}" data-confirm="Buat pembaruan dari data ini? Data dan rekomendasi yang tersimpan tidak akan berubah.">@csrf<button class="button" type="submit">Buat Pembaruan</button></form>@elseif($period->revision_of_id && auth()->user()->role === 'owner')<form method="POST" action="{{ route('datasets.revise.discard', $period) }}" data-confirm="Batalkan pembaruan ini? Perubahan yang belum dibuatkan rekomendasi akan dihapus, sedangkan riwayat asli tetap aman.">@csrf @method('DELETE')<button class="button small danger" type="submit">Batalkan Pembaruan</button></form>@endif</div>
</section>

@if($isLocked)
<div class="notice info" style="margin-top:16px"><strong>Data sudah tersimpan sebagai riwayat.</strong> Data dan hasil tetap dapat ditinjau, tetapi tidak dapat diubah. Gunakan <b>Buat Pembaruan</b> bila ditemukan koreksi.</div>
@else
<div class="notice info" style="margin-top:16px"><strong>Alur kerja data.</strong> Gunakan <b>Simpan Draft</b> untuk menyimpan isian sebagian. Setelah seluruh data lengkap, pilih <b>Simpan &amp; Buat Rekomendasi</b> untuk langsung membuat prioritas restock.</div>
@endif

<form method="POST" action="{{ route('datasets.update') }}" style="margin-top:16px" data-dataset-form @unless($isLocked) data-unsaved-form @endunless novalidate>@csrf @method('PUT')
    <input type="hidden" name="_form" value="dataset"><input type="hidden" name="page" value="{{ $sales->currentPage() }}">
    <input type="hidden" name="period_id" value="{{ $period->id }}">
    <section class="card">
        <div class="card-header"><h2>Data Penjualan dan Stok</h2><small>{{ $sales->total() }} barang · maksimal 50 baris per halaman</small></div>
        <div class="card-body">
            @if($sales->count())
            <div class="table-wrap">
                <table class="responsive-table">
                    <thead><tr><th>Nama Barang</th>@foreach($criteria as $criterion)<th class="numeric">{{ $criterion->code }} · {{ $criterion->name }}</th>@endforeach<th>Status</th></tr></thead>
                    <tbody>
                    @foreach($sales as $sale)
                        @php($stock = $stocks->get($sale->product_id))
                        @php($rowComplete = $criteria->every(fn($criterion) => $criterion->valueFor($sale, $stock) !== null))
                        <tr data-dataset-row>
                            <td><strong>{{ $sale->product->name }}</strong><br><small>{{ $sale->product->code }}</small></td>
                            @foreach($criteria as $criterion)
                                @php($value = $criterion->valueFor($sale, $stock))
                                <td class="numeric">@if($criterion->value_source === 'sales_value')<input class="inline-input currency-input" aria-label="{{ $criterion->accessibleName() }} {{ $sale->product->name }}" type="text" inputmode="numeric" name="rows[{{ $sale->product_id }}][{{ $criterion->value_source }}]" value="{{ old('rows.'.$sale->product_id.'.'.$criterion->value_source, $value === null ? null : number_format((float) $value, 0, ',', '.')) }}" required data-required-value data-currency-input @if($isLocked) readonly aria-readonly="true" @endif><small class="currency-preview" data-currency-preview>{{ $value === null ? 'Rp—' : 'Rp'.number_format((float) $value, 0, ',', '.') }}</small>@else<input class="inline-input" aria-label="{{ $criterion->accessibleName() }} {{ $sale->product->name }}" type="number" step="{{ $sale->product->quantityStep() }}" min="0" name="rows[{{ $sale->product_id }}][{{ $criterion->value_source }}]" value="{{ old('rows.'.$sale->product_id.'.'.$criterion->value_source, $value === null ? null : ($sale->product->usesWholeUnits() ? number_format((float) $value, 0, '.', '') : $value)) }}" required data-required-value @if($isLocked) readonly aria-readonly="true" @endif>@endif @error('rows.'.$sale->product_id.'.'.$criterion->value_source)<small class="field-error">{{ $message }}</small>@enderror</td>
                            @endforeach
                            <td><span class="pill {{ $rowComplete ? 'green' : 'gray' }}" data-row-status>{{ $rowComplete ? 'Valid' : 'Lengkapi data' }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="table-scroll-hint" aria-hidden="true">Geser tabel ke kanan untuk melihat seluruh nilai dan aksi.</p>
            <div class="form-footer sticky-actions dataset-actions">
                <span class="pill {{ $completeCount === $sales->total() ? 'green' : 'gray' }}" data-complete-summary data-total="{{ $sales->total() }}" data-complete-outside="{{ $completeCount - $sales->filter(fn($sale) => $criteria->every(fn($criterion) => $criterion->valueFor($sale, $stocks->get($sale->product_id)) !== null))->count() }}">{{ $completeCount }} dari {{ $sales->total() }} baris lengkap</span>
                @if($isLocked)<span class="pill gray">Buat pembaruan untuk mengubah data</span>@else<div class="heading-actions"><button class="button" type="submit" name="next" value="stay">Simpan Draft</button><button class="button success" type="submit" name="next" value="calculate">Simpan &amp; Buat Rekomendasi</button></div>@endif
            </div>
            <span class="save-state" data-save-state role="status"></span>
            <x-pagination :paginator="$sales" />
            @else
                <x-empty-state title="Belum ada barang pada data ini" description="Impor laporan penjualan atau tambahkan barang lalu buat input manual." />
            @endif
        </div>
    </section>
</form>
@else
<section class="card"><x-empty-state title="Belum ada data operasional" description="Buat input manual atau impor laporan penjualan untuk memulai." /></section>
@endif
@endsection

@push('dialogs')
<dialog id="import-dataset" aria-labelledby="import-dataset-title">
    <div class="dialog-header"><h2 id="import-dataset-title">Impor Data Penjualan</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog impor laporan">×</button></div>
    <form method="POST" action="{{ route('datasets.import') }}" enctype="multipart/form-data" data-date-range data-unsaved-form novalidate>@csrf<input type="hidden" name="_form" value="import-dataset">
        <div class="dialog-body">
            @php($dialogValue = fn($key, $default = null) => old('_form') === 'import-dataset' ? old($key, $default) : $default)
            @if(old('_form') === 'import-dataset')<x-form-errors :bag="$errors" />@endif
            <div class="form-grid">
                <label class="field full">NAMA DATA<input name="name" value="{{ $dialogValue('name', 'Data Penjualan '.now()->translatedFormat('F Y')) }}" required></label>
                <label class="field">TANGGAL AWAL<input type="date" name="start_date" value="{{ $dialogValue('start_date', now()->startOfMonth()->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required></label>
                <label class="field">TANGGAL AKHIR<input type="date" name="end_date" value="{{ $dialogValue('end_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                <label class="field full file-drop">BERKAS CSV, XLS, ATAU XLSX (MAKS. 10 MB)<span data-file-name="sales">Belum ada berkas dipilih</span><input type="file" name="file" accept=".csv,.xls,.xlsx" data-file-input="sales" required></label>
            </div>
            <div class="notice info" style="margin-top:16px"><strong>Kolom yang dikenali</strong>Kode/no. barang, nama/deskripsi barang, jumlah terjual/Kts. Standar, dan nilai penjualan/Nilai barang. Gunakan <a href="{{ route('datasets.template.download') }}"><b>template CSV</b></a> agar susunan kolom sesuai. Maksimal 10.000 baris per impor; seluruh data divalidasi sebelum disimpan.</div>
            <div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Impor dan Tinjau Data</button></div>
        </div>
    </form>
</dialog>

<dialog id="new-period" aria-labelledby="new-period-title">
    <div class="dialog-header"><h2 id="new-period-title">Buat Input Manual</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog input manual">×</button></div>
    <form method="POST" action="{{ route('datasets.periods.store') }}" data-date-range data-unsaved-form novalidate>@csrf<input type="hidden" name="_form" value="new-period">
        <div class="dialog-body">
            @php($dialogValue = fn($key, $default = null) => old('_form') === 'new-period' ? old($key, $default) : $default)
            @if(old('_form') === 'new-period')<x-form-errors :bag="$errors" />@endif
            <div class="form-grid">
                <label class="field full">NAMA DATA<input name="name" value="{{ $dialogValue('name', 'Data Penjualan '.now()->translatedFormat('F Y')) }}" placeholder="Contoh: Rekap penjualan bulan ini" required></label>
                <label class="field">TANGGAL AWAL<input type="date" name="start_date" value="{{ $dialogValue('start_date', now()->startOfMonth()->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required></label>
                <label class="field">TANGGAL AKHIR<input type="date" name="end_date" value="{{ $dialogValue('end_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
            </div>
            <div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Buat Data</button></div>
        </div>
    </form>
</dialog>
@endpush
