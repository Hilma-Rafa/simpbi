# Laporan Perbaikan — Batch 1

Tanggal: 21 September 2026. Mengerjakan **A-001, A-016, A-002, A-004, A-005, A-014** dari `laporan-audit.md`. `laporan-audit.md` sengaja **tidak diubah**; koreksi atas isinya dicatat di sini (C-002).

## 1. Cara kerja dan pengaman

| Butir | Keterangan |
|---|---|
| Git | Hanya perintah baca (`status`, `diff`, `log`). Tidak ada `add`/`commit`/`checkout`. Cabang `main`, HEAD `b79831d`, ±134 entri belum di-commit milik pemilik (pemilik memutuskan tidak commit dulu). |
| Cadangan | Setiap berkas yang diubah disalin **sebelum** diedit ke `E:\Projek Skripsi\cadangan-audit\batch-1\` (path relatif utuh): `app/Services/StokService.php`, `routes/web.php`, `bootstrap/app.php`, `app/Filament/Resources/BastMutasiAsets/Tables/BastMutasiAsetsTable.php`, `tests/Feature/{PemeliharaanDataTest,RiwayatPenempatanAsetTest,SinkronisasiAsetTetapTest,NomorBonPengeluaranTest,DokumenBastTest}.php`, `docs/audit/matriks-akses.md`. (`DokumenBastTest.php` ternyata tidak perlu diubah — lihat C-002.) |
| Basis data kerja | Tidak disentuh. Dari `.env` hanya `DB_CONNECTION` (=`sqlite`) yang dibaca. Semua tes dan server verifikasi memakai basis data lain (di bawah). |
| MySQL sementara | Biner `E:\laragon\laragon\bin\mysql\mysql-8.4.3-winx64\bin\` (8.4.3), datadir di folder scratchpad sesi (di luar repositori dan di luar folder Laragon), `127.0.0.1:3399`. Layanan MySQL Laragon (proses `mysqld` pada 3306) tidak dijalankan, dihentikan, atau diubah. Sebelum tiap tes MySQL dicetak: `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_audit_test`, dan `.env` = `sqlite` (berbeda). |
| Storage | Semua tes dijalankan dengan `LARAVEL_STORAGE_PATH` ke salinan storage di scratchpad; `storage/` repositori tidak ditulisi dan tidak ada berkas yang dihapus di sana (`BAST-UJI-0001.pdf` di storage nyata bertanggal 16:01 sebelum batch dan tidak berubah). |
| Pemformatan | `pint` tidak dijalankan. Suntingan dilakukan per blok dengan akhiran baris berkas dipertahankan (CRLF pada `BastMutasiAsetsTable.php`, `PemeliharaanDataTest.php`, `matriks-akses.md`; LF pada lainnya). |
| Skill | Tidak ada skill di `.claude/skills` yang relevan untuk perbaikan ini (sebagian besar skill desain/animasi); skill `code-review` tidak dipakai. Untuk verifikasi UI dipakai skill **`browser-automation`**. |

## 2. Hasil tes

| Suite | Garis dasar (sebelum ubah) | Akhir |
|---|---|---|
| SQLite (memori, bawaan `phpunit.xml`) | OK — 470 tes, 3338 assertion | **OK — 492 tes, 3412 assertion** (470 + 2 tes A-001 + 20 tes `UnduhanDokumenTest`) |
| MySQL 8.4.3 sementara | **17 error + 3 gagal** (470 tes) | **OK — 492 tes, 3412 assertion; 0 error, 0 gagal** |

Garis dasar MySQL yang gagal — 17 error, semuanya `SQLSTATE[42000] 1064 … CAST(nomor_bon AS INTEGER)`: `DashboardTimWidgetsTest::test_tren_konsumsi_tim_saya_…`; `KartuKendaliTest::test_pemakaian_memakai_nomor_bon_…`; `MonitoringPolaPermintaanTest` ×3; `NomorBonPengeluaranTest` ×7; `PengendalianStokTest` ×3; `TandaTanganTahapanTest` ×2. Tiga gagal: `PemeliharaanDataTest::test_arsip_terbentuk_bercap_waktu_beserta_manifesnya` dan dua kasus `test_pemulihan_menolak_arsip_yang_tidak_sah` ("penggerak tidak cocok", "curahan tidak ada").

## 3. Per ID

| ID | Status | Berkas yang diubah | Tes | Bukti verifikasi | Catatan |
|---|---|---|---|---|---|
| **A-001** | **Diperbaiki** | `app/Services/StokService.php` (`terbitkanNomorBon`, +5/−1 baris) | `NomorBonPengeluaranTest`: `test_penomoran_berurutan_melewati_batas_satu_ke_dua_digit` (008 → 009, 010, 011) dan `test_nomor_tanpa_lapis_nol_dibandingkan_sebagai_angka` (bon lama `9` dan `10` → `011`; sebagai teks "9" akan menang) | 17 error MySQL hilang; kelas ini 9 tes OK di SQLite **dan** MySQL 8.4.3 | Dipilih percabangan menurut driver (`INTEGER` untuk SQLite, `SIGNED` untuk lainnya), sama polanya dengan `KartuKendaliService::petikTahun()`: perubahan terkecil, tetap satu kueri `ORDER BY … LIMIT 1` (alternatif "ambil maksimum di PHP" memuat seluruh baris tahun itu). Hasil identik: nomor terbesar `tahun_bon` + 1, berlapis nol tiga digit. Pencarian sintaks khusus SQLite lain (`strftime`, `julianday`, `GLOB`, `IFNULL`, `AS INTEGER`, `datetime('now')`, `PRAGMA`, `group_concat`) pada `app/`, `routes/`, `config/`, `database/seeders`, `database/factories`: **hanya** `KartuKendaliService.php:371` yang sudah bercabang menurut driver. Tidak ada C- dari pencarian ini. **MariaDB (XAMPP) tidak dijalankan**: `CAST(… AS SIGNED)` sah di MariaDB menurut dokumentasinya, tetapi tidak diuji di sini. |
| **A-016** | **Diperbaiki** (dengan koreksi, lihat C-002) | `tests/Feature/PemeliharaanDataTest.php` (+5), `tests/Feature/RiwayatPenempatanAsetTest.php` (+4), `tests/Feature/SinkronisasiAsetTetapTest.php` (+9) | tes yang ada; tidak ada tes baru | `PemeliharaanDataTest`: 11 tes OK di SQLite **dan** MySQL. Kebocoran storage: sebelum, `RiwayatPenempatanAsetTest` dan `SinkronisasiAsetTetapTest` menulis `bast-mutasi/BAST-UJI-0001.pdf` ke disk nyata (dibuktikan dengan cap waktu berkas per kelas tes); sesudah `Storage::fake('public')` cap waktunya tidak berubah | (a) `PemeliharaanDataTest::setUp()` kini memaksa `database.default = sqlite` untuk kelas itu saja, karena tes-tesnya memang memeriksa cadangan/pemulihan berkas SQLite miliknya sendiri; ini membuatnya bebas driver dan — bonus keselamatan — pemulihan/migrasi di kelas ini tidak lagi berjalan pada basis data rangkaian uji bila rangkaian memakai MySQL. Tidak ada tes yang dihapus atau dilompati. (b) `DokumenBastTest` **sudah** memakai `Storage::fake('public')`; tidak diubah. `BAST-UJI-0001.pdf` yang sudah ada di `storage/app/public/bast-mutasi/` **tidak dihapus**. Tidak ada job CI ditambahkan. |
| **A-002** | **Diperbaiki** | `routes/web.php` (rute `bukti.unduh`, `bast.unduh`) | `UnduhanDokumenTest` (bukti: Tim A/Ketua A 404, Tim B/Ketua B/Admin/Kasubbag/Gudang 200, tanpa berkas 404; BAST: asal/tujuan/Kasubbag/Gudang 200, tim lain 404, Admin 403) | Peramban (lihat bagian 4): unduhan 200 dengan isi **byte-identik** (md5) dengan berkas di storage; Ketua Tim 3 → 404 untuk semua dokumen; Ketua Tim 1 → 404 untuk bukti Tim 2, 200 untuk BAST yang asalnya timnya | Aturan **tidak ditulis ulang**: rute memanggil `PermintaanBarangResource::canAccess()` + `getEloquentQuery()->whereKey($id)->exists()` dan padanannya di `BastMutasiAsetResource`. Diperiksa: `getEloquentQuery()` berjalan baik di luar konteks panel (uji HTTP dan peramban lulus tanpa perantara tambahan), jadi tidak ada kelas/scope baru. Urutan: (BAST) belum disahkan → 404; tanpa akses resource → 403; tidak terlihat → 404; berkas belum ada → 404 (perilaku lama). Nama dan isi unduhan tidak berubah. **Perubahan perilaku:** lihat bagian 5. **Peringatan: C-001.** |
| **A-004** | **Diperbaiki** | `routes/web.php` (variabel `$gerbangAkun` dipakai pada ketiga rute) | `UnduhanDokumenTest`: nonaktif 403, `harus_ganti_sandi` → `GantiKataSandi::getUrl()`, belum lengkap → `LengkapiAkun::getUrl()`, masing-masing untuk 3 rute (9 kasus) | Peramban pada sesi hidup (akun `wanda.pribadi@bps.go.id` pada salinan): `status_aktif=0` → **403**; `harus_ganti_sandi=1` → dialihkan ke `/admin/ganti-kata-sandi`; `no_hp` dikosongkan → `/admin/lengkapi-akun`; dipulihkan → 200 | Gerbang = `Filament\Http\Middleware\Authenticate` (logika `canAccessPanel` yang sama dengan panel) + `PaksaGantiKataSandi` + `PaksaLengkapiAkun`, **dipakai apa adanya tanpa mengubah middleware-nya**; keduanya sudah berfungsi di luar panel karena hanya membaca `$request->user()` dan mengalihkan ke URL halamannya. Diuji mundur (mutation): dengan `routes/web.php` lama, 15 dari 20 tes baru gagal; dengan yang baru semuanya lulus. |
| **A-005** | **Diperbaiki** | `bootstrap/app.php` (+`redirectGuestsTo`), `routes/web.php` (memakai `Authenticate` Filament) | `UnduhanDokumenTest::test_tamu_dialihkan_ke_halaman_masuk_panel` ×3 | `curl` tanpa login pada server verifikasi: `bukti-permintaan/48`, `dokumen-bast/3`, `dokumen-bast/4`, `pusat-bantuan/panduan` → **302 `/admin/login`** (dulu 500); `admin/pengaturan` tetap 302 `/admin/login`; login panel dan dialog Livewire tetap berfungsi pada semua sesi peramban | `redirectGuestsTo(fn () => route('filament.admin.auth.login'))` sesuai permintaan; karena ketiga rute kini memakai `Authenticate` Filament yang sudah mengalihkan tamu ke halaman masuk panel, `redirectGuestsTo` menjadi jaring pengaman untuk rute `auth` lain yang kelak ditambahkan. `/pusat-bantuan/panduan` tanpa berkas tetap 404 bagi yang sudah masuk (tes + peramban). |
| **A-014** | **Diperbaiki** | `app/Filament/Resources/BastMutasiAsets/Tables/BastMutasiAsetsTable.php` (1 baris: `visible` ditambah `filled($r->disahkan_at)`), `routes/web.php` (404 selama `disahkan_at` kosong) | `UnduhanDokumenTest`: `test_tombol_unduh_bast_hanya_tampil_setelah_disahkan`, `test_bast_yang_belum_disahkan_tidak_dapat_diunduh_siapa_pun` | Peramban (daftar Mutasi Aset, salinan berisi satu BAST sah dan satu draf): Kasubbag, Gudang, Ketua Tim, Tim — `BAST-2026-0001` "Unduh BAST" **ADA**, `BAST-2026-0002` (draf) **tidak**; `/dokumen-bast/{draf}` → 404 untuk semua peran | Pembuatan draf, isi PDF, tata letak PDF, dan gaya tombol **tidak diubah**. Draf yang dibuat saat BAST dibuat kini tak lagi dilayani rute mana pun: lihat C-003 (dilaporkan, tidak dihapus). |

## 4. Verifikasi peramban (F.2) dan PDF (F.3)

Server: `php -S 127.0.0.1:8123` pada salinan SQLite (`DB_CONNECTION=sqlite DB_DATABASE=…/verif-b1.sqlite`, bukan `.env`), storage salinan; `php artisan optimize:clear` dijalankan terhadap storage salinan itu (bukan storage repositori). Salinan diisi ulang hanya dengan satu baris BAST draf tambahan (data uji pada salinan) dan pelengkapan akun dua Ketua Tim lain.

| Peran (akun) | Bukti perm. tim 2 | BAST sah (asal tim 1, tujuan tim 2) | BAST draf | Tombol Unduh BAST |
|---|---|---|---|---|
| Admin | 200 | **403** | 404 | — (resource tertutup) |
| Kasubbag | 200 | 200 | 404 | hanya baris sah |
| Petugas Gudang | 200 | 200 | 404 | hanya baris sah |
| Ketua Tim 2 (pemilik) | 200 | 200 | 404 | hanya baris sah |
| Tim 2 (pemilik) | 200 | 200 | 404 | hanya baris sah |
| Ketua Tim 1 (asal BAST) | **404** | 200 | 404 | hanya baris sah |
| Ketua Tim 3 (pihak lain) | **404** | **404** | 404 | (daftar kosong) |
| Tamu | 302 → login | 302 → login | 302 → login | — |

Tombol **Unduh Bukti** dari dialog Rincian pada halaman Riwayat (Ketua Tim 2) menghasilkan tautan `/bukti-permintaan/56` → 200, `attachment; filename=PB-2026-0010.pdf`.

**PDF tidak berubah:** md5 hasil unduh = md5 berkas di `storage/app/public/` repositori — `PB-2026-0002.pdf` `c8b63829dff53416ac2a21dd4720e2c4`, `BAST-2026-0001.pdf` `d6248330f9ad28b9273a9e4289c29a90`. Tidak ada kode pembuat PDF, tampilan PDF, atau layanan dokumen yang disentuh (`DokumenBastService`, `DokumenPermintaanService`, `resources/views/pdf/*` tidak berubah). Yang **tidak** diperiksa: hasil pembuatan ulang PDF pada data yang sama (tidak ada perubahan kode ke arah itu).

Tidak ada dependensi, migration, tabel, field, seeder, atau job CI baru.

## 5. Perubahan perilaku

1. **Admin Sistem tidak lagi dapat mengunduh BAST lewat URL** (403). Resource Mutasi Aset memang tertutup baginya; sebelumnya URL langsung masih terbuka.
2. **BAST yang belum disahkan tidak dapat diunduh siapa pun** (404), termasuk Kasubbag dan Gudang; tombolnya juga disembunyikan.
3. Ketua Tim/Tim hanya dapat mengunduh bukti tim sendiri dan BAST yang asal/tujuannya tim sendiri; selebihnya 404 (dulu 200).
4. Akun nonaktif kini 403 dan akun wajib-ganti-sandi/belum-lengkap dialihkan **pada ketiga rute unduhan**, termasuk `/pusat-bantuan/panduan`.
5. Tamu ke rute unduhan → 302 halaman masuk panel (dulu 500).
6. Tes `PemeliharaanDataTest` kini selalu memakai SQLite miliknya sendiri.

## 6. ID baru (tidak diperbaiki)

| ID | Kategori | Keparahan | Temuan | Bukti |
|---|---|---|---|---|
| **C-001** | Keamanan | **Tinggi** (Kritis bila `storage:link` dijalankan seperti direncanakan) | Bukti dan BAST disimpan pada disk `public` (`storage/app/public/bukti-permintaan/…`, `…/bast-mutasi/…`), sedangkan `docs/finalisasi-akhir.md:423` dan `:765` mencantumkan `php artisan storage:link` sebagai langkah pemasangan. Bila tautan itu dibuat, setiap PDF dapat dibuka **tanpa login** lewat `/storage/bukti-permintaan/PB-2026-0002.pdf` dan `/storage/bast-mutasi/BAST-2026-0001.pdf` — namanya berpola dan dapat ditebak (kode permintaan / nomor BAST) — sehingga seluruh pembatasan A-002/A-004/A-014 dapat dikelak. Draf BAST (memuat gambar tanda tangan Ketua Tim asal) juga ada di sana. | `config/filesystems.php:41-48` (disk `public`, `url = APP_URL/storage`); `DokumenPermintaanService`/`DokumenBastService` menulis ke disk `public`; nama berkas terlihat di `storage/app/public/`. **Tidak diverifikasi:** `public/storage` tidak ada pada working tree ini, jadi eksposur belum dapat dicoba; kondisional pada pemasangan. Pemilik perlu memutuskan (mis. pindah ke disk privat dengan rute yang sama, atau tidak menjalankan `storage:link`). |
| **C-002** | Konsistensi (koreksi audit) | Rendah | Koreksi atas **A-016** pada `laporan-audit.md`: dinyatakan `DokumenBastTest` menulis ke disk asli. Ternyata `DokumenBastTest` sudah memakai `Storage::fake('public')`; penulis sebenarnya adalah `RiwayatPenempatanAsetTest` dan `SinkronisasiAsetTetapTest` (dibuktikan dengan menjalankan tiap kelas tes dan memeriksa cap waktu `BAST-UJI-0001.pdf`). Keduanya sudah diperbaiki (A-016). | Menjalankan 10 kelas tes yang menyentuh BAST satu per satu terhadap storage salinan: hanya dua kelas itu yang memperbarui berkas. |
| **C-003** | Sia-sia | Rendah | `CreateBastMutasiAset::afterCreate()` (`:26-31`) tetap membuat PDF draf saat BAST dibuat, padahal kini tak ada jalur (tombol maupun rute) yang melayaninya sampai BAST disahkan dan berkas ditimpa pada path yang sama. Dilaporkan sesuai instruksi, **tidak dihapus**. Opsi: biarkan / hapus pembuatan draf / tandai "DRAF" bila kelak dipakai. Berkaitan dengan C-001 (draf bertanda tangan Ketua asal berada di disk `public`). | Kode: `CreateBastMutasiAset.php:26-31`; `MutasiAsetService::sahkan()` menimpa berkas yang sama. |
| **C-004** | Keamanan | Rendah | Rute unduhan berada di luar panel sehingga tidak dikenai `AuthenticateSession` (bagian dari middleware panel): sesi lain milik akun yang baru mengganti sandinya tetap dapat mengunduh sampai sesinya habis. | *(kode)* `AdminPanelProvider.php` (`AuthenticateSession` hanya di middleware panel); `$gerbangAkun` tidak memuatnya. Tidak diuji dinamis. |

## 7. Tidak dapat diverifikasi

- **MariaDB (XAMPP):** tidak diuji; hanya MySQL 8.4.3 dan SQLite.
- Eksposur C-001 (butuh `storage:link` / peladen sebenarnya).
- Pembuatan ulang PDF pada data yang sama tidak dibandingkan (tak ada perubahan kode PDF); yang dibandingkan adalah byte unduhan dengan berkas tersimpan.
- Unduhan lewat klik langsung pada tombol tabel BAST di peramban tidak dijalankan; yang diuji: keberadaan tombol per baris dan status HTTP tautannya.
- `optimize:clear` dijalankan terhadap storage/cache salinan. Perintah itu juga memuat `clear-compiled` yang dapat menghapus `bootstrap/cache/packages.php` dan `services.php` milik repositori (berkas ini dibuat ulang otomatis pada boot berikutnya; keduanya ada saat pemeriksaan akhir).

## 8. Berkas berubah

Diubah: `app/Services/StokService.php`, `routes/web.php`, `bootstrap/app.php`, `app/Filament/Resources/BastMutasiAsets/Tables/BastMutasiAsetsTable.php`, `tests/Feature/PemeliharaanDataTest.php`, `tests/Feature/RiwayatPenempatanAsetTest.php`, `tests/Feature/SinkronisasiAsetTetapTest.php`, `tests/Feature/NomorBonPengeluaranTest.php`, `docs/audit/matriks-akses.md`.
Baru: `tests/Feature/UnduhanDokumenTest.php`, `docs/audit/laporan-perbaikan.md`.
Beberapa berkas di atas sudah memuat perubahan pemilik yang belum di-commit (status `MM`); `git add` akan memasukkan keduanya.

---

# Batch 2 — stok, kedaluwarsa, katalog, penomoran, Stok Masuk

Tanggal: 21 September 2026. Mengerjakan **A-003, A-030, A-006, A-029 (bersyarat), A-017, A-020, A-021**. `laporan-audit.md`, C-001 sampai C-004, dan temuan lain tidak disentuh.

## B2.1 Cara kerja dan pengaman

| Butir | Keterangan |
|---|---|
| Git | Hanya baca (`branch --show-current`, `rev-parse`, `status`, `diff`). Cabang `main`, HEAD `b79831d`. Perubahan pemilik dan hasil Batch 1 yang belum di-commit dibiarkan utuh. |
| Cadangan | `E:\Projek Skripsi\cadangan-audit\batch-2\`: `kondisi-awal.txt` (status + diff stat sebelum sentuhan pertama), `asli\` (salinan berkas sebelum diubah), `sha-awal.tsv`, `manifest.md` (sha256 awal/akhir dan hitungan baris `git diff --no-index` per berkas), `keluaran-awal\` dan `keluaran-akhir\` (keluaran beku). |
| Basis data | Basis data kerja tidak disentuh (dari `.env` hanya `DB_CONNECTION=sqlite` dibaca). MySQL 8.4.3 sementara: biner Laragon `mysql-8.4.3-winx64`, datadir di scratchpad sesi, `127.0.0.1:3399`. Sebelum tiap tes MySQL dicetak `DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_audit_test`, berbeda dari `.env`. Layanan MySQL Laragon (proses `mysqld` 8.0.30 pada 3306) tidak disentuh. |
| Storage dan cache | Semua tes dan server verifikasi memakai storage salinan (`LARAVEL_STORAGE_PATH`); `storage/` repositori tidak ditulisi. `optimize:clear` **tidak** dijalankan. `bootstrap/cache` sebelum dan sesudah: `packages.php`, `services.php` (keduanya bertanggal 17:51, tidak berubah). |
| Pemformatan | `pint` tidak dijalankan; suntingan per blok dengan akhiran baris berkas dipertahankan (CRLF: `KedaluwarsaService`, `CreateBastMutasiAset`, `StokMasuk`, `matriks-akses.md`; LF: lainnya). |
| Skill | Tidak ada skill di `.claude/skills` yang relevan untuk perbaikan ini. Untuk verifikasi UI dipakai **`browser-automation`**. |

## B2.2 Hasil tes

| Suite | Garis dasar (sebelum ubah) | Akhir |
|---|---|---|
| SQLite (memori) | OK — 492 tes, 3412 assertion | **OK — 526 tes, 3617 assertion** (492 + 34 tes baru) |
| MySQL 8.4.3 sementara | OK — 492 tes, 3412 assertion | **OK — 526 tes, 3617 assertion; 0 error, 0 gagal** |

Tes baru (34): `PersetujuanTidakMelebihiDimintaTest` 8, `StokTerkunciBacaSajaTest` 4, `NotifikasiKedaluwarsaTest` 5, `PengajuanKatalogAturanTest` 7, `PenomoranBastTest` 6, `KodeBarangBaruStokMasukTest` 4. Uji mundur: dengan berkas kode asli dikembalikan sementara, 28 entri gagal atau error dari 34 tes baru (sisanya menguji perilaku yang tetap benar pada kode lama); dengan kode baru semuanya lulus. Kode baru sudah dipulihkan dan diperiksa identik.

## B2.3 Per ID

| ID | Status | Berkas yang diubah | Tes | Bukti verifikasi | Catatan |
|---|---|---|---|---|---|
| **A-003** | **Diperbaiki** | `PermintaanBarangResource.php` (+21), `StokService.php` | `PersetujuanTidakMelebihiDimintaTest`: skenario audit stok 10/kunci 5 (R1=8 ditolak, stok dan kunci tetap; R1=2 → kunci R2=3 utuh dan R2 selesai sampai Sahkan; sebagian R1=1 → 1 unit dilepas, keduanya selesai, tanpa saldo negatif); muatan dimodifikasi; penjaga penangan; layanan; data lama rusak | Peramban (Kasubbag): kolom Disetujui berlabel `max=2` untuk diminta 2; dengan batas HTML dan validasi peramban dilepas (klien yang dimodifikasi) dan nilai 3, server menampilkan "Jumlah disetujui tidak boleh melebihi jumlah diminta (2)." dan tidak menyimpan; nilai 2 disetujui → status `siap_diproses`, `jumlah_final=2` | Batas atas dibaca dari **rincian tersimpan** (`$record->detail`), bukan dari isian formulir, sehingga menaikkan "Diminta" pada muatan tak menembusnya. Lapis kedua: `StokService::pastikanTidakMelebihiDiminta()` dipanggil penangan sebelum transaksi (notifikasi, tanpa 500, tanpa mengubah status/stok) dan di awal `sesuaikanHold()`. Kunci per permintaan **tidak tersimpan tersendiri** (hanya agregat `stok_hold`); ia diturunkan sebagai `min(jumlah_final ?? diminta, diminta)`, dan `release()` serta `konversi()` tidak lagi mengurangi kunci lebih dari itu. Alur, status, notifikasi, format nomor tidak berubah. |
| **A-030** | **Diperbaiki** | `BarangPersediaanForm.php` (+4/−1) | `StokTerkunciBacaSajaTest`: Ubah tidak mengubah `stok_hold` walau muatan `data.stok_hold=99`; kolom tetap tampil berlabel "Stok Terkunci" dan disabled; `stok_fisik` tetap dapat diubah lewat dialog konfirmasi; Buat berhasil dengan `stok_hold` 0 walau muatan 50 | Peramban (Kasubbag, Ubah Barang): `stok_hold` `disabled=true`, `stok_fisik` `disabled=false` | Kolom `stok_hold` bernilai bawaan 0 pada migration (`NOT NULL DEFAULT 0`), jadi tidak dikirim saat Buat tidak memicu galat. Label dan keterangan tidak diubah; `required()` diganti `disabled()->dehydrated(false)`. `EditBarangPersediaan.php` tidak diubah. |
| **A-006** | **Diperbaiki** | `KedaluwarsaService.php` (+38/−3) | `NotifikasiKedaluwarsaTest`: tepat 1 notifikasi dalam aplikasi untuk pemohon; sapuan kedua 0 tambahan; 1 baris WhatsApp bila kanal menyala; permintaan yang sudah ditangani sejak daftar dibaca dilewati tanpa notifikasi dan tanpa pelepasan kunci; kegagalan notifikasi tidak menggagalkan sapuan | Peramban: setelah satu permintaan `menunggu_ketua` dengan batas lampau disapu oleh permintaan panel Kasubbag, lonceng Tim (pemohon) menampilkan "Permintaan kedaluwarsa — PB-2026-0003 melewati batas waktu dan stok yang dikunci telah dilepaskan."; basis data memuat satu baris dalam aplikasi dan satu WhatsApp per anggota tim | Teks arm `'kedaluwarsa'` tidak diubah. Status diperiksa ulang di dalam transaksi dengan `lockForUpdate()`; yang tidak diubah sapuan ini dikeluarkan dari hasil (`->filter()->values()`), jadi tidak diberitahu dan tidak dihitung perintah `permintaan:lepas-hold`. Notifikasi dipanggil sesudah commit dalam `try/catch` sendiri dengan `report($e)`; pemanggil lain memanggil `permintaanBerubah` tanpa `try/catch`, tetapi persyaratan batch (sapuan dan permintaan panel tidak boleh 500) menuntut isolasi ini. |
| **A-029** | **Dilewati** (syarat tidak terpenuhi) | *(tidak ada)* | *(tidak ada)* | — | Pemeriksaan syarat (baca saja): **(1) TIDAK terpenuhi** — `database/migrations/2026_08_26_000010_create_riwayat_persetujuan_table.php:30` mendefinisikan `foreignId('pelaksana_id')->constrained('users')->restrictOnDelete()` **tanpa** `nullable()`, dan tidak ada migration lain yang mengubah kolom itu; mengisi null menuntut migration baru (dilarang). (2) Pembaca riwayat sudah aman terhadap pelaksana kosong (`detail-permintaan.blade.php:330` memakai `?->name ?? '—'`; `DokumenPermintaanService::pelaksana()` null-safe) dan penentu "Lewat batas waktu" bergantung pada status dan baris riwayat terakhir (`:39-43`, `:292`), bukan pada `pelaksana_id`. (3) Perbaikan tidak menuntut perubahan PDF/ekspor. Karena syarat 1 gagal, tidak ada yang diubah. Baris kedaluwarsa tetap memakai pola lama (pelaksana = pemohon, keputusan `tolak`). Keputusan pemilik: apakah akan dibuatkan migration `nullable` pada batch tersendiri. |
| **A-017** | **Diperbaiki** | `StokService.php` (`hold()`, `pesanAturan()`), `KatalogBarang.php` (+31/−3) | `PengajuanKatalogAturanTest`: barang nonaktif ditolak tanpa permintaan dan tanpa kunci (termasuk kunci barang aktif lain dalam pengajuan yang sama); barang aktif berhasil; stok tidak cukup tetap menampilkan pesan bisnisnya; kegagalan teknis (QueryException disimulasikan) menampilkan pesan umum, bukan SQL | Peramban: Katalog untuk Tim tidak menampilkan barang nonaktif ("Binder Clips No. 155" → 0 baris); penolakan sisi server lewat tes | Pembeda pesan: hanya `\RuntimeException` dan `\InvalidArgumentException` **persis** (kelas yang dilemparkan sengaja oleh `StokService`) yang tampil apa adanya; `QueryException`, `ModelNotFoundException`, dan semua turunannya (yang juga `RuntimeException`) dicatat dengan `report()` dan diganti "Coba lagi, atau hubungi Sub-Bagian Umum bila berulang." `hold()` menolak `status_aktif=false` dengan "{nama} tidak tersedia untuk diminta." |
| **A-020** | **Diperbaiki** | `MutasiAsetService.php` (+10/−4), `CreateBastMutasiAset.php` (+29), `KatalogBarang.php` | `PenomoranBastTest`: nomor pertama; celah `0001,0002,0003` dihapus `0002` → berikutnya `0004` (bukan bentrok `0003`); tahun lain tak dihitung; pembuatan lewat form setelah celah; bentrok sekali → diulang; bentrok tiga kali → berhenti dengan pesan umum. `PengajuanKatalogAturanTest`: kode permintaan bentrok sekali diulang, bentrok berulang dibatasi 3 kali; kode berikutnya dari nomor terbesar dengan celah | Tes di SQLite dan MySQL; format `BAST-YYYY-NNNN` dan `PB-YYYY-NNNN` tidak berubah | Nomor BAST kini dari nomor terbesar berawalan `BAST-{tahun}-` (dibaca lalu dimaksimumkan di PHP, agar tak bergantung pada `CAST` khusus driver). Model **tidak** memakai `SoftDeletes` (dicek), jadi tak ada baris terhapus lunak untuk dihitung. Deteksi bentrok memakai `Illuminate\Database\UniqueConstraintViolationException`, yang dilempar Laravel untuk MySQL maupun SQLite. Kode permintaan sudah berbasis nomor terbesar (bukan `count()`); ditambah pengulangan. **Keterbatasan:** konkurensi sungguhan tidak dapat diuji di sini; bentrok disimulasikan dengan menyisipkan baris bernomor sama tepat sebelum penyimpanan. Pada pengajuan Katalog simulasi itu ikut tergulung bersama transaksi uji, jadi yang dibuktikan adalah pengulangannya (tetap satu permintaan, tanpa kunci ganda, tanpa pesan gagal). |
| **A-021** | **Diperbaiki** | `StokMasuk.php` (+47/−9) | `KodeBarangBaruStokMasukTest`: kode sama pada kategori sama terdeteksi termasuk spasi ujung; kategori lain atau kode baru diterima; barang baru berstok nol dengan kode terpangkas; pelanggaran UNIQUE yang lolos validasi → notifikasi + `Halt`, bukan SQL | Peramban (Petugas Gudang, Stok Masuk → Catat → Barang Baru): kategori "Alat Tulis" + kode `000122 ` (ganda, dengan spasi ujung) → "Kode barang sudah dipakai pada kategori ini." tanpa galat SQL; kode `000122-UJI` diterima dan barang terbuat dengan stok 0 | Satu-satunya jalur pembuatan `BarangPersediaan` pada halaman ini adalah `createOptionUsing` (diperiksa: tidak ada `create`/`firstOrCreate`/`insert` lain). Ditambah aturan pada kolom kode (dibandingkan terpangkas dan disaring per `kategori_id`), `dehydrateStateUsing(trim)`, dan `simpanBarangBaru()` yang menangkap `UniqueConstraintViolationException`. Hak Petugas Gudang membuat barang dipertahankan; resource Barang Persediaan tetap tertutup baginya (`canAccess` tidak diubah). |

## B2.4 Keluaran beku (aturan B)

Skenario yang sama dijalankan **sebelum** dan **sesudah** perubahan (skrip di luar repositori, basis data SQLite memori, waktu dibekukan `2026-09-22 09:00 Asia/Jakarta`): siklus penuh pengajuan → Sahkan (dengan persetujuan sebagian), satu BAST dibuat → disahkan → dikonfirmasi, satu permintaan kedaluwarsa, Kartu Kendali (PDF dan XLSX), dan ekspor Riwayat keseluruhan (PDF dan XLSX). Isi PDF diekstrak dengan `pdftotext -layout`, sel XLSX dibaca dengan PhpSpreadsheet, token QR 40 aksara dinormalkan. Dua run tanpa perubahan kode terbukti identik (skenario deterministik). Perbandingan `cmp` sebelum vs sesudah:

| Keluaran | Hasil |
|---|---|
| Bukti permintaan (teks PDF) | identik |
| Bukti permintaan berfootnote (teks PDF) | identik |
| BAST disahkan (teks PDF) | identik |
| Kartu Kendali (teks PDF) | identik |
| Kartu Kendali (sel XLSX, seluruh lembar) | identik |
| Ekspor Riwayat semua jenis (teks PDF) | identik |
| Ekspor Riwayat semua jenis (sel XLSX) | identik |
| Dialog Rincian permintaan kedaluwarsa (teks) | identik |
| Baris riwayat kedaluwarsa (`tahap`, `keputusan`, `catatan`, pelaksana ada) | identik (A-029 dilewati) |

Berkas yang diubah oleh batch ini: `PermintaanBarangResource.php`, `StokService.php`, `BarangPersediaanForm.php`, `KedaluwarsaService.php`, `KatalogBarang.php`, `MutasiAsetService.php`, `CreateBastMutasiAset.php`, `StokMasuk.php`, dan `docs/audit/matriks-akses.md`, ditambah enam berkas tes baru. **Tidak ada view Blade PDF, kelas/kueri ekspor, template, atau definisi kolom di dalamnya.** Tidak ada dependensi, migration, tabel, field, seeder, atau job CI baru.

## B2.5 Perubahan perilaku

1. Kasubbag tidak dapat lagi menyetujui lebih dari jumlah diminta (formulir dan server).
2. Kolom "Stok Terkunci" pada form Barang Persediaan hanya tampil.
3. Permintaan yang kedaluwarsa kini diberitahukan kepada pemohon dan anggota timnya (dalam aplikasi dan, bila diaktifkan, WhatsApp), sekali saja.
4. Barang nonaktif ditolak saat pengajuan; galat teknis pada pengajuan tampil sebagai pesan umum (detail masuk log).
5. Nomor BAST dan kode permintaan yang bentrok kini diulang (maks. 3 kali) sebelum gagal dengan pesan umum.
6. Kode Barang Baru di Stok Masuk wajib unik per kategori, spasi ujung dipangkas.

## B2.6 ID baru (tidak diperbaiki)

| ID | Kategori | Keparahan | Temuan | Bukti |
|---|---|---|---|---|
| **D-001** | Kinerja | Rendah | Notifikasi kedaluwarsa (A-006) terbit **di dalam permintaan panel** yang kebetulan memicu sapuan (`SapuPermintaanKedaluwarsa`); pengguna pertama sesudah sejumlah permintaan kedaluwarsa menanggung penyisipan baris notifikasi (pengiriman WhatsApp tetap diantrekan). Belum diukur pada beban nyata. | *(kode)* `KedaluwarsaService::sapu()` memanggil `permintaanBerubah()` sinkron; jeda sapuan 60 detik menahan frekuensinya. |
| **D-002** | Bug (data) | Rendah | Baris `detail_permintaan_barang` lama yang sudah memiliki `jumlah_final > jumlah_diminta` (mungkin tercipta sebelum perbaikan A-003) tidak dapat diperiksa dari sini. Efeknya sudah dibatasi (kunci yang dilepas ≤ diminta), tetapi stok fisik yang dikeluarkan `konversi()` tetap mengikuti `jumlah_final`. | Basis data kerja tidak boleh disentuh; pemilik dapat memeriksa dengan `SELECT ... WHERE jumlah_final > jumlah_diminta`. |

## B2.7 Tidak dapat diverifikasi

- **Konkurensi sungguhan** (A-020 dan penyapuan A-006 yang benar-benar tumpang tindih): hanya disimulasikan.
- **MariaDB (XAMPP)** tidak dijalankan; hanya MySQL 8.4.3 dan SQLite.
- Klik bawaan Playwright pada tombol baris "Tinjau" tidak memicu Livewire pada halaman verifikasi; klik lewat DOM berfungsi dan dipakai untuk penelusuran. Ini keterbatasan alat, bukan temuan aplikasi.
- Gelembung validasi bawaan peramban ("Value must be less than or equal to 2.") mengikuti bahasa peramban; pesan server berbahasa Indonesia terlihat setelah batas HTML dilepas.
- Dialog Barang Baru diuji lewat peramban pada salinan SQLite; MySQL sementara diuji lewat tes otomatis (bukan lewat peramban).
- Keluaran beku dibandingkan pada SQLite; tidak diulang pada MySQL.

---

# C-001 — dokumen di disk privat

Tanggal: 21 September 2026. Memperbaiki **C-001** (temuan Batch 1): PDF bukti permintaan dan BAST disimpan di disk `public` sehingga, setelah `storage:link`, dapat dibuka tanpa login lewat `/storage/...`. Hanya C-001 yang dikerjakan.

## C1.1 Cara kerja dan pengaman

| Butir | Keterangan |
|---|---|
| Git | Hanya baca. Cabang `main`, HEAD `b79831d`; perubahan pemilik dan hasil Batch 1–2 yang belum di-commit dibiarkan utuh. |
| Cadangan | `E:\Projek Skripsi\cadangan-audit\batch-2b-c001\`: `kondisi-awal.txt`, `asli\`, `sha-awal.tsv`, `manifest.md`, `keluaran-awal\`, `keluaran-akhir\`. |
| Basis data | Basis data kerja tidak disentuh (dari `.env` hanya `DB_CONNECTION=sqlite` dibaca). MySQL 8.4.3 sementara `127.0.0.1:3399`, `DB_DATABASE=simpbi_audit_test` (dicetak sebelum tiap run; berbeda dari `.env`); biner Laragon 8.4.3, datadir di scratchpad; MySQL Laragon (3306) tidak disentuh. |
| Storage dan cache | Semua tes dan perintah artisan memakai storage salinan. **`simpbi:pindah-dokumen` hanya dijalankan pada storage salinan** (bukan storage repositori). `optimize:clear` tidak dijalankan. `storage/app` repositori tidak ditulisi selama batch ini. **Koreksi, lihat E-001:** server `php -S` verifikasi pada Batch 1–2 (dan satu percobaan pertama di batch ini) mengabaikan `LARAVEL_STORAGE_PATH`. |
| Skill | Tidak ada skill di `.claude/skills` yang relevan; verifikasi UI memakai `browser-automation`. |

## C1.2 Inventarisasi (dibuat sebelum mengubah)

| # | Temuan |
|---|---|
| 1. Penulis | `DokumenBastService.php:84` (`bast-mutasi/{nomor_bast}.pdf`; dipanggil saat BAST dibuat = **draf**, saat disahkan, dan saat dikonfirmasi lewat `MutasiAsetService`); `DokumenPermintaanService.php:83` (`bukti-permintaan/{kode}.pdf`) dan `:94` (`bukti-permintaan/{kode}-berfootnote.pdf`). Semuanya `Storage::disk('public')`. |
| 2. Pembaca | `routes/web.php`: `bukti.pindai` `/bukti/{token}` (berfootnote, inline), `bukti.asli` `/bukti/{token}/asli` (asli, inline), `bast.unduh`, `bukti.unduh` — semuanya `Storage::disk('public')`. Aksi Unduh pada tabel Filament (`PermintaanBarangResource::aksiUnduhBukti`, `BastMutasiAsetsTable`) memakai `route()`, bukan path langsung. Tidak ada `Storage::url()`, `asset('storage/...')`, atau path `/storage` pada kode aplikasi maupun view. |
| 3. Kolom database | `permintaan_barang.file_bukti_path`, `bast_mutasi_aset.file_bast_path`: path **relatif** (`bukti-permintaan/PB-2026-0002.pdf`, `bast-mutasi/BAST-2026-0001.pdf`). Berkas berfootnote **tidak tercatat** di kolom mana pun; lintasannya diturunkan dari kode permintaan (`DokumenPermintaanService::lintasanBerfootnote`). |
| 4. Tanda tangan | **Sudah di disk privat**: `TandaTangan.php` memakai `Storage::disk('local')` (`storage/app/private/tanda-tangan/`). Bukan bagian dari masalah ini dan tidak diubah; yang bocor adalah PDF yang menyematkan gambarnya. |
| 5. Disk | `config/filesystems.php`: `local` (root `storage/app/private`, sudah dipakai tanda tangan dan panduan `config/pusat_bantuan.php`) dan `public` (root `storage/app/public`, `url = APP_URL/storage`, tautan `public/storage`). Dipakai disk `local` yang **sudah ada**; tidak ada disk baru. |
| 6. Cadangan | `Cadangan::DATA` memetakan kunci arsip `storage/public/bukti-permintaan` dan `storage/public/bast-mutasi` ke `storage/app/public/...`. `ResetDemo` menyapu nilai peta yang sama. |
| 7. Tes | `Storage::fake('public')` dan `disk('public')` pada dokumen dipakai di 9 kelas tes (lihat C1.4). |

## C1.3 Hasil tes

| Suite | Garis dasar (sebelum ubah) | Akhir |
|---|---|---|
| SQLite (memori) | OK — 526 tes, 3617 assertion | **OK — 539 tes, 3696 assertion** (526 + 13 tes baru) |
| MySQL 8.4.3 sementara | OK — 526 tes, 3617 assertion | **OK — 539 tes, 3696 assertion; 0 error, 0 gagal** |

Uji mundur: dengan `DokumenBastService`, `DokumenPermintaanService`, dan `routes/web.php` asli dikembalikan sementara, 9 tes gagal (4 di `DokumenDiDiskPrivatTest`, 2 di `UnduhanDokumenTest`, 3 di `PemindaianBuktiTest`); dengan kode baru semuanya lulus (40 tes pada empat kelas, sudah dipulihkan dan diperiksa identik). Tanpa penyesuaian tes lama, penulisan/pembacaan ke disk privat membuat 11 tes lama gagal seperti yang diharapkan (mereka membaca disk `public`); semuanya sudah diperbarui (C1.4). Suite akhir dijalankan dengan storage salinan yang **tidak** memuat folder `bukti-permintaan`/`bast-mutasi` di `app/private`; sesudahnya folder itu tetap tidak ada — tak ada tes yang menulis dokumen ke storage nyata.

## C1.4 Perubahan

| Berkas | Perubahan |
|---|---|
| `app/Services/DokumenBastService.php` (+3/−1) | `Storage::disk('public')` → `Storage::disk('local')` (path relatif sama, termasuk draf). |
| `app/Services/DokumenPermintaanService.php` (+4/−2) | idem untuk berkas asli dan berfootnote. |
| `routes/web.php` (+8/−8) | Enam pembacaan pada empat rute kini dari disk `local`. Perilaku luar sama: `Storage::download` untuk rute unduhan; `response(get(), 200, ...)` dengan header `Content-Type`/`Content-Disposition: inline` untuk rute bertoken; status 404 sama. Gerbang A-002/A-004/A-005 tidak disentuh. |
| `app/Support/Cadangan.php` (+5/−2) | Nilai `DATA` untuk dokumen menjadi `app/private/bukti-permintaan` dan `app/private/bast-mutasi`. **Kunci arsip sengaja tidak diubah** (`storage/public/...`) sehingga arsip lama tetap dapat dipulihkan — ke lokasi privat yang baru. Perubahan dua baris; `simpbi:backup` dan `simpbi:restore` tidak diubah selain itu. |
| `app/Console/Commands/PindahkanDokumen.php` (**baru**, 195 baris) | `simpbi:pindah-dokumen` (lihat C1.5). |
| `docs/finalisasi-akhir.md` (+19/−2) | Bagian 20 (pemasangan): `storage:link` tidak diperlukan; langkah `simpbi:pindah-dokumen`; subbagian "Dokumen di disk privat" berisi urutan langkah. |
| `docs/pemeliharaan-data.md` (+2/−2) | Lokasi dokumen pada tabel "Yang dicadangkan". |
| `docs/audit/matriks-akses.md` (+4/−2) | Catatan pada Tabel 1 dan Tabel 4. |
| Tes lama (disk `public` → `local` pada baca/assert; `Storage::fake('local')` ditambahkan bila belum ada) | `BastTandaTanganTersimpanTest`, `DokumenBastTest`, `DokumenBuktiTest`, `PemindaianBuktiTest`, `UnduhanDokumenTest`, `RiwayatPenempatanAsetTest`, `SinkronisasiAsetTetapTest`, `PemeliharaanDataTest` (jalur `app/public` → `app/private`). `Storage::fake('public')` dipertahankan sebagai penjaga. Tidak ada tes yang dihapus. |
| Tes baru (13) | `DokumenDiDiskPrivatTest` (4): bukti asli+berfootnote dan BAST (draf dan disahkan) ada di disk privat dengan path relatif yang sama dan **tidak** ada di disk public; dokumen lama yang masih di public tidak terbaca rute sebelum dipindah; tak ada lagi `disk('public')`/`Storage::url`/`asset('storage` pada layanan dokumen dan rute. `PindahDokumenTest` (9): dry-run tidak mengubah apa pun; `--jalankan` menyalin dan memverifikasi tanpa menghapus asli; `--hapus-asli` tanpa `--jalankan` ditolak; `--hapus-asli` menghapus asli hanya setelah verifikasi; pengulangan aman; berkas hilang dilaporkan tanpa menghentikan perintah; berkas berbeda di tujuan tidak ditimpa dan asli tidak dihapus; hanya dokumen tercatat di basis data yang dipindah (aset publik lain tidak disentuh); salinan yang gagal verifikasi dibuang dan asli tidak disentuh. |

Tidak ada view Blade PDF, kelas ekspor, template, migration, tabel, field, dependensi, atau disk baru yang diubah/dibuat. Nilai path pada kolom database tidak berubah.

## C1.5 Perintah `simpbi:pindah-dokumen`

- Bekerja dari catatan basis data: `file_bukti_path` (dan berfootnote yang diturunkan darinya) dan `file_bast_path`; **tidak** memindai folder.
- Tanpa opsi = rencana (dry-run): daftar "akan disalin", "sudah ada (identik)", "HILANG", "BERBEDA".
- `--jalankan`: menyalin (aliran) dari `public` ke `local`, lalu memverifikasi **ukuran dan sha256**; salinan yang tak cocok dibuang dan asli tidak disentuh.
- `--hapus-asli` (hanya bersama `--jalankan`): menghapus berkas di `public` hanya untuk berkas yang terbukti identik di tujuan. Berkas "BERBEDA" tidak ditimpa dan asli tidak dihapus.
- Idempoten. Berkas hilang dilaporkan tanpa menghentikan perintah. Kode keluar non-nol hanya bila ada salinan gagal atau berkas "BERBEDA".
- Hanya menyentuh dokumen yang tercatat di basis data: berkas lain di `storage/app/public` (mis. artefak tes seperti `BAST-UJI-0001.pdf`, lihat E-002) **tidak** dipindah dan tidak dihapus.

## C1.6 Verifikasi

**Perbandingan isi berkas (aturan "isi tidak berubah").** Skenario yang sama dijalankan **sebelum** dan **sesudah** perubahan (skrip di luar repositori; SQLite memori; waktu dibekukan): siklus penuh pengajuan → Sahkan, satu BAST dibuat (draf) → disahkan → dikonfirmasi, satu permintaan kedaluwarsa, Kartu Kendali (PDF dan XLSX), ekspor Riwayat (PDF dan XLSX). Isi PDF diekstrak dengan `pdftotext -layout`, sel XLSX dengan PhpSpreadsheet, token QR dinormalkan; dua run tanpa perubahan kode identik. `cmp` sebelum vs sesudah: **10 dari 10 identik** — bukti permintaan, bukti berfootnote, BAST draf, BAST disahkan, Kartu Kendali (PDF dan XLSX), ekspor Riwayat (PDF dan XLSX), dialog Rincian kedaluwarsa, baris riwayat kedaluwarsa. Pada skenario "sesudah" dokumen dibaca dari disk privat.

**Simulasi `/storage` terpasang (hanya pada salinan).** Dokumen root khusus di scratchpad (`index.php` yang memuat aplikasi repositori, dengan junction `storage` → `app/public` salinan; tidak ada perubahan pada `public/` repositori), basis data SQLite salinan, storage salinan berisi 15 dokumen lama di disk `public`, server `php -S -d variables_order=EGPCS` (lihat E-001). Hasil (status HTTP; md5 dipotong 10 karakter):

| Tahap | Unduh bukti 48 / BAST 3 (Kasubbag) | `/bukti/{token}` (berfootnote) / `/asli` | `/storage/...` tanpa login |
|---|---|---|---|
| 1. Sebelum pindah (dokumen hanya di public) | 404 / 404 (belum ada di disk privat — sesuai urutan pemasangan) | 404 / 404 | **200** untuk bukti, berfootnote, BAST — *inilah eksposur C-001* |
| 2. Sesudah `--jalankan` (salinan; asli masih ada) | 200 `c8b63829df` / 200 `ae2d47769a` | 200 `2f3cf26eff` / 200 `c8b63829df` | 200 (asli belum dihapus) |
| 3. Sesudah `--jalankan --hapus-asli` dan pembentukan ulang satu BAST oleh kode baru | 200 `c8b63829df` / 200 (isi baru, hanya di privat) | 200 `2f3cf26eff` / 200 `c8b63829df` | **403 untuk ketiganya — tidak lagi terbuka** |

md5 unduhan = md5 berkas sumber (`c8b63829df` bukti asli, `2f3cf26eff` berfootnote, `ae2d47769a` BAST sebelum dibentuk ulang). Sesudah tahap 3 hanya `BAST-UJI-0001.pdf` (artefak tes, tidak tercatat di basis data) tersisa di `app/public` salinan. Unduhan per peran sesuai hak sudah dibuktikan pada Batch 1 (tes `UnduhanDokumenTest` tetap lulus setelah pemindahan disk).

**Uji `simpbi:pindah-dokumen` pada storage salinan berisi berkas lama:** rencana 15 berkas "akan disalin"; `--jalankan` → 15 disalin dan terverifikasi, 0 gagal; `--jalankan --hapus-asli` → 15 sudah ada (identik), 15 asli dihapus; pengulangan → tidak ada perubahan dan tanpa galat.

## C1.7 Langkah untuk pemilik saat pemasangan (urutan)

Perintah ini **dijalankan pemilik sendiri** pada mesin sebenarnya; saya tidak menjalankannya pada data nyata.

1. **Sebelum kode baru dipasang**, dengan kode lama: `php artisan simpbi:backup` (cadangan ini masih memuat dokumen pada lokasi lama).
2. Pasang kode baru. Dokumen lama belum terbaca (404) sampai langkah 4.
3. `php artisan simpbi:pindah-dokumen` — periksa rencananya.
4. `php artisan simpbi:pindah-dokumen --jalankan` — menyalin dan memverifikasi; asli belum dihapus.
5. Buka satu bukti permintaan dan satu BAST dari aplikasi untuk memastikan terbaca.
6. `php artisan simpbi:pindah-dokumen --jalankan --hapus-asli` — menghapus asli hanya setelah terverifikasi. Aman diulang.
7. `php artisan simpbi:backup` lagi (kini mencakup lokasi baru).
8. **Jangan** menjalankan `php artisan storage:link` (tidak ada lagi yang perlu dibuka lewat `/storage`). Bila tautan `public/storage` sudah ada, hapus. Sebelum itu, periksa isi `storage/app/public` (mis. artefak tes seperti `BAST-UJI-0001.pdf`, E-002).

Pada mesin baru tanpa dokumen lama, langkah 1 dan 3–6 tidak diperlukan.

## C1.8 ID baru (tidak diperbaiki)

| ID | Kategori | Keparahan | Temuan | Bukti |
|---|---|---|---|---|
| **E-001** | Proses (koreksi laporan sebelumnya) | Rendah | Server verifikasi `php -S` yang saya pakai pada **Batch 1 dan 2** (dan percobaan pertama di batch ini) **mengabaikan `LARAVEL_STORAGE_PATH`**: variabel itu dibaca dari `$_ENV`, yang kosong pada `php -S` bawaan. Akibatnya server menulis sesi, singgahan, dan log ke `storage/` **repositori**, bukan salinan, sehingga pernyataan "storage/ repositori tidak ditulisi" pada laporan Batch 1 dan 2 **tidak tepat untuk bagian server** (tes PHPUnit dan perintah artisan tidak terpengaruh: variabelnya efektif di CLI). Yang tertulis hanya berkas kerangka kerja *gitignored* (sesi, singgahan, log); dokumen dan `storage/app` tidak disentuh. Basis data tidak terpengaruh (`DB_*` dibaca dari lingkungan nyata dan efektif). Server batch ini diulang dengan `php -d variables_order=EGPCS` dan terbukti menulis ke salinan. | Menghitung berkas sesi: server lama: salinan 21→21, repositori 20→21; server diperbaiki: salinan 21→22, repositori 21→21. Sejak awal batch ini `storage/framework/sessions` repositori bertambah 5 berkas dan `cache` 3 (sebagian mungkin dari server pengembangan pemilik di port 8000; tidak dapat dipisahkan). `storage/app` repositori tidak berubah. Tidak ada berkas yang dihapus. |
| **E-002** | Keamanan | Rendah | Perintah pemindah hanya memindahkan dokumen yang **tercatat di basis data**. Berkas lain di `storage/app/public` tetap di sana dan tetap terjangkau `/storage` bila `storage:link` dijalankan — terutama artefak tes `bast-mutasi/BAST-UJI-0001.pdf` (bukan dokumen sungguhan; hasil kebocoran tes A-016, sudah ditambal, tetapi berkas lamanya tetap ada). | `storage/app/public` repositori berisi 17 berkas, termasuk `BAST-UJI-0001.pdf`. Tidak dihapus (dilarang). Pemilik memutuskan (hapus manual, atau tidak menjalankan `storage:link`). |
| **E-003** | Konsistensi | Rendah | Arsip cadangan yang dibuat kode baru tetap memakai kunci `storage/public/bukti-permintaan` dan `storage/public/bast-mutasi` untuk dokumen yang kini privat (agar arsip lama tetap dapat dipulihkan). Fungsional, tetapi penamaannya menyesatkan bagi yang membuka ZIP. Opsi: biarkan (terdokumentasi di `Cadangan::DATA`) atau ganti kunci dan tambahkan pemetaan arsip lama pada batch tersendiri. | `Cadangan.php` (komentar pada `DATA`). |
| **E-004** | Bug (risiko pemasangan) | Sedang bila urutan dilanggar | Setelah kode baru dipasang dan **sebelum** `simpbi:pindah-dokumen --jalankan`, seluruh dokumen lama mengembalikan 404 (rute membaca disk privat). Cadangan yang dibuat dengan kode **baru** sebelum pemindahan tidak memuat dokumen lama (yang masih di `public`). | Tahap 1 pada tabel simulasi; `Cadangan::DATA`. Karena itu langkah 1 (cadangan dengan kode lama) dan langkah 2–4 dilakukan berurutan pada jendela pemasangan yang sama. |

## C1.9 Tidak dapat diverifikasi

- Perintah `simpbi:pindah-dokumen`, `simpbi:backup`, `simpbi:restore` pada **data nyata** (dilarang). `simpbi:backup`/`simpbi:restore` tidak dijalankan sama sekali di batch ini; perubahan petanya diuji lewat `PemeliharaanDataTest` (SQLite dan MySQL): siklus buat → rusakkan → pulihkan mengembalikan dokumen ke `app/private/...`.
- Pemulihan arsip yang dibuat oleh kode lama (kunci lama) hanya diuji lewat sifat kunci yang tidak diubah, bukan dengan arsip lama sungguhan.
- MariaDB (XAMPP) tidak dijalankan; MySQL 8.4.3 dan SQLite saja.
- Respons `/storage/...` setelah dokumen dihapus adalah 403 pada peladen bawaan PHP dengan junction; pada Apache/Nginx sungguhan bisa 404. Yang dibuktikan: tidak 200.
- Penerbitan PDF sungguhan lewat UI (Sahkan) pada peramban tidak dijalankan; jalur yang sama dijalankan lewat Livewire (tes dan skenario beku).

---

# Batch 3 — Mutasi Aset (BAST), Aset Tetap Tim Saya, form Tim Kerja

Tanggal: 21–22 September 2026. ID yang dikerjakan: **A-011**, **A-018**, **A-031**, dan **A-012** (blok J ada pada permintaan). Temuan lain serta ID C-/D-/E- tidak disentuh; `laporan-audit.md` tidak diubah.

## B3.1 Cara kerja dan pengaman

| Butir | Keterangan |
|---|---|
| Git | Hanya baca (`status`, `diff`, `log`, `rev-parse`, `branch --show-current`). Cabang `main`, HEAD `b79831d`. Perubahan pemilik dan hasil Batch 1–2 dan C-001 yang belum di-commit dibiarkan utuh; tidak ada commit. |
| Cadangan | `E:\Projek Skripsi\cadangan-audit\batch-3\`: `kondisi-awal.txt`, `asli\` (salinan tiap berkas yang diubah, diambil **sebelum** sentuhan pertama), `sha-awal.tsv`, `manifest.md`, `keluaran-awal\`, `keluaran-akhir\`. Bukti perubahan = `git diff --no-index` salinan asli vs berkas sekarang. |
| Basis data | Basis data kerja tidak disentuh (dari `.env` hanya `DB_CONNECTION=sqlite` dibaca). MySQL 8.4.3 **sementara** `127.0.0.1:3399` (biner Laragon 8.4.3, datadir di scratchpad; MySQL Laragon 3306 tidak disentuh). `DB_HOST`/`DB_PORT`/`DB_DATABASE` dicetak sebelum tiap run MySQL: suite `simpbi_audit_test`, keluaran beku `simpbi_b3_beku`, peramban `simpbi_b3_verif` — semuanya berbeda dari `.env`. |
| Isolasi (A4) | Semua tes, perintah artisan, dan server memakai storage salinan (`LARAVEL_STORAGE_PATH`). Bukti (proses aplikasi dengan env yang sama, `artisan tinker --execute` dan skrip penyiap): SQLite — `storage_path()` = `…\scratchpad\app-storage-sqlite`, `database.default` = `sqlite`, basis data `:memory:` (tes) / `…\scratchpad\verif-b3.sqlite` (peramban); MySQL — `…\scratchpad\app-storage`, `database.default` = `mysql`, `127.0.0.1:3399`, `select database()` = `simpbi_audit_test` (peramban: `simpbi_b3_verif`). Server `php -d variables_order=EGPCS -S` (dengan skrip router agar `/livewire-…/livewire.js` dilayani); sesi tampil di storage salinan, bukan repositori. **Berkas `storage/framework` repositori: 162 sebelum, 162 sesudah** seluruh verifikasi; `bootstrap/cache` tidak berubah (`packages.php` 3659 B, `services.php` 23857 B, keduanya bertanggal 2026-09-21 17:51). `optimize:clear` tidak dijalankan; tidak ada berkas repositori storage yang dihapus. |
| Skill | Dari `.claude/skills` hanya `browser-automation` yang relevan (verifikasi UI); tidak ada skill lain dipakai. |

## B3.2 Hasil tes

| Suite | Garis dasar (sebelum ubah) | Akhir |
|---|---|---|
| SQLite (memori) | OK — 539 tes, 3696 assertion | **OK — 576 tes, 3972 assertion** (539 + 37 tes baru) |
| MySQL 8.4.3 sementara | OK — 539 tes, 3696 assertion | **OK — 576 tes, 3972 assertion; 0 error, 0 gagal** |

**Uji mundur.** Ketujuh berkas kode dikembalikan sementara ke salinan asli, tiga kelas tes baru dijalankan: **28 dari 37 tes gagal** (26 gagal, 2 galat); 9 tetap lulus karena memang menguji perilaku yang tidak boleh berubah (pembuatan dan Sahkan normal, pengguna yang punya tim, kolom Ketua kosong, penyimpanan Ketua yang sah, impor). Berkas dipulihkan dan dicocokkan sha256 dengan salinan sebelum uji mundur (7 dari 7 identik).

**Satu tes lama disesuaikan** — `PenomoranBastTest` (Batch 2): simulasi bentrok nomor menyisipkan "pembuatan lain" di dalam transaksi percobaan sehingga baris itu ikut dibatalkan bersama percobaan yang bentrok, dan memakai aset yang sama sehingga kini diblokir A-011. Simulasi diganti: pesaing disisipkan di luar transaksi (seolah sudah terkomit) untuk aset lain, tepat setelah nomor dihitung. Asersi tes (`0001` lalu `0002`; tiga percobaan lalu pesan umum) tidak berubah dan perilaku kode Batch 2 tidak diubah.

## B3.3 Per ID

| ID | Status | Berkas diubah | Tes | Catatan |
|---|---|---|---|---|
| **A-011** | **Diperbaiki** | `MutasiAsetService.php` (+71/−1), `BastMutasiAsetForm.php` (+23), `CreateBastMutasiAset.php` (+26/−1), `BastMutasiAsetsTable.php` (+20/−1) | `MutasiAsetPenempatanTest` (18 tes) | Lihat B3.4. Penomoran dan ulang-bentrok Batch 2 tidak diubah dan tetap lulus. |
| **A-018** | **Diperbaiki** | `AsetTetapTimSaya.php` (+7/−1), `KondisiAsetTetapTim.php` (+6/−1) | `PenggunaTanpaTimTest` (3 tes × 2 peran = 6 kasus) | Pengguna tanpa tim: hasil kosong lewat `when(tim_id, …, whereRaw('1 = 0'))`; lihat B3.4. Hak akses tidak berubah. |
| **A-031** | **Diperbaiki** | `TimForm.php` (bagian Sinkronisasi) | `FormTimKerjaTest` (5 dari 13 tes) | `external_id` dan `synced_at` tampil tetapi `disabled()->dehydrated(false)`. Impor dan sinkronisasi tidak berubah. |
| **A-012** | **Diperbaiki** | `TimForm.php` (Select `ketua_tim_id`; total `TimForm.php` +40/−4) | `FormTimKerjaTest` (8 dari 13 tes) | Struktur data dan alur persetujuan tidak diubah; hanya pilihan dan validasi. Lihat B3.4. |

Tidak ada view Blade PDF, kelas ekspor, template, migration, tabel, field, dependensi, seeder, job CI, atau disk baru yang diubah/dibuat. `Tim.php`, `PermintaanBarangResource.php`, `NotifikasiService.php`, dan `ImporPengguna.php` hanya dibaca.

## B3.4 Rincian keputusan

**A-011 — Mutasi Aset.**
1. **Tim Asal**: `disabled()->dehydrated()` — tetap terisi otomatis dari penempatan aset pilihan dan ikut dikirim, tetapi tidak dapat diubah di form. Memilih aset tanpa penempatan menampilkan peringatan; aturan pada kolom Aset menampilkan kesalahan yang sama saat Buat (juga bagi aset yang masih punya BAST menunggu pengesahan).
2. **Server tidak percaya muatan form** (`MutasiAsetService::periksaPembuatan`, dipanggil di dalam transaksi bersama pembuatan BAST, dengan `AsetTetap::lockForUpdate()` — pola yang sudah dipakai `StokService`): aset tanpa penempatan → "Aset ini belum memiliki penempatan. Tetapkan penempatan terlebih dahulu lewat menu Aset Tetap."; `tim_asal_id` berbeda dari penempatan sekarang → "Penempatan aset telah berubah. Muat ulang formulir dan coba lagi."; BAST menunggu → "Aset ini masih memiliki BAST yang menunggu pengesahan (nomor BAST). Sahkan BAST tersebut terlebih dahulu." Penolakan tampil sebagai notifikasi "BAST tidak dapat dibuat" dan tidak ada BAST terbentuk. Hanya `\RuntimeException` persis yang dianggap penolakan bisnis (`StokService::pesanAturan`); galat teknis dilempar ulang.
3. **Blokir**: hanya BAST `menunggu_pengesahan` yang `tim_asal_id`-nya **masih sama** dengan penempatan aset sekarang. `menunggu_konfirmasi`, `selesai_administratif`, dan BAST usang tidak memblokir.
4. **Sahkan** (`sahkan()`): di dalam transaksi, baris aset dikunci dan `tim_penempatan_id` dibandingkan dengan `tim_asal_id` BAST **sebelum tulisan apa pun**. Bila berbeda → `\RuntimeException` "Penempatan aset sudah berubah sejak BAST dibuat, sehingga BAST ini tidak dapat disahkan. Buat BAST baru bila mutasi masih diperlukan." Tanpa perubahan status, perpindahan aset, dokumen, atau riwayat. Aksi Sahkan menampilkan penolakan itu sebagai notifikasi "BAST tidak dapat disahkan" (bukan galat mentah).
5. **Transaksi per percobaan.** Pembuatan dibungkus `DB::transaction` di dalam tiap percobaan pada loop ulang-bentrok Batch 2 (bukan satu transaksi di luar loop): menurut semantik REPEATABLE READ pada MySQL (dipertimbangkan, tidak diuji dengan koneksi paralel), satu transaksi di luar loop mempertahankan gambaran lama sehingga `nomorBaru()` sesudah bentrok tidak melihat baris pesaing yang baru terkomit dan ulang-bentrok Batch 2 tidak berfungsi.
6. **Keterbatasan konkurensi.** Kunci baris hanya berlaku pada MySQL; SQLite tidak mengenal kunci baris (penulisnya sudah diserialkan basis datanya). Pada MySQL, form Ubah Aset Tetap mengubah baris yang sama dan akan menunggu kunci itu. Perilaku dengan dua koneksi paralel sungguhan **tidak diuji** (tes hanya memeriksa logika).

**A-018.** `->where('tim_penempatan_id', auth()->user()?->tim_id)` → `->when(tim_id, fn ($q, $timId) => where('tim_penempatan_id', $timId), fn ($q) => whereRaw('1 = 0'))` pada halaman dan panel; keadaan kosong yang sudah ada dipakai ("Belum ada aset yang ditempatkan" / "Belum ada aset tetap"). **Pemindaian pola serupa:** hanya kedua tempat itu yang bocor, sebab kolom tim pada tempat lain NOT NULL (lihat F-001).

**A-031.** Kedua kolom tetap tampil; `disabled()->dehydrated(false)` sehingga muatan yang dimodifikasi diabaikan. Form Buat berfungsi (kolomnya nullable tanpa nilai bawaan; hasilnya NULL). Strip Status Sinkronisasi membaca `MAX(synced_at)` dan tidak lagi bergeser oleh form. Impor (`ImporTimKerja`) tetap mengisi `external_id` dan `synced_at` (diuji).

**A-012.** Pilihan Ketua Tim: pengguna `status_aktif`, `role = ketua_tim`, `tim_id` = tim yang sedang diubah; pada form Buat pilihan **kosong** (belum ada pengguna pada tim itu; Ketua ditetapkan lewat Ubah setelah peran dan tim diatur di menu Pengguna). Aturan server pada kolom (pesan sesuai permintaan) menolak muatan yang dimodifikasi: pengguna tim lain, nonaktif, berperan lain, tidak ada, atau — pada form Buat — nilai apa pun. Kolom kosong tetap boleh (tidak diwajibkan sebelumnya). **Data lama menyimpang** (mis. Ketua dari tim lain): form Ubah terbuka, nama pilihan lama tetap tampil (`getOptionLabelUsing`), dan Simpan menuntut perbaikan; tidak ada galat 500 (diuji pada Livewire dan di peramban). Sebuah teks bantuan menjelaskan isi pilihan.

## B3.5 Keluaran beku (aturan B)

Skenario dijalankan **sebelum** dan **sesudah** perubahan, pada SQLite (memori) **dan** MySQL sementara (skrip di luar repositori; waktu dibekukan; token dinormalkan): siklus penuh pengajuan → Ketua → verifikasi → Kasubbag → siapkan → konfirmasi (bukti permintaan asli + berfootnote), **dua BAST berurutan pada aset yang sama** (tim → tim lain → tim ketiga; masing-masing draf, disahkan, dikonfirmasi), permintaan kedaluwarsa, Kartu Kendali (PDF dan XLSX), ekspor Riwayat (PDF dan XLSX), dialog Rincian kedaluwarsa, baris riwayat kedaluwarsa, dan isi Riwayat Penempatan Aset. Isi PDF diekstrak dengan `pdftotext -layout`, sel XLSX dengan PhpSpreadsheet.

| Driver | Berkas dibandingkan | `diff -r` awal vs akhir |
|---|---|---|
| SQLite | 14 | **identik (kosong)** |
| MySQL 8.4.3 sementara | 14 | **identik (kosong)** |

Dua run tanpa perubahan kode identik pada tiap driver (skenario deterministik). Riwayat Penempatan pada kedua driver: `Statistik Sosial (penempatan_awal) → Sub Bagian Umum (mutasi BAST-2026-0001) → Statistik Distribusi (mutasi BAST-2026-0002, berlaku)`; penempatan akhir = tim ketiga.

Catatan: baris ekspor Riwayat dengan cap waktu **persis sama** tampil dalam urutan berbeda antara SQLite dan MySQL (tidak ada urutan tie-break); ini bukan akibat perubahan (sama pada awal dan akhir di tiap driver) — lihat F-004. Daftar berkas yang diubah tidak memuat view PDF, kelas ekspor, atau template.

## B3.6 Verifikasi peramban (headless, server sesuai A4, salinan SQLite **dan** MySQL sementara)

| Peran / langkah | Hasil (SQLite; MySQL setara kecuali dinyatakan) |
|---|---|
| Petugas Gudang — form Buat BAST, pilih aset bertim | Tim Kerja Asal terisi (Statistik Sosial) dan **disabled**. |
| Petugas Gudang — pilih aset tanpa penempatan | Notifikasi peringatan; Buat → kesalahan pada kolom Aset dengan pesan yang diminta; BAST tidak terbentuk. |
| Petugas Gudang — BAST pertama, lalu BAST kedua untuk aset yang sama | Pertama dibuat; kedua ditolak: "Aset ini masih memiliki BAST yang menunggu pengesahan (BAST-2026-0002). Sahkan BAST tersebut terlebih dahulu." |
| Kasubbag — Sahkan; Ketua Tim tujuan — Konfirmasi Penerimaan | "BAST disahkan" → "Penerimaan aset dikonfirmasi"; status `selesai_administratif`. |
| Rantai berurutan (hanya SQLite) | BAST baru untuk aset yang sama (asal terisi otomatis = tim tujuan sebelumnya) diperbolehkan setelah yang pertama disahkan; Riwayat Penempatan: Statistik Sosial → Sub Bagian Umum (BAST-2026-0002) → Statistik Distribusi (BAST-2026-0003, berlaku). |
| Penempatan berubah lewat Aset Tetap (Admin) lalu Kasubbag Sahkan | Notifikasi "BAST tidak dapat disahkan" dengan pesan yang diminta; status tetap `menunggu_pengesahan`, penempatan dan riwayat tidak berubah (diperiksa di basis data). Pada SQLite maupun MySQL. BAST baru untuk aset itu (asal terisi = penempatan sekarang) diperbolehkan walau BAST usang masih menunggu (SQLite). |
| Ketua Tim dan Tim tanpa tim | "Aset Tetap Tim Saya" kosong (0 baris, keadaan kosong bawaan); panel Kondisi Aset kosong; kontrol (tim01) tetap melihat 2 aset. |
| Admin — Ubah Tim | Pilihan Ketua Tim (Statistik Sosial) hanya "Wanda Pribadi"; Buat Tim: pilihan kosong; ID Eksternal dan Waktu Sinkronisasi **disabled**. Tim berketua menyimpang: form terbuka menampilkan nama pilihan lama; Simpan → pesan A-012; tidak ada galat 500. |

Run yang dijadikan bukti tidak menghasilkan `console.error` maupun permintaan gagal yang bermakna. Kesalahan konsol dan 404/500 hanya muncul selama gangguan disk `C:` (lihat B3.11); langkah yang terdampak diulang setelah ruang dibebaskan dan hasil di atas berasal dari pengulangan itu.

## B3.7 Hasil pemeriksaan C.6 (tidak mengubah apa pun)

| Butir | Hasil |
|---|---|
| (a) Tim Tujuan = Tim Asal? | **Tidak dapat lewat form:** aturan `different('tim_asal_id')` menolak (diuji sementara: kesalahan pada Tim Tujuan, BAST = 0). Layanan tidak memeriksa ulang (jalur satu-satunya adalah form). Tidak ada temuan F-. |
| (b) Satu BAST memuat banyak aset? | **Tidak.** `bast_mutasi_aset.aset_id` satu kolom; aturan berlaku per BAST = per aset. |
| (c) Form Buat dan Impor Aset Tetap mengisi penempatan? | **Opsional, bukan wajib.** Form Buat: Select `tim_penempatan_id` tidak `required`; bila diisi, `catatPenempatanAwal()` menulis riwayat awal. Impor: kolom "Tim Kerja Penempatan" opsional dan hanya dipakai untuk aset **baru** (juga memanggil `catatPenempatanAwal()`); pada aset yang sudah tercatat penempatan tidak diubah. Jadi aset tanpa penempatan dapat ada, sehingga penolakan di BAST diperlukan (kueri C.8c di B3.8 mendaftarkannya). |
| (d) Siapa dapat mengubah penempatan di luar BAST? | Hanya form **Ubah Aset Tetap** (`AsetTetapResource::canAccess`: **Admin dan Kasubbag**). Impor tidak mengubah aset lama; tidak ada aksi massal atau perintah artisan yang menulis `tim_penempatan_id` (selain seeder). Perubahan itu **tidak menulis Riwayat Penempatan** (F-002; ada tes lama yang mendokumentasikannya) — inilah jalur yang melahirkan BAST usang. |

## B3.8 Kueri SELECT untuk pemilik (hanya baca; **jangan dijalankan pada basis data kerja oleh saya**)

Diuji pada salinan basis data SQLite dan MySQL sementara; jalan di keduanya. Data lama **tidak diubah** oleh perbaikan ini.

```sql
-- C.8: BAST menunggu_pengesahan yang tim asalnya tidak sama dengan penempatan aset sekarang (BAST usang;
--      Sahkan akan ditolak, tetapi BAST ini tidak memblokir BAST baru)
SELECT b.id, b.nomor_bast, a.nup, b.tim_asal_id, ta.nama_tim AS tim_asal_di_bast,
       a.tim_penempatan_id, tp.nama_tim AS penempatan_sekarang
FROM bast_mutasi_aset b
JOIN aset_tetap a ON a.id = b.aset_id
LEFT JOIN tim ta ON ta.id = b.tim_asal_id
LEFT JOIN tim tp ON tp.id = a.tim_penempatan_id
WHERE b.status = 'menunggu_pengesahan'
  AND (a.tim_penempatan_id IS NULL OR a.tim_penempatan_id <> b.tim_asal_id)
ORDER BY b.id;

-- C.8b: aset dengan lebih dari satu BAST menunggu_pengesahan (BAST ganda lama; yang pertama disahkan
--       membuat yang lain usang)
SELECT aset_id, COUNT(*) AS jumlah, GROUP_CONCAT(nomor_bast) AS nomor
FROM bast_mutasi_aset WHERE status = 'menunggu_pengesahan'
GROUP BY aset_id HAVING COUNT(*) > 1;

-- C.8c: aset aktif tanpa penempatan (tidak dapat dimutasi sebelum penempatannya ditetapkan)
SELECT id, nup, nama_aset FROM aset_tetap WHERE status_aktif = 1 AND tim_penempatan_id IS NULL;

-- J.6 (A-012): tim yang ketua_tim_id-nya tidak memenuhi aturan (tidak ada/nonaktif/bukan ketua_tim/bukan anggota tim itu)
SELECT t.id, t.nama_tim, t.ketua_tim_id, u.name AS nama_ketua, u.role, u.status_aktif, u.tim_id AS tim_pengguna
FROM tim t
LEFT JOIN users u ON u.id = t.ketua_tim_id
WHERE t.ketua_tim_id IS NOT NULL
  AND (u.id IS NULL OR u.status_aktif <> 1 OR u.role <> 'ketua_tim' OR u.tim_id IS NULL OR u.tim_id <> t.id)
ORDER BY t.id;
```

## B3.9 Perubahan perilaku

1. Form Buat BAST: Tim Kerja Asal tidak dapat diubah; aset tanpa penempatan dan aset yang masih punya BAST menunggu pengesahan ditolak (form dan server).
2. Sahkan menolak BAST yang penempatan asalnya sudah berubah (notifikasi); BAST usang itu tetap `menunggu_pengesahan` dan tombol Sahkan-nya tetap tampil (lihat F-005).
3. Pengguna Tim/Ketua Tim tanpa tim melihat "Aset Tetap Tim Saya" dan panel kondisinya kosong.
4. Form Tim Kerja: ID Eksternal dan Waktu Sinkronisasi hanya-baca; pilihan Ketua Tim dibatasi (kosong pada form Buat) dan divalidasi di server.
5. Tidak berubah: status, notifikasi, isi dan tata letak PDF, penomoran BAST, Riwayat Penempatan, Konfirmasi Penerimaan, hak akses, logika persetujuan, tanda tangan, dan impor.

## B3.10 ID baru (tidak diperbaiki)

| ID | Kategori | Keparahan | Temuan | Bukti |
|---|---|---|---|---|
| **F-001** | Ketahanan | Rendah (laten) | Pola `where(kolom tim, tim_id pengguna)` tanpa penjaga pada enam tempat lain dan `NotifikasiService::berperan` `->when($timId, …)` (bila `$timId` kosong filter tim dilewati dan seluruh pengguna berperan itu diberi notifikasi). Hari ini aman: kolomnya NOT NULL (`tim_pemohon_id`, `tim_asal_id`, `tim_tujuan_id`) sehingga hasilnya kosong dan setiap permintaan punya tim. Rapuh bila kolom dilonggarkan. | `RingkasanKetua.php:32-34`, `RingkasanTim.php:31-33`, `TrenKonsumsiTim.php:99`, `PermintaanBarangResource.php:73`, `PerluTindakan.php:72`, `BastMutasiAsetResource.php:60-61`, `NotifikasiService.php:165`. |
| **F-002** | Konsistensi data | Sedang | Form **Ubah Aset Tetap** dapat mengganti `tim_penempatan_id` (Admin/Kasubbag) tanpa menutup atau menulis Riwayat Penempatan; penempatan dan riwayat menjadi berbeda. Pesan impor sendiri menyatakan perpindahan "hanya sah melalui BAST mutasi". Jalur ini pula yang membuat BAST usang (A-011). | Peramban (SQLite): aset NUP 3.10.01.00004 dipindah ke Statistik Distribusi lewat form; sesudahnya `tim_penempatan_id = 5` sedangkan Riwayat Penempatan masih `Sub Bagian Umum — penempatan_awal — berlaku`. Tes lama `RiwayatPenempatanAsetTest::test_mengganti_tim_lewat_penyuntingan_tidak_dicatat_sebagai_mutasi`. |
| **F-003** | Konsistensi | Rendah | Form Aset Tetap masih memuat `external_id` dan `synced_at` yang dapat disunting dan menggeser strip Status Sinkronisasi Aset Tetap — masalah yang sama dengan A-031 untuk Tim. | `AsetTetapForm.php:74-78`; `StatusSinkronisasiAsetTetap.php:30`. |
| **F-004** | Ketahanan | Rendah | Ekspor/tampilan Riwayat tidak memiliki urutan tie-break: baris dengan cap waktu persis sama tampil dalam urutan berbeda antara SQLite dan MySQL. Pada data nyata (detik berbeda) jarang muncul. | Perbandingan `keluaran-awal\sqlite` vs `\mysql` (`riwayat-semua-pdf.txt`, `riwayat-semua-xlsx.json`); di tiap driver hasilnya konsisten antar-run. |
| **F-005** | Alur | Rendah (konsekuensi keputusan pemilik) | BAST usang tetap `menunggu_pengesahan` selamanya: tombol Sahkan tetap tampil bagi Kasubbag dan selalu ditolak; alur tidak punya pembatalan. Kueri C.8 mendaftarkannya. | Peramban: BAST-2026-0005 (SQLite) / BAST-2026-0003 (MySQL). |
| **F-006** | Ketahanan | Rendah | `MutasiAsetService::sahkan()` tidak memeriksa status `menunggu_pengesahan` (dijaga hanya oleh `->visible()` pada aksi), dan pembuatan BAST tidak memeriksa `status_aktif` aset di server (hanya opsi form yang menyaring); muatan yang dimodifikasi dapat memutasi aset nonaktif. | `MutasiAsetService.php` (`sahkan`, `periksaPembuatan`); `BastMutasiAsetForm.php` (opsi `where('status_aktif', true)`). |

## B3.11 Tidak dapat diverifikasi

- **Konkurensi sungguhan** (dua koneksi paralel yang membuat BAST untuk aset yang sama, atau Sahkan bersamaan dengan Ubah Aset Tetap): kunci baris diterapkan tetapi tidak diuji dengan koneksi paralel; SQLite tidak mengenal kunci baris.
- MariaDB (XAMPP) tidak dijalankan; MySQL 8.4.3 dan SQLite saja.
- Isi PDF BAST yang dibentuk lewat peramban (Sahkan/Konfirmasi) tidak dibandingkan; yang dibandingkan adalah skenario beku yang menjalankan jalur yang sama lewat Livewire (14 berkas identik di dua driver). Di peramban Sahkan dan Konfirmasi berhasil tanpa galat.
- Rantai tiga tim (Statistik Sosial → Sub Bagian Umum → Statistik Distribusi) diverifikasi di peramban hanya pada SQLite; pada MySQL peramban menjalankan satu langkah mutasi dan penolakan BAST usang, sedangkan rantai penuh dua BAST diuji pada tes dan skenario beku di kedua driver.
- **Gangguan lingkungan selama verifikasi:** disk `C:` mencapai 0 GB kosong (sisa 0,39 GB setelah saya menghapus profil peramban sementara milik batch ini; scratchpad hanya ±250 MB, sehingga penyebab utamanya bukan berkas saya). Akibatnya beberapa permintaan peramban mengembalikan 500/ENOSPC dan satu proses peramban macet; tautan `public-c1` di scratchpad juga hilang dan docroot dibuat ulang (`public-b3`, `public/` repositori diperiksa utuh, `git status` bersih untuk `public/`). Seluruh langkah peramban yang terdampak diulang setelah ruang dibebaskan; satu pembacaan awal di MySQL (`disabled=false` pada kolom sinkronisasi) tidak dapat direproduksi pada semua pembacaan berikutnya (keempat halaman pada dua server, enam pembacaan berurutan sejak render pertama, dan pengulangan skrip asal — semuanya `true`). Sebaiknya pemilik memeriksa ruang disk `C:`.

---

# Verifikasi ulang setelah disk penuh (Batch 3)

Tanggal: 22 September 2026. Disk `C:` sempat penuh (0 GB) saat verifikasi Batch 3; bagian ini mengulang pemeriksaan setelah ruang dibebaskan. **Tidak ada kode, tes, atau dokumen yang diubah** selain penambahan bagian ini; tidak ada yang dihapus. Git hanya-baca (cabang `main`, HEAD `b79831d`). Basis data kerja tidak dibuka. MySQL Laragon (3306) tidak disentuh. `optimize:clear` tidak dijalankan.

## V.1 Ruang disk

| Drive | Sebelum verifikasi | Sesudah verifikasi |
|---|---|---|
| `C:` | 7,39 GB kosong (ambang berhenti 3 GB terpenuhi) | 6,59 GB kosong |
| `E:` | 50,67 GB kosong | 50,67 GB kosong |

## V.2 Berkas dan folder sementara (hanya didaftar; pemilik yang memutuskan)

Ukuran diukur tanpa mengikuti junction. Tidak ada yang dihapus.

| Butir | Lokasi | Ukuran |
|---|---|---|
| Data MySQL sementara **lama** (Batch 1–3) | `scratchpad/mysql-data` | 218,1 MB |
| Data MySQL sementara **baru** (verifikasi ulang ini, instans sudah dimatikan) | `scratchpad/mysql-data-v3` | 198 MB |
| Salinan storage (13 folder `app-storage*`) | `scratchpad/app-storage*` | ±44,7 MB (terbesar 5,9 MB) |
| Salinan basis data SQLite (5 berkas: `audit`, `verif-b1`, `verif-b2`, `verif-c1`, `verif-b3`) | `scratchpad/*.sqlite` | ±1,5 MB |
| Docroot server verifikasi | `scratchpad/public-b3` (5 junction ke `public/` repositori: `build`, `css`, `fonts`, `images`, `js`), `scratchpad/public-c1` | ±0 MB (hanya `index.php`) |
| Log, keluaran tes, tangkapan layar, skrip | `scratchpad/` (sisanya) | ±5 MB |
| **Total scratchpad sesi ini** | `C:/Users/dell/AppData/Local/Temp/claude/E--Projek-Skripsi-simpbi/14519757-…/scratchpad` | **467,6 MB** |
| Profil peramban buatan batch (lihat V.7) | `~/.codegpt/ab-sessions/` `b3g` 96,1 · `b3k` 94,9 · `b3t` 89,0 · `b3a` 81,1 · `m1` 58,4 (+ `s6`, folder kosong) | **419,5 MB** |
| Profil peramban lain (bukan dari batch audit menurut catatan saya; asal-usulnya tidak dapat saya pastikan) | `my` 81,3 · `simpbi-qa2` 61,4 · `simpbi-visual` 61,3 · `wz` 48,8 · `pengaturan-qa` 46,8 · `wnip` 38,2 · `ks2` 30,9 · `v2` 20,6 · `ks` 19,2 | 408,5 MB |
| Folder Temp sesi lain | `Temp/claude/E--Projek-Skripsi-simpbi/<sesi lain>` (8 folder) | 0–5,1 MB tiap folder, total ±16 MB |
| Cadangan audit (bukan sampah; jangan dihapus sebelum diputuskan) | `E:/Projek Skripsi/cadangan-audit/` `batch-1` 0,1 · `batch-2` 0,21 · `batch-2b-c001` 0,3 · `batch-3` 0,27 · `storage-public-sebelum-pindah` 1,29 | ±2,2 MB |

Catatan: junction di `public-b3` harus dilepas dengan `cmd /c rmdir` (bukan `Remove-Item -Recurse`, yang mengikuti junction ke `public/` repositori).

## V.3 Integritas berkas

| Pemeriksaan | Hasil |
|---|---|
| sha256 terhadap `manifest.md` Batch 2, C-001, dan Batch 3 (berkas unik; dibandingkan dengan sha256 akhir manifest **terbaru** yang memuatnya) | **42 dari 42 cocok**; 0 berbeda, 0 hilang, 0 berukuran nol |
| Rantai sha256 akhir → awal antarbatch untuk berkas yang diubah lebih dari satu batch | 0 ketidaksesuaian |
| `php -l` pada 40 berkas PHP yang diubah Batch 1, 2, C-001, dan 3 | **40 dari 40 lulus** |
| Batch 1 | **Tidak memiliki `manifest.md`** (hanya folder salinan awal), sehingga sha256 akhirnya tidak dapat dicocokkan. 10 berkas ada, tidak berukuran nol, lulus sintaks; selisih terhadap salinan sesuai laporan: `bootstrap/app.php` +2/−1, `NomorBonPengeluaranTest.php` +44, `StokService.php` +49/−3 (= Batch 1 +5/−1 ditambah Batch 2 +44/−2). Dua berkas (`bootstrap/app.php`, `NomorBonPengeluaranTest.php`) tidak tercantum di manifest lain, jadi keadaan akhirnya hanya dapat dinilai dari selisih itu. |

Berkas laporan ini sebelum bagian ini ditambahkan memiliki sha256 `cf973a7e…6c3f`, sama dengan sha256 akhir pada manifest Batch 3 (`af9e2400…4f05` adalah sha256 awal Batch 3); sha256 barunya berbeda **hanya** karena bagian ini.

**Dua perubahan di luar manifest mana pun (dipindai: berkas repositori yang lebih baru dari mulainya Batch 2, di luar `vendor`, `node_modules`, `.git`, `storage`, `bootstrap/cache`):**

| Berkas | Temuan | Status |
|---|---|---|
| `database/migrations/2026_08_26_000010_create_riwayat_persetujuan_table.php` | Terdaftar ` M` di git. Diubah **22 Sep 2026 00:23:05**, lebih baru dari suntingan terakhir saya (00:01) dan tidak dicatat oleh batch mana pun. Selisih hanya spasi: baris baru sesudah `{` pada `up()` hilang, sehingga menjadi `{        Schema::create('riwayat_persetujuan', …` (1 baris +, 2 baris −). `php -l` lulus. Menurut catatan saya tidak ada batch yang mengubah migration; penyebabnya tidak diketahui. | **Dibiarkan apa adanya.** Perlu keputusan pemilik (kembalikan atau terima). |
| `database/database.sqlite` (basis data kerja, 331.776 B) | Waktu ubah **21 Sep 2026 21:44:52**, antara mulainya Batch 2 (19:11) dan C-001 (21:49); tidak berubah selama verifikasi ulang ini. Berkas diabaikan git (`*.sqlite*`) dan tidak dicatat di kondisi awal batch mana pun, sehingga saya **tidak dapat membuktikan** bahwa pernyataan "basis data kerja tidak disentuh" pada laporan Batch 2 benar, maupun bahwa yang menulis adalah aplikasi yang dipakai pemilik. Isinya tidak saya buka. | **Tidak diperiksa lebih jauh.** Pemilik perlu memastikan apakah perubahan itu dari pemakaian aplikasi sendiri. |

`.phpunit.result.cache` (diabaikan git) diperbarui oleh PHPUnit seperti biasa.

## V.4 Test suite ulang (576 tes target)

| Driver | Sebelum run dicetak | Hasil |
|---|---|---|
| SQLite (`phpunit.xml`: `sqlite` + `:memory:`) | `DB_HOST`/`DB_PORT` tidak diset; `DB_DATABASE=:memory:`; storage salinan `app-storage-sqlite` | **OK — 576 tes, 3972 assertion** (4 mnt 45 dtk) |
| MySQL 8.4.3 sementara (instans **baru**, datadir `mysql-data-v3`) | `DB_HOST=127.0.0.1`, `DB_PORT=3399`, `DB_DATABASE=simpbi_audit_test` (`select database()` = sama); storage salinan `app-storage` | **OK — 576 tes, 3972 assertion** (4 mnt 50 dtk) |

`.env` hanya memuat `DB_CONNECTION=sqlite` (tanpa host, port, atau nama basis data), sehingga semua nilai di atas berbeda darinya. Angkanya sama dengan hasil Batch 3.

## V.5 Ulang di peramban headless (MySQL sementara, basis data `simpbi_v3_verif` yang baru dimigrasi dan di-seed; `127.0.0.1:3399`, storage `app-storage-v3`, server `php -d variables_order=EGPCS -S 127.0.0.1:8126`)

**(a) Form Ubah Tim Kerja, bagian Sinkronisasi** (Tim 2 Statistik Sosial; nilai awal di basis data sementara diisi `external_id=EXT-ASLI-002`, `synced_at=2026-03-01 08:00:00` agar uji bermakna):
- Kedua kolom tampil dengan `disabled=true` dan menampilkan nilai basis data.
- Muatan diubah lewat Livewire (`external_id=HACK-XYZ`, `synced_at=2030-01-01 00:00:00`; terbukti masuk ke status komponen di klien) bersama perubahan sah (`nama_tim` menjadi "Statistik Sosial UJI"), lalu Simpan → notifikasi "Data berhasil disimpan", tanpa galat.
- Kueri basis data sesudahnya: `nama_tim` berubah dan `updated_at` maju (Simpan benar berjalan), tetapi `external_id` tetap `EXT-ASLI-002` dan `synced_at` tetap `2026-03-01 08:00:00`. **Kolom tidak ikut tersimpan.** (Form yang belum dimuat ulang masih menampilkan nilai klien `HACK-XYZ`; sesudah dimuat ulang kembali menampilkan nilai basis data.) `nama_tim` sudah dikembalikan ke "Statistik Sosial".

**(b) Rantai BAST tiga tim** (aset NUP 3.10.01.00001, Laptop Lenovo ThinkPad E14, awalnya Statistik Sosial):
1. Petugas Gudang membuat BAST: Tim Asal terisi "Statistik Sosial" dan `disabled`, tujuan Sub Bagian Umum → BAST-2026-0002 dibuat. Kasubbag Sahkan ("BAST disahkan"); Ketua Sub Bagian Umum Konfirmasi Penerimaan ("Penerimaan aset dikonfirmasi") → `selesai_administratif`.
2. BAST kedua: Tim Asal terisi otomatis "Sub Bagian Umum" (`disabled`), tujuan Statistik Distribusi → BAST-2026-0003; Kasubbag Sahkan ("BAST disahkan"); Ketua Statistik Distribusi Konfirmasi Penerimaan → baris tabel `selesai_administratif` (notifikasinya tidak sempat terbaca oleh skrip; status dibuktikan lewat tabel dan basis data).
3. Dialog Riwayat Penempatan dan basis data sama: **Statistik Sosial (penempatan awal, 22-03-2026 → 22-09-2026) → Sub Bagian Umum (mutasi BAST-2026-0002) → Statistik Distribusi (mutasi BAST-2026-0003, sedang berlaku)**; `aset_tetap.tim_penempatan_id` = Statistik Distribusi.

Run yang dijadikan bukti tidak menghasilkan `console.error`; satu-satunya permintaan gagal adalah pembatalan (`ERR_ABORTED`) pada `chart.js` dan pembaruan Livewire yang tertimpa navigasi.

## V.6 Keadaan repositori dan lingkungan

- `storage/framework` repositori: 162 berkas sebelum dan sesudah; `bootstrap/cache` tidak berubah (`packages.php` 3659 B, `services.php` 23857 B).
- Semua tes dan server memakai storage salinan (`app-storage-sqlite`, `app-storage`, `app-storage-v3`). Server `php -S` memakai `-d variables_order=EGPCS` dan skrip router.
- Sesudah selesai, server 8126 dan instans MySQL sementara (3399) dimatikan; kedua port bebas. Datanya dibiarkan (V.2).

## V.7 Koreksi atas laporan Batch 3 dan kendala verifikasi

- **Koreksi (B3.11):** saya menulis bahwa profil peramban sementara milik batch telah dihapus. Kenyataannya hanya sebagian: `b2`, `m2`–`m9`, dan isi `s6` (tinggal folder kosong) terhapus (±0,39 GB), sedangkan **lima profil (`b3g`, `b3k`, `b3t`, `b3a`, `m1`; 419,5 MB) masih ada** dengan ukuran hampir sama seperti sebelum penghapusan (kemungkinan berkasnya terkunci proses peramban yang macet saat itu). Tidak ada proses headless yang kini mengunci profil itu.
- Pemuatan halaman login pertama pada run peramban pertama sekali gagal (kolom email tidak muncul dalam 20 detik) dan pulih pada pengulangan; server dan basis data sehat.
- Skrip verifikasi saya perlu diperbaiki dua kali (bukan aplikasi): `page.evaluate` patchright berjalan di dunia JS terisolasi sehingga `window.Livewire` tidak terlihat (dipakai dunia utama), dan pemilihan aset pada form BAST perlu menunggu pencarian yang ditunda ±2,5 detik.
- Basis data `simpbi_b3_beku` kosong ikut terbuat pada instans baru (tidak dipakai).

---

# Batch 4 — Pengaturan, ganti sandi, manajemen akun, konfirmasi akun Tim

Tanggal: 22 September 2026. ID yang dikerjakan: **A-007**, **A-008**, **C-004**, **A-010**, **A-015**, dan **A-015b** (bagian H). Temuan lain serta ID C-, D-, E-, F- lainnya tidak disentuh; `laporan-audit.md` tidak diubah.

## B4.1 Cara kerja dan pengaman

| Butir | Keterangan |
|---|---|
| Git | Hanya baca (`status`, `diff`, `log`, `rev-parse`, `branch --show-current`; `git show :berkas` dan `git diff --no-index` untuk membandingkan). Cabang `main`, HEAD `b79831d`. Perubahan pemilik dan hasil batch sebelumnya dibiarkan utuh; tidak ada commit. |
| Cadangan | `E:\Projek Skripsi\cadangan-audit\batch-4\`: `kondisi-awal.txt`, `asli\` (salinan sebelum sentuhan pertama), `sha-awal.tsv`, `manifest.md`, `diff\`, `keluaran-awal\`, `keluaran-akhir\`. Satu berkas (`tests/Feature/TandaTanganPenggunaTest.php`) belum ikut tersalin sebelum diubah; salinan aslinya dibentuk ulang dari kebalikan persis suntingan saya dan **terbukti identik** dengan versi di indeks git (sha256 sama, `5f787072…af0a`), sebab berkas itu berstatus `M ` (tanpa perubahan belum-staged) pada `kondisi-awal.txt`. |
| Basis data kerja | Tidak disentuh. Dari `.env` hanya `DB_CONNECTION=sqlite` dibaca. `database/database.sqlite` tidak dibuka, dibaca, atau disalin; hanya metadatanya: **sebelum** `2026-09-21 21:44:52.3980772`, 331.776 B; **sesudah** `2026-09-21 21:44:52.3980772`, 331.776 B (**tidak berubah**). |
| MySQL sementara | MySQL 8.4.3, biner `E:\laragon\laragon\bin\mysql\mysql-8.4.3-winx64\bin\`, **datadir baru** `scratchpad/mysql-data-b4` (di luar repositori dan folder Laragon), `127.0.0.1:3399`. Layanan MySQL Laragon (3306, milik pemilik, sedang berjalan) tidak dijalankan ulang, dihentikan, atau diubah; datadir-nya tidak dibaca. Sebelum tiap run dicetak `DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=…`: suite `simpbi_audit_test`, keluaran beku `simpbi_b4_beku`, peramban `simpbi_b4_verif`; `.env` tidak memuat host, port, maupun nama basis data. |
| Isolasi (A4) | Semua tes, perintah artisan, dan server memakai storage salinan (`LARAVEL_STORAGE_PATH`). Bukti (proses aplikasi dengan env yang sama): SQLite — `storage_path()` = `…/scratchpad/app-storage-sqlite`, `database.default` = `sqlite`, basis data `:memory:` (dari `phpunit.xml`); MySQL — `…/scratchpad/app-storage`, `default` = `mysql`, `127.0.0.1:3399`, `select database()` = `simpbi_audit_test`; peramban — `…/scratchpad/app-storage-b4v`, `simpbi_b4_verif` (dimigrasi dan di-seed dari migration dan seeder existing, **bukan** salinan basis data kerja). Server `php -d variables_order=EGPCS -S 127.0.0.1:8126` dengan skrip router. **`storage/framework` repositori: 163 berkas sebelum dan 163 sesudah** (selisih 1 terhadap batch sebelumnya, 162 → 163, berasal dari berkas sesi dan cache milik server pemilik pada 02:49–03:16, sebelum batch ini dimulai). `bootstrap/cache` tidak berubah (`packages.php` 3659 B, `services.php` 23857 B). `optimize:clear` dan `pint` tidak dijalankan. |
| Disk | `C:` 6,57 GB kosong sebelum, 6,14 GB sesudah (ambang berhenti 3 GB terpenuhi); `E:` 50,67 GB. |
| Skill | Tidak ada skill di `.claude/skills` yang relevan untuk perbaikan ini (sebagian besar skill desain dan animasi). Untuk verifikasi UI dipakai skill **`browser-automation`** (di luar repositori). |
| Format | Akhiran baris tiap berkas dipertahankan (CRLF pada `EditUser.php` dan `PersetujuanTidakMelebihiDimintaTest.php`, LF pada lainnya). |

## B4.2 Hasil tes

| Suite | Garis dasar (sebelum ubah) | Akhir |
|---|---|---|
| SQLite (memori) | OK — 576 tes, 3972 assertion (3 mnt 25 dtk) | **OK — 617 tes, 4439 assertion** (3 mnt 45 dtk) |
| MySQL 8.4.3 sementara | OK — 576 tes, 3972 assertion (3 mnt 41 dtk) | **OK — 617 tes, 4439 assertion** (4 mnt 03 dtk); 0 error, 0 gagal |

**41 tes baru** dalam lima kelas: `PengaturanTombolSimpanTest` (7), `GantiSandiSesiTest` (9), `AdminTidakMengunciDiriTest` (10), `NamaNipTerkunciPengaturanTest` (6), `KonfirmasiAkunTimTest` (9).

**Uji mundur.** Enam berkas kode dikembalikan sementara ke salinan asli dan 40 tes baru dijalankan: **25 gagal atau galat**; 15 tetap lulus karena memang menguji perilaku yang tidak boleh berubah (Ketua Tim, Petugas Gudang, dan Kasubbag tetap mengetik NIP; form Buat dan Ubah pengguna lain; kolom kata sandi kosong; dan sebagainya). Tes ke-41, khusus C-004 (tiga rute unduhan dengan hash sandi usang), lulus dengan `routes/web.php` baru dan **gagal** dengan `routes/web.php` asli. Semua berkas dipulihkan dan dicocokkan sha256 dengan salinan sebelum uji mundur (6 dari 6 identik).

**Lima tes lama disesuaikan** — hanya karena perilaku yang memang diubah batch ini; asersinya yang lain tidak berubah:
- A-015: `PengaturanTest::test_kata_sandi_baru_kosong_tidak_mengubah_apa_apa` dan `TandaTanganPenggunaTest::test_menyimpan_tanpa_menggambar_tidak_menghapus_yang_lama` memakai perubahan nama sebagai "perubahan lain yang sah"; nama kini terkunci, sehingga dipakai perubahan nomor WhatsApp.
- A-015b: `TandaTanganTahapanTest` (3 tes) dan `PersetujuanTidakMelebihiDimintaTest` (2 tes) mengetik NIP pada akun peran Tim; kini mengetik nama tim.

## B4.3 Per ID

| ID | Status | Berkas diubah | Tes | Catatan |
|---|---|---|---|---|
| **A-007** | **Diperbaiki** | `Pengaturan.php` (`simpanAction()`; metode baru `perluKonfirmasiPeragaan()`; properti `$waAlihkanAwal` dihapus) | `PengaturanTombolSimpanTest` (7 tes, lewat `mountAction`) | Lihat B4.5. |
| **A-008** | **Diperbaiki** | `GantiKataSandi.php` (aturan, hash sesi, notifikasi), `Pengaturan.php` (aturan, hash sesi) | `GantiSandiSesiTest` (9 tes) | Penyebab terbukti: B4.4. |
| **C-004** | **Diperbaiki** | `routes/web.php` (`$gerbangAkun` ditambah `AuthenticateSession` milik Filament) | `GantiSandiSesiTest` (termasuk tes khusus tiga rute) | `AdminPanelProvider.php` dan `bootstrap/app.php` hanya dibaca. |
| **A-010** | **Diperbaiki** | `UserForm.php`, `EditUser.php` | `AdminTidakMengunciDiriTest` (10 tes) | Lihat B4.5. Pemeriksaan F.4: B4.8. |
| **A-015** | **Diperbaiki** | `Pengaturan.php` (bagian Akun Saya dan `simpan()`) | `NamaNipTerkunciPengaturanTest` (6 tes) | Syarat awal (1)–(3) terpenuhi (B4.8). Nama Pemohon di Katalog tidak berubah (diuji). |
| **A-015b** | **Diperbaiki** | `PermintaanBarangResource.php` (`bidangKonfirmasiNip()`, `cocokIdentitas()`, metode baru `ratakanTeks()`) | `KonfirmasiAkunTimTest` (9 tes) | Investigasi H.1–H.3 tidak menemukan halangan (B4.8). |

Tidak ada view Blade PDF, kelas ekspor, template, migration, tabel, field, dependensi, seeder, job CI, atau disk baru yang diubah atau dibuat. Hak akses tidak berubah.

## B4.4 Penyebab A-008 (terbukti sebelum diperbaiki)

Kode `GantiKataSandi::simpan()` dan `Pengaturan::simpan()` mengganti kata sandi tetapi tidak memperbarui `password_hash_web` di sesi, dan itulah yang membuat pemiliknya terlempar. Rangkaian bukti pada kode asli (peramban headless, MySQL sementara, berkas sesi dibaca dari storage salinan):

1. Kontrol: sesudah masuk, `password_hash_web` di berkas sesi **cocok** dengan hash sandi akun (bentuk HMAC yang dipakai Laravel 12).
2. Sesudah `POST /livewire…/update` 200 yang menyimpan sandi baru (navigasi ke `/admin` sengaja ditahan agar keadaan sesi dapat dibaca), kolom `users.password` sudah berganti tetapi `password_hash_web` di sesi **masih hash lama** (tidak cocok).
3. Tanpa penahanan, rantai permintaan: `POST 200 /livewire-…/update` → `GET 302 /admin` → `GET 200 /admin/login`.
4. Mekanisme (vendor): `Illuminate\Session\Middleware\AuthenticateSession` menyimpan hash di sesi lewat `tap()` sesudah `$next`; pada permintaan Livewire, `AuthenticateSession` (didaftarkan Filament pada `FilamentServiceProvider`) hanyalah middleware **persisten** yang dijalankan `Livewire\Mechanisms\PersistentMiddleware` lewat `Utils::applyMiddleware()` dalam pipeline yang berakhir dengan respons kosong, **sebelum** komponen dihidrasi dan dijalankan. Hash yang disimpan `tap()` karena itu adalah hash sebelum komponen mengganti sandi; tidak ada yang memperbaruinya sesudahnya. Permintaan penuh berikutnya (`GET /admin`) membandingkan hash sesi (lama) dengan `users.password` (baru), tidak cocok, lalu `logout`.
5. Halaman profil bawaan Filament menangani hal yang sama secara eksplisit (`vendor/filament/filament/src/Auth/Pages/EditProfile.php:217-221`: `session()->put('password_hash_' . Filament::getAuthGuard(), …)` sesudah menyimpan); perbaikan mengikuti pola itu.

Sesudah perbaikan, eksperimen yang sama (navigasi ditahan): `password_hash_web` di sesi baru **cocok** dengan hash sandi sekarang. Sesi lama akun yang sama tetap tidak cocok, yaitu tidak berlaku.

## B4.5 Rincian keputusan

**A-007.** Ada dua penyebab. (1) Teks dialog (`modalHeading`, `modalDescription`) yang terpasang membuat Filament membuka dialog walau `requiresConfirmation()` bernilai `false` (`CanOpenModal::shouldOpenModal()`); ditambahkan `->modal(fn () => …)` dengan kondisi yang sama. (2) Untuk Admin, pembanding "nomor awal" disimpan pada properti `protected $waAlihkanAwal`, padahal Livewire tidak mempertahankan properti `protected` antar-permintaan: pada permintaan tombol nilainya selalu kosong, sehingga dialog terbuka setiap kali kolom peragaan terisi walau tidak berubah. Pembandingnya kini nomor **tersimpan** dari basis data (`PengalihanWhatsApp::nomor()`, sudah seragam); properti yang tak berguna itu dihapus. Perilaku: non-Admin menyimpan langsung; Admin — dialog hanya bila nomor peragaan (setelah diseragamkan, sehingga 08xx dan 62xx dianggap sama) terisi dan berbeda dari yang tersimpan (mengaktifkan atau mengubah); **mematikan** mode peragaan (kolom dikosongkan) tetap tanpa dialog, seperti yang sudah dimaksudkan kodenya; tanpa perubahan pada kolom peragaan, simpan langsung. Teks, isi, dan perilaku setelah dikonfirmasi tidak berubah. Tes lama yang memanggil `->instance()->simpanAction()->isConfirmationRequired()` tetap lulus dan tidak menangkap bug ini sebab dijalankan dalam permintaan yang sama dengan `mount`.

**A-008.** (1) Hash sesi diperbarui sesudah kata sandi berganti (`GantiKataSandi::perbaruiHashSesi()`, dipakai juga oleh Pengaturan, sesudah transaksinya). Dipakai `session()->put()`, bukan `request()->session()` seperti Filament, sebab `request()->hasSession()` bernilai `false` di dalam `Livewire::test` (pada permintaan nyata keduanya sama). (2) Aturan `GantiKataSandi::aturanBerbedaDariSaatIni()` menolak sandi baru yang cocok dengan hash tersimpan: "Kata sandi baru harus berbeda dari kata sandi saat ini." (Ganti Kata Sandi dan Pengaturan; pada Pengaturan hanya bila kolom sandi baru diisi, jadi kolom kosong tetap berarti "tidak diubah"). (3) Ganti Kata Sandi wajib: notifikasi kini "Kata sandi berhasil diubah" (isi "Selamat datang di SIMPBI." dipertahankan) dan tujuan tetap `Dashboard::getUrl()`, yang oleh middleware existing berlanjut ke Dasbor atau ke Lengkapi Akun; Pengaturan tetap di halaman dengan notifikasi "Pengaturan tersimpan".

**C-004.** `Filament\Http\Middleware\AuthenticateSession` (kelas yang sama dengan panel) ditempatkan paling depan pada `$gerbangAkun`, sebelum `Authenticate`, seperti urutan pada panel. Berlaku pada `/dokumen-bast/{bast}`, `/bukti-permintaan/{permintaan}`, dan `/pusat-bantuan/panduan`. Permintaan Livewire dan proses masuk tidak berubah (rute-rute itu bukan bagian panel).

**A-010.** Pada akun sendiri, `role` dan `status_aktif` tampil dengan keterangan tetapi `disabled` dan tidak ikut disimpan. `EditUser::beforeSave()` membaca muatan mentah: perubahan peran atau status pada akun sendiri ditolak seluruhnya (notifikasi "Perubahan ditolak", pesan yang diminta; nama yang ikut dikirim juga tidak tersimpan), dan perubahan yang menghabiskan Admin aktif ditolak dengan "Harus ada minimal satu Admin aktif." Perubahan lain (nama, email, tim, dan sebagainya) tetap boleh. **Catatan:** contoh pada permintaan (Admin A menonaktifkan Admin B yang menjadi satu-satunya Admin aktif lain) tidak pernah menghabiskan Admin aktif, sebab A sendiri aktif; penjaga kedua baru bekerja bila pelakunya Admin yang sudah dinonaktifkan tetapi sesinya masih hidup, dan itulah yang diuji.

**A-015.** Nama dan NIP tampil, `disabled` dan tidak ikut disimpan, untuk semua peran termasuk Admin, dengan keterangan "Diubah oleh Administrator melalui menu Pengguna." `simpan()` tidak lagi mengisi nama dan NIP dari muatan (aturan wajib, panjang, dan placeholder pada kedua kolom dibuang karena tidak berlaku pada kolom hanya-baca), sehingga muatan yang dimodifikasi, termasuk kosong, tidak berpengaruh dan tidak menggagalkan penyimpanan bagian lain. Sesudah simpan, formulir diisi ulang dengan nama dan NIP akun. Nomor WhatsApp, email, tanda tangan, dan ubah kata sandi tidak berubah.

**A-015b.** Untuk `role = tim`: label "Ketik nama tim Anda untuk konfirmasi", placeholder nama tim, dan pembanding di server berupa nama tim (relasi `tim`), tak peka huruf besar-kecil, spasi di ujung dibuang, deret spasi menjadi satu (`ratakanTeks()`). Tim kosong ditolak dengan "Akun Anda belum terhubung ke tim kerja, sehingga konfirmasi tidak dapat dilakukan. Hubungi Administrator." (bukan galat 500). Nama tim salah: "Tidak cocok dengan nama tim Anda. Ketik persis nama tim Anda." Peran lain: label, pembanding NIP, dan pesan tidak berubah. Satu penyesuaian teks kecil pada kolom yang sama: bantuan di bawah kolom untuk peran Tim menyebut "Tanda tangan Ketua Tim yang tersimpan dibubuhkan otomatis" (bukan "Tanda tangan Anda"), sebab tanda tangan yang dibubuhkan memang milik Ketua Tim. `pastikanTandaTangan()`, `penandaTanganPenerima()`, dan `ketuaTimPemohon()` tidak disentuh, sehingga tanda tangan dan nama pada dokumen tetap milik Ketua Tim.

## B4.6 Keluaran beku (aturan B)

Skenario siklus penuh Batch 3 (`BekuB4Test`, salinannya) dijalankan **sebelum** dan **sesudah** perubahan di SQLite (memori) dan MySQL sementara (`simpbi_b4_beku`): pengajuan → Ketua → verifikasi → Kasubbag → siapkan → konfirmasi → sahkan (bukti permintaan asli dan berfootnote), dua BAST berurutan, permintaan kedaluwarsa, Kartu Kendali (PDF dan XLSX), ekspor Riwayat (PDF dan XLSX), dialog Rincian kedaluwarsa, dan Riwayat Penempatan. Isi PDF diekstrak dengan `pdftotext -layout`, sel XLSX dengan PhpSpreadsheet. Satu-satunya penyesuaian skenario: pada fase "akhir" langkah konfirmasi oleh akun Tim mengetik nama tim (fase "awal" mengetik NIP, sesuai perilaku kode masing-masing).

| Driver | Berkas dibandingkan | `diff -r` awal vs akhir |
|---|---|---|
| SQLite | 14 | **kosong (identik)** |
| MySQL 8.4.3 sementara | 14 | **kosong (identik)** |

Berkas `bukti-permintaan.txt` yang identik sekaligus membuktikan bahwa dokumen tidak bergantung pada isian konfirmasi akun Tim. Baris ekspor Riwayat berstempel waktu sama tampil berbeda antara SQLite dan MySQL seperti pada Batch 3 (F-004); tidak ada hubungannya dengan batch ini.

## B4.7 Verifikasi peramban (headless, MySQL sementara `simpbi_b4_verif`, server sesuai A4)

| Butir | Hasil |
|---|---|
| (a) Kasubbag, Gudang, Ketua Tim, Tim | Ubah nomor WA lalu Simpan Perubahan: **tanpa dialog**, notifikasi "Pengaturan tersimpan", nomor tetap sesudah muat ulang. Nama dan NIP `disabled` pada keempatnya. Gudang mengganti sandi lewat Pengaturan: tanpa dialog, tetap di `/admin/pengaturan` dan tetap masuk sesudah muat ulang; sandi yang sama ditolak ("Kata sandi baru harus berbeda dari kata sandi saat ini."). |
| (b) Admin | Ubah WA saja: tanpa dialog. Mengaktifkan peragaan: dialog "Aktifkan mode peragaan?" lalu tersimpan setelah "Ya, Simpan". Mode menyala, ubah WA saja: tanpa dialog. Mengubah nomor peragaan: dialog "Ubah nomor mode peragaan?"; Batal tidak menyimpan. Mematikan: tanpa dialog, tersimpan. |
| (c) Ganti sandi wajib | Sandi sama ditolak. Akun lengkap: sandi baru → notifikasi "Kata sandi berhasil diubah", `/admin` (Dasbor), tetap masuk (`/admin/pengaturan` terbuka). Akun belum lengkap: berlanjut ke `/admin/lengkapi-akun`, tetap masuk. Berkas sesi: hash cocok segera sesudah POST. |
| (d) Dua konteks | Dua konteks peramban terpisah (`browser.newContext()`) untuk `dua.sesi@bps.go.id`; A mengganti sandi lewat Pengaturan. B lama: `/admin/pengaturan` → `/admin/login`; `/dokumen-bast/{id}`, `/bukti-permintaan/{id}`, `/pusat-bantuan/panduan` → `302 → /admin/login`. A tetap sah: panel terbuka, unduhan 200/404/404 (berkas bukti dan panduan memang belum ada di data uji), tanpa pengalihan ke halaman masuk. |
| (e) Admin akun sendiri | Peran dan status `disabled` dengan keterangan; muatan diubah lewat Livewire (peran kasubbag, nonaktif) → notifikasi "Perubahan ditolak — Anda tidak dapat menonaktifkan atau mengubah peran akun Anda sendiri."; basis data: peran `admin`, `status_aktif = 1`. Ubah nama saja: "Data berhasil disimpan". |
| (f) Konfirmasi Penerimaan | Akun Tim: label "Ketik nama tim Anda untuk konfirmasi", placeholder nama tim; nama tim lain ditolak ("Tidak cocok dengan nama tim Anda…"); `  statistik   SOSIAL  ` diterima → "Penerimaan barang dikonfirmasi". Ketua Tim: label "Ketik NIP Anda untuk mengonfirmasi", NIP diterima. Kasubbag mengesahkan keduanya (mengetik nama lengkap karena akunnya tanpa NIP, seperti sebelumnya). Dokumen yang dikonfirmasi akun Tim dan yang dikonfirmasi Ketua Tim: teks identik (`pdftotext`; satu-satunya beda adalah jumlah barang pada data uji saya), blok tanda tangan memuat nama Ketua Tim (Wanda Pribadi), dan struktur gambar identik (logo, tanda tangan, QR). Nama akun Tim tidak muncul. |

Tidak ada `console.error` pada run yang dijadikan bukti; satu-satunya permintaan gagal adalah pembatalan (`ERR_ABORTED`) pada `chart.js` dan pembaruan Livewire yang tertimpa navigasi.

## B4.8 Hasil pemeriksaan F.4, G.5, dan investigasi H.1–H.3 (tidak mengubah apa pun)

**F.4 — celah pada Hapus dan Impor Pengguna** (probe di luar repositori, SQLite memori):
- **Hapus pengguna** (`AksiHapusTerlindung`, penjaga `User::punyaRiwayat()` saja): Admin aktif satu-satunya tanpa riwayat **berhasil menghapus akunnya sendiri** lewat tombol Hapus pada halaman Ubah. Hapus massal memakai penjaga yang sama; dibaca dari kode, tidak diprobe dinamis. → **G-001**.
- **Impor Pengguna** (`ImporPengguna::jalankan`, `forceFill` dari berkas): baris untuk Admin dengan peran "Kasubbag Umum" dan Aktif "Tidak" diterapkan tanpa penjaga; hasilnya **nol Admin aktif** tersisa. → **G-002**.

**G.5:**
- *Akun Tim tanpa NIP pada konfirmasi yang meminta NIP, sebelum A-015b* (dari kode asli `cocokIdentitas()`: `filled($nip) ? $nip : $name`): tidak diblokir, tidak dilewati, dan tidak memakai NIP Ketua Tim; pembandingnya **nama lengkap akun Tim itu sendiri** (`users.name`, mis. "Tim Statistik Sosial"). Pada data seeder, `tim01` tidak punya NIP. Tidak diuji dinamis pada kode asli; tes lama memakai akun uji yang ber-NIP.
- *Peran Tim di Pengaturan*: melihat kolom NIP (kosong, kini hanya-baca) dan Nomor WhatsApp. Nomor WhatsApp **dipakai**: `Onboarding::PERAN_BERNOMOR_WA` mencakup `tim`, sehingga akun Tim wajib mengisinya pada Lengkapi Akun, dan `NotifikasiService` (`berperan(['tim', 'ketua_tim'], …)`) mengirim notifikasi ke semua akun tim itu termasuk akun Tim, memakai nomornya sendiri (`KirimPesanWhatsApp`: `$notifikasi->user?->no_hp`). Ini bertentangan dengan keputusan pemilik (akun Tim tanpa kewajiban WhatsApp; notifikasi memakai nomor Ketua Tim). → **G-004**. **[Digantikan pada Batch 5, 22 Sep 2026: pernyataan tentang nomor Ketua Tim tidak lagi berlaku. Keputusan pemilik opsi B — akun Tim mengisi nomor WhatsApp milik tim, dan notifikasi WhatsApp tetap ke nomor pada akun itu. G-004 ditutup tanpa perubahan kode.]** NIP peran Tim tidak dipakai di mana pun selain sebagai pembanding lama tadi.
- *Email pada Akun Saya*: **dapat diubah sendiri oleh pemilik akun** (satu-satunya aturan: `email` unik), padahal email adalah kredensial masuk. → **G-005**.
- *Syarat awal A-015*: (1) form Pengguna memiliki kolom `name` dan `nip` yang dapat disunting Admin — **terpenuhi**; (2) `ImporPengguna` mengisi NIP (`'nip' => $ambil('nip') ?: null`) — **terpenuhi**; (3) `LengkapiAkun` tidak menyimpan nama atau NIP (hanya menampilkannya, dan menyimpan `no_hp` serta tanda tangan) — **terpenuhi**.

**H.1 — aksi yang meminta ketik NIP:** tiga, semuanya lewat `bidangKonfirmasiNip()`: *Barang Siap Diambil* (`siapkan`, Petugas Gudang), *Konfirmasi Penerimaan* (`konfirmasi`, `tim` dan `ketua_tim`), dan *Sahkan* (`sahkan`, Kasubbag). Peran Tim hanya dapat melakukan Konfirmasi Penerimaan — **terkonfirmasi** (kondisi `visible()` tiap aksi).

**H.2 — akun Tim tanpa NIP (perilaku sebelum A-015b):** memakai nama lengkap akun (lihat G.5); tidak diblokir dan tidak dilewati.

**H.3 — ke mana nilai yang diketik pergi:** **tidak ke mana pun**. `konfirmasi_nip` hanya dibaca oleh aturan validasinya; `konfirmasiPenerimaan()` tidak membacanya dan tidak ada tulisan ke riwayat persetujuan, PDF, notifikasi, maupun ekspor. Karena tidak muncul pada PDF atau Excel beku, perbaikan A-015b dilanjutkan (dan terbukti oleh keluaran beku identik).

## B4.9 Perubahan perilaku

1. Simpan Perubahan pada Pengaturan tidak lagi membuka dialog mode peragaan kecuali Admin mengaktifkan atau mengubah nomor peragaan (dibandingkan dengan nilai tersimpan).
2. Sesudah mengganti kata sandi (Ganti Kata Sandi wajib maupun Pengaturan), pengguna tetap masuk di perangkatnya; sesi di perangkat lain, termasuk pada tiga rute unduhan, tidak berlaku. Kata sandi baru harus berbeda dari yang sedang berlaku. Notifikasi Ganti Kata Sandi: "Kata sandi berhasil diubah".
3. Admin tidak dapat menonaktifkan atau mengubah peran akunnya sendiri, dan tidak dapat membuat Admin aktif menjadi nol lewat form Ubah pengguna.
4. Nama dan NIP hanya-baca pada Pengaturan untuk semua peran.
5. Akun peran Tim mengetik nama timnya (bukan NIP atau nama akun) pada Konfirmasi Penerimaan; tanpa tim ditolak dengan pesan.
6. Tidak berubah: hak akses, alur persetujuan, isi dan tata letak PDF dan Excel, tanda tangan dan nama penanda tangan pada dokumen, Nama Pemohon di Katalog, peran lain pada langkah yang meminta NIP.

## B4.10 ID baru (tidak diperbaiki)

| ID | Kategori | Keparahan | Temuan | Bukti |
|---|---|---|---|---|
| **G-001** | Keamanan | Sedang | Admin dapat **menghapus akunnya sendiri**, termasuk sebagai Admin aktif terakhir, bila tidak punya riwayat (tombol Hapus pada halaman Ubah dan hapus massal). Penjaga hanya `punyaRiwayat()`. | Probe SQLite: akun terhapus; `AksiHapusTerlindung.php`, `User::punyaRiwayat()`. |
| **G-002** | Keamanan | Sedang | **Impor Pengguna** menurunkan peran atau menonaktifkan Admin (juga Admin aktif terakhir) tanpa penjaga; berkas impor kerap diunggah ulang. | Probe SQLite: peran → kasubbag, aktif → tidak, Admin aktif tersisa 0; `ImporPengguna.php` (`forceFill($atribut)`). |
| **G-003** | Bug | Sedang | Penyebab yang sama dengan A-008 terjadi pada form **Pengguna**: Admin yang mengisi Kata Sandi pada akunnya sendiri lalu Simpan terlempar ke halaman masuk. | Peramban: `POST 200 → POST 302 → GET 200 /admin/login`, lalu `/admin/users` → `/admin/login`. Tidak diperbaiki (di luar ID batch ini). |
| **G-004** | Keputusan pemilik | **Ditutup (22 Sep 2026, Batch 5)** | **Keputusan pemilik opsi B: akun Tim mengisi nomor WhatsApp milik tim dan notifikasi WhatsApp tetap ke nomor pada akun itu; tidak ada perubahan kode. Pernyataan Batch 4 tentang nomor Ketua Tim digantikan.** Temuan semula: peran `tim` wajib melengkapi nomor WhatsApp (Lengkapi Akun) dan menerima notifikasi WhatsApp di nomornya sendiri, yang kini menjadi perilaku yang dikehendaki. | `Onboarding::PERAN_BERNOMOR_WA` (memuat `tim`); `NotifikasiService.php:266` (`berperan(['tim', 'ketua_tim'], …)`); `KirimPesanWhatsApp.php:75`. |
| **G-005** | Keamanan | Rendah | **Email** pada Akun Saya (kredensial masuk) dapat diubah sendiri oleh pemilik akun, tidak seperti nama dan NIP yang kini terkunci. | `Pengaturan.php` (`TextInput::make('email')`); tes `test_nomor_whatsapp_dan_email_tetap_dapat_disimpan`. |
| **G-006** | Konsistensi | Rendah | **NIP Pemohon** pada dialog Ajukan di Katalog bebas diisi, tersimpan (`nip_pemohon`), dan tampil pada Rincian permintaan serta ekspor Riwayat. Keputusan pemilik hanya menyebut Nama Pemohon sebagai bebas. | `KatalogBarang.php:126,161,270`; `detail-permintaan.blade.php:169`; `MengeksporRiwayat.php:218`. |

## B4.11 Tidak dapat diverifikasi

- **Peramban hanya pada MySQL sementara.** SQLite dicakup oleh suite dan skenario beku, bukan oleh peramban.
- **Perilaku asli A-007 dan H.2 di peramban** tidak direkam ulang pada kode asli; buktinya adalah uji mundur (tes gagal pada kode asli) dan pembacaan kode, sedangkan bukti A-008 direkam langsung pada kode asli (B4.4).
- **Hapus massal (G-001)** hanya dibaca dari kode.
- **Sesi dengan "ingat saya"** (`viaRemember`) tidak diuji.
- **Unduhan bukti dan panduan** pada data uji bersifat 404 (berkas belum ada), sehingga yang dibuktikan hanyalah tidak adanya pengalihan ke halaman masuk bagi sesi yang sah.
- **Konkurensi** penghapusan atau penonaktifan Admin oleh dua Admin serentak tidak diuji; penjaga A-010 membaca basis data tanpa kunci baris.
- **Dua konteks pada sesi yang sama** dibuktikan dengan konteks peramban terpisah; sesi lain di perangkat berbeda secara fisik tidak diuji.

---

# Batch 5 — penjaga akun Admin, kunci email, penempatan aset, status BAST, NIP pemohon

Tanggal: 22 September 2026. ID yang dikerjakan: **G-001**, **G-002**, **G-003**, **G-005**, **G-006**, **F-002 (opsi B, dengan keputusan pemilik di B5.5)**, **F-003**, **F-006**, ditambah koreksi catatan **G-004** (dokumen saja). F-001, F-004, dan F-005 hanya catatan dan tidak dikerjakan; temuan lain tidak disentuh; `laporan-audit.md` tidak diubah.

## B5.1 Cara kerja dan pengaman

| Butir | Keterangan |
|---|---|
| Git | Hanya baca (`status`, `diff`, `log`, `rev-parse`, `branch --show-current`; `git diff --no-index` untuk membandingkan). Cabang `main`, HEAD `b79831d`. Perubahan pemilik dan hasil batch sebelumnya dibiarkan utuh; tidak ada commit. |
| Cadangan | `E:\Projek Skripsi\cadangan-audit\batch-5\`: `kondisi-awal.txt`, `asli\` (23 berkas, disalin sebelum sentuhan pertama, termasuk berkas tes dan dokumen), `sha-awal.tsv`, `manifest.md`, `diff\`, `keluaran-awal\`, `keluaran-akhir\`. Bukti perubahan = `git diff --no-index` salinan asli vs berkas sekarang. |
| Basis data kerja | Tidak disentuh. Dari `.env` hanya `DB_CONNECTION=sqlite` dibaca. `database/database.sqlite` tidak dibuka, dibaca, atau disalin; hanya metadatanya: **sebelum** `2026-09-21 21:44:52.3980772`, 331.776 B (sama dengan akhir Batch 4); **sesudah** `2026-09-21 21:44:52.3980772`, 331.776 B (**tidak berubah**). |
| MySQL sementara | MySQL 8.4.3, biner `E:\laragon\laragon\bin\mysql\mysql-8.4.3-winx64\bin\`, **datadir baru** `scratchpad/mysql-data-b5` (di luar repositori dan folder Laragon), `127.0.0.1:3399`. MySQL Laragon (3306, milik pemilik, tetap berjalan dengan PID yang sama) tidak dijalankan ulang, dihentikan, atau diubah; datadir-nya tidak dibaca. Sebelum tiap run dicetak `DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=…`: suite `simpbi_audit_test`, keluaran beku `simpbi_b5_beku`, peramban `simpbi_b5_verif`; `.env` tidak memuat host, port, maupun nama basis data. |
| Isolasi (A4) | Semua tes, perintah artisan, dan server memakai storage salinan (`LARAVEL_STORAGE_PATH`). Bukti (proses aplikasi dengan env yang sama): SQLite — `storage_path()` = `…/scratchpad/app-storage-sqlite`, `database.default` = `sqlite`, `:memory:` (dari `phpunit.xml`); MySQL — `…/scratchpad/app-storage`, `default` = `mysql`, `127.0.0.1:3399`, `select database()` = `simpbi_audit_test`; peramban — `…/scratchpad/app-storage-b5v`, `simpbi_b5_verif` (dimigrasi dan di-seed dari migration dan seeder existing, **bukan** salinan basis data kerja). Server `php -d variables_order=EGPCS -S 127.0.0.1:8126` dengan skrip router. **`storage/framework` repositori: 163 berkas sebelum dan 163 sesudah** (selisih 0); `bootstrap/cache` tidak berubah (`packages.php` 3659 B, `services.php` 23857 B). `optimize:clear` dan `pint` tidak dijalankan. |
| Disk | `C:` 6,29 GB kosong sebelum, 6,07 GB sesudah (ambang berhenti 3 GB terpenuhi); `E:` 50,67 GB. |
| Skill | Tidak ada skill di `.claude/skills` yang relevan (sebagian besar skill desain dan animasi). Untuk verifikasi UI dipakai skill **`browser-automation`** (di luar repositori). |
| Format | Akhiran baris tiap berkas dipertahankan (CRLF pada `User.php`, `EditUser.php`, `ImporPengguna.php`, `BastMutasiAsetsTable.php`, `PerlindunganHapusTest.php`; LF pada lainnya). Pembantu Batch 4 `GantiKataSandi::perbaruiHashSesi()` dipakai ulang, tidak diduplikasi. |

## B5.2 Hasil tes

| Suite | Garis dasar (sebelum ubah) | Akhir |
|---|---|---|
| SQLite (memori) | OK — 617 tes, 4439 assertion (3 mnt 45 dtk) | **OK — 663 tes, 4825 assertion** (4 mnt 27 dtk) |
| MySQL 8.4.3 sementara | OK — 617 tes, 4439 assertion (4 mnt 03 dtk) | **OK — 663 tes, 4825 assertion** (4 mnt 44 dtk); 0 error, 0 gagal |

**46 tes baru** dalam tujuh kelas: `HapusAkunTerjagaTest` (10), `ImporPenggunaPenjagaAdminTest` (7), `SandiFormPenggunaTest` (4), `EmailTerkunciPengaturanTest` (4), `NipPemohonKatalogTest` (5), `PenempatanAsetFormTest` (7), `StatusBastDanAsetAktifTest` (9).

**Uji mundur.** Sepuluh berkas kode dikembalikan sementara ke salinan asli dan 46 tes baru dijalankan: **27 gagal atau galat** (26 gagal, 1 galat); 19 tetap lulus. Yang lulus menguji perilaku yang tidak boleh berubah: menghapus Admin lain saat masih ada Admin aktif lain, hapus massal beberapa Admin lain, pengguna biasa dan pengguna berriwayat, impor pengguna biasa dan akun baru, kolom lain pada akun pengimpor, Admin nonaktif yang diturunkan, NIP 18 angka, kosong, dan Nama Pemohon bebas, form Buat aset, mutasi BAST, sandi pengguna lain dan sandi kosong pada form Pengguna, alur normal Sahkan dan Konfirmasi, aset aktif, serta email yang diubah Admin lewat form Pengguna. Satu di antaranya perlu dijelaskan: `test_muatan_dimodifikasi_dengan_aset_nonaktif_tidak_membentuk_bast` lulus juga pada kode asli, sebab validasi opsi Select pada form Buat BAST sudah menolak aset nonaktif dari muatan Livewire ("The selected aset (NUP — Nama) is invalid", terbukti lewat probe pada layanan asli); penjaga F-006(b) yang ditambahkan menutup jalur di luar form (`periksaPembuatan()`), dan itu dibuktikan tes layanan yang gagal pada kode asli. Semua berkas dipulihkan dan dicocokkan sha256 dengan salinan sebelum uji mundur (10 dari 10 identik).

**Tes lama yang disesuaikan (3, tanpa menghapus tes):**
- G-001: `PerlindunganHapusTest::test_pengguna_tanpa_riwayat_tetap_dapat_dihapus` — pengguna yang dihapus kebetulan Admin aktif satu-satunya; datanya diganti menjadi peran Kasubbag (pengguna biasa). Asersinya tidak berubah.
- G-005: `NamaNipTerkunciPengaturanTest::test_nomor_whatsapp_dan_email_tetap_dapat_disimpan` (Batch 4) diubah menjadi `test_nomor_whatsapp_tetap_dapat_disimpan`; asersi bahwa email dapat disimpan sendiri dicabut (kini terkunci, dibuktikan `EmailTerkunciPengaturanTest`).
- F-002: `RiwayatPenempatanAsetTest::test_mengganti_tim_lewat_penyuntingan_tidak_dicatat_sebagai_mutasi` — ditambah satu asersi bahwa penempatan memang tidak berpindah; datanya tidak diubah.

**G-006:** tes lama yang memakai NIP pemohon sembarang: **0** (dua berkas yang memakai `nip_pemohon`, `PengajuanKatalogAturanTest` dan `PengajuanPermintaanTest`, sudah memakai `199001012020121001`, 18 angka). Skenario beku tidak mengisi `nip_pemohon` (null), sehingga datanya tidak perlu diubah.

## B5.3 Per ID

| ID | Status | Berkas diubah | Tes | Catatan |
|---|---|---|---|---|
| **G-001** | **Diperbaiki** | `Models/User.php` (`alasanTidakDapatDihapus()`, pendengar `deleting`), `Filament/Support/AksiHapusTerlindung.php` (parameter `$penjaga` pada `tunggal()` dan `massal()`), `Resources/Users/Tables/UsersTable.php`, `Resources/Users/Pages/EditUser.php` | `HapusAkunTerjagaTest` (10) | B5.4. |
| **G-002** | **Diperbaiki** | `Services/Impor/ImporPengguna.php` (`pesanPenjagaAdmin()`) | `ImporPenggunaPenjagaAdminTest` (7) | Memakai mekanisme baris gagal yang ada (`HasilImpor::catatGalat`); template tidak berubah. |
| **G-003** | **Diperbaiki** | `Resources/Users/Pages/EditUser.php` (`afterSave()`) | `SandiFormPenggunaTest` (4) | Memakai `GantiKataSandi::perbaruiHashSesi()` (Batch 4). |
| **G-005** | **Diperbaiki** | `Filament/Pages/Pengaturan.php` | `EmailTerkunciPengaturanTest` (4) | Syarat awal terpenuhi (B5.6). |
| **G-006** | **Diperbaiki** | `Filament/Pages/KatalogBarang.php` | `NipPemohonKatalogTest` (5) | Investigasi a–c di B5.6. |
| **F-002** | **Diperbaiki (dengan keputusan pemilik)** | `Resources/AsetTetaps/Schemas/AsetTetapForm.php` | `PenempatanAsetFormTest` (7, bersama F-003) | B5.5; investigasi a–c dan kueri SELECT di B5.7. |
| **F-003** | **Diperbaiki** | `Resources/AsetTetaps/Schemas/AsetTetapForm.php` | `PenempatanAsetFormTest` | Kolom nullable; impor dan sinkronisasi tidak berubah. |
| **F-006** | **Diperbaiki** | `Services/MutasiAsetService.php`, `Resources/BastMutasiAsets/Tables/BastMutasiAsetsTable.php` | `StatusBastDanAsetAktifTest` (9) | B5.4. |
| **G-004** | **Ditutup** (dokumen saja) | `laporan-perbaikan.md` (bagian Batch 4) | — | Keputusan pemilik opsi B; tidak ada perubahan kode. |

Tidak ada view Blade PDF, kelas ekspor, template (termasuk template impor pengguna dan aset), migration, tabel, field, dependensi, seeder, job CI, atau disk baru yang diubah atau dibuat. Hak akses dan perilaku notifikasi WhatsApp tidak berubah.

## B5.4 Rincian keputusan

**G-001.** Penjaga di **server**, bukan hanya di tampilan, pada tiga jalur:
1. *Jalur langsung (model).* `User::alasanTidakDapatDihapus()` menolak (a) menghapus akun yang sedang masuk ("Anda tidak dapat menghapus akun Anda sendiri.") dan (b) penghapusan yang menyisakan nol Admin aktif ("Harus ada minimal satu Admin aktif."). Dipasang pada `deleting`. **Jebakan yang ditemukan:** peristiwa `deleting` dijalankan dengan halt, sehingga pendengar pertama yang mengembalikan nilai bukan null menghentikan rantai; pendengar trait `DilindungiRiwayat` mengembalikan `true` bagi yang boleh dihapus. Pendengar yang didaftarkan sesudahnya (`booted()`) **tidak pernah dijalankan**, jadi penghapusan Admin aktif terakhir tetap lolos walau kode tampak benar. Karena itu pendengar didaftarkan pada `booting()` (sebelum trait) dan mengembalikan `null` bila boleh, `false` bila menolak. Kasus ini dijaga tes jalur langsung (dicatat sebagai H-001).
2. *Aksi tunggal* (halaman Ubah) dan 3. *hapus massal* (`DeleteBulkAction`): `AksiHapusTerlindung::tunggal()` dan `massal()` menerima `$penjaga` opsional yang memeriksa **seluruh pilihan sekaligus sebelum apa pun dihapus** (`before` + `cancel()`), sehingga hapus massal ditolak seluruhnya, tanpa penghapusan sebagian. Penjaga riwayat yang sudah ada (`punyaRiwayat()`) tidak berubah dan berjalan berdampingan. Pemakai lain pembantu ini (Barang, Tim, Aset, Kategori) tidak terpengaruh karena parameternya opsional.

**G-002.** Pemeriksaan per baris, berurutan, terhadap keadaan basis data saat baris diproses, sebelum transaksi baris itu: (1) baris yang mengubah `role` atau `status_aktif` **akun pengimpor sendiri** ditolak ("Baris ini mengubah peran atau status aktif akun Anda sendiri, sehingga tidak diproses. Ubah lewat menu Pengguna."), kolom lain pada akun itu tetap boleh; (2) baris yang menurunkan peran atau menonaktifkan **Admin aktif terakhir** ditolak ("… Harus ada minimal satu Admin aktif."). Baris yang ditolak dilaporkan lewat `HasilImpor::catatGalat` seperti galat baris lain (nomor baris dan pesan pada ringkasan), sedangkan baris berikutnya tetap diproses. Kunci pencocokan akun adalah `username` (yang dipakai impor). Karena pemeriksaan bergantung pada keadaan saat itu, urutan baris berbeda selalu menyisakan minimal satu Admin aktif (diuji dua urutan). Impor yang tidak melibatkan Admin tidak berubah. Keterbatasan: pemeriksaan tidak mengunci baris, sehingga dua impor serentak tidak diuji.

**G-003.** `EditUser::afterSave()`: bila akun yang disimpan adalah akun yang sedang masuk dan `password` berubah, instans pengguna yang masuk dimuat ulang (ia masih memegang hash lama, sebab `EditRecord` memuat rekamannya sendiri) lalu `GantiKataSandi::perbaruiHashSesi()` dipanggil. Akun pengguna lain tidak menyentuh sesi Admin; sesi lama pengguna itu tidak berlaku (perilaku bawaan `AuthenticateSession`). Aturan "sandi baru harus berbeda" **tidak** ditambahkan pada form ini (diuji: sandi yang sama tidak ditolak).

**G-005.** Email pada Akun Saya tampil dengan keterangan "Diubah oleh Administrator melalui menu Pengguna.", `disabled` dan `dehydrated(false)` untuk semua peran; `simpan()` tidak lagi mengisi email dari muatan (aturan email, wajib, unik, dan placeholder dibuang karena tidak berlaku pada kolom hanya-baca); formulir diisi ulang dengan email akun sesudah simpan. Nomor WhatsApp, tanda tangan, dan ubah kata sandi tidak berubah.

**G-006.** Kolom `nip_pemohon` tetap opsional. Bila diisi, aturan server pada formulir aksi menuntut 18 angka setelah spasi dibuang ("NIP pemohon harus terdiri atas angka dengan format yang benar."); nilai disimpan sebagai angka murni (`dehydrateStateUsing`). Nama Pemohon tidak berubah. Data lama tidak diubah; cara nilai tampil pada Rincian dan ekspor Riwayat tidak berubah.

**F-006.** (1) `MutasiAsetService::kunciDanPastikanStatus()`: di dalam transaksi `sahkan()` (status harus `menunggu_pengesahan`) dan `konfirmasi()` (harus `menunggu_konfirmasi`), sebelum tulisan apa pun, baris BAST dikunci (`lockForUpdate`, pola yang sama dengan penguncian aset) dan statusnya dibaca dari baris terkunci, bukan dari model yang dipegang pemanggil (bisa usang pada panggilan ganda). Bila tidak sesuai: `RuntimeException` "BAST ini sudah diproses atau statusnya telah berubah. Muat ulang halaman." tanpa mengubah status, aset, riwayat penempatan, dokumen, maupun notifikasi. Aksi Konfirmasi Penerimaan kini menampilkan penolakan itu sebagai notifikasi ("Penerimaan tidak dapat dikonfirmasi"), seperti Sahkan sudah menampilkan "BAST tidak dapat disahkan" (Batch 3); tanpa itu galat tampil mentah. Model pemanggil sengaja tidak ditimpa dengan baris terkunci: tes Batch 3 `test_galat_teknis_saat_sahkan_tidak_ditelan` menyimulasikan galat teknis lewat atribut di memori. (2) `periksaPembuatan()` menolak aset dengan `status_aktif = false` ("Aset ini tidak aktif dan tidak dapat dimutasi."); pemeriksaan penempatan (Batch 3) dan penomoran (Batch 2) tidak diubah. **Keterbatasan konkurensi:** kunci baris hanya berlaku pada MySQL; SQLite tidak mengenalnya (penulisnya sudah diserialkan basis datanya). Perilaku dengan dua koneksi paralel sungguhan tidak diuji; pada peramban, dua klik Sahkan berjarak 60 ms menghasilkan satu pemrosesan.

## B5.5 F-002 dan F-003: keputusan pemilik dan rinciannya

**Keputusan pemilik (dipilih pada 22 Sep 2026 atas pertanyaan saya):** kolom penempatan pada form Ubah **terkunci hanya bila aset sudah ditempatkan**. Sebabnya: aset dapat dicatat tanpa penempatan dan mekanisme "penempatan awal" lewat form Ubah sudah ada (`EditAsetTetap::afterSave` mencatatnya ke Riwayat). Impor tidak mengubah aset yang sudah ada dan BAST menolak aset tanpa penempatan; bila kolom dikunci penuh, aset semacam itu tidak akan pernah dapat ditempatkan lewat aplikasi (hanya lewat basis data). Perilaku:
- Form **Ubah**, aset sudah ditempatkan: kolom `disabled`, tidak ikut disimpan, dengan keterangan "Penempatan diubah melalui Mutasi Aset (BAST)." Muatan yang dimodifikasi tidak menulis penempatan (server: `dehydrated(false)`).
- Form Ubah, aset belum ditempatkan: kolom tetap dapat diisi sekali (dicatat sebagai penempatan awal, perilaku sekarang); sesudah itu terkunci.
- Form **Buat**: tidak berubah (dapat memilih penempatan).
- Perpindahan antar tim: hanya lewat Mutasi Aset (BAST), yang tetap memindahkan aset dan menulis riwayat (diuji).

**F-003.** `external_id` dan `synced_at` tampil dengan `disabled()->dehydrated(false)`. Kolom keduanya `nullable` tanpa nilai bawaan, jadi form Buat menyimpan NULL. Impor dan sinkronisasi tidak diubah (`SinkronisasiAsetTetapTest` tetap lulus tanpa perubahan); strip status sinkronisasi tidak berubah.

## B5.6 Investigasi G-005 dan G-006 (baca saja)

**G-005, syarat awal:** (1) form Pengguna memiliki kolom `email` yang dapat disunting Admin — **terpenuhi**; (2) Lengkapi Akun/Onboarding tidak mengumpulkan atau menyimpan email (komentar `Onboarding.php`: "email sudah berasal dari data akun dan tidak diisi ulang di sini") — **terpenuhi**; (3) tidak ada fitur yang bergantung pada pemilik akun mengubah emailnya sendiri: tidak ada pemulihan kata sandi mandiri (halaman masuk hanya menampilkan nomor bantuan), `MustVerifyEmail` dinonaktifkan (komentar), tidak ada pengiriman surel (`Mail::`, `sendResetLink`, verifikasi perubahan email kosong pada `app/`, `config/`, `routes/`); email pada Pusat Bantuan adalah alamat kontak Sub-Bagian Umum, bukan email pengguna — **terpenuhi**. Email dipakai untuk masuk; diuji Admin mengubahnya lewat form Pengguna, tampil pada Pengaturan, dan dipakai masuk (email lama tidak lagi berlaku).

**G-006:**
- **(a)** Kolom **opsional** sekarang (`TextInput::make('nip_pemohon')` tanpa `required()`; nilainya diisi awal dari NIP akun, kosong untuk akun Tim). Tidak berubah.
- **(b)** **Tidak ada aturan format NIP di mana pun**: form Pengguna hanya `maxLength(30)`, Pengaturan (sebelum dikunci) hanya `maxLength(30)`, Impor Pengguna menyimpan `$ambil('nip') ?: null` tanpa validasi. Karena itu dipakai aturan **baru**: 18 angka (spasi diabaikan).
- **(c)** Disimpan **mentah** apa adanya pada `permintaan_barang.nip_pemohon` (`?: null`); dipakai dan ditampilkan pada dialog Rincian permintaan (`detail-permintaan.blade.php:169`) dan ekspor Riwayat (`MengeksporRiwayat.php:218`, keluaran beku). Kini disimpan sebagai angka murni; tampilan tidak diubah.

## B5.7 Investigasi F-002 (baca saja) dan kueri SELECT

- **(a) Pembuatan aset** menulis baris pembuka Riwayat Penempatan: **ya**, bila timnya terisi (`CreateAsetTetap::afterCreate` → `AsetTetap::catatPenempatanAwal()`, yang menolak baris kedua dan tidak membuat apa pun tanpa tim).
- **(b) Impor Aset Tetap:** **tidak** mengubah penempatan aset yang **sudah ada** (`atributSumber()` sengaja tidak memuat `tim_penempatan_id`; kolom Tim Kerja Penempatan pada baris itu dilaporkan "tidak dipakai"), sehingga tidak menulis riwayat. Untuk aset **baru** ia mengisi penempatan dan memanggil `catatPenempatanAwal()`. Bukan upsert atas penempatan.
- **(c) Semua penulis `tim_penempatan_id`** (pencarian di `app/`, `database/`, `routes/`, `resources/`, `config/`): `AsetTetapForm` (form Buat dan Ubah; kini terkunci pada Ubah bila sudah ditempatkan), `ImporAsetTetap` (aset baru, dengan riwayat awal), `MutasiAsetService::sahkan()` (dengan riwayat mutasi), dan `AsetTetapSeeder` (query builder, menulis baris riwayat sendiri). `BastMutasiAsetSeeder` hanya membaca. **Tidak ada jalur yang melewati riwayat**; tidak ada ID H- dari investigasi ini.

**Kueri SELECT F-002** (hanya baca; **jangan dijalankan pada basis data kerja oleh saya**). Aset yang penempatan sekarang-nya tidak sama dengan tim pada baris riwayat terbuka (termasuk aset bertim yang belum berriwayat, dan aset tanpa tim yang masih punya riwayat terbuka). Diuji pada salinan sementara MySQL 8.4.3 dan SQLite: kosong pada data konsisten, dan menemukan aset yang sengaja disimpangkan.

```sql
SELECT a.id, a.nup, a.nama_aset, a.tim_penempatan_id, r.tim_id AS tim_riwayat_terbuka
FROM aset_tetap a
LEFT JOIN riwayat_penempatan_aset r ON r.aset_id = a.id AND r.tanggal_selesai IS NULL
WHERE (a.tim_penempatan_id IS NOT NULL AND (r.id IS NULL OR r.tim_id <> a.tim_penempatan_id))
   OR (a.tim_penempatan_id IS NULL AND r.id IS NOT NULL)
ORDER BY a.id;
```

## B5.8 Keluaran beku (aturan B)

Skenario siklus penuh Batch 3 dan 4 (`BekuB5Test`, salinannya; konfirmasi akun Tim kini selalu mengetik nama tim) dijalankan **sebelum** dan **sesudah** perubahan di SQLite (memori) dan MySQL sementara (`simpbi_b5_beku`): siklus permintaan sampai pengesahan (bukti asli dan berfootnote), dua BAST berurutan (Sahkan dan Konfirmasi, jalur yang kini memakai penjaga F-006), permintaan kedaluwarsa, Kartu Kendali, ekspor Riwayat (PDF dan XLSX), dialog Rincian, dan Riwayat Penempatan. Isi PDF diekstrak dengan `pdftotext -layout`, sel XLSX dengan PhpSpreadsheet.

| Driver | Berkas dibandingkan | `diff -r` awal vs akhir |
|---|---|---|
| SQLite | 14 | **kosong (identik)** |
| MySQL 8.4.3 sementara | 14 | **kosong (identik)** |

## B5.9 Verifikasi peramban (headless, MySQL sementara `simpbi_b5_verif`, server sesuai A4)

| Butir | Hasil |
|---|---|
| (a) Admin hapus akun sendiri | Tombol Hapus pada halaman Ubah → dialog → konfirmasi → notifikasi "Tidak dapat dihapus — Anda tidak dapat menghapus akun Anda sendiri."; tetap di halaman, akun tetap ada. |
| (b) Impor pengguna | Berkas dengan dua baris (akun pengimpor → Kasubbag; Admin lain → Kasubbag): "Impor selesai — 1 diperbarui · 1 bermasalah. Baris 2: Baris ini mengubah peran atau status aktif akun Anda sendiri, sehingga tidak diproses. …". Basis data: pengimpor tetap `admin` aktif; Admin lain menjadi `kasubbag`. Penolakan "Admin aktif terakhir" tidak dapat dipicu dari UI oleh Admin aktif (ia sendiri selalu terhitung); dibuktikan tes. |
| (c) Sandi di form Pengguna | Admin mengisi sandi akunnya sendiri: "Data berhasil disimpan", `/admin/users` dan `/admin/pengaturan` tetap terbuka (sebelum perbaikan: `POST 302` lalu `/admin/login`, lihat G-003 Batch 4). Admin mengubah sandi pengguna lain (konteks peramban kedua yang sudah masuk): Admin tetap masuk; sesi lama pengguna itu → `/admin/login`. |
| (d) Email di Pengaturan | Admin, Kasubbag, Gudang, Ketua Tim, dan Tim: email `disabled` dengan keterangan; muatan `peretas@luar.com` diubah lewat Livewire bersama nomor WA baru → "Pengaturan tersimpan" tanpa dialog; sesudah muat ulang email tetap, nomor WA tersimpan (juga di basis data). |
| (e) Katalog | `12345abcde`, 16 angka, dan 19 angka → "NIP pemohon harus terdiri atas angka dengan format yang benar." (tidak ada permintaan terbentuk); `19800101 201001 1 001` → "Permintaan berhasil diajukan", tersimpan `198001012010011001`. |
| (f) Aset Tetap | Ubah aset bertim: penempatan `disabled` dengan keterangan, `external_id` dan `synced_at` `disabled`; muatan diubah (tim lain, `HACK-XYZ`, 2030) bersama nama → "Data berhasil disimpan"; basis data: hanya nama berubah, penempatan (Statistik Sosial), `external_id` (`EXT-ASLI-001`), `synced_at`, dan riwayat (1 baris) tetap. Ubah aset tanpa penempatan: kolom penempatan aktif. Form Buat: penempatan aktif. |
| (g) BAST | Sahkan dengan dua klik berjarak 60 ms: satu notifikasi "BAST disahkan"; basis data: status `menunggu_konfirmasi`, riwayat aset +1 (mutasi, 1 baris untuk BAST itu), notifikasi BAST 1, penempatan berpindah sekali. |

Tidak ada `console.error` pada run yang dijadikan bukti; permintaan gagal hanya pembatalan (`ERR_ABORTED`) pada `chart.js`, logo, dan pembaruan Livewire yang tertimpa navigasi.

## B5.10 Perubahan perilaku

1. Pengguna tidak dapat menghapus akunnya sendiri (tunggal maupun massal), dan penghapusan yang menyisakan nol Admin aktif ditolak; hapus massal ditolak seluruhnya. Berlaku juga pada jalur langsung (model).
2. Impor Pengguna menolak baris (dilaporkan) yang mengubah peran atau status akun pengimpor, atau yang menurunkan atau menonaktifkan Admin aktif terakhir.
3. Admin yang mengganti sandi akunnya sendiri lewat form Pengguna tetap masuk.
4. Email pada Pengaturan hanya-baca untuk semua peran.
5. NIP Pemohon di Katalog (bila diisi) harus 18 angka; disimpan tanpa spasi.
6. Penempatan aset yang sudah ditempatkan tidak dapat diubah lewat form Ubah; ID Eksternal dan Waktu Sinkronisasi hanya-baca pada form Aset Tetap.
7. Sahkan dan Konfirmasi Penerimaan BAST ditolak (dengan notifikasi) bila status sudah berubah; pembuatan BAST menolak aset nonaktif di server.
8. Tidak berubah: hak akses, alur bisnis, notifikasi (termasuk WhatsApp), penomoran, isi dan tata letak PDF dan Excel, template impor, Nama Pemohon.

## B5.11 ID baru (tidak diperbaiki)

| ID | Kategori | Keparahan | Temuan | Bukti |
|---|---|---|---|---|
| **H-001** | Ketahanan | Rendah (laten) | Trait `DilindungiRiwayat` mendaftarkan pendengar `deleting` yang mengembalikan `true` bagi yang boleh dihapus. Pada peristiwa yang dijalankan dengan halt, itu menghentikan rantai: pendengar lain yang didaftarkan sesudahnya pada model yang sama (Barang, Tim, Aset, Kategori, Pengguna) tidak pernah dijalankan. Hari ini aman (G-001 didaftarkan lebih dulu pada `booting`), tetapi penjaga baru mana pun yang ditambahkan lewat `booted()` akan diam-diam tidak berlaku. | Probe: `delete()` mengembalikan `true` pada Admin aktif satu-satunya sebelum pendaftaran dipindah; `app/Models/Concerns/DilindungiRiwayat.php:22`. |
| **H-002** | Konsistensi | Rendah | Form Pengguna, Impor Pengguna, dan Pengaturan tidak memvalidasi format NIP (hanya `maxLength(30)` atau tanpa aturan), sedangkan Katalog kini menuntut 18 angka. NIP akun yang tersimpan dengan pemisah selain spasi (mis. titik) akan ditolak ketika terisi otomatis pada NIP Pemohon dan pemiliknya harus mengetik ulang. | `UserForm.php`, `ImporPengguna.php` (`'nip' => $ambil('nip') ?: null`), `KatalogBarang.php` (isi awal `auth()->user()->nip`). |
| **H-003** | Konsistensi data | Rendah | `NIP Pemohon` bernilai bebas pada permintaan **lama** tetap tampil apa adanya pada Rincian dan ekspor Riwayat (data lama tidak diubah sesuai instruksi). | `permintaan_barang.nip_pemohon`; `detail-permintaan.blade.php:169`. |

## B5.12 Tidak dapat diverifikasi

- **Peramban hanya pada MySQL sementara.** SQLite dicakup oleh suite dan skenario beku, bukan oleh peramban.
- **Konkurensi sungguhan** (dua koneksi paralel mengesahkan BAST yang sama, atau dua impor pengguna serentak): kunci baris BAST diterapkan tetapi hanya logikanya diuji; SQLite tidak mengenal kunci baris. Uji peramban dua klik berjarak 60 ms menghasilkan satu pemrosesan, tetapi tidak membuktikan perlombaan antarkoneksi.
- **Hapus massal lewat peramban** tidak dijalankan (dicakup `HapusAkunTerjagaTest` pada Livewire); hapus tunggal dijalankan di peramban.
- **Penolakan "Admin aktif terakhir" lewat UI** tidak dapat dipicu oleh Admin aktif (ia selalu terhitung); dibuktikan lewat tes dengan pelaku nonaktif atau non-Admin dan lewat jalur langsung.
- Keterangan koreksi G-004 pada bagian Batch 4 adalah perubahan dokumen; tidak ada kode notifikasi yang disentuh.


## B6. Batch 6 — kosmetik: bahasa, tautan dasbor, aset pihak ketiga, konsistensi istilah, kode tak terpakai (22 September 2026)

ID yang dikerjakan: **A-009, A-013, A-019, A-023, A-025, A-026**. Batch ini murni kosmetik: tidak ada perubahan alur bisnis, status, validasi, hak akses, struktur data, nilai enum, nama kolom/kelas, atau kunci internal — hanya label yang tampil, satu kondisi tampil/sembunyi tautan (A-013), dan penghapusan kode/berkas yang terbukti tidak terpakai (A-025, A-026). Git tetap hanya-baca (`status`/`diff`/`log`); tidak ada `commit`. Skill yang dipakai: **`browser-automation`** (global) untuk seluruh tangkapan peramban headless (sebelum/sesudah, uji validasi, uji tautan Kondisi Stok, uji rute lama); tidak ada skill di `.claude/skills` proyek yang relevan untuk batch ini.

### B6.1 Ringkasan per ID

| ID | Status | Berkas utama | Bukti |
|---|---|---|---|
| **A-009** — pesan validasi berbahasa Indonesia | Diperbaiki | `lang/id/validation.php` (baru, 135 kunci + `attributes` + `custom`) | `PesanValidasiIndonesiaTest` (7 lolos); parity kunci diuji terhadap `vendor/laravel/framework/.../lang/en/validation.php`; peramban (a). |
| **A-013** — tautan Kondisi Stok disembunyikan bagi Petugas Gudang | Diperbaiki | `KondisiStok.php`, `kondisi-stok.blade.php` | `KondisiStokTautanTest` (4 lolos); tangkapan peramban (diulang dengan cakupan DOM dibatasi ke widget, lihat B6.15): Kasubbag tetap **16 tautan di dalam widget** (15 kategori + "Lihat semua", persis sama dengan temuan audit), semuanya 200; Gudang **0 tautan** (dulu 16, semua 403); Admin tidak pernah melihat widget ini sama sekali (`canView()` dibatasi ke `kasubbag`/`petugas_gudang`, tidak diubah Batch 6, konsisten dengan matriks-akses Tabel 5). Tabel 6 matriks-akses.md (M-1) diperbarui. |
| **A-019** — avatar dan font tanpa pihak ketiga | Diperbaiki sebagian (lihat E2) | `Pengaturan.php`, `aksi-bilah-atas.blade.php`, `pengaturan.blade.php`, `theme.css`, `font-inter-lokal.blade.php` (baru), `fonts.blade.php`, `welcome.blade.php`, `AdminPanelProvider.php` | `AvatarFontLokalTest` (7 lolos); tangkapan peramban: `ui-avatars.com` hilang total dari kedua fase; Inter 7 deklarasi `@font-face` lokal per halaman. |
| **A-023** — konsistensi istilah dan format tanggal | Diperbaiki (1 pengecualian disengaja, lihat catatan) | `PermintaanBarang.php`, `detail-permintaan.blade.php`, `muka.php`, `PerluTindakan.php`, `riwayat.blade.php`, 6 kelas tabel, `status-sinkronisasi.blade.php` | `IstilahKonsistenTest` (10 lolos); F.5 di bawah. |
| **A-025** — kode awal starter yang tak terpakai | Diperbaiki | `app.js`, `bootstrap.js` (dihapus), `vite.config.js`, `package.json`, `app.css`, `theme.css`, `AdminPanelProvider.php` | G.7 di bawah; `npm install` (axios −1 paket langsung, 27 paket transitif, −359 baris `package-lock.json`); `npm run build` sukses. |
| **A-026** — rute lama tanpa tampilan mati | Diperbaiki | `resources/views/filament/pages/detail-permintaan.blade.php` (dihapus); rute dan `DetailPermintaanBarang.php` **tidak diubah** | `RutePermintaanLamaTest` (4 lolos); eksperimen "tampilan diracuni" membuktikan `$view` tak pernah dirender sebelum dihapus. |

### B6.2 A-009 — pesan validasi Indonesia

`lang/id/validation.php` diterjemahkan penuh (135 kunci, termasuk aturan ukuran/pola dan pesan khusus tiap tipe data), dengan peta `attributes` untuk nama kolom yang sering tampil (nama, password, password_confirmation, current_password, nip → NIP, no_hp → nomor WhatsApp, role → peran, tim_id → tim kerja, status_aktif → status aktif, username, email) dan blok `custom` untuk pesan yang sudah spesifik per kolom. Diuji: setiap kunci bawaan Laravel (`lang/en/validation.php` milik vendor) punya padanan Indonesia dengan penanda tempat (`:attribute`, `:min`, dst.) yang sama persis — mencegah galat diam-diam kembali ke bahasa Inggris. Peramban (a): mengisi form Tim Kerja tanpa nama → "Kolom nama Tim wajib diisi." (validasi HTML5 dimatikan lebih dulu dengan `noValidate` agar pesan server benar-benar diuji).

### B6.3 A-013 — tautan Kondisi Stok bagi Petugas Gudang

Widget `KondisiStok` mendapat `getBolehMenautkanProperty()`: `KatalogBarang::canAccess() || BarangPersediaanResource::canAccess()`. Bila `false` (Petugas Gudang), judul panel dan setiap baris kategori dirender sebagai tag `<div>` biasa (bukan `<a>`), memakai tag dinamis Blade (`<{{ $tag }} …>`); angka dan urutan kategori tidak berubah, hanya elemen pembungkusnya. Sebelumnya seluruh 16 tautan itu berujung 403 di server — kini tidak ada tautan sama sekali untuk peran itu, konsisten dengan seluruh dasbor lain yang menyembunyikan, bukan menampilkan-lalu-menolak.

### B6.4 A-019 — avatar dan font lokal

**Avatar.** `Pengaturan::inisial()` menggantikan `Filament::getUserAvatarUrl()` (yang memanggil `ui-avatars.com`): logika sama persis dengan `UiAvatarsProvider` bawaan Filament (huruf pertama tiap kata, tanda baca awal kata dilewati, maksimum dua huruf, huruf besar), tetapi murni PHP/CSS. Dipakai di pemicu dropdown bilah atas (`<span class="simpbi-avatar-inisial">`) dan kartu Ringkasan Akun (varian `--besar`, 3rem). Gaya CSS baru meniru `.simpbi-profil-avatar` (lingkaran navy, teks putih).

**Font.** `resources/views/partials/font-inter-lokal.blade.php` (baru) mendeklarasikan ulang 7 `@font-face` untuk keluarga **"Inter"** (nama dipertahankan sama agar Tailwind/Filament tidak perlu diubah) menunjuk berkas `.woff2` lokal yang sudah ada di `public/fonts/filament/filament/inter/` (bawaan Filament, sebelumnya tidak dipakai karena nama keluarganya "Inter Variable"). `AdminPanelProvider` beralih dari `->font('Inter')` (memakai `BunnyFontProvider` bawaan, memicu request tambahan ke `fonts.bunny.net`) ke `->font('Inter', provider: LocalFontProvider::class)` yang tidak memuat apa pun.

### B6.5 A-023 — konsistensi istilah dan format tanggal

Keputusan istilah: peran **"Kasubbag Umum"**, tahap ke-3 **"Persetujuan akhir Kasubbag"**, tombol **"Ekspor"** (bukan "Export"), tanggal pada tabel/daftar **d-m-Y**, tanggal dalam teks/dialog **d F Y** (nama bulan Indonesia). Nilai `STATUS`/`STATUS_RINGKAS` di `PermintaanBarang.php`, `lang/id/muka.php`, dan `PerluTindakan.php` diselaraskan; format tanggal diubah di `AsetTetapTimSaya`, `StokMasuk`, dan lima kelas tabel (`AsetTetapsTable`, `BastMutasiAsetsTable`, `KategorisTable`, `TimsTable`, `UsersTable`) dari `'d M Y'` (singkatan bulan Inggris, mis. "22 Sep 2026") ke `'d-m-Y'`; `status-sinkronisasi.blade.php` dari `format('d M Y, H:i')` ke `translatedFormat('d F Y, H:i')`.

**Pengecualian yang disengaja (dampak pada ekspor beku).** Label status `ditolak_kasubbag` awalnya diubah dari `"Ditolak Kasubbag"` menjadi `"Ditolak Kasubbag Umum"`, tetapi label ini mengalir langsung ke kolom Status pada ekspor Riwayat (PDF dan Excel beku) dan pengujian siklus-penuh menunjukkan pergeseran tata letak kolom PDF (lihat B6.7). Karena aturan kerja melarang perubahan struktur/tata letak keluaran beku, label ini **dikembalikan ke `"Ditolak Kasubbag"`** (tanpa "Umum") — satu-satunya tempat istilah "Kasubbag Umum" belum sepenuhnya seragam. Ini dicatat sebagai **I-001** di bawah, bukan diperbaiki sepihak, karena mengubah lebar kolom PDF/Excel beku memerlukan persetujuan pemilik di luar cakupan kosmetik batch ini.

### B6.6 F.5 — daftar kemunculan istilah, sebelum vs sesudah (tangkapan peramban)

Diambil dari dua tangkapan penuh (`b6-tangkap-sebelum.json` / `b6-tangkap-sesudah.json`, skrip `v6-tangkap.mjs`, viewport 1440×900) yang menghitung kemunculan setiap frasa pada teks halaman untuk lima peran × halaman relevan. Hanya baris yang berubah ditampilkan; baris yang tidak tercantum berarti angka sebelum = sesudah (termasuk seluruh halaman yang tidak disinggung A-023).

| Halaman | Frasa | Sebelum | Sesudah |
|---|---|---|---|
| Muka (`/`) | "Persetujuan Kasubbag" → "Persetujuan akhir Kasubbag" | 1 / 0 | 0 / 1 |
| Admin — `/admin/permintaan-barangs` | "Kasubbag Umum" | 0 | 1 |
| Kasubbag — `/admin/permintaan-barangs` | "Kasubbag Umum" | 0 | 1 |
| Gudang — `/admin/permintaan-barangs` | "Kasubbag Umum" | 0 | 1 |
| Ketua Tim — `/admin/permintaan-barangs` | "Kasubbag Umum" | 0 | 1 |
| Tim — `/admin/permintaan-barangs` | "Kasubbag Umum" | 0 | 1 |
| Kasubbag — `/admin/riwayat` | "Export" → "Ekspor" | 1 / 1 | 0 / 2 |
| Kasubbag — `/admin/bast-mutasi-asets` | tanggal tabel | `22 Sep 2026` | `22-09-2026` |
| Gudang — `/admin/stok-masuk` | tanggal tabel | `01 Jan 2026` | `01-01-2026` |
| Ketua Tim / Tim — `/admin/aset-tetap-tim-saya` | tanggal tabel | `22 Mar 2026`, `22 Sep 2026` | `22-03-2026`, `22-09-2026` |
| Dialog Rincian (PB-B6-0001) | judul status | `MENUNGGU PERSETUJUAN KASUBBAG` | `MENUNGGU PERSETUJUAN AKHIR KASUBBAG` |
| Pengaturan — semua peran | label peran (`labelPeran`) | tidak berubah (mis. "Kasubbag Umum" sudah ada sejak batch sebelumnya) | tidak berubah |

Tidak ditemukan sisa "Persetujuan Kasubbag" (tanpa "akhir") atau "Export" (bahasa Inggris) pada seluruh halaman yang ditangkap sesudah perubahan. Tidak ada gulir mendatar baru akibat label yang memanjang (`/admin/permintaan-barangs`, viewport 1440 dan 1366: `{halaman:0, tabel:0}` sebelum maupun sesudah, kelima peran).

### B6.7 Keluaran beku — verifikasi dampak label (aturan B, pengecualian sempit A-023)

Dua skenario dijalankan **sebelum** dan **sesudah** di SQLite (memori) dan MySQL 8.4.3 sementara (`simpbi_b6_beku`):

**Skenario standar** (`BekuB6Test`; siklus permintaan → BAST → Kartu Kendali → ekspor Riwayat → dialog Rincian, 14 berkas dibandingkan dengan `pdftotext -layout` / PhpSpreadsheet):

| Driver | Berkas dibandingkan | Berbeda |
|---|---|---|
| SQLite | 14 | **1** — `dialog-rincian-kedaluwarsa.txt`: teks halaman "tombol Export" → "tombol Ekspor" (label saja, tidak ada perubahan lain pada baris itu) |
| MySQL sementara | 14 | **1** — sama persis |

**Skenario label** (`BekuLabelB6Test`, dibuat khusus untuk mengukur dampak `ditolak_kasubbag`/`menunggu_kasubbag` pada ekspor Riwayat): permintaan dengan status `ditolak_kasubbag` dan `menunggu_kasubbag` diekspor ke PDF, Excel, dan diambil teks halaman daftar aktif.

| Driver | riwayat-pdf.txt | riwayat-xlsx.json | daftar-permintaan-aktif.txt |
|---|---|---|---|
| SQLite | **identik** | **identik** | berbeda — "Menunggu Kasubbag" → "Menunggu Kasubbag Umum" (label, bukan struktur) |
| MySQL sementara | **identik** | **identik** | sama persis |

Dengan label `ditolak_kasubbag` dikembalikan ke `"Ditolak Kasubbag"` (I-001), **ekspor Riwayat PDF dan Excel beku byte-identik sebelum/sesudah pada kedua driver** — pengecualian sempit A-023 (label teks pada tampilan langsung, bukan pada dokumen beku) terpenuhi tanpa syarat.

### B6.8 G.7 — verifikasi ulang A-025 (kode starter tak terpakai)

| # | Item | Verifikasi setelah dihapus/diubah |
|---|---|---|
| 1 | `resources/js/app.js` | Dihapus. `grep` di seluruh `resources/views` dan `app/`: tidak ada `@vite(['resources/js/app.js'])` atau rujukan lain. |
| 2 | `resources/js/bootstrap.js` | Dihapus. Satu-satunya pengimpornya adalah `app.js` (sudah dihapus); tidak ada rujukan lain. |
| 3 | Entri Vite `resources/js/app.js` | Dihapus dari `input` di `vite.config.js`. `public/build/manifest.json` sesudah `npm run build` hanya berisi `resources/css/app.css` dan `resources/css/filament/admin/theme.css` — tidak ada berkas `app-*.js` yang dihasilkan lagi. |
| 4 | `axios` | Dihapus dari `package.json` (satu-satunya pemakainya adalah `bootstrap.js`, sudah dihapus). `npm install` menghasilkan 27 paket transitif berkurang, `package-lock.json` −359 baris. `grep -rl axios` pada `resources/` dan `app/`: kosong. |
| 5 | `@source "../**/*.vue"` (Tailwind) | Dihapus dari `resources/css/app.css`. `find resources -iname "*.vue"`: tidak ada berkas `.vue` di repositori. |
| 6 | Kelas tint `.simpbi-tint-icon--blue` / `--orange` (terang & gelap) | Dihapus dari `theme.css`. `grep` pada `resources/` dan `app/`: tidak ada rujukan tersisa di markup maupun CSS. Kelas `--green` dan `--slate` (masih dipakai) tidak disentuh. |
| 7 | `discoverPages()` ganda pada `AdminPanelProvider` | Panggilan kedua (identik, hanya beda posisi) dihapus; komentarnya dipindah ke panggilan pertama. Dibuktikan **tanpa perubahan urutan pendaftaran**: `b6-urutan-sebelum.txt` vs `b6-urutan-sesudah.txt` — daftar kelas Halaman, Resource, dan urutan menu (MD5 `ddf6f629b24a33ac5ee5d69925c85814`) identik untuk keempat akun yang diuji sebelum dan sesudah. |

### B6.9 E2 — investigasi font pihak ketiga

| Keluarga | Sebelum | Sesudah | Keterangan |
|---|---|---|---|
| **Inter** | `fonts.bunny.net/css?family=inter:...` (dua kali: partial fonts dan `->font('Inter')` panel) | **Lokal** — 7 `@font-face` (`cyrillic-ext`, `cyrillic`, `greek-ext`, `greek`, `vietnamese`, `latin-ext`, `latin`) menunjuk `public/fonts/filament/filament/inter/*.woff2` (berkas bawaan Filament, sudah ada di repo) | Nama keluarga dipertahankan `"Inter"` (bukan "Inter Variable" bawaan Filament) agar seluruh CSS yang sudah memakai `font-family: Inter` tidak perlu diubah. |
| **Plus Jakarta Sans** | `fonts.bunny.net/css?family=...plus-jakarta-sans:600,700,800...` | **Masih dari fonts.bunny.net** | Tidak ada berkas lokal untuk keluarga ini di repo maupun paket vendor manapun; instruksi kerja melarang mengunduh aset baru. Perlu berkas font dari pemilik untuk dilokalkan. |
| **Space Grotesk** | `fonts.bunny.net/css?family=...space-grotesk:500,700` | **Masih dari fonts.bunny.net** | Sama seperti di atas. |

Total host eksternal pada tangkapan peramban: **sebelum** = `fonts.bunny.net/css`, `ui-avatars.com/api`; **sesudah** = `fonts.bunny.net/css` — satu-satunya host pihak ketiga yang tersisa, untuk dua keluarga font yang belum bisa dilokalkan.

### B6.10 A-026 — rute lama, tampilan mati

Rute `GET /admin/permintaan-barangs/{record}` dan kelas halamannya (`DetailPermintaanBarang.php`) **tidak diubah** — keduanya tetap ada agar tautan lama (mis. pesan WhatsApp lama yang menunjuk ke URL ini) tetap mendarat pada pop-up Rincian yang benar. Sebelum menghapus, dibuktikan dengan eksperimen "tampilan diracuni": isi `filament.pages.detail-permintaan` diganti teks yang sengaja menyebabkan galat bila dirender — permintaan HTTP ke rute itu tetap mengembalikan 302 tanpa galat, membuktikan `mount()` selalu memanggil `redirect()` sebelum Livewire sempat merender `$view`. Berkas tampilannya kemudian dihapus. `DetailPermintaanBarang.php` masih mendeklarasikan `protected string $view = 'filament.pages.detail-permintaan';` yang kini menunjuk berkas tak ada — dicatat sebagai **I-002** (tidak berbahaya selama `mount()` tidak diubah, tetapi berpotensi membingungkan atau memicu galat bila suatu saat perilaku redirect itu dihapus tanpa menyadari `$view` masih dirujuk).

### B6.11 Uji baru dan uji mundur

Lima berkas uji baru, 32 uji, tidak ada uji lama yang diubah:

| Berkas | Jumlah uji |
|---|---|
| `tests/Feature/PesanValidasiIndonesiaTest.php` | 7 |
| `tests/Feature/KondisiStokTautanTest.php` | 4 |
| `tests/Feature/AvatarFontLokalTest.php` | 7 |
| `tests/Feature/IstilahKonsistenTest.php` | 10 |
| `tests/Feature/RutePermintaanLamaTest.php` | 4 |

**Uji mundur.** Seluruh 26 berkas sumber yang diubah dikembalikan sementara ke versi sebelum Batch 6 (dari cadangan `asli/`), dua berkas baru (`lang/id/validation.php`, `partials/font-inter-lokal.blade.php`) disingkirkan, dan tampilan `detail-permintaan.blade.php` yang dihapus dikembalikan — lalu ke-32 uji baru dijalankan di SQLite terhadap kode lama itu:

**22 gagal, 10 lolos (90 assersi), durasi 13,26 detik.**

Yang lolos pada kode lama (dan alasannya, membuktikan uji itu memang tidak menguji perubahan Batch 6):
- **`RutePermintaanLamaTest` (4/4)** — menguji perilaku pengalihan rute (`mount()`→`redirect()`), yang **tidak berubah oleh A-026**; A-026 hanya menghapus tampilan yang memang tidak pernah dirender, jadi keberadaannya tidak memengaruhi hasil tes ini baik sebelum maupun sesudah.
- **`KondisiStokTautanTest` (3/4)** — tiga uji yang memeriksa perilaku Kasubbag/Admin (tautan tetap tampil) lolos karena perilaku itu memang sudah benar sebelum Batch 6; hanya uji "Petugas Gudang tidak lagi melihat tautan" yang gagal pada kode lama (sesuai harapan — itulah yang diperbaiki A-013).
- **`IstilahKonsistenTest` (3/10)**: (1) label peran "Kasubbag Umum" pada dasbor/Pengaturan sudah benar sejak batch sebelumnya, tidak disentuh A-023; (2) label tahap ke-3 di halaman Pengaturan (`Pengaturan.php:105`, `'batas_kasubbag_jam' => 'Persetujuan akhir Kasubbag'`) **sudah** memakai kata "akhir" sebelum Batch 6 — justru sumber acuan yang membuat A-023 menyelaraskan tempat lain; (3) format tanggal teks pada dialog Rincian (`d F Y`) sudah benar sebelum Batch 6, hanya format tanggal **tabel** yang perlu diperbaiki.

Sesudah uji mundur, seluruh berkas dikembalikan ke versi Batch 6 dan **diverifikasi sha256 cocok 100% untuk ke-28 berkas** yang sempat dikembalikan (lihat `uji-mundur/` dan `manifest.md` pada cadangan). Suite penuh SQLite dijalankan ulang sesudah pemulihan: **695 lolos (5150 assersi)** — sama dengan hasil akhir sebelum uji mundur.

### B6.12 Hasil uji penuh dan lingkungan

| Tahap | Driver | Hasil |
|---|---|---|
| Baseline (sebelum perubahan) | SQLite | 663 lolos, 4825 assersi |
| Baseline (sebelum perubahan) | MySQL 8.4.3 sementara | 663 lolos, 4825 assersi |
| Akhir (sesudah perubahan) | SQLite | 695 lolos, 5150 assersi |
| Akhir (sesudah perubahan) | MySQL 8.4.3 sementara | 695 lolos, 5150 assersi |
| Sesudah uji mundur (pemulihan diverifikasi) | SQLite | 695 lolos, 5150 assersi |

Verifikasi peramban dan `npm run build` dijalankan lewat server isolasi (`LARAVEL_STORAGE_PATH` ke salinan di scratchpad, `php -d variables_order=EGPCS`, dokroot junction ke `public/` repo). `database/database.sqlite` tidak dibuka sepanjang batch ini; metadata sebelum dan sesudah identik (`LastWriteTime` 21/9/2026 21:44:52, ukuran 331.776 B). MySQL Laragon (port 3306) tidak tersentuh. `storage/framework` repo: 163 berkas sebelum dan sesudah (tidak berubah).

### B6.13 ID baru (tidak diperbaiki, dicatat untuk pemilik)

| ID | Kategori | Keparahan | Temuan | Bukti |
|---|---|---|---|---|
| **I-001** | Konsistensi | Rendah | Label status `ditolak_kasubbag` masih `"Ditolak Kasubbag"` (tanpa "Umum"), berbeda dari peran "Kasubbag Umum" yang dipakai di tempat lain sejak A-023. Sengaja tidak diseragamkan karena mengalir ke kolom Status pada ekspor Riwayat (PDF/Excel) beku dan mengubah lebar kolom PDF (lihat B6.7) — perubahan struktur/tata letak keluaran beku di luar cakupan batch kosmetik ini. | `app/Models/PermintaanBarang.php` (`STATUS['ditolak_kasubbag']`); `BekuLabelB6Test`. |
| **I-002** | Kebersihan kode | Rendah (laten) | `App\Filament\Resources\PermintaanBarangs\Pages\DetailPermintaanBarang` masih mendeklarasikan `protected string $view = 'filament.pages.detail-permintaan'` yang kini menunjuk berkas yang sudah dihapus (A-026). Aman selama `mount()` selalu `redirect()` sebelum Livewire merender `$view`; berisiko bila perilaku itu berubah tanpa menyadari properti ini masih ada. | `app/Filament/Resources/PermintaanBarangs/Pages/DetailPermintaanBarang.php`. |
| **I-003** | Aset pihak ketiga | Rendah | Keluarga font **Plus Jakarta Sans** dan **Space Grotesk** masih dimuat dari `fonts.bunny.net`; tidak ada berkas lokal untuk keduanya di repo atau paket vendor, dan instruksi kerja melarang mengunduh aset baru. Memerlukan berkas font dari pemilik untuk dilokalkan sepenuhnya (lihat B6.9). | `resources/views/filament/fonts.blade.php`, `resources/views/welcome.blade.php`. |

### B6.14 Tidak dapat diverifikasi / catatan

- Perbandingan `npm run build` dan konsol peramban sebelum/sesudah sempat terganggu oleh junction statis (`public-b3`) yang hilang di lingkungan verifikasi scratchpad (bukan masalah pada kode aplikasi) — dibuat ulang, lalu tangkapan diulang; hasil akhir 0 galat konsol dan 0 permintaan lokal gagal pada kedua fase.
- Dua keluarga font (I-003) tidak dapat diverifikasi sebagai "sepenuhnya lokal" karena berkasnya memang belum ada; ini bukan kegagalan verifikasi, melainkan keterbatasan bahan (tidak boleh mengunduh).


### B6.15 Klarifikasi pasca-laporan (pertanyaan pemilik, 22 September 2026)

Tiga hal berikut diperiksa ulang dengan bukti eksekusi nyata sesudah laporan B6 pertama diserahkan; satu di antaranya memperbaiki kekeliruan penulisan pada B6.1.

**1. Lokasi Plus Jakarta Sans dan Space Grotesk (sudah ada sebelum Batch 6).** Kedua keluarga ini **bukan temuan baru A-019**, melainkan sudah dipakai di kode sejak sebelum Batch 6 dimulai — dibuktikan dengan membandingkan berkas cadangan `asli/` (versi sebelum Batch 6) terhadap versi sekarang: definisi dan pemakaiannya identik, tidak disentuh oleh perubahan Batch 6 sama sekali.

- `resources/css/filament/admin/theme.css:629-630` — variabel `--simpbi-huruf-judul: 'Plus Jakarta Sans', …` dan `--simpbi-huruf-wordmark: 'Space Grotesk', …`. Dipakai pada:
  - `theme.css:673` — `.fi-topbar .fi-logo .simpbi-wordmark` (wordmark "SIMPBI" di bilah atas, **tampil di setiap halaman panel admin untuk seluruh peran**).
  - `theme.css:1310` — `.fi-header-heading` (judul setiap halaman Filament, seluruh peran/halaman).
  - `theme.css:1319` — `.fi-section-header-heading` (judul kartu/section di dalam halaman, seluruh peran/halaman).
- `resources/views/welcome.blade.php:39-40` — variabel `--font-display` (Plus Jakarta Sans) dan `--font-wordmark` (Space Grotesk), dipakai pada halaman muka publik (`/`): tautan navigasi (baris 395, kelas `.font-display`), judul hero "SIMPBI" (baris 461, `.font-wordmark`), subjudul (baris 470, `.font-display`), dan angka statistik (baris 535, `.font-wordmark`).

Kedua keluarga ini ditemukan (bukan dibuat) selama investigasi A-019, ketika ditelusuri mengapa tautan `fonts.bunny.net` memuat tiga keluarga (`inter`, `plus-jakarta-sans`, `space-grotesk`) — hasil penelusuran memastikan ketiganya benar-benar dipakai (bukan sisa kode), dan hanya Inter yang punya berkas lokal siap pakai (bawaan Filament) untuk dilokalkan tanpa mengunduh apa pun.

**2. A-013 — koreksi kekeliruan penulisan "17 tautan" dan konfirmasi Admin.**

*(a) Kenapa 17, bukan 16?* Kekeliruan ada pada skrip pengukuran saya, bukan pada perilaku aplikasi. Query awal (`v6-tangkap.mjs`) menghitung **seluruh** `<a>` pada halaman `/admin` yang hrefnya mengandung `barang-persediaans`/`katalog-barang` — ini juga menjaring tautan menu navigasi sisi kiri ("Persediaan › Barang Persediaan", yang untuk Kasubbag menuju URL tanpa filter, sama persis dengan tautan "Lihat semua" milik widget). Diperiksa ulang dengan query yang dibatasi khusus pada kontainer widget Kondisi Stok (menelusuri DOM dari judul "Kondisi Stok per Kategori" ke atas sampai menemukan kontainer dengan banyak tautan):

| Peran | Tautan **di dalam widget** | Tautan menu navigasi (di luar widget) | Total halaman (metode lama, keliru) |
|---|---|---|---|
| Kasubbag | **16** (15 kategori + 1 "Lihat semua") — persis sama dengan audit | 1 (menu "Barang Persediaan", URL sama dengan "Lihat semua") | 17 baris mentah / 16 unik |
| Petugas Gudang | **0** (sesudah A-013; dulu 16, semua 403) | 0 (Gudang tidak berhak, tidak ada di menu) | 0 |

Basis data verifikasi dicek langsung: `SELECT COUNT(*) FROM kategori WHERE tipe='persediaan'` → **15**, sama dengan `KatalogBarangSeeder.php` (15 baris `'tipe' => 'persediaan'`). Jadi 15 kategori + 1 "Lihat semua" = **16 tautan widget**, sama persis dengan audit dan prompt Batch 6. Dokumen (B6.1 dan matriks-akses M-1) sudah diperbaiki untuk tidak lagi menyebut "17".

*(b) Apakah Admin melihat widget ini?* **Tidak — sejak sebelum Batch 6, tidak diubah.** `KondisiStok.php`: `public static function canView(): bool { return in_array(auth()->user()?->role, ['kasubbag', 'petugas_gudang']); }` — baris ini tidak disentuh Batch 6 sama sekali. Dibuktikan dengan tangkapan peramban baru: pada halaman `/admin` milik akun Admin, teks "Kondisi Stok per Kategori" **tidak ditemukan sama sekali** di seluruh isi halaman (`punyaTeksWidget: false`, `headingDitemukan: false`), sedangkan pada akun Kasubbag dan Gudang teks itu selalu ada. Dua tautan `barang-persediaans` yang terdeteksi pada halaman Admin berasal dari menu navigasi (muncul dua kali karena markup sisi bilah desktop dan drawer seluler Filament), bukan dari widget. Kalimat "Kasubbag/Admin tetap 17 tautan" pada laporan pertama adalah **kesalahan penulisan saya** (mencampurkan hasil Kasubbag dengan kata "Admin" tanpa Admin benar-benar diukur) — sudah diperbaiki di B6.1 dan matriks-akses M-1.

**3. I-002 — bukti eksekusi HTTP nyata (bukan pembacaan kode) untuk rute lama.**

Dijalankan lewat server sungguhan (bukan PHPUnit) pada **kedua driver**, dengan permintaan `PB-B6-0001` (milik tim `Statistik Sosial`, `tim_id=2`) dan tujuh akun berbeda, memakai `page.request.get(..., { maxRedirects: 0 })` lewat peramban headless agar status HTTP mentah terlihat apa adanya:

| Skenario | MySQL 8.4.3 sementara | SQLite |
|---|---|---|
| Tamu (tanpa sesi) | 302 → `/admin/login` | 302 → `/admin/login` |
| Admin (lintas tim) | 302 → pop-up Rincian | 302 → pop-up Rincian |
| Kasubbag (lintas tim) | 302 → pop-up Rincian | 302 → pop-up Rincian |
| Petugas Gudang (lintas tim) | 302 → pop-up Rincian | 302 → pop-up Rincian |
| Ketua Tim **tim sendiri** (`tim_id=2`) | 302 → pop-up Rincian | 302 → pop-up Rincian |
| Tim **tim sendiri** (`tim_id=2`) | 302 → pop-up Rincian | 302 → pop-up Rincian |
| Ketua Tim **tim lain** (`tim_id=1`) | **404** | **404** |
| Tim **tim lain** (`tim_id=1`, akun baru dibuat khusus di basis data verifikasi sementara untuk melengkapi skenario) | **404** | **404** |
| ID tidak ada (`999999`) | **404** | **404** |
| Permintaan yang sengaja diputus (dibatalkan paksa 5 ms sesudah dikirim), lalu **diulang** pada permintaan yang sama | terputus di sisi klien seperti diharapkan; permintaan ulang → **302** normal, server tetap sehat | terputus di sisi klien seperti diharapkan; permintaan ulang → **302** normal, server tetap sehat |

**Tidak ada status 500 pada skenario mana pun, di kedua driver**, termasuk sesudah permintaan yang terputus di tengah jalan — server memproses permintaan berikutnya secara normal. Ini menguatkan (bukan menggantikan) bukti kode "tampilan diracuni" yang sudah ada: `mount()` selalu memanggil `redirect()` sebelum Livewire sempat merender `$view` yang sudah dihapus, baik pada jalur normal maupun pada permintaan yang terputus. I-002 tetap tercatat sebagai catatan kebersihan kode (properti `$view` yang menunjuk berkas tak ada), bukan sebagai risiko keandalan yang terbukti — status ini tidak berubah, hanya sekarang didukung bukti eksekusi langsung, bukan hanya pembacaan kode.

Lingkungan: MySQL dipakai instance sementara yang sama (`simpbi_b6_verif`, port 3399); SQLite dipakai basis data file baru (`verif-i002.sqlite`, migrate+seed penuh dari nol) yang disajikan lewat server PHP terpisah di port 8127 — keduanya di luar basis data kerja dan di luar Laragon, dimatikan bersih sesudah pengujian selesai. `database/database.sqlite` (basis data kerja) tetap tidak tersentuh (metadata sebelum = sesudah pengecekan ini).
