# Sinkronisasi Data SIMPBI

Metode yang dipakai SIMPBI adalah **sinkronisasi berkala satu arah berbasis
berkas**.

Istilah itu dipilih apa adanya. Akses antarmuka pemrograman (API) ke sistem
sumber tidak tersedia, sehingga SIMPBI **tidak** melakukan integrasi API dan
**tidak** melakukan pemutakhiran seketika (*real-time*). Yang dilakukannya:
data diekspor dari sistem sumber menjadi berkas, berkas itu diunggah ke SIMPBI,
lalu SIMPBI menyelaraskan datanya dengan isi berkas tersebut.

Arahnya satu: sistem sumber menulis ke SIMPBI, tidak sebaliknya. SIMPBI tidak
pernah mengirim apa pun kembali ke sistem sumber.

---

## A. Ruang lingkup

| Data | Kunci pencocokan | Status |
|---|---|---|
| Tim Kerja | `nama_tim`, tanpa peka besar kecil huruf | ✅ tersedia |
| Aset Tetap (Peralatan dan Mesin) | `nup` | ✅ tersedia |
| Kategori Barang *(impor data induk, sejak 25 Sep 2026)* | `kode_kategori` | ✅ tersedia — bagian H |
| Barang Persediaan *(impor data induk, sejak 25 Sep 2026)* | `kode_kategori` + `kode_barang` | ✅ tersedia — bagian H |
| Stok Awal *(impor transaksi, sejak 25 Sep 2026)* | `kode_kategori` + `kode_barang` | ✅ tersedia — bagian I |

**Saldo Barang Persediaan tetap berada di luar ruang lingkup sinkronisasi.**
Saldo dan pergerakannya lahir dari transaksi SIMPBI sendiri dan dihitung dari
buku besar mutasi stok. Yang dapat diimpor hanyalah **data induknya** (kategori,
kode, nama, satuan, stok minimum), tanpa kolom provenans dan tanpa strip
"sinkronisasi terakhir" (keputusan pemilik G-7). Satu-satunya jalan stok masuk
lewat berkas adalah **Impor Stok Awal** (bagian I), yang mencatat transaksi
melalui jalur Stok Masuk biasa, bukan menyelaraskan angka.

Yang **tidak pernah** disinkronkan, dalam keadaan apa pun:

- saldo dan pergerakan stok (`stok_fisik`, `stok_hold`, `mutasi_stok`, Kartu
  Kendali) — seluruhnya lahir dari transaksi SIMPBI sendiri;
- permintaan barang beserta seluruh tahapan persetujuannya;
- BAST mutasi aset dan riwayat penempatan aset;
- histori proses mana pun.

---

## B. Cara kerjanya, dari unggah sampai basis data

1. Pengelola membuka halaman datanya (Aset Tetap atau Tim Kerja) dan menekan
   **Impor** pada kepala tabel.
2. Bila belum punya berkasnya, **Unduh Template** menghasilkan berkas Excel
   berisi tajuk kolom, satu baris contoh, dan satu lembar petunjuk pengisian.
3. Berkas hasil ekspor sistem sumber diunggah. Berkas **tidak disimpan** di
   peladen — ia dibaca sekali lalu dibuang.
4. Tiap baris dibaca, divalidasi, lalu dicocokkan dengan data yang ada memakai
   kunci pencocokan pada tabel di atas.
5. Baris yang cocok **diperbarui** pada kolom yang memang dimiliki sistem
   sumber. Baris yang belum ada **ditambahkan**.
6. `synced_at` diisi waktu saat itu; `sumber_data` diisi `impor` (hanya pada
   aset tetap — lihat bagian G); `external_id` diisi bila berkas membawanya.
7. Hasilnya dilaporkan: berapa baris baru, berapa diperbarui, dan baris mana
   yang ditolak beserta alasannya. Bila berkas memuat kolom yang sengaja
   diabaikan — misalnya Tim Kerja Penempatan pada aset yang sudah tercatat —
   keterangannya ikut disampaikan, supaya pembaca laporan tidak menyangka
   penempatannya ikut berubah.

Tiap baris dibungkus transaksinya sendiri. Baris yang sah tetap masuk meski
baris lain bermasalah — berkas ekspor kerap memuat ratusan baris, dan menahan
seluruhnya karena satu salah ketik berarti menunda pemutakhiran yang sudah
benar. Baris yang ditolak tidak mengubah apa pun.

**Sinkronisasi tidak pernah menghapus.** Data yang tidak muncul pada berkas
dibiarkan apa adanya. Berkas ekspor bisa saja tersaring sebagian, dan menghapus
berdasarkan ketidakhadiran akan memusnahkan riwayat yang menyertainya.

---

## C. Pemetaan kolom — Aset Tetap

Berkas: **Template-Sinkronisasi-Aset-Tetap.xlsx**

| Kolom berkas | Kolom SIMPBI | Wajib | Perlakuan |
|---|---|---|---|
| NUP | `nup` | ✅ | Kunci pencocokan. Tidak pernah diubah |
| Nama Aset | `nama_aset` | ✅ | Diperbarui |
| Kategori | `kategori_id` | ✅ | Dipetakan dari nama kategori **aset tetap** yang sudah terdaftar |
| Kondisi | `kondisi` | — | Diperbarui bila diisi; dikosongkan berarti tidak diubah |
| Tim Kerja Penempatan | `tim_penempatan_id` | — | **Hanya pada aset baru** |
| ID Sumber | `external_id` | — | Disimpan sebagai keterangan asal; dikosongkan berarti tidak diubah |
| — | `sumber_data` | — | Diisi `impor` |
| — | `synced_at` | — | Diisi waktu sinkronisasi |

### Yang dilindungi dan alasannya

| Kolom | Mengapa tidak ditimpa |
|---|---|
| `tim_penempatan_id` pada aset yang sudah ada | Ditulis oleh pengesahan BAST mutasi bersama satu baris riwayat penempatan. Menimpanya membuat penempatan dan riwayatnya bertentangan, dan membatalkan BAST yang sudah disahkan tanpa jejak |
| `status_aktif` | Kendali pengelola, bukan keterangan sistem sumber. Penolakan penghapusan aset justru mengarahkan pengguna ke sana sebagai jalan keluarnya, sehingga berkas tidak boleh menghidupkan kembali aset yang sengaja dinonaktifkan |
| `nup` | Kunci pencocokannya sendiri |

### Aset baru dan riwayat penempatannya

Aset yang ditambahkan sinkronisasi memakai mekanisme yang sama dengan
penambahan lewat formulir, yaitu `AsetTetap::catatPenempatanAwal()`. Metode itu
menjaga dirinya sendiri: tanpa tim kerja ia tidak membuat riwayat apa pun, dan
pada aset yang sudah berriwayat ia menolak menambah baris kedua. Dengan begitu
sinkronisasi tidak membuka kembali celah histori penempatan.

### Baris yang ditolak

- NUP kosong atau melebihi 30 aksara
- Nama Aset kosong atau melebihi 150 aksara
- Kategori kosong, atau belum terdaftar sebagai kategori aset tetap
- Kondisi berisi nilai selain Baik / Rusak Ringan / Rusak Berat
- Tim Kerja Penempatan diisi tetapi belum terdaftar
- ID Sumber melebihi 50 aksara

Kategori dan tim kerja **tidak pernah dibuat otomatis**. Membuatnya berarti
membiarkan katalog tumbuh sendiri dari salah ketik pada berkas.

---

## D. Pemetaan kolom — Tim Kerja

Berkas: **Template-Impor-Tim-Kerja.xlsx**

| Kolom berkas | Kolom SIMPBI | Wajib | Perlakuan |
|---|---|---|---|
| Nama Tim | `nama_tim` | ✅ | Kunci pencocokan |
| ID Sumber | `external_id` | — | Disimpan; dikosongkan berarti tidak diubah |
| Aktif | `status_aktif` | — | Diperbarui; dikosongkan berarti Ya |
| — | `synced_at` | — | Diisi waktu sinkronisasi |

`ketua_tim_id` tidak disentuh. Penetapan ketua tim tetap milik impor pengguna,
yang memang mengenal kedua belah pihak.

**Mengapa nama tim, bukan `external_id`?** `external_id` boleh kosong, tidak
unik, dan pada data berjalan memang belum terisi sama sekali. Memakainya sebagai
kunci berarti tidak satu pun tim lama akan pernah cocok, dan seluruhnya akan
tersisip sebagai tim baru. Nama tim adalah satu-satunya penanda yang benar-benar
terisi. Hal yang sama berlaku pada aset tetap, yang memakai NUP.

**Pencocokan nama tidak peka besar kecil huruf.** "Statistik Sosial",
"STATISTIK SOSIAL", dan "statistik sosial" dikenali sebagai tim kerja yang sama;
spasi berlebih di ujung dan di tengah juga diabaikan. Aturan yang sama dipakai
untuk mencocokkan nama kategori pada sinkronisasi aset. Tanpa itu, berkas yang
menulis nama dengan kapitalisasi berbeda akan melahirkan tim kembar — dan karena
sinkronisasi tidak pernah menghapus, kembaran itu menetap. **Nama yang tersimpan
tidak pernah diubah**: yang tampil pada tabel dan dokumen tetap ejaan resminya.

---

## E. Kemutakhiran data

Kolom **Tersinkron** pada tabel Aset Tetap dan Tim Kerja menampilkan
`synced_at`, yaitu kapan baris itu terakhir diselaraskan. Kolomnya tersembunyi
secara bawaan dan dapat dimunculkan lewat pengatur kolom.

SIMPBI **tidak** menetapkan batas waktu kedaluwarsa dan **tidak** menandai data
sebagai basi. Tidak ada ketentuan yang menetapkan berapa lama sebuah baris
boleh tidak diselaraskan, sehingga angka apa pun yang dipakai akan menjadi
karangan. Yang ditampilkan hanyalah tanggalnya apa adanya.

Sinkronisasi juga **tidak dijadwalkan otomatis**. Ia dijalankan ketika pengelola
memang hendak memutakhirkan data, sesuai kebutuhan operasional.

---

## F. Hak akses

Mengikuti kewenangan halamannya, tanpa peran baru:

| Data | Yang dapat menyinkronkan |
|---|---|
| Aset Tetap | Admin Sistem, Kasubbag Umum |
| Tim Kerja | Admin Sistem, Kasubbag Umum |
| Kategori Barang (impor) | Admin Sistem, Kasubbag Umum |
| Barang Persediaan (impor) | Admin Sistem, Kasubbag Umum |
| Stok Awal (impor, halaman Stok Masuk) | Petugas Gudang |

---

## G. Keterbatasan yang diketahui

**Tim Kerja tidak mencatat `sumber_data`.** Tabel `tim` tidak memiliki kolom
itu; yang tersedia hanya `external_id` dan `synced_at`, dan keduanya sudah
diisi. Provenansnya karena itu tercatat sebagian.

**Struktur berkas ekspor sistem sumber belum dipastikan.** Pemetaan kolom di
atas disusun dari kolom yang benar-benar ada di SIMPBI, bukan dari contoh berkas
ekspor yang sebenarnya. Bila berkas ekspor ternyata memakai nama kolom yang
berbeda, tajuk pada template perlu disesuaikan — bukan datanya yang dipaksakan.
Nama sistem sumber juga belum dibakukan: komentar migrasi menyebut KiPApp,
sedangkan catatan penguji menyebut SAKTI dan Keep-Up.

---

## H. Impor data induk Kategori Barang dan Barang Persediaan

Ditambahkan 25 September 2026 (`docs/audit/rencana-data-induk-persediaan.md`,
bagian C). Polanya sama dengan Tim Kerja dan Aset Tetap — tombol **Impor** di
kepala tabel, **Unduh Template** di kaki dialog, upsert per baris, baris sah tetap
masuk, tidak pernah menghapus — tetapi tanpa `synced_at`/`external_id`, sebab
kedua tabel tidak memilikinya dan tidak ditambahkan.

**Kategori Barang** (`ImporKategori`, `Template-Impor-Kategori-Barang.xlsx`)

| Kolom | Wajib | Keterangan |
|---|---|---|
| Kode Kategori | Ya | Kunci pencocokan; tidak pernah diubah dari impor. |
| Nama Kategori | Ya | Diperbarui pada impor ulang. |
| Kode Akun | Ya | Kode akun neraca BAS; kategori persediaan tepat 6 digit (mis. 117111), aset tetap bebas. |
| Tipe | Ya | Persediaan / Aset Tetap. Kategori yang sudah dipakai tidak dapat berganti tipe. |

**Barang Persediaan** (`ImporBarangPersediaan`, `Template-Impor-Barang-Persediaan.xlsx`)

| Kolom | Wajib | Keterangan |
|---|---|---|
| Kode Kategori | Ya | Harus kategori persediaan yang sudah terdaftar. |
| Kode Barang | Ya | Tepat 6 digit (teks). Sel angka yang kehilangan nol di depan ditolak dengan pesan khusus. |
| Nama Barang | Ya | Diperbarui pada impor ulang. |
| Satuan | Ya | Diperbarui pada impor ulang. |
| Stok Minimum | Tidak | Kosong = 0 pada barang baru, tidak diubah pada barang lama. |

Yang **tidak pernah** ditulis impor barang: `stok_fisik`, `stok_hold`,
`status_aktif`, dan tabel `mutasi_stok`. Barang baru lahir berstok 0 dan aktif.
Bila berkas membawa kolom stok (Stok, Stok Fisik, Stok Terkunci, …), kolomnya
diabaikan dan keterangannya disampaikan pada hasil impor.

---

## I. Impor Stok Awal

Ditambahkan 25 September 2026. Tombol **Impor Stok Awal** di halaman Stok Masuk
(`ImporStokAwal`, `Template-Impor-Stok-Awal.xlsx`), hanya untuk Petugas Gudang.
Berbeda dengan bagian H, impor ini **mencatat transaksi**: setiap baris sah
dicatat lewat `StokService::tambah()` bersumber **Stok Awal** — jalur yang sama
dengan Catat Stok Masuk — sehingga stok fisik, buku besar mutasi, kolom Sisa, dan
kartu kendali tetap konsisten.

| Kolom | Wajib | Keterangan |
|---|---|---|
| Kode Kategori | Ya | Kategori persediaan. |
| Kode Barang | Ya | Tepat 6 digit; barang harus sudah terdaftar dan aktif. |
| Nama Barang | Tidak | Pembantu membaca saja; tidak dipakai mencocokkan. |
| Jumlah Stok Awal | Ya | Bilangan bulat > 0; dapat diambil dari kolom Sisa kartu kendali lama. |

Isian dialog: **Tanggal Stok Awal** (wajib, tidak melewati hari ini — aturan yang
sama dengan Catat Stok Masuk) dan **Nomor Dasar** (boleh kosong, maks. 60
aksara). Tanggal 1 Januari membuat saldo tampil sebagai *Stok Awal* kartu kendali
tahun itu (`KartuKendaliService::bawaanTahunLalu`).

Pengaman stok ganda: barang yang sudah memiliki `stok_fisik > 0` **atau** baris
mutasi apa pun ditolak, diperiksa pada baris barang yang terkunci di dalam
transaksi. Mengunggah berkas yang sama dua kali tidak menggandakan stok.

Pergantian tahun tidak memerlukan langkah manual: saldo awal kartu kendali
tahun N dibaca dari `saldo_sesudah` transaksi terakhir sebelum 1 Januari N, dan
`stok_fisik` tidak pernah di-nol-kan. Stok awal karena itu cukup diimpor sekali,
saat SIMPBI mulai dipakai.
