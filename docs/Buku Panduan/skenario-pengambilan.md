# Skenario Pengambilan Gambar

Skenario ini menuntun pengambilan seluruh gambar pada `daftar-tangkap-layar.md` secara berurutan, memakai satu kisah uji yang sama dengan buku: permintaan 10 rim Kertas HVS A4 oleh Tim Statistik Sosial, dan satu BAST mutasi Lemari Arsip dari Tim Keuangan ke Tim Statistik Sosial.

## Persiapan {#K-00}

1. Siapkan salinan basis data pengembangan (bukan basis data kerja) dengan lima akun uji: `admin@bps.go.id`, `kasubbag@bps.go.id`, `probo@bps.go.id` (Petugas Gudang), `wanda.pribadi@bps.go.id` (Ketua Tim, Tim Statistik Sosial), `tim01@bps.go.id` (Tim, Tim Statistik Sosial) — sesuai akun audit yang tercatat di `docs/audit/matriks-akses.md`.
2. Pastikan akun Petugas Gudang dan Ketua Tim uji sudah melengkapi akun (nomor WhatsApp + tanda tangan tersimpan), **kecuali** bila sengaja menyiapkan satu akun terpisah yang BELUM lengkap, khusus untuk mengambil [[G-MULAI-03-1]].
3. Pastikan barang "Kertas HVS A4" ada di katalog dengan stok fisik mencukupi (≥ 10, sisakan cukup untuk hasil verifikasi 8).
4. Pastikan aset tetap uji "Lemari Arsip" sudah ditempatkan di Tim Keuangan (untuk skenario BAST).
5. **Nyalakan mode peragaan** (Pengaturan → kolom (mode peragaan), akun Admin Sistem) dengan nomor uji sebelum memulai, agar notifikasi WhatsApp yang terpicu sepanjang skenario ini **tidak** terkirim ke nomor sungguhan siapa pun — termasuk nomor WhatsApp akun Tim uji sendiri.

## Adegan Lintas Akun — Alur Permintaan Barang {#K-01}

1. **[Tim]** masuk sebagai `tim01@bps.go.id` → Katalog Barang → tambah 10 rim Kertas HVS A4 ke keranjang → ambil [[G-TIM-01-1]] → buka Detail Keranjang, isi Nama Pemohon "Tim Statistik Sosial" → ambil [[G-TIM-02-1]] → tekan Ajukan Permintaan.
2. **[Ketua Tim]** masuk sebagai `wanda.pribadi@bps.go.id` → Permintaan Barang → buka baris permintaan baru (status Menunggu Ketua Tim) → ambil [[G-KT-01-1]] → tekan Setujui.
3. **[Petugas Gudang]** masuk sebagai `probo@bps.go.id` → Permintaan Barang → buka baris (status Menunggu Gudang) → isi Hasil Pengecekan "8" → ambil [[G-GUD-01-1]] → simpan.
4. **[Kasubbag Umum]** masuk sebagai `kasubbag@bps.go.id` → Permintaan Barang → buka baris (status Menunggu Kasubbag Umum) → isi Disetujui "8" → ambil [[G-KAS-01-1]] → tekan Setujui.
5. **[Petugas Gudang]** kembali → buka baris (status Siap Diproses) → ambil [[G-GUD-02-1]] → tekan Barang Siap Diambil.
6. **[Tim]** kembali sebagai `tim01@bps.go.id` → buka baris (status Siap Diambil) → ambil [[G-TIM-03-1]] → tekan Konfirmasi.
7. **[Kasubbag Umum]** kembali → buka baris (status Menunggu Pengesahan) → ambil [[G-KAS-03-1]] → tekan Sahkan.

> **Hasil akhir adegan:** permintaan berstatus Selesai, dokumen bukti dapat diunduh. Ambil [[G-UMUM-01-1]] pada halaman Riwayat (akun Kasubbag) menampilkan baris permintaan ini.

## Adegan Tambahan — Mutasi Aset (BAST) {#K-02}

1. **[Kasubbag Umum atau Admin Sistem]** → Aset Tetap → pastikan aset "Lemari Arsip" berpenempatan Tim Keuangan; bila belum, tempatkan lebih dulu pada aset lain yang masih kosong dan ambil [[G-KAS-06-1]] pada aset itu (penempatan awal).
2. **[Petugas Gudang]** → Mutasi Aset → Buat BAST, pilih aset Lemari Arsip, Tim Tujuan "Tim Statistik Sosial" → ambil [[G-GUD-05-1]] → simpan.
3. **[Kasubbag Umum]** → Mutasi Aset → buka baris BAST baru (status Menunggu Pengesahan) → ambil [[G-KAS-04-1]] → tekan Sahkan.
4. **[Ketua Tim]** masuk sebagai `wanda.pribadi@bps.go.id` → Mutasi Aset → buka baris BAST (status Menunggu Konfirmasi) → ambil [[G-KT-03-1]] → tekan Konfirmasi Penerimaan.
5. **[Tim/Ketua Tim]** → Aset Tetap Tim Saya → ambil [[G-UMUM-06-1]] menampilkan Lemari Arsip sudah berpindah.

## Adegan Tambahan — Pengaturan dan Pusat Bantuan {#K-03}

1. **[Petugas Gudang]** → Pengaturan → ambil [[G-MULAI-06-1]].
2. **[Admin Sistem]** → Pengguna → Tambah → isi data fiktif → ambil [[G-ADM-01-1]].
3. **[Admin Sistem]** → Pengaturan → bagian Batas Waktu Alur → ambil [[G-ADM-03-1]].
4. **[Admin Sistem]** → Pengaturan → isi kolom (mode peragaan) dengan nomor lain (mengubah nomor yang sudah menyala sejak [[K-00]] langkah 5) → ambil [[G-ADM-04-1]] (dialog "Ubah nomor mode peragaan?").
5. **[akun bebas]** → avatar → Pusat Bantuan → ambil [[G-MULAI-08-1]].
6. **[akun bebas]** → avatar → Keluar → ambil [[G-MULAI-09-1]] → batalkan (jangan benar-benar keluar bila skenario belum selesai).

## Adegan Tambahan — Notifikasi {#K-04}

1. **[Ketua Tim]** → bilah atas → lonceng → ambil [[G-NOTIF-01-1]] (notifikasi dari adegan [[K-01]] seharusnya sudah muncul di sini).
2. **[Admin Sistem]** → Riwayat → jenis Notifikasi → ambil [[G-NOTIF-02-1]] (baris pengiriman WhatsApp dari seluruh adegan di atas, seluruhnya berkanal ke nomor mode peragaan berkat [[K-00]] langkah 5).

## Adegan Tambahan — Keadaan Kosong dan Dasbor {#K-05}

1. **[Kasubbag Umum]** → Dasbor → ambil [[G-MULAI-04-1]].
2. **[Petugas Gudang]** → Stok Masuk → Catat Stok Masuk → ambil [[G-GUD-03-1]] → simpan.
3. **[Petugas Gudang atau Kasubbag Umum]** → Kartu Kendali → ambil [[G-GUD-04-1]] (menampilkan pergerakan dari langkah 2 di atas).
4. **[akun baru belum lengkap]** → masuk pertama kali sebagai Petugas Gudang/Ketua Tim uji yang sengaja belum melengkapi akun → ambil [[G-MULAI-03-1]] pada wizard Lengkapi Akun, tahap Tanda Tangan.

## Penutup {#K-06}

Setelah seluruh gambar terambil: **matikan mode peragaan** (kosongkan kembali kolom (mode peragaan) di Pengaturan, akun Admin Sistem) dan kembalikan data uji ke keadaan semula bila salinan basis data ini akan dipakai ulang untuk keperluan lain.
