# Modul 3 — Pencatatan Administratif Mutasi Aset

Cakupan fitur: Mutasi Aset (BAST — pembuatan, pengesahan Kasubbag Umum, konfirmasi
penerimaan Ketua Tim tujuan), Aset Tetap Tim Saya, dan Riwayat Penempatan Aset.

"Mutasi" di sini berarti perpindahan penempatan **aset tetap** antar-tim kerja, bukan
pergerakan stok barang persediaan. Modul ini bergantung pada Modul 1 (aset tetap, tim
kerja, akun penanda tangan) dan berbagi pola layanan dengan Modul 2 (`StokService::pesanAturan`
dipakai untuk memilah pesan bisnis dan galat teknis). Uji regresinya menjalankan berkas
Modul 1, 2, dan 3 bersama-sama.

Semua angka berasal dari eksekusi nyata pada 23 September 2026.

---

## 1. Regression Testing

### 1.1 Berkas uji yang dipetakan ke Modul 3

| No | Berkas uji | Metode | Kasus dieksekusi | Assertion | Kelas aplikasi yang diimpor | Lintas modul |
|---|---|---|---|---|---|---|
| 1 | `Feature/AsetTetapTimSayaTest.php` | 7 | 10 | 22 | `AsetTetapTimSaya` | — |
| 2 | `Feature/BastMutasiAsetAksesTest.php` | 3 | 3 | 5 | `BastMutasiAsetResource`, `ListBastMutasiAsets` | — |
| 3 | `Feature/BastTandaTanganTersimpanTest.php` | 3 | 3 | 11 | `BastMutasiAset`, `MutasiAsetService`, `TandaTangan` | — |
| 4 | `Feature/DokumenBastTest.php` | 7 | 7 | 17 | `BastMutasiAset`, `DokumenBastService` | — |
| 5 | `Feature/MutasiAsetPenempatanTest.php` | 18 | 18 | 124 | `CreateBastMutasiAset`, `ListBastMutasiAsets`, `BastMutasiAset`, `RiwayatPenempatanAset`, `Tim`, `User`, `MutasiAsetService` | — |
| 6 | `Feature/PenggunaTanpaTimTest.php` | 3 | 6 | 26 | `AsetTetapTimSaya`, `KondisiAsetTetapTim` | Ya → Modul 4 |
| 7 | `Feature/PenomoranBastTest.php` | 6 | 6 | 20 | `CreateBastMutasiAset`, `BastMutasiAset`, `Tim`, `User`, `DokumenBastService`, `MutasiAsetService` | — |
| 8 | `Feature/RiwayatPenempatanAsetTest.php` | 20 | 20 | 107 | `CreateAsetTetap`, `EditAsetTetap`, `ListAsetTetaps`, `AsetTetap`, `Kategori`, `RiwayatPenempatanAset`, `MutasiAsetService` | Ya → Modul 1 |
| 9 | `Feature/StatusBastDanAsetAktifTest.php` | 9 | 9 | 45 | `CreateBastMutasiAset`, `ListBastMutasiAsets`, `BastMutasiAset`, `Notifikasi`, `RiwayatPenempatanAset`, `Tim`, `User`, `MutasiAsetService` | — |
| | **Jumlah** | **76** | **82** | **377** | | |

### 1.2 Berkas lintas modul (modul utama tetap Modul 3)

| Berkas | Modul lain yang tersentuh | Alasan penempatan di Modul 3 |
|---|---|---|
| `PenggunaTanpaTimTest` | Modul 4 | Menguji halaman **Aset Tetap Tim Saya** dan widget `KondisiAsetTetapTim` bagi pengguna tanpa tim; keduanya membaca penempatan aset. |
| `RiwayatPenempatanAsetTest` | Modul 1 | Riwayat penempatan awal terbentuk ketika aset dibuat/disunting lewat form **Aset Tetap** (Modul 1); yang diuji adalah isi dan tampilan **riwayat penempatan**. |

Berkas Modul 1 yang juga menyentuh Modul 3 (`PenempatanAsetFormTest`,
`SinkronisasiAsetTetapTest`) dan berkas Modul 2 yang menyentuh BAST
(`DokumenDiDiskPrivatTest`, `PindahDokumenTest`, `UnduhanDokumenTest`) tercatat pada
dokumen modul masing-masing.

### 1.3 Hasil — Langkah 3 (Modul 1 + 2 + 3 bersama-sama)

Konfigurasi PHPUnit berisi 58 berkas dalam tiga *testsuite*.

| Driver | Tes dijalankan | Lulus | Gagal | Error | Assertion | Durasi |
|---|---|---|---|---|---|---|
| SQLite (`:memory:`) | 516 | 516 | 0 | 0 | 4537 | 4 mnt 22 dtk |
| MySQL 8.4.3 sementara | 516 | 516 | 0 | 0 | 4537 | 5 mnt 31 dtk |

Rincian per modul di dalam run langkah 3:

| Modul | Kasus | Lulus — SQLite | Lulus — MySQL | Gagal (kedua driver) |
|---|---|---|---|---|
| Modul 1 (regresi) | 251 | 251 / 251 | 251 / 251 | 0 |
| Modul 2 (regresi) | 183 | 183 / 183 | 183 / 183 | 0 |
| Modul 3 (baru) | 82 | 82 / 82 | 82 / 82 | 0 |

Pada langkah 4 (seluruh suite) ke-82 kasus Modul 3 kembali lulus 82/82 di kedua driver.

### 1.4 Kesimpulan regression Modul 3

**Layak.** Pada run gabungan Modul 1 + 2 + 3, kasus Modul 1 (251) dan Modul 2 (183)
tetap lulus 100% di SQLite dan MySQL; ke-82 kasus Modul 3 juga lulus.

---

## 2. White-Box Testing

### 2.1 Kelas yang diperiksa

| Kelas | Tanggung jawab |
|---|---|
| `app/Services/MutasiAsetService.php` | Penomoran BAST, pemeriksaan penempatan sebelum pembuatan, kunci status saat Sahkan/Konfirmasi, pemindahan penempatan dan riwayat. |
| `app/Filament/Resources/BastMutasiAsets/Pages/CreateBastMutasiAset.php` | **Kelas ekuivalen** untuk "retry bentrok": perulangan pembuatan BAST dengan nomor baru saat terjadi `UniqueConstraintViolationException` berada di `handleRecordCreation()` halaman ini, bukan di service. |

### 2.2 Jalur kritis dan pemetaannya ke test

**A. Penomoran dan retry bentrok**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| MA-1 | `nomorBaru()`: nomor terbesar tahun berjalan + 1, format `BAST-YYYY-NNNN` (baris 21–35) | Celah nomor dan nomor tahun lain tidak menyebabkan bentrok | Tercakup | `PenomoranBastTest::test_nomor_pertama_tahun_ini_adalah_0001`, `::test_celah_nomor_tidak_menyebabkan_bentrok`, `::test_nomor_tahun_lain_tidak_ikut_dihitung`, `::test_pembuatan_lewat_form_setelah_ada_celah_berhasil` |
| MA-2 | `handleRecordCreation()`: bentrok UNIQUE, percobaan < 3 → nomor baru lalu ulangi (baris 48, 59) | Dua pembuatan bersamaan tetap menghasilkan dua BAST | Tercakup | `PenomoranBastTest::test_bentrok_sekali_diulang_dengan_nomor_berikutnya` |
| MA-3 | Bentrok pada percobaan ke-3 → notifikasi "Nomor BAST gagal diterbitkan" + `halt()` (baris 49–57) | Tidak ada galat basis data mentah; tidak ada BAST tersimpan | Tercakup | `PenomoranBastTest::test_bentrok_berulang_berhenti_setelah_tiga_kali_dengan_pesan_umum` |
| MA-4 | `RuntimeException` aturan bisnis → notifikasi "BAST tidak dapat dibuat" + `halt()` (baris 60–74) | Penolakan tampil sebagai pesan | Tercakup | `MutasiAsetPenempatanTest::test_muatan_dengan_tim_asal_berbeda_ditolak_dan_tidak_membentuk_bast`, `::test_formulir_usang_karena_aset_sudah_pindah_ditolak` |
| MA-5 | Galat teknis (`pesanAturan()` = `null`) → dilempar ulang (baris 64–66) | Galat teknis tidak disamarkan sebagai penolakan bisnis | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ma5_galat_teknis_tetap_menjalar_sesuai_desain_bukan_disamarkan` — diverifikasi manual 23 September 2026: `DokumenBastService::buat()` (dipanggil dari `CreateBastMutasiAset::afterCreate()`, di luar `try/catch` milik `handleRecordCreation()`) yang melempar `\RuntimeException` murni menjalar tanpa tertangkap; tidak ditemukan bug, perilakunya sesuai maksud kode. |

> **Revisi 23 September 2026 (eksekusi pembalikan alur, lihat
> `docs/audit/rencana-pembalikan-alur-mutasi-aset.md`).** Urutan alur BAST
> dibalik: Buat → `menunggu_konfirmasi` → **Konfirmasi Penerimaan** (Ketua Tim
> tujuan; aset berpindah di sini) → `menunggu_pengesahan` → **Sahkan**
> (Kasubbag; finalisasi, langkah terakhir) → `selesai_administratif`. Nomor
> baris pada tabel B dan C di bawah sudah diselaraskan dengan
> `app/Services/MutasiAsetService.php` versi baru, dan nama test method
> mengikuti berkas uji yang sudah ditulis ulang (lihat rencana bagian B untuk
> pemetaan lengkap tiap test). **Ini bukan audit ulang menyeluruh** —
> deskripsi jalur MA-1 s.d. MA-5 (penomoran) dan MA-9/MA-10 (aset
> tidak-ditemukan/nonaktif) tidak berubah maknanya dan tidak ditinjau ulang
> baris-per-baris di sini; sesi pengujian berikutnya sebaiknya memetakan ulang
> tabel ini secara lengkap dari nol sebagai bagian regresi formal, bukan
> mengandalkan revisi cepat ini sebagai sumber kebenaran akhir.

**B. Pemeriksaan penempatan sebelum pembuatan**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| MA-6 | `pesanAsetTidakDapatDimutasi()`: aset tanpa penempatan (baris 61–63) | Aset tanpa penempatan tidak dapat dimutasi | Tercakup | `MutasiAsetPenempatanTest::test_memilih_aset_tanpa_penempatan_menampilkan_peringatan`, `::test_aset_tanpa_penempatan_ditolak_pada_form` |
| MA-7 | `pesanAsetTidakDapatDimutasi()`: masih ada BAST **`menunggu_konfirmasi`** (bukan lagi `menunggu_pengesahan`) dari penempatan sekarang → pesan blokir (baris 65–74) | Tidak ada dua BAST menunggu untuk aset yang sama | Tercakup | `MutasiAsetPenempatanTest::test_form_menampilkan_kesalahan_pada_aset_yang_masih_punya_bast_menunggu`, `::test_skenario_audit_asal_salah_ditolak_asal_benar_dibuat_bast_kedua_diblokir` |
| MA-8 | BAST berstatus lain, atau BAST usang yang `tim_asal_id`-nya bukan penempatan sekarang → `null` (tidak memblokir) | Alur tanpa jalur pembatalan tidak terkunci selamanya | Tercakup | `MutasiAsetPenempatanTest::test_bast_yang_sudah_dikonfirmasi_atau_tuntas_tidak_memblokir`, `::test_bast_usang_yang_asalnya_bukan_penempatan_sekarang_tidak_memblokir`, `::test_bast_usang_yang_ditolak_tidak_memblokir_bast_baru_untuk_aset_yang_sama` |
| MA-8b (baru) | `pesanTandaTanganBelumLengkap()` (E): Ketua Tim asal/tujuan belum bertanda tangan → pesan yang menyebut nama tim | Ketua Tim yang belum bertanda tangan disebut jelas; BAST tidak terbentuk | Tercakup | `PopUpBastTanpaKetikNipTest::test_server_menolak_pembuatan_bila_ketua_tim_tujuan_belum_bertanda_tangan`, `::test_server_mengizinkan_pembuatan_bila_kedua_ketua_tim_sudah_bertanda_tangan`, `::test_peringatan_tanda_tangan_tampil_saat_tim_tujuan_belum_bertanda_tangan_dan_hilang_setelah_lengkap` |
| MA-9 | `periksaPembuatan()`: aset tidak ditemukan → "Aset tidak ditemukan." | Muatan berisi aset fiktif ditolak | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ma9_aset_id_fiktif_menampilkan_pesan_validasi_bukan_galat_mentah` — temuan 23 September 2026 (lihat catatan asal di bawah tabel) masih berlaku: `aset_id` fiktif tidak sampai memicu baris ini, validasi Select `BastMutasiAsetForm` menolaknya lebih dulu. |
| MA-10 | `periksaPembuatan()`: aset nonaktif | BAST untuk aset nonaktif ditolak di server | Tercakup | `StatusBastDanAsetAktifTest::test_pembuatan_bast_untuk_aset_nonaktif_ditolak_di_server`, `::test_muatan_dimodifikasi_dengan_aset_nonaktif_tidak_membentuk_bast`; aset aktif tetap lolos: `::test_aset_aktif_tidak_terpengaruh` |
| MA-11 | `periksaPembuatan()`: aset tanpa penempatan | Server menolak walau form dilewati | Tercakup | `MutasiAsetPenempatanTest::test_server_menolak_aset_tanpa_penempatan_tanpa_bergantung_pada_form` |
| MA-12 | `periksaPembuatan()`: `tim_asal_id` ≠ penempatan aset | Muatan dimodifikasi/formulir usang ditolak | Tercakup | `MutasiAsetPenempatanTest::test_muatan_dengan_tim_asal_berbeda_ditolak_dan_tidak_membentuk_bast`, `::test_formulir_usang_karena_aset_sudah_pindah_ditolak` |
| MA-13 | `periksaPembuatan()`: BAST menunggu masih ada | Blokir berlaku di server | Tercakup | `MutasiAsetPenempatanTest::test_server_memblokir_bast_kedua_tanpa_bergantung_pada_form` |

**C. Kunci status saat Konfirmasi/Sahkan**

Urutan dibalik: guard *input* tiap method **tidak berubah** (`sahkan()` tetap
mensyaratkan `menunggu_pengesahan`; `konfirmasi()` tetap mensyaratkan
`menunggu_konfirmasi`) — yang tertukar adalah status *keluaran* dan efek
sampingnya (pemindahan aset kini di `konfirmasi()`, finalisasi dokumen kini di
`sahkan()`).

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| MA-14 | `kunciDanPastikanStatus()`: status baris terkunci ≠ yang diharapkan (baris 149–156, tidak berubah) | Sahkan/konfirmasi ganda atau pada status salah ditolak tanpa tulisan tambahan | Tercakup | `StatusBastDanAsetAktifTest::test_sahkan_dua_kali_berturut_turut_yang_kedua_ditolak_tanpa_tulisan_tambahan`, `::test_sahkan_pada_status_selain_menunggu_pengesahan_ditolak`, `::test_konfirmasi_penerimaan_pada_status_selain_menunggu_konfirmasi_ditolak`, `::test_konfirmasi_dua_kali_yang_kedua_ditolak` |
| MA-15 | `kunciDanPastikanStatus()`: baris BAST sudah tidak ada (`! $terkini`) | Pesan yang sama | **Tidak tercakup** | — |
| MA-16 | `konfirmasi()` (dipindah dari `sahkan()` lama): penempatan aset berubah sejak BAST dibuat → `RuntimeException` sebelum menulis (baris 200–223) | BAST usang tidak memindahkan aset; riwayat tidak berubah | Tercakup | `MutasiAsetPenempatanTest::test_konfirmasi_ditolak_bila_penempatan_berubah_dan_tidak_ada_yang_berubah`, `::test_konfirmasi_di_layanan_melempar_pesan_bisnis_sebelum_menulis_apa_pun` |
| MA-17 | `konfirmasi()` (dipindah): aset tidak ada → `ModelNotFoundException` tidak ditelan | Galat teknis tidak disamarkan | Tercakup | `MutasiAsetPenempatanTest::test_galat_teknis_saat_konfirmasi_tidak_ditelan` |
| MA-18 | `konfirmasi()` normal (dipindah): status `menunggu_pengesahan`, penempatan pindah, riwayat lama ditutup, riwayat mutasi baru dibuka, dokumen dibentuk ulang dengan TTD penerima | Riwayat penempatan berurutan tanpa celah | Tercakup | `MutasiAsetPenempatanTest::test_konfirmasi_normal_tetap_berhasil`, `::test_setelah_bast_pertama_dikonfirmasi_bast_baru_dari_tim_tujuan_diperbolehkan_dan_riwayat_berurutan`, `RiwayatPenempatanAsetTest::test_tampilan_riwayat_memuat_baris_mutasi_beserta_nomor_bast` |
| MA-19 | `sahkan()` normal (kini finalisasi murni, langkah terakhir): `selesai_administratif`, dokumen dibentuk ulang dengan e-TTD Kasubbag | Mutasi tuntas; BAST memuat e-TTD Kasubbag | Tercakup | `StatusBastDanAsetAktifTest::test_alur_normal_konfirmasi_lalu_sahkan_tetap_berhasil`, `BastTandaTanganTersimpanTest::test_konfirmasi_membentuk_ulang_dokumen_dengan_signature_penerima` |
| MA-20 | Aksi Konfirmasi/Sahkan di tabel: penolakan layanan tampil sebagai notifikasi, bukan galat mentah | Pengguna melihat pesan yang dapat ditindaklanjuti | Tercakup | `StatusBastDanAsetAktifTest::test_aksi_konfirmasi_menampilkan_penolakan_sebagai_notifikasi_bukan_galat_mentah` |
| MA-21 (baru) | Dialog Sahkan/Konfirmasi (C, mengganti rencana "ketik NIP"): rincian BAST wajib tampil, tombol footer tuntas sekali klik tanpa kolom isian | Tidak ada aksi tanpa sengaja dari baris tabel; tidak ada langkah konfirmasi kedua | Tercakup | `PopUpBastTanpaKetikNipTest::test_dialog_sahkan_menampilkan_rincian_bast_tanpa_kolom_ketik_nip`, `::test_dialog_konfirmasi_menampilkan_rincian_bast_tanpa_kolom_ketik_nip`, `::test_sahkan_tuntas_dengan_satu_kali_callaction_tanpa_data_isian`, `::test_konfirmasi_tuntas_dengan_satu_kali_callaction_tanpa_data_isian` |
| MA-22 (baru) | Pengisian otomatis `pihak_penyerah`/`pihak_penerima` dari nama Ketua Tim asal/tujuan (D, G.3) saat pembuatan | Kolom NOT NULL tetap terisi tanpa diminta di form | Tercakup | `PopUpBastTanpaKetikNipTest::test_pihak_penyerah_dan_penerima_terisi_otomatis_dari_nama_ketua_tim`, `::test_form_buat_tidak_lagi_meminta_pihak_penyerah_dan_penerima` |

### 2.3 Alat ukur cakupan

Tidak tersedia (tidak ada `pcov`/`xdebug`; `phpdbg` tidak didukung PHPUnit 11; tidak
ada driver baru yang dipasang). Cakupan diukur manual seperti tabel di atas.

Catatan driver: SQLite tidak mengenal kunci baris, sehingga `lockForUpdate()` pada MA-9 s.d.
MA-19 baru benar-benar mengunci di MySQL. Seluruh test di atas lulus di **kedua** driver,
tetapi tidak ada test yang mensimulasikan dua transaksi bersamaan (paralel sungguhan);
perilaku kunci di bawah konkurensi nyata belum diuji.

### 2.4 Kesimpulan white-box Modul 3

**Layak Bersyarat.** Dari 20 jalur kritis, 17 tercakup dan seluruh test yang
mencakupnya lulus (82/82 kasus Modul 3 di kedua driver). Tiga jalur tidak tercakup dan
diteruskan ke pengujian Black-Box:

1. **MA-5** — galat teknis saat pembuatan BAST dilempar ulang, bukan ditampilkan sebagai
   penolakan bisnis.
2. **MA-9** — pembuatan BAST dengan `aset_id` yang tidak ada.
3. **MA-15** — Sahkan/Konfirmasi atas BAST yang barisnya sudah hilang.

Ditambah satu catatan non-jalur: konkurensi sungguhan (dua Kasubbag menekan Sahkan
bersamaan) belum diuji di MySQL.
