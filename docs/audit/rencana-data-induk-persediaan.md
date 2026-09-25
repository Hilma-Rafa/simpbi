# Rencana: Data Induk Persediaan (Kategori Barang & Barang Persediaan) + Penyeragaman UI

> **Status: DIEKSEKUSI 25 September 2026** (bagian A–E dengan keputusan pemilik G-1 s.d. G-11, ditambah
> bagian F.0/F.1 — pergantian tahun dan Impor Stok Awal). Ringkasan pelaksanaan ada di bagian
> **H. Pelaksanaan** di akhir dokumen. Isi bagian A–G di bawah dibiarkan sebagai rencana aslinya.
>
> *Status semula: INVESTIGASI DAN RENCANA — belum ada kode yang diubah.*
> Disusun 25 September 2026 di atas `main` @ `8449a47` ditambah perubahan kerja yang belum di-commit
> (perbaikan T-1 `EditUser::beforeSave` dan T-2 keunikan kode barang di `BarangPersediaanForm`,
> `CreateBarangPersediaan`, `EditBarangPersediaan`). Rencana ini dieksekusi **setelah** T-1/T-2 selesai,
> lalu pengujian white-box dan regression diulang.
>
> Aturan yang dipegang selama investigasi: git hanya-baca, tanpa commit; basis data kerja
> (`database/database.sqlite`) hanya dibaca lewat PDO `SQLITE_OPEN_READONLY`, tanpa bootstrap Laravel;
> tata letak PDF dan ekspor Excel yang sudah ada **beku** — tidak ada usulan di bawah yang menyentuh
> `KartuKendaliService::pdf()/spreadsheet()/isiLembar()`, `resources/views/pdf/*`, atau
> `EksporRiwayatService`.
>
> **Skill.** Seluruh skill di `.claude/skills` sudah diperiksa (animate, impeccable, design-taste-*,
> minimalist-ui, redesign-existing-projects, dan lain-lain). Semuanya skill desain visual/animasi;
> tidak ada yang mencakup Laravel/Filament, validasi, atau impor data. Untuk bagian D dan E (UI),
> acuan yang berlaku adalah `.claude/Instruksi.md` dan pola yang sudah ada di kode (`AksiUbah`,
> `aksiUnduhBukti`) — skill desain tidak menggantikan brief itu. Jadi **tidak ada skill yang dipakai
> secara substantif**; semua temuan berasal dari pembacaan kode langsung.

---

## A. Kode Akun pada Kategori Barang

### A.1 Letak kolom

| Lapisan | Berkas:baris | Isi |
|---|---|---|
| Migration | `database/migrations/2026_08_26_000003_create_kategori_table.php:13-15` | `$table->string('kode_akun', 10);` — wajib (NOT NULL), tidak unik, tanpa indeks. Komentar: *"Mengikuti struktur laporan persediaan BPS — kode_akun contoh: 117111 (Barang Konsumsi)"*. |
| Model | `app/Models/Kategori.php:13` | `$guarded = []`; tidak ada cast, accessor, atau aturan untuk `kode_akun`. |
| Form | `app/Filament/Resources/Kategoris/Schemas/KategoriForm.php:24-28` | `TextInput::make('kode_akun')->label('Kode Akun')->helperText('Kode akun neraca sesuai bagan akun.')->required()->maxLength(10)` |
| Tabel | `app/Filament/Resources/Kategoris/Tables/KategorisTable.php:44-49` | Kolom badge abu-abu, `searchable()`, `toggleable()`. |
| Seeder | `database/seeders/KatalogBarangSeeder.php:25-39` (15× `117111`), `database/seeders/AsetTetapSeeder.php:24` (`1.3.2`) | Sumber data kerja. |

### A.2 Semua pemakaian `kode_akun`

Penelusuran `kode_akun|kodeAkun|kode akun` (tanpa membedakan huruf besar-kecil) di `app/`, `resources/`, `database/`, `routes/`, `config/`, `tests/`, dan `docs/`:

| Tempat | Dipakai? | Bukti |
|---|---|---|
| Dokumen PDF (bukti permintaan, BAST, kartu kendali) | **Tidak** | Tidak ada kecocokan di `resources/views/pdf/*`, `DokumenPermintaanService`, `DokumenBastService`, `KartuKendaliService`. |
| Ekspor Excel (kartu kendali, riwayat) | **Tidak** | Tidak ada kecocokan di `KartuKendaliService::spreadsheet/isiLembar`, `EksporRiwayatService`. |
| Kartu kendali acuan (`docs/Kartu-Kendali-Persediaan-2025.xlsx`) | **Tidak** | `sharedStrings.xml` hanya memuat "Kode Barang" + kode lengkap `1.01.03.01.001.000122`; tidak ada "akun" atau `117111`. |
| Kode lengkap barang (`kode_lengkap`) | **Tidak** — yang dipakai `kode_kategori` | `app/Models/BarangPersediaan.php:54-78`. |
| Laporan / widget / pengelompokan | **Tidak** | Widget (`KondisiStok`, `TrenKonsumsiKategori`, dll.) mengelompokkan per `kategori_id`/`nama_kategori`, bukan `kode_akun`. |
| Validasi | Hanya `required` + `maxLength(10)` | `KategoriForm.php:27-28`. |
| Tampilan | Form, kolom tabel, pencarian | `KategoriForm.php:24`, `KategorisTable.php:44-49`. |
| Tes | Hanya sebagai isian data (`117111`, `117112`, `1.3.2`, `9.9.9`, `1170`…`117n`) | mis. `tests/Feature/Concerns/MenyiapkanDataUji.php:78,101`, `tests/Feature/PerlindunganHapusTest.php:375,394`, `tests/Feature/KondisiStokTautanTest.php:37`. Tidak ada tes yang menguji arti atau format kode akun. |

### A.3 Nilai di basis data kerja (baca-saja)

| id | kode_akun | kode_kategori | nama_kategori | tipe |
|---|---|---|---|---|
| 1–15 | `117111` | 10 digit (`1010301001` … `1010399999`) | Alat Tulis … Alat/Bahan Untuk Kegiatan Kantor Lainnya | persediaan |
| 17 | `1.3.2` | `PM` | Peralatan dan Mesin | aset_tetap |

Pola: **16 kategori, hanya dua nilai kode akun.** Seluruh kategori persediaan memakai `117111` (6 digit angka,
tanpa titik); satu-satunya kategori aset tetap memakai `1.3.2` (bertitik, 5 aksara). Jadi formatnya **tidak seragam
antar-tipe**.

### A.4 Kesimpulan fungsi

**Berdasarkan bukti kode:** `kode_akun` adalah **atribut keterangan murni**. Ia disimpan, ditampilkan, dan dapat
dicari, tetapi tidak dipakai perhitungan, pengelompokan, validasi silang, dokumen, maupun ekspor mana pun. Mengubah
nilainya tidak berdampak apa pun selain pada tampilan tabel Kategori Barang.

**Dugaan yang perlu dikonfirmasi pemilik (tidak disebut oleh kode):**
- `117111` kemungkinan adalah kode akun neraca **Barang Konsumsi** pada Bagan Akun Standar (BAS) pemerintah pusat
  yang dipakai SAKTI (kelompok 117 = Persediaan). Satu-satunya bukti di kode adalah komentar migration
  "*117111 (Barang Konsumsi)*" dan helperText "*Kode akun neraca sesuai bagan akun*"; standar BAS/SAKTI tidak
  disebut di mana pun.
- `1.3.2` kemungkinan adalah **golongan Peralatan dan Mesin** pada aset tetap, ditulis dalam notasi bertitik
  (padanan BAS enam digitnya berawalan 132…). Ini dugaan; ada kemungkinan penulisannya sekadar ringkas.

### A.5 Usulan teks bantuan dan validasi

**Teks bantuan (tidak mengubah perilaku, aman diterapkan kapan pun):**

- `placeholder`: `117111`
- `helperText` (diusulkan): *"Kode akun neraca (Bagan Akun Standar) tempat kategori ini dilaporkan, mis. 117111
  untuk Barang Konsumsi. Hanya keterangan: kode ini tidak memengaruhi stok, kartu kendali, maupun dokumen."*
  — frasa "Barang Konsumsi"/"Bagan Akun Standar" dipakai **hanya bila pemilik mengonfirmasi** A.4; bila tidak,
  pakai versi netral: *"Kode akun neraca sesuai bagan akun, mis. 117111. Hanya keterangan: …"*.

**Validasi format — hanya bila pemilik memastikan formatnya tetap (Pertanyaan G-1, G-2):**

| Opsi | Aturan | Dampak pada data kerja |
|---|---|---|
| A-1 (disarankan bila dikonfirmasi) | Bergantung tipe: `persediaan` → `/^\d{6}$/`; `aset_tetap` → dibiarkan bebas (`maxLength(10)` seperti sekarang) | 16/16 lolos |
| A-2 | Enam digit untuk semua tipe | `1.3.2` (id 17) **gagal** saat kategori itu diubah — harus diganti manual lebih dulu |
| A-3 | Tanpa validasi format, hanya teks bantuan | Tidak ada |

Opsi A-1 memerlukan closure `rule(fn (Get $get) => …)` yang membaca `tipe` → lahir **satu percabangan baru** di
`KategoriForm::configure` (lihat F).

### A.6 Temuan sampingan (di luar permintaan, relevan dengan T-2)

1. **`kode_kategori` unik di basis data tetapi tidak divalidasi di form.** Indeks unik ada di
   `2026_08_26_000003_create_kategori_table.php:17`, sedangkan `KategoriForm.php:20-23` tidak memakai `->unique()`
   dan `CreateKategori`/`EditKategori` tidak punya jaring `UniqueConstraintViolationException`. Kode kategori ganda
   akan berujung galat basis data mentah — **kelas cacat yang sama dengan T-2.** Usul: tambahkan
   `->unique(ignoreRecord: true)` + pesan, dan jaring pengaman seperti `CreateBarangPersediaan::handleRecordCreation`.
2. **Pilihan Kategori di form Barang Persediaan tidak disaring per tipe.** `BarangPersediaanForm.php:22-27`
   memakai `relationship('kategori', 'nama_kategori')` tanpa `modifyQueryUsing`, sehingga kategori aset tetap
   "Peralatan dan Mesin" dapat dipilih untuk barang persediaan. Dialog Barang Baru di Stok Masuk sudah menyaringnya
   (`StokMasuk.php:444`). Usul: samakan (`->where('tipe', 'persediaan')`).

---

## B. Kode Barang 6 Digit

Ketetapan pemilik: kode barang **selalu 6 digit angka** dan berasal dari **kartu kendali persediaan**. Bukti yang
selaras di kode: `BarangPersediaan::getKodeLengkapAttribute()` (`app/Models/BarangPersediaan.php:44-78`) menyusun
`1.01.03.01.001.000122` dengan kode barang sebagai segmen terakhir, dan kartu kendali acuan
(`docs/Kartu-Kendali-Persediaan-2025.xlsx`) memuat segmen terakhir 6 digit (`000122`, `000123`, `000289`, …).

### B.1 Validasi `kode_barang` saat ini di semua titik masuk

| Titik masuk | Berkas:baris | Validasi sekarang | 6 digit? |
|---|---|---|---|
| Form Barang Persediaan — Buat & Ubah (skema yang sama) | `BarangPersediaanForm.php:28-44` | `required`, `maxLength(30)`, `trim()`, `unique(ignoreRecord, where kategori_id)` + pesan (T-2, belum di-commit) | **Tidak** |
| Jaring pengaman server — Buat | `CreateBarangPersediaan.php:25-37` | Tangkap `UniqueConstraintViolationException` → notifikasi + `Halt` | — |
| Jaring pengaman server — Ubah | `EditBarangPersediaan.php:103-115` | Idem | — |
| Dialog Barang Baru di Stok Masuk | `StokMasuk.php:451-463` (borang), `:389-394` (`kodeBarangSudahDipakai`), `:401-422` (`simpanBarangBaru`) | `required`, `maxLength(30)`, `dehydrateStateUsing(trim)`, closure rule keunikan per kategori; helperText "*Kode hanya perlu unik di dalam kategorinya, mengikuti penomoran Sub-Bagian Umum.*" | **Tidak** |
| Impor | — | **Belum ada** impor Barang Persediaan | — |
| Basis data | `2026_08_26_000004_create_barang_persediaan_table.php:16,26` | `string(30)`, unik `(kategori_id, kode_barang)` | — |

Catatan teknis penting untuk B.3: di dialog Stok Masuk pemangkasan dilakukan dengan `dehydrateStateUsing`, yang
berjalan **sesudah** validasi. Aturan 6 digit yang ditambahkan di sana akan menolak `" 000100 "` padahal setelah
dipangkas sah. Form Barang Persediaan tidak kena masalah ini karena memakai `->trim()` (Filament
`CanTrimState`, dijalankan pada state untuk validasi).

### B.2 Data kerja yang bukan tepat 6 digit (baca-saja)

```
total barang: 119
panjang 6: 119
bukan /^\d{6}$/: 0
```

**Tidak ada satu pun barang yang melanggar.** Aturan baru aman diterapkan pada data kerja. Karena basis data
produksi bisa berbeda, sebelum deploy jalankan pemeriksaan yang sama di produksi (baca-saja):

```sql
-- MySQL
SELECT id, kategori_id, kode_barang, nama_barang FROM barang_persediaan
WHERE kode_barang NOT REGEXP '^[0-9]{6}$';
-- SQLite
SELECT id, kategori_id, kode_barang, nama_barang FROM barang_persediaan
WHERE kode_barang NOT GLOB '[0-9][0-9][0-9][0-9][0-9][0-9]';
```

### B.3 Usulan aturan dan bentuk input

**Satu sumber aturan.** Konstanta di model agar ketiga titik masuk (form, dialog, impor) tidak menyimpang:

```php
// app/Models/BarangPersediaan.php
public const POLA_KODE  = '/^\d{6}$/';
public const PESAN_KODE = 'Kode barang harus tepat 6 digit angka, sesuai kartu kendali persediaan (mis. 000122).';
```

**Aturan validasi:** `->regex(BarangPersediaan::POLA_KODE)` + `->validationMessages(['regex' => PESAN_KODE])`.
Nilai tetap string, sehingga nol di depan terjaga (kolomnya sudah `string`). **Jangan** memakai `->numeric()` —
itu mengubah jenis input dan membuka jalan hilangnya nol di depan.

**Bentuk input (mencegah salah ketik):**

| Pengaturan | Nilai | Guna |
|---|---|---|
| `->length(6)` | min & max 6 | Menggantikan `maxLength(30)`; peramban menolak ketikan ke-7. |
| `->inputMode('numeric')` | papan angka di ponsel | Tanpa mengubah jenis input menjadi `number`. |
| `->mask('999999')` | hanya angka | Masker Alpine Filament; huruf tidak dapat diketik. |
| `->placeholder('000122')` | contoh | |
| `->trim()` | pangkas spasi | Di dialog Stok Masuk **menggantikan** `dehydrateStateUsing(trim)` agar validasi melihat nilai terpangkas (lihat B.1). |
| `->helperText(...)` | lihat di bawah | |

**Teks bantuan (disatukan untuk semua titik masuk):**
*"6 digit angka sesuai kartu kendali persediaan, termasuk nol di depan (mis. 000122). Kode cukup unik di dalam
kategorinya."*

**Keunikan per kategori (T-2) tetap berlaku berdampingan:**
- Form Barang Persediaan: `unique(ignoreRecord, where kategori_id)` yang sudah ada.
- Dialog Stok Masuk: closure `kodeBarangSudahDipakai` yang sudah ada (unit white-box 1.10, tidak berubah logikanya).
- Impor: pemeriksaan yang sama (lihat C.3).

Urutan pesan: format diperiksa lebih dulu daripada keunikan, sehingga pengguna yang mengetik `12345` melihat
"harus 6 digit", bukan "sudah dipakai".

### B.4 Penanganan data lama

Data kerja 0 pelanggaran, jadi yang disarankan adalah **aturan berlaku universal** (Buat, Ubah, Stok Masuk, impor)
tanpa migration dan tanpa mengubah data apa pun.

Bila pemeriksaan produksi (B.2) menemukan pelanggaran, alternatifnya **tanpa migration**:
- Aturan hanya berlaku pada **data baru** dan pada **Ubah bila kode diubah**: di `BarangPersediaanForm`,
  `->regex(...)` dipasang lewat `->rule(fn (?Model $record, $state) => $record && $record->kode_barang === $state ? null : 'regex:…')`.
  Barang lama dengan kode tak baku tetap dapat diubah nama/satuannya tanpa dipaksa mengganti kode.
- Daftar barang pelanggar diserahkan ke pemilik untuk diperbaiki manual lewat form (per barang), bukan otomatis.
- Konsekuensi: pengecualian ini menambah **satu percabangan** di `BarangPersediaanForm::configure` (calon unit
  white-box). Karena itu opsi ini hanya dipakai bila memang ada data pelanggar (Pertanyaan G-3).

---

## C. Impor, Sinkronisasi, dan Unduh Template

### C.1 Pola yang sudah ada

| Aspek | Tim Kerja | Pengguna | Aset Tetap |
|---|---|---|---|
| Kelas impor | `app/Services/Impor/ImporTimKerja.php` | `app/Services/Impor/ImporPengguna.php` | `app/Services/Impor/ImporAsetTetap.php` |
| Pemasangan tombol | `TimsTable.php:85-92` (`headerActions`) | `UsersTable.php:92-99` | `AsetTetapsTable.php:125-132` |
| Nama template | `Template-Impor-Tim-Kerja.xlsx` | `Template-Impor-Pengguna.xlsx` | `Template-Sinkronisasi-Aset-Tetap.xlsx` |
| Kunci pencocokan | `nama_tim` tanpa beda huruf (`PencocokanNama::samakan`) | `username` | `nup` |
| Operasi | Upsert, **tidak pernah menghapus** | Upsert, tidak menghapus | Upsert, tidak menghapus |
| Provenans | `synced_at` selalu; `external_id` bila diisi (`:127-131`) | tidak ada | `sumber_data='impor'`, `synced_at`, `external_id` bila diisi (`:306-321`) |
| Kolom yang dilindungi saat pembaruan | ketua tim | peran/status akun pengimpor, Admin aktif terakhir (G-002) | `tim_penempatan_id`, `status_aktif`, `nup` (`atributSumber`, `:277-324`) |
| Strip "terakhir disinkronkan" | `StatusSinkronisasiTim` (`MAX(synced_at)`) | tidak ada | `StatusSinkronisasiAsetTetap` |
| Hak akses | Admin, Kasubbag (`TimResource::canAccess`) | Admin (`UserResource::canAccess`) | Admin, Kasubbag |

**Komponen bersama** (semuanya dipakai ulang, tidak ada yang perlu dibuat baru):
- `App\Filament\Support\AksiImpor::buat(judul, kolom, namaTemplate, impor)` — satu tombol **Impor** (abu-abu,
  bergaris) membuka dialog: unggah berkas (`xlsx`/`csv`, `storeFiles(false)`), tombol **Unduh Template** di kaki
  dialog, dan pemberitahuan hasil (`beritahukan`, menetap bila ada galat/catatan, maksimal 5 baris galat).
- `Kolom` — satu definisi untuk tajuk template, lembar Petunjuk, dan pembacaan.
- `PembuatTemplate` — lembar **Data** (tajuk bertanda `*` untuk wajib + satu baris contoh miring abu-abu) dan
  lembar **Petunjuk** (Kolom · Wajib · Keterangan).
- `PembacaBerkas` — cocokkan kolom berdasarkan tajuk, lewati baris kosong, hanya lembar pertama.
- `HasilImpor` — pencacah `ditambah/diperbarui/dilewati`, `galat` per nomor baris, `catatan` untuk kolom yang
  sengaja diabaikan.
- `PencocokanNama` — pembanding nama tanpa beda huruf/spasi.

**"Sinkronisasi" bukan aksi terpisah.** Di ketiga halaman, sinkronisasi = aksi Impor yang sama (upsert satu arah
berbasis berkas, per baris dalam transaksinya sendiri, baris sah tetap masuk walau baris lain gagal). Label
"Sinkronisasi" hanya muncul pada nama template Aset Tetap dan strip status.

### C.2 Rancangan padanan

Mengikuti pola **Pengguna** untuk provenans: tabel `kategori` dan `barang_persediaan` **tidak punya**
`synced_at`/`external_id`/`sumber_data`, dan Instruksi melarang menambah struktur hanya demi UI. Karena itu:
**tanpa strip status sinkronisasi dan tanpa kolom provenans** (Pertanyaan G-7 bila pemilik menginginkannya).

#### Kategori Barang — `ImporKategori`

Nama template: `Template-Impor-Kategori-Barang.xlsx`. Dipasang di `KategorisTable::configure` → `headerActions`.

| Kolom (tajuk) | Wajib | Contoh | Keterangan pada lembar Petunjuk |
|---|---|---|---|
| Kode Kategori | Ya | `1010301001` | Kunci pencocokan. Kode yang sudah ada diperbarui, kode baru ditambahkan. Kode tidak pernah diubah dari impor. Kategori persediaan: 10 digit angka. |
| Nama Kategori | Ya | `Alat Tulis` | Maks. 100 aksara. |
| Kode Akun | Ya | `117111` | Kode akun neraca, lihat bagian A. Format mengikuti keputusan G-1/G-2. |
| Tipe | Ya | `Persediaan` | `Persediaan` atau `Aset Tetap`. Hanya dipakai untuk kategori baru; kategori yang sudah dipakai barang/aset tidak dapat berganti tipe dari sini. |

- **Kunci pencocokan: `kode_kategori`** (sudah unik di basis data, stabil, dan menjadi bagian kode lengkap kartu
  kendali). Nama **bukan** kunci: nama boleh dibetulkan ejaannya lewat impor ulang.
- **Diperbarui saat impor ulang:** `nama_kategori`, `kode_akun`. `tipe` hanya diterima bila sama dengan yang
  tersimpan, atau bila kategori belum dipakai (`Kategori::punyaRiwayat()` = false).
- **Tidak menghapus** kategori yang tidak ada di berkas.

#### Barang Persediaan — `ImporBarangPersediaan`

Nama template: `Template-Impor-Barang-Persediaan.xlsx`. Dipasang di `BarangPersediaansTable::configure` →
`headerActions`.

| Kolom (tajuk) | Wajib | Contoh (disimpan sebagai **teks**) | Keterangan |
|---|---|---|---|
| Kode Kategori | Ya | `1010301001` | Harus sudah terdaftar sebagai kategori **persediaan**. Kategori tidak dibuat otomatis. |
| Kode Barang | Ya | `000122` | Tepat 6 digit angka sesuai kartu kendali, termasuk nol di depan. Format sel sebagai **Teks** (atau awali dengan tanda petik `'`), sebab Excel membuang nol di depan pada sel angka. |
| Nama Barang | Ya | `BINDER CLIPS NO. 105` | Maks. 150 aksara. |
| Satuan | Ya | `Dus` | Maks. 20 aksara. |
| Stok Minimum | Tidak | `0` | Bilangan bulat ≥ 0. Kosong: barang baru 0, barang lama tidak diubah. 0 = tidak dipantau. |

Tidak ada kolom stok (lihat bagian Stok di bawah). Kolom **Aktif** sengaja tidak disertakan (lihat G-6).

- **Kunci pencocokan: (kategori, `kode_barang`)** — persis indeks unik `(kategori_id, kode_barang)`.
  Kategori dirujuk dengan **Kode Kategori**, bukan nama, karena itulah yang tercetak di kartu kendali
  (`1.01.03.01.001`) dan tidak ambigu (Pertanyaan G-4).
- **Diperbarui saat impor ulang:** `nama_barang`, `satuan`, dan `stok_minimum` bila diisi.
- **Tidak pernah disentuh:** `stok_fisik`, `stok_hold`, `status_aktif`, `kategori_id` dan `kode_barang`
  (keduanya kunci — mengubahnya berarti menunjuk barang lain), dan tabel `mutasi_stok`.
- **Tidak menghapus** barang yang tidak ada di berkas.

### C.3 Aturan validasi per baris

**Kategori (`ImporKategori::jalankan`)** — setiap pelanggaran → `catatGalat(nomor, pesan)` lalu `continue`:
1. Kode Kategori kosong / > 20 aksara.
2. Nama Kategori kosong / > 100 aksara.
3. Kode Akun kosong / > 10 aksara / tidak sesuai format (bila G-1 memutuskan ada format).
4. Tipe tidak dikenali (`persediaan`, `aset tetap`, `aset_tetap` diterima tanpa beda huruf).
5. Tipe persediaan dengan kode kategori bukan 10 digit (bila pemilik mengonfirmasi; data kerja 15/15 sudah 10 digit).
6. Kategori lama yang masih dipakai tetapi tipenya berbeda → ditolak dengan alasan.
7. Jaring pengaman `UniqueConstraintViolationException` pada `create` (pola `ImporAsetTetap.php:239-251`).

**Barang (`ImporBarangPersediaan::jalankan`)**:
1. Kode Kategori kosong.
2. Kategori tidak ditemukan **atau bertipe aset tetap** → *"Kategori 1010301001 belum terdaftar sebagai kategori
   persediaan. Tambahkan lewat menu Kategori Barang."*
3. Kode Barang kosong.
4. Kode Barang tidak cocok `BarangPersediaan::POLA_KODE`. Bila isinya angka murni kurang dari 6 digit, pesannya
   khusus: *"Kode Barang 122 tampaknya kehilangan nol di depan. Format selnya sebagai Teks lalu tulis 000122."*
   Ini perlu karena `PembacaBerkas::teks()` (`PembacaBerkas.php:97-103`) mengubah sel angka `000122` menjadi `"122"`.
   Sistem **tidak** menambal nol otomatis (menebak itu berbahaya — pola yang sama dengan `bacaYaTidak`).
5. Nama Barang kosong / > 150; Satuan kosong / > 20.
6. Stok Minimum bukan bilangan bulat ≥ 0.
7. **Kode ganda di dalam berkas yang sama** (kategori + kode sama pada dua baris) → baris kedua ditolak, supaya
   baris pertama tidak diam-diam tertimpa baris kedua.
8. Keunikan per kategori terhadap basis data ditangani sebagai **pembaruan** (upsert), bukan galat; jaring
   `UniqueConstraintViolationException` pada `create` untuk unggahan bersamaan.
9. Berkas yang membawa tajuk **Stok Fisik / Stok / Stok Terkunci** → kolomnya diabaikan dan dilaporkan lewat
   `HasilImpor::catat()`: *"Kolom Stok Fisik diabaikan. Stok hanya berubah lewat Stok Masuk dan alur permintaan
   agar saldo kartu kendali tetap benar."* (pola yang sama dengan catatan Tim Kerja Penempatan di
   `ImporAsetTetap.php:266-272`).

### C.4 STOK — perlakuan barang hasil impor

**Usulan: barang baru hasil impor lahir dengan `stok_fisik = 0`, `stok_hold = 0`, `status_aktif = true`**,
persis seperti `StokMasuk::simpanBarangBaru()` (`StokMasuk.php:410-412`). Stok awalnya dicatat Petugas Gudang lewat
**Stok Masuk** dengan sumber **Stok Awal** (`StokMasuk::SUMBER_MASUK`, dibaca `KartuKendaliService::bawaanTahunLalu`,
`:167-171`), sehingga setiap angka stok punya baris kartu kendali.

Barang yang sudah ada: impor **tidak menulis** ke `stok_fisik`/`stok_hold` sama sekali (kedua kolom tidak ada di
array atribut yang ditulis), dan tidak menyentuh `mutasi_stok`.

**Risiko bila memilih jalan lain:**

| Alternatif | Risiko |
|---|---|
| Impor menulis `stok_fisik` langsung | Stok tanpa baris mutasi. `StokService::saldoAwalTersirat()` (`StokService.php:301-304`) akan membaca selisihnya sebagai saldo pembawaan, sehingga kolom **Sisa** di kartu kendali bergeser tanpa satu pun transaksi yang menjelaskannya — efek T-57 (`docs/penyesuaian-stok.md`), tetapi massal dan tanpa dialog konfirmasi. Barang lama dengan HOLD berjalan dapat berakhir `stok_fisik < stok_hold` (stok tersedia negatif). |
| Impor menerbitkan mutasi `stok_awal` lewat `StokService::tambah()` | Kartu kendali benar, tetapi impor data induk berubah menjadi **transaksi stok** yang dilakukan Admin/Kasubbag, padahal Stok Masuk hanya milik Petugas Gudang (`StokMasuk::canAccess`, `:61-64`). Ini **perubahan alur kerja dan hak akses** — Instruksi mensyaratkan persetujuan eksplisit. Juga butuh tanggal, nomor dasar, dan petugas per baris. |

Ditandai sebagai **Pertanyaan G-5**.

Catatan terkait (bukan bagian impor): form **Buat** Barang Persediaan sekarang masih menerima `stok_fisik` > 0
(`BarangPersediaanForm.php:59-64`; diuji `StokTerkunciBacaSajaTest.php:91-110` dengan stok 12). Itu jalur yang
sama dengan alternatif pertama di atas. Tidak diusulkan diubah di sini karena T-57 sudah diputuskan, tetapi pemilik
mungkin ingin menyelaraskannya dengan keputusan G-5.

### C.5 Hak akses

Sesuai `docs/audit/matriks-akses.md` (baris 118-119) dan `canAccess()` kedua resource: **Admin dan Kasubbag**
(`KategoriResource.php:48-51`, `BarangPersediaanResource.php:48-51`). Karena tombol dipasang di tabel resource,
aksesnya otomatis mengikuti `canAccess()` — sama dengan Tim Kerja dan Aset Tetap. Petugas Gudang tidak mendapat
impor (tetap bisa membuat satu barang lewat dialog Stok Masuk, seperti sekarang). Matriks akses perlu satu kolom
"Impor Excel + Template (Adm, Kas)" pada kedua baris itu.

### C.6 Perkiraan berkas

| Berkas | Baru/Ubah | Isi |
|---|---|---|
| `app/Services/Impor/ImporKategori.php` | Baru | `JUDUL`, `kolom()`, `jalankan()`, `bacaTipe()` |
| `app/Services/Impor/ImporBarangPersediaan.php` | Baru | `JUDUL`, `kolom()`, `jalankan()`, `bacaStokMinimum()`, `atributSumber()` |
| `app/Filament/Resources/Kategoris/Tables/KategorisTable.php` | Ubah | `headerActions([AksiImpor::buat(...)])` |
| `app/Filament/Resources/BarangPersediaans/Tables/BarangPersediaansTable.php` | Ubah | idem |
| `app/Models/BarangPersediaan.php` | Ubah | konstanta `POLA_KODE`, `PESAN_KODE` (bagian B) |
| `app/Services/Impor/PembuatTemplate.php` | **Tidak diubah** | Contoh `000122` ditulis sebagai string dan tampil apa adanya |
| `tests/Feature/ImporKategoriTest.php` | Baru | ± 12 kasus |
| `tests/Feature/ImporBarangPersediaanTest.php` | Baru | ± 18 kasus, termasuk invarian stok |
| `docs/audit/matriks-akses.md`, `docs/sinkronisasi-data.md` | Ubah | dokumentasi |

Perkiraan tes baru bagian C: **± 32** (12 + 18 + 2 tes akses/tombol).

---

## D. Penyeragaman Tombol Unduh

### D.1 Semua tombol unduh/ekspor

Acuan **Unduh Bukti** — `PermintaanBarangResource::aksiUnduhBukti()` (`app/Filament/Resources/PermintaanBarangs/PermintaanBarangResource.php:298-311`):
`icon('heroicon-m-arrow-down-tray')` · `iconPosition(Before)` · `color('primary')` · `button()` · `outlined()` + tooltip.

| # | Tombol | Berkas:baris | Gaya sekarang | Sama dengan Unduh Bukti? |
|---|---|---|---|---|
| 1 | Unduh Bukti | `PermintaanBarangResource.php:298-311` | acuan | — |
| 2 | Unduh BAST | `BastMutasiAsetsTable.php:212-222` | primary · button · outlined · ikon di depan | **Sama nilainya, tetapi disalin manual** (komentar `:208-211` mengakuinya) |
| 3 | **Unduh Template** (kaki dialog Impor; dipakai Tim Kerja, Pengguna, Aset Tetap, dan kelak Kategori/Barang) | `app/Filament/Support/AksiImpor.php:67-71` | **gray · `link()`** — tautan abu-abu tanpa garis | **Berbeda** |
| 4 | Ekspor Kartu Kendali (toolbar) | `app/Filament/Pages/KartuKendali.php:216-218` | ikon saja; warna/bentuk **tidak ditentukan** (bawaan Filament, tombol terisi) | **Berbeda** |
| 5 | Ekspor (grup Riwayat: PDF, Excel, Semua) | `app/Filament/Pages/Concerns/MengeksporRiwayat.php:61-64` | `ActionGroup` · primary · `button()` **terisi** (tidak bergaris) | **Berbeda** |
| 6 | Unduh Panduan (Pusat Bantuan) | `resources/views/filament/pages/pusat-bantuan.blade.php:134-143` | `<x-filament::button>` Blade, primary **terisi**, ikon `heroicon-o-` (outline, bukan `-m-`) | **Berbeda** (bukan objek Action PHP) |

Bukan tombol unduh (tidak diubah): "Kartu Kendali" (pratinjau, `KartuKendali.php:183-187`, gray outlined) dan
tombol kirim dialog "Cetak PDF"/"Unduh" (`modalSubmitActionLabel`) — keduanya bagian dari dialog, bukan tombol
berdiri sendiri. Tombol Impor (`AksiImpor.php:36-40`, gray outlined) juga bukan tombol unduh.

### D.2 Usulan

Jadikan gaya Unduh Bukti **satu definisi yang dipakai ulang**, bukan disalin. Polanya meniru `AksiUbah`:

```php
// app/Filament/Support/GayaUnduh.php
class GayaUnduh
{
    /** Gaya tombol pengunduhan: bergaris warna utama, ikon unduh di depan label. */
    public static function terapkan(Action $aksi): Action
    {
        return $aksi
            ->icon('heroicon-m-arrow-down-tray')
            ->iconPosition(IconPosition::Before)
            ->color('primary')
            ->button()
            ->outlined();
    }
}
```

| Tombol | Perubahan |
|---|---|
| Unduh Bukti | `GayaUnduh::terapkan(Action::make('unduhBukti'))->label(...)->tooltip(...)...` — tetap menjadi acuan, tampilannya tidak berubah. |
| Unduh BAST | Ganti lima baris salinan dengan `GayaUnduh::terapkan(...)`. Tampilan tidak berubah. |
| **Unduh Template** | `GayaUnduh::terapkan(Action::make('unduhTemplate'))` — dari tautan abu-abu menjadi tombol bergaris biru. Satu perubahan di `AksiImpor` berlaku untuk kelima halaman impor. |
| Ekspor Kartu Kendali | `GayaUnduh::terapkan(...)` — disarankan. |
| Ekspor (Riwayat) | `ActionGroup` bukan `Action`, sehingga tidak bisa lewat metode di atas tanpa overload. Opsi: tambahkan `->outlined()` pada grupnya, atau biarkan terisi sebagai aksi utama halaman. **Keputusan pemilik (G-8).** |
| Unduh Panduan | Blade: dapat diberi atribut `outlined` dan ikon `heroicon-m-arrow-down-tray`, tetapi **tidak bisa memakai definisi PHP yang sama** — nilainya tetap salinan. Ini kartu unduhan utama halaman; disarankan dibiarkan (G-8). |

Tidak ada perubahan pada isi berkas yang diunduh — hanya gaya tombol. Aturan PDF/Excel beku tidak tersentuh.

---

## E. Tombol Kembali di Halaman Buat dan Ubah

### E.1 Daftar halaman

Semua halaman di bawah memakai breadcrumb bawaan Filament (tidak ada yang mematikannya; tidak ada
`getBreadcrumbs`/`breadcrumbs` yang ditimpa di `app/` maupun CSS tema) dan tombol **Batal** bawaan di kaki form
(`CreateRecord.php:288-300`, `EditRecord.php:350-362` di `vendor/filament/filament`). Batal memakai
`history.back()` bila ada referrer, lalu jatuh ke daftar. **Tidak ada satu pun yang punya tombol "Kembali" di
kepala halaman.**

| Halaman | Berkas | Breadcrumb | Tombol Kembali | Header actions sekarang |
|---|---|---|---|---|
| Buat Kategori | `Kategoris/Pages/CreateKategori.php` | Ya | Tidak | — |
| Ubah Kategori | `Kategoris/Pages/EditKategori.php:13-18` | Ya | Tidak | Hapus terlindung |
| Buat Barang Persediaan | `BarangPersediaans/Pages/CreateBarangPersediaan.php` | Ya | Tidak | — |
| Ubah Barang Persediaan | `BarangPersediaans/Pages/EditBarangPersediaan.php:28-33` | Ya | Tidak | Hapus terlindung (+ dialog konfirmasi stok) |
| Buat Aset Tetap | `AsetTetaps/Pages/CreateAsetTetap.php` | Ya | Tidak | — |
| Ubah Aset Tetap | `AsetTetaps/Pages/EditAsetTetap.php:46-51` | Ya | Tidak | Hapus terlindung |
| Buat Tim Kerja | `Tims/Pages/CreateTim.php` | Ya | Tidak | — |
| Ubah Tim Kerja | `Tims/Pages/EditTim.php:13-18` | Ya | Tidak | Hapus terlindung |
| Buat Pengguna | `Users/Pages/CreateUser.php` | Ya | Tidak | — |
| Ubah Pengguna | `Users/Pages/EditUser.php:17-22` | Ya | Tidak | Hapus terlindung |

Dikecualikan:
- **Buat BAST** — pop-up (`ListBastMutasiAsets.php:25-50`, resource tanpa halaman `create`). Tidak diubah.
- **Rincian Permintaan** — `DetailPermintaanBarang` hanya pengalih ke pop-up di daftar (`DetailPermintaanBarang.php:32-37`); tidak ada UI.
- **Rincian/Kartu Kendali/Riwayat Penempatan** — semuanya pop-up (modal), bukan halaman.
- **Ganti Kata Sandi**, **Lengkapi Akun** — lihat E.3.
- **Pengaturan**, **Pusat Bantuan**, **Riwayat**, **Stok Masuk**, **Kartu Kendali**, **Katalog** — halaman menu, bukan halaman yang dibuka dari daftar.

### E.2 Usulan satu cara yang konsisten

Pabrik aksi bersama di `app/Filament/Support`, sejalan dengan `AksiUbah`/`AksiImpor`/`AksiHapusTerlindung`:

```php
// app/Filament/Support/AksiKembali.php
class AksiKembali
{
    /** @param class-string<\Filament\Resources\Resource> $resource */
    public static function keDaftar(string $resource): Action
    {
        return Action::make('kembali')
            ->label('Kembali')
            ->icon('heroicon-m-arrow-left')
            ->iconPosition(IconPosition::Before)
            ->color('gray')
            ->button()
            ->outlined()
            ->url($resource::getUrl('index'));
    }
}
```

Dipasang **eksplisit** sebagai aksi pertama `getHeaderActions()` di kesepuluh halaman
(`AksiKembali::keDaftar(static::getResource())`). Trait yang menimpa `getHeaderActions()` tidak dipilih karena
kelima halaman Ubah sudah punya `getHeaderActions()` sendiri — metode kelas akan menang atas trait dan tombolnya
diam-diam hilang.

Gaya: abu-abu bergaris, sama dengan tombol sekunder yang sudah ada (Impor, Riwayat di Stok Masuk). Tidak memakai
warna utama agar tidak bersaing dengan tombol Simpan.

Tujuan: URL daftar yang tetap (`getUrl('index')`), bukan `history.back()`, supaya perilakunya dapat diramalkan
(sesudah Simpan, `history.back()` bisa kembali ke form yang sama). Penyaring daftar tidak dipertahankan
(Filament tidak menyimpannya di sesi pada panel ini) — lihat G-9.

Catatan: seperti breadcrumb, Kembali tidak menanyakan perubahan yang belum disimpan (panel tidak memakai
`unsavedChangesAlerts()`). Pada Ubah Barang Persediaan, perubahan stok yang belum dikonfirmasi memang belum
tersimpan, jadi tidak ada data yang tertinggal setengah jalan.

### E.3 Penjaga yang tidak boleh dilewati

- `PaksaGantiKataSandi` (`app/Http/Middleware/PaksaGantiKataSandi.php:25-52`) dan `PaksaLengkapiAkun`
  (`app/Http/Middleware/PaksaLengkapiAkun.php:34-56`) adalah middleware `authMiddleware` yang memeriksa **setiap
  permintaan** halaman. Tombol Kembali hanya berupa tautan GET, sehingga walaupun ditekan, middleware tetap
  mengalihkan kembali ke halaman penjaga. **Tidak ada jalur pintas.**
- Meski begitu, **Ganti Kata Sandi** dan **Lengkapi Akun** harus **dikecualikan**: tombol Kembali di sana
  menjanjikan jalan keluar yang tidak ada (mengklik hanya memantul ke halaman yang sama). Keduanya bukan
  CreateRecord/EditRecord, jadi otomatis tidak ikut bila tombol dipasang hanya di sepuluh halaman di E.1.
- `EditUser` pada akun sendiri: Kembali tidak memengaruhi penjaga Admin terakhir (`beforeSave`), karena tidak
  menyimpan apa pun.

---

## F. Dampak terhadap Pengujian

Rujukan unit: `docs/pengujian/wb-increment-1.md` (Modul 1 — Pengguna & Data Induk) dan pemetaan berkas tes di
`docs/pengujian/regresi.md` §2.

### F.A Kode Akun

| Aspek | Rincian |
|---|---|
| Method berubah | `KategoriForm::configure` — teks bantuan saja: **tanpa percabangan baru**. Bila opsi A-1 (format bergantung tipe) dipilih: satu closure bercabang → **calon unit white-box baru** (V(G)=2). |
| Temuan sampingan A.6-1 | `KategoriForm::configure` (`->unique`) + `CreateKategori::handleRecordCreation`/`EditKategori::handleRecordUpdate` baru (masing-masing V(G)=2, pola T-2) → 2 calon unit. |
| Temuan sampingan A.6-2 | `BarangPersediaanForm` — penyaring query, tanpa percabangan. |
| Tes lama terdampak | Tidak ada tes yang membuat kategori lewat form dengan kode akun tak baku (`9.9.9`, `1.3.2`, `117n` dibuat lewat model, tidak melewati validasi form). |
| Tes baru | ± 3 (format sah/tidak sah per tipe) + ± 5 bila A.6-1 dikerjakan. |

### F.B Kode Barang 6 Digit

| Aspek | Rincian |
|---|---|
| Method berubah | `BarangPersediaanForm::configure` (aturan tambahan, **tanpa percabangan** bila universal; +1 cabang bila memakai pengecualian B.4). `StokMasuk::borangBarangBaru` (aturan tambahan; `dehydrateStateUsing` diganti `trim()`) — closure unit **1.10** tidak berubah logikanya, V(G) tetap 2. `StokMasuk::simpanBarangBaru` (unit 1.11) **tidak berubah**. |
| Tes lama yang **harus diperbarui** (memakai kode tak baku lewat form/dialog) | `KodeBarangUnikFormTest` — 8 kasus (`A-100`, `A-101`, `B-200`, `C-300`) · `JalurBasisModul1Test` 1.10 J1 (`Z-900`, akan gagal) dan J2 (`' A-100 '`, masih lulus tetapi karena alasan yang salah — ditolak format, bukan keunikan; jalur basisnya tidak lagi teruji) · `StokTerkunciBacaSajaTest::test_barang_baru_berhasil_dibuat_dengan_stok_terkunci_nol` (`BR-001`). Penggantian: kode 6 digit (`000100`, `000101`, `000200`, `000300`, `000900`). |
| Tes lama yang tidak terdampak | `KodeBarangBaruStokMasukTest` (memanggil `kodeBarangSudahDipakai`/`simpanBarangBaru` langsung, tanpa validasi form) · `JalurBasisModul1Test` 1.11 J3 (kode kosong lewat metode statis) · seluruh tes yang memakai `MenyiapkanDataUji` (sudah `str_pad(…, 6, '0')`). Boleh diseragamkan ke 6 digit demi kerapian, tidak wajib. |
| Tes baru | ± 7: 5 digit ditolak, 7 digit ditolak, huruf ditolak, `000122` diterima dan tersimpan dengan nol di depan, berspasi ujung di dialog diterima setelah dipangkas, format diperiksa sebelum keunikan, (bila B.4) Ubah barang lama tanpa mengganti kode tetap boleh. |

### F.C Impor

| Aspek | Rincian |
|---|---|
| Unit white-box baru | `ImporKategori::jalankan` (perkiraan V(G) ≈ 11-12 ⚠), `ImporKategori::bacaTipe` (≈ 3), `ImporBarangPersediaan::jalankan` (≈ 14-16 ⚠), `ImporBarangPersediaan::bacaStokMinimum` (≈ 3). Keduanya `jalankan` di atas 10 — sebanding dengan `ImporPengguna::jalankan` (20 ⚠) dan layak dipecah menjadi `periksaBaris()` bila ingin V(G) ≤ 10. |
| Method lama berubah | `KategorisTable::configure`, `BarangPersediaansTable::configure` — penambahan aksi, tanpa percabangan. |
| Tes lama terdampak | Tidak ada. `AksiImpor`, `PembuatTemplate`, `PembacaBerkas`, `HasilImpor` tidak diubah. |
| Tes baru | ± 32 (C.6), termasuk: stok barang lama tidak berubah setelah impor ulang; barang baru lahir `stok_fisik=0`, `stok_hold=0`; tidak ada baris `mutasi_stok` baru; kolom Stok Fisik di berkas diabaikan dan dicatat; kategori aset tetap ditolak; kode kehilangan nol di depan ditolak dengan pesan khusus; kode ganda di dalam satu berkas; Petugas Gudang/Tim tidak melihat tombol. Ditambah tes jalur basis per unit baru (mengikuti gaya `JalurBasisModul1Test`). |
| Pemetaan regresi | Dua berkas baru → Modul 1. Jumlah berkas Modul 1: 31 → 33. |

### F.D Tombol Unduh

| Aspek | Rincian |
|---|---|
| Method berubah | `aksiUnduhBukti`, `BastMutasiAsetsTable::configure`, `AksiImpor::buat`, `KartuKendali::table` — hanya pemanggilan gaya, **tanpa percabangan**. `GayaUnduh::terapkan` tanpa percabangan (V(G)=1, tidak perlu unit white-box). |
| Tes lama terdampak | Tidak ada. Tes yang menyebut Unduh Bukti (`DialogRincianPermintaanTest.php:161,174`) hanya memeriksa keberadaan label; tes unduhan (`UnduhanDokumenTest`, `DokumenBuktiTest`) memeriksa rute, bukan gaya. |
| Tes baru | ± 2 (tombol Unduh Template masih mengunduh berkas xlsx dengan tajuk yang sama; aksi bergaris warna utama). |

### F.E Tombol Kembali

| Aspek | Rincian |
|---|---|
| Method berubah/lahir | `AksiKembali::keDaftar` (V(G)=1). Sepuluh `getHeaderActions()` — lima di antaranya baru lahir (halaman Buat), semuanya tanpa percabangan. |
| Tes lama terdampak | Tidak ada yang menghitung jumlah header action. Tes Hapus (`PerlindunganHapusTest`, `HapusAkunTerjagaTest`) memanggil aksi berdasarkan nama. |
| Tes baru | ± 3: sepuluh halaman memiliki aksi `kembali` ber-URL daftar (satu tes berpenyedia data); Ganti Kata Sandi dan Lengkapi Akun tidak memilikinya; pengguna `harus_ganti_sandi` yang membuka URL daftar tetap dialihkan. |

### Ringkasan

| Bagian | Calon unit white-box baru | Tes lama diperbarui | Tes baru (perkiraan) |
|---|---|---|---|
| A | 0 (1 bila A-1; +2 bila A.6-1) | 0 | 3 (+5) |
| B | 0 (1 bila B.4) | 11 kasus di 3 berkas | 7 |
| C | 4 | 0 | 32 + jalur basis |
| D | 0 | 0 | 2 |
| E | 0 | 0 | 3 |

Setelah eksekusi, dokumen `docs/pengujian/wb-increment-1.md` bertambah unit C (dan A/B bila opsi bercabang dipilih),
dan `docs/pengujian/regresi.md` perlu dijalankan ulang (retest-all) karena bagian B mengubah tes lama.

---

## G. Pertanyaan Terbuka untuk Pemilik

| # | Pertanyaan | Usulan bawaan bila tidak ada jawaban |
|---|---|---|
| G-1 | **Arti kode akun.** Apakah `117111` memang kode akun Barang Konsumsi pada Bagan Akun Standar/SAKTI, dan `1.3.2` golongan Peralatan dan Mesin? Kode tidak menyebutnya. | Teks bantuan versi netral (A.5). |
| G-2 | **Format kode akun.** Tetap 6 digit untuk kategori persediaan? Bagaimana untuk aset tetap (`1.3.2` bertitik)? | Opsi A-1 bila G-1 dikonfirmasi; A-3 (tanpa validasi) bila tidak. |
| G-3 | **Data lama yang tidak 6 digit.** Data kerja 0 pelanggaran. Bila produksi memuat pelanggaran: aturan hanya untuk data baru dan saat kode diubah (B.4), atau diperbaiki manual dulu lalu aturan universal? | Periksa produksi dulu (B.2); bila 0, aturan universal. |
| G-4 | **Kunci sinkronisasi.** Kategori dicocokkan lewat **Kode Kategori** (bukan nama)? Barang lewat **Kode Kategori + Kode Barang** (bukan nama kategori)? | Ya, keduanya lewat kode. |
| G-5 | **Stok pada impor barang.** Barang baru lahir berstok 0 dan diisi lewat Stok Masuk (sumber Stok Awal) oleh Petugas Gudang? Atau ada kebutuhan mengisi stok awal massal (yang berarti perubahan alur dan hak akses)? | Stok 0; impor tidak pernah menyentuh stok. |
| G-6 | **Status Aktif pada impor barang.** Dikeluarkan dari template (menonaktifkan tetap lewat form), atau disertakan dengan aturan "kosong = tidak diubah"? | Dikeluarkan — seperti `ImporAsetTetap`, status aktif adalah kendali pengelola. |
| G-7 | **Provenans.** Perlukah strip "Sinkronisasi terakhir" di Kategori/Barang? Itu butuh kolom `synced_at` baru (migration skema, tanpa mengubah data). | Tidak; ikuti pola Pengguna. |
| G-8 | **Tombol Ekspor (Riwayat) dan Unduh Panduan.** Ikut dijadikan bergaris seperti Unduh Bukti, atau dibiarkan terisi sebagai aksi utama halaman? | Unduh Template, Unduh BAST, Ekspor Kartu Kendali diseragamkan; Ekspor Riwayat dan Unduh Panduan dibiarkan. |
| G-9 | **Tujuan tombol Kembali.** Daftar polos, atau daftar dengan penyaring terakhir? | Daftar polos (`getUrl('index')`). |
| G-10 | **Temuan sampingan A.6.** Dikerjakan bersama rencana ini (keunikan `kode_kategori` di form; penyaring tipe pada pilihan kategori barang)? | Ya — keduanya kecil dan sekelas dengan T-2. |
| G-11 | **Stok fisik di form Buat Barang Persediaan.** Tetap boleh diisi (keputusan T-57 berlaku juga saat Buat), atau diselaraskan dengan G-5 (barang baru selalu 0)? Mengubahnya berdampak pada `StokTerkunciBacaSajaTest`. | Dibiarkan; hanya dicatat. |

---

*Tidak ada kode, basis data, maupun riwayat git yang diubah dalam penyusunan dokumen ini. Menunggu keputusan pemilik
sebelum eksekusi.*

---

## H. Pelaksanaan (25 September 2026)

Dieksekusi di atas `main` @ `8449a47` + perbaikan T-1/T-2 yang belum di-commit (suite awal: **815 lolos,
5683 assertion**, SQLite). Cadangan berkas sebelum disentuh beserta manifest sha256:
`E:\Projek Skripsi\cadangan-audit\data-induk-persediaan\asli\` (28 berkas). Tidak ada commit, tidak ada migration,
basis data kerja tidak disentuh.

### Keputusan pemilik yang diterapkan

G-1 teks bantuan versi lengkap · G-2 opsi A-1 · G-3 aturan 6 digit universal · G-4 kunci kode · G-5 impor tidak
pernah menulis stok · G-6 tanpa kolom Aktif · G-7 tanpa provenans/migration · G-8 seluruh tombol unduh lewat
`GayaUnduh` · G-9 Kembali ke `getUrl('index')` · G-10 kedua temuan sampingan · G-11 form Buat dibiarkan.

### Berkas per bagian

| Bagian | Baru | Diubah |
|---|---|---|
| B | `app/Support/KodeBarang.php` | `BarangPersediaanForm.php`, `StokMasuk.php` (dialog Barang Baru: `dehydrateStateUsing` → `trim()`) |
| A.6 | — | `KategoriForm.php` (unique + pesan), `CreateKategori.php`, `EditKategori.php` (jaring `UniqueConstraintViolationException`), `BarangPersediaanForm.php` (kategori persediaan saja) |
| A | — | `KategoriForm.php` (placeholder, helperText BAS, regex 6 digit bila tipe persediaan) |
| C | `ImporKategori.php`, `ImporBarangPersediaan.php` | `KategorisTable.php`, `BarangPersediaansTable.php` |
| D | `app/Filament/Support/GayaUnduh.php` | `PermintaanBarangResource.php` (Unduh Bukti), `BastMutasiAsetsTable.php`, `AksiImpor.php` (Unduh Template), `KartuKendali.php`, `MengeksporRiwayat.php` (grup Ekspor), `pusat-bantuan.blade.php` (salinan + komentar) |
| E | `app/Filament/Support/AksiKembali.php` | 10 halaman Create/Edit (Kategori, Barang Persediaan, Aset Tetap, Tim, Pengguna) |
| F.1 | `ImporStokAwal.php` | `AksiImpor.php` (parameter `label`, `isian`; kompatibel mundur), `StokMasuk.php` (tombol Impor Stok Awal) |
| Dok. | — | `docs/audit/matriks-akses.md`, `docs/sinkronisasi-data.md`, dokumen ini |

Tes: diperbarui `KodeBarangUnikFormTest` (8), `JalurBasisModul1Test` 1.10 J1/J2 (J2 kini juga memastikan pesannya
pesan keunikan, bukan pesan 6 digit), `StokTerkunciBacaSajaTest` (1). Baru: `KodeBarangEnamDigitTest` (10),
`KategoriFormTest` (10), `ImporKategoriTest` (16), `ImporBarangPersediaanTest` (23), `GayaTombolUnduhTest` (5),
`TombolKembaliTest` (12), `ImporStokAwalTest` (25) — **101 kasus baru**.

### Bukti larangan PDF/Excel beku

1. `git diff --stat` untuk `resources/views/pdf/*`, `DokumenPermintaanService`, `DokumenBastService`,
   `KartuKendaliService`, `EksporRiwayatService`, `app/Models/BarangPersediaan.php`: **kosong**. sha256 kesembilan
   berkas sama sebelum dan sesudah (`beku-sebelum.sha256` = `beku-sesudah.sha256`). Master
   `resources/templates/kartu-kendali.xlsx` juga tidak berubah.
2. PDF bukti permintaan (asli dan berfootnote), PDF kartu kendali, dan Excel kartu kendali dibentuk dari data yang
   sama sebelum dan sesudah perubahan (`dokumen-sebelum/`, `dokumen-sesudah/`), lalu dibandingkan byte demi byte:

| Berkas | Ukuran | Byte berbeda | Letak perbedaan | Setelah metadata disamakan |
|---|---|---|---|---|
| bukti-permintaan.pdf | 71452 = 71452 | 72 | `/CreationDate`, `/ModDate`, `/ID` | identik |
| bukti-permintaan-berfootnote.pdf | 64840 = 64840 | 70 | idem | identik |
| kartu-kendali.pdf | 4760 = 4760 | 74 | idem | identik |
| kartu-kendali.xlsx | 8428 = 8428 | 48 | stempel waktu header zip | identik; isi ke-12 entri zip identik |

   Pembanding dikalibrasi dulu pada dua bentukan "sebelum" berturut-turut (derau 60–66 byte, di tempat yang sama).

### Hasil tes

| Basis data | Hasil |
|---|---|
| SQLite (`:memory:`) | **916 lolos, 6123 assertion**, 258,8 s |
| MySQL 8.4.3 sementara (`127.0.0.1:3317/simpbi_uji`, datadir di luar repo, layanan Laragon 3306 tidak disentuh) | **916 lolos, 6123 assertion**, 287,4 s |

### F.0 — Pergantian tahun (tanpa perubahan kode)

- Saldo awal kartu tahun N (`KartuKendaliService::data`, `KartuKendaliService.php:102-148`) = `saldo_sesudah` baris
  mutasi terakhir sebelum 1 Januari N, ditambah baris Stok Awal bertanggal tepat 1 Januari
  (`saldoPembuka`/`bawaanTahunLalu`, `:159-172`); tidak ada baris = 0. Sisa akhir = `saldo_sesudah` baris terakhir
  dalam tahun itu, atau saldo awal bila kosong.
- `saldoAwalTersirat` (`StokService.php:301-304`) hanya dipakai saat menyisipkan transaksi (titik mulai
  `hitungUlangSaldo`), bukan saat membentuk kartu.
- Satu-satunya penulis `stok_fisik` di luar form data induk adalah `StokService::hitungUlangSaldo`
  (`StokService.php:348`); tidak ada perintah terjadwal (`routes/console.php` hanya `permintaan:lepas-hold`) maupun
  kode lain yang me-nol-kan atau mengubah stok saat tahun berganti.
- Simulasi (basis data `:memory:`): stok awal 10 (2 Jan 2026), pembelian 25 (15 Mar), keluar 3 (10 Jun) dan 7
  (30 Des). **Sisa akhir 2026 = 25; saldo awal 2027 = 25**, `stok_fisik` 25 tanpa tindakan apa pun. Setelah
  masuk 5 dan keluar 4 pada 2027: saldo awal 2027 tetap 25, sisa akhir 26, `stok_fisik` 26. Tidak ada langkah
  manual yang diperlukan, sehingga F.1 dikerjakan.

### Calon unit white-box baru (perkiraan V(G), konvensi `wb-increment-1.md`; analisis final terpisah)

| Method | Perkiraan V(G) |
|---|---|
| `ImporKategori::jalankan` | ≈ 19 ⚠ |
| `ImporKategori::bacaTipe` | 2 |
| `ImporBarangPersediaan::jalankan` | ≈ 23 ⚠ |
| `ImporBarangPersediaan::bacaStokMinimum` | 3 |
| `ImporStokAwal::jalankan` | ≈ 19 ⚠ |
| `KodeBarang::pesanGalatImpor` | 4 |
| `CreateKategori::handleRecordCreation` | 2 |
| `EditKategori::handleRecordUpdate` | 2 |
| `StokMasuk` › closure impor stok awal (`?? null`) | 2 |

Tanpa percabangan (V(G) = 1, bukan calon unit): `KodeBarang::sah`, `GayaUnduh::terapkan`, `AksiKembali::keDaftar`,
closure kondisi aturan kode akun di `KategoriForm` (percabangannya dijalankan Filament lewat
`rule($aturan, $kondisi)`), closure `modifyQueryUsing` di `BarangPersediaanForm`. Unit lama 1.10 (closure keunikan
dialog Barang Baru) dan 1.11 (`simpanBarangBaru`) tidak berubah logikanya; `AksiImpor::buat` hanya menerima dua
parameter opsional baru.

### Yang tidak dikerjakan

- Analisis white-box ulang (sesuai perintah; dijalankan terpisah).
- Aturan "kode kategori persediaan 10 digit" (rencana C.3 butir 5) — tidak termasuk keputusan pemilik.
- Tinjauan visual di peramban untuk tombol yang diseragamkan dan tombol Kembali — rupa hanya dibuktikan lewat tes
  atribut aksi (warna, ikon, posisi ikon, bergaris, tampilan tombol).
