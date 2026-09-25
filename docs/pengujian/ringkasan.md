# Ringkasan Pengujian White-Box dan Regression — SIMPBI

**Versi sistem yang diuji:** commit `8449a47c625bd6e1b185bd43bef9eb6e30320349` (`main`), working tree bersih saat pengujian dimulai. Kode aplikasi tidak diubah; satu-satunya tambahan adalah empat berkas tes baru dan dokumen di folder ini.

| Dokumen | Isi |
|---|---|
| [wb-increment-1.md](wb-increment-1.md) | Basis path Increment 1 — Pengguna & Data Induk |
| [wb-increment-2.md](wb-increment-2.md) | Basis path Increment 2 — Permintaan Barang (StokService, KedaluwarsaService) |
| [wb-increment-3.md](wb-increment-3.md) | Basis path Increment 3 — Mutasi Aset |
| [wb-increment-4.md](wb-increment-4.md) | Basis path Increment 4 — Notifikasi dan widget dasbor |
| [regresi.md](regresi.md) | Retest-all kumulatif per langkah, SQLite dan MySQL |

## Tabel ringkas per increment

| Increment | Modul | Method | Total V(G) | Jalur mustahil | Jalur lolos awal | Jalur lolos akhir | Jalur gagal | Regresi SQLite | Regresi MySQL |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Pengguna & Data Induk | 13 | 71 | 3 | 50 (70.4%) | 67 (94.4%) | 1 | 277/278 (1 gagal, 0 error) | 277/278 (1 gagal, 0 error) |
| 2 | Permintaan Barang | 13 | 53 | 5 | 27 (50.9%) | 48 (90.6%) | 0 | 481/482 (1 gagal, 0 error) | 481/482 (1 gagal, 0 error) |
| 3 | Mutasi Aset | 10 | 39 | 0 | 29 (74.4%) | 39 (100.0%) | 0 | 592/593 (1 gagal, 0 error) | 592/593 (1 gagal, 0 error) |
| 4 | Dashboard & Monitoring | 13 | 62 | 10 | 35 (56.5%) | 52 (83.9%) | 0 | 804/805 (1 gagal, 0 error) | 804/805 (1 gagal, 0 error) |
| **Jumlah** | | **49** | **225** | **18** | **141** (62.7%) | **206** (91.6%) | **1** | | |

Kolom regresi = tes lolos / tes dijalankan pada langkah increment itu (kumulatif: langkah k menjalankan Modul 1 s.d. k).
Jalur lolos maksimum yang dapat dicapai = total V(G) − jalur mustahil = 225 − 18 = 207.

## Rincian per method

| Inc | ID | Method | V(G) | J_awal | J_akhir |
|---|---|---|---|---|---|
| 1 | 1.1 | `UserForm::akunSendiri` | 2 | 2 | 2 |
| 1 | 1.2 | `EditUser::beforeSave` | 11 ⚠ | 8 | 9 |
| 1 | 1.3 | `EditUser::afterSave` | 3 | 3 | 3 |
| 1 | 1.4 | `User::alasanTidakDapatDihapus` | 5 | 5 | 5 |
| 1 | 1.5 | `User::booting (pendengar deleting)` | 2 | 2 | 2 |
| 1 | 1.6 | `User::punyaRiwayat` | 5 | 3 | 5 |
| 1 | 1.7 | `ImporPengguna::jalankan` | 20 ⚠ | 11 | 18 |
| 1 | 1.8 | `ImporPengguna::pesanPenjagaAdmin` | 10 | 8 | 10 |
| 1 | 1.9 | `ImporPengguna::bacaYaTidak` | 4 | 2 | 4 |
| 1 | 1.10 | `StokMasuk::borangBarangBaru › aturan kode_barang` | 2 | 0 | 2 |
| 1 | 1.11 | `StokMasuk::simpanBarangBaru` | 3 | 2 | 3 |
| 1 | 1.12 | `AsetTetapForm::configure › placeholder tim_penempatan_id` | 2 | 2 | 2 |
| 1 | 1.13 | `AsetTetapForm::configure › helperText tim_penempatan_id` | 2 | 2 | 2 |
| 2 | 2.1 | `StokService::hold` | 4 | 3 | 4 |
| 2 | 2.2 | `StokService::release` | 4 | 2 | 3 |
| 2 | 2.3 | `StokService::pesanAturan` | 2 | 1 | 2 |
| 2 | 2.4 | `StokService::pastikanTidakMelebihiDiminta` | 5 | 2 | 4 |
| 2 | 2.5 | `StokService::sesuaikanHold` | 5 | 2 | 4 |
| 2 | 2.6 | `StokService::konversi` | 5 | 2 | 4 |
| 2 | 2.7 | `StokService::tambah` | 2 | 2 | 2 |
| 2 | 2.8 | `StokService::terbitkanNomorBon` | 3 | 2 | 3 |
| 2 | 2.9 | `StokService::hitungUlangSaldo` | 4 | 1 | 4 |
| 2 | 2.10 | `StokService::tanggalMutasiTerakhir` | 2 | 0 | 2 |
| 2 | 2.11 | `KedaluwarsaService::sapuBilaPerlu` | 2 | 1 | 2 |
| 2 | 2.12 | `KedaluwarsaService::sapu` | 9 | 4 | 8 |
| 2 | 2.13 | `KedaluwarsaService::tahapTerakhir` | 6 | 5 | 6 |
| 3 | 3.1 | `MutasiAsetService::nomorBaru` | 2 | 2 | 2 |
| 3 | 3.2 | `MutasiAsetService::pesanAsetTidakDapatDimutasi` | 3 | 3 | 3 |
| 3 | 3.3 | `MutasiAsetService::pesanTandaTanganBelumLengkap` | 2 | 2 | 2 |
| 3 | 3.4 | `MutasiAsetService::periksaPembuatan` | 8 | 6 | 8 |
| 3 | 3.5 | `MutasiAsetService::kunciDanPastikanStatus` | 3 | 2 | 3 |
| 3 | 3.6 | `MutasiAsetService::sahkan` | 1 | 1 | 1 |
| 3 | 3.7 | `MutasiAsetService::konfirmasi` | 2 | 2 | 2 |
| 3 | 3.8 | `ListBastMutasiAsets::handleCreation` | 9 | 5 | 9 |
| 3 | 3.9 | `BastMutasiAsetForm::pesanTandaTangan` | 5 | 4 | 5 |
| 3 | 3.10 | `BastMutasiAsetsTable::configure › aksi Konfirmasi Penerimaan` | 4 | 2 | 4 |
| 4 | 4.1 | `NotifikasiService::kirim` | 2 | 1 | 2 |
| 4 | 4.2 | `NotifikasiService::terbitkanWhatsApp` | 5 | 4 | 4 |
| 4 | 4.3 | `NotifikasiService::berperan` | 2 | 2 | 2 |
| 4 | 4.4 | `NotifikasiService::permintaanBerubah` | 15 ⚠ | 11 | 13 |
| 4 | 4.5 | `NotifikasiService::stokMenipis` | 5 | 0 | 4 |
| 4 | 4.6 | `NotifikasiService::bastBerubah` | 10 | 4 | 6 |
| 4 | 4.7 | `TindakanPermintaan::statusUntuk` | 5 | 3 | 5 |
| 4 | 4.8 | `PerluTindakan::kueri` | 3 | 2 | 3 |
| 4 | 4.9 | `PermintaanBarangResource::getEloquentQuery` | 2 | 2 | 2 |
| 4 | 4.10 | `StatusPermintaanTim::cacah` | 3 | 0 | 2 |
| 4 | 4.11 | `KondisiAsetTetapTim::cacah` | 5 | 4 | 4 |
| 4 | 4.12 | `TrenKonsumsiTim::keluarPerHari` | 3 | 1 | 3 |
| 4 | 4.13 | `PolaPermintaan::cacahPerHari` | 2 | 1 | 2 |

⚠ = V(G) > 10 (batas yang disarankan McCabe, 1976).

## Jalur yang gagal

- 1.2 J3: status `gagal` — tes `JalurBasisModul1Test::test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak`. Rincian dan bukti: wb-increment-1.md bagian Temuan.

---

## Setelah perbaikan

Perbaikan T-1 (+ celah sisa T-1b yang ditemukan saat uji ulang) dan T-2 diterapkan pada kode commit `8449a47c625bd6e1b185bd43bef9eb6e30320349` (belum di-commit); tabel di atas adalah hasil awal dan tidak diubah.

| Increment | Method | Total V(G) | Mustahil | Jalur lolos | Regresi SQLite | Regresi MySQL |
|---|---|---|---|---|---|---|
| 1 | 15 | 76 | 3 | 73 (96.1%) | 288/288 (0 gagal, 0 error) | 288/288 (0 gagal, 0 error) |
| 2 | 13 | 53 | 5 | 48 (90.6%) | 492/492 (0 gagal, 0 error) | 492/492 (0 gagal, 0 error) |
| 3 | 10 | 39 | 0 | 39 (100.0%) | 603/603 (0 gagal, 0 error) | 603/603 (0 gagal, 0 error) |
| 4 | 13 | 62 | 10 | 52 (83.9%) | 815/815 (0 gagal, 0 error) | 815/815 (0 gagal, 0 error) |
| **Jumlah** | **51** | **230** | **18** | **212** (92.2%) | | |

Jalur lolos maksimum = 230 − 18 = 212; tercapai 212. Increment 2–4 tidak tersentuh perbaikan, sehingga angka white-box-nya tetap; regresi seluruh increment dijalankan ulang. Jalur gagal: tidak ada.

