<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — SPK Restock H2 Asia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
<section class="login-hero">
    <div class="brand login-brand"><span class="brand-mark">H2</span><span><strong>H2 ASIA</strong><small>SWALAYAN</small></span></div>
    <div class="login-copy">
        <h1>Sistem<br>Pendukung<br>Keputusan<br>Restock Barang</h1>
        <p>Rekomendasi restock yang terstruktur, transparan, dan mudah ditelusuri.</p>
        <ol>
            <li><span>1</span>Catat penjualan dan stok terbaru</li>
            <li><span>2</span>Dapatkan prioritas restock</li>
            <li><span>3</span>Catat pesanan hingga barang diterima</li>
        </ol>
    </div>
</section>
<section class="login-panel">
    <form class="login-card" method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <span class="eyebrow">AKSES PENGGUNA</span>
        <h2>Masuk ke Sistem</h2>
        <p>Gunakan akun yang telah terdaftar untuk melanjutkan.</p>
        @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
        <label>USERNAME<input type="text" name="username" value="{{ old('username') }}" placeholder="Masukkan username" autocomplete="username" required autofocus></label>
        <label>PASSWORD<input type="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required></label>
        <label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
        <button class="button primary wide" type="submit">MASUK</button>
        <div class="login-meta"><span>Akses untuk owner/admin dan petugas persediaan yang terdaftar.</span></div>
    </form>
    <small class="copyright">© {{ now()->year }} H2 Asia Swalayan</small>
</section>
</body>
</html>
