# FINAL SYSTEM AUDIT — SIMPBI

Audit kualitas menyeluruh sebelum sistem dinyatakan final, **15 September 2026**.
Menggantikan `docs/pekerjaan-tertunda.md`.

---

## 1. Executive Summary

**Vonis: NOT READY TO FREEZE** — tertahan oleh **satu** temuan, bukan oleh
kondisi sistem secara umum.

Sistemnya sendiri dalam keadaan baik. Seluruh 26 use case terimplementasi, ERD
terpasang utuh, kelima peran terpisah dengan benar sampai ke tingkat menu, alur
enam tahap permintaan tidak punya jalan buntu yang tidak disengaja, dan
pemeriksaan peramban atas halaman publik maupun panel menghasilkan **nol galat
konsol dan nol permintaan jaringan gagal**.

Yang menahan freeze adalah satu lubang integritas data: **menghapus satu barang
persediaan akan menghapus seluruh buku besar mutasinya secara diam-diam.** Buku
besar itulah sumber Kartu Kendali — dokumen yang justru menjadi keluaran utama
sistem ini. Satu klik "Hapus" oleh Admin atau Kasubbag memusnahkan jejak audit
sebuah barang tanpa peringatan yang menyebutkannya.

Perbaikannya tidak menyentuh basis data: cukup penjaga di antarmuka.

Selain itu ada empat temuan P1 dan delapan P2 yang tidak menghalangi freeze.

**Cara audit ini dikerjakan.** Tidak ada kode maupun data yang diubah. Seluruh
pengujian yang menyentuh penghapusan dijalankan di dalam transaksi basis data
yang digulung balik; jumlah baris seluruh tabel diperiksa sebelum dan sesudah
audit, dan identik (`users` 12, `tim` 8, `barang_persediaan` 119,
`permintaan_barang` 5, `mutasi_stok` 42, `notifikasi` 61, 11 berkas dokumen).
`reset-demo` tidak dijalankan.

---

## 2. Status Implementasi

### Use case — **PASS**

26 use case pada Instruksi.md, seluruhnya punya tempat di sistem:

| Use case | Tempat |
|---|---|
| UC-01 Autentikasi | `App\Filament\Auth\Login` + `PaksaGantiKataSandi` |
| UC-02 Kelola Pengguna | `Resources/Users` |
| UC-03 Master Barang | `Resources/BarangPersediaans` |
| UC-04 Master Aset | `Resources/AsetTetaps` |
| UC-05 Kategori | `Resources/Kategoris` |
| UC-06 Data Tim | `Resources/Tims` |
| UC-07 Stok Masuk | `Pages/StokMasuk` |
| UC-08 Mengajukan Permintaan | `Pages/KatalogBarang` |
| UC-09 Persetujuan Ketua | aksi `setujui`/`tolak` |
| UC-10 Verifikasi Stok | aksi `verifikasi` |
| UC-11 Persetujuan Kasubbag | aksi `setujuiKasubbag`/`tolakKasubbag` |
| UC-12 Menyiapkan Barang | aksi `siapkan` |
| UC-13 Pengambilan | aksi `konfirmasi` |
| UC-14 Mencatat Ketidaksesuaian | di dalam aksi `konfirmasi` |
| UC-15 Pengesahan | aksi `sahkan` + `DokumenPermintaanService` |
| UC-16–18 BAST Mutasi Aset | `Resources/BastMutasiAsets` + `MutasiAsetService` |
| UC-19 Dashboard | 14 widget, seluruhnya ber-`canView()` |
| UC-20–22 Riwayat & Pola | `Pages/Riwayat`, widget `PolaPermintaan` |
| UC-23 Notifikasi | `LoncengNotifikasi` + `NotifikasiService` |
| UC-24 Ekspor | Kartu Kendali (PDF/XLSX), Riwayat, dokumen |
| UC-25 Detail Transaksi | `DetailPermintaanBarang` |
| UC-26 (rujukan penamaan) | — |

Tidak ditemukan use case yang hanya ada di diagram, maupun fitur besar tanpa
tempat dalam rancangan. Dua fitur di luar daftar UC — impor data dan perkakas
pemeliharaan (`simpbi:backup/restore/reset-demo`) — keduanya perkakas
administratif, bukan proses bisnis, dan tidak mengubah Use Case Diagram.

### ERD → basis data — **PASS**

19 tabel terpasang, seluruh relasi terbaca oleh `Schema::getForeignKeys()`:

```
induk     : tim(8) users(12) kategori(16) barang_persediaan(119) aset_tetap(12)
transaksi : permintaan_barang(5) detail_permintaan_barang(6) mutasi_stok(42)
            bast_mutasi_aset(1) ketidaksesuaian_barang(0)
riwayat   : riwayat_persetujuan(31) riwayat_penempatan_aset(13)
pendukung : notifikasi(61) pengaturan(8) jobs(24) sessions cache migrations
```

Seluruh kunci asing terpasang dan terarah benar. Tidak ada tabel yang dirancang
tetapi tidak ada, dan tidak ada tabel liar di luar rancangan.

### Lima peran — **PASS**

Diverifikasi di peramban dengan masuk sebagai kelima akun. Menu yang benar-benar
muncul:

| Peran | Menu |
|---|---|
| Admin Sistem | Dasbor · Permintaan Barang · Barang Persediaan · Kategori Barang · Aset Tetap · Riwayat · Tim · Pengguna |
| Kasubbag Umum | Dasbor · Permintaan Barang · Barang Persediaan · Kategori Barang · Kartu Kendali · Aset Tetap · Mutasi Aset · Riwayat · Tim |
| Petugas Gudang | Dasbor · Permintaan Barang · Stok Masuk · Kartu Kendali · Mutasi Aset · Riwayat |
| Ketua Tim | Dasbor · Katalog Barang · Permintaan Barang · Riwayat · Mutasi Aset |
| Tim | Dasbor · Katalog Barang · Permintaan Barang · Riwayat |

Cocok dengan `canAccess()` masing-masing modul. Hanya Admin yang melihat
Pengguna; hanya Petugas Gudang yang melihat Stok Masuk; Kartu Kendali hanya
Gudang dan Kasubbag.

### Workflow — **PASS**

Sebelas status, seluruhnya dapat dicapai, dan setiap aksi terkunci pada
kombinasi peran + status yang benar. Rinciannya di bagian 10.

### Fitur utama — **PASS**

PDF, Excel, QR, e-TTD, Kartu Kendali, BAST, notifikasi, dasbor, riwayat, stok,
persetujuan, impor, cadangan/pemulihan/reset — seluruhnya ada dan berfungsi.

---

## 3. UI / Visual Consistency

Diverifikasi di peramban pada 1440 px, 768 px, dan 390 px.

- **Halaman muka dan halaman masuk: 0 galat konsol, 0 permintaan gagal, tidak
  ada guliran mendatar, tidak ada elemen yang meluber** pada ketiga lebar.
- **Panel (dasbor, permintaan, kartu kendali, riwayat) pada 390 px: tidak ada
  guliran mendatar pada halaman.** Tabel memang lebih lebar dari layar, tetapi
  berada di dalam pembungkus bergulir milik Filament — itu pola yang benar,
  bukan cacat.
- Judul halaman konsisten: `SIMPBI — …`, `Masuk - SIMPBI`.

**PASS**, dengan satu catatan warna di bagian 5.

---

## 4. Typography & Spacing

Satu keluarga huruf (Inter) pada panel, Times New Roman pada seluruh dokumen
cetak — pemisahan yang disengaja dan konsisten: layar memakai huruf antarmuka,
naskah dinas memakai huruf naskah dinas.

`theme.css` 1.046 baris dengan 114 deklarasi kustom, seluruhnya berkomentar
alasannya. Tidak ditemukan campuran ukuran huruf atau jarak yang menonjol pada
pemeriksaan peramban.

**PASS.** Tidak ada yang perlu disentuh.

---

## 5. Color System

Palet ditetapkan terpusat di `AdminPanelProvider`:

```
primary  navy #0B2A5B      success #16A34A     warning #D97706
accent   #F59E0B           danger  #DC2626     info    #2563EB      gray Slate
```

Makna warna konsisten di hampir seluruh sistem: `success` = selesai/baik/masuk,
`danger` = ditolak/rusak berat/keluar/gagal, `warning` = menunggu/rusak ringan,
`info` = sedang berjalan.

**Dua ketidakkonsistenan nyata ditemukan** — lihat P1-4 dan P2-4.

---

## 6. Button & Action

Seluruh aksi alur diperiksa satu per satu. Label, ikon, warna, dan syarat
tampilnya:

| Aksi | Label | Warna | Muncul bila |
|---|---|---|---|
| `setujui` | Setujui | success | ketua_tim + menunggu_ketua |
| `tolak` | Tolak | danger | ketua_tim + menunggu_ketua |
| `verifikasi` | Verifikasi | info | petugas_gudang + menunggu_verifikasi |
| `setujuiKasubbag` | Setujui | success | kasubbag + menunggu_kasubbag |
| `tolakKasubbag` | Tolak | danger | kasubbag + menunggu_kasubbag |
| `siapkan` | Barang Siap Diambil | info | petugas_gudang + siap_diproses |
| `konfirmasi` | Konfirmasi Penerimaan | — | tim/ketua_tim + siap_diambil |
| `sahkan` | Sahkan | success | kasubbag + menunggu_pengesahan |
| `unduh` | Unduh Bukti | primary | dokumen sudah terbit |

Tidak ada tombol yang muncul pada tahap yang salah, dan tidak ada label yang
menjanjikan hal berbeda dari yang dikerjakannya. Label "Barang Siap Diambil"
lebih menjelaskan akibat daripada sekadar "Siapkan" — justru lebih baik.

Aksi merusak: `DeleteAction`/`DeleteBulkAction` memakai konfirmasi bawaan
Filament; aksi kirim ulang WhatsApp dan ekspor memakai `requiresConfirmation()`.

**PASS**, kecuali satu label Inggris (P2-1) dan cakupan konfirmasi hapus (P0-1).

---

## 7. Form & Modal

- `UserForm`: username wajib + unik, email wajib + unik + bervalidasi surel,
  kata sandi wajib hanya saat membuat, tim wajib hanya untuk peran Tim dan Ketua
  Tim — dengan `helperText` yang menjelaskannya. Rapi.
- Dialog aksi alur seluruhnya berjudul dan berketerangan; yang berisiko punya
  keterangan akibat, misalnya "Stok yang dikunci akan dilepaskan kembali."
- Dialog ekspor Kartu Kendali: Format (PDF bawaan) → Periode → Kategori, dengan
  `helperText` pada Kategori.

**PASS.**

---

## 8. Table

Kolom, penyaring, pengurutan, dan paginasi wajar di seluruh tabel. Tidak
ditemukan kolom yang membingungkan atau berlebihan.

Empat tabel memakai keadaan kosong kustom (Aset Tetap, Barang Persediaan, Kartu
Kendali, Stok Masuk); empat lainnya memakai bawaan Filament (BAST, Kategori,
Tim, Pengguna) — lihat P2-3.

---

## 9. Animation & Interaction

Durasi yang dipakai: 60–320 ms untuk interaksi (10× 250 ms, 8× 150 ms, 5× 200
ms) — semuanya dalam rentang yang wajar. Yang lebih panjang hanya tiga dan
semuanya disengaja: `.7s` untuk kemunculan hero, `880ms` untuk layar pemuatan,
`7s` untuk animasi glitch dekoratif pada halaman muka.

**`prefers-reduced-motion` ditangani di lima tempat** dan mencakup seluruhnya,
termasuk glitch yang berulang tanpa henti, panah petunjuk, kemunculan widget,
dan layar pemuatan.

**PASS.** Tidak ada animasi yang rusak, berlebihan, atau menyebabkan pergeseran
tata letak.

---

## 10. Workflow

Alur enam tahap utuh dan tidak punya jalan buntu yang tidak disengaja:

```
menunggu_ketua → menunggu_verifikasi → menunggu_kasubbag → siap_diproses
    → siap_diambil → menunggu_pengesahan → selesai
```

Cabang keluar: `ditolak_ketua`, `ditolak_kasubbag`, `bermasalah`, `kedaluwarsa`.

- Setiap status punya penetapnya di kode; tidak ada status yatim.
- `bermasalah` dan `kedaluwarsa` memang **terminal** — tercatat pada
  `PermintaanBarang::STATUS_RIWAYAT` beserta alasannya: kunci stoknya sudah
  dilepaskan sehingga permintaan tidak dapat dilanjutkan. Ini **keterbatasan
  yang disengaja**, bukan jalan buntu; tim mengajukan permintaan baru.
- Kedaluwarsa ditegakkan dua jalur: middleware `SapuPermintaanKedaluwarsa` tiap
  panel dibuka, dan `permintaan:lepas-hold` tiap sepuluh menit sebagai cadangan.

**PASS.**

---

## 11. Dashboard

14 widget, seluruhnya ber-`canView()`. Diverifikasi di peramban: dasbor Kasubbag,
Petugas Gudang, dan Ketua Tim seluruhnya memuat isi tanpa galat konsol, dengan
grafik yang benar-benar tergambar (2, 1, dan 2 kanvas).

Isi dasbor memang berbeda per peran — dan itu memang dikehendaki, bukan
ketidakkonsistenan.

**PASS.**

---

## 12. Notification

- Lonceng memakai `wire:poll` dengan selang yang dihitung, bukan tetap.
- **Dismiss tidak menghapus histori**: ia menulis `disembunyikan_at`, dan
  `scopeTampil()` menyaring berdasarkan kolom itu. Barisnya tetap ada dan tetap
  muncul di halaman Riwayat.
- Kanal `in_app` dan `whatsapp` dibedakan warnanya secara konsisten
  (success/gray), status kirim `terkirim`/`gagal`/`pending` →
  success/danger/warning.

**PASS.**

---

## 13. PDF / Excel

| Dokumen | Keadaan |
|---|---|
| Bukti Permintaan | 1 halaman, isi dalam margin (x 58,2→502,1 · y 51,6→828,9 dari A4 595×842), blok tanda tangan sejajar acuan sampai ke poin |
| BAST Mutasi Aset | 1 halaman, blok pengesahan identik dengan bukti permintaan pada setiap jarak baris |
| Kartu Kendali PDF | 119 kartu → 119 halaman, 177 KB, teks terpilih; judul kolom berulang lintas halaman (terbukti pada uji 60 transaksi → 2 halaman) |
| Kartu Kendali XLSX | 119 lembar, 334 KB, templat master utuh (0 sel menyimpang, dimensi A1:I32) |
| Ekspor Riwayat | tersedia, satu jalur penamaan dengan dokumen lain |

PDF adalah **cuplikan pada saat diekspor**; data kartu kendali di sistem tetap
mengikuti transaksi baru. Keduanya berangkat dari `KartuKendaliService::data()`
yang sama, sehingga isi PDF dan XLSX tidak mungkin berbeda.

**PASS.**

---

## 14. QR / Verification / e-TTD

Seluruh 9 berkas PDF yang tersimpan dipindai ulang dengan pembaca QR:

```
BAST-2026-0001            → http://192.168.68.144:8000/verifikasi-bast/AMAIqvn…
PB-2026-0002 … 0005       → http://192.168.68.144:8000/bukti/<token>
PB-…-berfootnote          → http://192.168.68.144:8000/bukti/<token>/asli
```

**Tidak ada** `localhost`, `127.0.0.1`, `null`, atau alamat kosong. Alamat
dibentuk dari `config('app.url')`, sehingga cukup mengganti `APP_URL` saat
pemasangan. Lambang BPS menyatu di dalam matriks kode (nisbah 16,1% terhadap
acuan 15,8%), bukan ditumpangkan.

Keterbacaan cetak: e-TTD terbaca sampai 200 dpi, kode catatan kaki sampai 250
dpi.

⚠ **Satu masalah pernyataan — lihat P1-1.** Catatan kaki kedua dokumen menyebut
"sertifikat elektronik yang diterbitkan oleh Sistem Informasi Manajemen
Permintaan Barang dan Inventaris". SIMPBI tidak menerbitkan sertifikat
elektronik; ia menerbitkan token acak 40 aksara. Kalimat itu perlu diperbaiki
agar tidak terbaca sebagai klaim TTE tersertifikasi.

---

## 15. Role & Authorization

- Seluruh 8 resource dan 6 halaman kustom punya `canAccess()` atau
  `shouldRegisterNavigation()` yang eksplisit.
- Penyaringan data: `PermintaanBarangResource::getEloquentQuery()` membatasi Tim
  dan Ketua Tim ke `tim_pemohon_id = user.tim_id`; halaman Detail mewarisi
  pembatasan itu, sehingga membuka URL langsung permintaan tim lain berujung
  404, bukan kebocoran.
- Seluruh 14 widget ber-`canView()`.
- Tidak ditemukan peningkatan hak, aksi peran lain yang terlihat, maupun ekspor
  yang tidak semestinya terjangkau.

**PASS.**

---

## 16. Responsive

Diukur, bukan dikira:

| Halaman | 1440 | 768 | 390 |
|---|---|---|---|
| Halaman muka | bersih | bersih | bersih |
| Halaman masuk | bersih | bersih | bersih |
| Dasbor | bersih | — | bersih |
| Permintaan / Kartu Kendali / Riwayat | bersih | — | bersih¹ |

¹ Tabel lebih lebar dari layar tetapi bergulir di dalam pembungkusnya sendiri;
halaman tidak ikut bergulir mendatar.

**PASS.**

---

## 17. Technical Quality

- **0 penanda `TODO`/`FIXME`** di seluruh `app/`, `resources/`, `routes/`,
  `config/`, `database/`.
- **0 view mati**: seluruh 31 berkas Blade dirujuk kode.
- **0 galat konsol dan 0 permintaan gagal** pada halaman publik dan pada dasbor
  ketiga peran yang diperiksa.
- 35 rute, seluruhnya terpakai.
- 271 pengujian otomatis lulus.

Utang teknis yang tersisa ada di P2.

---

## 18. Performance

Diukur dengan pencatat kueri:

| Operasi | Kueri | Waktu |
|---|---|---|
| Tabel Kartu Kendali, 1 halaman (10 baris) | 32 | 0,06 dtk |
| Ringkasan seluruh 119 barang | 361 | 0,16 dtk |
| Data satu kartu | 4 | ~0 dtk |
| Ekspor PDF 119 kartu | 361 | **9,09 dtk** |
| Ekspor XLSX 119 kartu | 361 | 2,46 dtk |

Ada pola N+1 (3 kueri per barang), tetapi **dampaknya terukur kecil** — 0,16
detik untuk 119 barang. Tidak perlu dioptimalkan sekarang.

Satu catatan pemasangan: ekspor PDF 9 detik masih aman terhadap
`max_execution_time` bawaan 30 detik, tetapi bila katalog tumbuh menjadi ~400
barang, angkanya mendekati batas. Lihat bagian 20.

---

## 19. Data Integrity

Audit ini **tidak mengubah data**. Jumlah baris seluruh tabel sebelum dan
sesudah audit identik. Setiap pengujian penghapusan dijalankan di dalam
transaksi yang digulung balik. `reset-demo` tidak dijalankan.

Namun audit **menemukan** satu lubang integritas data pada sistemnya — lihat
P0-1.

---

## 20. Deployment Readiness

| Butir | Keadaan sekarang | Tindakan saat pemasangan |
|---|---|---|
| `APP_ENV` | `local` | → `production` |
| `APP_DEBUG` | `true` | → `false` ⚠ menampilkan jejak galat ke siapa pun |
| `APP_URL` | `http://192.168.68.144:8000` | → domain sebenarnya; ini yang tertanam di setiap QR |
| `APP_TIMEZONE` | `Asia/Jakarta` | ✅ siap |
| Basis data | SQLite | → MySQL 8 (target Instruksi); jalur cadangan/pemulihan MySQL **sudah diuji nyata** |
| `storage:link` | belum | `php artisan storage:link` |
| Antrean | `database`, **24 pekerjaan menumpuk** | jalankan `queue:work` lewat Supervisor/systemd |
| Penjadwal | 1 perintah terdaftar | pasang cron `* * * * * php artisan schedule:run` |
| WhatsApp | `WHATSAPP_DRIVER=catat` | daftarkan gerbang sungguhan, uji dengan nomor nyata |
| `config:cache` | tidak aktif | jalankan **setelah** `.env` terisi |
| Cadangan | perintah siap | pasang cron harian, salin arsip ke luar mesin |
| HTTPS | — | wajib: token QR dan sesi melintas jaringan |
| `max_execution_time` | bawaan | naikkan bila katalog jauh bertambah (lihat bagian 18) |

---

## 21. P0 — Critical

### P0-1 · Menghapus barang persediaan memusnahkan buku besarnya secara diam-diam

- **Lokasi:** `app/Filament/Resources/BarangPersediaans/Pages/EditBarangPersediaan.php:16`
  (`DeleteAction`), `.../Tables/BarangPersediaansTable.php:181` (`DeleteBulkAction`);
  penyebabnya `database/migrations/2026_08_26_000005_create_mutasi_stok_table.php:26`
  — `barang_id` memakai `cascadeOnDelete()`.
- **Masalah:** dibuktikan di dalam transaksi yang digulung balik — menghapus
  satu barang yang punya riwayat mutasi **berhasil**, dan seluruh baris
  `mutasi_stok` miliknya ikut terhapus. Dialog konfirmasi bawaan Filament hanya
  berkata "apakah Anda yakin", tanpa menyebut bahwa riwayatnya ikut hilang.
- **Dampak:** buku besar mutasi adalah sumber tunggal Kartu Kendali — keluaran
  utama sistem ini dan bahan rekonsiliasi Sub-Bagian Umum. Kehilangannya tidak
  dapat dipulihkan kecuali dari cadangan. Tersedia bagi Admin **dan** Kasubbag.
- **Ketidakkonsistenan yang memperjelas ini bukan kesengajaan:**
  `detail_permintaan_barang.barang_id` justru memakai `restrictOnDelete()`.
  Artinya barang yang pernah **diminta** terlindungi, tetapi barang yang hanya
  punya **mutasi stok** tidak. Dua kolom yang merujuk tabel yang sama
  diperlakukan berbeda.
- **Rekomendasi:** tanpa menyentuh skema. `barang_persediaan` sudah punya kolom
  `status_aktif` — jalan yang benar untuk barang yang tidak dipakai lagi.
  Sembunyikan atau matikan aksi Hapus ketika barang punya baris `mutasi_stok`,
  dan arahkan ke penonaktifan. Perubahan terbatas pada dua berkas Filament.
- **Perlu diperbaiki sebelum freeze?** **Ya.**

---

## 22. P1 — Important

### P1-1 · Catatan kaki dokumen mengklaim "sertifikat elektronik" yang tidak ada

- **Lokasi:** `resources/views/pdf/bukti-permintaan.blade.php:336`,
  `resources/views/pdf/bast-mutasi.blade.php:221`.
- **Masalah:** kalimatnya berbunyi "ditandatangani secara elektronik menggunakan
  sertifikat elektronik yang diterbitkan oleh Sistem Informasi Manajemen
  Permintaan Barang dan Inventaris". SIMPBI tidak menerbitkan sertifikat
  elektronik — yang ada adalah token acak 40 aksara dan halaman verifikasi.
  Tidak ada integrasi dengan penyelenggara sertifikasi elektronik.
- **Dampak:** overclaim pada dokumen yang beredar ke luar dan pada naskah
  skripsi. Penguji yang memahami TTE akan menanyakannya.
- **Rekomendasi:** ubah bunyi kalimatnya menjadi pernyataan yang benar, misalnya
  "disahkan secara elektronik melalui Sistem Informasi Manajemen Permintaan
  Barang dan Inventaris" — tanpa kata "sertifikat". Hanya teks, tidak menyentuh
  mekanisme QR.
- **Perlu sebelum freeze?** Sebaiknya ya — murah dan menyangkut kejujuran
  dokumen.

### P1-2 · Menghapus pengguna atau tim yang punya riwayat menampilkan galat mentah

- **Lokasi:** `app/Filament/Resources/Users/Pages/EditUser.php:16` dan
  `Tables/UsersTable.php:87`; `Resources/Tims/Pages/EditTim.php:16` dan
  `Tables/TimsTable.php:81`.
- **Masalah:** dibuktikan di transaksi yang digulung balik — keduanya melempar
  `QueryException: FOREIGN KEY constraint failed`, bukan pesan yang dapat
  dibaca pengguna. `users` dirujuk lima kunci asing `restrictOnDelete`.
- **Dampak:** Admin melihat halaman galat teknis alih-alih penjelasan. Tidak ada
  kehilangan data — batasan basis data justru bekerja.
- **Rekomendasi:** sembunyikan aksi Hapus ketika pengguna/tim punya rujukan, dan
  arahkan ke `status_aktif` yang sudah tersedia pada kedua tabel.
- **Perlu sebelum freeze?** Sebaiknya ya — satu pola perbaikan yang sama dengan
  P0-1.

### P1-3 · Impor pengguna membolehkan email kosong, padahal masuk memakai email

- **Lokasi:** `app/Services/Impor/ImporPengguna.php:176–198`.
- **Masalah:** halaman masuk memakai **email** (`App\Filament\Auth\Login`,
  label "Alamat Email"), dan kolom `users.email` `nullable()`. Formulir Pengguna
  mewajibkan email, tetapi jalur impor hanya memvalidasi email **bila diisi** —
  baris 198 menulis `'email' => $email ?: null`.
- **Dampak:** akun hasil impor tanpa email tersimpan sebagai akun yang **tidak
  akan pernah bisa masuk**, tanpa satu pun peringatan saat impor. Data sekarang
  aman (12 pengguna, 12 email unik, 0 kosong), tetapi tidak ada yang menjaganya.
- **Rekomendasi:** jadikan email wajib pada impor, dengan pesan galat yang
  menyebut alasannya. Tanpa perubahan skema.
- **Perlu sebelum freeze?** Ya bila impor akan dipakai untuk menyiapkan akun
  pegawai sungguhan.

### P1-4 · Status `bermasalah` berwarna berbeda di dua halaman

- **Lokasi:** `app/Filament/Pages/Riwayat.php:381` (`danger`) vs
  `app/Filament/Resources/PermintaanBarangs/PermintaanBarangResource.php:146`
  (`gray`).
- **Masalah:** satu status, dua warna, pada dua halaman yang sama-sama sering
  dibuka.
- **Dampak:** kosmetik, tetapi persis jenis ketidakkonsistenan yang terlihat
  ketika sistem didemokan.
- **Rekomendasi:** pilih satu. `danger` lebih tepat — ia memang perlu perhatian,
  dan dasbor Petugas Gudang sudah menandainya `danger`.
- **Perlu sebelum freeze?** Sebaiknya — perbaikannya satu baris.

### P1-5 · Dokumentasi akun demo menyebut username, padahal masuk memakai email

- **Lokasi:** `docs/pemeliharaan-data.md` bagian C, dan keluaran
  `app/Console/Commands/ResetDemo.php:82`.
- **Masalah:** keduanya menulis "Akun bawaan: admin · kasubbag · gudang ·
  ketua01 · tim01 — kata sandi `password`". Halaman masuk meminta **email**,
  bukan username. Lebih jauh, sesudah `KetuaTimSeeder` berjalan, email `ketua01`
  menjadi `wanda.pribadi@bps.go.id`, bukan `ketua01@bps.go.id`.
- **Dampak:** penguji atau rekan yang mengikuti dokumentasi akan gagal masuk dan
  mengira sistemnya rusak. Terbukti — saya sendiri gagal masuk saat audit ini
  karena mengikuti dokumentasi tersebut.
- **Catatan:** ini **kesalahan saya sendiri** pada pekerjaan cadangan kemarin.
- **Rekomendasi:** tulis emailnya, dan sebutkan bahwa email Ketua Tim mengikuti
  data pegawai sebenarnya.
- **Perlu sebelum freeze?** Ya — dokumentasi yang salah lebih berbahaya daripada
  tidak ada dokumentasi.

---

## 23. P2 — Optional

### P2-1 · Label tombol berbahasa Inggris
`app/Filament/Pages/Concerns/MengeksporRiwayat.php:61` → `label('Export')`.
Satu-satunya label tombol Inggris di sistem yang selebihnya berbahasa Indonesia.

### P2-2 · Istilah campur pada dua label
`Pages/Riwayat.php:374` → `label('Item')` (di tempat lain memakai "Barang");
`UserForm.php:52` dan `UsersTable.php:38` → `label('Email')` (bukan "Surel").
Keduanya lazim dipakai dan tidak membingungkan. Label `Status` **bukan** temuan
— kata itu sah dalam bahasa Indonesia.

### P2-3 · Empat tabel tanpa keadaan kosong kustom
BastMutasiAsets, Kategoris, Tims, Users memakai bawaan Filament, sedangkan empat
tabel lain memakai teks sendiri. Tidak rusak, hanya tidak seragam.

### P2-4 · Warna peran memakai palet semantik status
`UsersTable.php:21–25` → `ketua_tim` = `warning`, `tim` = `success`. Warna yang
di seluruh sistem berarti "perlu perhatian" dan "selesai" dipakai untuk menandai
identitas peran. Tidak salah, tetapi menumpangi makna yang sudah mapan.

### P2-5 · N+1 pada ringkasan kartu kendali
3 kueri per barang (361 untuk 119). Terukur 0,16 detik — **tidak perlu
dioptimalkan**, dicatat agar diketahui bila katalog tumbuh jauh.

### P2-6 · Metode `tautan()` kembar
Enam baris identik di `DokumenPermintaanService` dan `DokumenBastService`.
Disengaja saat itu, agar dokumen bukti permintaan yang sudah terbukti tidak
disentuh.

### P2-7 · Direktori `undefined/`
Berisi `undefined/login-fokus.png` tertanggal 10 September — lintasan yang salah
tulis saat menyimpan tangkapan layar.

### P2-8 · Pekerjaan belum dikomit
**92 berkas** berubah atau baru sejak komit terakhir `7ac12ac` (11 September).
Di dalamnya: Kartu Kendali, impor data, ganti kata sandi paksa, tanda tangan
pengguna, perbaikan dokumen dan e-TTD, zona waktu, perkakas pemeliharaan, dan
ekspor PDF kartu kendali. **Tidak ada titik pulih.** Ini bukan cacat sistem,
tetapi risiko nyata bagi pekerjaan Anda — dan yang paling murah dikerjakan.

---

## 24. PASS — bagian yang sudah baik dan tidak perlu disentuh

- **Use case, ERD, dan implementasi** — bertemu tanpa celah.
- **Otorisasi lima peran** — menu, halaman, aksi, widget, dan penyaringan data
  seluruhnya terkunci pada perannya. Tidak ada kebocoran data antar tim.
- **Alur enam tahap permintaan** — lengkap, tidak ada status yatim, tidak ada
  tombol pada tahap yang salah.
- **Perhitungan stok dan saldo** — satu sumber (`KartuKendaliService::data()`)
  dipakai layar, PDF, dan Excel.
- **Dokumen PDF** — tata letak, margin, pemenggalan halaman, pengulangan judul
  kolom, catatan kaki.
- **QR dan pembentukan alamatnya** — bersih dari `localhost`/`127.0.0.1`/`null`,
  lambang menyatu, terbaca sampai 200 dpi.
- **Zona waktu** — WIB, dan jam kerja ikut benar.
- **Animasi dan `prefers-reduced-motion`** — durasi wajar, cakupan lengkap.
- **Responsivitas** — tidak ada guliran mendatar pada halaman mana pun.
- **Notifikasi** — dismiss tidak menghapus histori.
- **Perkakas pemeliharaan** — ketiga perintah utuh, penjaga produksi masih
  berdiri pada `ResetDemo.php:37`.
- **Kebersihan teknis** — 0 TODO, 0 view mati, 0 galat konsol, 271 uji lulus.

---

## 25. Final Verdict

> ## NOT READY TO FREEZE

Satu hal yang benar-benar menghalangi:

**P0-1 — menghapus barang persediaan memusnahkan seluruh buku besar mutasinya
secara diam-diam.** Sistem yang keluaran utamanya adalah Kartu Kendali tidak
boleh menyediakan satu klik yang menghapus sumber kartu itu tanpa peringatan,
apalagi ketika kolom `status_aktif` untuk menonaktifkan barang sudah tersedia.
Perbaikannya terbatas pada dua berkas Filament dan tidak menyentuh basis data,
alur, maupun peran.

Selebihnya tidak menghalangi. Empat temuan P1 sebaiknya dikerjakan sebelum
freeze karena murah dan menyangkut kejujuran dokumen serta ketepatan
dokumentasi; delapan temuan P2 boleh ditunda tanpa akibat — kecuali **P2-8**,
yang bukan soal kualitas sistem melainkan keselamatan pekerjaan Anda sendiri.

**Urutan yang saya sarankan:** P2-8 (komit dulu, agar ada titik pulih) → P0-1 →
P1-5 → P1-1 → P1-2 → P1-3 → P1-4.

Tidak ada satu pun rekomendasi di atas yang menambah fitur, mengubah alur,
menyentuh skema, atau mengubah data.
