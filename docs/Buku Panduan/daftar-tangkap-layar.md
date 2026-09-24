# Daftar Tangkap Layar

Aturan pengambilan gambar untuk seluruh entri di bawah ini, kecuali disebutkan lain per entri:

- Format **PNG**, lebar jendela peramban **1440px**, tanpa bilah alamat/bilah tab peramban ikut tertangkap.
- Gambar dipangkas (crop) pada area yang relevan dengan langkah yang dijelaskan — tidak perlu selalu tangkapan layar penuh.
- Memakai akun dan data uji pada **salinan basis data pengembangan** (bukan basis data kerja), dengan data yang jelas fiktif (mengikuti kisah "permintaan kertas HVS oleh Tim Statistik Sosial" pada buku).
- Elemen yang menjadi fokus langkah ditandai **kotak merah tebal**; bila lebih dari satu elemen ditandai pada satu gambar, dipakai **lingkaran bernomor** (1)(2)(3) sesuai urutan langkah yang merujuknya.
- Samarkan NIP, nomor WhatsApp, dan alamat email **asli** bila secara tidak sengaja terekam di luar data uji (mis. pada baris lain yang ikut tertangkap); data uji fiktif tidak perlu disamarkan.
- Nama berkas gambar = ID gambar, dengan akhiran `.png` (mis. `G-MULAI-01-1.png`).

Akun uji yang dipakai (salinan basis data pengembangan, sesuai `docs/audit/matriks-akses.md`): `admin@bps.go.id` (Admin Sistem), `kasubbag@bps.go.id` (Kasubbag Umum), `probo@bps.go.id` (Petugas Gudang), `wanda.pribadi@bps.go.id` (Ketua Tim, Tim Statistik Sosial), `tim01@bps.go.id` (Tim, Tim Statistik Sosial).

Jumlah entri pada daftar ini (27) sama dengan jumlah penanda `[GAMBAR: ...]` pada seluruh bab `sumber/`.

## Umum / Sebelum Masuk

- [ ] **G-MULAI-01-1** — Halaman masuk SIMPBI, panel kiri navy dan formulir masuk
  - Akun/peran: tidak ada (belum masuk)
  - Jalur/URL: `/admin/login`
  - Kondisi data: tidak perlu data khusus
  - Langkah: buka alamat panel di peramban, tunggu layar pemuatan selesai
  - Elemen ditandai: tidak perlu kotak merah — gambar utuh sebagai ilustrasi tata letak

## Akun Tim (`tim01@bps.go.id`)

- [ ] **G-TIM-01-1** — Katalog Barang, dialog Tambah jumlah
  - Jalur/URL: sidebar **Katalog Barang**
  - Kondisi data: barang "Kertas HVS A4" tersedia dengan stok ≥ 10
  - Langkah: tekan [Tambah] pada baris Kertas HVS A4
  - Elemen ditandai: (1) kolom Jumlah Diminta, (2) tombol konfirmasi dialog

- [ ] **G-TIM-02-1** — Dialog Lihat Keranjang dan Ajukan Permintaan, terisi kertas HVS
  - Jalur/URL: Katalog Barang → [Detail Keranjang]
  - Kondisi data: keranjang berisi 10 rim Kertas HVS A4
  - Langkah: buka Detail Keranjang setelah [[G-TIM-01-1]]
  - Elemen ditandai: (1) kolom Nama Pemohon, (2) tombol [Ajukan Permintaan]

- [ ] **G-TIM-03-1** — Dialog Konfirmasi Penerimaan, kolom nama tim
  - Jalur/URL: **Permintaan Barang** → baris berstatus Siap Diambil → [Konfirmasi]
  - Kondisi data: permintaan uji sudah melewati tahap 1–4 (lihat `skenario-pengambilan.md`)
  - Langkah: tekan [Konfirmasi] pada baris permintaan
  - Elemen ditandai: kolom "Ketik nama tim Anda untuk konfirmasi"

## Akun Ketua Tim (`wanda.pribadi@bps.go.id`)

- [ ] **G-KT-01-1** — Dialog Setujui Permintaan, catatan opsional
  - Jalur/URL: **Permintaan Barang** → baris berstatus Menunggu Ketua Tim → [Setujui]
  - Kondisi data: permintaan Kertas HVS dari [[G-TIM-02-1]] belum diproses
  - Langkah: tekan [Setujui]
  - Elemen ditandai: tombol [Setujui] pada dialog

- [ ] **G-KT-03-1** — Dialog Konfirmasi Penerimaan BAST
  - Jalur/URL: **Mutasi Aset** → baris BAST berstatus Menunggu Konfirmasi dengan tim tujuan Statistik Sosial → [Konfirmasi Penerimaan]
  - Kondisi data: BAST uji aset "Lemari Arsip" dari Tim Keuangan ke Tim Statistik Sosial, sudah disahkan Kasubbag (lihat `skenario-pengambilan.md` adegan BAST)
  - Langkah: tekan [Konfirmasi Penerimaan]
  - Elemen ditandai: tombol [Konfirmasi Penerimaan] pada dialog

## Akun Petugas Gudang (`probo@bps.go.id`)

- [ ] **G-GUD-01-1** — Dialog Verifikasi Ketersediaan Fisik
  - Jalur/URL: **Permintaan Barang** → baris berstatus Menunggu Gudang → [Verifikasi]
  - Kondisi data: permintaan Kertas HVS sudah disetujui Ketua Tim
  - Langkah: tekan [Verifikasi], isi Hasil Pengecekan "8"
  - Elemen ditandai: (1) kolom Hasil Pengecekan, (2) kolom Kondisi

- [ ] **G-GUD-02-1** — Dialog Penyiapan Barang, pratinjau tanda tangan
  - Jalur/URL: **Permintaan Barang** → baris berstatus Siap Diproses → [Barang Siap Diambil]
  - Kondisi data: permintaan sudah disetujui akhir Kasubbag Umum; akun uji Gudang sudah punya tanda tangan tersimpan
  - Langkah: tekan [Barang Siap Diambil]
  - Elemen ditandai: (1) pratinjau tanda tangan, (2) kolom Ketik NIP Anda

- [ ] **G-GUD-03-1** — Dialog Catat Stok Masuk
  - Jalur/URL: **Stok Masuk** → [Catat Stok Masuk]
  - Kondisi data: tidak perlu data khusus
  - Langkah: tekan [Catat Stok Masuk], isi Sumber "Pembelian", Nomor Dasar contoh
  - Elemen ditandai: (1) kolom Sumber, (2) kolom Barang, (3) kolom Jumlah

- [ ] **G-GUD-04-1** — Halaman Kartu Kendali, tabel pergerakan stok
  - Jalur/URL: **Kartu Kendali**
  - Kondisi data: ada pergerakan stok pada periode berjalan (mis. hasil [[G-GUD-03-1]])
  - Langkah: buka halaman, biarkan periode bawaan
  - Elemen ditandai: kolom Masuk, Keluar, Sisa

- [ ] **G-GUD-05-1** — Formulir Buat BAST Mutasi Aset
  - Jalur/URL: **Mutasi Aset** → [Buat BAST]
  - Kondisi data: aset uji "Lemari Arsip" sudah ditempatkan di Tim Keuangan
  - Langkah: pilih aset, tim tujuan "Tim Statistik Sosial", isi Alasan Mutasi
  - Elemen ditandai: (1) kolom Tim Kerja Asal (terkunci), (2) kolom Tim Kerja Tujuan

## Akun Kasubbag Umum (`kasubbag@bps.go.id`)

- [ ] **G-KAS-01-1** — Dialog Persetujuan Akhir Permintaan, kolom Disetujui
  - Jalur/URL: **Permintaan Barang** → baris berstatus Menunggu Kasubbag Umum → [Setujui]
  - Kondisi data: permintaan Kertas HVS sudah diverifikasi Gudang (hasil cek 8)
  - Langkah: isi kolom Disetujui "8"
  - Elemen ditandai: kolom Disetujui

- [ ] **G-KAS-03-1** — Dialog Pengesahan Akhir Permintaan
  - Jalur/URL: **Permintaan Barang** → baris berstatus Menunggu Pengesahan → [Sahkan]
  - Kondisi data: permintaan sudah dikonfirmasi diterima ([[G-TIM-03-1]])
  - Langkah: isi kolom Ketik NIP Anda
  - Elemen ditandai: kolom Ketik NIP Anda, tombol [Sahkan]

- [ ] **G-KAS-04-1** — Dialog Sahkan BAST Mutasi Aset
  - Jalur/URL: **Mutasi Aset** → baris BAST Menunggu Pengesahan → [Sahkan]
  - Kondisi data: BAST uji dari [[G-GUD-05-1]]
  - Langkah: tekan [Sahkan]
  - Elemen ditandai: teks deskripsi tim tujuan pada dialog

- [ ] **G-KAS-06-1** — Formulir Ubah Aset Tetap, kolom Tim Kerja sebelum diisi
  - Jalur/URL: **Aset Tetap** → baris aset dengan Tim Kerja "Belum ditempatkan"
  - Kondisi data: aset uji baru tanpa penempatan
  - Langkah: buka formulir Ubah
  - Elemen ditandai: kolom Tim Kerja (placeholder "Belum ditempatkan")

## Akun Admin Sistem (`admin@bps.go.id`)

- [ ] **G-ADM-01-1** — Formulir Tambah Pengguna
  - Jalur/URL: **Pengguna** → [Tambah]
  - Kondisi data: tidak perlu data khusus
  - Langkah: buka formulir Tambah, isi data uji fiktif
  - Elemen ditandai: (1) kolom Peran, (2) kolom Tim Kerja
  - Catatan privasi: **wajib** memakai data fiktif pada kolom Nama/NIP/Email/No. HP — jangan gunakan data pegawai sungguhan sama sekali pada gambar ini

- [ ] **G-ADM-03-1** — Bagian Batas Waktu Alur pada Pengaturan
  - Jalur/URL: avatar → Pengaturan → bagian Batas Waktu Alur
  - Kondisi data: tidak perlu data khusus
  - Langkah: buka bagian Batas Waktu Alur
  - Elemen ditandai: kelima kolom jam tahap

- [ ] **G-ADM-04-1** — Dialog konfirmasi "Aktifkan mode peragaan?"
  - Jalur/URL: Pengaturan → kolom (mode peragaan) diisi → [Simpan Perubahan]
  - Kondisi data: kolom mode peragaan sebelumnya kosong
  - Langkah: isi nomor uji pada kolom (mode peragaan), tekan Simpan Perubahan
  - Elemen ditandai: judul dan deskripsi dialog
  - Catatan privasi: nomor uji pada gambar harus nomor fiktif, bukan nomor WhatsApp sungguhan siapa pun

## Tugas Umum (akun bebas sesuai kolom Peran pada tugasnya)

- [ ] **G-UMUM-01-1** — Halaman Riwayat, jenis Permintaan Barang, penyaring terbuka
  - Akun: `kasubbag@bps.go.id`
  - Jalur/URL: **Riwayat**, jenis Permintaan Barang
  - Kondisi data: minimal satu permintaan berstatus Selesai
  - Langkah: buka Riwayat, buka panel penyaring
  - Elemen ditandai: tombol [Ekspor]

- [ ] **G-UMUM-06-1** — Halaman Aset Tetap Tim Saya
  - Akun: `wanda.pribadi@bps.go.id`
  - Jalur/URL: **Aset Tetap Tim Saya**
  - Kondisi data: tim uji memiliki minimal satu aset tetap
  - Langkah: buka halaman
  - Elemen ditandai: kolom Mulai Penempatan

## Tugas Umum (Memulai) — akun bebas

- [ ] **G-MULAI-03-1** — Wizard Lengkapi Akun, tahap Tanda Tangan
  - Akun: akun uji baru berperan Petugas Gudang atau Ketua Tim yang belum melengkapi akun
  - Jalur/URL: dialihkan otomatis setelah masuk pertama kali
  - Kondisi data: akun belum punya tanda tangan tersimpan
  - Langkah: isi tahap WhatsApp, lanjut ke tahap Tanda Tangan
  - Elemen ditandai: kanvas gambar tanda tangan

- [ ] **G-MULAI-04-1** — Tampilan Dasbor lengkap
  - Akun: `kasubbag@bps.go.id` (widget terbanyak)
  - Jalur/URL: **Dasbor**
  - Kondisi data: data uji beragam agar widget tidak kosong
  - Langkah: buka Dasbor
  - Elemen ditandai: (1) sidebar, (2) bilah atas, (3) area widget

- [ ] **G-MULAI-06-1** — Halaman Pengaturan, bagian Akun Saya
  - Akun: `probo@bps.go.id`
  - Jalur/URL: avatar → Pengaturan
  - Langkah: buka bagian Akun Saya
  - Elemen ditandai: (1) kolom Nama/NIP/Email (terkunci), (2) kolom No. HP
  - Catatan privasi: samarkan NIP dan email pada gambar bila memakai akun uji yang kebetulan mirip data asli

- [ ] **G-MULAI-08-1** — Halaman Pusat Bantuan, keempat kartu
  - Akun: bebas
  - Jalur/URL: avatar → Pusat Bantuan
  - Langkah: buka halaman
  - Elemen ditandai: kartu WhatsApp (tombol [Chat Sekarang])

- [ ] **G-MULAI-09-1** — Dialog konfirmasi "Keluar dari akun?"
  - Akun: bebas
  - Jalur/URL: avatar → [Keluar]
  - Langkah: tekan [Keluar]
  - Elemen ditandai: tombol [Ya, Keluar]

## Notifikasi dan Batas Waktu — akun bebas

- [ ] **G-NOTIF-01-1** — Panel lonceng notifikasi terbuka
  - Akun: `wanda.pribadi@bps.go.id`
  - Jalur/URL: bilah atas → ikon lonceng
  - Kondisi data: minimal 2–3 notifikasi belum dibaca (hasil skenario alur permintaan)
  - Langkah: tekan ikon lonceng
  - Elemen ditandai: satu baris notifikasi contoh

- [ ] **G-NOTIF-02-1** — Riwayat, jenis Notifikasi, kolom Status Kirim
  - Akun: `admin@bps.go.id`
  - Jalur/URL: **Riwayat**, jenis Riwayat Pengiriman Notifikasi
  - Kondisi data: mode peragaan menyala (lihat `skenario-pengambilan.md`) agar ada baris data untuk ditangkap tanpa mengirim WhatsApp sungguhan
  - Langkah: buka Riwayat, pilih jenis Notifikasi
  - Elemen ditandai: kolom Status Kirim
