# Ketua Tim {#B-KT}

Sebagai Ketua Tim, Andalah penjaga gerbang pertama pada setiap permintaan barang anggota tim Anda — permintaan tidak berjalan ke Petugas Gudang sebelum Anda menyetujuinya. Anda juga pihak yang tanda tangannya melekat pada dokumen bukti permintaan dan BAST tim Anda, apa pun akun yang menekan tombol konfirmasinya.

> **Yang dapat Anda lakukan:** mengajukan permintaan barang atas nama tim, menyetujui/menolak permintaan anggota tim Anda (tahap 1), mengonfirmasi penerimaan barang (tahap 5), mengonfirmasi penerimaan BAST yang tim Anda menjadi tujuannya, melihat Mutasi Aset dan Aset Tetap Tim Saya, melihat Riwayat tim Anda (termasuk Riwayat Mutasi Aset).
>
> **Yang tidak dapat Anda lakukan:** memverifikasi stok fisik atau menyiapkan barang (wewenang Petugas Gudang), memberi persetujuan akhir atau mengesahkan (wewenang Kasubbag Umum), membuat BAST, melihat data tim lain, mengubah data induk sistem.

## Mengajukan Permintaan Barang {#S-KT-01}

Langkah menambahkan barang dan mengajukan permintaan sama persis dengan akun Tim: lihat [[T-TIM-01]] dan [[T-TIM-02]].

> **Catatan:** bila **Anda sendiri** yang mengajukan permintaan (bukan anggota tim lewat akun Tim), tahap [[T-KT-01]] (Persetujuan Ketua Tim) dilewati otomatis — permintaan Anda langsung berstatus **Menunggu Verifikasi Gudang**, sebab tidak masuk akal meminta persetujuan dari diri sendiri.

## Menyetujui atau Menolak Permintaan (Tahap 1) {#S-KT-02}

### Menyetujui permintaan {#T-KT-01}

**Peran:** Ketua Tim (tim sendiri)

Fungsi: memberi persetujuan pertama atas permintaan yang diajukan anggota tim Anda (lewat akun Tim), sebelum diteruskan ke Petugas Gudang.

Prasyarat: permintaan berstatus **Menunggu Persetujuan Ketua Tim**.

Langkah:
1. Pada halaman **Permintaan Barang**, buka baris permintaan yang berstatus Menunggu Ketua Tim.
2. Tekan [Setujui].
3. Isi **Catatan** bila perlu (opsional).
4. Tekan [Setujui] pada dialog.

[GAMBAR: G-KT-01-1 | Dialog Setujui Permintaan dengan catatan opsional]

> **Contoh:** permintaan 10 rim kertas HVS dari Tim Statistik Sosial (lihat [[T-TIM-02]]) muncul di daftar Anda berstatus Menunggu Ketua Tim. Anda menekan [Setujui] tanpa catatan tambahan.

> **Hasil:** "Permintaan akan diteruskan kepada Petugas Gudang untuk verifikasi ketersediaan fisik." Status berubah menjadi **Menunggu Verifikasi Gudang**.

### Menolak permintaan {#T-KT-02}

**Peran:** Ketua Tim (tim sendiri)

Prasyarat: permintaan berstatus **Menunggu Persetujuan Ketua Tim**.

Langkah:
1. Pada baris permintaan, tekan [Tolak].
2. Isi **Alasan Penolakan** (wajib).
3. Tekan [Tolak] pada dialog.

> **Perhatian:** stok yang sudah dikunci sejak pengajuan langsung dilepaskan kembali begitu permintaan ditolak — tidak dapat dibatalkan.

> **Hasil:** status permintaan menjadi **Ditolak Ketua Tim**, dan permintaan berpindah ke Riwayat.

## Mengonfirmasi Penerimaan Barang (Tahap 5) {#S-KT-03}

Langkahnya sama dengan akun Tim, kecuali kolom konfirmasinya: lihat [[T-TIM-03]] — sebagai Ketua Tim, Anda mengetik **NIP Anda sendiri** (bukan nama tim) pada kolom "Ketik NIP Anda untuk mengonfirmasi".

## Mengonfirmasi Penerimaan BAST {#S-KT-04}

### Mengonfirmasi penerimaan aset mutasi {#T-KT-03}

**Peran:** Ketua Tim (tim tujuan BAST)

Fungsi: menyatakan aset dari BAST mutasi sudah diterima tim Anda, sebagai pihak penerima.

Prasyarat: tanda tangan Anda sudah terdaftar ([[T-MULAI-03]]); BAST berstatus **Menunggu Konfirmasi** (sudah disahkan Kasubbag Umum) dan tim tujuannya adalah tim Anda.

Langkah:
1. Pada halaman **Mutasi Aset**, buka baris BAST berstatus Menunggu Konfirmasi dengan tim tujuan Anda.
2. Tekan [Konfirmasi Penerimaan].
3. Baca deskripsi konfirmasi, lalu tekan [Konfirmasi Penerimaan] pada dialog.

[GAMBAR: G-KT-03-1 | Dialog Konfirmasi Penerimaan BAST]

> **Catatan:** Anda tidak menggambar tanda tangan lagi di sini — tanda tangan yang sudah terdaftar di akun Anda otomatis dibubuhkan pada BAST sebagai pihak penerima.

> Bila tanda tangan Anda belum terdaftar: "Tanda tangan belum tersedia — Lengkapi tanda tangan Anda melalui pelengkapan akun sebelum mengkonfirmasi penerimaan aset."

> **Hasil:** BAST berstatus **Selesai Administratif**. Barang tercatat berpindah penempatan ke tim Anda, dan dapat dilihat pada [[T-UMUM-06]] (Aset Tetap Tim Saya).

## Tugas Lain {#S-KT-05}

Melihat Riwayat, mengunduh bukti permintaan dan BAST, melihat daftar Mutasi Aset dan Aset Tetap Tim Saya: lihat [[B-UMUM]].
