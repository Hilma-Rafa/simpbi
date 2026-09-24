# Notifikasi dan Batas Waktu {#B-NOTIF}

## Notifikasi Dalam Aplikasi {#S-NOTIF-01}

### Lonceng notifikasi {#T-NOTIF-01}

**Peran:** semua peran (notifikasi milik akun sendiri)

Fungsi: melihat kejadian yang relevan dengan Anda — perubahan status permintaan, BAST, atau peringatan stok — tanpa harus membuka halaman terkait satu per satu.

Langkah:
1. Tekan ikon lonceng pada bilah atas.
2. Tekan sebuah notifikasi untuk membukanya (otomatis ditandai dibaca) dan diarahkan ke halaman terkait.
3. Untuk menyingkirkan satu notifikasi dari panel tanpa membukanya, tekan opsi sembunyikan pada notifikasi itu.

[GAMBAR: G-NOTIF-01-1 | Panel lonceng notifikasi terbuka dengan beberapa entri]

> **Catatan:** notifikasi yang disembunyikan **tidak terhapus** — barisnya tetap tersimpan (mis. untuk ditelusuri Admin Sistem lewat Riwayat Pengiriman Notifikasi), hanya disingkirkan dari panel lonceng Anda. Tidak ada cara menghapus notifikasi secara permanen dari lonceng.

## Notifikasi WhatsApp {#S-NOTIF-02}

Selain notifikasi dalam aplikasi, kejadian yang sama juga dikirim lewat WhatsApp ke nomor yang terdaftar pada akun penerima — termasuk nomor akun Tim sendiri (bukan nomor Ketua Tim) bagi pemberitahuan yang ditujukan kepada Tim. Pengiriman ini bergantung pada nomor WhatsApp yang sudah diisi ([[T-MULAI-03]]); akun tanpa nomor tidak menerima kanal ini, hanya notifikasi dalam aplikasi.

> **Catatan:** Admin Sistem dapat menyalakan **mode peragaan** ([[T-ADM-04]]) yang membelokkan seluruh notifikasi WhatsApp ke satu nomor uji — berguna untuk mencoba alur tanpa mengirim pesan sungguhan ke siapa pun.

### Memeriksa dan mengirim ulang notifikasi yang gagal {#T-NOTIF-02}

**Peran:** Admin Sistem

Fungsi: memeriksa status pengiriman tiap notifikasi WhatsApp (Menunggu/Terkirim/Gagal beserta alasannya) dan mengirim ulang yang gagal.

Langkah:
1. Buka **Riwayat**, pilih jenis **Riwayat Pengiriman Notifikasi**.
2. Periksa kolom **Status Kirim** dan alasan kegagalannya bila ada.
3. Untuk satu notifikasi: tekan [Kirim Ulang] pada barisnya. Untuk semua yang gagal sekaligus: tekan [Kirim Ulang yang Gagal].

[GAMBAR: G-NOTIF-02-1 | Halaman Riwayat, jenis Notifikasi, dengan kolom Status Kirim]

> **Hasil:** "Notifikasi dikembalikan ke antrean."

## Batas Waktu Tiap Tahap {#S-NOTIF-03}

Lima dari enam tahap alur permintaan barang punya batas waktu (dalam jam), diatur Admin Sistem lewat **Pengaturan → Batas Waktu Alur** ([[T-ADM-03]]): Persetujuan Ketua Tim, Verifikasi stok fisik, Persetujuan akhir Kasubbag, Penyiapan barang, dan Pengambilan oleh pemohon. Tahap Pengesahan (tahap 6) tidak punya batas waktu.

Apa artinya batas waktu bagi Anda:

- Selama sebuah permintaan berada dalam salah satu dari lima tahap berbatas waktu, **stok barangnya tetap terkunci** — tidak dapat diambil permintaan lain.
- Bila batas waktu tahap yang sedang berjalan terlewati tanpa tindak lanjut, permintaan otomatis berubah status menjadi **Kedaluwarsa**, stok yang terkunci dilepaskan kembali, dan permintaan berpindah ke Riwayat.
- Pemohon (Ketua Tim dan akun Tim tim tersebut) menerima notifikasi "Permintaan kedaluwarsa — {kode} melewati batas waktu dan stok yang dikunci telah dilepaskan.", baik dalam aplikasi maupun WhatsApp.
- Permintaan yang kedaluwarsa dapat **diajukan kembali kapan saja** lewat Katalog Barang — tidak ada batasan jumlah pengajuan ulang.

> **Catatan:** pemeriksaan kedaluwarsa berjalan otomatis setiap kali ada yang membuka panel SIMPBI (bukan hanya lewat penjadwal terpusat), sehingga permintaan yang lewat batas waktu akan tertandai kedaluwarsa segera setelah ada aktivitas di sistem, sekalipun tidak ada seorang pun yang membuka halaman permintaan itu sendiri.
