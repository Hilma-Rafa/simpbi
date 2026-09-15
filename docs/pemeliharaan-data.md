# Pemeliharaan Data SIMPBI

Prosedur pencadangan, pemulihan, dan reset demo. Ditujukan bagi administrator
peladen dan pengembang — **bukan** pengguna sistem. Ketiganya hanya tersedia
sebagai perintah Artisan; tidak ada tombol, menu, maupun alamat web yang
menjalankannya.

---

## A. Pencadangan

```
php artisan simpbi:backup
```

### Yang dicadangkan

| Isi | Asal |
|---|---|
| Curahan basis data | `mysqldump` (MySQL) atau `VACUUM INTO` (SQLite) |
| Tanda tangan pegawai | `storage/app/private/tanda-tangan/` |
| Bukti permintaan yang sudah terbit | `storage/app/public/bukti-permintaan/` |
| BAST mutasi aset | `storage/app/public/bast-mutasi/` |
| `manifes.json` | keterangan arsip |

Berkas di luar basis data ikut dicadangkan karena basis data hanya menyimpan
**lintasannya**. Dokumen yang sudah disahkan pun tidak dapat dibentuk ulang
secara setara: ia sengaja dibekukan saat terbit, sebab tanda tangan dibaca dari
akun penggunanya dan akan ikut berubah bila orangnya menggambar ulang tanda
tangannya.

### Yang TIDAK dicadangkan

`.env` beserta seluruh kredensial di dalamnya — `APP_KEY`, sandi basis data, dan
token gerbang WhatsApp. Cadangan berpindah tempat dan berganti tangan; rahasia
tidak boleh ikut berpindah bersamanya.

**Simpan `.env` terpisah, di tempat yang berbeda.** Tanpa `APP_KEY` yang sama,
sesi dan cookie lama tidak lagi terbaca sesudah pemulihan.

### Hasil

Arsip ZIP di `storage/cadangan/`, bernama `simpbi-backup-YYYY-MM-DD-HHMMSS.zip`.
Cap waktu membuat cadangan lama tidak pernah tertimpa.

Direktori itu berada **di luar** `storage/app/`, sehingga tidak terjangkau
`public/storage` dan tidak dapat diunduh lewat alamat web. Ia juga tidak ikut
terarsip — arsip yang memuat arsip sebelumnya akan berlipat setiap kali
dijalankan.

### Memastikan cadangan berhasil

Perintahnya menampilkan lokasi, waktu, status basis data, jumlah berkas data,
ukuran, dan lama pengerjaan. Kode keluar `0` berarti berhasil.

Untuk memastikan arsipnya benar-benar dapat dipakai:

```
php artisan simpbi:restore storage/cadangan/<nama>.zip
```

lalu jawab **tidak** pada pertanyaan konfirmasi. Arsip sudah diperiksa
sepenuhnya sebelum pertanyaan itu muncul, sehingga arsip yang cacat akan
tertolak tanpa menyentuh data apa pun.

### Menjadwalkan

```
0 1 * * * cd /path/ke/simpbi && php artisan simpbi:backup >> /dev/null 2>&1
```

Salin arsipnya ke luar mesin. Cadangan yang berada di disk yang sama hanya
melindungi dari kesalahan manusia, bukan dari mesin yang mati.

---

## B. Pemulihan

```
php artisan simpbi:restore <berkas-cadangan>
```

Argumennya boleh berupa lintasan lengkap atau sekadar nama berkas di dalam
`storage/cadangan/`.

### Prasyarat

- Cadangan harus dibuat dari **penggerak basis data yang sama**. Curahan MySQL
  tidak dapat dibaca pemasangan SQLite, dan sebaliknya; perintahnya menolak
  dengan pesan yang jelas.
- Untuk MySQL, perkakas `mysql` harus tersedia pada `PATH`. Bila tidak, tunjuk
  lintasannya lewat `SIMPBI_MYSQL` pada `.env`. Pencadangan MySQL membutuhkan
  `mysqldump`, yang dapat ditunjuk lewat `SIMPBI_MYSQLDUMP`.
- `.env` harus sudah terisi lebih dulu; ia tidak ada di dalam arsip.

### ⚠ Pemulihan menimpa

Seluruh isi basis data, tanda tangan, dan dokumen yang tersimpan sekarang akan
diganti oleh isi arsip. Berkas yang tidak ada di dalam arsip ikut tersapu.

### Urutan aman

1. **Matikan pekerja antrean dan penjadwal.** Tanpa ini, pekerjaan lama berjalan
   di atas data yang baru dipulihkan.
2. Jalankan `php artisan simpbi:restore <berkas>`.
3. Jawab konfirmasi. Perintahnya mencadangkan keadaan sekarang lebih dulu ke
   `sebelum-restore-*.zip`, sehingga pemulihan yang ternyata salah pilih berkas
   masih dapat dibatalkan.
4. **`php artisan queue:clear`.** Curahan basis data ikut membawa isi tabel
   `jobs`; tanpa langkah ini, pesan WhatsApp lama terkirim ulang ke nomor
   pegawai.
5. `php artisan config:clear`, lalu nyalakan lagi antrean dan penjadwal.

### Penjagaan

Arsip diperiksa **sebelum** apa pun disentuh: keutuhan ZIP, keberadaan dan
kesahihan manifes, kecocokan penggerak basis data, dan keberadaan curahan yang
tidak berukuran nol. Arsip yang gagal di salah satunya ditolak dengan kode
keluar `1` dan tidak ada data yang berubah.

Opsi `--paksa` melewati pertanyaan konfirmasi untuk pemulihan tanpa penjaga;
`--tanpa-cadangan` melewati pencadangan pengaman. Keduanya tidak melewati
pemeriksaan arsip.

---

## C. Reset Demo

```
php artisan simpbi:reset-demo
```

### Kapan dipakai

Pengujian fungsional dan penilaian usability dijalankan berkali-kali dengan
pengguna berbeda, dan setiap sesi harus berangkat dari keadaan yang sama.
Perintah ini mengembalikan basis data ke kondisi pemasangan baru.

Menyeed ulang saja tidak cukup: seluruh seeder proyek ini memakai
`updateOrInsert` sehingga bersifat idempoten — dijalankan di atas basis data
yang sudah terpakai, ia hanya menimpa data induk dan meninggalkan seluruh
transaksi.

### 🚫 Dilarang di produksi

Bila `APP_ENV=production`, perintahnya **langsung menolak** dan tidak menghapus
apa pun. Penolakan itu berdiri paling depan, sebelum konfirmasi maupun opsi apa
pun dibaca, dan **tidak dapat dilewati** — termasuk oleh `--paksa`.

### Yang dilakukan

1. `migrate:fresh` — seluruh tabel dibuang dan dibangun ulang, sehingga urutan
   kunci asing tidak perlu diurus sendiri dan tidak mungkin ada sisa.
2. `db:seed` — Tim → Pengguna → Ketua Tim → Katalog → Stok Awal → Aset Tetap →
   BAST contoh.
3. `SaldoAwalKartuKendaliSeeder` — saldo akhir kartu kendali 2025 dicatat sebagai
   transaksi "Stok Awal", supaya kartu kendali hasil pengujian memperlihatkan
   saldo yang sesungguhnya. Membaca `docs/Kartu-Kendali-Persediaan-2025.xlsx`;
   bila berkasnya tidak ada, perintahnya melaporkannya dan tetap melanjutkan.
4. Menyapu `tanda-tangan`, `bukti-permintaan`, dan `bast-mutasi`. Tanpa ini,
   penyimpanan berisi dokumen yang tidak lagi punya catatan.

### Keadaan sesudah reset

| | |
|---|---|
| Tim Kerja | 8 |
| Pengguna | 12 |
| Kategori | 16 |
| Barang persediaan | 119 |
| Aset tetap | 12 |
| Buku besar stok | terisi saldo awal |
| BAST contoh | 1 |
| Permintaan, persetujuan, ketidaksesuaian, notifikasi, antrean | kosong |
| Dokumen dan tanda tangan | kosong |

Akun bawaan: `admin`, `kasubbag`, `gudang`, `ketua01`, `tim01` — kata sandi
`password`.

**Yang hilang:** data yang pernah diimpor atau diketik lewat antarmuka dan tidak
ada di seeder, serta pengaturan batas jam dan WhatsApp yang kembali ke nilai
bawaan migrasi. Bila data itu ingin dipertahankan, jalankan `simpbi:backup`
lebih dulu.

---

## Keamanan

- Arsip cadangan tidak dapat diakses lewat alamat web.
- `.env` dan kredensial tidak pernah masuk arsip.
- Sandi basis data diserahkan kepada `mysqldump`/`mysql` lewat peubah
  lingkungan `MYSQL_PWD`, bukan sebagai argumen baris perintah yang terbaca
  siapa pun yang melihat daftar proses.
- Pemulihan dan reset selalu meminta konfirmasi.
- Tidak ada rute, tombol, peran, atau hak akses baru.
