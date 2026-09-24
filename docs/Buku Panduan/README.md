# Buku Panduan Pengguna SIMPBI — Bahan Penyusunan

Folder ini berisi **isi dan bahan mentah** untuk Buku Panduan Pengguna SIMPBI (Markdown, daftar tangkap layar, skenario pengambilan gambar, token tema) — bukan dokumen Word/PDF jadi. Penyusunan menjadi dokumen akhir dilakukan pihak lain.

## Struktur Folder {#R-01}

```
docs/Buku Panduan/
├── Operating Manual SIMAN 2 - Modul Evaluasi Kinerja 2025.pdf   (referensi pola, lihat catatan di bawah)
├── sumber/                    satu berkas Markdown per bab (00–12)
├── fakta-sistem.md            sumber kebenaran setiap klaim pada buku
├── daftar-tangkap-layar.md    daftar bercek 27 gambar yang perlu diambil
├── skenario-pengambilan.md    urutan pengambilan gambar, akun uji, data uji
├── tema-simpbi.md             token warna/font/radius untuk tata letak dokumen akhir
├── laporan-verifikasi.md      hasil uji langkah dan selisih terhadap catatan pemilik
└── README.md                  berkas ini
```

> **Catatan penempatan berkas referensi:** instruksi penyusunan awal meminta berkas PDF SIMAN ditempatkan di sub-folder `referensi/`. Berkas ini sudah ada di `docs/Buku Panduan/` (bukan di dalam `referensi/`) sebelum penyusunan dimulai, dan instruksi yang sama secara tegas melarang memindahkannya. Berkas dibiarkan di lokasi asalnya; penyusun dokumen akhir cukup tahu bahwa referensi polanya ada di folder ini, bukan di sub-folder terpisah.

`docs/Buku Panduan/` tidak berisi sisa pekerjaan penyusunan sebelumnya — sebelum penyusunan ini, folder hanya berisi berkas PDF referensi di atas. Tidak ada yang digantikan.

## Pola Referensi SIMAN (Ringkasan) {#R-02}

Diadopsi dari `Operating Manual SIMAN 2 - Modul Evaluasi Kinerja 2025.pdf`, **hanya pola susunan dan gaya**, bukan istilah atau isinya:
- Sampul, daftar isi bernomor, bab Pendahuluan di awal.
- Tiap tugas diawali "fungsi utama" (siapa pelakunya, apa tujuannya), lalu langkah bernomor.
- Nama tombol/tab/menu dalam kurung siku, mis. [Tambah].
- Tangkapan layar di bawah langkah, kotak merah pada elemen yang dimaksud.
- Langkah terakhir tiap tugas berupa hasil ("berhasil ...").
- **Kekurangan yang diperbaiki di buku ini:** langkah tambah/ubah/hapus yang identik tidak diulang per bab — ditulis sekali di [[B-UMUM]] dan dirujuk; disusun **per peran** (bukan per modul), mengikuti kebutuhan pembaca yang biasanya hanya satu peran; salah ketik dan istilah tidak konsisten dihindari lewat satu glosarium tunggal ([[B-LAMPIRAN]]).

## Konvensi Markdown {#R-03}

Lihat `sumber/00-informasi-dokumen.md` dan bab mana pun untuk contoh langsung. Ringkasan:
- `#` bab, `##` subbab, `###` tugas — **tidak** dinomori manual; ID stabil ditulis `{#B-...}`, `{#S-...}`, `{#T-...}`.
- Rujukan silang `[[ID]]`; gambar `[GAMBAR: G-<peran>-<tugas>-<urut> | keterangan]`.
- Kotak `> **Catatan:**`, `> **Perhatian:**`, `> **Hasil:**`; baris `**Peran:** ...`.
- Nama tombol/tab/menu dalam kurung siku.

## Urutan Bab Peran (04–08) {#R-04}

Tim → Ketua Tim → Petugas Gudang → Kasubbag Umum → Admin Sistem. Urutan ini mengikuti **posisi tiap peran dalam alur permintaan barang** yang sesungguhnya di kode: Tim/Ketua Tim mengawali (pengajuan, tahap 1, tahap 5), Petugas Gudang memproses tengah alur (tahap 2, tahap 4), Kasubbag Umum mengunci dua ujung alur (tahap 3, tahap 6) sekaligus mengesahkan BAST. Admin Sistem ditulis terakhir karena secara eksplisit **tidak berpartisipasi** dalam alur operasional ini (`app/Support/Onboarding.php`, komentar kelas: "Admin Sistem tidak menjalankan alur operasional") — perannya murni pengelolaan data induk dan pengaturan sistem.

## Cara Menambah atau Mengubah Isi {#R-05}

1. Ubah/tambah berkas di `sumber/` mengikuti konvensi di atas. Setiap kalimat baru harus tertelusur ke `fakta-sistem.md` — tambahkan entri baru di sana lebih dulu bila fakta belum tercatat, lengkap dengan rujukan berkas:baris kode.
2. Tambah/ubah gambar: tambahkan entri baru di `daftar-tangkap-layar.md` dan langkah pengambilannya di `skenario-pengambilan.md`, jaga ID gambar tetap sinkron dengan penanda `[GAMBAR: ...]` pada bab.
3. Sebelum menyerahkan ke penyusun dokumen akhir, jalankan pemeriksaan:
   - Jumlah `[GAMBAR: ...]` pada seluruh `sumber/*.md` = jumlah entri `daftar-tangkap-layar.md`.
   - Semua `[[ID]]` merujuk ID `{#...}` yang benar-benar ada (tidak ada tautan mati).
   - Tidak ada penomoran bab/subbab/tugas manual yang tertulis di teks.
4. Perubahan pada kode aplikasi **tidak** mengubah berkas di folder ini secara otomatis — sinkronkan manual dan perbarui rujukan pada `fakta-sistem.md` bila perilaku aplikasi berubah di kemudian hari.
