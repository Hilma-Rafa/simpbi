# Rencana Pembalikan Alur Mutasi Aset (BAST) — Investigasi Fase 1

Tanggal: 23 September 2026. Dokumen ini awalnya adalah **hasil investigasi dan
perencanaan saja** (Fase 1) — tidak ada kode yang diubah untuk menghasilkannya.

> **STATUS: DIEKSEKUSI, 23 September 2026.** Pemilik sistem menjawab seluruh
> pertanyaan terbuka bagian G (lihat jawaban tercatat di tiap sub-bagian G di
> bawah) dan memberi keputusan baru yang mengganti sebagian rencana bagian B/C
> (ketik NIP dihapus sepenuhnya, diganti dialog rincian yang tuntas sekali
> klik) — lihat `docs/audit/laporan-perbaikan.md` bagian "Pembalikan urutan
> alur Mutasi Aset" untuk ringkasan eksekusi, berkas yang diubah, dan hasil
> pengujian. Dokumen ini **dipertahankan apa adanya sebagai catatan Fase 1**
> (peta investigasi awal); tidak diedit ulang menyeluruh mengikuti hasil
> eksekusi, kecuali penambahan status di tiap sub-bagian G ini.

Skill proyek yang diperiksa: seluruh isi `.claude/skills/` adalah skill tampilan/desain
front-end (animasi, tema visual, dsb.) dan tidak relevan bagi investigasi backend/alur
ini. **Tidak ada skill proyek yang dipakai.**

Cakupan keputusan pemilik sistem yang menjadi dasar rencana ini: **A** (pembalikan
urutan), **B** (pop-up pembuatan BAST), **C** (pop-up Sahkan/Konfirmasi + ketik NIP),
**D** (hapus field Pihak Penyerah/Penerima dari UI), **E** (penjaga tanda tangan sebelum
Buat), **F** (wajib Tim Kerja saat Buat Aset Tetap).

---

## Ringkasan Temuan Kunci (baca ini dulu)

1. **Nama field status TIDAK berubah**, dan lebih penting lagi: **guard input status
   pada `sahkan()` dan `konfirmasi()` juga TIDAK perlu berubah** (`sahkan()` tetap
   mensyaratkan input `menunggu_pengesahan`; `konfirmasi()` tetap mensyaratkan input
   `menunggu_konfirmasi`). Yang berubah hanya: (a) status yang ditulis
   `CreateBastMutasiAset` saat pembuatan (dari `menunggu_pengesahan` menjadi
   `menunggu_konfirmasi`), dan (b) status **keluaran** tiap method (`sahkan()` kini
   keluar ke `selesai_administratif`, bukan `menunggu_konfirmasi`; `konfirmasi()` kini
   keluar ke `menunggu_pengesahan`, bukan `selesai_administratif`).
2. Akibatnya, **`->visible()` pada aksi tabel `sahkan` dan `konfirmasi`
   (`BastMutasiAsetsTable.php`) TIDAK PERLU DIUBAH SAMA SEKALI** — keduanya sudah
   ditulis dalam istilah status ("tampil bila status = menunggu_pengesahan/
   menunggu_konfirmasi"), bukan dalam istilah "langkah pertama/terakhir". Hanya teks
   `modalDescription()` pada aksi `sahkan` yang salah secara faktual di alur baru
   (lihat A.7) dan perlu diperbaiki.
3. **Tata letak visual PDF BAST TIDAK PERLU berubah** (lihat A.6) — kondisi tampil tiap
   blok tanda tangan sudah dikaitkan ke kolom timestamp (`disahkan_at`,
   `dikonfirmasi_at`), bukan ke posisi langkah ke berapa. Menukar method mana yang
   mengisi timestamp mana sudah cukup; tidak ada baris CSS/HTML pada
   `bast-mutasi.blade.php` yang perlu disentuh.
4. **`pihak_penyerah`/`pihak_penerima` adalah kolom database sungguhan, NOT NULL, tanpa
   default** (lihat D). Karena aturan proyek melarang migration baru, keduanya **tetap
   ada di skema** — hanya dihapus dari form dan tabel — dan kode pembuatan BAST **wajib
   tetap mengisinya dengan nilai bukan-null** (lihat pertanyaan terbuka G.3).
5. **Pola "dialog rincian" dan "ketik NIP" pada Permintaan Barang BELUM pernah dipakai
   untuk BAST** — dikonfirmasi langsung dari kode: aksi `sahkan`/`konfirmasi` pada
   `BastMutasiAsetsTable.php` hari ini hanya `->requiresConfirmation()` polos tanpa
   `schema()` dan tanpa field NIP (lihat C).
6. **Keputusan F menyentuh footprint test yang tidak berkaitan dengan A–E**: empat test
   di `RiwayatPenempatanAsetTest.php` secara eksplisit menguji "buat Aset Tetap TANPA
   Tim Kerja lewat form Buat lalu isi belakangan lewat Ubah" — skenario yang menjadi
   **mustahil** begitu F diterapkan (lihat F dan B.7).

---

## A. Pemetaan Logika Lama yang Tersentuh Urutan

### A.1–A.4 — `app/Services/MutasiAsetService.php`

**Method demi method, dipetakan ke perilaku BARU:**

| Method | SEKARANG | BARU |
|---|---|---|
| `nomorBaru()` (baris 21–35) | Tidak terkait urutan | **Tidak berubah.** |
| `pesanAsetTidakDapatDimutasi()` (baris 45–61) | Blokir bila ada BAST `menunggu_pengesahan` dengan `tim_asal_id` = penempatan sekarang | **Blokir string status berubah menjadi `menunggu_konfirmasi`** — itulah status yang kini berarti "BAST masih berjalan dan aset belum berpindah". Pesan teks "...BAST yang menunggu pengesahan..." juga perlu diperbarui menjadi "...menunggu konfirmasi...". |
| `periksaPembuatan()` (baris 71–98) | Memanggil `pesanAsetTidakDapatDimutasi()` | **Tidak perlu logika baru** — otomatis ikut berubah begitu method di atasnya berubah. Pemeriksaan `status_aktif` (F-006) dan `blank(tim_penempatan_id)` tidak tersentuh. |
| `kunciDanPastikanStatus()` (baris 111–118) | Generik, dipanggil dengan status yang diharapkan sebagai parameter | **Tidak berubah** — hanya *pemanggil*-nya yang berbeda string (lihat baris berikut). |
| `sahkan()` (baris 130–169) | Input wajib `menunggu_pengesahan`. Isinya: (1) kunci ulang status, (2) kunci baris aset & **periksa ulang** `aset.tim_penempatan_id === bast.tim_asal_id` (cek "usang", MA-16), (3) set `disahkan_oleh_id`/`disahkan_at`/status→`menunggu_konfirmasi`, (4) **pindahkan** `aset.tim_penempatan_id` ke tujuan, (5) **tutup** baris riwayat lama, (6) **buka** baris riwayat baru (`jenis: mutasi`), (7) bentuk ulang dokumen | **Input TETAP `menunggu_pengesahan`** (tidak berubah — lihat Ringkasan #1). Isi BARU: (1) kunci ulang status (tetap), (2) **HAPUS** langkah kunci-aset dan cek-usang (pindah ke `konfirmasi()`, lihat baris berikut), (3) set `disahkan_oleh_id`/`disahkan_at`/**status→`selesai_administratif`**, (4)–(6) **DIHAPUS** (tidak lagi memindahkan aset atau menulis riwayat — itu sudah terjadi di `konfirmasi()` yang sekarang berjalan lebih dulu), (7) bentuk ulang dokumen (tetap, di sinilah e-TTD/QR Kasubbag akhirnya terbubuh sebagai finalisasi). |
| `konfirmasi()` (baris 180–197) | Input wajib `menunggu_konfirmasi`. Isinya: (1) kunci ulang status, (2) set `dikonfirmasi_oleh_id`/`dikonfirmasi_at`/status→`selesai_administratif`, (3) bentuk ulang dokumen (di sinilah TTD penerima akhirnya terbubuh) | **Input TETAP `menunggu_konfirmasi`** (tidak berubah). Isi BARU: (1) kunci ulang status (tetap), **(2) TAMBAH** langkah kunci-aset dan cek-usang yang dipindah dari `sahkan()` lama (bila `aset.tim_penempatan_id !== bast.tim_asal_id`, tolak dengan pesan yang sepadan dengan `PESAN_SAHKAN_USANG` sekarang — hanya pesannya perlu disesuaikan agar menyebut "konfirmasi", bukan "disahkan"), **(3) TAMBAH** langkah pindahkan `aset.tim_penempatan_id` ke tujuan + tutup/buka riwayat (dipindah dari `sahkan()` lama), (4) set `dikonfirmasi_oleh_id`/`dikonfirmasi_at`/**status→`menunggu_pengesahan`** (bukan lagi `selesai_administratif`), (5) bentuk ulang dokumen (tetap — di sinilah TTD penerima terbubuh, sama seperti sekarang, hanya lebih awal dalam urutan). |

**Detail penting yang mudah terlewat:** pemeriksaan "usang" (BAST batal berlaku karena
penempatan aset sudah berubah sejak BAST dibuat, MA-16) **harus ikut berpindah bersama
logika pemindahan aset**, sebab pemeriksaan itu hanya masuk akal tepat sebelum aset
benar-benar dipindah. Method `sahkan()` yang baru (finalisasi murni, tanpa menyentuh
baris aset) **tidak butuh pemeriksaan usang sama sekali** — persis seperti `konfirmasi()`
versi lama yang juga tidak pernah melakukan pemeriksaan semacam itu (simetris).

`nomorBaru()` dan `periksaPembuatan()` (bagian pemeriksaan `status_aktif`/`blank`
tim_penempatan) sama sekali tidak tersentuh keputusan A.

### A.5 — `app/Services/NotifikasiService.php::bastBerubah()`

**Temuan mengejutkan:** pemetaan **penerima per nilai status TIDAK PERLU BERUBAH**,
sebab penerima ditentukan oleh *arti* status ("menunggu_pengesahan" secara inheren
berarti "Kasubbag perlu bertindak", terlepas dari kapan status itu tercapai dalam
urutan):

| Status | Penerima sekarang (kode) | Perlu diubah? |
|---|---|---|
| `menunggu_pengesahan` | Kasubbag | **Tidak** — status ini kini tercapai setelah Konfirmasi (bukan saat dibuat), tapi "Kasubbag perlu bertindak" tetap benar. |
| `menunggu_konfirmasi` | Ketua Tim tim tujuan | **Tidak** — status ini kini tercapai saat BAST dibuat (bukan setelah Sahkan), tapi "Ketua Tim tujuan perlu bertindak" tetap benar. |
| `selesai_administratif` | Kasubbag + Petugas Gudang | Tidak berubah (status akhir, tidak tersentuh urutan). |

**Yang WAJIB berubah:** teks pesan pada cabang `menunggu_konfirmasi`
(`NotifikasiService.php:342–347`) saat ini berbunyi *"{$nomor} **telah disahkan**.
{$aset} **dipindahkan** ke {$tujuan}..."* — kalimat ini mengandaikan Sahkan (dan
perpindahan aset) sudah terjadi. Di alur baru, cabang ini terpicu **saat BAST baru
dibuat**, sebelum siapa pun bertindak dan sebelum aset berpindah. Pesan harus ditulis
ulang menjadi sesuatu seperti *"{$nomor} untuk mutasi {$aset} dari {$asal} ke {$tujuan}
menunggu konfirmasi penerimaan Anda."* — pola kalimat yang sama dengan cabang
`menunggu_pengesahan` yang sudah ada.

Cabang `menunggu_pengesahan` (baris 334–339) tidak wajib diubah — teksnya sudah generik
("menunggu pengesahan Anda") dan tidak mengandaikan urutan tertentu — meski boleh
dipertegas ("...telah dikonfirmasi diterima, menunggu pengesahan Anda") sebagai polesan
opsional, bukan keharusan.

**Ini usulan, bukan keputusan final saya** — pemilik perlu mengonfirmasi kalimat
pengganti persisnya (lihat G.1).

### A.6 — `app/Services/DokumenBastService.php` dan `resources/views/pdf/bast-mutasi.blade.php`

**Jawaban eksplisit: TATA LETAK TIDAK PERLU BERUBAH. Hanya WAKTU pengisian yang
berubah**, dan itu pun otomatis mengikuti perubahan A.1–A.4 tanpa menyentuh berkas
blade sama sekali. Dibuktikan dengan menelusuri tiap kondisi render:

- **Blok "Yang Menyerahkan" (Ketua Tim asal):** `$namaPenyerah`/`$ttdPenyerah`
  **tidak bersyarat status apa pun** (`DokumenBastService.php:46–52`) — selalu terisi
  sejak draf pertama kali dibentuk, tidak peduli urutan. Tidak terdampak.
- **Blok "Yang Menerima" (Ketua Tim tujuan):** `$ttdPenerima` bersyarat
  `$bast->dikonfirmasi_at` (baris 53–55). Kolom `dikonfirmasi_at` ini **tetap** diisi
  oleh `konfirmasi()` — hanya *kapan* `konfirmasi()` dipanggil dalam urutan yang
  berubah (kini pertama, bukan terakhir). Kondisi blade `@if (($ttdPenerima ?? '') !== '')`
  tidak perlu disentuh.
- **Blok pengesahan Kasubbag (QR e-TTD) + tautan verifikasi:** bersyarat
  `$bast->disahkan_at` (baris 67–69, 209–214 blade). Kolom `disahkan_at` **tetap**
  diisi oleh `sahkan()` — hanya *kapan* dipanggil yang berubah (kini terakhir). Tidak
  perlu disentuh.
- **Catatan kaki (kode QR + klaim e-TTD):** juga bersyarat `$bast->disahkan_at`
  (blade baris 231). Sama, tidak perlu disentuh.

Karena tiap kondisi dikaitkan ke **kolom timestamp**, bukan ke "langkah keberapa",
menukar isi `sahkan()`/`konfirmasi()` (A.1–A.4) sudah cukup membuat seluruh dokumen
terisi benar pada waktu yang benar, tanpa mengubah satu baris pun di
`bast-mutasi.blade.php` atau `DokumenBastService.php`.

**Konsekuensi positif tervalidasi:** tombol "Unduh BAST" (`BastMutasiAsetsTable.php:190`,
bersyarat `filled($r->disahkan_at)`) **otomatis** hanya tampil setelah
`selesai_administratif` di alur baru — sebab `disahkan_at` kini hanya pernah terisi pada
langkah terakhir. Ini **tidak memerlukan perubahan kode**, persis seperti asumsi pemilik
di keputusan A. (Catatan latar belakang, bukan risiko baru: di alur LAMA, tombol ini
sebetulnya sudah tampil sejak status `menunggu_konfirmasi` — sebelum
`selesai_administratif` — karena `disahkan_at` terisi di langkah pertama; ini adalah
perilaku lama yang sudah berjalan, bukan sesuatu yang perlu diperbaiki sebagai bagian
tugas ini.)

### A.7 — `BastMutasiAsetsTable.php` dan `BastMutasiAsetForm.php`

**`BastMutasiAsetsTable.php`:**

- `->visible()` pada aksi `sahkan` (baris 85) dan `konfirmasi` (baris 127–129):
  **TIDAK PERLU DIUBAH** — keduanya sudah dalam istilah status (`menunggu_pengesahan`
  / `menunggu_konfirmasi`) yang tidak berubah sebagai syarat *input*.
- `modalDescription()` aksi `sahkan` (baris 84): **WAJIB diubah** — teks sekarang
  berbunyi *"Sahkan {$r->nomor_bast}? **Penempatan aset akan dipindahkan** ke
  {$r->timTujuan?->nama_tim} dan e-TTD dibubuhkan pada dokumen."* Di alur baru, Sahkan
  tidak lagi memindahkan apa pun — hanya memfinalisasi dokumen. Usul teks pengganti:
  *"Sahkan {$r->nomor_bast}? Dokumen akan difinalisasi dengan e-TTD Anda."*
- `modalDescription()` aksi `konfirmasi` (baris 125): sudah cocok dengan semantik baru
  ("Konfirmasikan bahwa aset telah diterima... tanda tangan Anda dibubuhkan sebagai
  pihak penerima") — **tidak wajib diubah**, meski boleh ditambah kalimat bahwa
  penempatan aset berpindah saat ini juga.
- Penanganan galat (`try/catch RuntimeException` → `StokService::pesanAturan`) pada
  kedua aksi: tidak tersentuh, generik terhadap pesan apa pun yang dilempar.

**`BastMutasiAsetForm.php`:** logika `tim_asal_id` (otomatis dari penempatan aset
sekarang, terkunci) tidak tersentuh keputusan A sama sekali — perilaku itu berlaku
independen dari urutan Sahkan/Konfirmasi. Perubahan pada berkas ini murni berasal dari
keputusan B (jadi pop-up) dan D (hapus dua field).

---

## B. Pop-up Pembuatan BAST

### B.1 — Bagaimana pola "dialog rincian" Permintaan Barang bekerja (untuk ditiru)

Ditelusuri dari `PermintaanBarangResource::aksiDetail()`
(`PermintaanBarangResource.php:1230–1284`):

- Satu `Filament\Actions\Action::make('detail')` terpasang sebagai *aksi baris*
  (bukan tombol berdiri sendiri — dipicu klik baris, sesuai catatan komentar kode
  "Rincian disajikan sebagai komponen skema, bukan modalContent").
- `->modalSubmitAction(false)` — dialog ini murni tampilan, tidak punya tombol kirim
  sendiri.
- `->schema(fn ($record) => [View::make('filament.partials.detail-permintaan')
  ->viewData([...])])` — isi dialog adalah satu partial Blade yang menampilkan data
  rincian, dimuat dengan *eager loading* relasi yang dibutuhkan.
- `->extraModalFooterActions([...])` — berisi **Action lain yang sudah ada**
  (`aksiSetujuiKetua()`, `aksiSahkan()`, dst.), masing-masing dengan `->visible()`,
  `->schema()` (formulir aksinya sendiri, termasuk `bidangKonfirmasiNip()`), dan
  `->action()` sendiri. Filament merender tombol-tombol ini di kaki dialog rincian;
  mengklik salah satunya membuka **dialog kedua (bertumpuk)** berisi skema aksi
  tersebut — inilah tempat field "ketik NIP" muncul, SETELAH rincian sudah terbaca.

Pola ini **langsung dapat ditiru** untuk BAST: aksi-aksi `sahkan`, `konfirmasi`, dan
`unduh` yang sudah ada di `BastMutasiAsetsTable.php` tinggal dibungkus sebagai
`extraModalFooterActions` dari satu `Action::make('detail')` baru, dengan partial Blade
baru (mis. `filament.partials.detail-bast`) yang menampilkan aset, tim asal/tujuan,
alasan mutasi, dan status.

### B.2 — Pembuatan BAST via modal: CreateAction, bukan CreateRecord page

**Temuan penting:** `ListBastMutasiAsets.php` **sudah memakai**
`Filament\Actions\CreateAction::make()->label('Buat BAST')` (baris 16) — bukan tautan
manual ke halaman. Namun karena `BastMutasiAsetResource::getPages()` masih mendaftarkan
rute `'create' => CreateBastMutasiAset::route('/create')`, Filament mengarahkan
`CreateAction` ini ke **URL halaman terpisah**, bukan membuka modal — perilaku baku
Filament: `CreateAction` hanya menjadi modal ketika resource **tidak** punya halaman
`create` terdaftar.

**Rencana konkret (bukan pendekatan baru, pola Filament standar):**

1. Hapus entri `'create' => CreateBastMutasiAset::route('/create')` dari
   `BastMutasiAsetResource::getPages()` (berkas `CreateBastMutasiAset.php` itu sendiri
   dapat dihapus setelah logikanya dipindah).
2. `CreateAction::make()` yang sudah ada di `ListBastMutasiAsets.php` otomatis berubah
   menjadi modal begitu tidak ada halaman create untuk dituju, memakai skema dari
   `BastMutasiAsetResource::form()` (yaitu `BastMutasiAsetForm::configure()`, yang tetap
   dipakai bersama — form ini tidak perlu digandakan).
3. Logika yang sekarang ada di method-method `CreateBastMutasiAset` (halaman) harus
   dipindah ke hook setara pada `CreateAction`, yang semuanya tersedia di Filament:
   - `mutateFormDataBeforeCreate()` → `CreateAction::mutateFormDataUsing()`.
   - `handleRecordCreation()` (loop retry UNIQUE + `periksaPembuatan()` dalam transaksi)
     → `CreateAction::using(function (array $data): Model { ... })`.
   - `afterCreate()` (bentuk dokumen draf + notifikasi) → `CreateAction::after()`.
   - `getRedirectUrl()` tidak relevan lagi — modal menutup sendiri, tidak ada navigasi.
4. Field "Aset yang Dimutasi" dan "Berita Acara" tetap dari `BastMutasiAsetForm`
   (dikurangi `pihak_penyerah`/`pihak_penerima` per keputusan D), ditambah UI penjaga
   tanda tangan per keputusan E.

Ini **refactor menengah**, bukan trivial: logika transaksi + retry percobaan tiga kali
yang sekarang berada di method halaman (`CreateBastMutasiAset::handleRecordCreation()`,
41 baris) harus dipindah utuh ke closure `CreateAction::using()` tanpa kehilangan
perilakunya (MA-1 s.d. MA-5 di `docs/pengujian/03-modul-3-mutasi-aset.md`).

### B.3 — Reuse pola "ketik NIP" (A-015b) untuk pembuatan, Sahkan, dan Konfirmasi

Method `bidangKonfirmasiNip()`, `cocokIdentitas()`, dan `ratakanTeks()`
(`PermintaanBarangResource.php:658–730`) **saat ini adalah `protected static` milik
`PermintaanBarangResource`** — bukan trait atau kelas Support yang sudah dipisah untuk
dipakai ulang. Secara **logika** (perbandingan NIP/nama-tim terhadap akun yang sedang
masuk, tidak peka huruf besar-kecil, dsb.) polanya bisa dipakai identik untuk pembuatan
BAST, Sahkan, dan Konfirmasi — tidak ada penyesuaian konseptual yang dibutuhkan karena
"membuat" dan "menyetujui" sama-sama tindakan yang perlu konfirmasi identitas pelaku.

Namun **secara struktur kode**, memakainya ulang untuk BAST berarti salah satu dari:

- **(a)** menduplikasi ketiga method itu ke `BastMutasiAsetResource` (cepat, konsisten
  dengan kondisi kode saat ini yang memang belum ada abstraksi bersama), atau
- **(b)** mengekstraknya lebih dulu menjadi kelas Support bersama (mis.
  `App\Support\KonfirmasiIdentitas`) yang dipakai kedua resource.

Ini keputusan rancangan kecil yang sebaiknya dikonfirmasi pemilik, bukan diasumsikan
(lihat G.5) — pilihan (a) tidak menciptakan konsep baru dan lebih aman terhadap
regresi Permintaan Barang, tetapi menambah duplikasi; pilihan (b) lebih bersih tetapi
menyentuh berkas yang sudah stabil dan teruji (`PermintaanBarangResource.php`).

### B.4 — Kondisi pola "dialog rincian + ketik NIP" pada Sahkan/Konfirmasi BAST **hari ini**

**Dikonfirmasi: BELUM ADA sama sekali.** Dibaca langsung dari
`BastMutasiAsetsTable.php:78–172` — kedua aksi (`sahkan`, `konfirmasi`) memakai
`->requiresConfirmation()` bawaan Filament (dialog konfirmasi polos "Ya/Batal" dengan
`modalDescription()` sebagai teks statis) tanpa `->schema()` apa pun. Tidak ada field
NIP, tidak ada dialog rincian terpisah dari dialog konfirmasi itu sendiri. Ini
membenarkan dugaan pada instruksi tugas.

---

## C. Diagram Alur Baru (versi teks)

```
[Petugas Gudang: Buat BAST — pop-up]
   → menjaga: Ketua Tim Tim Asal & Ketua Tim Tim Tujuan sama-sama sudah
     punya tanda tangan tersimpan (E), tombol Buat terkunci bila belum.
   → ketik NIP/nama tim untuk konfirmasi pembuatan (B.3/C).
   ↓
status = "menunggu_konfirmasi"   (BARU — dulu status awal ialah menunggu_pengesahan)
   → notifikasi: Ketua Tim Tim Tujuan
   ↓
[Ketua Tim Tim Tujuan: Konfirmasi Penerimaan — dialog rincian + ketik NIP/nama tim]
   → di sinilah ASET BERPINDAH: tim_penempatan_id diperbarui, riwayat
     penempatan lama ditutup, riwayat mutasi baru dibuka, TTD penerima
     terbubuh pada dokumen. Pemeriksaan "usang" (penempatan berubah sejak
     BAST dibuat) terjadi di sini.
   ↓
status = "menunggu_pengesahan"   (BARU — dulu status ini tercapai saat dibuat)
   → notifikasi: Kasubbag Umum
   ↓
[Kasubbag Umum: Sahkan — dialog rincian + ketik NIP]
   → finalisasi murni: e-TTD + QR verifikasi Kasubbag terbubuh, dokumen
     final terbentuk. TIDAK menyentuh aset atau riwayat lagi (sudah
     terjadi di langkah Konfirmasi).
   ↓
status = "selesai_administratif"
   → notifikasi: Kasubbag + Petugas Gudang
   → tombol "Unduh BAST" baru tampil di sini (disahkan_at akhirnya terisi).
```

Nama status (`menunggu_pengesahan`, `menunggu_konfirmasi`, `selesai_administratif`)
dipertahankan apa adanya sesuai instruksi — hanya kapan tiap status dicapai, dan siapa
yang bertindak untuk mencapainya, yang terbalik.

---

## D. Pemeriksaan Pihak Penyerah/Pihak Penerima

**Migration** (`2026_08_26_000007_create_bast_mutasi_aset_table.php:18–19`):

```php
$table->string('pihak_penyerah', 100);
$table->string('pihak_penerima', 100);
```

**Jawaban eksplisit: keduanya kolom database sungguhan** — `string(100)`, **tidak
nullable**, **tanpa default value**. Bukan sekadar field form yang tidak persisten.

**Konsekuensi (sesuai aturan proyek — tanpa migration baru):**

- Kolom **tetap ada di skema database**, tidak dipakai secara bermakna.
- **Dihapus dari `BastMutasiAsetForm.php`** (baris 83–90, dua `TextInput`) — tidak lagi
  diminta diisi pengguna.
- **Dihapus dari tampilan tabel** — sebenarnya kedua field ini **sudah tidak pernah
  ditampilkan** di `BastMutasiAsetsTable.php` (kolom tabelnya hanya nomor, aset, asal,
  tujuan, status, tanggal) — jadi untuk bagian tabel, tidak ada yang perlu dihapus,
  hanya dikonfirmasi bahwa memang sudah tidak ada.
- Karena kolom NOT NULL tanpa default, **kode pembuatan BAST (CreateAction hasil B)
  wajib tetap mengisi kedua kolom ini dengan nilai bukan-null** saat `INSERT`, meski
  nilainya tidak lagi berasal dari input pengguna. Ini **pertanyaan terbuka** (lihat
  G.3) — dua opsi wajar: string kosong `''`, atau nama Ketua Tim asal/tujuan pada saat
  pembuatan (sekadar arsip, tidak pernah dibaca ulang oleh dokumen).

**Konfirmasi PDF tidak pernah membaca kedua field ini — dibuktikan, bukan diasumsikan:**

- `DokumenBastService.php:32–41` (komentar kode eksplisit): *"Identitas dan tanda
  tangan kedua pihak diambil dari akun masing-masing... bukan dari kolom
  pihak_penyerah / pihak_penerima yang diketik operator."* — nama & TTD diambil dari
  `$bast->timAsal->ketuaTim` dan `$bast->dikonfirmasiOleh ?? $bast->timTujuan->ketuaTim`.
- `resources/views/pdf/bast-mutasi.blade.php:171–199`: blok tanda tangan memakai
  `$namaPenyerah`/`$namaPenerima`/`$ttdPenyerah`/`$ttdPenerima` (variabel yang dikirim
  `DokumenBastService`, bukan `$bast->pihak_penyerah`/`$bast->pihak_penerima` sama
  sekali) — kedua kolom itu **tidak muncul satu kali pun** di seluruh berkas blade.

Tidak ditemukan satu pun pemakaian `pihak_penyerah`/`pihak_penerima` di luar
`BastMutasiAsetForm.php` (form input) — dikonfirmasi lewat pencarian menyeluruh pada
`app/`.

---

## E. Penjaga Tanda Tangan Sebelum Buat

### Pola yang sudah ada untuk ditiru

`App\Support\TandaTangan` (baris 93–96) sudah punya `terdaftar(?User $pengguna): bool`
— memeriksa kolom `tanda_tangan_path` terisi (tanpa memeriksa berkas fisik ada di
cakram). Method inilah yang sudah dipakai sebagai **lapis terakhir** sebelum aksi
Konfirmasi Penerimaan BAST (`BastMutasiAsetsTable.php:136`) dan implisit oleh gerbang
pelengkapan akun. Ada pula `tersedia(?User $pengguna): bool` (baris 78–81) yang lebih
ketat (memeriksa berkas benar-benar ada di cakram) — dipakai `DokumenBastService`
secara tidak langsung lewat `dataUri()`.

Sumber kebenaran "siapa Ketua Tim suatu tim" — **sudah diselaraskan lewat perbaikan
A-012** — adalah relasi `Tim::ketuaTim()` (kolom `tim.ketua_tim_id`), **bukan**
`User::where('role','ketua_tim')->where('tim_id', ...)`. Ini persis yang dipakai
`DokumenBastService.php:46–47` (`$bast->timAsal?->ketuaTim`,
`$bast->timTujuan?->ketuaTim`) dan seeder BAST (`BastMutasiAsetSeeder.php:61–64`).
Penjaga baru **wajib memakai sumber yang sama** — `Tim::query()->find($timId)?->ketuaTim`
— supaya konsisten dengan yang sudah diperbaiki, bukan sumber kedua yang bisa berbeda.

### Rekomendasi lokasi pemeriksaan: form (UX) + server (jaring pengaman)

Konsisten dengan pola dua-lapis yang sudah berlaku di proyek ini (mis. A-011: form
menampilkan galat + server memeriksa ulang di `periksaPembuatan()`):

- **Form (UX, reaktif):** pada CreateAction hasil B, tambahkan komponen reaktif
  (`Placeholder` peringatan, mengikuti pola `ketuaBelumBertandaTangan` yang sudah ada
  di `PermintaanBarangResource.php:594–606`) yang memantau `tim_asal_id` (sudah
  otomatis dari aset yang dipilih) dan `tim_tujuan_id` (dipilih pengguna, `->live()`),
  lalu mengevaluasi ulang `TandaTangan::terdaftar($timAsal?->ketuaTim)` dan
  `TandaTangan::terdaftar($timTujuan?->ketuaTim)`. Tombol submit dibekukan
  (`->disabled(closure)`) selama salah satu belum terpenuhi, dengan pesan yang
  menyebut nama tim mana yang belum.
- **Server (jaring pengaman):** tambahkan pemeriksaan setara di dalam
  `MutasiAsetService::periksaPembuatan()` (atau closure `CreateAction::using()` yang
  memanggilnya di dalam transaksi yang sama) — melempar `\RuntimeException` dengan
  pesan yang sepadan bila salah satu Ketua Tim belum bertanda tangan, mengikuti pola
  pesan bisnis yang sudah ada (ditangkap `StokService::pesanAturan()` di pemanggil).

Ini konsisten dengan pola A-011 (validasi form dan server yang tumpang tindih tapi
tidak identik posisinya) — bukan logika baru yang berbeda gaya.

---

## F. Wajib Tim Kerja saat Buat Aset Tetap

### F.1–F.2 — Satu schema untuk Buat dan Ubah, dibedakan lewat `$operation`

Dikonfirmasi (`AsetTetapForm.php:52–67`): field `tim_penempatan_id` memang **satu
schema yang sama** dipakai `CreateAsetTetap` dan `EditAsetTetap` (tidak ada dua berkas
form terpisah). Kondisi kunci-bersyarat F-002 yang sudah ada
(`->disabled(fn (?Model $record) => filled($record?->tim_penempatan_id))`) memakai
closure `(?Model $record)` — pada konteks Buat, `$record` bernilai `null` sehingga
`disabled()` selalu `false` (field selalu aktif di Buat, sesuai desain sekarang).

**Presedan langsung sudah ada di proyek ini** untuk membedakan Buat vs Ubah pada satu
schema yang sama: `app/Filament/Resources/Users/Schemas/UserForm.php:73` memakai
```php
->required(fn (string $operation): bool => $operation === 'create')
```
Filament menyediakan parameter `$operation` ini ke closure schema (didukung
`Filament\Schemas\Concerns\HasOperation`), sehingga rencana berikut **bukan pola baru**:

```php
Select::make('tim_penempatan_id')
    ->label('Tim Kerja')
    ->relationship('timPenempatan', 'nama_tim')
    ->searchable()
    ->preload()
    // Placeholder "Belum ditempatkan" hanya relevan di Ubah, pada aset lama
    // yang datanya memang kosong — bukan lagi opsi yang ditawarkan di Buat.
    ->placeholder(fn (string $operation): ?string => $operation === 'create' ? null : 'Belum ditempatkan')
    ->required(fn (string $operation): bool => $operation === 'create')
    ->disabled(fn (?Model $record): bool => filled($record?->tim_penempatan_id))
    ->dehydrated(fn (?Model $record): bool => blank($record?->tim_penempatan_id))
    ->helperText(...) // tidak berubah
```

Logika kunci-bersyarat F-002 pada Ubah (`disabled()`/`dehydrated()` berdasar
`$record`) **tidak tersentuh** — aset lama yang masih kosong tetap dapat diisi sekali
lewat Ubah, sebagaimana desain F-002 yang tidak diubah keputusan F ini.

### F.3 — Status kolom Tim Kerja Penempatan pada template impor

**Dikonfirmasi dari `ImporAsetTetap::kolom()` (baris 90–97):** kolom `tim_penempatan`
**TIDAK** memakai `wajib: true` (berbeda dari `nup`, `nama_aset`, `kategori` yang
eksplisit `wajib: true`) — **saat ini opsional**. Baris impor tanpa Tim Kerja pada aset
baru lolos dan menghasilkan aset dengan `tim_penempatan_id = null` (dikonfirmasi lewat
alur `jalankan()` baris 187–212: `$timPenempatan` tetap `null` bila kolom kosong, tidak
ada penolakan baris).

**Usulan (bukan bagian otomatis keputusan F — perlu dikonfirmasi terpisah, lihat G.5):**
mewajibkan kolom ini agar konsisten dengan form, dengan pesan penolakan baris yang
jelas (pola sudah ada: `catatGalat($nomor, pesan)`) bagi baris aset **baru** (aset yang
sudah ada tetap boleh kosong di kolom ini, karena memang diabaikan untuk aset lama —
lihat `$aset && $namaTim !== ''` di baris 193–197).

### F.4 — Jumlah aset tanpa penempatan di basis data kerja

Dihitung langsung (baca-saja, `php artisan tinker`), **1 dari 13** aset tetap pada basis
data kerja saat ini memiliki `tim_penempatan_id = null`. Angka ini akan tetap ada
sebagai data lama meski aturan F berlaku untuk aset **baru** ke depan. Pengaman
"aset tanpa penempatan ditolak saat BAST dibuat" (MA-6/`pesanAsetTidakDapatDimutasi()`,
MA-11/`periksaPembuatan()`) **tetap dipertahankan** sebagai jaring pengaman bagi data
lama ini — jalur itu tidak diusulkan dihapus, hanya akan makin jarang terpicu untuk
aset baru.

### F.5 — Apakah F berdiri sendiri, atau terikat A–E?

**Secara kode: berdiri sendiri.** Tidak ada satu pun method di `MutasiAsetService.php`,
`BastMutasiAsetsTable.php`, atau `NotifikasiService.php` yang bergantung pada apakah
field `tim_penempatan_id` opsional atau wajib saat Buat Aset Tetap — keduanya berada
di modul yang berbeda (`AsetTetapForm.php` vs `BastMutasiAsetForm.php`/
`MutasiAsetService.php`) dan tidak saling memanggil.

**Namun TIDAK bebas dari footprint test** (lihat B.7 di bagian test): empat test di
`RiwayatPenempatanAsetTest.php` secara eksplisit membangun skenario "Buat Aset Tetap
lewat form Livewire **tanpa** tim, lalu isi belakangan lewat Ubah" — begitu F
diterapkan, langkah pertama skenario ini (buat tanpa tim lewat form Create) akan gagal
validasi (`assertHasNoFormErrors()` yang mereka panggil akan gagal), sehingga keempatnya
**wajib ditulis ulang** terlepas dari apakah A–E dikerjakan bersamaan atau tidak.

**Rekomendasi urutan pengerjaan:** F dapat dikerjakan sebagai **batch tersendiri lebih
dulu**, terpisah dari A–E — cakupannya jauh lebih sempit (satu berkas form + satu
pertimbangan template impor + empat test), tidak membutuhkan refactor CreateAction/pop-up
apa pun, dan tidak bergantung pada keputusan A–E selesai lebih dulu.

---

## G. Risiko dan Pertanyaan Terbuka

Daftar berikut **harus dijawab pemilik sistem** sebelum Fase 2 (implementasi) dimulai:

1. **Teks pesan notifikasi pengganti (A.5). TERJAWAB (G.1):** pemilik
   memutuskan TIDAK memakai usulan redaksi baru di atas — teks literal
   `NotifikasiService::bastBerubah()` dipakai ulang apa adanya untuk tiap
   status, tanpa diubah kata-katanya sama sekali. Yang berubah hanya TITIK
   PEMANGGILAN: cabang `menunggu_konfirmasi` kini terpicu saat BAST dibuat
   (bukan saat disahkan), cabang `menunggu_pengesahan` kini terpicu saat
   Konfirmasi Penerimaan (bukan saat dibuat). Karena penerima per status tidak
   berubah, hampir seluruh kode `bastBerubah()` tidak disentuh — lihat
   `docs/audit/laporan-perbaikan.md`.
2. **Tata letak PDF BAST: TIDAK berubah** (dibuktikan di A.6, bukan sekadar
   diasumsikan) — sehingga tidak ada pelanggaran terhadap aturan "PDF beku" yang perlu
   persetujuan terpisah. *(Item ini sudah terjawab oleh investigasi; dicantumkan di
   sini karena diminta eksplisit oleh instruksi tugas bagian G.2.)*
3. **Nilai pengganti `pihak_penyerah`/`pihak_penerima` saat INSERT. TERJAWAB
   (G.3):** diisi otomatis dengan nama Ketua Tim asal/tujuan pada saat BAST
   dibuat — sumber yang SAMA PERSIS dengan yang dipakai `DokumenBastService`
   merender tanda tangan pada dokumen (`Tim::ketuaTim()`, A-012). Diterapkan
   di `ListBastMutasiAsets::getHeaderActions()` (`CreateAction::mutateFormDataUsing()`).
4. **Skala penulisan ulang test — lihat tabel di bawah.** Dari 82 test BAST yang ada,
   perkiraan **41 tetap berlaku tanpa perubahan, 40 perlu ditulis ulang (sebagian besar
   ringan — hanya penyesuaian fixture/status/teks; sebagian penuh — jalur bisnis inti),
   dan 1 menjadi tidak relevan** (isinya berpindah ke method lain). Realistis dipecah
   jadi beberapa batch mengikuti pola audit sebelumnya (per file/per kelompok
   MA-nomor), bukan sekaligus — lihat rincian di bagian "Test yang Terikat" bawah.
5. **Cara memakai ulang pola "ketik NIP" (B.3). TIDAK RELEVAN LAGI (G.5):**
   keputusan baru pemilik menghapus ketik NIP sepenuhnya dari ketiga aksi BAST
   (Buat/Sahkan/Konfirmasi), diganti dialog rincian yang tuntas sekali klik —
   tidak ada pola `bidangKonfirmasiNip()` yang perlu diduplikasi/diekstraksi
   untuk BAST sama sekali.
6. **Apakah F berdiri sendiri (F.5):** dikonfirmasi ulang saat eksekusi —
   Batch F (wajib Tim Kerja saat Buat Aset Tetap) **TIDAK termasuk cakupan
   eksekusi Fase 0-6 ini**, `app/Filament/Resources/AsetTetaps/` tidak
   disentuh saat itu. **BATCH F SELESAI DIKERJAKAN TERPISAH, 24 September
   2026** (bersamaan dengan perbaikan akses Buat BAST) — lihat
   `docs/audit/laporan-perbaikan.md` bagian "Perbaikan Batch F". Berdiri
   sendiri secara kode terbukti benar (tidak ada konflik dengan A–E), tetapi
   footprint test seperti diperkirakan: 4 test `RiwayatPenempatanAsetTest`
   ditulis ulang, plus 1 test file lain (`NupUnikAsetTetapTest.php`)
   ditemukan terdampak di luar perkiraan awal.
7. **Kolom Tim Penempatan pada template impor diwajibkan? (F.3) TERJAWAB:**
   TETAP OPSIONAL — tidak diwajibkan, dan tidak termasuk cakupan eksekusi ini
   sama sekali (Batch F terpisah). `ImporAsetTetap.php` tidak disentuh.
8. **Temuan tambahan dari investigasi (tidak diminta eksplisit, tapi berisiko bila
   terlewat di Fase 2):**
   - `database/seeders/BastMutasiAsetSeeder.php` memanggil
     `$layanan->sahkan($bast, $kasubbag->id)` tepat setelah `BastMutasiAset::create()`
     untuk membentuk contoh data peragaan. Di alur baru, status awal BAST adalah
     `menunggu_konfirmasi`, sehingga memanggil `sahkan()` (yang kini mensyaratkan input
     `menunggu_pengesahan`) akan **ditolak `kunciDanPastikanStatus()`**. Seeder ini wajib
     diperbarui memanggil `konfirmasi()` sebagai gantinya untuk tetap menghasilkan
     contoh data yang valid. Tidak disentuh pada Fase 1 ini (seeder adalah kode), hanya
     dicatat sebagai temuan wajib-diperbaiki di Fase 2.
   - Tanda tangan Ketua Tim tujuan pada seeder di atas: seeder ini **tidak**
     menjamin `$timTujuan->ketuaTim` sudah punya tanda tangan tersimpan (hanya
     mengutamakan tim yang *punya* Ketua Tim, bukan yang *sudah bertanda tangan*) —
     bila keputusan E diterapkan sebagai jaring pengaman server yang keras (menolak
     pembuatan BAST tanpa TTD kedua Ketua Tim), seeder ini berisiko gagal berjalan
     ulang pada data peragaan yang Ketua Timnya belum lengkapi akun. Perlu diperiksa
     ulang di Fase 2, bukan diasumsikan aman.

---

## Daftar Berkas yang Akan Berubah (Fase 2)

| Berkas | Ringkasan perubahan | Keputusan terkait |
|---|---|---|
| `app/Services/MutasiAsetService.php` | Tukar isi `sahkan()` ↔ `konfirmasi()` (movement + cek usang pindah ke `konfirmasi()`; status keluaran tertukar); ubah status blokir di `pesanAsetTidakDapatDimutasi()`; tambah pemeriksaan TTD kedua Ketua Tim di `periksaPembuatan()` | A, E |
| `app/Services/NotifikasiService.php` | Ubah teks pesan cabang `menunggu_konfirmasi` pada `bastBerubah()` | A |
| `app/Filament/Resources/BastMutasiAsets/Pages/CreateBastMutasiAset.php` | Dihapus; logikanya (`mutateFormDataBeforeCreate`, `handleRecordCreation`, `afterCreate`) dipindah ke `CreateAction` di `ListBastMutasiAsets` | B |
| `app/Filament/Resources/BastMutasiAsets/BastMutasiAsetResource.php` | Hapus entri `'create'` dari `getPages()` | B |
| `app/Filament/Resources/BastMutasiAsets/Pages/ListBastMutasiAsets.php` | `CreateAction` dilengkapi hook `mutateFormDataUsing/using/after`; tambah aksi `detail` (dialog rincian) yang membungkus `sahkan`/`konfirmasi`/`unduh` | B, C |
| `app/Filament/Resources/BastMutasiAsets/Schemas/BastMutasiAsetForm.php` | Hapus `TextInput` `pihak_penyerah`/`pihak_penerima`; tambah UI penjaga tanda tangan (E) | D, E |
| `app/Filament/Resources/BastMutasiAsets/Tables/BastMutasiAsetsTable.php` | Perbaiki teks `modalDescription()` aksi `sahkan`; tambah `schema()` ketik-NIP pada aksi `sahkan`/`konfirmasi`; `->visible()` TIDAK berubah | A, C |
| Partial baru (mis. `resources/views/filament/partials/detail-bast.blade.php`) | Isi dialog rincian BAST, mengikuti pola `detail-permintaan.blade.php` | C |
| `app/Filament/Resources/AsetTetaps/Schemas/AsetTetapForm.php` | `tim_penempatan_id` → `required()`/`placeholder()` bersyarat `$operation === 'create'` | F |
| `app/Services/Impor/ImporAsetTetap.php` | (usulan, perlu konfirmasi) `Kolom::buat('tim_penempatan', wajib: true)` + penolakan baris tanpa tim pada aset baru | F (usulan) |
| `database/seeders/BastMutasiAsetSeeder.php` | Ganti panggilan `sahkan()` → `konfirmasi()` agar tetap valid di status awal baru | A (temuan G.8) |
| `resources/views/pdf/bast-mutasi.blade.php` | **Tidak berubah** (dibuktikan A.6) | — |
| `app/Services/DokumenBastService.php` | **Tidak berubah** (dibuktikan A.6) | — |
| Migration `bast_mutasi_aset` | **Tidak berubah** (tanpa migration baru per aturan proyek) | D |

Kelas Support baru opsional (bila pilihan G.5 = ekstraksi): `App\Support\KonfirmasiIdentitas`
atau serupa, dipakai bersama `PermintaanBarangResource` dan `BastMutasiAsetResource`.

---

## Test yang Terikat Urutan Lama

Sembilan berkas dibaca menyeluruh. Kategori: **(a)** tetap berlaku, **(b)** perlu
ditulis ulang (ringan → hanya fixture/teks, atau penuh → jalur bisnis inti), **(c)**
tidak relevan lagi (isinya berpindah ke method lain).

| Berkas | Total | (a) Tetap | (b) Tulis ulang | (c) Tidak relevan | Penyebab utama |
|---|---:|---:|---:|---:|---|
| `MutasiAsetPenempatanTest.php` | 18 | 1 | 16 | 1 | A (status/movement) + B (Create page→modal) + D (field `pihak_*` di `fillForm`) menumpuk di sini |
| `StatusBastDanAsetAktifTest.php` | 9 | 4 | 5 | 0 | Guard *input* status tak berubah (banyak tetap valid); assertion *output*/movement yang berubah |
| `PenomoranBastTest.php` | 6 | 3 | 3 | 0 | 3 test lewat `buatLewatForm()` kena dampak B+D; 3 test murni `nomorBaru()`/fixture langsung tetap valid |
| `BastTandaTanganTersimpanTest.php` | 3 | 2 | 1 | 0 | Hanya test pipeline (`sahkan()` lalu `konfirmasi()` berurutan) yang perlu dibalik |
| `DokumenBastTest.php` | 7 | 1 | 6 | 0 | Semua "b" **ringan**: fixture `bastDisahkan()` perlu status→`selesai_administratif`, assersi PDF sendiri tak berubah |
| `NotifikasiMutasiAsetTest.php` | 6 | 4 | 2 | 0 | 2 test ("bast_baru", "bast_disahkan") tertukar makna nama vs status; assersi penerima-per-status sendiri tak berubah |
| `RiwayatPenempatanAsetTest.php` | 20 | 15 | 5 | 0 | 4 test kena dampak **F** (buat-tanpa-tim lewat form); 1 test kena dampak **A** (riwayat mutasi dari `sahkan()` → `konfirmasi()`) |
| `BastMutasiAsetAksesTest.php` | 3 | 3 | 0 | 0 | Akses/visibilitas resource, tidak tersentuh sama sekali |
| `VerifikasiNotifikasiMutasiAsetTest.php` | 10 | 8 | 2 | 0 | 8 test murni Permintaan Barang (NS-12..18), tidak relevan; 2 test MA-5/MA-9 kena dampak B+D (`Livewire::test(CreateBastMutasiAset::class)`) |
| **Jumlah** | **82** | **41** | **40** | **1** | |

**Catatan penting soal bobot:** ke-40 "(b)" **tidak seragam beratnya**. Enam di
`DokumenBastTest.php` hanya perlu satu baris fixture diubah. Lima di
`StatusBastDanAsetAktifTest.php` sebagian besar hanya perlu assersi status
keluaran ditukar. Sebaliknya, sebagian besar dari 16 di `MutasiAsetPenempatanTest.php`
adalah jalur bisnis inti (A-011/MA-6 s.d. MA-18) yang harus ditulis ulang menyeluruh
karena tiga keputusan (A/B/D) menumpuk pada berkas yang sama. **Perkiraan realistis:
sekitar 15–18 dari 40 adalah penulisan ulang substansial; sisanya penyesuaian ringan.**

---

## Ringkasan untuk Fase 2

- **Perkiraan skala:** ~13 berkas kode berubah (lihat tabel di atas; 3 di antaranya
  "tidak berubah" dicantumkan untuk kejelasan), 1 berkas dihapus
  (`CreateBastMutasiAset.php`), 1 partial Blade baru, 1 seeder diperbaiki, ~40 dari 82
  test BAST ditulis ulang (perkiraan 15–18 substansial, sisanya ringan), 1 test menjadi
  tidak relevan.
- **Urutan pengerjaan yang disarankan:** **F lebih dulu** sebagai batch kecil dan
  independen (1 form + pertimbangan template impor + 4 test), **baru A–E** sebagai
  batch besar (saling terkait erat: A mengubah method service yang jadi dasar C's
  action logic, B mengubah tempat form itu dipasang, D mengubah isi form yang sama,
  E menambah UI pada form yang sama) — mengerjakannya sekaligus masuk akal karena
  keempatnya menyentuh berkas yang sama (`BastMutasiAsetForm.php`,
  `BastMutasiAsetsTable.php`, `MutasiAsetService.php`), tetapi sebaiknya dipecah jadi
  beberapa batch commit mengikuti pola audit sebelumnya (per kelompok test/per berkas),
  bukan satu commit raksasa.
- **Pertanyaan terbuka (G) harus dijawab dulu** sebelum Fase 2 dimulai, khususnya
  G.3 (nilai pengganti `pihak_penyerah`/`pihak_penerima`), G.5 (duplikasi vs ekstraksi
  pola ketik-NIP), dan G.7 (apakah kolom impor ikut diwajibkan).
