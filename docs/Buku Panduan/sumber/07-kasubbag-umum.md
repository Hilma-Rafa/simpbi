# Kasubbag Umum {#B-KAS}

Sebagai Kasubbag Umum, Anda memegang dua kata akhir dalam alur permintaan barang: persetujuan akhir jumlah yang benar-benar diberikan (tahap 3), dan pengesahan dokumen setelah barang diterima (tahap 6). Anda juga mengesahkan setiap BAST mutasi aset, dan bertanggung jawab atas data induk barang persediaan dan aset tetap.

> **Yang dapat Anda lakukan:** memberi persetujuan akhir (tahap 3) dan mengesahkan (tahap 6) permintaan barang, mengesahkan BAST mutasi aset, mengelola Barang Persediaan, Kategori Barang, Aset Tetap, dan Tim Kerja, melihat Kartu Kendali dan mengekspornya, melihat Riwayat (Permintaan, Mutasi Stok, Mutasi Aset).
>
> **Yang tidak dapat Anda lakukan:** memverifikasi stok fisik atau menyiapkan barang (wewenang Petugas Gudang), membuat BAST (hanya membuat, wewenang Petugas Gudang — Anda mengesahkannya), mengelola akun Pengguna atau pengaturan sistem (wewenang Admin Sistem).

## Persetujuan Akhir Permintaan (Tahap 3) {#S-KAS-01}

### Menyetujui permintaan {#T-KAS-01}

**Peran:** Kasubbag Umum

Fungsi: menetapkan jumlah akhir yang benar-benar disetujui untuk tiap barang pada permintaan, berdasarkan hasil verifikasi fisik Petugas Gudang.

Prasyarat: permintaan berstatus **Menunggu Persetujuan akhir Kasubbag**.

Langkah:
1. Pada halaman **Permintaan Barang**, buka baris permintaan berstatus Menunggu Kasubbag Umum, tekan [Setujui].
2. Untuk tiap barang, periksa **Diminta** dan **Hasil Cek**, lalu isi **Disetujui** — jumlah dapat lebih kecil dari yang diminta apabila permintaan disetujui sebagian.
3. Isi **Catatan** bila perlu.
4. Tekan [Setujui].

[GAMBAR: G-KAS-01-1 | Dialog Persetujuan Akhir Permintaan dengan kolom Disetujui]

> **Perhatian:** jumlah yang Anda setujui tidak boleh melebihi jumlah yang diminta. Jika dilanggar: "Jumlah disetujui tidak boleh melebihi jumlah diminta (:max)."

> **Contoh:** hasil verifikasi Petugas Gudang atas 10 rim kertas HVS yang diminta Tim Statistik Sosial menunjukkan hanya 8 rim tersedia ([[T-GUD-01]]). Anda menyetujui 8 rim sesuai hasil cek.

> **Hasil:** status permintaan menjadi **Siap Diproses**, menunggu penyiapan oleh Petugas Gudang ([[T-GUD-02]]).

### Menolak permintaan (tahap 3) {#T-KAS-02}

**Peran:** Kasubbag Umum

Prasyarat: permintaan berstatus **Menunggu Persetujuan akhir Kasubbag**.

Langkah:
1. Pada baris permintaan, tekan [Tolak].
2. Isi **Alasan Penolakan** (wajib).
3. Tekan [Tolak].

> **Hasil:** "Stok yang dikunci akan dilepaskan kembali." Status permintaan menjadi **Ditolak Kasubbag**.

## Pengesahan Akhir (Tahap 6) {#S-KAS-02}

### Mengesahkan permintaan {#T-KAS-03}

**Peran:** Kasubbag Umum

Fungsi: menutup permintaan secara resmi setelah barang dikonfirmasi diterima, menerbitkan dokumen bukti permintaan beserta kode QR verifikasi keasliannya.

Prasyarat: permintaan berstatus **Menunggu Pengesahan** (pemohon sudah mengonfirmasi penerimaan, [[T-TIM-03]]/[[S-KT-03]]).

Langkah:
1. Pada baris permintaan berstatus Menunggu Pengesahan, tekan [Sahkan].
2. Isi **Catatan** bila perlu.
3. Pada kolom **Ketik NIP Anda untuk mengonfirmasi**, ketik NIP Anda.
4. Tekan [Sahkan].

[GAMBAR: G-KAS-03-1 | Dialog Pengesahan Akhir Permintaan]

> **Catatan:** pengesahan Anda tidak membubuhkan gambar tanda tangan — melainkan e-TTD (nama Anda + kode QR verifikasi keaslian dokumen), sesuai kewenangan Kasubbag Umum pada dokumen resmi SIMPBI.

> **Hasil:** "Pengesahan akan menerbitkan dokumen bukti permintaan beserta kode QR verifikasi." Status permintaan menjadi **Selesai**. Dokumen bukti dapat diunduh ([[T-UMUM-03]]) oleh pihak yang berhak.

## Mengesahkan BAST Mutasi Aset {#S-KAS-03}

### Mengesahkan BAST {#T-KAS-04}

**Peran:** Kasubbag Umum

Fungsi: mengesahkan BAST yang dibuat Petugas Gudang, memindahkan penempatan aset ke tim tujuan dan membubuhkan e-TTD pada dokumen.

Prasyarat: BAST berstatus **Menunggu Pengesahan**.

Langkah:
1. Pada halaman **Mutasi Aset**, buka baris BAST berstatus Menunggu Pengesahan, tekan [Sahkan].
2. Baca deskripsi konfirmasi (nama tim tujuan disebutkan di sana), lalu tekan [Sahkan].

[GAMBAR: G-KAS-04-1 | Dialog Sahkan BAST Mutasi Aset]

> **Perhatian:** bila penempatan aset sudah berubah sejak BAST dibuat (mis. aset sudah dipindah lewat BAST lain lebih dulu), pengesahan ditolak dengan pesan "BAST tidak dapat disahkan" beserta alasannya — status dan penempatan tidak berubah.

> **Hasil:** BAST berstatus **Menunggu Konfirmasi**, menunggu konfirmasi penerimaan oleh Ketua Tim tim tujuan ([[T-KT-03]]). Status BAST secara berurutan: **Menunggu Pengesahan → Menunggu Konfirmasi → Selesai Administratif**.

## Mengelola Data Induk {#S-KAS-04}

### Mengelola Barang Persediaan {#T-KAS-05}

**Peran:** Admin Sistem, Kasubbag Umum

Fungsi: menambah, mengubah, dan menghapus data barang persediaan beserta stoknya.

Langkah:
1. Buka menu **Barang Persediaan** pada sidebar.
2. Tekan [Tambah] untuk barang baru, atau buka baris yang ada untuk mengubahnya.
3. Isi/ubah **Nama Barang**, **Kategori**, **Satuan**, **Stok Fisik** (mengubahnya memunculkan dialog konfirmasi), dan kolom lain.
4. Tekan [Simpan].

> **Catatan:** kolom **Stok Terkunci** (`stok_hold`) hanya menampilkan jumlah stok yang sedang terkunci oleh permintaan berjalan — tidak dapat diubah manual dari formulir mana pun, sebab angkanya harus selalu mencerminkan permintaan yang benar-benar terkunci.

> Barang yang sudah punya riwayat pergerakan (stok masuk/keluar) dilindungi dari penghapusan.

### Menempatkan aset tetap untuk pertama kali {#T-KAS-06}

**Peran:** Admin Sistem, Kasubbag Umum

Fungsi: menetapkan tim kerja pemegang sebuah aset tetap yang belum pernah ditempatkan — langkah wajib sebelum aset itu dapat dimutasi lewat BAST.

Langkah:
1. Buka menu **Aset Tetap**, buka baris aset dengan **Tim Kerja** bertanda "Belum ditempatkan".
2. Pilih **Tim Kerja** tujuan penempatan awal.
3. Tekan [Simpan].

[GAMBAR: G-KAS-06-1 | Formulir Ubah Aset Tetap, kolom Tim Kerja sebelum diisi]

> **Perhatian:** ini hanya bisa dilakukan **sekali** per aset. Setelah tersimpan, kolom **Tim Kerja** pada formulir Ubah Aset Tetap terkunci selamanya dengan keterangan "Penempatan diubah melalui Mutasi Aset (BAST)." — perpindahan berikutnya hanya lewat BAST ([[T-GUD-05]]).

> Kolom **ID Eksternal** dan **Waktu Sinkronisasi** pada bagian Sinkronisasi Data selalu terkunci di formulir ini maupun di Tim Kerja — hanya terisi otomatis lewat impor/sinkronisasi.

### Mengelola Kategori Barang dan Tim Kerja {#T-KAS-07}

**Peran:** Admin Sistem, Kasubbag Umum

Fungsi: mengelola data kategori barang persediaan dan data tim kerja (nama tim, Ketua Tim, status aktif).

Langkah:
1. Buka menu **Kategori Barang** atau **Tim Kerja** pada sidebar.
2. Tekan [Tambah] atau buka baris yang ada, isi/ubah data, tekan [Simpan].

> **Catatan:** pada formulir Tim Kerja, pilihan **Ketua Tim** hanya menampilkan pengguna aktif berperan Ketua Tim pada tim itu — kosong pada formulir Tambah, sebab akun Ketua Tim baru biasanya dibuat lewat menu Pengguna terlebih dulu.

## Tugas Lain {#S-KAS-05}

Melihat Riwayat dan Kartu Kendali, mengunduh BAST: lihat [[B-UMUM]] dan [[T-GUD-04]].
