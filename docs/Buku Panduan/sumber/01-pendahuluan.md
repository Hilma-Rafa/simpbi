# Pendahuluan {#B-PEND}

## Tentang SIMPBI {#S-PEND-01}

SIMPBI (Sistem Informasi Manajemen Persediaan dan Barang Inventaris) adalah aplikasi yang mengelola dua hal yang selama ini berjalan terpisah di atas kertas dan spreadsheet: permintaan barang persediaan (alat tulis, bahan habis pakai, dan sejenisnya) dari tim kerja ke Sub-Bagian Umum, dan pencatatan aset tetap (perlengkapan kantor, mesin, kendaraan) beserta perpindahannya antartim kerja lewat Berita Acara Serah Terima (BAST).

Setiap permintaan barang berjalan lewat alur berjenjang yang melibatkan Ketua Tim, Petugas Gudang, dan Kasubbag Umum secara berurutan — bukan sekadar dicatat, tetapi disetujui, diverifikasi, disiapkan, dan disahkan pada tiap tahapnya. Aset tetap dicatat sekali penempatan awalnya, lalu setiap perpindahan berikutnya wajib lewat BAST yang juga melalui pengesahan dan konfirmasi penerimaan.

## Tujuan dan Ruang Lingkup {#S-PEND-02}

Buku ini menuntun setiap peran pengguna SIMPBI menjalankan tugasnya di aplikasi: mengajukan permintaan barang, memprosesnya di tiap tahap, mencatat dan memindahkan aset tetap, serta mengelola data dan pengaturan sistem. Buku ini menjelaskan APA yang dapat dilakukan tiap peran dan BAGAIMANA melakukannya di layar aplikasi — bukan kebijakan pengadaan barang, bukan proses di luar sistem (mis. pengiriman fisik barang), dan bukan hal teknis pemasangan/pemeliharaan server.

## Peran Pengguna {#S-PEND-03}

SIMPBI mengenal lima peran, masing-masing dengan akun tersendiri:

| Peran | Kemampuan utama |
|---|---|
| **Admin Sistem** | Mengelola akun Pengguna, Tim Kerja, Barang Persediaan, Kategori Barang, dan Aset Tetap; mengatur Batas Waktu Alur dan pengalihan WhatsApp (mode peragaan); tidak berpartisipasi dalam alur persetujuan permintaan barang maupun BAST. |
| **Kasubbag Umum** | Memberi persetujuan akhir dan pengesahan pada permintaan barang; mengesahkan BAST; mengelola Barang Persediaan, Kategori Barang, Aset Tetap, dan Tim Kerja; melihat Kartu Kendali dan mengekspornya. |
| **Petugas Gudang** | Memverifikasi stok fisik dan menyiapkan barang pada permintaan; mencatat Stok Masuk; membuat BAST mutasi aset; melihat Kartu Kendali. |
| **Ketua Tim** | Mengajukan permintaan barang (lewat Katalog Barang) atas nama timnya; menyetujui/menolak permintaan anggota timnya (kecuali bila ia sendiri pengajunya — lihat [[S-PEND-04]]); mengonfirmasi penerimaan barang dan BAST; melihat Aset Tetap Tim Saya. |
| **Tim** | Akun bersama satu tim kerja (bukan akun perorangan); mengajukan permintaan barang dan mengonfirmasi penerimaannya; melihat Aset Tetap Tim Saya. |

Rincian lengkap kemampuan tiap peran per halaman ada di [[B-LAMPIRAN]].

## Gambaran Alur Permintaan Barang {#S-PEND-04}

Setiap permintaan barang berjalan lewat **enam tahap** berurutan, dari pengajuan sampai selesai:

1. **Persetujuan Ketua Tim** — Ketua Tim pemohon menyetujui atau menolak.
2. **Verifikasi stok fisik** — Petugas Gudang memeriksa ketersediaan fisik barang.
3. **Persetujuan akhir Kasubbag Umum** — Kasubbag menetapkan jumlah yang benar-benar disetujui (dapat lebih kecil dari yang diminta) atau menolak.
4. **Penyiapan barang** — Petugas Gudang menyiapkan barang dan membubuhkan tanda tangannya sendiri sebagai bukti penyiapan.
5. **Pengambilan / Konfirmasi Penerimaan** — Ketua Tim atau akun Tim pemohon mengonfirmasi bahwa barang sudah diterima.
6. **Pengesahan** — Kasubbag Umum mengesahkan dokumen bukti permintaan secara final. Permintaan berstatus **Selesai**.

Lima dari enam tahap ini punya batas waktu yang diatur Admin lewat **Pengaturan → Batas Waktu Alur** (tahap keenam, Pengesahan, tidak punya batas waktu tersendiri); lihat [[B-NOTIF]] untuk penjelasan lengkapnya.

> **Catatan:** bila yang mengajukan permintaan adalah akun **Ketua Tim sendiri**, tahap 1 (Persetujuan Ketua Tim) dilewati otomatis — permintaan langsung masuk ke tahap 2 (Verifikasi stok fisik), sebab tidak masuk akal seorang Ketua Tim meminta persetujuan dari dirinya sendiri.

Diagram alur enam tahap ini disiapkan oleh penyusun dokumen final; lihat [[K-01]] pada `skenario-pengambilan.md` untuk data contoh yang dipakai menelusurinya.

## Cara Membaca Buku Ini {#S-PEND-05}

- Nama tombol, tab, atau menu ditulis dalam kurung siku, misalnya [Setujui] atau [Tambah].
- Kotak **> Catatan** menjelaskan hal yang sering membingungkan pengguna baru.
- Kotak **> Perhatian** menandai konsekuensi yang tidak dapat dibatalkan atau aturan keras.
- Kotak **> Hasil** menuliskan persis pesan atau notifikasi yang muncul di aplikasi setelah suatu langkah selesai.
- Nomor kecil pada gambar, misalnya (1) (2), menunjuk elemen yang ditandai kotak merah — nomornya cocok dengan urutan di `daftar-tangkap-layar.md`.
- Seluruh contoh pada buku ini memakai satu kisah yang sama — permintaan kertas HVS oleh Tim Statistik Sosial — supaya alurnya terasa sebagai satu cerita utuh, bukan potongan lepas antarbab. Data pada contoh ini fiktif, bukan data pegawai sungguhan.
