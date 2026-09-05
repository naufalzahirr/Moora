# Tinjauan UI/UX dan penggunaan — H2 Asia Restock

**Status implementasi:** perbaikan temuan 1–7 dan penyederhanaan antarmuka telah diterapkan. Isi audit di bawah merekam kondisi sebelum perbaikan; hasil verifikasi terbaru tercantum pada bagian terakhir.

Tanggal: 5 September 2026. Cakupan: kode aplikasi saat ini, template Blade, CSS, JavaScript, dan pengujian HTTP Laravel menggunakan SQLite dalam memori.

Prioritas perbaikan adalah konsistensi penyimpanan, persetujuan, dan konteks data. Setelah itu, sederhanakan navigasi, isi dashboard, dan formulir operasional.

**Batas pemeriksaan:** browser otomatis tidak tersedia pada sesi ini. Temuan perilaku di bawah diverifikasi melalui kode dan pengujian terisolasi; ukuran, kepadatan, dan responsivitas disimpulkan dari CSS/template dan masih memerlukan pemeriksaan visual desktop/ponsel. Screenshot pada laporan lama tidak digunakan sebagai bukti tampilan terbaru. Kode aplikasi dan data operasional tidak diubah.

## Masalah yang perlu diperbaiki lebih dulu

### 1. Aksi massal Petugas dapat membuka kembali keputusan Owner

**Terbukti melalui pengujian HTTP dan HTML hasil render.** Setelah Owner menyetujui barang, tampilan Petugas menunjukkan “Keputusan Owner” dan menutup editor per baris. Namun, checkbox barang tersebut masih aktif. Mengirim usulan massal untuk barang itu mengubah status `approved` menjadi `proposed` dan mengosongkan identitas pemberi persetujuan.

**Dampak:** tindakan yang terlihat seperti mengirim usulan bisa membatalkan keputusan yang sudah dibuat Owner.

**Usulan:** terapkan aturan status dan peran yang sama pada perubahan per baris maupun massal. Kunci checkbox untuk keputusan final yang tidak boleh diubah Petugas; server harus menolak atau melewati perubahan itu. Pembukaan kembali keputusan harus menjadi tindakan Owner yang eksplisit.

**Bukti kode:** `resources/views/restock-actions/index.blade.php:57`, `app/Http/Controllers/RestockActionController.php:145`.

### 2. Aksi massal mengabaikan jumlah yang sedang diketik

**Terbukti dari kepemilikan form pada HTML dan pengujian HTTP.** Input jumlah/catatan terhubung ke form per baris, sedangkan tombol persetujuan massal mengirim form lain yang hanya membawa pilihan barang dan status. Server mengambil jumlah yang sudah disimpan, kemudian memakai saran sistem jika belum ada.

**Contoh:** jumlah tersimpan 25, pengguna mengubah tampilan menjadi 10 lalu memilih “Setujui Terpilih”. Nilai 10 tidak termasuk dalam kiriman form massal; persetujuan tetap memakai 25. “Setujui Semua sesuai Saran” juga dapat memakai keputusan tersimpan yang berbeda dari saran sistem.

**Usulan:** tentukan satu model penyimpanan yang jelas. Pilihan utama: aksi massal menyimpan perubahan pada baris terpilih sekaligus mengirim keputusan, dengan ringkasan jumlah sebelum disetujui. Alternatif: blok aksi massal selama ada perubahan belum disimpan dan arahkan pengguna menyimpannya dahulu. Bedakan label “Gunakan saran sistem” dan “Gunakan jumlah keputusan”.

**Bukti kode:** `resources/views/restock-actions/index.blade.php:40`, `resources/views/restock-actions/index.blade.php:68`, `app/Http/Controllers/RestockActionController.php:150`.

### 3. Form edit yang gagal mencampurkan input ke dialog barang lain

**Terbukti melalui pengujian validasi dan HTML hasil render.** Setelah edit barang gagal validasi, nama yang baru diketik muncul sebagai nilai input pada beberapa dialog edit dan dialog tambah. Semua dialog memakai `old('name')`, `old('code')`, dan kunci lain yang sama, tanpa identitas form asal. Dialog juga tidak dibuka kembali otomatis untuk menunjukkan kesalahan.

**Dampak:** pengguna kehilangan konteks koreksi dan berisiko menyimpan nilai milik barang A ke barang B. Nilai database belum berubah hanya karena validasi gagal; risiko muncul saat dialog lain kemudian disimpan.

**Usulan:** simpan identitas form/barang yang gagal, isi ulang hanya form tersebut, buka kembali dialognya, dan fokuskan kolom bermasalah. Terapkan pola yang sama pada supplier, pengguna, dan tindak lanjut karena penggunaan `old()` global juga terlihat di sana.

**Bukti kode:** `resources/views/products/partials/form.blade.php:4`, `resources/views/products/index.blade.php:68`, `resources/js/app.js:22`.

### 4. “Simpan Data” belum mendukung penyimpanan bertahap

**Terbukti melalui pengujian HTTP.** Petunjuk menyatakan data bisa disimpan untuk dilanjutkan nanti, tetapi tombol “Simpan Data” dan “Simpan & Buat Rekomendasi” memakai validasi yang sama: seluruh kolom setiap baris harus lengkap. Satu stok akhir kosong membuat penyimpanan ditolak.

**Usulan:** “Simpan Draft” menerima isian sebagian, mempertahankan kolom kosong sebagai belum diisi, dan menampilkan progres. Kelengkapan penuh diwajibkan saat membuat rekomendasi. Tambahkan pemberitahuan perubahan belum disimpan sebelum berpindah periode/halaman.

**Bukti kode:** `resources/views/datasets/index.blade.php:28`, `resources/views/datasets/index.blade.php:58`, `app/Http/Requests/DatasetRequest.php:17`.

### 5. Filter laporan tidak mengendalikan target unduhan

**Terbukti melalui pengujian HTTP.** Pencarian yang tidak menemukan periode menampilkan daftar kosong, tetapi ringkasan dan tombol unduh tetap menunjuk rekomendasi terbaru di seluruh database. Run untuk unduhan dipilih terpisah dari hasil filter, tanpa identitas periode yang jelas di dekat tombol.

**Dampak:** pengguna berpotensi mengunduh laporan yang berbeda dari konteks pencarian.

**Usulan:** letakkan aksi PDF/Excel pada baris laporan atau panel pilihan yang secara eksplisit menyebut nama periode, rentang tanggal, dan waktu perhitungan. Ketika tidak ada hasil/pilihan, tampilkan keadaan kosong yang sesuai dan hilangkan aksi unduh yang tidak berkonteks.

**Bukti kode:** `app/Http/Controllers/ReportController.php:32`, `resources/views/reports/index.blade.php:9`.

### 6. Peringatan stok rendah tertutup oleh konfigurasi yang belum lengkap

**Terbukti melalui pengujian HTTP.** Bila ada barang lain yang minimum stoknya belum diatur, kartu “Stok di Bawah Minimum” diganti seluruhnya oleh “Minimum Belum Diatur”, meskipun terdapat barang yang benar-benar berada di bawah minimum. Pola yang sama ada di Stok Berjalan.

**Usulan:** selalu tampilkan jumlah barang dengan stok rendah. Tampilkan kelengkapan pengaturan sebagai peringatan terpisah yang menautkan pengguna ke barang yang perlu dilengkapi.

**Catatan terkait:** “Barang Tercatat” di dashboard menghitung barang dalam run terakhir saat run tersedia, sehingga labelnya tidak selalu mewakili jumlah master barang saat ini. Pisahkan “Barang aktif” dan “Barang dianalisis”. Daftar “paling perlu ditindaklanjuti” juga mengambil lima ranking pertama tanpa menyaring barang yang sudah diterima atau tidak dipesan.

**Bukti kode:** `resources/views/dashboard/index.blade.php:25`, `resources/views/dashboard/index.blade.php:43`, `app/Http/Controllers/DashboardController.php:46`, `resources/views/inventory/index.blade.php:21`.

### 7. Angka pada tombol pembuatan pesanan menyesatkan

**Terbukti melalui pengujian HTTP.** Dua barang yang disetujui menghasilkan tombol “Buat 2 Pesanan Siap Diproses”, tetapi jika suppliernya sama, yang dibuat hanya satu pesanan pembelian. Angka tombol menghitung barang, bukan pesanan.

**Usulan:** gunakan “Buat Draft Pesanan” dan keterangan “2 barang · 1 supplier”. Sebelum membuat, tampilkan pengelompokan barang per supplier dan barang yang belum memiliki supplier.

**Bukti kode:** `resources/views/restock-actions/index.blade.php:28`, `resources/views/restock-actions/index.blade.php:37`, `app/Http/Controllers/PurchaseOrderController.php:65`.

## Penyederhanaan UI/UX yang disarankan

Bagian ini merupakan penilaian desain berdasarkan template/CSS dan perlu divalidasi secara visual.

| Area | Yang terasa kurang efektif | Perbaikan yang disarankan |
| --- | --- | --- |
| Dashboard | KPI jumlah kriteria, jumlah barang berulang di ikon dan teks, serta petunjuk generik memakai ruang utama. | Utamakan stok rendah, usulan menunggu persetujuan, pesanan perlu ditindaklanjuti, dan penerimaan. Jadikan kartu tersebut tautan ke pekerjaan terkait. Jumlah kriteria cukup di halaman konfigurasi/detail. |
| Navigasi dan aksi utama | Owner memiliki 12 menu. Hasil Rekomendasi dan Tindak Lanjut menampilkan barang yang sama di halaman terpisah. Tombol utama Tindak Lanjut justru kembali ke Hasil; tombol utama Pesanan mengarah ke Stok Berjalan. | Tampilkan tahapan “Data → Rekomendasi → Persetujuan → Pesanan → Penerimaan”. Pertimbangkan tab Hasil/Tindak Lanjut dengan periode yang tetap. Jadikan aksi tahap berikutnya sebagai tombol utama sesuai peran/status. |
| Form barang | Identitas barang langsung bercampur dengan safety stock, masa tinjau, MOQ, dan kelipatan pesanan; banyak istilah tanpa contoh singkat. | Kelompokkan identitas, supplier, dan pengaturan restock. Simpan pengaturan lanjutan dalam bagian yang bisa dibuka. Tampilkan contoh konkret satuan dan kelipatan. |
| Status dan pesan | Peringatan data lama bisa tampil bersama panel hijau permanen “Rekomendasi berhasil dibuat / Siap ditindaklanjuti”. “Belum Ditinjau” juga mencakup usulan yang sudah dikirim. | Satu ringkasan status berdasarkan kondisi aktual; bedakan draft, menunggu Owner, disetujui, dipesan, dan diterima. Pesan keberhasilan cukup muncul setelah tindakan berhasil. |
| Tabel dan skala data | Tabel umum minimum 720 px, Tindak Lanjut 1080 px pada desktop. Hasil dan tindak lanjut memuat seluruh baris; selector riwayat/PO memuat seluruh pilihan. | Pertahankan nama barang dan aksi saat digulir; pindahkan atribut sekunder ke detail. Tambahkan pencarian dan filter status/supplier. Uji dengan ratusan barang, bukan hanya lima data contoh. |
| Ponsel dan keterbacaan | Banyak label 10 px, tombol tabel 34 px, serta tabel umum yang tetap melebar. Tindak Lanjut sudah memiliki aturan kartu pada ponsel. | Naikkan ukuran teks bantuan yang penting, perbesar aksi sentuh, dan gunakan susunan kartu untuk pekerjaan gudang yang sering dilakukan. Uji 390 px, tablet, laptop, dan zoom 200% sebelum menetapkan hasil visual. |
| Detail dan istilah | Skor Yi tampil di tabel hasil utama, input bobot Owner memakai pecahan `0.4` sedangkan total memakai `100%`, badge navigasi memakai DB/BR/SP, dan logout memakai simbol ↗. | Utamakan ranking/jumlah/status; simpan rincian Yi dalam detail yang mudah dibuka. Input bobot dalam persen. Gunakan ikon yang dikenali atau label teks. Sediakan tombol Keluar yang maknanya jelas. |

Supplier, pengaturan kriteria, riwayat perhitungan, pencatatan penerimaan, dan arsip tetap mempunyai fungsi yang nyata pada aplikasi ini. Penyederhanaan sebaiknya mengurangi langkah dan kepadatan layar sambil mempertahankan fungsi tersebut. Dua tahap persetujuan barang dan persetujuan PO perlu alasan operasional yang terlihat; apabila keduanya tetap diperlukan, jelaskan keputusan yang dibuat pada masing-masing tahap.

## Hal yang sudah membaik dari laporan lama

- Login tidak lagi menampilkan helper pengisian kredensial demo; verifikasi lewat tes yang sudah ada.
- Dashboard mempertahankan rekomendasi terakhir saat draft tersedia; verifikasi lewat tes yang sudah ada.
- Ada konfirmasi pembuatan pembaruan, validasi tanggal masa depan, template impor, dan batas impor 10 MB.
- Ada indikator umur data, tampilan kriteria hanya-baca untuk Petugas, dan format jumlah sesuai satuan.
- CSS menyediakan fokus yang terlihat, tombol menu/penutup dialog 44 px, perbaikan `sr-only`, dan susunan kartu Tindak Lanjut pada ponsel. Perilaku visualnya belum diperiksa ulang di browser.

## Validasi dan urutan pengerjaan

Sebanyak **20 tes aplikasi terkait lulus, dengan 144 assertion**. Audit tambahan menjalankan **7 skenario terisolasi, dengan 43 assertion**, yang mengonfirmasi perilaku bermasalah nomor 1–7 di atas. Lulusnya skenario audit berarti perilaku saat ini berhasil direproduksi, bukan berarti masalahnya telah diperbaiki. Pengujian memakai SQLite `:memory:`; tidak menjalankan migrasi atau seeder terhadap database operasional.

Urutan yang disarankan:

1. Perbaiki aturan keputusan Owner/Petugas, penyimpanan massal, dan pemulihan form yang gagal.
2. Dukung penyimpanan draft, perjelas konteks unduhan, pertahankan peringatan stok rendah, dan koreksi jumlah pada tombol PO.
3. Rapikan dashboard, tahapan kerja, formulir barang, dan pesan status.
4. Verifikasi visual dan interaksi di browser, termasuk ponsel, keyboard, kesalahan validasi, pergantian periode dengan perubahan belum disimpan, dan daftar barang berukuran besar.

## Hasil implementasi

- Keputusan Owner dan barang dalam PO terkunci bagi Petugas, baik pada permintaan per baris maupun massal.
- Tindak Lanjut memakai pilihan barang dengan jumlah/catatan yang ikut disimpan. Perubahan otomatis memilih baris terkait; konfirmasi persetujuan menampilkan rincian pilihan. Validasi yang gagal membatalkan seluruh penyimpanan pilihan.
- Form yang gagal hanya memulihkan isian pada dialog asal. Dialog dibuka kembali dan kolom bermasalah diberi penanda. Perpindahan halaman/periode memiliki perlindungan untuk perubahan yang belum disimpan.
- Draft operasional menerima nilai kosong tanpa mengubahnya menjadi nol; pembuatan rekomendasi tetap mewajibkan data lengkap. Angka rupiah lokal dan desimal juga ditangani tanpa menghapus tanda negatif.
- PDF/Excel terkait langsung dengan baris laporan atau panel periode yang dipilih. Filter tanpa hasil tidak menyediakan unduhan untuk laporan lain.
- Dashboard menampilkan stok rendah, usulan menunggu persetujuan, pesanan perlu diproses, dan penerimaan. Pengaturan minimum yang belum lengkap menjadi pemberitahuan terpisah. Jumlah barang aktif berasal dari master saat ini.
- Pembuatan PO menyediakan tinjauan barang per supplier dan menghitung jumlah pesanan dari kelompok supplier.
- Tahapan restock tetap membawa konteks periode/run. Form barang memiliki bagian pengaturan lanjutan, input bobot memakai persen, dan tabel operasional memiliki susunan kartu untuk ponsel. Label/aksi diperbesar dan navigasi Keluar diperjelas.
- Data operasional, hasil, dan tindak lanjut dibagi 50 baris per halaman. Tindak lanjut memiliki pencarian/filter status dan supplier; pesanan mempunyai daftar, pencarian, filter status, dan pagination.

**Verifikasi:** 61 tes PHP lulus (343 assertion), 3 tes JavaScript lulus, build Vite berhasil, dan pemeriksaan format/diff bersih. Sebanyak 22 permintaan halaman melalui kernel Laravel berhasil dengan status 200 untuk akun Owner/Petugas pada database lokal yang sudah diperbarui.

Migrasi `2026_09_05_000002_allow_incomplete_operational_drafts` sudah diterapkan pada database SQLite lokal. Backup sebelum migrasi tersedia di `storage/app/backups/pre-ux-draft-be05a6ac5a.sqlite`. Isi 23 tabel selain metadata migrasi dibandingkan sebelum/sesudah dan tidak berubah; pemeriksaan integritas SQLite berhasil serta tidak ditemukan pelanggaran foreign key.

**Batas verifikasi:** browser otomatis masih tidak tersedia. Perubahan responsif dan pengelolaan interaksi sudah diimplementasikan, tetapi penampilan visual serta interaksi DOM langsung di browser belum dapat dikonfirmasi. Build dan pemeriksaan HTTP tidak menggantikan pemeriksaan visual tersebut.
