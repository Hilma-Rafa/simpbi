# Modul 4 — Dashboard, Monitoring & Pelaporan

Cakupan fitur: dasbor per peran (Admin Sistem, Kasubbag Umum, Petugas Gudang, Ketua
Tim, Tim), Riwayat (empat jenis), Ekspor, dan Pusat Bantuan. Termasuk pula notifikasi
(lonceng dalam aplikasi dan WhatsApp) sebagai sarana pemantauan.

Modul ini berada di hilir: ia membaca data yang dihasilkan Modul 1–3. Uji regresinya
adalah seluruh suite (Modul 1 + 2 + 3 + 4).

Semua angka berasal dari eksekusi nyata pada 23 September 2026.

---

## 1. Regression Testing

### 1.1 Berkas uji yang dipetakan ke Modul 4

| No | Berkas uji | Metode | Kasus dieksekusi | Assertion | Kelas aplikasi yang diimpor | Lintas modul |
|---|---|---|---|---|---|---|
| 1 | `Feature/AvatarFontLokalTest.php` | 7 | 7 | 36 | `Pengaturan` | Ya → Modul 1 |
| 2 | `Feature/BilahAtasTest.php` | 17 | 21 | 186 | `Dashboard`, `Pengaturan`, `PusatBantuan` | Ya → Modul 1 |
| 3 | `Feature/DashboardTimWidgetsTest.php` | 7 | 7 | 16 | `KondisiAsetTetapTim`, `PolaPermintaan`, `StatusPermintaanTim`, `TrenKonsumsiTim`, `StokService` | — |
| 4 | `Feature/IstilahKonsistenTest.php` | 10 | 10 | 31 | `Riwayat`, `ListBastMutasiAsets`, `ListPermintaanBarangs`, `PerluTindakan`, `PermintaanBarang`, `RiwayatPersetujuan` | Ya → Modul 2 |
| 5 | `Feature/KondisiStokTautanTest.php` | 4 | 4 | 28 | `BarangPersediaanResource`, `KondisiStok`, `Kategori` | — |
| 6 | `Feature/MonitoringPolaPermintaanTest.php` | 18 | 18 | 72 | `Dashboard`, `BarangPalingDiminta`, `PermintaanPerTim`, `TrenKonsumsiKategori`, `Kategori`, `MutasiStok`, `Tim`, `User`, `StokService` | — |
| 7 | `Feature/NotifikasiMutasiAsetTest.php` | 6 | 6 | 15 | `Notifikasi`, `NotifikasiService` | Ya → Modul 3 |
| 8 | `Feature/NotifikasiTautanRincianTest.php` | 2 | 2 | 6 | `PermintaanBarangResource`, `LoncengNotifikasi`, `Notifikasi`, `PermintaanBarang` | Ya → Modul 2 |
| 9 | `Feature/PengalihanWhatsAppTest.php` | 7 | 7 | 11 | `KirimPesanWhatsApp`, `Notifikasi`, `User`, `NotifikasiService`, `PengirimWhatsApp` | — |
| 10 | `Feature/PengirimanFonnteTest.php` | 16 | 21 | 37 | `KirimPesanWhatsApp`, `Notifikasi`, `User`, `PengirimanGagal`, `PengirimFonnte`, `PengirimWhatsApp` | — |
| 11 | `Feature/PengirimanWhatsAppTest.php` | 24 | 40 | 73 | `KirimPesanWhatsApp`, `Notifikasi`, `User`, `NotifikasiService`, `PengirimanGagal`, `PengirimCatat`, `PengirimOpenWa`, `PengirimWhatsApp` | — |
| 12 | `Feature/PerluTindakanWidgetTest.php` | 6 | 6 | 9 | `PerluTindakan`, `PermintaanBarang` | — |
| 13 | `Feature/PusatBantuanTest.php` | 14 | 14 | 46 | `Dashboard`, `PusatBantuan` | — |
| 14 | `Feature/RiwayatNotifikasiTest.php` | 7 | 7 | 24 | `Riwayat`, `KirimPesanWhatsApp`, `Notifikasi` | — |
| 15 | `Feature/RiwayatTabTest.php` | 4 | 4 | 11 | `KartuKendali`, `Riwayat`, `BarangPerluPerhatian` | — |
| 16 | `Feature/SembunyikanNotifikasiTest.php` | 8 | 8 | 27 | `LoncengNotifikasi`, `Notifikasi`, `User` | — |
| | **Jumlah** | **157** | **182** | **628** | | |

### 1.2 Berkas lintas modul (modul utama tetap Modul 4)

| Berkas | Modul lain yang tersentuh | Alasan penempatan di Modul 4 |
|---|---|---|
| `AvatarFontLokalTest` | Modul 1 | Menguji kerangka tampilan panel (avatar inisial di bilah atas, font lokal); halaman Pengaturan hanya dipakai sebagai halaman yang dibuka. |
| `BilahAtasTest` | Modul 1 | Menguji bilah atas dasbor (urutan tombol tema, lonceng, avatar; menu profil; dialog Keluar). Menu Pengaturan dan Keluar menyentuh Modul 1, tetapi seluruh tes dijalankan pada halaman Dashboard dan menyangkut navigasi panel. |
| `IstilahKonsistenTest` | Modul 2 | Keseragaman label status dan format tanggal pada dasbor, Riwayat, panel Perlu Tindakan, dialog rincian, dan daftar permintaan. |
| `NotifikasiMutasiAsetTest` | Modul 3 | Menguji **penerima** `NotifikasiService::bastBerubah()` untuk tiap status BAST. |
| `NotifikasiTautanRincianTest` | Modul 2 | Tautan lonceng menuju pop-up rincian permintaan. |

### 1.3 Hasil — Langkah 4 (Modul 1 + 2 + 3 + 4 = seluruh suite)

Langkah ini adalah run suite penuh dengan `phpunit.xml` proyek (74 berkas). Isinya
identik dengan gabungan keempat modul: pemetaan 74 berkas di dokumen ini dicocokkan
1:1 dengan daftar berkas pada JUnit hasil run (tidak ada berkas yang terlewat atau ganda).

| Driver | Tes dijalankan | Lulus | Gagal | Error | Assertion | Durasi |
|---|---|---|---|---|---|---|
| SQLite (`:memory:`) | 698 | 698 | 0 | 0 | 5165 | 7 mnt 34 dtk |
| MySQL 8.4.3 sementara | 698 | 698 | 0 | 0 | 5165 | 6 mnt 08 dtk |

Rincian per modul di dalam run langkah 4 (identik di kedua driver, termasuk jumlah
assertion per berkas):

| Modul | Kasus | Assertion | Lulus — SQLite | Lulus — MySQL | Gagal |
|---|---|---|---|---|---|
| Modul 1 (regresi) | 251 | 1496 | 251 | 251 | 0 |
| Modul 2 (regresi) | 183 | 2664 | 183 | 183 | 0 |
| Modul 3 (regresi) | 82 | 377 | 82 | 82 | 0 |
| Modul 4 (baru) | 182 | 628 | 182 | 182 | 0 |
| **Jumlah** | **698** | **5165** | **698** | **698** | **0** |

### 1.4 Kesimpulan regression Modul 4

**Layak.** Pada run seluruh suite, kasus Modul 1 (251), Modul 2 (183), dan Modul 3 (82)
tetap lulus 100% di SQLite dan MySQL, dan ke-182 kasus Modul 4 juga lulus.

Catatan cakupan fitur (bukan kegagalan): **Ekspor Riwayat** (aksi `eksporPdf` dan ekspor
berkas sebar pada `Pages/Concerns/MengeksporRiwayat.php`) tidak dijalankan oleh test mana
pun — `IstilahKonsistenTest::test_halaman_riwayat_menyebut_ekspor_bukan_export` hanya
memeriksa labelnya. Ekspor yang benar-benar diuji isinya adalah ekspor Kartu Kendali
(Modul 2, `KartuKendaliTest`).

---

## 2. White-Box Testing

### 2.1 Kelas yang diperiksa

| Kelas | Tanggung jawab |
|---|---|
| `app/Services/NotifikasiService.php` | Penentuan penerima per peristiwa (permintaan, stok, BAST), penerbitan baris dalam aplikasi dan WhatsApp. |
| `app/Filament/Widgets/*.php` (17 widget) | Gerbang `canView()` per peran dan kueri yang membatasi data menurut peran/tim. |

Temuan saat menelusuri widget: Filament memuat widget secara *lazy* secara bawaan
(`Filament\Support\Concerns\CanBeLazy::$isLazy = true`), dan tidak ada widget SIMPBI yang
mengubahnya. Akibatnya test yang hanya membuka dasbor lewat HTTP
(`MonitoringPolaPermintaanTest::test_dasbor_seluruh_peran_tetap_terangkai`) memang
membuktikan dasbor kelima peran terbuka (200), tetapi **tidak** menjalankan kueri widget
— hanya gerbang `canView()` yang dievaluasi, tanpa asersi widget mana yang tampil. Kueri
widget hanya tercakup bila test memanggil `Livewire::test(Widget::class)` secara langsung.

### 2.2 Jalur kritis dan pemetaannya ke test

Status **Tercakup** berarti ada test yang menjalankan jalur itu **dan** menegaskan
hasilnya (penerima, angka, atau tautan). Jalur yang ikut dijalankan oleh alur lain tanpa
asersi atas hasilnya dicatat *Tidak tercakup* dengan keterangan.

**A. `NotifikasiService` — penerbitan**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| NS-1 | `kirim()`: penerima kosong → `0`, tidak ada insert (baris 65–67) | Kejadian tanpa pihak berkepentingan tidak menulis apa pun | **Tidak tercakup** | — |
| NS-2 | `kirim()`: `filter()->unique('id')` (baris 45–47) | Pengguna yang masuk dua kelompok penerima hanya menerima satu | **Tidak tercakup** | — |
| NS-3 | `kirim()`: baris `in_app` ditulis dan jumlah penerima dikembalikan (baris 69–76) | Lonceng terisi | Tercakup | `PengirimanWhatsAppTest::test_kanal_mati_tidak_menerbitkan_baris_whatsapp`, `::test_kanal_hidup_menerbitkan_baris_dan_mengantre_pengiriman` |
| NS-4 | `terbitkanWhatsApp()`: kanal WA mati → berhenti (baris 99–101) | Tidak ada baris/antrean WhatsApp | Tercakup | `PengirimanWhatsAppTest::test_kanal_mati_tidak_menerbitkan_baris_whatsapp` |
| NS-5 | Pengguna tanpa nomor dan pengalihan mati → dilewati (baris 120–122) | Riwayat tidak dipenuhi kegagalan pasti | Tercakup | `PengirimanWhatsAppTest::test_kanal_hidup_menerbitkan_baris_dan_mengantre_pengiriman`, `PengalihanWhatsAppTest::test_pengalihan_kosong_tetap_melewati_pengguna_tanpa_nomor` |
| NS-6 | Pengalihan menyala → pengguna tanpa nomor tetap mendapat baris (baris 108, 120) | Mode peragaan tetap memunculkan pesan | Tercakup | `PengalihanWhatsAppTest::test_pengalihan_menyala_menerbitkan_baris_bagi_pengguna_tanpa_nomor` |
| NS-7 | Baris WA `pending` + `KirimPesanWhatsApp` diantrekan (baris 124–139) | Pengiriman lewat antrean, tidak menahan HTTP | Tercakup | `PengirimanWhatsAppTest::test_kanal_hidup_menerbitkan_baris_dan_mengantre_pengiriman`, `PengajuanKatalogAturanTest::test_pengajuan_tim_mengantrekan_whatsapp_untuk_ketua_tim` |
| NS-8 | `berperan()`: saring per tim bila `$timId` diisi (baris 165) | Ketua Tim tim lain tidak menerima | Tercakup | `NotifikasiMutasiAsetTest::test_bast_disahkan_memberitahu_ketua_tim_tujuan_saja` |
| NS-9 | `berperan()`: hanya akun `status_aktif` (baris 163) | Akun nonaktif tidak diberi tahu | **Tidak tercakup** | — |

**B. `NotifikasiService::permintaanBerubah()` — penerima per status**

| No | Status baru | Penerima menurut kode | Status | Test method / keterangan |
|---|---|---|---|---|
| NS-10 | `menunggu_ketua` | Ketua Tim pada tim pemohon | Tercakup | `PengajuanKatalogAturanTest::test_pengajuan_tim_memberi_tahu_ketua_timnya` |
| NS-11 | `menunggu_verifikasi` | Petugas Gudang | Tercakup | `PengajuanKatalogAturanTest::test_pengajuan_ketua_tim_memberi_tahu_petugas_gudang` |
| NS-12 | `menunggu_kasubbag` | Kasubbag Umum | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ns12_verifikasi_gudang_memberi_tahu_kasubbag` — diverifikasi manual 23 September 2026: baris in-app dan payload job WhatsApp diperiksa; penerima sesuai kode, tidak ditemukan bug. |
| NS-13 | `siap_diproses` | Petugas Gudang | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ns13_setujui_kasubbag_memberi_tahu_gudang` — diverifikasi manual 23 September 2026, tidak ditemukan bug. |
| NS-14 | `siap_diambil` | Tim + Ketua Tim pemohon | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ns14_siapkan_memberi_tahu_tim_dan_ketua` — diverifikasi manual 23 September 2026, tidak ditemukan bug. |
| NS-15 | `menunggu_pengesahan` | Kasubbag Umum | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ns15_konfirmasi_sesuai_memberi_tahu_kasubbag` — diverifikasi manual 23 September 2026, tidak ditemukan bug. |
| NS-16 | `selesai` | Tim + Ketua Tim pemohon | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ns16_sahkan_memberi_tahu_tim_dan_ketua` — diverifikasi manual 23 September 2026, tidak ditemukan bug. |
| NS-17 | `ditolak_ketua` / `ditolak_kasubbag` (+ alasan) | Tim + Ketua Tim pemohon | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ns17a_tolak_ketua_memberi_tahu_tim_dan_ketua_dengan_alasan`, `::test_ns17b_tolak_kasubbag_memberi_tahu_tim_dan_ketua_dengan_alasan` — diverifikasi manual 23 September 2026 (kedua tahap); pesan penolakan memuat teks alasan persis, tidak ditemukan bug. |
| NS-18 | `bermasalah` | Kasubbag + Petugas Gudang + Tim/Ketua Tim pemohon | Tercakup | `VerifikasiNotifikasiMutasiAsetTest::test_ns18_konfirmasi_bermasalah_memberi_tahu_kasubbag_gudang_tim_ketua` — diverifikasi manual 23 September 2026, tidak ditemukan bug. |
| NS-19 | `kedaluwarsa` | Tim + Ketua Tim pemohon | Tercakup | `NotifikasiKedaluwarsaTest::test_sapuan_memberi_tahu_pemohon_tepat_satu_kali` |
| NS-20 | Status lain (`default`) → `0` (baris 246–251) | Tidak ada | **Tidak tercakup** | — |

**C. `NotifikasiService::stokMenipis()`**

| No | Jalur / cabang | Hasil bisnis | Status | Test method |
|---|---|---|---|---|
| NS-21 | Tersedia > 0 dan di atas stok minimum → `0` (baris 285–287) | Tidak ada peringatan pada setiap perubahan stok | **Tidak tercakup** | — |
| NS-22 | Tersedia ≤ 0 → "Stok habis" ke Petugas Gudang + Kasubbag (baris 289–293) | Pengadaan segera diketahui | **Tidak tercakup** | — |
| NS-23 | 0 < tersedia ≤ minimum → "Stok menipis" (baris 294–298) | Peringatan dini | **Tidak tercakup** | — |

**D. `NotifikasiService::bastBerubah()` — penerima per status**

| No | Status baru | Penerima menurut kode | Status | Test method |
|---|---|---|---|---|
| NS-24 | `menunggu_pengesahan` | Kasubbag saja | Tercakup | `NotifikasiMutasiAsetTest::test_bast_baru_memberitahu_kasubbag_saja` |
| NS-25 | `menunggu_konfirmasi` | Ketua Tim tim tujuan saja | Tercakup | `NotifikasiMutasiAsetTest::test_bast_disahkan_memberitahu_ketua_tim_tujuan_saja` |
| NS-26 | `selesai_administratif` | Kasubbag + Petugas Gudang | Tercakup | `NotifikasiMutasiAsetTest::test_mutasi_selesai_memberitahu_kasubbag_dan_petugas_gudang` |
| NS-27 | Status lain → `0` | Tidak ada | Tercakup | `NotifikasiMutasiAsetTest::test_status_yang_belum_dikenali_tidak_menerbitkan_notifikasi` |
| NS-28 | Referensi dan isi pesan (nomor BAST, aset, tim asal/tujuan) | — | Tercakup | `NotifikasiMutasiAsetTest::test_notifikasi_menunjuk_ke_bast_yang_benar`, `::test_pesan_menyebut_tim_kerja_asal_dan_tujuan` |

**E. Widget dasbor per peran (`app/Filament/Widgets/`)**

| No | Widget — jalur | Peran (`canView`) | Status | Test method |
|---|---|---|---|---|
| W-1 | `RingkasanAdmin` — cacah pengguna/tim/barang aktif, kategori persediaan | Admin | **Tidak tercakup** | — |
| W-2 | `RingkasanKasubbag` — menunggu persetujuan/pengesahan, stok kritis, tidak tersedia | Kasubbag | **Tidak tercakup** | — |
| W-3 | `RingkasanGudang` — perlu verifikasi/disiapkan, siap diambil, bermasalah | Petugas Gudang | **Tidak tercakup** | — |
| W-4 | `RingkasanKetua` — dibatasi `tim_pemohon_id` = tim pengguna | Ketua Tim | **Tidak tercakup** | — |
| W-5 | `RingkasanTim` — dibatasi `tim_pemohon_id` = tim pengguna | Tim | **Tidak tercakup** | — |
| W-6 | `KelengkapanDataInduk` — pengguna bertim tanpa tim, barang tanpa stok minimum, tim tanpa ketua | Admin | **Tidak tercakup** | — |
| W-7 | `KondisiAsetTetap` — aset aktif per kondisi | Admin, Kasubbag | **Tidak tercakup** | — |
| W-8 | `KondisiAsetTetapTim` — gerbang peran | Ketua Tim, Tim | Tercakup | `DashboardTimWidgetsTest::test_kondisi_aset_tetap_tim_saya_hanya_untuk_ketua_tim_dan_tim` |
| W-9 | `KondisiAsetTetapTim` — hanya aset aktif tim pengguna | Ketua Tim, Tim | Tercakup | `DashboardTimWidgetsTest::test_kondisi_aset_tetap_tim_saya_hanya_mencacah_aset_tim_pengguna`, `PenggunaTanpaTimTest::test_pengguna_yang_punya_tim_tetap_melihat_aset_timnya` |
| W-10 | `KondisiAsetTetapTim` — pengguna tanpa tim → `whereRaw('1 = 0')`, keadaan kosong | Ketua Tim, Tim | Tercakup | `PenggunaTanpaTimTest::test_panel_kondisi_kosong_bagi_pengguna_tanpa_tim` |
| W-11 | `TrenKonsumsiTim` — gerbang peran | Ketua Tim, Tim | Tercakup | `DashboardTimWidgetsTest::test_tren_konsumsi_tim_saya_hanya_untuk_ketua_tim_dan_tim` |
| W-12 | `TrenKonsumsiTim` — hanya barang keluar milik tim pengguna | Ketua Tim, Tim | Tercakup | `DashboardTimWidgetsTest::test_tren_konsumsi_tim_saya_hanya_menjumlahkan_barang_keluar_tim_pengguna` |
| W-13 | `TrenKonsumsiTim` — penyaring kategori, seri kosong | Ketua Tim, Tim | **Tidak tercakup** | — |
| W-14 | `StatusPermintaanTim` — cacah per kelas status, dibatasi `PermintaanBarangResource::getEloquentQuery()` | Tim, Ketua Tim | **Tidak tercakup** | — (hanya urutan `$sort` yang diuji: `DashboardTimWidgetsTest::test_status_permintaan_tim_saya_berpindah_ke_baris_kedua`) |
| W-15 | `PolaPermintaan` — judul per peran | Kasubbag, Ketua Tim, Tim | Tercakup | `DashboardTimWidgetsTest::test_pola_permintaan_menyebut_tim_saya_bagi_ketua_tim_dan_tim`, `::test_pola_permintaan_tetap_judul_lama_bagi_kasubbag` |
| W-16 | `PolaPermintaan` — batas periode 30/90/semua dan cacah per hari | Kasubbag, Ketua Tim, Tim | **Tidak tercakup** | — |
| W-17 | `PerluTindakan` — status per peran (`TindakanPermintaan::statusUntuk`) dan batas tim | Kasubbag, Ketua Tim, Petugas Gudang, Tim | Tercakup | `PerluTindakanWidgetTest::test_ketua_tim_melihat_permintaan_siap_diambil_yang_dapat_dikonfirmasi`, `::test_tim_juga_melihat_permintaan_siap_diambil_timnya`, `::test_permintaan_tim_lain_tidak_muncul`, `::test_petugas_gudang_melihat_verifikasi_dan_penyiapan`, `::test_item_keluar_dari_panel_setelah_status_berubah`, `::test_tautan_panel_membuka_pop_up_rincian`; Kasubbag (`menunggu_kasubbag`): `IstilahKonsistenTest::test_daftar_permintaan_dan_panel_perlu_tindakan_memakai_label_baru` |
| W-18 | `PerluTindakan` — Kasubbag pada `menunggu_pengesahan`; kelas urgensi batas waktu (netral/lewat/mendesak/wajar, baris 163–174) | — | **Tidak tercakup** | — |
| W-19 | `KondisiStok` — isi sama bagi Gudang dan Kasubbag; tautan hanya bagi yang berhak | Kasubbag, Petugas Gudang | Tercakup | `KondisiStokTautanTest::test_angka_dan_isi_panel_gudang_sama_dengan_kasubbag`, `::test_petugas_gudang_tidak_lagi_melihat_tautan_ke_barang_persediaan`, `::test_kasubbag_tetap_melihat_seluruh_tautan_dan_semuanya_terbuka` |
| W-20 | `BarangPerluPerhatian` — gerbang dan tautan ke Kartu Kendali | Petugas Gudang | Tercakup | `RiwayatTabTest::test_tautan_panel_menuju_halaman_yang_terbuka_bagi_gudang` |
| W-21 | `BarangPerluPerhatian` — kueri stok ≤ minimum | Petugas Gudang | **Tidak tercakup** | — |
| W-22 | `BarangPalingDiminta` — gerbang, frekuensi, periode, keadaan kosong | Kasubbag | Tercakup | `MonitoringPolaPermintaanTest::test_panel_analisis_hanya_untuk_kasubbag`, `::test_peringkat_barang_memakai_frekuensi_permintaan`, `::test_peringkat_barang_mengikuti_periode_terpilih`, `::test_peringkat_barang_kosong_ketika_belum_ada_permintaan` |
| W-23 | `PermintaanPerTim` — jumlah permintaan vs barang disalurkan, tautan, batas periode | Kasubbag | Tercakup | `MonitoringPolaPermintaanTest::test_panel_tim_kerja_membedakan_jumlah_permintaan_dan_barang_disalurkan`, `::test_permintaan_tanpa_pengeluaran_tidak_terhitung_sebagai_barang_disalurkan`, `::test_tautan_baris_mengikuti_metrik`, `::test_batas_periode_kedua_metrik_sama` |
| W-24 | `TrenKonsumsiKategori` — 12 bulan, penyaring kategori, tahun lain tidak bocor, kosong | Kasubbag | Tercakup | `MonitoringPolaPermintaanTest::test_tren_konsumsi_selalu_dua_belas_bulan_januari_sampai_desember`, `::test_tren_konsumsi_menyaring_menurut_kategori`, `::test_tahun_lain_tidak_bocor_ke_dalam_periode`, `::test_tren_konsumsi_kosong_ketika_belum_ada_barang_keluar` |

### 2.3 Alat ukur cakupan

Tidak tersedia (tidak ada `pcov`/`xdebug`; `phpdbg` tidak didukung PHPUnit 11; tidak
ada driver baru yang dipasang). Cakupan diukur manual seperti tabel di atas.

### 2.4 Kesimpulan white-box Modul 4

**Layak Bersyarat.** Tidak ada jalur yang gagal: seluruh test yang mencakup jalur di
atas lulus (182/182 kasus Modul 4 di kedua driver). Namun cakupan Modul 4 adalah yang
paling tipis:

- `NotifikasiService`: 14 dari 28 jalur tercakup. Penerima untuk tujuh status permintaan
  (NS-12 s.d. NS-18) tidak pernah ditegaskan, dan `stokMenipis()` (NS-21 s.d. NS-23)
  tidak diuji sama sekali.
- Widget: 12 dari 24 jalur tercakup. Kelima widget *Ringkasan* per peran,
  `KelengkapanDataInduk`, dan `KondisiAsetTetap` tidak pernah dirender dalam test.

Daftar jalur yang diteruskan ke pengujian Black-Box:

1. Penerima notifikasi untuk `menunggu_kasubbag`, `siap_diproses`, `siap_diambil`,
   `menunggu_pengesahan`, `selesai`, `ditolak_ketua`/`ditolak_kasubbag`, `bermasalah`
   (NS-12 s.d. NS-18); akun nonaktif tidak menerima (NS-9); penerima ganda (NS-2);
   penerima kosong (NS-1); status tak dikenal (NS-20).
2. Peringatan stok habis/menipis (NS-21 s.d. NS-23).
3. Angka pada widget Ringkasan kelima peran, Kelengkapan Data Induk, dan Kondisi Aset
   Tetap (W-1 s.d. W-7).
4. Penyaring kategori Tren Konsumsi Tim (W-13), Status Permintaan Tim (W-14), periode
   Pola Permintaan (W-16), urgensi batas waktu Perlu Tindakan (W-18), kueri Barang Perlu
   Perhatian (W-21).
5. Ekspor Riwayat PDF/Excel (bagian 1.4).
