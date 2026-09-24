# SPK Restock H2 Asia Swalayan

Aplikasi Laravel 12 untuk mengelola siklus restock barang berkelanjutan menggunakan **MOORA (Multi-Objective Optimization on the Basis of Ratio Analysis)**. MOORA menentukan prioritas barang; jumlah pembelian dihitung terpisah dari kebutuhan permintaan, lead time, stok, dan pesanan yang masih berjalan.

## Alur utama: transaksi dan penilaian otomatis

1. **Data Barang:** daftarkan kode, nama, dan satuan barang sekali.
2. **Transaksi Barang:** catat stok awal pada awal hari mulai pencatatan, kemudian tambah penjualan, barang masuk, atau koreksi jumlah stok. Stok awal boleh nol. Transaksi dicatat berurutan per barang; penjualan dan koreksi keluar tidak boleh membuat stok negatif. Pengiriman ulang formulir yang sama tidak menggandakan transaksi.
3. **Penilaian MOORA:** pilih tanggal awal dan akhir. C1 adalah saldo stok sampai akhir rentang; C2 dan C3 adalah jumlah dan nilai penjualan selama rentang tersebut. Semua barang aktif harus mempunyai stok awal paling lambat pada tanggal awal analisis. Tidak perlu mengisi rekap manual.
4. **Laporan:** lihat ranking, detail perhitungan, dan unduh PDF/Excel.

Penjualan memerlukan total rupiah transaksi (bukan harga satuan). Barang masuk hanya menambah stok. Koreksi stok tidak mengubah total penjualan. Perhitungan menyimpan snapshot; transaksi baru memberi penanda bahwa hasil perlu dihitung ulang tanpa menimpa hasil sebelumnya.

Data rekap lama dan modul pembelian lama dipertahankan sebagai arsip. Data agregat lama tidak dipecah menjadi transaksi harian secara otomatis karena tanggal transaksi aslinya tidak diketahui. Buku transaksi baru memakai stok awal eksplisit dan terpisah dari mutasi operasional lama agar tidak menghitung ganda. Akses arsip data lama tersedia pada Dashboard dan Transaksi Barang. PDF yang sudah diarsipkan tetap mempertahankan dokumen aslinya.

Migrasi tambahan `2026_09_24_000000_create_stock_transactions_table` membuat tabel transaksi dan metadata sumber analisis tanpa menghapus data lama. Jalankan `php artisan migrate --force` setelah membuat cadangan database.

## Fitur dan modul yang tersedia

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

### MySQL lokal melalui Docker

Proyek menyediakan `compose.mysql.yaml` untuk MySQL 8.4 khusus aplikasi ini, dengan volume persisten dan akses lokal pada `127.0.0.1:3308`.

Pada komputer baru, salin `.env.mysql.example` menjadi `.env.mysql`, lalu isi `DB_PASSWORD` dan `MYSQL_ROOT_PASSWORD` dengan dua kata sandi unik. Jalankan:

```bash
docker compose --env-file .env.mysql -f compose.mysql.yaml up -d --wait
```

Untuk instalasi baru dengan database kosong, salin nilai `DB_*` dan `BACKUP_MYSQL_CONTAINER` dari `.env.mysql` ke `.env`, lalu jalankan `php artisan config:clear` dan `php artisan migrate --force`. Kredensial tetap disimpan lokal; `.env` dan `.env.mysql` tidak masuk Git. Mengubah kata sandi di file environment tidak mengubah akun MySQL yang sudah dibuat dalam volume.

Untuk instalasi SQLite yang sudah berisi data, migrasi skema saja belum memindahkan data. Cadangkan SQLite, buat skema MySQL kosong, salin data beserta ID dan riwayatnya, lalu verifikasi sebelum mengganti koneksi aplikasi. Jangan menjalankan `migrate:fresh` atau seeder pada data yang sudah digunakan.

Pada komputer ini, pemindahan telah dilakukan pada 9 September 2026 ke database `h2_asia_moora`, pengguna `h2_asia`, port `3308`. Semua 24 tabel diperiksa; laporan verifikasi serta cadangan SQLite dan konfigurasi sebelumnya tersimpan lokal di `storage/app/backups`. Pemeriksaan menyamakan format tanggal, skala kolom desimal, dan representasi angka JSON MySQL. File `database/database.sqlite` tetap tersedia sebagai salinan data sebelum perpindahan; data baru selanjutnya masuk ke MySQL.

Docker Desktop perlu berjalan saat aplikasi dipakai. Untuk menyalakan kembali database, gunakan perintah `up` di atas. Untuk menghentikannya dengan data tetap tersimpan:

```bash
docker compose --env-file .env.mysql -f compose.mysql.yaml stop
```

## Data impor

CSV/XLS/XLSX maksimal 10 MB (hingga 10.000 baris per impor) dapat memakai nama kolom: `kode barang`/`no. barang`, `nama barang`/`deskripsi barang`, `jumlah terjual`/`kts. standar`, dan `nilai penjualan`/`nilai barang`. Nilai numerik wajib terisi, valid, dan nonnegatif. Unduh template CSV pada dialog impor bila diperlukan. Data Operasional dapat disimpan sebagai draft meskipun sebagian kolom masih kosong. Kolom kosong tetap dianggap belum diisi. Lengkapi seluruh penjualan dan stok akhir sebelum membuat rekomendasi. Daftar ditampilkan 50 barang per halaman; simpan perubahan sebelum berpindah halaman.

Batas aplikasi dan PHP diselaraskan melalui `public/.user.ini` (`upload_max_filesize=10M`, `post_max_size=12M`). Pada server yang mengabaikan `.user.ini`, terapkan nilai yang sama pada konfigurasi PHP/FPM atau panel hosting.

Untuk deployment HTTPS, gunakan konfigurasi production agar debug mati, cookie sesi aman, dan konfigurasi production aktif. Setelah mengubah environment, jalankan `php artisan optimize`.

## Alur operasional berkelanjutan

1. Buat data operasional untuk periode penjualan baru, lalu impor atau isi penjualan dan stok akhir.
2. Jalankan MOORA untuk membentuk snapshot prioritas dan rekomendasi jumlah restock.
3. Pada **Tindak Lanjut**, pilih barang dan sesuaikan jumlah/catatan, lalu simpan draft atau kirim usulan. Owner menyetujui atau menolak keputusan akhir. Aksi massal menyimpan isian barang terpilih; keputusan akhir terkunci bagi Petugas.
4. Owner meninjau pengelompokan supplier sebelum membuat draft **Pesanan Pembelian**. Satu supplier menghasilkan satu pesanan, yang dapat berisi beberapa barang.
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

Aplikasi mendukung backup SQLite (`.sqlite`) dan MySQL (`.sql`) pada `storage/app/backups`, dengan retensi backup rutin selama 14 hari. Cadangan sebelum migrasi dan berkas manual tidak ikut dihapus. Jadwal harian berjalan ketika scheduler Laravel aktif:

```bash
php artisan schedule:work
```

Backup manual dapat dibuat dengan `php artisan app:backup-database`. Atur `BACKUP_DAILY_AT` dan `BACKUP_RETENTION_DAYS` pada environment bila diperlukan.

Untuk MySQL Docker lokal, gunakan `BACKUP_MYSQL_CONTAINER=h2-asia-moora-mysql`. Perintah backup menjalankan `mysqldump` di dalam container menggunakan akun database aplikasi; kata sandi tidak dimasukkan ke argumen perintah. Scheduler perlu akses ke Docker; `BACKUP_DOCKER_BINARY` dapat diisi path absolut CLI Docker bila tidak tersedia pada `PATH` scheduler. Untuk MySQL tanpa Docker, kosongkan `BACKUP_MYSQL_CONTAINER` dan sediakan `mysqldump` pada `PATH` atau atur `BACKUP_MYSQL_BINARY`. Dump menggunakan snapshot transaksi untuk tabel InnoDB aplikasi.

## Memperbarui instalasi yang sudah ada

Jalankan `php artisan migrate --force` untuk menerapkan migrasi baru, kemudian `npm run build` untuk membangun aset antarmuka. Migrasi draft membuat kolom penjualan dan stok akhir menerima nilai kosong tanpa mengubah nilai yang sudah tersimpan. Cadangkan database sebelum pembaruan.

Validasi: `vendor/bin/phpunit --do-not-cache-result` dan `node --test tests/js/*.test.js`.
