# Ringkasan Pengujian Regression dan White-Box — SIMPBI

Disusun 23 September 2026. Cakupan: **Regression Testing** dan **White-Box Testing**
per empat modul. Pengujian Black-Box tidak termasuk dokumen ini dan akan disusun
terpisah; jalur yang belum tercakup di sini dicatat sebagai masukan untuknya.

Pekerjaan ini tidak menambah test baru dan tidak mengubah kode aplikasi. Yang dilakukan:
memetakan 74 berkas test yang sudah ada ke empat modul, menjalankannya ulang secara
kumulatif di dua driver basis data, dan menelusuri jalur logika kritis ke test yang
benar-benar menjalankannya.

| Dokumen | Isi |
|---|---|
| [01-modul-1-pengguna-data-induk.md](01-modul-1-pengguna-data-induk.md) | Masuk, Pengguna, Tim Kerja, Kategori, Barang Persediaan, Aset Tetap, Pengaturan Akun, Lengkapi Akun |
| [02-modul-2-permintaan-barang.md](02-modul-2-permintaan-barang.md) | Katalog Barang, alur Permintaan Barang enam tahap, Stok Masuk, Kartu Kendali |
| [03-modul-3-mutasi-aset.md](03-modul-3-mutasi-aset.md) | Mutasi Aset (BAST), Aset Tetap Tim Saya, Riwayat Penempatan Aset |
| [04-modul-4-dashboard-monitoring.md](04-modul-4-dashboard-monitoring.md) | Dasbor per peran, Riwayat, Ekspor, Pusat Bantuan, notifikasi |

---

## 1. Hasil singkat

| Modul | Berkas | Metode test | Kasus dieksekusi | Regression | White-box (jalur tercakup) |
|---|---|---|---|---|---|
| 1 — Pengguna & Data Induk | 29 | 245 | 251 | **Layak** | **Layak Bersyarat** (20 / 22) |
| 2 — Permintaan Barang | 20 | 173 | 183 | **Layak** | **Layak Bersyarat** (23 / 33) |
| 3 — Mutasi Aset | 9 | 76 | 82 | **Layak** | **Layak Bersyarat** (17 / 20) |
| 4 — Dashboard & Monitoring | 16 | 157 | 182 | **Layak** | **Layak Bersyarat** (26 / 52) |
| **Jumlah** | **74** | **651** | **698** | | |

Kriteria yang dipakai:

- **Regression** — *Layak* bila 100% kasus modul-modul sebelumnya tetap lulus ketika
  modul berikutnya ditambahkan ke run yang sama; *Tidak Layak* bila ada yang gagal.
- **White-box** — *Layak* bila seluruh jalur kritis tercakup dan lulus; *Layak Bersyarat*
  bila ada jalur yang belum tercakup tetapi tidak ada yang gagal; *Tidak Layak* bila ada
  jalur yang gagal saat dijalankan.

Tidak ada satu pun kegagalan pada seluruh run (0 gagal, 0 error, 0 dilewati).

---

## 2. Lingkungan dan isolasi

| Butir | Keadaan |
|---|---|
| Kode yang diuji | Commit `35a1a7a` (`main`) **ditambah perubahan yang belum di-commit** di working tree. Yang berpengaruh pada angka: `tests/Feature/PengajuanKatalogAturanTest.php` (+3 metode) dan `tests/Feature/PusatBantuanTest.php` (1 metode diganti nama, jumlah tetap), serta `app/Filament/Pages/KatalogBarang.php`, `PusatBantuan.php`, `app/Support/KontakBantuan.php`. Karena itu suite kini **698** kasus, bukan 695 seperti angka akhir Batch 7. |
| PHP / PHPUnit / Laravel | PHP 8.3.33 (Laragon), PHPUnit 11.5.56, Laravel 12.69.0 |
| SQLite | Bawaan `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| MySQL | Instans **sementara** MySQL 8.4.3 (biner `E:\laragon\laragon\bin\mysql\mysql-8.4.3-winx64\bin\`), datadir baru di folder scratchpad sesi (di luar repositori dan di luar folder Laragon), `127.0.0.1:3399`, basis data `simpbi_uji_modul`. Sebelum tiap run MySQL dicetak `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji_modul`, berbeda dari `.env` (`DB_CONNECTION=sqlite`). Sesudah run, basis data itu berisi 21 tabel dan 23 baris `migrations` — bukti migrasi uji berjalan di instans sementara. |
| MySQL Laragon (3306) | Tidak dijalankan, dihentikan, atau diubah. |
| Basis data kerja | Dari `.env` hanya `DB_CONNECTION` yang dibaca. `database/database.sqlite`: md5 `5360cca51a27bb6bea5beafb22ec97a6`, 335 872 B, mtime 14:28:33 — **sama sebelum dan sesudah** seluruh run. |
| Storage | Semua run memakai salinan storage di scratchpad (`LARAVEL_STORAGE_PATH`); kedua salinan (SQLite dan MySQL) masing-masing menerima 340 berkas tulisan selama run. `storage/framework` repositori: 289 berkas sebelum, 288 sesudah. Satu-satunya berkas repositori yang tertulis selama pengujian adalah satu berkas sesi (15:17) milik server pengembangan pemilik (`artisan serve`, `queue:work`, `schedule:work` sedang berjalan dan tidak disentuh). Tes sendiri memakai `SESSION_DRIVER=array` dari `phpunit.xml`. `bootstrap/cache` tidak berubah. |
| Instans MySQL sementara | Dimatikan dengan `mysqladmin shutdown` sesudah seluruh run; port 3399 bebas. Datadir dibiarkan di scratchpad. |
| Alat ukur cakupan | Tidak tersedia: tidak ada `pcov`/`xdebug`; `phpdbg.exe` ada, tetapi PHPUnit 11 hanya mendukung PCOV dan Xdebug. Tidak ada driver yang dipasang. Cakupan white-box diukur manual (penelusuran jalur ke isi test). |

Kendala yang terjadi dan dilaporkan apa adanya: run MySQL pertama berhenti karena
instans MySQL sementara mati kehabisan memori ("Unable to allocate memory") ketika
dijalankan bersamaan dengan run SQLite (memori bebas mesin ± 450 MB). Proses PHPUnit
MySQL itu dihentikan, instans dijalankan ulang dengan `innodb_buffer_pool_size=64M`, dan
seluruh run berikutnya dijalankan **berurutan**. Hasil yang dicantumkan hanya dari run
yang selesai lengkap.

---

## 3. Suite penuh — angka acuan kondisi saat ini

| Driver | Tes | Lulus | Gagal | Error | Assertion | Durasi |
|---|---|---|---|---|---|---|
| SQLite | 698 | 698 | 0 | 0 | 5165 | 7 mnt 34 dtk |
| MySQL 8.4.3 sementara | 698 | 698 | 0 | 0 | 5165 | 6 mnt 08 dtk |

Jumlah tes dan assertion **per berkas** identik di kedua driver (dibandingkan dari berkas
JUnit masing-masing).

---

## 4. Pemetaan berkas test ke modul

Setiap berkas dibuka dan dipetakan menurut kelas aplikasi yang benar-benar diuji
(pernyataan `use App\...` dan isi metode), bukan hanya nama berkas. Daftar lengkap per
berkas — termasuk jumlah metode, kasus, assertion, kelas yang diimpor, dan alasan untuk
berkas lintas modul — ada di bagian 1.1–1.2 tiap dokumen modul.

Ringkasan berkas lintas modul (modul utama → modul lain yang tersentuh):

| Berkas | Modul utama | Juga menyentuh |
|---|---|---|
| `AksesPanelTest` | 1 | 4 |
| `KodeBarangBaruStokMasukTest` | 1 | 2 |
| `KontakBantuanMasukTest` | 1 | 4 |
| `NamaNipTerkunciPengaturanTest` | 1 | 2 |
| `PenempatanAsetFormTest` | 1 | 3 |
| `PenyesuaianStokFisikTest` | 1 | 2 |
| `SinkronisasiAsetTetapTest` | 1 | 3 |
| `DialogRincianPermintaanTest` | 2 | 4 |
| `DokumenDiDiskPrivatTest` | 2 | 3 |
| `NotifikasiKedaluwarsaTest` | 2 | 4 |
| `PengajuanKatalogAturanTest` | 2 | 4 |
| `PindahDokumenTest` | 2 | 3 |
| `UnduhanDokumenTest` | 2 | 3, 4 |
| `PenggunaTanpaTimTest` | 3 | 4 |
| `RiwayatPenempatanAsetTest` | 3 | 1 |
| `AvatarFontLokalTest` | 4 | 1 |
| `BilahAtasTest` | 4 | 1 |
| `IstilahKonsistenTest` | 4 | 2 |
| `NotifikasiMutasiAsetTest` | 4 | 3 |
| `NotifikasiTautanRincianTest` | 4 | 2 |

Selisih 651 metode → 698 kasus berasal dari `#[DataProvider]` pada sembilan berkas:
`AsetTetapTimSayaTest` (7 → 10), `BilahAtasTest` (17 → 21), `PemeliharaanDataTest`
(8 → 11), `PenggunaTanpaTimTest` (3 → 6), `PengirimanFonnteTest` (16 → 21),
`PengirimanWhatsAppTest` (24 → 40), `StokMasukTest` (21 → 23), `TandaTanganPenggunaTest`
(11 → 14), `UnduhanDokumenTest` (12 → 20).

---

## 5. Regression kumulatif mengikuti dependensi

Untuk tiap langkah dibuat satu konfigurasi PHPUnit sementara (di luar repositori) yang
berisi berkas modul-modul itu saja, dengan satu *testsuite* per modul, sehingga hasil
dapat dipilah per modul dari berkas JUnit. Langkah 4 memakai `phpunit.xml` proyek (74
berkas, identik dengan gabungan keempat modul).

| Langkah | Isi run | Berkas | SQLite: tes / assertion / gagal | MySQL: tes / assertion / gagal |
|---|---|---|---|---|
| 1 | Modul 1 | 29 | 251 / 1496 / 0 | 251 / 1496 / 0 |
| 2 | Modul 1 + 2 | 49 | 434 / 4160 / 0 | 434 / 4160 / 0 |
| 3 | Modul 1 + 2 + 3 | 58 | 516 / 4537 / 0 | 516 / 4537 / 0 |
| 4 | Modul 1 + 2 + 3 + 4 | 74 | 698 / 5165 / 0 | 698 / 5165 / 0 |

Kasus modul sebelumnya yang lulus di tiap langkah (kedua driver sama):

| Langkah | Modul 1 (251) | Modul 2 (183) | Modul 3 (82) | Modul 4 (182) |
|---|---|---|---|---|
| 1 | 251 | — | — | — |
| 2 | 251 | 183 | — | — |
| 3 | 251 | 183 | 82 | — |
| 4 | 251 | 183 | 82 | 182 |

---

## 6. Regresi historis selama delapan tahap perbaikan (470 → 695)

Sumber: tabel "Hasil tes" pada tiap batch di `docs/audit/laporan-perbaikan.md`
(B2.2, C1.3, B3.2, V.4, B4.2, B5.2, B6.12) dan `docs/audit/laporan-final.md` bagian 1.
Sejak Batch 1, suite penuh pada **setiap** tahap dijalankan di SQLite dan MySQL 8.4.3
sementara dan dilaporkan lulus dengan 0 gagal dan 0 error (baseline audit di MySQL masih
gagal; lihat baris pertama). Artinya tes
dari tahap-tahap sebelumnya terus ikut dijalankan dan tetap lulus sepanjang delapan tahap.

| Tahap | Tes | Δ | ID yang dikerjakan | Berkas uji baru (jumlah metode saat ini) | Modul tersentuh (kasar) |
|---|---|---|---|---|---|
| Audit awal | 470 | — | — (baca saja) | — | Baseline. Lulus di SQLite; di MySQL sementara **17 error + 3 gagal** — 17 error `CAST(nomor_bon AS INTEGER)` (penomoran bon, diperbaiki A-001) dan 3 gagal di `PemeliharaanDataTest` (cadangan/pemulihan). Seluruhnya lulus sejak Batch 1. |
| Batch 1 | 492 | +22 | A-001, A-002, A-004, A-005, A-014, A-016 | `NomorBonPengeluaranTest` (+2 metode), `UnduhanDokumenTest` (20 kasus) | M2 (+22); rute unduh juga menyentuh M3 (BAST) dan gerbang akun M1 |
| Batch 2 | 526 | +34 | A-003, A-006, A-017, A-020, A-021, A-030 | `PersetujuanTidakMelebihiDimintaTest` 8, `NotifikasiKedaluwarsaTest` 5, `PengajuanKatalogAturanTest` 7¹, `PenomoranBastTest` 6, `KodeBarangBaruStokMasukTest` 4, `StokTerkunciBacaSajaTest` 4 | M2 (+20), M1 (+8), M3 (+6) |
| C-001 | 539 | +13 | C-001 (dokumen di disk privat) | `DokumenDiDiskPrivatTest` 4, `PindahDokumenTest` 9 | M2 (+13), juga BAST (M3) |
| Batch 3 | 576 | +37 | A-011, A-012, A-018, A-031 | `MutasiAsetPenempatanTest` 18, `PenggunaTanpaTimTest` 6 kasus, `FormTimKerjaTest` 13 | M3 (+24), M1 (+13) |
| Batch 4 | 617 | +41 | A-007, A-008, A-010, A-015, C-004 | `AdminTidakMengunciDiriTest` 10, `GantiSandiSesiTest` 9, `NamaNipTerkunciPengaturanTest` 6, `PengaturanTombolSimpanTest` 7, `KonfirmasiAkunTimTest` 9 | M1 (+32), M2 (+9) |
| Batch 5 | 663 | +46 | G-001, G-002, G-003, F-002, F-003, F-006 (+ investigasi G-005, G-006) | `HapusAkunTerjagaTest` 10, `ImporPenggunaPenjagaAdminTest` 7, `SandiFormPenggunaTest` 4, `PenempatanAsetFormTest` 7, `EmailTerkunciPengaturanTest` 4, `StatusBastDanAsetAktifTest` 9, `NipPemohonKatalogTest` 5 | M1 (+32), M3 (+9), M2 (+5) |
| Batch 6 | 695 | +32 | A-009, A-013, A-019, A-023, A-025, A-026 | `PesanValidasiIndonesiaTest` 7, `KondisiStokTautanTest` 4, `AvatarFontLokalTest` 7, `IstilahKonsistenTest` 10, `RutePermintaanLamaTest` 4 | M4 (+21), M1 (+7), M2 (+4) |
| Batch 7 | 695 | 0 | Regresi akhir, tanpa ID baru | — | Seluruh modul dijalankan ulang, lulus di kedua driver |
| Sesudah Batch 7 (belum di-commit) | 698 | +3 | Notifikasi pengajuan di Katalog; pesan otomatis "Chat Sekarang" Pusat Bantuan | +3 metode di `PengajuanKatalogAturanTest`; 1 metode diganti di `PusatBantuanTest` | M2/M4 (+3) |

¹ `PengajuanKatalogAturanTest` kini berisi 10 metode; 3 di antaranya tambahan yang belum
di-commit (baris terakhir tabel).

Catatan penamaan tahap: `laporan-final.md` menyebut angka 539 sebagai "Batch 3 (tahap
awal)", sedangkan `laporan-perbaikan.md` bagian C1.3 mencatat transisi 526 → 539 pada
pekerjaan C-001. Tabel di atas mengikuti `laporan-perbaikan.md`; jumlah +13 cocok persis
dengan dua berkas uji C-001. Kolom "Modul tersentuh" adalah perkiraan: jumlah per modul
dihitung dari berkas uji baru yang disebut laporan untuk batch itu, memakai pemetaan
modul dan jumlah metode saat ini. Jumlahnya cocok dengan selisih tes tiap batch.

---

## 7. Celah yang diteruskan ke pengujian Black-Box

Tidak satu pun celah ini berupa kegagalan; semuanya jalur yang belum ditegaskan oleh
test otomatis.

**Modul 1**
- UF-5: `tim_id` wajib untuk peran Tim/Ketua Tim pada form Pengguna.
- KB-5: kode barang ganda lewat form Buat/Ubah Barang Persediaan. Form ini tidak punya
  aturan unik (hanya indeks basis data); menurut pembacaan kode, hasilnya galat basis
  data. Belum diverifikasi dinamis.

**Modul 2**
- SS-10, SS-14, SS-16, SS-17, SS-22: rincian tanpa `jumlah_final`, disetujui nol,
  `final > diminta` saat konversi, konversi kedua pada permintaan bernomor bon, sisa
  kartu kendali negatif.
- SS-6, SS-15: barang hilang saat release/konversi (defensif).
- KS-1, KS-9, KS-11: jeda 60 detik sapuan, laporan sapuan gagal, `default` tahap (defensif).

**Modul 3**
- MA-5, MA-9, MA-15: galat teknis saat membuat BAST, `aset_id` fiktif, BAST hilang saat
  Sahkan/Konfirmasi.
- Konkurensi nyata (dua pengesahan bersamaan) di MySQL.

**Modul 4**
- Penerima notifikasi tujuh status permintaan (NS-12 s.d. NS-18), akun nonaktif (NS-9),
  penerima ganda/kosong (NS-1, NS-2), status tak dikenal (NS-20).
- Peringatan stok habis/menipis (NS-21 s.d. NS-23).
- Widget Ringkasan kelima peran, Kelengkapan Data Induk, Kondisi Aset Tetap (W-1 s.d. W-7);
  penyaring/periode/urgensi pada W-13, W-14, W-16, W-18, W-21.
- Ekspor Riwayat PDF/Excel.
