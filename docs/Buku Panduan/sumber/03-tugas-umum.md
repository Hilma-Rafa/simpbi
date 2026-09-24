# Tugas Umum Lintas Peran {#B-UMUM}

Tugas pada bab ini dapat dilakukan oleh lebih dari satu peran, dengan cakupan data yang berbeda-beda (lihat baris **Peran** pada tiap tugas). Ditulis sekali di sini dan dirujuk dari bab peran masing-masing, supaya langkahnya tidak diulang-ulang.

## Melihat Riwayat {#S-UMUM-01}

### Membuka Riwayat {#T-UMUM-01}

**Peran:** semua peran (cakupan data berbeda per peran)

Fungsi: melihat data yang sudah selesai berjalan, dikelompokkan menjadi empat jenis. Jenis yang tersedia berbeda menurut peran:

| Jenis Riwayat | Tersedia untuk |
|---|---|
| Riwayat Permintaan Barang | semua peran (Ketua Tim dan Tim hanya melihat milik tim sendiri) |
| Riwayat Mutasi Stok | Admin Sistem, Kasubbag Umum, Petugas Gudang |
| Riwayat Mutasi Aset | Kasubbag Umum, Petugas Gudang, Ketua Tim (Ketua Tim hanya tim sendiri) |
| Riwayat Pengiriman Notifikasi | Admin Sistem |

Langkah:
1. Buka menu **Riwayat** pada sidebar.
2. Pilih jenis riwayat yang ingin dilihat (bagi peran yang melihat lebih dari satu jenis).
3. Gunakan penyaring yang tersedia (mis. rentang tanggal, status, kode) untuk mempersempit daftar.

[GAMBAR: G-UMUM-01-1 | Halaman Riwayat, jenis Permintaan Barang, dengan penyaring terbuka]

> **Catatan:** Riwayat Pengiriman Notifikasi bukan informasi operasional, melainkan catatan teknis pengiriman WhatsApp (kanal, status kirim, alasan gagal) — karena itu hanya Admin Sistem yang melihatnya. Peran lain tetap menerima notifikasinya lewat lonceng seperti biasa.

### Mengekspor Riwayat {#T-UMUM-02}

**Peran:** sama seperti [[T-UMUM-01]], sesuai jenis riwayat yang sedang dilihat

Fungsi: mengunduh data riwayat sebagai berkas, mengikuti penyaring yang sedang diterapkan di layar — bukan seluruh data.

Langkah:
1. Terapkan penyaring yang diinginkan lebih dulu.
2. Tekan [Ekspor].

> **Catatan:** SIMPBI tidak memiliki menu "Laporan" terpisah. Ekspor data tersedia langsung dari halaman **Riwayat** dan **Kartu Kendali**, selalu mengikuti penyaring yang sedang aktif di layar saat tombol ditekan.

## Mengunduh Bukti Permintaan dan BAST {#S-UMUM-02}

### Mengunduh bukti permintaan {#T-UMUM-03}

**Peran:** Admin Sistem, Kasubbag Umum, Petugas Gudang (seluruh tim); Ketua Tim, Tim (tim sendiri)

Fungsi: mengunduh dokumen PDF bukti permintaan barang.

Prasyarat: permintaan sudah mencapai tahap yang menghasilkan berkas bukti.

Langkah:
1. Pada baris permintaan di halaman **Permintaan Barang** atau **Riwayat**, tekan [Unduh Bukti].

> **Catatan:** dokumen tersimpan di penyimpanan privat server dan hanya dapat dibuka lewat rute aplikasi ini, bukan tautan penyimpanan publik — tautan yang Anda unduh tidak dapat dibagikan begitu saja kepada orang di luar sistem untuk dibuka langsung.

### Mengunduh BAST {#T-UMUM-04}

**Peran:** Kasubbag Umum, Petugas Gudang (seluruh BAST); Ketua Tim, Tim (BAST tim asal atau tim tujuan sendiri) — Admin Sistem tidak dapat mengunduh BAST

Fungsi: mengunduh dokumen PDF Berita Acara Serah Terima mutasi aset.

Prasyarat: BAST **sudah disahkan** oleh Kasubbag Umum. Tombol [Unduh BAST] baru muncul setelah pengesahan itu — bukan sejak BAST dibuat.

Langkah:
1. Pada baris BAST di halaman **Mutasi Aset** atau **Riwayat**, tekan [Unduh BAST].

## Mutasi Aset (BAST) {#S-UMUM-03}

### Melihat daftar Mutasi Aset {#T-UMUM-05}

**Peran:** Kasubbag Umum, Petugas Gudang (semua BAST); Ketua Tim, Tim (BAST dengan tim asal atau tim tujuan = tim sendiri)

Langkah:
1. Buka menu **Mutasi Aset** pada sidebar.

Lihat [[B-GUD]] untuk cara membuat BAST, [[B-KAS]] untuk cara mengesahkannya, dan [[B-KT]] untuk cara mengonfirmasi penerimaannya.

> **Catatan:** kenapa sebuah aset kadang tidak bisa dipilih pada pembuatan BAST baru? Dua kemungkinan: aset itu belum pernah ditempatkan sama sekali (harus ditempatkan lebih dulu lewat halaman **Aset Tetap**, lihat [[T-KAS-06]] di bab Kasubbag Umum), atau aset itu masih memiliki BAST lain yang berstatus **Menunggu Pengesahan** dengan tim asal yang masih sama dengan penempatannya sekarang — BAST itu harus disahkan atau diselesaikan lebih dulu.

## Aset Tetap Tim Saya {#S-UMUM-04}

### Melihat aset tetap tim saya {#T-UMUM-06}

**Peran:** Ketua Tim, Tim (data tim sendiri)

Fungsi: melihat daftar aset tetap yang sedang ditempatkan pada tim Anda beserta kondisinya (Baik / Rusak Ringan / Rusak Berat).

Langkah:
1. Buka menu **Aset Tetap Tim Saya** pada sidebar.
2. Kolom yang tersedia: **Nama Aset**, **Kategori**, **Mulai Penempatan**.
3. Untuk melihat riwayat perpindahan sebuah aset, tekan [Riwayat Penempatan] pada barisnya.

[GAMBAR: G-UMUM-06-1 | Halaman Aset Tetap Tim Saya dengan daftar aset]

> **Catatan:** bila akun Anda belum terhubung ke tim kerja mana pun, halaman ini menampilkan daftar kosong — hubungi Administrator untuk melengkapi data akun Anda.

## Pola Penempatan Awal vs. Mutasi lewat BAST {#S-UMUM-05}

Setiap aset tetap punya tepat satu cara masuk ke sebuah tim kerja untuk pertama kalinya (**penempatan awal**, diisi sekali lewat halaman **Aset Tetap** oleh Admin Sistem atau Kasubbag Umum), dan satu cara berpindah sesudahnya (**Mutasi Aset lewat BAST**, dibuat Petugas Gudang). Begitu penempatan awal terisi, kolom itu terkunci selamanya pada form Ubah Aset Tetap — satu-satunya jalan memindahkannya adalah BAST. Ini mencegah dua sumber pencatatan penempatan yang bisa berbeda nilai.
