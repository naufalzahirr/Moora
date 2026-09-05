<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terjadi Kendala — H2 Asia</title>
    @vite(['resources/css/app.css'])
</head>
<body>
<main class="error-page">
    <section class="card pad error-card" role="alert">
        <span class="error-code">500</span>
        <h1>Terjadi kendala</h1>
        <p>Permintaan belum dapat diselesaikan. Silakan kembali ke dashboard dan coba lagi. Bila masalah berulang, hubungi administrator.</p>
        <a class="button primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Kembali ke Dashboard</a>
    </section>
</main>
</body>
</html>
