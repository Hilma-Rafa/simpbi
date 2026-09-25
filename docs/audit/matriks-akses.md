# Matriks Akses SIMPBI — peran × halaman/fitur × kemampuan

Dihasilkan pada audit baca-saja tanggal **21 September 2026** (kode: commit `b79831d` + perubahan yang belum di-commit pada working tree, lihat `laporan-audit.md`).

> **Diperbarui 21 September 2026 (perbaikan batch 1: A-001, A-016, A-002, A-004, A-005, A-014):** hanya baris yang berubah — Tabel 2 (rute unduhan dan uji tambahan), Tabel 4 (unduhan bukti/BAST) dan ketidakcocokan M-2 dan M-4 — yang diperbarui; baris lain adalah hasil audit awal. Lihat `laporan-perbaikan.md`. **Perubahan perilaku:** Admin Sistem tidak lagi dapat mengunduh BAST lewat URL (resource Mutasi Aset memang tertutup baginya); BAST yang belum disahkan tidak dapat diunduh siapa pun.

> **Diperbarui 21 September 2026 (perbaikan batch 2: A-003, A-030, A-006, A-017, A-020, A-021; A-029 dilewati):** hanya dua baris Tabel 4 yang berubah — Barang Persediaan (Ubah: stok terkunci baca-saja) dan Stok Masuk (Buat: aturan unik kode barang). **Hak akses tidak berubah.** Lihat `laporan-perbaikan.md`, bagian Batch 2.

> **Diperbarui 21–22 September 2026 (perbaikan batch 3: A-011, A-018, A-031, A-012):** hanya tiga baris Tabel 4 (Aset Tetap Tim Saya, Mutasi Aset (BAST), Tim Kerja) dan ketidakcocokan M-3 (Tabel 6) yang diperbarui. **Hak akses tidak berubah.** Lihat `laporan-perbaikan.md`, bagian Batch 3.

> **Diperbarui 22 September 2026 (perbaikan batch 5: G-001, G-002, G-003, G-005, G-006, F-002, F-003, F-006):** hanya empat baris Tabel 4 (Pengguna — Ubah, Hapus, Impor; Pengaturan — Akun Saya; Aset Tetap — Ubah; Mutasi Aset (BAST)). **Hak akses tidak berubah.** Keputusan pemilik pada F-002: penempatan aset terkunci pada form Ubah hanya bila aset sudah ditempatkan. Lihat `laporan-perbaikan.md`, bagian Batch 5.

> **Diperbarui 22 September 2026 (perbaikan batch 4: A-007, A-008, C-004, A-010, A-015, A-015b):** hanya lima baris Tabel 4 (Pengguna — Ubah, Pengaturan — Akun Saya, Pengaturan — Ubah kata sandi, Permintaan Barang tahap 5, unduhan bukti dan BAST). **Hak akses tidak berubah.** Tombol *Simpan Perubahan* pada Pengaturan kini membuka dialog mode peragaan hanya bila Admin mengaktifkan atau mengubah nomor peragaan (A-007). Lihat `laporan-perbaikan.md`, bagian Batch 4.

> **Diperbarui 21 September 2026 (perbaikan C-001):** hanya catatan pada Tabel 1 (rute bertoken `/bukti/{token}`) dan Tabel 4 (unduhan bukti/BAST): dokumen kini tersimpan dan dibaca dari disk privat, tidak lagi dapat dibuka lewat `/storage/...`. **Hak akses dan perilaku luar rute tidak berubah.**

> **Diperbarui 25 September 2026 (rencana data induk persediaan, `rencana-data-induk-persediaan.md`):** hanya tiga baris Tabel 4 — Stok Masuk (Impor Stok Awal + Unduh Template, **Gud**), Barang Persediaan dan Kategori Barang (Impor Excel + Unduh Template, **Adm, Kas**) — ditambah aturan kode barang 6 digit, kode akun persediaan 6 digit, dan keunikan kode kategori pada form. **Hak akses tidak berubah**: tombol impor mengikuti `canAccess()` halaman masing-masing. Semua halaman Buat/Ubah data induk kini punya tombol *Kembali* ke daftar.

> **Diperbarui 22 September 2026 (perbaikan batch 6, kosmetik — A-009, A-013, A-019, A-023, A-025, A-026):** hanya ketidakcocokan M-1 (Tabel 6) yang diperbarui — widget *Kondisi Stok* tidak lagi menampilkan tautan bagi Petugas Gudang. **Hak akses, alur bisnis, status, dan struktur data tidak berubah**; A-009/A-019/A-023/A-025/A-026 hanya mengubah label, bahasa, dan kode tak terpakai, tidak memengaruhi Tabel 2–5. Baris rute lama `/admin/permintaan-barangs/{id}` pada Tabel 2 tetap sama persis (A-026 hanya menghapus tampilan yang tidak pernah dirender). Lihat `laporan-perbaikan.md`, bagian Batch 6.

**Sumber kebenaran:** `canAccess()`, `canCreate()`, `canView()`, `getEloquentQuery()`, closure `->visible()` pada tiap aksi, middleware, dan `routes/web.php`. Tidak ada `Policy`, `Gate`, atau paket permission di aplikasi ini (`app/Policies` tidak ada; pencarian `Gate::`/`->authorize(` tidak menemukan apa pun). Seluruh kewenangan bertumpu pada pembacaan `auth()->user()->role` di kelas Filament dan pada closure `->visible()`.

**Cara membandingkan dengan yang benar-benar terjadi:**
1. Kode: dibaca per berkas (rujukan berkas:baris pada kolom "Sumber").
2. Server: uji HTTP per peran memakai kernel Laravel + `actingAs` pada basis data uji dalam memori (skrip di luar repositori). Hasil status HTTP ada pada Tabel 2.
3. UI: menu samping yang benar-benar tampil dibaca dari peramban headless untuk lima akun nyata pada salinan basis data pengembangan (Tabel 3). Tautan pada dasbor diuji satu per satu (Tabel 6).
4. Aksi tahapan: siklus penuh dan aksi ilegal diuji lewat komponen Livewire (bagian "Aksi tahapan" pada Tabel 4).

Singkatan peran: **Adm** = Admin Sistem (`admin`), **Kas** = Kasubbag Umum (`kasubbag`), **Gud** = Petugas Gudang (`petugas_gudang`), **KT** = Ketua Tim (`ketua_tim`), **Tim** = Tim (`tim`).
Lambang: ✔ = boleh, ✘ = ditolak/tidak tampil, ◐ = boleh sebagian (lihat catatan), — = tidak berlaku.

---

## Tabel 1. Pintu masuk tanpa login (publik)

| URL | Fungsi | Pembatas | Sumber |
|---|---|---|---|
| `/` | Halaman muka (dwibahasa) | tidak ada | `routes/web.php:10` |
| `/bahasa/{kode}` | Ganti bahasa (`id`/`en`), disimpan di sesi | daftar putih kode; **GET yang mengubah sesi** | `routes/web.php:21` |
| `/admin/login` | Halaman masuk | throttle Filament (5 percobaan/menit, terverifikasi) | `App\Filament\Auth\Login` |
| `/verifikasi/{token}` | Lembar keaslian bukti permintaan | token 40 aksara acak + `pengesahan_at` tidak null | `routes/web.php:42` |
| `/bukti/{token}` dan `/bukti/{token}/asli` | Berkas PDF bukti (inline) | token 40 aksara acak + sudah disahkan; **berkas dibaca dari disk privat (`storage/app/private`), bukan `/storage` — C-001, 21 Sep 2026** | `routes/web.php:70`, `:93` |
| `/verifikasi-bast/{token}` | Lembar keaslian BAST | token + `disahkan_at` tidak null | `routes/web.php:115` |
| `/up` | Health check | tidak ada | `bootstrap/app.php` |

---

## Tabel 2. Hasil uji server: status HTTP per peran (basis data uji, dua tim: A dan B)

`KT-A`/`Tim-A` adalah Ketua Tim dan anggota Tim A; `KT-B` adalah Ketua Tim B (pemilik data uji). Data uji: satu permintaan **milik Tim B** berstatus `selesai` dengan berkas bukti, dan satu BAST Tim B → Tim A.

| URL | Adm | Kas | Gud | KT-A | Tim-A | KT-B | Sesuai kode? |
|---|---|---|---|---|---|---|---|
| `/admin` (Dasbor) | 200 | 200 | 200 | 200 | 200 | 200 | ya |
| `/admin/katalog-barang` | 403 | 403 | 403 | 200 | 200 | 200 | ya (`KatalogBarang.php:56`) |
| `/admin/permintaan-barangs` | 200 | 200 | 200 | 200 | 200 | 200 | ya |
| `/admin/permintaan-barangs/{id}` (rute lama) | 302→Riwayat | 302 | 302 | **404** | **404** | 302 | ya — tim lain ditolak lewat `getEloquentQuery()` |
| `/admin/riwayat` | 200 | 200 | 200 | 200 | 200 | 200 | ya |
| `/admin/kartu-kendali` | 403 | 200 | 200 | 403 | 403 | 403 | ya (`KartuKendali.php:72`) |
| `/admin/stok-masuk` | 403 | 403 | 200 | 403 | 403 | 403 | ya (`StokMasuk.php:59`) |
| `/admin/barang-persediaans` (+`/create`, `/{id}/edit`) | 200 | 200 | 403 | 403 | 403 | 403 | ya |
| `/admin/kategoris` (+`/create`, `/{id}/edit`) | 200 | 200 | 403 | 403 | 403 | 403 | ya |
| `/admin/aset-tetaps` (+`/create`, `/{id}/edit`) | 200 | 200 | 403 | 403 | 403 | 403 | ya |
| `/admin/aset-tetap-tim-saya` | 403 | 403 | 403 | 200 | 200 | 200 | ya |
| `/admin/bast-mutasi-asets` | 403 | 200 | 200 | 200 | 200 | 200 | ya |
| `/admin/bast-mutasi-asets/create` | 403 | 403 | 200 | 403 | 403 | 403 | ya (`BastMutasiAsetResource.php:46`) |
| `/admin/tims` (+`/create`, `/{id}/edit`) | 200 | 200 | 403 | 403 | 403 | 403 | ya |
| `/admin/users` (+`/create`, `/{id}/edit`) | 200 | 403 | 403 | 403 | 403 | 403 | ya |
| `/admin/pengaturan` | 200 | 200 | 200 | 200 | 200 | 200 | ya (bagian admin disaring di dalam halaman) |
| `/admin/pusat-bantuan` | 200 | 200 | 200 | 200 | 200 | 200 | ya |
| `/bukti-permintaan/{id}` (milik Tim B) | 200 | 200 | 200 | **404** | **404** | 200 | ya — **diperbaiki 21 Sep 2026 (A-002)**; sebelumnya 200 bagi KT-A/Tim-A. Uji: `UnduhanDokumenTest` |
| `/dokumen-bast/{id}` (BAST B→A, **sudah disahkan**) | **403** | 200 | 200 | 200 | 200 | 200 | ya — **diperbaiki 21 Sep 2026 (A-002, A-014)**. Tim ketiga (bukan asal/tujuan): 404. BAST **belum disahkan**: 404 untuk semua peran. **Admin kini 403** (dulu 200). Uji: `UnduhanDokumenTest` |
| `/pusat-bantuan/panduan` | 404 | 404 | 404 | 404 | 404 | 404 | berkas panduan belum ada di `storage/app/private/panduan/` |

Uji tambahan (akun khusus, uji Livewire/HTTP):

| Keadaan | `/admin/*` | `/bukti-permintaan/{id}` | Catatan |
|---|---|---|---|
| Tamu (belum login) | 302 → `/admin/login` | **302 → `/admin/login`** (dulu 500) | **diperbaiki 21 Sep 2026 (A-005)**; sama untuk `/dokumen-bast/{id}` dan `/pusat-bantuan/panduan` |
| Akun `status_aktif = false`, sesi masih hidup | 403 | **403** (dulu 200) | **diperbaiki 21 Sep 2026 (A-004)** |
| Akun `harus_ganti_sandi = true` | 302 → ganti sandi | **302 → ganti sandi** (dulu 200) | **diperbaiki 21 Sep 2026 (A-004)**; akun belum melengkapi data: 302 → Lengkapi Akun |
| Tim A membuka `/bukti/{token}` bertoken salah | — | 404 | benar |

---

## Tabel 3. Menu samping yang benar-benar tampil (peramban headless, akun nyata pada salinan basis data pengembangan)

| Peran (akun uji) | Item menu samping |
|---|---|
| Admin (`admin@bps.go.id`) | Dasbor · Permintaan Barang · Barang Persediaan · Kategori Barang · Aset Tetap · Riwayat · Tim Kerja · Pengguna |
| Kasubbag (`kasubbag@bps.go.id`) | Dasbor · Permintaan Barang · Barang Persediaan · Kategori Barang · **Kartu Kendali** · Aset Tetap · **Mutasi Aset** · Riwayat · Tim Kerja |
| Petugas Gudang (`probo@bps.go.id`) | Dasbor · Permintaan Barang · **Stok Masuk** · **Kartu Kendali** · **Mutasi Aset** · Riwayat |
| Ketua Tim (`wanda.pribadi@bps.go.id`) | Dasbor · Katalog Barang · Permintaan Barang · Mutasi Aset · Aset Tetap Tim Saya · Riwayat |
| Tim (`tim01@bps.go.id`) | Dasbor · Katalog Barang · Permintaan Barang · Mutasi Aset · Aset Tetap Tim Saya · Riwayat |

Pengaturan dan Pusat Bantuan sengaja tidak masuk menu samping (`shouldRegisterNavigation() = false`); keduanya dijangkau dari menu profil pada bilah atas. Ganti Kata Sandi dan Lengkapi Akun adalah halaman penahan yang hanya muncul lewat pengalihan middleware.

**Menu = server:** untuk kelima peran, item menu persis sama dengan halaman yang mengembalikan 200 pada Tabel 2. Tidak ada item menu yang berujung 403, dan tidak ada halaman yang terbuka tetapi tersembunyi dari menu (selain dua halaman menu-profil di atas).

---

## Tabel 4. Kemampuan per fitur

| Fitur / halaman | Lihat | Buat | Ubah | Hapus | Ekspor / Unduh | Setujui / Proses | Sumber |
|---|---|---|---|---|---|---|---|
| **Katalog Barang** (ajukan permintaan) | KT, Tim | KT, Tim (keranjang → *Ajukan*) | — | ✔ hapus dari keranjang (KT, Tim) | — | — | `KatalogBarang.php:56` |
| **Permintaan Barang** (daftar aktif) | Adm, Kas, Gud (semua tim) · KT, Tim (tim sendiri) | ✘ (`canCreate=false`; hanya via Katalog) | ✘ | ✘ | Unduh Bukti (semua yang bisa melihat, bila berkas ada) | lihat baris berikut | `PermintaanBarangResource.php:52-77` |
| ↳ Tahap 1 — Setujui/Tolak | — | — | — | — | — | **KT** (tim sendiri, status `menunggu_ketua`) | `:319-356` |
| ↳ Tahap 2 — Verifikasi stok fisik | — | — | — | — | — | **Gud** (`menunggu_verifikasi`) | `:360-420` |
| ↳ Tahap 3 — Setujui/Tolak akhir | — | — | — | — | — | **Kas** (`menunggu_kasubbag`) | `:424-498` |
| ↳ Tahap 4 — Barang Siap Diambil | — | — | — | — | — | **Gud** (`siap_diproses`; wajib TTD tersimpan + ketik NIP) | `:502-529` |
| ↳ Tahap 5 — Konfirmasi Penerimaan | — | — | — | — | — | **KT, Tim** (tim sendiri, `siap_diambil`; KT mengetik NIP, **akun Tim mengetik nama timnya** — A-015b, diperbaiki 22 Sep 2026; tanda tangan dan nama pada dokumen tetap milik Ketua Tim) | `:533-606`, `bidangKonfirmasiNip()` |
| ↳ Tahap 6 — Sahkan | — | — | — | — | — | **Kas** (`menunggu_pengesahan`; ketik NIP) | `:610-632` |
| **Riwayat** — jenis *Permintaan* | semua peran (KT/Tim terbatas tim sendiri) | — | — | — | Ekspor PDF/Excel: semua peran yang bisa melihat (query sama dengan tampilan) | — | `Riwayat.php:78-125` |
| ↳ jenis *Mutasi Stok* | Adm, Kas, Gud | — | — | — | Ekspor | — | idem |
| ↳ jenis *Mutasi Aset* | Kas, Gud, KT (terbatas tim sendiri) | — | — | — | Ekspor | — | idem |
| ↳ jenis *Notifikasi* | Adm | — | — | — | Ekspor; **Kirim Ulang** WhatsApp gagal (Adm) | — | idem, `Riwayat.php:279` |
| **Kartu Kendali** | Kas, Gud | — | — | — | Ekspor PDF/XLSX (Kas, Gud) | — | `KartuKendali.php:72` |
| **Stok Masuk** | Gud | Gud (dapat pula membuat *Barang baru* dari isian nota; **sejak 21 Sep 2026 kode barang wajib unik per kategori**, A-021; **sejak 25 Sep 2026 kode barang tepat 6 digit angka**) | — | — | **Impor Stok Awal + Unduh Template (Gud)** — dicatat lewat `StokService::tambah()` bersumber Stok Awal; hanya barang yang belum pernah punya stok/mutasi (25 Sep 2026) | — | `StokMasuk.php:64` |
| **Barang Persediaan** | Adm, Kas | Adm, Kas (**kode barang tepat 6 digit angka; pilihan kategori hanya kategori persediaan — 25 Sep 2026**) | Adm, Kas (`stok_fisik` dengan dialog konfirmasi; **`stok_hold` kini hanya tampil, tidak dapat diubah — diperbaiki 21 Sep 2026, A-030**) | Adm, Kas (dilindungi bila punya riwayat) | **Impor Excel + Unduh Template (Adm, Kas)** — data induk saja, tidak pernah mengubah stok (25 Sep 2026) | — | `BarangPersediaanResource.php:48` |
| **Kategori Barang** | Adm, Kas | Adm, Kas (**kode kategori unik dengan pesan; kode akun persediaan tepat 6 digit — 25 Sep 2026**) | Adm, Kas (idem) | Adm, Kas (dilindungi) | **Impor Excel + Unduh Template (Adm, Kas)** (25 Sep 2026) | — | `KategoriResource.php:48` |
| **Aset Tetap** | Adm, Kas | Adm, Kas | Adm, Kas (**penempatan terkunci bila aset sudah ditempatkan — perpindahan hanya lewat Mutasi Aset (BAST), F-002; ID Eksternal dan Waktu Sinkronisasi hanya-baca, F-003; diperbaiki 22 Sep 2026**) | Adm, Kas | Impor Excel + Unduh Template (Adm, Kas) | — | `AsetTetapResource.php:42` |
| **Aset Tetap Tim Saya** | KT, Tim (tim sendiri; **pengguna tanpa tim melihat daftar kosong** — A-018, diperbaiki 22 Sep 2026; panel *Kondisi Aset* pun kosong) | — | — | — | — | — (aksi *Riwayat Penempatan* saja) | `AsetTetapTimSaya.php:46-64`, `KondisiAsetTetapTim.php:85-93` |
| **Mutasi Aset (BAST)** | Kas, Gud (semua) · KT, Tim (asal atau tujuan = tim sendiri) | **Gud** (*Tim Kerja Asal* selalu penempatan aset saat ini dan tidak dapat diubah; aset tanpa penempatan, atau yang masih punya BAST `menunggu_pengesahan` dengan asal yang masih sama, ditolak di form dan di server — A-011, diperbaiki 22 Sep 2026; **aset nonaktif ditolak di server — F-006, diperbaiki 22 Sep 2026**) | — | — | Unduh BAST (hanya BAST yang **sudah disahkan**, bagi yang bisa melihat barisnya; diperbaiki 21 Sep 2026) | *Sahkan*: **Kas** (menolak BAST yang penempatan asalnya sudah berubah — A-011; **hanya bila status `menunggu_pengesahan`, diperiksa ulang di server dengan baris BAST terkunci — F-006, diperbaiki 22 Sep 2026**) · *Konfirmasi Penerimaan*: **KT tim tujuan** (**hanya bila status `menunggu_konfirmasi`, diperiksa ulang di server — F-006**) | `BastMutasiAsetResource.php:40-64`, `BastMutasiAsetsTable.php:84`, `:109`, `:144-153` |
| **Tim Kerja** | Adm, Kas | Adm, Kas | Adm, Kas (pilihan *Ketua Tim* hanya pengguna aktif berperan `ketua_tim` pada tim itu, kosong pada form Buat — A-012; *ID Eksternal* dan *Waktu Sinkronisasi* hanya-baca — A-031; diperbaiki 22 Sep 2026) | Adm, Kas (dilindungi) | Impor Excel + Template | — | `TimResource.php:41` |
| **Pengguna** | Adm | Adm | Adm (termasuk peran, tim, status aktif; **pada akunnya sendiri peran dan status terkunci**, dan perubahan yang menghabiskan Admin aktif ditolak di server — A-010, diperbaiki 22 Sep 2026; **Admin yang mengisi sandi akunnya sendiri tetap masuk — G-003, diperbaiki 22 Sep 2026**) | Adm (dilindungi; **tidak dapat menghapus akun sendiri atau menyisakan nol Admin aktif, tunggal maupun massal (massal ditolak seluruhnya), juga pada jalur langsung — G-001, diperbaiki 22 Sep 2026**) | Impor Excel + Template (**baris yang mengubah peran atau status akun pengimpor, atau menurunkan atau menonaktifkan Admin aktif terakhir, ditolak dan dilaporkan sebagai baris gagal — G-002, diperbaiki 22 Sep 2026**) | — | `UserResource.php:46` |
| **Pengaturan** — Akun Saya | semua | — | semua (no. WA); **nama, NIP, dan email hanya-baca untuk semua peran, diubah Admin lewat menu Pengguna — A-015 dan G-005, diperbaiki 22 Sep 2026** | — | — | — | `Pengaturan.php:206-222`, `simpan()` |
| ↳ Tanda tangan | Gud, KT | — | Gud, KT | — | — | — | `Pengaturan.php:270` |
| ↳ Ubah kata sandi | semua | — | semua (sandi baru harus berbeda dari yang sedang berlaku; pengguna tetap masuk di perangkatnya, sesi di perangkat lain tidak berlaku — A-008, diperbaiki 22 Sep 2026) | — | — | — | idem, `GantiKataSandi.php` |
| ↳ Batas Waktu Alur, WhatsApp | Adm | — | Adm (server menolak selain Adm: `simpan()` keluar bila bukan admin) | — | — | — | `Pengaturan.php:117-120`, `:563` |
| **Pusat Bantuan** | semua | — | — | — | Unduh panduan (semua; berkas belum ada) | — | `PusatBantuan.php`, `routes/web.php:162` |
| **Lonceng notifikasi** | semua (milik sendiri) | — | tandai dibaca / sembunyikan (milik sendiri) | — | — | — | `LoncengNotifikasi.php` |
| **Lengkapi Akun** | Gud, KT (wajib WA + TTD); peran lain sesuai `Onboarding` | — | ✔ | — | — | — | `Onboarding.php`, `PaksaLengkapiAkun.php` |
| **Unduh `bukti-permintaan/{id}`, `dokumen-bast/{id}`** | mengikuti resource: bukti = hak lihat pada Permintaan Barang (Adm, Kas, Gud semua tim; KT, Tim tim sendiri); BAST = hak lihat pada Mutasi Aset (Kas, Gud semua; KT, Tim asal/tujuan) dan hanya bila sudah disahkan; **Admin 403 untuk BAST** | — | — | — | ✔ | — | `routes/web.php:138-175` — A-002, A-004, A-014 (**diperbaiki 21 Sep 2026**; ketiga rute unduhan juga dikenai gerbang akun panel; **berkas kini di disk privat `storage/app/private`, tidak terjangkau `/storage` — C-001, 21 Sep 2026**; **gerbang akun kini juga memutus sesi yang hash sandinya usang, mis. sesi lain sesudah ganti sandi — C-004, 22 Sep 2026**) |
| **Artisan** (`simpbi:backup`, `simpbi:restore`, `simpbi:reset-demo`, `permintaan:lepas-hold`) | operator server | — | — | — | — | — | `app/Console/Commands` |

**Aksi tahapan — bukti penegakan di server.** Filament 5 menjalankan `isDisabled()` (yang mencakup `isHidden()`) sebelum memasang maupun memanggil aksi (`vendor/filament/actions/src/Concerns/InteractsWithActions.php:163, :285`). Karena itu setiap closure `->visible()` pada Tabel 4 juga ditegakkan di server. Terverifikasi lewat uji Livewire: anggota Tim biasa tidak dapat memakai *Setujui* Ketua Tim, Gudang tidak dapat memakai *Verifikasi* saat status `menunggu_ketua`, Kasubbag tidak dapat memakai *Setujui* akhir sebelum waktunya, Gudang tidak dapat *Konfirmasi Penerimaan* — semuanya tersembunyi dan tidak mengubah status.

---

## Tabel 5. Widget dasbor per peran (hasil `canView()` dan render nyata)

| Widget | Adm | Kas | Gud | KT | Tim |
|---|---|---|---|---|---|
| RingkasanAdmin | ✔ | | | | |
| KelengkapanDataInduk | ✔ | | | | |
| RingkasanKasubbag | | ✔ | | | |
| BarangPalingDiminta | | ✔ | | | |
| TrenKonsumsiKategori | | ✔ | | | |
| PermintaanPerTim | | ✔ | | | |
| RingkasanGudang | | | ✔ | | |
| BarangPerluPerhatian | | | ✔ | | |
| RingkasanKetua | | | | ✔ | |
| RingkasanTim | | | | | ✔ |
| StatusPermintaanTim | | | | ✔ | ✔ |
| TrenKonsumsiTim | | | | ✔ | ✔ |
| KondisiAsetTetapTim | | | | ✔ | ✔ |
| KondisiAsetTetap | ✔ | ✔ | | | |
| KondisiStok | | ✔ | ✔ | | |
| PerluTindakan | | ✔ | ✔ | ✔ | ✔ |
| PolaPermintaan | ✔ | ✔ | ✔ | ✔ | ✔ |

---

## Tabel 6. Ketidakcocokan UI ↔ server

| # | Ketidakcocokan | Arah | Bukti |
|---|---|---|---|
| M-1 | Dasbor **Petugas Gudang** memuat widget *Kondisi Stok* dengan **16 tautan** (satu per kategori + "Lihat semua") ke `/admin/barang-persediaans?...`. Halaman itu hanya untuk Admin/Kasubbag, sehingga seluruh tautan berujung **403**. | UI menampilkan, server menolak | Peramban headless, akun `probo@bps.go.id`: `403 /admin/barang-persediaans` dan 15 variannya. Akar: `KondisiStok.php:127-128` memilih `BarangPersediaanResource` bila bukan `KatalogBarang::canAccess()`. → **A-013** **DIPERBAIKI 22 Sep 2026 (batch 6, A-013):** widget kini memeriksa `KatalogBarang::canAccess() \|\| BarangPersediaanResource::canAccess()`; bagi Petugas Gudang, judul dan nama kategori tampil sebagai teks biasa (0 tautan) — angka dan isi panel tidak berubah. Kasubbag tidak terpengaruh: tetap **16 tautan di dalam widget** (sama persis dengan temuan audit ini), semuanya 200. Admin tidak pernah melihat widget ini sama sekali — `canView()` (`KondisiStok.php`) membatasinya ke `kasubbag`/`petugas_gudang` saja, tidak diubah oleh Batch 6; diverifikasi ulang lewat tangkapan peramban (heading widget tidak ada sama sekali di halaman Admin). |
| M-2 | Tombol **Unduh BAST** tampil sejak BAST dibuat (berkas draf sudah ada), padahal tooltip dan komentar kode menyebut "yang telah disahkan". | UI menampilkan lebih awal daripada yang dijanjikan teksnya | `CreateBastMutasiAset.php:26-31` mengisi `file_bast_path` saat `afterCreate`; `BastMutasiAsetsTable.php:150-153`. → **A-014** **DIPERBAIKI 21 Sep 2026 (batch 1, A-014):** tombol dan rute kini menuntut `disahkan_at`. |
| M-3 | Kolom *Ketua Tim* pada Tim Kerja (`tim.ketua_tim_id`) dan peran `ketua_tim` pada pengguna adalah dua sumber yang tidak dipaksa selaras. Persetujuan mengikuti `role`+`tim_id`; tanda tangan pada dokumen mengikuti `tim.ketua_tim_id`. | dua sumber kebenaran | `TimForm.php:26-31`, `PermintaanBarangResource.php:734-741`, `NotifikasiService.php` (`berperan('ketua_tim', tim)`). → **A-012** (pilihan dan validasi form diselaraskan pada 22 Sep 2026; kedua sumber data tetap ada dan persetujuan/tanda tangan tidak diubah) |
| M-4 | Rute unduhan `bukti-permintaan/{id}`, `dokumen-bast/{id}` hanya memeriksa "sudah login", bukan tim, peran, atau status aktif akun. Tombol *Unduh* di UI hanya muncul pada baris yang boleh dilihat, tetapi URL-nya dapat dipakai siapa pun yang login. | UI membatasi, server tidak | Tabel 2 dan uji "Akun nonaktif" di atas. → **A-002**, **A-004** **DIPERBAIKI 21 Sep 2026 (batch 1, A-002/A-004).** |
| M-5 | Notifikasi lonceng menyaring tautan lewat `canAccess()` masing-masing resource (`LoncengNotifikasi.php:151-170`). | UI ↔ server selaras | tidak ada temuan |
| M-6 | Petugas Gudang dapat **membuat Barang Persediaan baru** dari dialog Stok Masuk, padahal resource Barang Persediaan tertutup baginya. | jalur kedua ke data induk | `StokMasuk.php:255-256` (`createOptionForm`/`createOptionUsing`), `docs/penyesuaian-stok.md` bagian B. → **A-021** (butuh keputusan pemilik) |
