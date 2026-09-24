# Laporan Verifikasi

Metode: pembacaan langsung kode aplikasi (kelas Filament, model, service, view Blade, migrasi) pada commit `35a1a7a`. Tidak dilakukan penelusuran peramban headless pada aplikasi berjalan untuk penyusunan draf ini — seluruh verifikasi bersumber dari kode, yang untuk sistem ini terbukti sangat tertelusur (label, pesan galat, dan kondisi visibilitas ditulis eksplisit di kelas Filament, bukan disusun dinamis). Bagian yang tetap tidak pasti hanya dari kode ditandai [PERLU KONFIRMASI] pada bab terkait.

## Tabel 1 — Uji Langkah: Bagian Buku vs. Kode {#V-01}

| Bagian Buku | Elemen | Cocok? | Tindakan |
|---|---|---|---|
| [[B-PEND]] §alur 6 tahap | Urutan status `PermintaanBarang::STATUS` | Cocok | — |
| [[T-TIM-02]] | Label kolom formulir Ajukan Permintaan | Cocok (`KatalogBarang.php:157-175`) | — |
| [[T-KT-01]]/[[T-KT-02]] | Label tombol, judul dialog, deskripsi tahap 1 | Cocok (`PermintaanBarangResource.php:319-356`) | — |
| [[T-GUD-01]] | Label kolom Verifikasi (Hasil Pengecekan, Kondisi) | Cocok (`PermintaanBarangResource.php:383-408`) | — |
| [[T-GUD-02]] | Label tombol "Barang Siap Diambil" (bukan "Siapkan") | **Diperbaiki**: draf awal sempat memakai label singkat "Siapkan" dari kolom internal `TindakanPermintaan`; buku final memakai label tombol sesungguhnya "Barang Siap Diambil" (`PermintaanBarangResource.php:510`) | Sudah dikoreksi sebelum ditulis ke bab |
| [[T-KAS-01]] | Pesan validasi jumlah disetujui | Cocok (`PermintaanBarangResource.php:470`) | — |
| [[T-KAS-03]] | Deskripsi modal Pengesahan | Cocok (`PermintaanBarangResource.php:625`) | — |
| [[S-KAS-03]] | Status BAST 3 tahap (bukan 2) | Ditemukan lebih detail dari dugaan awal: migrasi menunjukkan `menunggu_konfirmasi` sebagai status antara yang eksplisit, bukan hanya "disahkan" | Buku ditulis dengan 3 status BAST |
| [[T-KAS-06]]/[[S-UMUM-05]] | Penguncian kolom Tim Kerja pada Aset Tetap | Cocok (`AsetTetapForm.php:46-61`) | — |
| [[T-ADM-04]] | Judul/isi dialog mode peragaan | Cocok (`Pengaturan.php:505-522`) | — |
| [[B-NOTIF]] §batas waktu | 5 dari 6 tahap berbatas waktu | Cocok (`Pengaturan.php:102-106`) | — |
| [[S-MULAI-03]]/[[B-KONTAK]] | Pesan otomatis Pusat Bantuan | Cocok (`PusatBantuan.php:123-126`, hasil perubahan sesi kerja yang sama) | — |
| [[T-MULAI-01]] | Halaman masuk: WA button pesan otomatis | **Tidak cocok dengan anggapan awal** — lihat Tabel 2, butir 19 | Buku ditulis mengikuti kode: kedua tombol punya pesan otomatis, teksnya berbeda |
| [[B-LAMPIRAN]] §Tabel Peran | Kemampuan per fitur | Cocok dengan `docs/audit/matriks-akses.md` Tabel 2–5, tidak ada baris yang berubah sejak audit terakhir | — |
| [[B-LAMPIRAN]] §Glosarium | "Tim Kerja", "e-TTD", "Ekspor", "Kasubbag Umum" | Cocok, tidak ada sisa istilah lama | — |

## Tabel 2 — Selisih terhadap Bagian E (Catatan Ingatan Pemilik) {#V-02}

| # | Butir E | Hasil verifikasi | Selisih |
|---|---|---|---|
| 1 | 5 peran | **Cocok** | — |
| 2 | Alur 6 tahap, 5 berbatas waktu | **Cocok** | — |
| 3 | NIP vs nama tim saat konfirmasi | **Cocok**, dengan tambahan detail: non-tim juga punya fallback "ketik nama lengkap" bila NIP kosong (tidak disebut di catatan E, tidak bertentangan — hanya lebih detail) | Buku menyebutkan fallback ini |
| 4 | Tanda tangan selalu milik Ketua Tim | **Cocok** | — |
| 5 | Akun Tim punya nomor WA sendiri | **Cocok** | — |
| 6 | Nama/NIP/Email terkunci semua peran | **Cocok** | — |
| 7 | Dialog mode peragaan hanya Admin | **Cocok** | — |
| 8 | Ganti sandi: tetap masuk, beda dari lama, sesi lain putus | **Cocok** | — |
| 9 | Admin tidak bisa nonaktifkan/hapus diri sendiri / nol Admin | **Cocok** | — |
| 10 | Mutasi Aset: Tim Asal otomatis+terkunci, blokir penempatan kosong/BAST menunggu, Unduh muncul setelah disahkan | **Cocok**, dengan detail tambahan: BAST punya status ketiga `menunggu_konfirmasi` di antara pengesahan dan selesai (tidak disebut E, tidak bertentangan) | Buku menjelaskan 3 status BAST |
| 11 | Aset Tetap: penempatan sekali+terkunci, sinkronisasi selalu terkunci | **Cocok** | — |
| 12 | Stok Terkunci hanya tampilan | **Cocok** | — |
| 13 | Kasubbag: jumlah disetujui ≤ diminta | **Cocok** | — |
| 14 | Permintaan kedaluwarsa mengirim notifikasi ke pemohon | **Cocok** | — |
| 15 | Dokumen di disk privat | **Cocok** | — |
| 16 | Pusat Bantuan: 4 kartu, WA dengan pesan "Halo! Saya sedang butuh bantuan." | **Cocok** | — |
| 17 | Header: tanpa pencarian, toggle tema di sebelah lonceng | **Cocok** | — |
| 18 | Dropdown profil: identitas bukan tombol, 2 menu, dialog keluar | **Cocok**, teks dialog persis "Keluar dari akun?" dikonfirmasi | — |
| 19 | Halaman masuk: pemuatan, animasi alur, Caps Lock, **tombol WA TANPA pesan otomatis** | **TIDAK COCOK sebagian** — lihat rincian di bawah | Buku dikoreksi mengikuti kode |
| 20 | Istilah baku | **Cocok** | — |
| 21 | Ekspor dari Riwayat & Kartu Kendali, tanpa menu Laporan | **Cocok** | — |
| 22 | Notifikasi bisa disembunyikan, tidak bisa dihapus | **Cocok** | — |

### Rincian selisih butir 19 {#V-03}

Catatan pemilik menyebut tombol bantuan WhatsApp pada halaman masuk **tanpa** pesan otomatis, "beda dari tombol di Pusat Bantuan, yang kini memakainya". Pembacaan kode menunjukkan sebaliknya:

- `resources/views/filament/auth/two-panel.blade.php:31` memanggil `KontakBantuan::tautanWhatsApp()` **tanpa argumen**.
- `KontakBantuan::tautanWhatsApp(?string $pesan = 'Halo, saya mengalami kendala masuk ke SIMPBI dan memerlukan bantuan.')` (`app/Support/KontakBantuan.php:45-46`) memakai pesan bawaan itu bila `$pesan` tidak diisi.
- Jadi tautan WhatsApp halaman masuk **sudah** membawa pesan otomatis "Halo, saya mengalami kendala masuk ke SIMPBI dan memerlukan bantuan." — sejak sebelum perubahan Pusat Bantuan pada sesi kerja yang sama, bukan baru.
- Dikonfirmasi oleh `tests/Feature/KontakBantuanMasukTest.php:30-37`, yang secara eksplisit menguji bahwa tautan halaman masuk berawalan `...?text=` (bukan tautan polos tanpa parameter teks).

Kesimpulan: **kedua** tombol bantuan WhatsApp (halaman masuk dan Pusat Bantuan) membawa pesan otomatis, hanya **teksnya berbeda**. Buku ([[T-MULAI-01]], [[S-MULAI-03]], [[B-KONTAK]]) ditulis mengikuti fakta ini, bukan mengikuti catatan ingatan pemilik. Bagian A dari prompt kerja yang menghasilkan buku ini secara eksplisit meminta perbedaan semacam ini dicatat dan diikuti kode — sudah dilakukan.

## Daftar [PERLU KONFIRMASI] {#V-04}

1. **[[S-KONTAK-02]]** — alamat email Sub-Bagian Umum `sub-bagianumum@bps.go.id` diambil dari `config/pusat_bantuan.php`; belum dapat dipastikan dari kode saja apakah ini alamat resmi yang sudah aktif atau nilai bawaan konfigurasi yang belum diperbarui pemilik sistem.
2. **[[L-WARNA]]** (tema-simpbi.md) — nilai hex persis untuk warna status semantik (success/warning/danger) tidak ditulis ulang sebagai token SIMPBI khusus di `theme.css`; mengikuti palet bawaan Filament terpasang. Penyusun dokumen akhir disarankan mengambilnya langsung dari tangkapan layar, bukan dari daftar token ini.
3. **[[L-KOMPONEN]]** (tema-simpbi.md) — nilai persis bayangan (box-shadow) tombol/kartu tidak diringkas sebagai token tunggal, sebab bervariasi per komponen dan mengikuti bawaan Filament.
