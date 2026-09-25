# Regression Testing (Retest-All Kumulatif) — SIMPBI

Versi sistem yang diuji: commit `8449a47c625bd6e1b185bd43bef9eb6e30320349` (branch `main`) ditambah empat berkas tes baru hasil White-Box Testing (`tests/Feature/JalurBasisModul1Test.php` s.d. `JalurBasisModul4Test.php`). Kode aplikasi tidak diubah.

## 1. Lingkungan

| Butir | Keadaan |
|---|---|
| PHP / PHPUnit / Laravel | PHP 8.3.33, PHPUnit 11.5.56, Laravel 12.69.0 |
| SQLite | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| MySQL | Instans **sementara** MySQL 8.4.3 dari `E:\laragon\laragon\bin\mysql\mysql-8.4.3-winx64-utuh\bin\`, datadir baru di folder scratchpad sesi (di luar repositori dan di luar folder Laragon), `127.0.0.1:3399`, basis data `simpbi_uji`, `innodb_buffer_pool_size=64M`. Layanan MySQL Laragon (3306) tidak dijalankan, dihentikan, atau diubah. |
| Konfigurasi | Satu berkas konfigurasi PHPUnit per langkah per driver (di luar repositori), satu *testsuite* per modul, sehingga hasil dapat dipilah per modul dari berkas JUnit. Variabel basis data ditulis dengan `force="true"`. |
| Storage | Setiap driver memakai salinan `storage/` di scratchpad (`LARAVEL_STORAGE_PATH`). |
| Basis data kerja | `database/database.sqlite` — 2f110b33ce188b9cc5657d7a0f872a79 *database/database.sqlite (sebelum); 2f110b33ce188b9cc5657d7a0f872a79 *database/database.sqlite (sesudah). |
| Urutan | Seluruh run dijalankan berurutan (tidak paralel). |

DB yang dicetak sebelum tiap run (dikutip dari log run):

```
awal-mysql       DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 02:59:05  selesai 2026-09-25 03:03:32  exit 0
langkah1-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 03:03:32  selesai 2026-09-25 03:04:24  exit 1
langkah2-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 03:04:25  selesai 2026-09-25 03:06:41  exit 1
langkah3-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 03:06:42  selesai 2026-09-25 03:10:02  exit 1
langkah4-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 03:10:02  selesai 2026-09-25 03:13:45  exit 1
langkah1-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 03:13:45  selesai 2026-09-25 03:14:47  exit 1
langkah2-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 03:14:48  selesai 2026-09-25 03:17:24  exit 1
langkah3-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 03:17:24  selesai 2026-09-25 03:21:10  exit 1
langkah4-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 03:21:11  selesai 2026-09-25 03:25:28  exit 1
```

## 2. Pemetaan berkas tes ke modul

Pemetaan 74 berkas mengikuti pemetaan terdahulu (dibaca dari kelas aplikasi yang diimpor dan isi metode). Lima berkas yang lahir pada commit `8449a47` dipetakan dengan cara yang sama: `NupUnikAsetTetapTest` (form Aset Tetap) → Modul 1; `AksesBuatBastTest` dan `PopUpBastTanpaKetikNipTest` (pop-up Buat BAST, Sahkan, Konfirmasi) → Modul 3; `TandaiDibacaLoncengTest` (lonceng notifikasi) dan `VerifikasiNotifikasiMutasiAsetTest` (penerima notifikasi tiap status, NS-12 s.d. NS-18) → Modul 4. Empat berkas tes baru dipetakan ke modul increment-nya.

| Modul | Berkas | Kasus (SQLite) | Assertion (SQLite) |
|---|---|---|---|
| 1 — Pengguna & Data Induk | 31 | 278 | 1611 |
| 2 — Permintaan Barang | 21 | 204 | 2706 |
| 3 — Mutasi Aset | 12 | 111 | 513 |
| 4 — Dashboard & Monitoring | 19 | 212 | 795 |
| **Jumlah** | **83** | **804** | **5624** |

**Modul 1 — Pengguna & Data Induk:** `AdminTidakMengunciDiriTest` (10), `AksesPanelTest` (5), `EmailTerkunciPengaturanTest` (4), `ExampleTest` (1), `FormTimKerjaTest` (13), `GantiSandiSesiTest` (9), `HapusAkunTerjagaTest` (10), `IlustrasiAlurMasukTest` (3), `ImporPenggunaPenjagaAdminTest` (7), `ImporPenggunaTest` (17), `ImporTimKerjaTest` (11), `IndikatorCapsLockMasukTest` (4), `IstilahTimKerjaTest` (4), `KodeBarangBaruStokMasukTest` (4), `KontakBantuanMasukTest` (6), `LengkapiAkunTest` (11), `NamaNipTerkunciPengaturanTest` (6), `PemeliharaanDataTest` (11), `PenempatanAsetFormTest` (10), `PengaturanTest` (17), `PengaturanTombolSimpanTest` (7), `PenyesuaianStokFisikTest` (9), `PerlindunganHapusTest` (20), `PesanValidasiIndonesiaTest` (7), `SandiFormPenggunaTest` (4), `SinkronisasiAsetTetapTest` (26), `StokTerkunciBacaSajaTest` (4), `TandaTanganPenggunaTest` (14), `ExampleTest` (1), `NupUnikAsetTetapTest` (6), `JalurBasisModul1Test` (17) *(baru)*

**Modul 2 — Permintaan Barang:** `DialogRincianPermintaanTest` (6), `DokumenBuktiTest` (9), `DokumenDiDiskPrivatTest` (4), `KartuKendaliTest` (19), `KonfirmasiAkunTimTest` (9), `NipPemohonKatalogTest` (5), `NomorBonPengeluaranTest` (9), `NotifikasiKedaluwarsaTest` (5), `PelepasanHoldKedaluwarsaTest` (5), `PemindaianBuktiTest` (7), `PengajuanKatalogAturanTest` (10), `PengajuanPermintaanTest` (5), `PengendalianStokTest` (12), `PersetujuanTidakMelebihiDimintaTest` (8), `PindahDokumenTest` (9), `RutePermintaanLamaTest` (4), `StokMasukTest` (23), `TandaTanganTahapanTest` (7), `UnduhanDokumenTest` (20), `JamKerjaTest` (7), `JalurBasisModul2Test` (21) *(baru)*

**Modul 3 — Mutasi Aset:** `AsetTetapTimSayaTest` (10), `BastMutasiAsetAksesTest` (3), `BastTandaTanganTersimpanTest` (3), `DokumenBastTest` (7), `MutasiAsetPenempatanTest` (18), `PenggunaTanpaTimTest` (6), `PenomoranBastTest` (6), `RiwayatPenempatanAsetTest` (20), `StatusBastDanAsetAktifTest` (9), `AksesBuatBastTest` (10), `PopUpBastTanpaKetikNipTest` (9), `JalurBasisModul3Test` (10) *(baru)*

**Modul 4 — Dashboard & Monitoring:** `AvatarFontLokalTest` (7), `BilahAtasTest` (21), `DashboardTimWidgetsTest` (7), `IstilahKonsistenTest` (10), `KondisiStokTautanTest` (4), `MonitoringPolaPermintaanTest` (18), `NotifikasiMutasiAsetTest` (6), `NotifikasiTautanRincianTest` (2), `PengalihanWhatsAppTest` (7), `PengirimanFonnteTest` (21), `PengirimanWhatsAppTest` (40), `PerluTindakanWidgetTest` (6), `PusatBantuanTest` (16), `RiwayatNotifikasiTest` (7), `RiwayatTabTest` (4), `SembunyikanNotifikasiTest` (8), `TandaiDibacaLoncengTest` (3), `VerifikasiNotifikasiMutasiAsetTest` (10), `JalurBasisModul4Test` (15) *(baru)*

## 3. Hasil per langkah (Tabel 3.3)

Langkah k menjalankan tes Modul 1 s.d. Modul k dalam satu run. *Lolos* = tes − gagal − error − dilewati. Durasi = waktu dinding PHPUnit.

| Langkah | Isi | Driver | Berkas | Tes | Assertion | Lolos | Gagal | Error | Dilewati | Durasi |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | M1 | SQLite | 31 | 278 | 1611 | 277 | 1 | 0 | 0 | 00:51.423 |
| 1 | M1 | MySQL | 31 | 278 | 1611 | 277 | 1 | 0 | 0 | 01:01.144 |
| 2 | M1 + M2 | SQLite | 52 | 482 | 4317 | 481 | 1 | 0 | 0 | 02:16.085 |
| 2 | M1 + M2 | MySQL | 52 | 482 | 4317 | 481 | 1 | 0 | 0 | 02:35.532 |
| 3 | M1 + M2 + M3 | SQLite | 64 | 593 | 4830 | 592 | 1 | 0 | 0 | 03:19.380 |
| 3 | M1 + M2 + M3 | MySQL | 64 | 593 | 4830 | 592 | 1 | 0 | 0 | 03:45.627 |
| 4 | M1 + M2 + M3 + M4 | SQLite | 83 | 805 | 5625 | 804 | 1 | 0 | 0 | 03:42.225 |
| 4 | M1 + M2 + M3 + M4 | MySQL | 83 | 805 | 5625 | 804 | 1 | 0 | 0 | 04:15.744 |

## 4. Rincian per modul pada tiap langkah

Sel berisi *lolos / tes* (assertion). Kolom modul sebelumnya menunjukkan apakah tes modul terdahulu tetap lolos ketika modul berikutnya ditambahkan.

**SQLite**

| Langkah | Modul 1 | Modul 2 | Modul 3 | Modul 4 |
|---|---|---|---|---|
| 1 | 277 / 278 (1611) | — | — | — |
| 2 | 277 / 278 (1611) | 204 / 204 (2706) | — | — |
| 3 | 277 / 278 (1611) | 204 / 204 (2706) | 111 / 111 (513) | — |
| 4 | 277 / 278 (1611) | 204 / 204 (2706) | 111 / 111 (513) | 212 / 212 (795) |

**MySQL 8.4.3 sementara**

| Langkah | Modul 1 | Modul 2 | Modul 3 | Modul 4 |
|---|---|---|---|---|
| 1 | 277 / 278 (1611) | — | — | — |
| 2 | 277 / 278 (1611) | 204 / 204 (2706) | — | — |
| 3 | 277 / 278 (1611) | 204 / 204 (2706) | 111 / 111 (513) | — |
| 4 | 277 / 278 (1611) | 204 / 204 (2706) | 111 / 111 (513) | 212 / 212 (795) |

## 5. Kegagalan

- Langkah 1, SQLite: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.
- Langkah 1, MySQL: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.
- Langkah 2, SQLite: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.
- Langkah 2, MySQL: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.
- Langkah 3, SQLite: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.
- Langkah 3, MySQL: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.
- Langkah 4, SQLite: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.
- Langkah 4, MySQL: `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak` (failure) — Tests\Feature\JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null. Failed asserting that false is true.

## 6. Pembanding: seluruh tes lama sebelum tes baru ditambahkan

| Run | Driver | Tes | Assertion | Lolos | Gagal | Error | Durasi |
|---|---|---|---|---|---|---|---|
| awal-sqlite | SQLite (phpunit.xml proyek) | 742 | 5465 | 742 | 0 | 0 | 04:07.882 |
| awal-mysql | MySQL | 742 | 5465 | 742 | 0 | 0 | 04:25.903 |

---

# Setelah perbaikan

Retest-all kumulatif diulang sesudah perbaikan T-1/T-1b dan T-2 (hasil awal di atas tidak diubah). Berkas tes Modul 1 bertambah `KodeBarangUnikFormTest` (8 tes, T-2), dan `JalurBasisModul1Test` bertambah 2 tes (kunci status hilang). Lingkungan sama: SQLite `:memory:`; MySQL 8.4.3 sementara dari folder `-utuh`, `127.0.0.1:3399`, `simpbi_uji`, datadir di scratchpad; Laragon 3306 tidak disentuh.

DB yang dicetak sebelum tiap run:

```
langkah1-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 05:37:37  selesai 2026-09-25 05:38:25  exit 0
langkah2-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 05:38:26  selesai 2026-09-25 05:40:44  exit 0
langkah3-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 05:40:45  selesai 2026-09-25 05:44:25  exit 0
langkah4-sqlite  DB_CONNECTION=sqlite DB_HOST= DB_PORT= DB_DATABASE=:memory:  mulai 2026-09-25 05:44:25  selesai 2026-09-25 05:49:20  exit 0
langkah1-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 05:49:20  selesai 2026-09-25 05:50:52  exit 0
langkah2-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 05:50:52  selesai 2026-09-25 05:54:22  exit 0
langkah3-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 05:54:23  selesai 2026-09-25 05:59:39  exit 0
langkah4-mysql   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 DB_DATABASE=simpbi_uji  mulai 2026-09-25 05:59:40  selesai 2026-09-25 06:06:04  exit 0
```

`database/database.sqlite`: 2f110b33ce188b9cc5657d7a0f872a79 *database/database.sqlite (sebelum), c488f78415e7b6d1ce2dd5950576ea59 *database/database.sqlite (sesudah).

md5 basis data kerja berubah selama rangkaian ini dan **bukan akibat pengujian**. Waktu ubah berkasnya 06:03:39, yaitu di tengah run `langkah4-mysql` (05:59:39–06:06:04), sedangkan run itu memaksa `DB_CONNECTION=mysql` (`force="true"`) dan memakai storage salinan di scratchpad. Pada saat yang sama `php artisan serve` milik pemilik proyek (berjalan sejak 24-09 23:49, memakai `.env` dengan `DB_CONNECTION=sqlite`) sedang melayani sesi peramban: berkas sesi di `storage/framework/sessions` repositori diperbarui 06:06:48. Basis data kerja hanya diperiksa secara baca-saja dan tidak dipulihkan atau diubah. Seluruh run awal (sebelum perbaikan) berlangsung tanpa perubahan md5.

| Langkah | Isi | Driver | Berkas | Tes | Assertion | Lolos | Gagal | Error | Dilewati | Durasi |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | M1 | SQLite | 32 | 288 | 1669 | 288 | 0 | 0 | 0 | 00:47.664 |
| 1 | M1 | MySQL | 32 | 288 | 1669 | 288 | 0 | 0 | 0 | 01:30.279 |
| 2 | M1 + M2 | SQLite | 53 | 492 | 4375 | 492 | 0 | 0 | 0 | 02:18.077 |
| 2 | M1 + M2 | MySQL | 53 | 492 | 4375 | 492 | 0 | 0 | 0 | 03:29.543 |
| 3 | M1 + M2 + M3 | SQLite | 65 | 603 | 4888 | 603 | 0 | 0 | 0 | 03:39.408 |
| 3 | M1 + M2 + M3 | MySQL | 65 | 603 | 4888 | 603 | 0 | 0 | 0 | 05:15.660 |
| 4 | M1 + M2 + M3 + M4 | SQLite | 84 | 815 | 5683 | 815 | 0 | 0 | 0 | 04:53.837 |
| 4 | M1 + M2 + M3 + M4 | MySQL | 84 | 815 | 5683 | 815 | 0 | 0 | 0 | 06:22.608 |

Rincian per modul (*lolos / tes (assertion)*):

**SQLite**

| Langkah | Modul 1 | Modul 2 | Modul 3 | Modul 4 |
|---|---|---|---|---|
| 1 | 288 / 288 (1669) | — | — | — |
| 2 | 288 / 288 (1669) | 204 / 204 (2706) | — | — |
| 3 | 288 / 288 (1669) | 204 / 204 (2706) | 111 / 111 (513) | — |
| 4 | 288 / 288 (1669) | 204 / 204 (2706) | 111 / 111 (513) | 212 / 212 (795) |

**MySQL 8.4.3 sementara**

| Langkah | Modul 1 | Modul 2 | Modul 3 | Modul 4 |
|---|---|---|---|---|
| 1 | 288 / 288 (1669) | — | — | — |
| 2 | 288 / 288 (1669) | 204 / 204 (2706) | — | — |
| 3 | 288 / 288 (1669) | 204 / 204 (2706) | 111 / 111 (513) | — |
| 4 | 288 / 288 (1669) | 204 / 204 (2706) | 111 / 111 (513) | 212 / 212 (795) |

Kegagalan: tidak ada.

