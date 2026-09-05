@extends('layouts.app')

@section('title', 'Log Aktivitas')
@section('breadcrumb', 'Log Aktivitas')

@section('content')
<div class="page-heading">
    <div><h1>Log Aktivitas</h1><p>Jejak perubahan dan tindakan penting dalam sistem.</p></div>
    <div class="heading-actions"><x-pill tone="blue">{{ $logs->total() }} aktivitas</x-pill><a class="button" href="{{ route('exports.activities') }}">Ekspor Excel</a></div>
</div>

<section class="card">
    <div class="card-header"><div><h2>Riwayat Aktivitas</h2><small>Gunakan filter untuk menemukan perubahan tertentu.</small></div></div>
    <div class="card-body">
        <form method="GET" class="filter-row" style="margin-bottom:16px">
            <label class="sr-only" for="activity-search">Cari aktivitas</label><input id="activity-search" type="search" name="q" value="{{ $search }}" placeholder="Cari tindakan atau keterangan">
            <label class="sr-only" for="activity-action">Filter jenis aktivitas</label><select id="activity-action" name="action"><option value="">Semua aktivitas</option>@foreach($actions as $action => $label)<option value="{{ $action }}" @selected($selectedAction === $action)>{{ $label }}</option>@endforeach</select>
            <label class="sr-only" for="activity-user">Filter pengguna</label><select id="activity-user" name="user"><option value="">Semua pengguna</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($selectedUser === $user->id)>{{ $user->name }}</option>@endforeach</select>
            <label class="sr-only" for="activity-from">Dari tanggal</label><input id="activity-from" type="date" name="from" value="{{ $from }}">
            <label class="sr-only" for="activity-until">Sampai tanggal</label><input id="activity-until" type="date" name="until" value="{{ $until }}">
            <button class="button small" type="submit">Terapkan</button>@if(request()->hasAny(['q', 'action', 'user', 'from', 'until']))<a class="button small" href="{{ route('activity-logs.index') }}">Reset</a>@endif
        </form>
        @if($logs->count())
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Deskripsi</th></tr></thead>
                    <tbody>@foreach($logs as $log)<tr><td><strong>{{ $log->created_at->translatedFormat('d M Y H:i') }}</strong></td><td>{{ $log->user?->name ?? 'Sistem' }}</td><td><x-pill tone="blue">{{ $log->actionLabel() }}</x-pill></td><td>{{ $log->description }}</td></tr>@endforeach</tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <nav class="pagination" aria-label="Navigasi log aktivitas">
                    @if($logs->onFirstPage())<span>‹</span>@else<a href="{{ $logs->previousPageUrl() }}">‹</a>@endif
                    <span class="active">{{ $logs->currentPage() }}</span><span>dari {{ $logs->lastPage() }}</span>
                    @if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}">›</a>@else<span>›</span>@endif
                </nav>
            @endif
        @else
            <x-empty-state title="Belum ada aktivitas" description="Tindakan penting dalam aplikasi akan dicatat di halaman ini." />
        @endif
    </div>
</section>
@endsection
