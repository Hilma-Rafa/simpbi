# Lampiran {#B-LAMPIRAN}

## Glosarium {#S-LAMP-01}

| Istilah | Arti |
|---|---|
| **Tim Kerja** | Satuan organisasi pengguna SIMPBI (bukan "Unit Kerja") — setiap permintaan barang dan penempatan aset dikaitkan ke sebuah Tim Kerja. |
| **Ketua Tim** | Peran yang menyetujui permintaan anggota timnya dan tanda tangannya melekat pada dokumen tim itu. |
| **Tim** | Peran berupa akun bersama satu Tim Kerja (bukan akun perorangan). |
| **Kasubbag Umum** | Peran yang memberi persetujuan akhir dan pengesahan (bukan disebut "Kasubbag" saja). |
| **e-TTD** | Tanda tangan elektronik Kasubbag Umum berupa nama + kode QR verifikasi (bukan gambar tanda tangan, dan bukan disebut "tanda tangan digital"). |
| **NUP** | Nomor Urut Pendaftaran — kode unik tiap aset tetap. |
| **BAST** | Berita Acara Serah Terima — dokumen resmi mutasi aset tetap antartim kerja. |
| **Ekspor** | Mengunduh data sebagai berkas PDF/XLSX (bukan disebut "Export"). |
| **Stok Terkunci** | Jumlah stok suatu barang yang sedang dikunci oleh permintaan yang masih berjalan — bukan angka yang dapat diedit langsung. |
| **Mode Peragaan** | Pengalihan sementara seluruh notifikasi WhatsApp ke satu nomor uji, diatur Admin Sistem. |
| **Kedaluwarsa** | Status permintaan yang tahapannya melewati batas waktu tanpa tindak lanjut; stok yang terkunci dilepaskan otomatis. |

## Status Permintaan Barang {#S-LAMP-02}

| Status | Arti |
|---|---|
| Menunggu Persetujuan Ketua Tim | Tahap 1 — menunggu Ketua Tim menyetujui/menolak |
| Menunggu Verifikasi Gudang | Tahap 2 — menunggu Petugas Gudang memverifikasi stok fisik |
| Menunggu Persetujuan akhir Kasubbag | Tahap 3 — menunggu Kasubbag Umum menetapkan jumlah akhir |
| Siap Diproses | Tahap 4 — menunggu Petugas Gudang menyiapkan barang |
| Siap Diambil | Tahap 5 — menunggu konfirmasi penerimaan oleh pemohon |
| Menunggu Pengesahan | Tahap 6 — menunggu pengesahan akhir Kasubbag Umum |
| Selesai | Alur enam tahap tuntas; dokumen bukti sudah terbit |
| Ditolak Ketua Tim | Ditolak pada tahap 1; stok dilepaskan |
| Ditolak Kasubbag | Ditolak pada tahap 3; stok dilepaskan |
| Bermasalah | Dihentikan di luar alur normal (mis. ketidaksesuaian barang); stok dilepaskan |
| Kedaluwarsa | Tahap berjalan melewati batas waktu tanpa tindak lanjut; stok dilepaskan otomatis |

Pada tabel daftar permintaan, badge status memakai versi ringkas untuk tiga status pertama: **Menunggu Ketua Tim**, **Menunggu Gudang**, **Menunggu Kasubbag Umum** — artinya sama persis dengan versi lengkap di atas, hanya dipersingkat agar tabel tidak melebar.

## Status BAST Mutasi Aset {#S-LAMP-03}

| Status | Arti |
|---|---|
| Menunggu Pengesahan | Dibuat Petugas Gudang, menunggu pengesahan Kasubbag Umum |
| Menunggu Konfirmasi | Disahkan Kasubbag Umum; menunggu konfirmasi penerimaan Ketua Tim tim tujuan |
| Selesai Administratif | Dikonfirmasi diterima; penempatan aset resmi berpindah |

## Tabel Peran dan Hak Akses {#S-LAMP-04}

| Halaman / Fitur | Admin Sistem | Kasubbag Umum | Petugas Gudang | Ketua Tim | Tim |
|---|:---:|:---:|:---:|:---:|:---:|
| Dasbor | ✔ | ✔ | ✔ | ✔ | ✔ |
| Katalog Barang (ajukan permintaan) | ✘ | ✘ | ✘ | ✔ | ✔ |
| Permintaan Barang (lihat) | ✔ (semua tim) | ✔ (semua tim) | ✔ (semua tim) | ✔ (tim sendiri) | ✔ (tim sendiri) |
| ↳ Setujui/Tolak tahap 1 | ✘ | ✘ | ✘ | ✔ | ✘ |
| ↳ Verifikasi tahap 2 | ✘ | ✘ | ✔ | ✘ | ✘ |
| ↳ Setujui/Tolak tahap 3 | ✘ | ✔ | ✘ | ✘ | ✘ |
| ↳ Siapkan tahap 4 | ✘ | ✘ | ✔ | ✘ | ✘ |
| ↳ Konfirmasi tahap 5 | ✘ | ✘ | ✘ | ✔ (tim sendiri) | ✔ (tim sendiri) |
| ↳ Sahkan tahap 6 | ✘ | ✔ | ✘ | ✘ | ✘ |
| Riwayat — Permintaan Barang | ✔ | ✔ | ✔ | ✔ (tim sendiri) | ✔ (tim sendiri) |
| Riwayat — Mutasi Stok | ✔ | ✔ | ✔ | ✘ | ✘ |
| Riwayat — Mutasi Aset | ✘ | ✔ | ✔ | ✔ (tim sendiri) | ✘ |
| Riwayat — Notifikasi | ✔ | ✘ | ✘ | ✘ | ✘ |
| Kartu Kendali | ✘ | ✔ | ✔ | ✘ | ✘ |
| Stok Masuk | ✘ | ✘ | ✔ | ✘ | ✘ |
| Barang Persediaan | ✔ | ✔ | ✘ | ✘ | ✘ |
| Kategori Barang | ✔ | ✔ | ✘ | ✘ | ✘ |
| Aset Tetap (kelola + penempatan awal) | ✔ | ✔ | ✘ | ✘ | ✘ |
| Aset Tetap Tim Saya (lihat) | ✘ | ✘ | ✘ | ✔ (tim sendiri) | ✔ (tim sendiri) |
| Mutasi Aset (lihat) | ✘ | ✔ (semua) | ✔ (semua) | ✔ (asal/tujuan tim sendiri) | ✔ (asal/tujuan tim sendiri) |
| ↳ Buat BAST | ✘ | ✘ | ✔ | ✘ | ✘ |
| ↳ Sahkan BAST | ✘ | ✔ | ✘ | ✘ | ✘ |
| ↳ Konfirmasi Penerimaan BAST | ✘ | ✘ | ✘ | ✔ (tim tujuan) | ✘ |
| ↳ Unduh BAST (sudah disahkan) | ✘ | ✔ | ✔ | ✔ (asal/tujuan) | ✔ (asal/tujuan) |
| Tim Kerja | ✔ | ✔ | ✘ | ✘ | ✘ |
| Pengguna | ✔ | ✘ | ✘ | ✘ | ✘ |
| Pengaturan — Akun Saya | ✔ | ✔ | ✔ | ✔ | ✔ |
| Pengaturan — Batas Waktu Alur, WhatsApp, Mode Peragaan | ✔ | ✘ | ✘ | ✘ | ✘ |
| Pusat Bantuan | ✔ | ✔ | ✔ | ✔ | ✔ |

Sumber: pembacaan langsung kode `canAccess()`/`canView()`/`canCreate()` dan closure `->visible()` per 22 September 2026, diverifikasi ulang terhadap `docs/audit/matriks-akses.md` (lihat `laporan-verifikasi.md` untuk hasil pembandingannya).

## Riwayat Versi Dokumen {#S-LAMP-05}

| Versi | Tanggal | Catatan |
|---|---|---|
| Draf 1 | 22 September 2026 | Penyusunan isi awal buku panduan, seluruh peran, berdasarkan kode commit `35a1a7a` |
