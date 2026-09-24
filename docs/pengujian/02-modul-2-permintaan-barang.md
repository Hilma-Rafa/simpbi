# Modul 2 — Permintaan Barang

Cakupan fitur: Katalog Barang, alur Permintaan Barang enam tahap (pengajuan →
persetujuan Ketua Tim → verifikasi Petugas Gudang → persetujuan Kasubbag Umum →
penyiapan → konfirmasi penerimaan/pengesahan), Stok Masuk, dan Kartu Kendali.

Modul ini bergantung pada Modul 1 (akun, tim kerja, kategori, barang persediaan). Uji
regresinya menjalankan berkas Modul 1 dan Modul 2 **bersama-sama** dalam satu run,
sehingga setiap efek samping dari Modul 2 terhadap Modul 1 akan terlihat sebagai
kegagalan di Modul 1.

Semua angka berasal dari eksekusi nyata pada 23 September 2026.

---

## 1. Regression Testing

### 1.1 Berkas uji yang dipetakan ke Modul 2

| No | Berkas uji | Metode | Kasus dieksekusi | Assertion | Kelas aplikasi yang diimpor | Lintas modul |
|---|---|---|---|---|---|---|
| 1 | `Feature/DialogRincianPermintaanTest.php` | 6 | 6 | 18 | `Riwayat`, `ListPermintaanBarangs`, `PermintaanBarang`, `KedaluwarsaService` | Ya → Modul 4 |
| 2 | `Feature/DokumenBuktiTest.php` | 9 | 9 | 23 | `PermintaanBarang`, `RiwayatPersetujuan`, `User`, `DokumenPermintaanService`, `TandaTangan` | — |
| 3 | `Feature/DokumenDiDiskPrivatTest.php` | 4 | 4 | 23 | `CreateBastMutasiAset`, `BastMutasiAset`, `PermintaanBarang`, `DokumenBastService`, `DokumenPermintaanService`, `MutasiAsetService` | Ya → Modul 3 |
| 4 | `Unit/JamKerjaTest.php` | 7 | 7 | 1086 | `JamKerja` | — |
| 5 | `Feature/KartuKendaliTest.php` | 19 | 19 | 643 | `KartuKendali`, `BarangPersediaan`, `KartuKendaliService`, `StokService` | — |
| 6 | `Feature/KonfirmasiAkunTimTest.php` | 9 | 9 | 131 | `ListPermintaanBarangs`, `PermintaanBarangResource`, `PermintaanBarang`, `User`, `TandaTangan` | — |
| 7 | `Feature/NipPemohonKatalogTest.php` | 5 | 5 | 80 | `KatalogBarang`, `PermintaanBarang` | — |
| 8 | `Feature/NomorBonPengeluaranTest.php` | 9 | 9 | 20 | `MutasiStok`, `PermintaanBarang`, `StokService` | — |
| 9 | `Feature/NotifikasiKedaluwarsaTest.php` | 5 | 5 | 20 | `Notifikasi`, `PermintaanBarang`, `KedaluwarsaService`, `NotifikasiService` | Ya → Modul 4 |
| 10 | `Feature/PelepasanHoldKedaluwarsaTest.php` | 5 | 5 | 27 | `PermintaanBarang`, `RiwayatPersetujuan` | — |
| 11 | `Feature/PemindaianBuktiTest.php` | 7 | 7 | 14 | `PermintaanBarang`, `RiwayatPersetujuan`, `DokumenPermintaanService` | — |
| 12 | `Feature/PengajuanKatalogAturanTest.php` | 10 | 10 | 66 | `KatalogBarang`, `KirimPesanWhatsApp`, `Notifikasi`, `PermintaanBarang`, `StokService` | Ya → Modul 4 |
| 13 | `Feature/PengajuanPermintaanTest.php` | 5 | 5 | 37 | `KatalogBarang`, `PermintaanBarang`, `RiwayatPersetujuan` | — |
| 14 | `Feature/PengendalianStokTest.php` | 12 | 12 | 41 | `BarangPersediaan`, `MutasiStok`, `StokService` | — |
| 15 | `Feature/PersetujuanTidakMelebihiDimintaTest.php` | 8 | 8 | 79 | `ListPermintaanBarangs`, `PermintaanBarangResource`, `BarangPersediaan`, `PermintaanBarang`, `Tim`, `User`, `StokService` | — |
| 16 | `Feature/PindahDokumenTest.php` | 9 | 9 | 56 | `PindahkanDokumen`, `BastMutasiAset`, `PermintaanBarang`, `Tim`, `User` | Ya → Modul 3 |
| 17 | `Feature/RutePermintaanLamaTest.php` | 4 | 4 | 21 | `PermintaanBarangResource`, `PermintaanBarang` | — |
| 18 | `Feature/StokMasukTest.php` | 21 | 23 | 166 | `StokMasuk`, `MutasiStok` | — |
| 19 | `Feature/TandaTanganTahapanTest.php` | 7 | 7 | 41 | `ListPermintaanBarangs`, `PermintaanBarang`, `User`, `TandaTangan` | — |
| 20 | `Feature/UnduhanDokumenTest.php` | 12 | 20 | 72 | `GantiKataSandi`, `LengkapiAkun`, `ListBastMutasiAsets`, `BastMutasiAset`, `PermintaanBarang`, `Tim`, `User` | Ya → Modul 3, 4 |
| | **Jumlah** | **173** | **183** | **2664** | | |

### 1.2 Berkas lintas modul (modul utama tetap Modul 2)

| Berkas | Modul lain yang tersentuh | Alasan penempatan di Modul 2 |
|---|---|---|
| `DialogRincianPermintaanTest` | Modul 4 | Dialog rincian **permintaan** dibuka dari daftar Permintaan Barang dan dari halaman Riwayat; yang diuji isi rincian permintaan dan jejak sapuan kedaluwarsa. |
| `DokumenDiDiskPrivatTest` | Modul 3 | 2 tes menyangkut bukti permintaan, 1 tes BAST, 1 tes layanan dokumen; fokusnya rute unduh `bukti.unduh`/`bukti.asli`. |
| `NotifikasiKedaluwarsaTest` | Modul 4 | Memanggil `KedaluwarsaService::sapu()` (Modul 2) dan menegaskan penerima notifikasinya. |
| `PengajuanKatalogAturanTest` | Modul 4 | 7 tes aturan pengajuan di Katalog (barang nonaktif, stok, bentrok kode); 3 tes menegaskan penerima notifikasi pengajuan. |
| `PindahDokumenTest` | Modul 3 | Perintah `simpbi:pindah-dokumen` memindahkan berkas bukti permintaan dan BAST dari disk publik ke disk privat; sebagian besar kasusnya memakai bukti permintaan. |
| `UnduhanDokumenTest` | Modul 3, Modul 4 | Rute unduh bukti permintaan (Modul 2), BAST (Modul 3), dan buku panduan (Modul 4); gerbang akunnya sama. Mayoritas kasus menyangkut bukti permintaan. |

`Unit/JamKerjaTest` dimasukkan ke Modul 2 karena `Support\JamKerja` menghitung batas
waktu tahapan permintaan (dipakai saat pengajuan di `KatalogBarang`).

### 1.3 Hasil — Langkah 2 (Modul 1 + Modul 2 bersama-sama)

Konfigurasi PHPUnit berisi 49 berkas (29 Modul 1 + 20 Modul 2) dalam dua *testsuite*.

| Driver | Tes dijalankan | Lulus | Gagal | Error | Assertion | Durasi |
|---|---|---|---|---|---|---|
| SQLite (`:memory:`) | 434 | 434 | 0 | 0 | 4160 | 3 mnt 35 dtk |
| MySQL 8.4.3 sementara | 434 | 434 | 0 | 0 | 4160 | 2 mnt 57 dtk |

Rincian per modul di dalam run langkah 2 (dari berkas JUnit):

| Modul | Kasus | Lulus — SQLite | Lulus — MySQL | Gagal (kedua driver) |
|---|---|---|---|---|
| Modul 1 (regresi) | 251 | 251 / 251 | 251 / 251 | 0 |
| Modul 2 (baru) | 183 | 183 / 183 | 183 / 183 | 0 |

Kasus Modul 2 juga tetap lulus pada langkah berikutnya:

| Langkah | Isi run | Kasus Modul 2 lulus — SQLite | Kasus Modul 2 lulus — MySQL |
|---|---|---|---|
| 3 | Modul 1 + 2 + 3 | 183 / 183 | 183 / 183 |
| 4 | Seluruh suite | 183 / 183 | 183 / 183 |

### 1.4 Kesimpulan regression Modul 2

**Layak.** Pada run gabungan Modul 1 + 2, ke-251 kasus Modul 1 tetap lulus 100% di
kedua driver (kriteria regresi terpenuhi), dan ke-183 kasus Modul 2 juga lulus.

---

## 2. White-Box Testing

### 2.1 Kelas yang diperiksa

| Kelas | Tanggung jawab |
|---|---|
| `app/Services/StokService.php` | HOLD (kunci stok saat pengajuan), RELEASE (lepas kunci), KONVERSI (pengeluaran), penyesuaian kunci saat persetujuan sebagian, nomor bon, stok masuk dan hitung ulang saldo kartu kendali. |
| `app/Services/KedaluwarsaService.php` | Sapuan permintaan yang melewati batas waktu tahapan: lepas kunci, status `kedaluwarsa`, riwayat, notifikasi. |

Struktur sesuai daftar tugas; tidak ada kelas pengganti.

### 2.2 Jalur kritis dan pemetaannya ke test

**A. `StokService`**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| SS-1 | `hold()`: barang tidak ada → `findOrFail` gagal, seluruh transaksi batal (baris 40) | Satu barang bermasalah membatalkan kunci barang lain | Tercakup | `PengendalianStokTest::test_hold_barang_yang_tidak_ada_menggagalkan_seluruh_pengajuan` |
| SS-2 | `hold()`: barang nonaktif → `RuntimeException` (baris 42–46) | Barang nonaktif tidak dapat diminta | Tercakup | `PengajuanKatalogAturanTest::test_barang_nonaktif_ditolak_tanpa_permintaan_dan_tanpa_kunci` |
| SS-3 | `hold()`: jumlah > tersedia → `RuntimeException` (baris 48–54) | Stok tidak pernah dikunci melebihi yang tersedia | Tercakup | `PengendalianStokTest::test_hold_ditolak_ketika_stok_tersedia_tidak_cukup`, `::test_hold_kedua_tidak_dapat_melampaui_sisa_stok_tersedia`, `PengajuanPermintaanTest::test_pengajuan_melebihi_stok_tersedia_dibatalkan_seluruhnya`, `PengajuanKatalogAturanTest::test_stok_tidak_cukup_tetap_menampilkan_pesan_bisnisnya` |
| SS-4 | `hold()`: stok cukup → `stok_hold` bertambah, `stok_fisik` tetap (baris 56) | Kunci tidak mengurangi stok fisik | Tercakup | `PengendalianStokTest::test_hold_mengunci_stok_tanpa_mengurangi_stok_fisik` |
| SS-5 | `release()`: jumlah dilepas = `min(final ?? diminta, diminta)` (baris 70) | Kunci permintaan lain tidak ikut terlepas | Tercakup | `PersetujuanTidakMelebihiDimintaTest::test_konversi_dan_release_tidak_menyentuh_kunci_permintaan_lain` (isinya memanggil `release()`) |
| SS-6 | `release()`: barang tidak ditemukan → dilewati (baris 72–75) | Pelepasan tidak gagal karena barang hilang | **Tidak tercakup** | — |
| SS-7 | `release()`: `max(0, …)` dan `hold_released_at` diisi (baris 78–83) | Pelepasan ganda tidak membuat kunci negatif | Tercakup | `PengendalianStokTest::test_release_ganda_tidak_membuat_kunci_menjadi_negatif`, `::test_release_melepas_kunci_dan_menandai_waktu_pelepasan` |
| SS-8 | `pesanAturan()`: kelas persis `RuntimeException`/`InvalidArgumentException` → pesan; lainnya → `null` (baris 93–98) | Pesan bisnis tampil apa adanya; galat teknis (SQL) disembunyikan | Tercakup | `PengajuanKatalogAturanTest::test_stok_tidak_cukup_tetap_menampilkan_pesan_bisnisnya`, `::test_kegagalan_teknis_menampilkan_pesan_umum_bukan_teks_sql` |
| SS-9 | `pastikanTidakMelebihiDiminta()`: `final > diminta` → `InvalidArgumentException` (baris 111–115) | Persetujuan tidak dapat melebihi jumlah diminta | Tercakup | `PersetujuanTidakMelebihiDimintaTest::test_persetujuan_melebihi_diminta_ditolak_dan_stok_tidak_berubah`, `::test_pesan_batas_menyebut_jumlah_diminta`, `::test_muatan_yang_dimodifikasi_ditolak_di_server`, `::test_layanan_stok_menolak_jumlah_tersimpan_yang_melebihi_diminta` |
| SS-10 | `sesuaikanHold()`: `jumlah_final` `null` → dilewati (baris 128–130) | Rincian tanpa keputusan tidak mengubah kunci | **Tidak tercakup** | — |
| SS-11 | `sesuaikanHold()`: selisih ≤ 0 → dilewati (baris 133–135) | Persetujuan penuh tidak mengubah kunci | Tercakup | `PengendalianStokTest::test_persetujuan_penuh_tidak_mengubah_kunci` |
| SS-12 | `sesuaikanHold()`: selisih > 0 → kunci dikurangi selisih (baris 137–142) | Sisa stok kembali tersedia bagi tim lain | Tercakup | `PengendalianStokTest::test_persetujuan_sebagian_melepas_selisih_kunci`, `PersetujuanTidakMelebihiDimintaTest::test_persetujuan_sebagian_melepas_selisih_dan_kedua_permintaan_selesai` |
| SS-13 | `konversi()`: jumlah = `final ?? diminta`; mutasi keluar tercatat dengan nomor bon, referensi, petugas (baris 151–196) | Barang keluar sejumlah yang disetujui | Tercakup | `PengendalianStokTest::test_konversi_mengurangi_stok_fisik_dan_mencatat_mutasi_keluar`, `::test_konversi_memakai_jumlah_final_ketika_disetujui_sebagian`, `::test_seluruh_daur_hidup_stok_kembali_konsisten` |
| SS-14 | `konversi()`: jumlah ≤ 0 → dilewati (baris 158–160) | Rincian disetujui nol tidak menerbitkan mutasi | **Tidak tercakup** | — |
| SS-15 | `konversi()`: barang tidak ditemukan → dilewati (baris 162–165) | — | **Tidak tercakup** | — |
| SS-16 | `konversi()`: kunci dikurangi `min(jumlah, diminta)` (baris 171–173) ketika `final > diminta` | Kunci permintaan lain tidak ikut berkurang | **Tidak tercakup** | — (test yang namanya menyebut "konversi" hanya memanggil `release()`) |
| SS-17 | `terbitkanNomorBon()`: permintaan sudah bernomor → nomor lama dipakai (baris 262–264) | Nomor bon tidak terbit dua kali | **Tidak tercakup** | — |
| SS-18 | `terbitkanNomorBon()`: nomor terbesar tahun berjalan + 1, urut sebagai angka, mulai ulang tiap tahun (baris 266–284) | Nomor bon unik dan berurutan | Tercakup | `NomorBonPengeluaranTest::test_permintaan_pertama_tahun_ini_bernomor_001`, `::test_nomor_berlanjut_pada_permintaan_berikutnya`, `::test_penomoran_dimulai_ulang_setiap_tahun`, `::test_penomoran_tetap_benar_setelah_melewati_seribu`, `::test_nomor_tanpa_lapis_nol_dibandingkan_sebagai_angka`, `::test_satu_permintaan_memakai_satu_nomor_untuk_semua_barangnya` |
| SS-19 | `terbitkanNomorBon()`: cabang driver `INTEGER` (SQLite) / `SIGNED` (MySQL) (baris 270) | Pengurutan numerik benar di kedua basis data | Tercakup | Test SS-18 yang sama, dijalankan di **kedua** driver (lulus di SQLite dan MySQL) |
| SS-20 | `tambah()`: mutasi masuk disisipkan menurut tanggal dokumen lalu saldo dihitung ulang (baris 217–242) | Kolom Sisa kartu kendali benar walau tanggal mundur | Tercakup | `PengendalianStokTest::test_stok_masuk_menambah_stok_dan_mencatat_saldo_berjalan`, `StokMasukTest::test_tanggal_mundur_diterima_dan_disisipkan_pada_urutannya`, `::test_kolom_sisa_dihitung_ulang_menurut_urutan_tanggal` |
| SS-21 | `saldoAwalTersirat()`: stok awal tanpa baris buku besar (baris 301–304) | Stok katalog awal tidak hilang saat saldo dihitung ulang | Tercakup | `StokMasukTest::test_stok_awal_tanpa_baris_buku_besar_tetap_terhitung` |
| SS-22 | `hitungUlangSaldo()`: saldo < 0 → `InvalidArgumentException` (baris 333–339) | Kartu kendali bersisa negatif ditolak | **Tidak tercakup** | — |

**B. `KedaluwarsaService`**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| KS-1 | `sapuBilaPerlu()`: `Cache::add` gagal → tidak menyapu; berhasil → `sapu()` (baris 52–59) | Sapuan dari middleware dibatasi sekali per 60 detik | **Tidak tercakup** | — (middleware `SapuPermintaanKedaluwarsa` ikut berjalan pada tes HTTP panel, tetapi tidak ada test yang menegaskan perilaku jedanya) |
| KS-2 | Kueri: hanya status berjalan, `hold_expired_at` terisi dan sudah lewat (baris 71–76) | Permintaan yang belum lewat batas atau sudah berstatus akhir tidak disentuh | Tercakup | `PelepasanHoldKedaluwarsaTest::test_permintaan_yang_belum_lewat_batas_waktu_tidak_disentuh`, `::test_permintaan_berstatus_akhir_tidak_ikut_dilepas`, `::test_permintaan_yang_lewat_batas_waktu_dilepas_dan_ditandai_kedaluwarsa` |
| KS-3 | Pemeriksaan ulang di dalam transaksi berkunci → `return` bila sudah ditangani (baris 87–96) | Kunci tidak dilepas dua kali; tidak ada notifikasi ganda | Tercakup | `NotifikasiKedaluwarsaTest::test_permintaan_yang_sudah_ditangani_sejak_daftar_dibaca_dilewati` |
| KS-4 | Lepas kunci, status `kedaluwarsa`, riwayat `tolak` pada tahap terakhir (baris 106–134) | Stok kembali tersedia; jejak tahap terhenti tercatat | Tercakup | `PelepasanHoldKedaluwarsaTest::test_permintaan_yang_lewat_batas_waktu_dilepas_dan_ditandai_kedaluwarsa`, `::test_riwayat_mencatat_tahap_yang_benar_benar_kedaluwarsa` |
| KS-5 | Waktu riwayat = saat batas jatuh, bukan saat sapuan (baris 113, 131) | Riwayat menunjukkan waktu sebenarnya | Tercakup | `DialogRincianPermintaanTest::test_riwayat_mencatat_saat_batas_jatuh_bukan_saat_sapuan` |
| KS-6 | `! $diubah` → tidak dilaporkan, tidak diberi tahu (baris 139–141) | Sapuan berulang tidak menambah notifikasi | Tercakup | `NotifikasiKedaluwarsaTest::test_sapuan_kedua_tidak_menambah_notifikasi` |
| KS-7 | Notifikasi sesudah transaksi, tepat satu kali per kanal (baris 145–146) | Pemohon diberi tahu satu kali | Tercakup | `NotifikasiKedaluwarsaTest::test_sapuan_memberi_tahu_pemohon_tepat_satu_kali`, `::test_penerima_whatsapp_tercatat_satu_kali_bila_kanal_menyala` |
| KS-8 | Notifikasi melempar → `report()`, sapuan tetap berhasil (baris 147–149) | Gangguan WhatsApp tidak membatalkan sapuan | Tercakup | `NotifikasiKedaluwarsaTest::test_kegagalan_notifikasi_tidak_menggagalkan_sapuan` |
| KS-9 | Transaksi gagal → hasil `berhasil = false` beserta pesan (baris 152–154) | Operator melihat permintaan yang gagal disapu | **Tidak tercakup** | — |
| KS-10 | `tahapTerakhir()`: lima status berjalan → tahap masing-masing (baris 161–166) | Riwayat menyebut tahap yang benar | Tercakup | `PelepasanHoldKedaluwarsaTest::test_seluruh_tahap_berjalan_tercakup` |
| KS-11 | `tahapTerakhir()`: `default` → `ketua_tim` (baris 167) | Cadangan untuk status di luar lima status berjalan | **Tidak tercakup** | — (tidak terjangkau lewat `sapu()` karena kuerinya hanya memuat status berjalan) |

### 2.3 Alat ukur cakupan

Tidak tersedia (tidak ada `pcov`/`xdebug`; `phpdbg` tidak didukung PHPUnit 11; tidak
ada driver baru yang dipasang). Cakupan diukur manual seperti tabel di atas.

### 2.4 Kesimpulan white-box Modul 2

**Layak Bersyarat.** Dari 33 jalur kritis (22 `StokService`, 11 `KedaluwarsaService`),
23 tercakup dan seluruh test yang mencakupnya lulus (183/183 kasus Modul 2 di kedua
driver). Sepuluh jalur tidak tercakup dan diteruskan ke pengujian Black-Box:

1. **SS-6, SS-15** — barang yang sudah tidak ada saat `release()`/`konversi()`
   (bersifat defensif; penghapusan barang bermutasi sudah dicegah `PerlindunganHapusTest`).
2. **SS-10** — `sesuaikanHold()` dengan rincian yang `jumlah_final`-nya kosong.
3. **SS-14** — Kasubbag menyetujui 0 untuk satu rincian, lalu barang dikeluarkan.
4. **SS-16** — batas `min(jumlah, diminta)` saat konversi.
5. **SS-17** — `konversi()` dipanggil kedua kali pada permintaan yang sudah bernomor bon.
6. **SS-22** — penyisipan yang membuat sisa stok negatif pada kartu kendali.
7. **KS-1** — jeda 60 detik sapuan dari middleware.
8. **KS-9** — laporan kegagalan transaksi sapuan.
9. **KS-11** — cabang `default` `tahapTerakhir()` (defensif).
