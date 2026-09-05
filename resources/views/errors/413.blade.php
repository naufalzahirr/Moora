<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berkas terlalu besar - H2 Asia MOORA</title>
    @vite(['resources/css/app.css'])
</head>
<body>
<main class="error-page">
    <section class="card pad error-card" role="alert">
        <span class="error-code">413</span>
        <h1>Berkas terlalu besar</h1>
        <p>Ukuran unggahan melebihi batas server. Gunakan berkas CSV, XLS, atau XLSX berukuran maksimal 10 MB.</p>
        <a class="button primary" href="{{ auth()->check() ? route('datasets.index') : route('login') }}">Kembali</a>
    </section>
</main>
</body>
</html>
