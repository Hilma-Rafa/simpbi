# Laporan Final — Audit dan Perbaikan SIMPBI

Disusun pada Batch 7 (penutup), 22 September 2026, di atas baseline commit `9a9bec9`
("Perbaikan audit batch 1-6 dan C-001: SIMPBI") pada cabang `main`. Batch 7 sendiri
**tidak memperbaiki ID apa pun** — murni verifikasi akhir dan penyiapan basis data
pengujian persisten. Rincian penuh tiap batch ada di `docs/audit/laporan-perbaikan.md`;
temuan audit asli ada di `docs/audit/laporan-audit.md` (tidak diubah oleh batch mana pun).

## 1. Ringkasan seluruh batch

| Tahap | Jumlah tes | ID yang dikerjakan |
|---|---|---|
| Audit awal | 470 | — (baseline audit baca-saja) |
| Batch 1 | 492 | A-001, A-016, A-002, A-004, A-005, A-014 |
| Batch 2 | 526 | A-003, A-030, A-006, A-017, A-020, A-021 (A-029 dilewati — syarat tak terpenuhi) |
| Batch 3 (tahap awal, sebelum jeda ruang disk) | 539 | bagian dari A-011, A-018, A-031, A-012 |
| Batch 3 (selesai, sesudah "verifikasi ulang" V.1–V.7) | 576 | A-011, A-018, A-031, A-012 (lengkap) |
| Batch 4 | 617 | A-007, A-008, C-004, A-010, A-015 |
| Batch 5 | 663 | G-001, G-002, G-003, G-005, G-006, F-002 (opsi B), F-003, F-006; koreksi catatan G-004 |
| Batch 6 | 695 | A-009, A-013, A-019, A-023, A-025, A-026 |
| **Batch 7 (regresi akhir, tanpa ID baru)** | **695** (dikonfirmasi ulang di SQLite dan MySQL 8.4.3 sementara) | — |

C-001 (dokumen tersimpan di disk privat, tidak lagi dapat dibuka lewat `/storage/...`)
tercatat pada 21 September 2026, digabung ke commit yang sama dengan Batch 6.

Seluruh transisi di atas terverifikasi lewat tabel "Hasil tes" pada bagian B2.2, B3.2,
V.4, B4.2, B5.2, dan bagian B6 `laporan-perbaikan.md`; angka 695 (Batch 6 akhir) telah
dikonfirmasi ulang secara independen pada Batch 7 ini (bagian 4.1 di bawah).

## 2. Status akhir seluruh ID A-001 s.d. A-031

| ID | Status akhir | Catatan singkat |
|---|---|---|
| A-001 | Diperbaiki | Penomoran bon lintas driver (SQLite/MySQL); Batch 1. |
| A-002 | Diperbaiki | Rute unduhan bukti/BAST kini memeriksa kepemilikan; Batch 1. |
| A-003 | Diperbaiki | Persetujuan tidak dapat melebihi jumlah diminta; Batch 2. |
| A-004 | Diperbaiki | Rute unduhan memeriksa status aktif/wajib ganti sandi/akun belum lengkap; Batch 1. |
| A-005 | Diperbaiki | Tamu tanpa sesi dialihkan ke halaman masuk (dulu 500); Batch 1. |
| A-006 | Diperbaiki | Notifikasi kedaluwarsa tepat sekali, tahan kegagalan; Batch 2. |
| A-007 | Diperbaiki | Tombol Simpan Pengaturan tidak lagi memicu dialog peragaan tanpa alasan; Batch 4. |
| A-008 | Diperbaiki | Ganti sandi akun sendiri tidak lagi melempar ke halaman masuk; Batch 4. |
| A-009 | Diperbaiki | Pesan validasi Laravel diterjemahkan penuh ke Indonesia; Batch 6. |
| A-010 | Diperbaiki | Admin tidak dapat mengunci/menonaktifkan akunnya sendiri lewat form; Batch 4. |
| A-011 | Diperbaiki | BAST usang akibat penempatan berubah ditolak dengan notifikasi; Batch 3. |
| A-012 | Diperbaiki | Sumber kebenaran Ketua Tim diselaraskan pada form Tim Kerja; Batch 3. |
| A-013 | Diperbaiki | Tautan Kondisi Stok disembunyikan bagi Petugas Gudang (dulu 403); Batch 6. |
| A-014 | Diperbaiki | Tombol Unduh BAST hanya tampil sesudah disahkan; Batch 1. |
| A-015 | Diperbaiki | Nama dan NIP terkunci pada Pengaturan (Akun Saya); Batch 4. |
| A-016 | Diperbaiki (dengan koreksi C-002) | Kebocoran storage nyata pada tes lama; suite dibuat bebas driver. |
| A-017 | Diperbaiki | Barang nonaktif ditolak di Katalog tanpa mengunci stok; Batch 2. |
| A-018 | Diperbaiki | Pengguna tanpa tim tidak lagi melihat aset tim lain; Batch 3. |
| A-019 | **Diperbaiki sebagian** | Avatar lokal penuh; font Inter lokal. Plus Jakarta Sans & Space Grotesk masih dari fonts.bunny.net — **perlu berkas font dari pemilik** (I-003). |
| A-020 | Diperbaiki | Penomoran BAST tahan celah dan bentrok; Batch 2. |
| A-021 | Diperbaiki | Kode barang baru pada Stok Masuk tervalidasi unik per kategori; Batch 2. |
| A-022 | **Dilewati** (tidak dipilih pada batch manapun) | Kinerja N+1 pada Kartu Kendali dan lonceng notifikasi; < 200 ms pada data uji. **Tindakan pemilik:** putuskan apakah perlu dioptimalkan (*eager load*/`whereIn`) atau diterima seperti sekarang. |
| A-023 | Diperbaiki (1 pengecualian disengaja) | Istilah dan format tanggal diseragamkan; label `ditolak_kasubbag` sengaja dipertahankan tanpa "Umum" (I-001). |
| A-024 | **Dilewati** (tidak dipilih pada batch manapun) | Teks pembaca-layar bawaan Filament (mis. "Skip to content") masih berbahasa Inggris. **Tindakan pemilik:** putuskan apakah perlu menimpa terjemahan vendor. |
| A-025 | Diperbaiki | Kode starter tak terpakai (app.js, bootstrap.js, axios, dll.) dihapus; Batch 6. |
| A-026 | Diperbaiki | Tampilan mati pada rute lama dihapus, rute dan perilakunya dipertahankan; Batch 6. |
| A-027 | **Dilewati** (tidak dipilih pada batch manapun) | Dua jalur pencarian kode permintaan (kolom + penyaring) berdampingan; penyaring dipakai tautan lonceng/WhatsApp. **Tindakan pemilik:** pilih salah satu — pertahankan keduanya, sembunyikan penyaring dari UI, atau satukan. |
| A-028 | **Dilewati** (tidak dipilih pada batch manapun) | 158 berkas menyimpang dari gaya `pint` saat audit; tidak ada `pint.json`. **Tindakan pemilik:** tetapkan `pint.json` lalu jalankan sekali (di luar cakupan batch manapun sejauh ini, sebab berisiko menyentuh banyak berkas sekaligus). |
| A-029 | **Dilewati** (syarat tidak terpenuhi) | `pelaksana_id` pada `riwayat_persetujuan` tidak `nullable`; perbaikan menuntut migration baru (dilarang tanpa persetujuan). **Tindakan pemilik:** putuskan apakah migration `nullable` dibuat pada batch tersendiri. |
| A-030 | Diperbaiki | Stok Terkunci terkunci baca-saja pada form Ubah; Batch 2. |
| A-031 | Diperbaiki | ID Eksternal dan Waktu Sinkronisasi terkunci baca-saja pada form Tim; Batch 3. |

Ringkasan: **26 Diperbaiki penuh**, **1 Diperbaiki sebagian** (A-019), **4 Dilewati** karena
tidak pernah dipilih ke dalam batch mana pun (A-022, A-024, A-027, A-028), **1 Dilewati**
karena syarat prasyaratnya sendiri tidak terpenuhi (A-029) — total 31 ID.

## 3. Ringkasan catatan lama yang masih perlu tindakan pemilik (bagian E)

ID berikut berstatus "dilewati", "dipertahankan", atau memerlukan keputusan pemilik,
dirangkum dari `laporan-perbaikan.md` (tidak diselidiki ulang pada Batch 7 ini):

| ID | Masalah (satu kalimat) | Tindakan yang masih perlu pemilik lakukan |
|---|---|---|
| **F-001** | Pola kueri tanpa penjaga eksplisit di beberapa tempat; aman hari ini karena kolom tim `NOT NULL`. | Tidak mendesak — hanya perlu diwaspadai bila kolom tim pada tabel terkait suatu saat dilonggarkan menjadi nullable. |
| **F-004** | Ekspor/tampilan Riwayat tidak punya urutan tie-break untuk baris bercap waktu persis sama. | Tidak mendesak — jarang terjadi pada data nyata (detik berbeda); opsional untuk ditambah `orderBy` kedua. |
| **F-005** | BAST usang (akibat A-011) tetap `menunggu_pengesahan` selamanya, tanpa mekanisme pembatalan. | Putuskan apakah diperlukan alur pembatalan/kedaluwarsa untuk BAST usang. |
| **H-001** | Pendengar `deleting` pada `DilindungiRiwayat` dapat diam-diam menghentikan pendengar lain bila didaftarkan lewat `booted()` pada model yang sama. | Waspada saat menambah penjaga baru pada model Barang/Tim/Aset/Kategori/Pengguna di masa depan; tidak perlu tindakan sekarang. |
| **H-002** | Form Pengguna, Impor Pengguna, dan Pengaturan tidak memvalidasi format NIP, sedangkan Katalog menuntut 18 angka. | Putuskan apakah validasi NIP perlu diseragamkan di seluruh tempat. |
| **H-003** | NIP Pemohon bebas pada permintaan **lama** tetap tampil apa adanya (data lama sengaja tidak diubah). | Tidak perlu tindakan — ini sesuai instruksi menjaga data lama; hanya dicatat sebagai kesadaran. |
| **I-001** | Label status `ditolak_kasubbag` masih "Ditolak Kasubbag" (tanpa "Umum") untuk melindungi lebar kolom ekspor Riwayat PDF beku. | Putuskan apakah ingin diseragamkan penuh (menuntut persetujuan perubahan tata letak PDF/Excel beku). |
| **I-002** | `DetailPermintaanBarang.php` masih mendeklarasikan `$view` yang menunjuk berkas yang sudah dihapus (A-026); aman selama `mount()` tidak diubah — dibuktikan lewat eksekusi HTTP nyata (SQLite dan MySQL) tanpa galat 500 sama sekali, termasuk pada permintaan yang terputus di tengah jalan. | Opsional — dapat dibersihkan (ubah/hapus properti `$view`) pada batch mendatang. |
| **I-003** | Font Plus Jakarta Sans dan Space Grotesk masih dimuat dari `fonts.bunny.net`; tidak ada berkas lokal di repo atau paket vendor. | Sediakan berkas `.woff2` kedua keluarga ini bila ingin dilokalkan penuh (dilarang mengunduh sendiri oleh pelaksana batch). |

Catatan: **G-004** (soal nomor WhatsApp akun Tim) sudah **ditutup** lewat keputusan pemilik
pada Batch 5 (opsi B, tanpa perubahan kode) — tidak memerlukan tindakan lebih lanjut.

## 4. Hasil Batch 7

### 4.1 Regresi penuh (bagian B)

| Driver | Hasil |
|---|---|
| SQLite (bawaan `phpunit.xml`) | **695 tes lolos, 5150 assertion** — 354,68 dtk |
| MySQL 8.4.3 sementara (`simpbi_b7_test`, datadir baru di scratchpad, port 3399, biner Laragon 8.4.3) | **695 tes lolos, 5150 assertion; 0 error, 0 gagal** — 369,50 dtk |

Kedua hasil sama persis dengan angka akhir Batch 6. `DB_HOST=127.0.0.1 DB_PORT=3399
DB_DATABASE=simpbi_b7_test` dicetak sebelum run dan berbeda dari `.env` (`DB_CONNECTION=sqlite`).
Tidak ada tes yang gagal — **tidak ada ID J- dari regresi**.

### 4.2 Basis data pengujian persisten `simpbi_pengujian` (bagian C)

Dibuat di server MySQL Laragon yang sudah berjalan (`127.0.0.1:3306`, layanan **tidak**
dihentikan/dimulai ulang; basis data `simpbi` milik pemilik **tidak disentuh**).
Migration dan `simpbi:reset-demo --paksa` dijalankan dengan variabel lingkungan
di-override HANYA untuk perintah tersebut (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`,
`DB_PORT=3306`, `DB_DATABASE=simpbi_pengujian`, `DB_USERNAME=root`, sandi kosong —
sama seperti kredensial Laragon yang sudah dipakai di seluruh batch sebelumnya);
**`.env` proyek tidak diubah**.

**Penting — isolasi berkas.** `simpbi:reset-demo` mengosongkan direktori dokumen dan
tanda tangan lewat `storage_path()`. Agar tidak menghapus dokumen asli pada repositori
kerja, perintah dijalankan dengan `LARAVEL_STORAGE_PATH` diarahkan ke direktori baru
**di luar repositori**: `E:\Projek Skripsi\simpbi-pengujian-storage\`. Direktori ini
dibuat khusus untuk `simpbi_pengujian` dan **dimaksudkan untuk tetap ada** mendampingi
basis data ini (lihat bagian 5 di bawah untuk cara memakainya).

Hasil `simpbi:reset-demo` (semua tahap `DONE`, tanpa galat):

| Tabel | Jumlah baris |
|---|---|
| `users` | 12 |
| `tim` | 8 |
| `barang_persediaan` | 119 |
| `aset_tetap` | 12 |
| `kategori` | 16 |
| `permintaan_barang` | 0 (bersih, siap dipakai) |
| `bast_mutasi_aset` | 1 |

Akun aktif per peran (kata sandi seluruhnya `password`):

| Peran | Jumlah akun | Aktif |
|---|---|---|
| Admin Sistem | 1 | 1 |
| Kasubbag Umum | 1 | 1 |
| Petugas Gudang | 1 | 1 |
| Ketua Tim | 8 | 8 |
| Tim | 1 | 1 |

Tidak ada galat dari `simpbi:reset-demo` — **tidak ada ID J- dari bagian ini**.

**Catatan operasional penting (kejadian selama verifikasi, sudah dipulihkan).** Pada
percobaan awal bagian D (lihat 4.3), skenario diuji lewat PHPUnit memakai trait
`RefreshDatabase`, yang **menjalankan `migrate:fresh` di awal proses** — ini sempat
mengosongkan seluruh isi `simpbi_pengujian` (termasuk data induk hasil `reset-demo`
di atas) sebelum transaksi pengujian dimulai. Begitu diketahui, `simpbi:reset-demo`
dijalankan ulang dengan langkah dan variabel lingkungan yang identik, dan tabel di atas
adalah hasil sesudah pemulihan — **diverifikasi ulang dan sudah benar**. Pelajaran ini
dicatat di sini secara transparan: **jangan pernah menjalankan alat berbasis
`RefreshDatabase` (PHPUnit) langsung terhadap `simpbi_pengujian`** — gunakan basis data
sekali-pakai terpisah (seperti pola Batch 3–6) untuk keperluan semacam itu.

### 4.3 Tinjauan visual — siklus penuh (bagian D)

Skenario siklus penuh (permintaan sampai selesai, satu BAST dibuat/disahkan/dikonfirmasi,
Kartu Kendali, ekspor Riwayat) dijalankan di atas skema `simpbi_pengujian` lewat
`tests/Feature/TinjauanVisualB7Test.php` (dijalankan dari luar repositori dan **dihapus
sesudahnya** — tidak ada berkas tes baru yang tertinggal di repositori), dibungkus
`RefreshDatabase` (transaksi digulung balik di akhir) sehingga data uji skenario ini
**tidak tertinggal** di `simpbi_pengujian` — hanya berkas keluarannya yang disimpan permanen.
Baris tabel `simpbi_pengujian` sesudah pengujian ini kembali sama persis dengan tabel
pada 4.2 (diverifikasi ulang).

Delapan berkas UTUH (bukan teks yang diekstrak) tersimpan di
`E:\Projek Skripsi\cadangan-audit\batch-7\tinjauan-visual\`:

| Berkas | Ukuran | Isi |
|---|---|---|
| `1-bukti-permintaan.pdf` | 72.145 B | Bukti permintaan (dokumen asli, status selesai) |
| `1b-bukti-permintaan-berfootnote.pdf` | 65.561 B | Varian dengan footnote |
| `2a-bast-draf.pdf` | 62.167 B | BAST sebelum disahkan (draf) |
| `2b-bast-disahkan.pdf` | 73.047 B | BAST sesudah disahkan dan dikonfirmasi |
| `3a-kartu-kendali.pdf` | 8.352 B | Kartu Kendali tahun 2026 |
| `3b-kartu-kendali.xlsx` | 11.255 B | Kartu Kendali (Excel) |
| `4a-riwayat.xlsx` | 8.142 B | Ekspor Riwayat (Excel) |
| `4b-riwayat.pdf` | 63.436 B | Ekspor Riwayat (PDF) |

Keenam berkas PDF diverifikasi berheader `%PDF-` yang sah; kedua berkas XLSX
dihasilkan langsung oleh PhpSpreadsheet dari kode aplikasi yang sama. Pemilik dapat
membuka berkas-berkas ini langsung untuk tinjauan visual.

## 5. Instruksi untuk pemilik: memakai `simpbi_pengujian`

Basis data ini **tetap ada** di server MySQL Laragon yang sudah berjalan di
`127.0.0.1:3306`, terpisah dari basis data `simpbi` yang sudah ada. Untuk mengarahkan
aplikasi ke sana secara **sementara** (mis. lewat variabel lingkungan pada sesi
terminal, bukan mengubah `.env` permanen — itu keputusan pemilik sepenuhnya), nilai yang
dipakai adalah:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simpbi_pengujian
DB_USERNAME=root
DB_PASSWORD=            (kosong, sama seperti kredensial Laragon bawaan)
LARAVEL_STORAGE_PATH=E:\Projek Skripsi\simpbi-pengujian-storage
```

`LARAVEL_STORAGE_PATH` **wajib** disertakan setiap kali memakai basis data ini — tanpa
itu, aplikasi akan memakai `storage/` repositori kerja (tempat dokumen dan tanda tangan
asli tersimpan), dan perintah seperti `simpbi:reset-demo` di masa depan berisiko
menghapusnya. Akun demo dan kata sandinya ada pada tabel di bagian 4.2 di atas
(kata sandi seluruhnya `password`).

Untuk mengembalikan `simpbi_pengujian` ke keadaan bersih kapan pun (mis. sebelum sesi
PSSUQ berikutnya), jalankan ulang persis perintah yang sama dengan variabel di atas:

```
php artisan simpbi:reset-demo --paksa
```

**Peringatan yang sama seperti pada bagian 4.2**: jangan pernah menjalankan `php artisan
test` atau `vendor/bin/phpunit` dengan `DB_DATABASE=simpbi_pengujian` — trait
`RefreshDatabase` yang dipakai hampir semua tes akan menjalankan `migrate:fresh` dan
mengosongkan basis data ini secara tidak sengaja.
