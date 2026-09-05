@extends('layouts.app')

@section('title', 'Supplier dan Lead Time')
@section('breadcrumb', 'Supplier')

@section('content')
<div class="page-heading">
    <div><h1>Supplier dan Lead Time</h1><p>Kelola sumber barang serta waktu tunggu yang menjadi dasar target stok dan estimasi kedatangan.</p></div>
    <div class="heading-actions"><button class="button primary" type="button" data-dialog-open="add-supplier" aria-controls="add-supplier" aria-expanded="false">+ Tambah Supplier</button></div>
</div>

<div class="notice info"><strong>Lead time memengaruhi saran pembelian berikutnya.</strong>Semakin lama waktu tunggu supplier, semakin besar stok yang perlu dicakup dalam perencanaan.</div>

<section class="card">
    <div class="card-header"><h2>Daftar Supplier</h2><small>{{ $suppliers->count() }} supplier tercatat</small></div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Supplier</th><th>Kontak</th><th class="numeric">Lead Time</th><th class="numeric">Barang</th><th class="numeric">Pesanan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($suppliers as $supplier)
                    <tr>
                        <td><strong>{{ $supplier->name }}</strong><br><small>{{ $supplier->address ?: 'Alamat belum dicatat' }}</small></td>
                        <td>{{ $supplier->phone ?: '—' }}</td>
                        <td class="numeric"><strong>{{ $supplier->lead_time_days }} hari</strong></td>
                        <td class="numeric">{{ $supplier->products_count }}</td>
                        <td class="numeric">{{ $supplier->purchase_orders_count }}</td>
                        <td><x-pill :tone="$supplier->active ? 'green' : 'gray'">{{ $supplier->active ? 'Aktif' : 'Nonaktif' }}</x-pill></td>
                        <td><div class="actions"><button class="button small" type="button" data-dialog-open="edit-supplier-{{ $supplier->id }}" aria-controls="edit-supplier-{{ $supplier->id }}" aria-expanded="false">Ubah</button>@if($supplier->active)<form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" data-confirm="Nonaktifkan supplier ini? Riwayat pesanan tetap tersimpan.">@csrf @method('DELETE')<button class="button small danger" type="submit">Nonaktifkan</button></form>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-cell">Belum ada supplier.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

@push('dialogs')
<dialog id="add-supplier" aria-labelledby="add-supplier-title">
    <div class="dialog-header"><h2 id="add-supplier-title">Tambah Supplier</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog tambah supplier">×</button></div>
    <form method="POST" action="{{ route('suppliers.store') }}" novalidate>@csrf
        <div class="dialog-body">@include('suppliers.partials.form', ['supplier' => null])<div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan Supplier</button></div></div>
    </form>
</dialog>
@foreach($suppliers as $supplier)
<dialog id="edit-supplier-{{ $supplier->id }}" aria-labelledby="edit-supplier-title-{{ $supplier->id }}">
    <div class="dialog-header"><h2 id="edit-supplier-title-{{ $supplier->id }}">Ubah Supplier: {{ $supplier->name }}</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog ubah supplier">×</button></div>
    <form method="POST" action="{{ route('suppliers.update', $supplier) }}" novalidate>@csrf @method('PUT')
        <div class="dialog-body">@include('suppliers.partials.form', ['supplier' => $supplier])<div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan Perubahan</button></div></div>
    </form>
</dialog>
@endforeach
@endpush
