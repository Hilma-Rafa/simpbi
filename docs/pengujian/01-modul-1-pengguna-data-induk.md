# Modul 1 — Manajemen Pengguna & Data Induk

Cakupan fitur: Masuk (login), Pengguna, Tim Kerja, Kategori Barang, Barang Persediaan,
Aset Tetap (data induk), Pengaturan Akun, Lengkapi Akun.

Modul ini berada di hulu dependensi: seluruh modul lain membutuhkan akun, tim kerja,
kategori, barang, dan aset yang dikelola di sini. Karena itu uji regresi Modul 1 adalah
*baseline* — tidak ada modul sebelumnya yang perlu dijaga.

Semua angka pada dokumen ini berasal dari eksekusi nyata pada 23 September 2026
(lihat `00-ringkasan-pengujian.md` untuk lingkungan dan isolasi).

---

## 1. Regression Testing

### 1.1 Berkas uji yang dipetakan ke Modul 1

Kolom *Metode* = jumlah metode `test_*` di dalam berkas. Kolom *Kasus dieksekusi* =
jumlah kasus yang benar-benar dijalankan PHPUnit (lebih besar dari jumlah metode bila
berkas memakai `#[DataProvider]`). Kolom *Kelas aplikasi* dibaca dari pernyataan
`use App\...` pada berkas uji itu sendiri.

| No | Berkas uji | Metode | Kasus dieksekusi | Assertion | Kelas aplikasi yang diimpor | Lintas modul |
|---|---|---|---|---|---|---|
| 1 | `Feature/AdminTidakMengunciDiriTest.php` | 10 | 10 | 94 | `CreateUser`, `EditUser`, `UserForm`, `User` | — |
| 2 | `Feature/AksesPanelTest.php` | 5 | 5 | 7 | `Dashboard`, `User` | Ya → Modul 4 |
| 3 | `Feature/EmailTerkunciPengaturanTest.php` | 4 | 4 | 82 | `Pengaturan`, `EditUser` | — |
| 4 | `Feature/ExampleTest.php` | 1 | 1 | 1 | — (uji HTTP/halaman) | — |
| 5 | `Feature/FormTimKerjaTest.php` | 13 | 13 | 126 | `CreateTim`, `EditTim`, `StatusSinkronisasiTim`, `Tim`, `User`, `ImporTimKerja` | — |
| 6 | `Feature/GantiSandiSesiTest.php` | 9 | 9 | 75 | `Dashboard`, `GantiKataSandi`, `LengkapiAkun`, `Pengaturan`, `User` | — |
| 7 | `Feature/HapusAkunTerjagaTest.php` | 10 | 10 | 57 | `EditUser`, `ListUsers`, `User` | — |
| 8 | `Feature/IlustrasiAlurMasukTest.php` | 3 | 3 | 19 | — (uji HTTP/halaman) | — |
| 9 | `Feature/ImporPenggunaPenjagaAdminTest.php` | 7 | 7 | 30 | `User`, `HasilImpor`, `ImporPengguna` | — |
| 10 | `Feature/ImporPenggunaTest.php` | 17 | 17 | 48 | `Dashboard`, `GantiKataSandi`, `Tim`, `User`, `HasilImpor`, `ImporPengguna`, `PembuatTemplate` | — |
| 11 | `Feature/ImporTimKerjaTest.php` | 11 | 11 | 30 | `Tim`, `ImporTimKerja`, `PembuatTemplate` | — |
| 12 | `Feature/IndikatorCapsLockMasukTest.php` | 4 | 4 | 17 | — (uji HTTP/halaman) | — |
| 13 | `Feature/IstilahTimKerjaTest.php` | 4 | 4 | 8 | `ListAsetTetaps`, `ListTims`, `TimResource` | — |
| 14 | `Feature/KodeBarangBaruStokMasukTest.php` | 4 | 4 | 14 | `StokMasuk`, `BarangPersediaan`, `Kategori` | Ya → Modul 2 |
| 15 | `Feature/KontakBantuanMasukTest.php` | 6 | 6 | 10 | `KontakBantuan` | Ya → Modul 4 |
| 16 | `Feature/LengkapiAkunTest.php` | 11 | 11 | 63 | `Dashboard`, `GantiKataSandi`, `LengkapiAkun`, `TandaTangan` | — |
| 17 | `Feature/NamaNipTerkunciPengaturanTest.php` | 6 | 6 | 141 | `KatalogBarang`, `Pengaturan`, `EditUser`, `PermintaanBarang` | Ya → Modul 2 |
| 18 | `Feature/PemeliharaanDataTest.php` | 8 | 11 | 42 | `Cadangan` | — |
| 19 | `Feature/PenempatanAsetFormTest.php` | 7 | 7 | 73 | `CreateAsetTetap`, `EditAsetTetap`, `AsetTetap`, `Kategori`, `RiwayatPenempatanAset`, `MutasiAsetService` | Ya → Modul 3 |
| 20 | `Feature/PengaturanTest.php` | 17 | 17 | 58 | `Pengaturan` | — |
| 21 | `Feature/PengaturanTombolSimpanTest.php` | 7 | 7 | 21 | `Pengaturan` | — |
| 22 | `Feature/PenyesuaianStokFisikTest.php` | 9 | 9 | 49 | `EditBarangPersediaan`, `BarangPersediaan`, `MutasiStok`, `StokService` | Ya → Modul 2 |
| 23 | `Feature/PerlindunganHapusTest.php` | 20 | 20 | 67 | `ListAsetTetaps`, `ListBarangPersediaans`, `ListKategoris`, `ListTims`, `ListUsers`, `AsetTetap`, `BarangPersediaan`, `Kategori`, `RiwayatPenempatanAset`, `MutasiStok`, `StokService` | — |
| 24 | `Feature/PesanValidasiIndonesiaTest.php` | 7 | 7 | 209 | `CreateBarangPersediaan`, `CreateKategori`, `CreateTim`, `CreateUser` | — |
| 25 | `Feature/SandiFormPenggunaTest.php` | 4 | 4 | 23 | `EditUser`, `User` | — |
| 26 | `Feature/SinkronisasiAsetTetapTest.php` | 25 | 25 | 78 | `AsetTetap`, `Kategori`, `RiwayatPenempatanAset`, `Tim`, `HasilImpor`, `ImporAsetTetap`, `PembuatTemplate`, `MutasiAsetService` | Ya → Modul 3 |
| 27 | `Feature/StokTerkunciBacaSajaTest.php` | 4 | 4 | 21 | `CreateBarangPersediaan`, `EditBarangPersediaan`, `BarangPersediaan`, `Kategori` | — |
| 28 | `Feature/TandaTanganPenggunaTest.php` | 11 | 14 | 32 | `Pengaturan`, `TandaTangan` | — |
| 29 | `Unit/ExampleTest.php` | 1 | 1 | 1 | — (uji kerangka `assertTrue(true)`) | — |
| | **Jumlah** | **245** | **251** | **1496** | | |

### 1.2 Berkas lintas modul (modul utama tetap Modul 1)

| Berkas | Modul lain yang tersentuh | Alasan penempatan di Modul 1 |
|---|---|---|
| `AksesPanelTest` | Modul 4 | Menguji `User::canAccessPanel()` (akun aktif/nonaktif, termasuk sesi berjalan). Dasbor hanya dipakai sebagai halaman tujuan setelah masuk. |
| `KodeBarangBaruStokMasukTest` | Modul 2 | Dialog *Barang Baru* berada di halaman Stok Masuk, tetapi yang diuji adalah pembuatan **data induk** Barang Persediaan dan keunikan kodenya per kategori. |
| `KontakBantuanMasukTest` | Modul 4 | Menguji `Support\KontakBantuan` pada **halaman masuk** (2 tes membuka `/admin/login`, 4 tes menguji normalisasi nomor). Kelas yang sama dipakai Pusat Bantuan (Modul 4). |
| `NamaNipTerkunciPengaturanTest` | Modul 2 | 5 dari 6 tes menguji Pengaturan Akun dan form Pengguna; 1 tes memastikan nama pemohon di Katalog Barang tetap bebas diisi. |
| `PenempatanAsetFormTest` | Modul 3 | Menguji form **Aset Tetap** (data induk); 1 tes memastikan mutasi lewat BAST tetap memindahkan aset. |
| `PenyesuaianStokFisikTest` | Modul 2 | Menguji form Ubah **Barang Persediaan** (dialog persetujuan perubahan stok fisik); stok fisik dibaca modul permintaan. |
| `SinkronisasiAsetTetapTest` | Modul 3 | Menguji impor/sinkronisasi **data induk** Aset Tetap dan Tim Kerja; beberapa tes memastikan riwayat penempatan dan BAST tidak tersentuh. |

Berkas pendukung yang tidak mewakili satu menu tertentu tetapi dijalankan dari sisi
Admin/akun, sehingga dimasukkan ke Modul 1 agar tetap ikut baseline:
`Feature/ExampleTest` (halaman muka `/` merespons 200), `Unit/ExampleTest`
(uji kerangka `assertTrue(true)`, bukan uji fitur), dan `PemeliharaanDataTest`
(perintah cadangan/pemulihan data `Support\Cadangan` milik Admin Sistem).

### 1.3 Hasil — Langkah 1 (baseline, hanya berkas Modul 1)

Dijalankan dengan konfigurasi PHPUnit berisi hanya 29 berkas di atas.

| Driver | Tes dijalankan | Lulus | Gagal | Error | Assertion | Durasi |
|---|---|---|---|---|---|---|
| SQLite (`:memory:`) | 251 | 251 | 0 | 0 | 1496 | 1 mnt 20 dtk |
| MySQL 8.4.3 sementara (`127.0.0.1:3399`, `simpbi_uji_modul`) | 251 | 251 | 0 | 0 | 1496 | 1 mnt 08 dtk |

Pada langkah 2, 3, dan 4 (lihat dokumen modul berikutnya) ke-251 kasus Modul 1 ikut
dijalankan bersama modul lain dan tetap lulus seluruhnya:

| Langkah | Isi run | Kasus Modul 1 lulus — SQLite | Kasus Modul 1 lulus — MySQL |
|---|---|---|---|
| 2 | Modul 1 + 2 | 251 / 251 | 251 / 251 |
| 3 | Modul 1 + 2 + 3 | 251 / 251 | 251 / 251 |
| 4 | Modul 1 + 2 + 3 + 4 (seluruh suite) | 251 / 251 | 251 / 251 |

### 1.4 Kesimpulan regression Modul 1

**Layak.** Baseline 251 kasus (1496 assertion) lulus 100% di SQLite dan MySQL, dan
tetap lulus 100% ketika Modul 2, 3, dan 4 ditambahkan ke run yang sama. Tidak ada
kegagalan yang perlu dilaporkan.

---

## 2. White-Box Testing

### 2.1 Kelas yang diperiksa

Daftar tugas menyebut `UserForm.php` untuk penjaga Admin, dan
`BarangPersediaanForm.php`/`StokMasuk.php` untuk validasi kode barang unik. Setelah
kodenya dibaca, penjaga Admin ternyata **tidak** seluruhnya berada di `UserForm`:
`UserForm` hanya mengunci tampilan kolom, sedangkan penolakan di server berada di tiga
kelas ekuivalen. Keempatnya diperiksa:

| Kelas | Peran dalam penjagaan |
|---|---|
| `app/Filament/Resources/Users/Schemas/UserForm.php` | Mengunci kolom Peran dan Akun Aktif pada akun sendiri; aturan wajib-isi. |
| `app/Filament/Resources/Users/Pages/EditUser.php` | `beforeSave()`: penolakan di server (akun sendiri, Admin aktif terakhir); `afterSave()`: hash sesi. |
| `app/Models/User.php` | `alasanTidakDapatDihapus()`: penjaga hapus tunggal/massal, juga dipasang pada peristiwa `deleting`. |
| `app/Services/Impor/ImporPengguna.php` | `pesanPenjagaAdmin()`: penjaga yang sama untuk jalur impor berkas. |
| `app/Filament/Pages/StokMasuk.php` | `kodeBarangSudahDipakai()`, aturan form *Barang Baru*, `simpanBarangBaru()`. |
| `app/Filament/Resources/BarangPersediaans/Schemas/BarangPersediaanForm.php` | Form Buat/Ubah Barang Persediaan. |

### 2.2 Jalur kritis dan pemetaannya ke test

Status **Tercakup** berarti ada metode test yang isinya (bukan hanya namanya) benar-benar
memanggil jalur itu dan menegaskan hasilnya. Nomor baris mengacu pada kode saat ini.

**A. `UserForm`**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| UF-1 | `akunSendiri($record)` benar (baris 28–31) → `role` & `status_aktif` `disabled` + `dehydrated(false)` + keterangan `PESAN_AKUN_SENDIRI` (baris 86–88, 101–103) | Admin tidak bisa mengubah peran/status akunnya sendiri lewat tampilan | Tercakup | `AdminTidakMengunciDiriTest::test_admin_melihat_kolom_peran_dan_status_akun_sendiri_terkunci` |
| UF-2 | `akunSendiri` salah (form Buat, atau akun orang lain) → kolom aktif | Form tetap berfungsi normal | Tercakup | `AdminTidakMengunciDiriTest::test_form_buat_pengguna_tidak_terkunci_dan_berfungsi`, `::test_ubah_pengguna_lain_yang_bukan_admin_tetap_berfungsi` |
| UF-3 | `password` wajib hanya saat `create` (baris 73); kosong saat ubah tidak disimpan (baris 74) | Sandi lama bertahan bila dikosongkan | Tercakup | `PesanValidasiIndonesiaTest::test_form_pengguna_menampilkan_pesan_wajib_diisi_dan_email_dalam_bahasa_indonesia` (wajib saat buat), `SandiFormPenggunaTest::test_perubahan_tanpa_mengisi_sandi_tidak_menyentuh_apa_pun` |
| UF-4 | `email` unik `ignoreRecord` (baris 66) | Email ganda ditolak | Tercakup | `PesanValidasiIndonesiaTest::test_email_yang_sudah_terdaftar_dilaporkan_dalam_bahasa_indonesia` |
| UF-5 | `tim_id` wajib bila peran `tim`/`ketua_tim` (baris 95) | Akun Tim tidak dapat dibuat tanpa tim kerja lewat form | **Tidak tercakup** | — (hanya jalur impor yang diuji: `ImporPenggunaTest::test_peran_bertim_wajib_disertai_tim_kerja`) |

**B. `EditUser`**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| EU-1 | Akun sendiri **dan** peran/status di muatan berubah (baris 36–39) → `tolakPerubahan()` + `halt()` | Muatan Livewire yang dimodifikasi tetap ditolak di server | Tercakup | `AdminTidakMengunciDiriTest::test_muatan_yang_dimodifikasi_pada_akun_sendiri_ditolak_dan_tidak_berubah`, `::test_hanya_peran_atau_hanya_status_yang_dimodifikasi_juga_ditolak` |
| EU-2 | Akun sendiri, peran/status tidak berubah → lolos | Data lain akun sendiri tetap bisa disunting | Tercakup | `AdminTidakMengunciDiriTest::test_admin_tetap_dapat_mengubah_data_lain_akun_sendiri` |
| EU-3 | Admin aktif terakhir diturunkan/dinonaktifkan (baris 41–47) → "Harus ada minimal satu Admin aktif." | Sistem tidak kehilangan Admin aktif | Tercakup | `AdminTidakMengunciDiriTest::test_perubahan_yang_menghabiskan_admin_aktif_ditolak` |
| EU-4 | Masih ada Admin aktif lain → perubahan pada Admin lain diizinkan | Pengelolaan Admin lain tidak terhalang | Tercakup | `AdminTidakMengunciDiriTest::test_admin_dapat_menonaktifkan_admin_lain_selama_masih_ada_admin_aktif_lain`, `::test_admin_dapat_mengubah_peran_admin_lain_selama_masih_ada_admin_aktif_lain` |
| EU-5 | `afterSave()`: akun sendiri + sandi berubah → `perbaruiHashSesi()` (baris 60–65); akun lain → sesi Admin tidak disentuh | Admin tidak terlempar keluar; sesi lama pengguna lain putus | Tercakup | `SandiFormPenggunaTest::test_admin_mengubah_sandi_akunnya_sendiri_tetap_terautentikasi`, `::test_admin_mengubah_sandi_pengguna_lain_tidak_menyentuh_sesinya_dan_memutus_sesi_lama_pengguna_itu` |

**C. `User::alasanTidakDapatDihapus()`**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| UD-1 | Kumpulan memuat akun yang sedang masuk (baris 78–80) | Tidak bisa menghapus akun sendiri, termasuk dalam hapus massal dan jalur langsung `delete()` | Tercakup | `HapusAkunTerjagaTest::test_admin_tidak_dapat_menghapus_akun_sendiri_walau_ada_admin_aktif_lain`, `::test_hapus_massal_berisi_akun_sendiri_ditolak_seluruhnya`, `::test_jalur_langsung_menolak_penghapusan_akun_yang_sedang_masuk` |
| UD-2 | Penghapusan menyisakan nol Admin aktif (baris 82–89) | Admin aktif terakhir tidak dapat dihapus | Tercakup | `HapusAkunTerjagaTest::test_satu_satunya_admin_aktif_tidak_dapat_dihapus_oleh_pelaku_nonaktif`, `::test_jalur_langsung_menolak_penghapusan_satu_satunya_admin_aktif`, `::test_hapus_massal_yang_menghabiskan_admin_aktif_ditolak_seluruhnya` |
| UD-3 | Tidak ada alasan (`null`) → boleh dihapus | Penghapusan sah tidak terhalang | Tercakup | `HapusAkunTerjagaTest::test_admin_dapat_menghapus_admin_lain_selama_masih_ada_admin_aktif_lain`, `::test_hapus_massal_beberapa_admin_lain_berhasil_selama_pelaku_tetap_ada`, `::test_pengguna_biasa_tetap_dapat_dihapus_tunggal_dan_massal` |

**D. `ImporPengguna::pesanPenjagaAdmin()`**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| IP-1 | Pengguna belum ada (baris 274) → `null` | Akun baru tetap dapat diimpor | Tercakup | `ImporPenggunaPenjagaAdminTest::test_impor_pengguna_biasa_dan_akun_baru_tetap_berhasil` |
| IP-2 | Baris mengubah peran/status akun pengimpor sendiri (baris 278–281) | Baris ditolak, baris lain tetap diproses | Tercakup | `ImporPenggunaPenjagaAdminTest::test_baris_yang_mengubah_peran_pengimpor_sendiri_ditolak`, `::test_baris_yang_menonaktifkan_pengimpor_sendiri_ditolak`; kolom lain tetap boleh: `::test_kolom_lain_pada_akun_pengimpor_tetap_boleh_diubah` |
| IP-3 | Baris menurunkan/menonaktifkan Admin aktif terakhir (baris 283–289) | Minimal satu Admin aktif tetap ada, apa pun urutan barisnya | Tercakup | `ImporPenggunaPenjagaAdminTest::test_baris_yang_menghabiskan_admin_aktif_ditolak_dan_baris_lain_tetap_diproses`, `::test_urutan_baris_berbeda_tetap_menyisakan_satu_admin_aktif`, `::test_admin_yang_sudah_nonaktif_dapat_diturunkan_karena_tidak_mengurangi_admin_aktif` |

**E. Validasi kode barang unik (`StokMasuk` dan `BarangPersediaanForm`)**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| KB-1 | Aturan form *Barang Baru*: `kodeBarangSudahDipakai()` benar (baris 389–394, 458–462), termasuk kode berspasi ujung | Kode ganda dalam kategori yang sama ditolak sebelum menyimpan | Tercakup | `KodeBarangBaruStokMasukTest::test_kode_yang_sama_pada_kategori_yang_sama_terdeteksi_termasuk_spasi_ujung` |
| KB-2 | `kodeBarangSudahDipakai()` salah (kategori lain atau kode baru) | Kode yang sama boleh di kategori lain | Tercakup | `KodeBarangBaruStokMasukTest::test_kode_yang_sama_pada_kategori_lain_atau_kode_baru_diterima` |
| KB-3 | `simpanBarangBaru()` berhasil: kode dipangkas, stok 0 (baris 401–413) | Barang baru lahir berstok nol | Tercakup | `KodeBarangBaruStokMasukTest::test_barang_baru_berhasil_dibuat_berstok_nol_dengan_kode_terpangkas` |
| KB-4 | `simpanBarangBaru()` menangkap `UniqueConstraintViolationException` → notifikasi + `Halt` (baris 414–421) | Bentrok yang lolos validasi tampil sebagai pesan, bukan SQL | Tercakup | `KodeBarangBaruStokMasukTest::test_pelanggaran_unique_yang_lolos_validasi_menjadi_pesan_bukan_sql` |
| KB-5 | `BarangPersediaanForm`: kolom `kode_barang` (baris 26–29) **tidak** memiliki aturan unik; keunikan hanya dijaga indeks basis data `unique(['kategori_id','kode_barang'])` pada migration `2026_08_26_000004`. Jalur Buat/Ubah Barang Persediaan dengan kode ganda | Menurut pembacaan kode, bentrok akan berakhir sebagai galat basis data, bukan pesan validasi | **Tidak tercakup** | — (tidak ada test yang mengirim kode ganda lewat `CreateBarangPersediaan`/`EditBarangPersediaan`; perilakunya **belum diverifikasi dinamis** karena tugas ini tidak menambah test) |
| KB-6 | `BarangPersediaanForm`: `stok_hold` `disabled()` + `dehydrated(false)` (baris 50–59) | Stok Terkunci tidak dapat diubah lewat form | Tercakup | `StokTerkunciBacaSajaTest::test_ubah_tidak_mengubah_stok_terkunci_walau_muatan_dimodifikasi`, `::test_barang_baru_berhasil_dibuat_dengan_stok_terkunci_nol` |

Catatan KB-5: temuan audit A-021 (Batch 2) hanya menambahkan aturan unik pada dialog
*Barang Baru* di Stok Masuk; form Barang Persediaan milik Admin/Kasubbag tidak ikut
diubah (`laporan-audit.md` baris A-021, `laporan-final.md` A-021). Dokumen ini hanya
mencatatnya — tidak ada kode yang diubah.

### 2.3 Alat ukur cakupan

Tidak tersedia. PHP 8.3.33 (Laragon) tidak memuat `pcov` maupun `xdebug`, dan tidak ada
berkas DLL keduanya di folder `ext`. `phpdbg.exe` ada, tetapi PHPUnit 11.5.56
(`phpunit/php-code-coverage`) hanya mendukung driver PCOV dan Xdebug. Sesuai batas tugas,
tidak ada driver baru yang dipasang. Karena itu cakupan diukur **manual**: setiap jalur
di atas ditelusuri ke isi metode test yang memanggilnya.

### 2.4 Kesimpulan white-box Modul 1

**Layak Bersyarat.** Dari 22 jalur kritis, 20 tercakup dan seluruh test yang
mencakupnya lulus (251/251 kasus Modul 1 di kedua driver). Dua jalur tidak tercakup dan
diteruskan ke pengujian Black-Box:

1. **UF-5** — `tim_id` wajib untuk peran Tim/Ketua Tim pada form Pengguna.
2. **KB-5** — kode barang ganda lewat form Buat/Ubah Barang Persediaan (tanpa aturan
   unik di form; hanya indeks basis data).
