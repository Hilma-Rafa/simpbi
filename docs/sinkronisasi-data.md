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

**Barang Persediaan berada di luar ruang lingkup sinkronisasi.** Datanya lahir
dari transaksi SIMPBI sendiri — saldo dan pergerakannya dihitung dari buku besar
mutasi stok — sehingga tidak ada yang perlu diselaraskan dari sistem sumber.
Tidak ada pengimpor, kolom provenans, maupun aksi sinkronisasi yang dibuat
untuknya.

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
