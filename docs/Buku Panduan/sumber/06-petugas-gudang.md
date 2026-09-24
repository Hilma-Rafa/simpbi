# Petugas Gudang {#B-GUD}

Sebagai Petugas Gudang, Andalah yang memastikan barang yang diminta benar-benar ada secara fisik sebelum permintaan berlanjut ke persetujuan akhir, dan yang menyiapkannya begitu semua tahap sebelumnya selesai. Anda juga mencatat barang yang masuk ke gudang dan membuat BAST ketika aset tetap perlu berpindah tim.

> **Yang dapat Anda lakukan:** memverifikasi stok fisik (tahap 2) dan menyiapkan barang (tahap 4) pada permintaan seluruh tim, mencatat Stok Masuk, membuat BAST mutasi aset, melihat Kartu Kendali dan mengekspornya, melihat Riwayat (Permintaan, Mutasi Stok, Mutasi Aset).
>
> **Yang tidak dapat Anda lakukan:** menyetujui permintaan pada tahap 1 atau 3, mengesahkan BAST atau permintaan, mengubah data Barang Persediaan/Kategori/Aset Tetap/Tim Kerja (hanya Admin dan Kasubbag), mengakses menu Pengguna.

## Memverifikasi Stok Fisik (Tahap 2) {#S-GUD-01}

### Memverifikasi ketersediaan fisik {#T-GUD-01}

**Peran:** Petugas Gudang

Fungsi: mencocokkan jumlah yang diminta dengan hasil pengecekan fisik di gudang, sebelum permintaan diteruskan ke persetujuan akhir Kasubbag Umum.

Prasyarat: permintaan berstatus **Menunggu Verifikasi Gudang**.

Langkah:
1. Pada halaman **Permintaan Barang**, buka baris permintaan berstatus Menunggu Gudang, tekan [Verifikasi].
2. Untuk tiap barang pada **Rincian Barang**, isi **Hasil Pengecekan** (jumlah fisik yang benar-benar ada) dan pilih **Kondisi**: Tersedia, Rusak, atau Kurang.
3. Isi **Keterangan Verifikasi** bila perlu.
4. Tekan [Simpan Hasil Verifikasi].

[GAMBAR: G-GUD-01-1 | Dialog Verifikasi Ketersediaan Fisik dengan rincian barang]

> **Contoh:** permintaan 10 rim kertas HVS dari Tim Statistik Sosial sudah disetujui Ketua Tim ([[T-KT-01]]) dan kini berstatus Menunggu Gudang. Anda mengecek fisik, mendapati hanya 8 rim yang tersedia, mengisi Hasil Pengecekan "8" dengan Kondisi "Tersedia".

> **Hasil:** status permintaan menjadi **Menunggu Persetujuan akhir Kasubbag**.

## Menyiapkan Barang (Tahap 4) {#S-GUD-02}

### Menandai barang siap diambil {#T-GUD-02}

**Peran:** Petugas Gudang

Fungsi: menyatakan barang sudah disiapkan dan siap diambil pemohon, menggantikan tanda tangan basah dengan konfirmasi identitas.

Prasyarat: permintaan berstatus **Siap Diproses** (sudah disetujui Kasubbag Umum); tanda tangan Anda sudah terdaftar ([[T-MULAI-03]]).

Langkah:
1. Pada baris permintaan berstatus Siap Diproses, tekan [Barang Siap Diambil].
2. Periksa pratinjau tanda tangan tersimpan Anda yang ditampilkan.
3. Pada kolom **Ketik NIP Anda untuk mengonfirmasi**, ketik NIP Anda.
4. Tekan [Tandai Siap Diambil].

[GAMBAR: G-GUD-02-1 | Dialog Penyiapan Barang dengan pratinjau tanda tangan]

> **Hasil:** status permintaan menjadi **Siap Diambil**, menunggu konfirmasi penerimaan oleh Ketua Tim atau akun Tim pemohon ([[T-TIM-03]] / [[S-KT-03]]).

## Mencatat Stok Masuk {#S-GUD-03}

### Mencatat penerimaan barang ke gudang {#T-GUD-03}

**Peran:** Petugas Gudang

Fungsi: menambah stok fisik suatu barang persediaan berdasarkan dokumen penerimaan (nota, faktur, dsb.) di luar sistem.

Langkah:
1. Buka menu **Stok Masuk** pada sidebar.
2. Tekan [Catat Stok Masuk].
3. Isi **Sumber**, **Nomor Dasar**, dan **Tanggal Dokumen**.
4. Pilih **Barang** yang diterima. Bila barang belum ada di katalog, Anda dapat membuatnya langsung dari dialog ini.
5. Isi **Jumlah** dan **Keterangan** bila perlu.
6. Tekan [Simpan].

[GAMBAR: G-GUD-03-1 | Dialog Catat Stok Masuk]

> **Catatan:** saat membuat barang baru dari dialog ini, **Kode Barang** hanya perlu unik di dalam kategorinya (mengikuti pola penomoran Sub-Bagian Umum) — dua barang di kategori berbeda boleh memakai kode yang sama. Bila kode sudah dipakai pada kategori yang sama: "Kode barang sudah dipakai pada kategori ini."

> **Hasil:** "Stok masuk tercatat." Stok fisik barang bertambah dan tercatat pada **Saldo Setelah**, dapat ditelusuri lewat Kartu Kendali ([[T-GUD-04]]).

## Kartu Kendali {#S-GUD-04}

### Melihat dan mengekspor Kartu Kendali {#T-GUD-04}

**Peran:** Kasubbag Umum, Petugas Gudang

Fungsi: melihat buku besar pergerakan stok tiap barang (Stok Awal, Masuk, Keluar, Sisa) per periode, mengikuti pola Kartu Kendali Sub-Bagian Umum.

Langkah:
1. Buka menu **Kartu Kendali** pada sidebar.
2. Pilih **Periode** dan **Kategori** yang ingin dilihat.
3. Untuk mengunduh, tekan [Ekspor Kartu Kendali], pilih **Format** (PDF atau XLSX), **Periode**, **Kategori**, dan **Nama Barang** bila ingin mempersempit, lalu unduh.

[GAMBAR: G-GUD-04-1 | Halaman Kartu Kendali dengan tabel pergerakan stok]

## Membuat BAST Mutasi Aset {#S-GUD-05}

### Membuat BAST baru {#T-GUD-05}

**Peran:** Petugas Gudang

Fungsi: mencatat perpindahan sebuah aset tetap dari satu tim kerja ke tim kerja lain, berdasarkan koordinasi yang sudah terjadi di luar sistem.

Prasyarat: aset yang dipilih sudah memiliki penempatan, dan tidak sedang memiliki BAST lain berstatus Menunggu Pengesahan dengan tim asal yang sama (lihat [[S-UMUM-03]]).

Langkah:
1. Buka menu **Mutasi Aset** pada sidebar, tekan [Buat BAST] (atau tombol serupa pada daftar).
2. Pilih **Aset (NUP — Nama)**. Kolom **Tim Kerja Asal** terisi otomatis dari penempatan aset saat ini dan tidak dapat diubah.
3. Pilih **Tim Kerja Tujuan** (harus berbeda dari tim asal).
4. Isi **Alasan Mutasi**, **Pihak Penyerah**, dan **Pihak Penerima**.
5. Tekan [Simpan] (atau tombol setara pada formulir Buat).

[GAMBAR: G-GUD-05-1 | Formulir Buat BAST Mutasi Aset]

> **Perhatian:** bila aset yang dipilih belum memiliki penempatan sama sekali, muncul peringatan "Aset belum memiliki penempatan" dan BAST tidak dapat dibuat — aset itu harus ditempatkan lebih dulu lewat halaman Aset Tetap (wewenang Admin Sistem/Kasubbag Umum).

> **Hasil:** BAST baru tercatat berstatus **Menunggu Pengesahan**, menunggu pengesahan Kasubbag Umum ([[S-KAS-03]]).

## Tugas Lain {#S-GUD-06}

Melihat Riwayat, mengunduh BAST yang sudah disahkan: lihat [[B-UMUM]].
