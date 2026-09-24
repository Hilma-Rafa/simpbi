# Fakta Sistem SIMPBI

Sumber kebenaran seluruh isi buku panduan. Disusun dari pembacaan langsung kode aplikasi pada commit `35a1a7a` (22 September 2026) — bukan dari dokumen rancangan atau ingatan. Setiap baris menyebut berkas sumbernya agar dapat diperiksa ulang. `docs/audit/matriks-akses.md` dipakai sebagai titik awal untuk bagian hak akses, lalu **diverifikasi ulang** terhadap kode saat ini (hasil pembandingannya: identik, tidak ada baris yang berubah sejak audit terakhir per commit ini).

## 1. Peran {#F-01}

Lima nilai kolom `role` pada tabel `users`, dengan label persis (`app/Filament/Resources/Users/Schemas/UserForm.php:17-21`):
`admin` → "Admin Sistem", `kasubbag` → "Kasubbag Umum", `petugas_gudang` → "Petugas Gudang", `ketua_tim` → "Ketua Tim", `tim` → "Tim". Tidak ada kelas enum PHP untuk peran — nilainya string biasa.

## 2. Alur Permintaan Barang {#F-02}

Status dan urutannya (`app/Models/PermintaanBarang.php:19-31`, transisi di `app/Filament/Resources/PermintaanBarangs/PermintaanBarangResource.php:819-1187`):

`menunggu_ketua` → `menunggu_verifikasi` → `menunggu_kasubbag` → `siap_diproses` → `siap_diambil` → `menunggu_pengesahan` → `selesai`. Status akhir non-normal: `ditolak_ketua`, `ditolak_kasubbag`, `bermasalah`, `kedaluwarsa` (`PermintaanBarang.php:77-83`, `STATUS_RIWAYAT`).

Kode komentar model menyebut eksplisit "alur enam tahap" (`PermintaanBarang.php:67`). Enam tahap tersebut, dengan pelaksana (`PermintaanBarangResource.php:319-637`, `app/Support/TindakanPermintaan.php:33-35`):
1. `menunggu_ketua` — Persetujuan Ketua Tim (pelaksana: `ketua_tim`)
2. `menunggu_verifikasi` — Verifikasi stok fisik (pelaksana: `petugas_gudang`)
3. `menunggu_kasubbag` — Persetujuan akhir (pelaksana: `kasubbag`)
4. `siap_diproses` — Penyiapan barang (pelaksana: `petugas_gudang`)
5. `siap_diambil` — Konfirmasi Penerimaan (pelaksana: `ketua_tim`/`tim` tim sendiri)
6. `menunggu_pengesahan` — Pengesahan (pelaksana: `kasubbag`)

**Pengecualian tahap 1:** bila pengaju permintaan berperan `ketua_tim`, tahap 1 dilewati otomatis dan status awal langsung `menunggu_verifikasi` (`app/Filament/Pages/KatalogBarang.php:257,281,302-312`); baris riwayat tambahan dicatat dengan catatan "Pengaju berperan sebagai Ketua Tim, tahap persetujuan dilewati."

Batas waktu (5 dari 6 tahap; tahap 6/Pengesahan tidak dibatasi) — kunci pengaturan dan labelnya (`app/Filament/Pages/Pengaturan.php:102-106`): `batas_ketua_jam` → "Persetujuan Ketua Tim", `batas_verifikasi_jam` → "Verifikasi stok fisik", `batas_kasubbag_jam` → "Persetujuan akhir Kasubbag", `batas_penyiapan_jam` → "Penyiapan barang", `batas_pengambilan_jam` → "Pengambilan oleh pemohon". Nilai bawaan 8 jam bila baris pengaturan hilang (`KatalogBarang.php:270-272`).

Label status lengkap dan ringkas (`PermintaanBarang.php:19-52`):

| Kunci | Label lengkap | Label ringkas (badge tabel) |
|---|---|---|
| `menunggu_ketua` | Menunggu Persetujuan Ketua Tim | Menunggu Ketua Tim |
| `menunggu_verifikasi` | Menunggu Verifikasi Gudang | Menunggu Gudang |
| `menunggu_kasubbag` | Menunggu Persetujuan akhir Kasubbag | Menunggu Kasubbag Umum |
| `siap_diproses` | Siap Diproses | (sama) |
| `siap_diambil` | Siap Diambil | (sama) |
| `menunggu_pengesahan` | Menunggu Pengesahan | (sama) |
| `selesai` | Selesai | (sama) |
| `ditolak_ketua` | Ditolak Ketua Tim | (sama) |
| `ditolak_kasubbag` | Ditolak Kasubbag | (sama) |
| `bermasalah` | Bermasalah | (sama) |
| `kedaluwarsa` | Kedaluwarsa | (sama) |

## 3. Konfirmasi Identitas Pengganti Tanda Tangan Basah {#F-03}

Kolom `konfirmasi_nip` dipakai pada tahap 4, 5, dan 6 (`PermintaanBarangResource.php:531,608,634,658-724`):
- Peran `tim`: label "Ketik nama tim Anda untuk konfirmasi", dibandingkan (tanpa peka huruf besar/kecil, spasi dirapikan) dengan `tim.nama_tim`. Galat: "Tidak cocok dengan nama tim Anda. Ketik persis nama tim Anda." Akun tanpa tim: "Akun Anda belum terhubung ke tim kerja, sehingga konfirmasi tidak dapat dilakukan. Hubungi Administrator."
- Peran lain (dipakai Petugas Gudang tahap 4, Ketua Tim tahap 5, Kasubbag tahap 6): label "Ketik NIP Anda untuk mengonfirmasi" bila `nip` terisi, atau "Ketik nama lengkap Anda untuk mengonfirmasi" bila NIP kosong (dibandingkan ke NIP atau nama). Galat: "Tidak cocok dengan data akun Anda. Ketik persis NIP (atau nama lengkap) Anda."
- Nilai yang diketik **tidak disimpan/dicetak** — hanya diperiksa.

## 4. Tanda Tangan pada Dokumen {#F-04}

- Peran yang membubuhkan tanda tangan tergambar: hanya `petugas_gudang` dan `ketua_tim` (`app/Support/Onboarding.php:26-34`, `PERAN_BERTANDA_TANGAN`). `kasubbag` memakai e-TTD (nama + kode QR), bukan gambar.
- Pada tahap 5 (Konfirmasi Penerimaan), tanda tangan yang dibubuhkan pada dokumen SELALU tanda tangan Ketua Tim yang tersimpan, sekalipun akun `tim` yang menekan tombol (`PermintaanBarangResource.php:653-656,732-738`).
- Pada tahap Konfirmasi Penerimaan BAST, tanda tangan penerima yang dibubuhkan adalah tanda tangan tersimpan Ketua Tim tim tujuan, bukan digambar ulang (`BastMutasiAsetsTable.php:114-119`).

## 5. Nomor WhatsApp per Peran {#F-05}

- Peran yang mengisi nomor WhatsApp sendiri: `tim`, `ketua_tim`, `petugas_gudang`, `kasubbag` (`Onboarding.php:37`, `PERAN_BERNOMOR_WA`); Admin tidak diwajibkan.
- Notifikasi WhatsApp dikirim ke `no_hp` milik **tiap penerima langsung**, termasuk akun `tim` sendiri (bukan nomor Ketua Tim) — penerima permintaan yang statusnya melibatkan tim pemohon selalu mencakup `['tim', 'ketua_tim']` pada tim itu (`app/Services/NotifikasiService.php:263-267`, `anggotaTim()`), dan pengiriman per baris memakai `$pengguna->no_hp` masing-masing (`NotifikasiService.php:91-137`).

## 6. Data Akun Terkunci {#F-06}

- Nama, NIP, Email pada Pengaturan → Akun Saya: `disabled()` untuk **semua peran**, termasuk Admin Sistem sendiri (`Pengaturan.php:225,233,239`). Hanya diubah Admin lewat menu Pengguna.
- Mode Peragaan: dialog konfirmasi hanya muncul bagi Admin (`bolehMengaturSistem()`) dan hanya saat mengaktifkan atau mengubah nomor pengalihan (`Pengaturan.php:505-542`, `perluKonfirmasiPeragaan()`). Judul dialog: "Aktifkan mode peragaan?" / "Ubah nomor mode peragaan?"; deskripsi menyebut nomor pegawai tidak dipakai selama nomor ini terisi.

## 7. Ganti Kata Sandi {#F-07}

`app/Filament/Pages/GantiKataSandi.php`: kata sandi baru harus berbeda dari yang lama (`aturanBerbedaDariSaatIni()`, galat "Kata sandi baru harus berbeda dari kata sandi saat ini."); sesudah diganti, hash sesi perangkat yang sedang dipakai diperbarui (`perbaruiHashSesi()`) sehingga pengguna **tetap masuk**; sesi di perangkat lain memakai hash lama dan otomatis tidak berlaku (ditegakkan `AuthenticateSession` middleware Laravel bawaan).

## 8. Perlindungan Akun Admin {#F-08}

`app/Models/User.php:74-105`, `alasanTidakDapatDihapus()`: Admin tidak dapat menghapus akunnya sendiri ("Anda tidak dapat menghapus akun Anda sendiri."), dan tidak boleh menyisakan nol Admin aktif ("Harus ada minimal satu Admin aktif.") — berlaku hapus satuan maupun massal, ditegakkan di event `deleting` model (server, bukan hanya UI). Perubahan peran/status akun sendiri juga dikunci pada form Ubah (`app/Filament/Resources/Users/Pages/EditUser.php:26-46`).

## 9. Mutasi Aset (BAST) {#F-09}

- Status: `menunggu_pengesahan` → `menunggu_konfirmasi` → `selesai_administratif` (migrasi `database/migrations/2026_08_26_000007_create_bast_mutasi_aset_table.php:20-24`). Label (`BastMutasiAsetsTable.php:19-23`): "Menunggu Pengesahan", "Menunggu Konfirmasi", "Selesai Administratif".
- `tim_asal_id` pada form Buat otomatis mengikuti `tim_penempatan_id` aset dan terkunci (`app/Filament/Resources/BastMutasiAsets/Schemas/BastMutasiAsetForm.php:36-67`).
- Aturan pembuatan (`app/Services/MutasiAsetService.php:45-61`, `pesanAsetTidakDapatDimutasi()`): aset tanpa penempatan → "Aset ini belum memiliki penempatan. Tetapkan penempatan terlebih dahulu lewat menu Aset Tetap."; aset dengan BAST lain berstatus `menunggu_pengesahan` dan `tim_asal_id` masih sama dengan penempatan sekarang → "Aset ini masih memiliki BAST yang menunggu pengesahan (...). Sahkan BAST tersebut terlebih dahulu."
- Tombol Unduh BAST hanya tampil bila `file_bast_path` **dan** `disahkan_at` terisi (`BastMutasiAsetsTable.php:180-190`) — bukan sejak BAST dibuat.
- Pengesahan (`BastMutasiAsetsTable.php:76-112`): hanya Kasubbag, status harus `menunggu_pengesahan`; galat bila penempatan berubah: "BAST tidak dapat disahkan" + alasan.
- Konfirmasi penerimaan (`BastMutasiAsetsTable.php:120-160`): hanya Ketua Tim tim tujuan, status harus `menunggu_konfirmasi`; menuntut tanda tangan tersimpan, galat bila belum ada: "Tanda tangan belum tersedia — Lengkapi tanda tangan Anda melalui pelengkapan akun sebelum mengkonfirmasi penerimaan aset."

## 10. Aset Tetap {#F-10}

`app/Filament/Resources/AsetTetaps/Schemas/AsetTetapForm.php:46-95`: kolom **Tim Kerja** (`tim_penempatan_id`) `disabled()` bila record sudah punya nilai, placeholder "Belum ditempatkan", helper "Penempatan diubah melalui Mutasi Aset (BAST)." saat terkunci. Kolom **ID Eksternal** (`external_id`) dan **Waktu Sinkronisasi** (`synced_at`) selalu `disabled()` + `dehydrated(false)` — begitu pula pada form Tim Kerja (`TimForm.php:76-81`).

## 11. Barang Persediaan {#F-11}

`app/Filament/Resources/BarangPersediaans/Schemas/BarangPersediaanForm.php:50-58`: kolom **Stok Terkunci** (`stok_hold`) `disabled()` — hanya tampilan.

## 12. Persetujuan Akhir Kasubbag (Tahap 3) {#F-12}

`PermintaanBarangResource.php:464-471`: kolom `jumlah_final` per item punya `maxValue()` = `jumlah_diminta` baris itu (dibaca dari data tersimpan, bukan input formulir); galat: "Jumlah disetujui tidak boleh melebihi jumlah diminta (:max)."

## 13. Kedaluwarsa {#F-13}

`app/Services/KedaluwarsaService.php`: lima status berjalan (`STATUS_BERJALAN`) diperiksa terhadap `hold_expired_at`; yang terlewat → status `kedaluwarsa`, stok dilepaskan (`StokService::release`), baris riwayat "Tahapan tidak ditindaklanjuti hingga batas waktu. Anda dapat mengajukan kembali kapan saja.". Notifikasi ke `anggotaTim()` (Tim + Ketua Tim tim pemohon): judul "Permintaan kedaluwarsa", isi "{kode} melewati batas waktu dan stok yang dikunci telah dilepaskan." (`NotifikasiService.php:240-244`). Pemeriksaan berjalan lazy pada tiap kunjungan panel (middleware) dan lewat penjadwal/perintah artisan `permintaan:lepas-hold`.

## 14. Penyimpanan Dokumen {#F-14}

Bukti permintaan dan BAST disimpan disk privat (`storage/app/private`), dibaca lewat rute aplikasi (`bukti.unduh`, `bast.unduh`) yang menuntut sesi masuk dan hak lihat sesuai resource — bukan lewat tautan `/storage` publik (`docs/audit/matriks-akses.md` Tabel 1 dan 4, catatan perbaikan C-001 21 Sep 2026, tidak berubah sejak itu).

## 15. Pusat Bantuan {#F-15}

`app/Filament/Pages/PusatBantuan.php`, `config/pusat_bantuan.php`, `app/Support/KontakBantuan.php`:
- Tidak terdaftar di sidebar (`shouldRegisterNavigation()` = false); dijangkau lewat dropdown profil.
- Kartu WhatsApp: nomor dari `KontakBantuan::nomor()` (baca `pengaturan.kontak_bantuan_wa`); tombol [Chat Sekarang] memanggil `KontakBantuan::tautanWhatsApp('Halo! Saya sedang butuh bantuan.')` — pesan diisi eksplisit, **berbeda** dari pesan bawaan helper. Nomor kosong/tidak valid → "Nomor WhatsApp belum diatur oleh Administrator." tanpa tombol.
- Kartu Panduan Penggunaan: `config('pusat_bantuan.panduan')` = disk `local`, path `panduan/Panduan-Penggunaan-SIMPBI.pdf`, nama tampilan "Panduan Penggunaan SIMPBI". Format dibaca dari ekstensi (`PATHINFO_EXTENSION`), bukan ditulis mati.
- Kartu Email: `config('pusat_bantuan.email')` = `sub-bagianumum@bps.go.id`.
- Kartu Jam Operasional: `config('pusat_bantuan.jam_layanan')` — Senin–Kamis 08.00–16.00, Jumat 08.00–16.30, Sabtu–Minggu libur; zona `Asia/Jakarta`; catatan balasan "Balasan paling lambat 1 hari kerja lewat email". Indikator status layanan dihitung ulang di sisi klien dari struktur JSON yang sama (`jamLayananJson()`).

## 16. Header/Navbar {#F-16}

- Pencarian global dimatikan: `->globalSearch(false)` (`app/Providers/Filament/AdminPanelProvider.php:119`).
- Kelompok kanan bilah atas, berurutan: toggle tema → lonceng notifikasi → avatar/dropdown profil (`resources/views/filament/partials/aksi-bilah-atas.blade.php:1-2,37-220`), dipasang lewat hook `GLOBAL_SEARCH_AFTER`.
- Toggle tema: dua keadaan (terang/gelap) lewat store Alpine `$store.theme`, disimpan localStorage.

## 17. Dropdown Profil {#F-17}

`aksi-bilah-atas.blade.php:158-219`: blok identitas (avatar+nama+email) **bukan** elemen interaktif (`<div>` tanpa href/aksi/tabindex). Dua tautan: **Pengaturan**/**Pengaturan Profil** (judul "Pengaturan" + subjudul "PROFIL, KEAMANAN & SISTEM" bagi Admin; "Pengaturan Profil" + "PROFIL & KEAMANAN" bagi peran lain) dan **Pusat Bantuan** (subjudul "PANDUAN & KONTAK"). Tombol Keluar (subjudul "AKHIRI SESI ANDA") membuka dialog, bukan langsung logout.

## 18. Dialog Keluar {#F-18}

`resources/views/filament/partials/dialog-keluar.blade.php`: heading "Keluar dari akun?", deskripsi "Sesi Anda akan diakhiri dan Anda perlu masuk kembali untuk memakai SIMPBI.", tombol "Batal" dan "Ya, Keluar" (POST ke rute logout Filament bawaan).

## 19. Halaman Masuk {#F-19}

`resources/views/filament/auth/two-panel.blade.php`, `resources/views/filament/auth/indikator-caps-lock.blade.php`, `resources/views/filament/partials/pemuatan.blade.php`:
- Layar pemuatan berlambang SIMPBI ditampilkan sebelum halaman masuk (`@include('filament.partials.pemuatan')`).
- Ilustrasi alur permintaan pada panel kiri: **dekoratif murni CSS**, menampilkan **5 label** ("Persetujuan Ketua Tim", "Verifikasi stok fisik", "Persetujuan akhir Kasubbag", "Penyiapan barang", "Pengambilan oleh pemohon") — bukan 6, dan tidak memuat data nyata (komentar kode eksplisit: "Hanya ilustrasi: tidak ada angka, nama, atau nomor permintaan nyata", `two-panel.blade.php:33-41`). Ini simplifikasi dekoratif, tidak mengubah fakta bahwa alur sesungguhnya (F-02) enam tahap.
- Indikator Caps Lock ada (berkas `indikator-caps-lock.blade.php` dipakai `login-content.blade.php`).
- **Tombol bantuan WhatsApp halaman masuk MEMILIKI pesan otomatis** (kontradiksi dengan anggapan sebelumnya bahwa tombol ini polos): `$tautanBantuan = \App\Support\KontakBantuan::tautanWhatsApp();` (`two-panel.blade.php:31`) memanggil TANPA argumen, sehingga memakai nilai bawaan parameter `$pesan` di `KontakBantuan::tautanWhatsApp()` — yaitu "Halo, saya mengalami kendala masuk ke SIMPBI dan memerlukan bantuan." — bukan tanpa pesan. Dikonfirmasi lewat `tests/Feature/KontakBantuanMasukTest.php:30-37`, yang memeriksa tautan dimulai dengan `?text=`.

## 20. Istilah Baku {#F-20}

Tidak ditemukan sisa "Export" (Inggris), "tanda tangan digital", atau "unit kerja" pada `app/` maupun `resources/views/` (pemeriksaan grep menyeluruh, hasil kosong). "e-TTD" dipakai konsisten pada lima berkas kode (`Pengaturan.php`, `CreateBastMutasiAset.php`, `BastMutasiAsetsTable.php`, `PermintaanBarangResource.php`, `DokumenBastService.php`). "Menunggu Verifikasi Gudang" persis sesuai `PermintaanBarang::STATUS['menunggu_verifikasi']`.

## 21. Ekspor dan Riwayat {#F-21}

`app/Filament/Pages/Riwayat.php:92-133`: empat jenis riwayat, tersedia per peran lewat `jenisTersediaUntuk()` — Permintaan Barang (semua peran), Mutasi Stok (admin/kasubbag/petugas_gudang), Mutasi Aset (kasubbag/petugas_gudang/ketua_tim — **`tim` TIDAK termasuk** di tab riwayat ini, meski akun `tim` tetap dapat melihat BAST tim-nya langsung lewat halaman Mutasi Aset itu sendiri), Notifikasi (admin saja). Ekspor mengikuti kueri/penyaring aktif (komentar `Riwayat.php:45`). Tidak ada menu "Laporan" terpisah pada sidebar mana pun (dikonfirmasi lewat `docs/audit/matriks-akses.md` Tabel 3).

## 22. Notifikasi (Lonceng) {#F-22}

`app/Livewire/LoncengNotifikasi.php:109-134`: `tandaiDibaca()` (menandai dibaca) dan `sembunyikan()` (mengisi `disembunyikan_at`, "Menyingkirkan satu notifikasi dari panel, tanpa menghapus barisnya" — komentar baris 115-120). **Tidak ada method hapus** pada komponen ini.

## 23. Katalog Barang / Keranjang {#F-23}

`app/Filament/Pages/KatalogBarang.php`: keranjang disimpan di sesi (bukan basis data) sampai diajukan. Galat tambah ke keranjang melebihi stok tersedia: "Jumlah melebihi stok tersedia" + rincian angka. Ajukan dengan keranjang kosong: "Keranjang masih kosong". Akun tanpa tim: "Akun Anda belum terhubung dengan tim kerja". Kolom formulir Ajukan: **Nama Pemohon** (wajib), **NIP Pemohon** (opsional, validasi 18 digit angka bila diisi), **Keperluan** (opsional).

## 24. Stok Masuk {#F-24}

`app/Filament/Pages/StokMasuk.php`: kolom formulir Catat Stok Masuk — Sumber, Nomor Dasar, Tanggal Dokumen, Barang, Jumlah, Keterangan. Kolom tabel riwayat: Tanggal, Barang, Jumlah, Sumber, Nomor Dasar, Saldo Setelah, Petugas. Pembuatan barang baru dari dialog ini: **Kode Barang** unik per kategori (bukan global) — galat "Kode barang sudah dipakai pada kategori ini." bila bentrok.

## 25. Kartu Kendali {#F-25}

`app/Filament/Pages/KartuKendali.php`: kolom tabel — Kode, Nama Barang, Satuan, Stok Awal, Masuk, Keluar, Sisa, Kategori, Pergerakan. Ekspor: format PDF/XLSX, penyaring Periode/Kategori/Nama Barang.

## 26. Kontribusi Widget Dasbor per Peran {#F-26}

Sesuai `docs/audit/matriks-akses.md` Tabel 5 (diverifikasi konsisten dengan `canView()` masing-masing widget, tidak berubah sejak audit): widget berbeda per peran, mis. `RingkasanAdmin`/`KelengkapanDataInduk` khusus Admin; `KondisiStok` khusus Kasubbag/Petugas Gudang; `KondisiAsetTetapTim`/`StatusPermintaanTim`/`TrenKonsumsiTim` khusus Ketua Tim/Tim; `PerluTindakan` dan `PolaPermintaan` tampil di seluruh peran.
