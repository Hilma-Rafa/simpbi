# Admin Sistem {#B-ADM}

Sebagai Admin Sistem, tugas Anda berbeda dari empat peran lain: Anda tidak berpartisipasi dalam alur persetujuan permintaan barang maupun BAST — peran Anda adalah menjaga data induk dan akun tetap benar, serta mengatur perilaku sistem secara keseluruhan (batas waktu tiap tahap, dan pengalihan WhatsApp untuk keperluan peragaan).

> **Yang dapat Anda lakukan:** mengelola akun Pengguna (termasuk peran, tim, dan status aktif), Tim Kerja, Barang Persediaan, Kategori Barang, dan Aset Tetap, mengatur Batas Waktu Alur dan mode peragaan WhatsApp, melihat Riwayat (Permintaan, Mutasi Stok, Notifikasi).
>
> **Yang tidak dapat Anda lakukan:** menyetujui, memverifikasi, menyiapkan, atau mengesahkan permintaan barang maupun BAST pada peran mana pun; mengunduh BAST (sekalipun sudah disahkan); melihat Kartu Kendali atau Stok Masuk; menonaktifkan, menghapus, atau menurunkan peran akunnya sendiri; menyisakan nol akun Admin aktif.

## Mengelola Pengguna {#S-ADM-01}

### Menambah atau mengubah akun pengguna {#T-ADM-01}

**Peran:** Admin Sistem

Fungsi: membuat akun baru atau mengubah data akun pengguna lain — satu-satunya jalan mengubah Nama, NIP, Email, Peran, Tim Kerja, dan status aktif siapa pun (lihat [[T-MULAI-06]]).

Langkah:
1. Buka menu **Pengguna** pada sidebar.
2. Tekan [Tambah] untuk akun baru, atau buka baris akun yang ada untuk mengubahnya.
3. Isi/ubah **Nama**, **Username**, **NIP**, **No. HP**, **Email**, **Kata Sandi** (saat membuat baru), **Peran**, **Tim Kerja** (bila perannya bertim), dan **Akun Aktif**.
4. Tekan [Simpan].

[GAMBAR: G-ADM-01-1 | Formulir Tambah Pengguna]

> **Perhatian:** pada akun Anda sendiri, kolom **Peran** dan **Akun Aktif** terkunci — Anda tidak dapat menonaktifkan atau menurunkan peran akun sendiri. Perubahan apa pun yang akan menyisakan nol Admin Sistem aktif juga ditolak di server, baik lewat perubahan satuan maupun impor massal.

### Menghapus akun pengguna {#T-ADM-02}

**Peran:** Admin Sistem

Prasyarat: akun yang dihapus belum pernah bertindak apa pun di sistem (mengajukan, memproses, mencatat), dan bukan akun Anda sendiri, dan penghapusannya tidak menyisakan nol Admin Sistem aktif — baik dihapus satu per satu maupun massal.

Langkah:
1. Pada halaman **Pengguna**, tekan [Hapus] pada baris akun yang dituju (atau pilih beberapa baris untuk hapus massal).
2. Konfirmasikan penghapusan.

> **Hasil bila ditolak:** "Anda tidak dapat menghapus akun Anda sendiri." atau "Harus ada minimal satu Admin aktif." — pada hapus massal, seluruh permintaan ditolak bila salah satu baris melanggar aturan ini, bukan hanya baris yang bermasalah.

## Batas Waktu Alur {#S-ADM-02}

### Mengatur batas waktu tiap tahap {#T-ADM-03}

**Peran:** Admin Sistem

Fungsi: menetapkan berapa lama (dalam jam) tiap tahap permintaan barang boleh berjalan sebelum dianggap kedaluwarsa.

Langkah:
1. Tekan avatar Anda, [Pengaturan], lalu buka bagian **Batas Waktu Alur**.
2. Ubah jam untuk tahap yang diinginkan: **Persetujuan Ketua Tim**, **Verifikasi stok fisik**, **Persetujuan akhir Kasubbag**, **Penyiapan barang**, **Pengambilan oleh pemohon**.
3. Tekan [Simpan Perubahan].

[GAMBAR: G-ADM-03-1 | Bagian Batas Waktu Alur pada halaman Pengaturan]

> **Catatan:** hanya lima dari enam tahap alur permintaan barang yang punya batas waktu di sini — tahap Pengesahan (tahap 6) tidak dibatasi waktu, sebab menunggu tindakan akhir Kasubbag Umum setelah barang sudah diterima pemohon, bukan tahap yang menahan stok. Lihat [[B-NOTIF]] untuk penjelasan apa yang terjadi bila suatu tahap kedaluwarsa.

## Mode Peragaan {#S-ADM-03}

### Menyalakan atau mengubah mode peragaan {#T-ADM-04}

**Peran:** Admin Sistem

Fungsi: mengalihkan **seluruh** notifikasi WhatsApp sistem ke satu nomor uji, agar alur dapat diperagakan tanpa mengirim pesan sungguhan ke nomor pegawai mana pun — termasuk nomor Ketua Tim.

Langkah:
1. Pada bagian **Kontak & WhatsApp** halaman Pengaturan, isi kolom **(mode peragaan)** dengan nomor tujuan pengalihan.
2. Tekan [Simpan Perubahan].
3. Pada dialog "Aktifkan mode peragaan?" (atau "Ubah nomor mode peragaan?" bila sudah menyala), baca peringatannya, lalu tekan [Ya, Simpan].

[GAMBAR: G-ADM-04-1 | Dialog konfirmasi Aktifkan mode peragaan]

> **Perhatian:** dialog konfirmasi ini hanya muncul bagi Admin Sistem, dan hanya ketika mode peragaan dinyalakan atau nomornya diubah — mematikannya (mengosongkan kolom) maupun perubahan lain pada Pengaturan (mis. ganti kata sandi, nomor WA pribadi) tersimpan langsung tanpa dialog apa pun.

> **Hasil selama menyala:** "SEDANG MENYALA. Seluruh notifikasi WhatsApp dikirim ke nomor ini, termasuk milik Ketua Tim, dan nomor pada akun pegawai tidak dipakai." Jangan lupa mengosongkan kolom ini setelah peragaan selesai.

### Mengatur nomor WhatsApp bantuan halaman masuk {#T-ADM-05}

**Peran:** Admin Sistem

Fungsi: mengatur nomor WhatsApp Sub-Bagian Umum yang ditampilkan pada tombol bantuan di halaman masuk **dan** Pusat Bantuan — keduanya membaca nomor yang sama dari satu tempat ini.

Langkah:
1. Pada bagian **Kontak & WhatsApp**, isi **Nomor WhatsApp bantuan masuk**.
2. Tekan [Simpan Perubahan].

> Bila kolom ini dikosongkan, tautan bantuan pada halaman masuk hilang (kalimatnya tetap tampil sebagai keterangan biasa tanpa tautan), dan kartu WhatsApp pada Pusat Bantuan menampilkan "Nomor WhatsApp belum diatur oleh Administrator."

## Mengelola Data Induk {#S-ADM-04}

Mengelola Barang Persediaan, Kategori Barang, Aset Tetap (termasuk penempatan awal), dan Tim Kerja: langkahnya sama persis dengan Kasubbag Umum — lihat [[S-KAS-04]].

## Tugas Lain {#S-ADM-05}

Melihat Riwayat: lihat [[B-UMUM]] (jenis Notifikasi hanya tersedia bagi Admin Sistem).
