# SPK Restock H2 Asia Swalayan

Aplikasi Laravel 12 untuk mengelola siklus restock barang berkelanjutan menggunakan **MOORA (Multi-Objective Optimization on the Basis of Ratio Analysis)**. MOORA menentukan prioritas barang; jumlah pembelian dihitung terpisah dari kebutuhan permintaan, lead time, stok, dan pesanan yang masih berjalan.

## Fitur

- Login berbasis peran `owner` dan `petugas`, termasuk manajemen akun aktif/nonaktif.
- Dashboard kondisi rekomendasi, stok minimum, dan barang dalam perjalanan.
- Data barang serta master supplier dan lead time.
- Konfigurasi kriteria cost/benefit dengan validasi total bobot 100%.
- Impor data penjualan CSV/XLSX, input stok akhir, dan validasi kelengkapan.
- Perhitungan matriks keputusan, normalisasi, pembobotan, nilai Yi, dan ranking MOORA.
- Rekomendasi jumlah restock berdasarkan permintaan harian, lead time, masa tinjau, safety stock, stok tersedia, barang dalam perjalanan, MOQ, dan kelipatan pesanan.
- Stok berjalan dengan mutasi saldo awal, penjualan agregat, penerimaan barang, dan stok opname.
- Tindak lanjut rekomendasi, draft pesanan pembelian per supplier, persetujuan, pengiriman, dan penerimaan parsial/penuh.
- Riwayat MOORA, ekspor Excel, laporan PDF, serta log aktivitas yang dapat ditelusuri.
- Setiap data analisis disimpan sebagai snapshot agar hasil lama tidak berubah ketika data master diperbarui.

## Akun dan deployment

Seeder hanya digunakan untuk lingkungan pengembangan/pengujian dan sengaja ditolak pada `APP_ENV=production`. Di production, buat akun Owner awal melalui prosedur provisioning yang aman, gunakan kata sandi unik, dan jangan membagikan kredensial melalui dokumentasi atau kode frontend.

## Instalasi

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

PDF dibuat dengan DomPDF secara default agar dapat berjalan tanpa ketergantungan browser. Chrome/Chromium dapat tetap dipilih secara eksplisit melalui `PDF_ENGINE=chrome` bila infrastruktur server sudah menyediakannya.

Secara default `.env.example` menggunakan MySQL. Untuk penggunaan lokal dengan SQLite, ubah menjadi:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/path/absolut/proyek/database/database.sqlite
```

## Data impor

CSV/XLS/XLSX maksimal 10 MB (hingga 10.000 baris per impor) dapat memakai nama kolom: `kode barang`/`no. barang`, `nama barang`/`deskripsi barang`, `jumlah terjual`/`kts. standar`, dan `nilai penjualan`/`nilai barang`. Nilai numerik wajib terisi, valid, dan nonnegatif. Unduh template CSV pada dialog impor bila diperlukan. Stok akhir dilengkapi pada halaman Data Operasional sebelum analisis dilakukan.

Batas aplikasi dan PHP diselaraskan melalui `public/.user.ini` (`upload_max_filesize=10M`, `post_max_size=12M`). Pada server yang mengabaikan `.user.ini`, terapkan nilai yang sama pada konfigurasi PHP/FPM atau panel hosting.

Untuk deployment HTTPS, gunakan konfigurasi production agar debug mati, cookie sesi aman, dan konfigurasi production aktif. Setelah mengubah environment, jalankan `php artisan optimize`.

## Alur operasional berkelanjutan

1. Buat data operasional untuk periode penjualan baru, lalu impor atau isi penjualan dan stok akhir.
2. Jalankan MOORA untuk membentuk snapshot prioritas dan rekomendasi jumlah restock.
3. Petugas menyiapkan usulan pada **Tindak Lanjut**; Owner menyetujui atau menolak keputusan akhir.
4. Owner membuat draft **Pesanan Pembelian** dari keputusan yang disetujui; barang otomatis dikelompokkan per supplier.
5. Owner menyetujui pesanan; petugas menandai pesanan sudah dikirim ke supplier.
6. Catat penerimaan barang penuh atau sebagian. Stok berjalan akan bertambah otomatis. Gunakan **Stok Berjalan** untuk stok opname dan audit mutasi.

Data periode adalah snapshot perencanaan dan pelaporan, bukan batas umur aplikasi. Data baru dapat dimasukkan pada bulan berikutnya tanpa mengubah riwayat bulan sebelumnya.

## Dasar jumlah restock

Rata-rata permintaan harian adalah jumlah terjual dibagi jumlah hari data. Target stok mengambil nilai terbesar dari stok minimum, target master, atau kebutuhan selama lead time plus masa tinjau dan safety stock. Sistem kemudian mengurangi stok tersedia serta pesanan yang masih berjalan, lalu membulatkan kekurangan sesuai MOQ dan kelipatan pesanan.

MOORA tetap menentukan urutan prioritas keputusan, sedangkan rumus ini menentukan jumlah yang layak dipesan.

## Rumus MOORA

```text
Normalisasi x*ij = xij / sqrt(sum(xij^2))
Nilai terbobot  = x*ij × wj
Yi              = sum(benefit) - sum(cost)
```

Konfigurasi awal: C1 Stok Akhir (cost 40%), C2 Jumlah Barang Terjual (benefit 35%), dan C3 Nilai Penjualan (benefit 25%).

Mockup sumber yang diekstrak dari Bab III disimpan di `docs/mockups` sebagai referensi visual.

## Backup database

Untuk SQLite, aplikasi membuat backup harian pada `storage/app/backups` dan menyimpan 14 hari terakhir. Jalankan scheduler Laravel di server:

```bash
php artisan schedule:work
```

Backup manual dapat dibuat dengan `php artisan app:backup-database`. Atur `BACKUP_DAILY_AT` dan `BACKUP_RETENTION_DAYS` pada environment bila diperlukan. Untuk MySQL/PostgreSQL, gunakan backup terkelola dari server database.
