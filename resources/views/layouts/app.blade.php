<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — H2 Asia Restock</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Navigasi aplikasi">
        <div class="brand">
            <span class="brand-mark">H2</span>
            <span><strong>H2 ASIA</strong><small>RESTOCK DECISION SUPPORT</small></span>
        </div>

        <nav class="nav-list" aria-label="Menu utama">
            <div class="nav-section"><p>RINGKASAN</p><a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><span>DB</span>Dashboard</a></div>
            <div class="nav-section"><p>PERSEDIAAN</p><a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"><span>BR</span>Data Barang</a><a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"><span>SP</span>Supplier</a><a href="{{ route('datasets.index') }}" class="nav-link {{ request()->routeIs('datasets.*') ? 'active' : '' }}"><span>DO</span>Data Operasional</a><a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}"><span>SB</span>Stok Berjalan</a></div>
            <div class="nav-section"><p>ANALISIS</p><a href="{{ route('criteria.index') }}" class="nav-link {{ request()->routeIs('criteria.*') ? 'active' : '' }}"><span>KR</span>Kriteria & Bobot</a><a href="{{ route('calculations.results') }}" class="nav-link {{ request()->routeIs('calculations.results', 'calculations.show') ? 'active' : '' }}"><span>HR</span>Hasil Rekomendasi</a></div>
            <div class="nav-section"><p>EKSEKUSI</p><a href="{{ route('restock-actions.index') }}" class="nav-link {{ request()->routeIs('restock-actions.*') ? 'active' : '' }}"><span>TL</span>Tindak Lanjut</a><a href="{{ route('purchase-orders.index') }}" class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"><span>PO</span>Pesanan Pembelian</a><a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"><span>LP</span>Arsip Laporan</a></div>
            @if(auth()->user()->role === 'owner')<div class="nav-section"><p>PENGATURAN</p><a href="{{ route('activity-logs.index') }}" class="nav-link {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}"><span>AT</span>Aktivitas</a><a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"><span>US</span>Pengguna</a></div>@endif
        </nav>

    </aside>

    <section class="workspace">
        <header class="topbar">
            <button class="menu-button" type="button" data-menu-toggle aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">☰</button>
            <div class="breadcrumb">SPK Restock <b>/</b> @yield('breadcrumb', 'Dashboard')</div>
            <div class="user-box">
                <div><strong>{{ auth()->user()->roleLabel() }}</strong><small>H2 Asia Swalayan</small></div>
                <span class="avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($word) => mb_substr($word, 0, 1))->take(2)->join('') }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" aria-label="Keluar dari aplikasi" title="Keluar" type="submit">↗</button></form>
            </div>
        </header>

        <main class="content">
            @if(session('success'))
                <div class="flash success" role="status" data-flash><div><b>Berhasil.</b> {{ session('success') }}</div><button type="button" class="flash-close" data-flash-close aria-label="Tutup pesan berhasil">×</button></div>
            @endif
            @if($errors->any())
                <div class="flash error" role="alert" data-flash><div><b>Periksa kembali data.</b> {{ $errors->first() }}</div><button type="button" class="flash-close" data-flash-close aria-label="Tutup pesan kesalahan">×</button></div>
            @endif
            @yield('content')
        </main>
    </section>
</div>
<button class="sidebar-backdrop" type="button" data-menu-close aria-label="Tutup menu" tabindex="-1"></button>
@stack('dialogs')
<dialog id="confirmation-dialog" aria-labelledby="confirmation-dialog-title" aria-describedby="confirmation-dialog-message">
    <div class="dialog-header"><h2 id="confirmation-dialog-title">Konfirmasi tindakan</h2><button type="button" class="dialog-close" data-confirmation-cancel aria-label="Tutup konfirmasi">×</button></div>
    <div class="dialog-body"><p id="confirmation-dialog-message" style="margin-top:0;line-height:1.55">Pastikan tindakan ini memang ingin dilakukan.</p><div class="form-footer"><button class="button" type="button" data-confirmation-cancel>Batal</button><button class="button primary" type="button" data-confirmation-accept>Lanjutkan</button></div></div>
</dialog>
</body>
</html>
