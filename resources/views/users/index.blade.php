@extends('layouts.app')

@section('title', 'Manajemen Pengguna')
@section('breadcrumb', 'Pengguna')

@section('content')
<div class="page-heading">
    <div><h1>Manajemen Pengguna</h1><p>Kelola akses Owner dan Petugas Persediaan.</p></div>
    <div class="heading-actions"><button class="button primary" type="button" data-dialog-open="add-user" aria-controls="add-user" aria-expanded="false">+ Tambah Pengguna</button></div>
</div>

<section class="card">
    <div class="card-header"><h2>Daftar Pengguna</h2><x-pill tone="blue">{{ $users->total() }} pengguna</x-pill></div>
    <div class="card-body">
        @if($users->count())
            <div class="table-wrap"><table class="responsive-table"><thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th></th></tr></thead><tbody>@foreach($users as $user)<tr><td><strong>{{ $user->name }}</strong><br><small>{{ $user->email ?? 'Email belum diatur' }}</small></td><td>{{ $user->username }}</td><td><x-pill :tone="$user->role === 'owner' ? 'blue' : 'gray'">{{ $user->roleLabel() }}</x-pill></td><td><x-pill :tone="$user->active ? 'green' : 'red'">{{ $user->active ? 'Aktif' : 'Nonaktif' }}</x-pill></td><td><div class="actions"><button class="button small" type="button" data-dialog-open="edit-user-{{ $user->id }}" aria-controls="edit-user-{{ $user->id }}" aria-expanded="false">Ubah</button>@if($user->active)<form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="Nonaktifkan akun {{ $user->username }}?">@csrf @method('DELETE')<button class="button small danger" type="submit">Nonaktifkan</button></form>@endif</div></td></tr>@endforeach</tbody></table></div>
        @else
            <x-empty-state title="Belum ada pengguna" description="Tambahkan akun pengguna untuk memberikan akses ke sistem." />
        @endif
        @if($users->hasPages())<nav class="pagination" aria-label="Navigasi pengguna">@if($users->onFirstPage())<span>‹</span>@else<a href="{{ $users->previousPageUrl() }}">‹</a>@endif<span class="active">{{ $users->currentPage() }}</span><span>dari {{ $users->lastPage() }}</span>@if($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}">›</a>@else<span>›</span>@endif</nav>@endif
    </div>
</section>
@endsection

@push('dialogs')
<dialog id="add-user" aria-labelledby="add-user-title"><div class="dialog-header"><h2 id="add-user-title">Tambah Pengguna</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog tambah pengguna">×</button></div><form method="POST" action="{{ route('users.store') }}" data-unsaved-form novalidate>@csrf<div class="dialog-body">@include('users.partials.form', ['managedUser' => null, 'passwordRequired' => true])<div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan Pengguna</button></div></div></form></dialog>
@foreach($users as $user)
<dialog id="edit-user-{{ $user->id }}" aria-labelledby="edit-user-title-{{ $user->id }}"><div class="dialog-header"><h2 id="edit-user-title-{{ $user->id }}">Ubah Pengguna: {{ $user->username }}</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Tutup dialog ubah {{ $user->username }}">×</button></div><form method="POST" action="{{ route('users.update', $user) }}" data-unsaved-form novalidate>@csrf @method('PUT')<div class="dialog-body">@include('users.partials.form', ['managedUser' => $user, 'passwordRequired' => false])<div class="form-footer"><button class="button" type="button" data-dialog-close>Batal</button><button class="button primary" type="submit">Simpan Perubahan</button></div></div></form></dialog>
@endforeach
@endpush
