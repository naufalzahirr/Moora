<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Ditolak — H2 Asia</title>
    @vite(['resources/css/app.css'])
</head>
<body>
<main class="error-page">
    <section class="card pad error-card" role="alert">
        <span class="error-code">403</span>
        <h1>Akses tidak diizinkan</h1>
        <p>Anda tidak memiliki hak akses untuk membuka halaman ini. Gunakan menu yang tersedia sesuai peran akun Anda.</p>
        <a class="button primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Kembali ke Dashboard</a>
    </section>
</main>
</body>
</html>
