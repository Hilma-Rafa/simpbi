# Laporan Pra-Deploy — SIMPBI

Disusun 22 September 2026, murni pelaporan (baca-saja). **Tidak ada berkas yang
dihapus, dipindah, diganti nama, atau diubah isinya** dalam penyusunan laporan ini,
kecuali berkas laporan ini sendiri.

**Konteks git saat penyusunan** (dicatat apa adanya, tidak perlu bersih untuk tugas
baca-saja ini): `HEAD` = `42da9c6` ("Selesai merge branch main dari origin"), cabang
`main`. `git status` menunjukkan satu perubahan belum tercatat: `DEMIPUSHcek`
terhapus dari working tree (berkas percobaan dari commit `a6fc29a "##"`; bukan
bagian dari aplikasi, tidak relevan untuk deploy). Tidak ada perubahan lain.

Skill: tidak ada skill di `.claude/skills` proyek yang relevan (semua untuk desain
UI/animasi); skill global `browser-automation` tidak dipakai — tugas ini murni
pembacaan berkas dan `git`, tidak ada verifikasi UI.

## 1. Ringkasan eksekutif

**Kategori 1 (aman dihapus): hanya 1 item berkeyakinan Tinggi** — satu berkas kunci
Excel sisa (`docs/~$Kartu-Kendali-Persediaan-2025.xlsx`, 165 B, tidak dilacak git).
Satu item lain (berkas `.docx` bernama mengandung "(1)") ditemukan tetapi
**berkeyakinan Rendah** — jangan dihapus tanpa memeriksa isinya (lihat §2). Dua
temuan audit lama (`BAST-UJI-0001.pdf`/E-002, folder `undefined/`/P2-7) **sudah
tidak ada lagi** — terverifikasi tuntas, tidak perlu tindakan.

**Kategori 2 (jangan dihapus dari repo, jangan ikut ke server): total ± 30,5 MB**
tersebar di `docs/perancangan/` (11 MB), `docs/Buku Panduan/` (19 MB, referensi
SIMAN eksternal), `docs/audit/` (240 KB), `tests/` (775 KB), dan dua `.docx` di akar
(± 48,6 KB) — seluruhnya dilacak git dan sah ada di repositori, tetapi tidak pantas
diakses publik.

**Temuan paling kritis (§C.7):** repositori ini berstruktur Laravel standar —
**document root produksi WAJIB diarahkan ke folder `public/`, bukan ke akar
repositori.** Ini tidak dapat diverifikasi dari repositori semata (murni
konfigurasi *virtual host* di server); bila keliru diarahkan ke akar, **seluruh
isi Kategori 2 di atas, `.git/`, sumber `vendor/`, dan bahkan `database/database.sqlite`
berpotensi diunduh langsung lewat URL** — termasuk rincian celah keamanan yang
sudah diperbaiki di `docs/audit/`.

**Temuan kritis kedua (§D.6):** `storage/app/public/` repositori kerja saat ini
masih berisi **15 dokumen lama** (bukti permintaan dan satu BAST bertanggal
15–18 September, sebelum migrasi ke disk privat/C-001) yang **tidak dilacak git**
tetapi ADA di disk lokal. **`php artisan storage:link` TIDAK BOLEH PERNAH
dijalankan di server produksi** — perintah itu akan membuka symlink `public/storage`
yang membuat dokumen-dokumen ini (dan dokumen privat lain) dapat diunduh tanpa
login, membuka kembali celah A-002/C-001 yang sudah ditutup.

## 2. Kategori 1 — aman dihapus dari repositori

| Path | Ukuran | Alasan | Keyakinan |
|---|---|---|---|
| `docs/~$Kartu-Kendali-Persediaan-2025.xlsx` | 165 B | Berkas kunci sementara Excel (dibuat saat `Kartu-Kendali-Persediaan-2025.xlsx` sedang terbuka). **Tidak dilacak git** (`git ls-files` kosong untuk berkas ini) — hanya sampah lokal. Sudah dicakup pola `/docs/~$*` di `.gitignore`, jadi tidak akan pernah ter-commit; hanya perlu dibersihkan manual di disk lokal bila diinginkan. | **Tinggi** |
| `docs/perancangan/Probis Usulan Pencatatan Administratif Aset Tetap Kategori Peralatan dan Mesin (1).docx` | 427.777 B | Nama mengandung pola duplikat unduhan peramban `"(1)"` — **TETAPI** diverifikasi: **tidak ada berkas kembar/asli** tanpa `"(1)"` di folder yang sama, dan berkas ini **dilacak git** (satu-satunya salinan kontennya di repositori). Kemungkinan besar ini cuma nama berkas yang canggung dari sesi unduh lama, bukan duplikat sungguhan. **Jangan dihapus** — bila ingin dirapikan, ganti nama saja (buang `" (1)"`), jangan hapus, dan periksa dulu isinya benar-benar unik. | **Rendah** |

**Sudah diverifikasi tuntas, tidak perlu tindakan (dicatat karena disebut di
brief tugas):**
- **E-002** (`storage/app/public/bast-mutasi/BAST-UJI-0001.pdf`, artefak tes A-016) —
  **tidak ditemukan lagi** di `storage/app/public/`. Yang tersisa di sana adalah 15
  dokumen bertanggal asli (lihat catatan kritis §1 dan §D.6), bukan artefak tes.
  Fixture tes aktif saat ini (`storage/framework/testing/disks/local/{bast-mutasi,bukti-permintaan}/*-UJI.pdf`)
  adalah hasil normal `Storage::fake()` PHPUnit, sudah gitignored via
  `storage/framework/testing/.gitignore`, tidak relevan untuk deploy.
- **P2-7** (folder `undefined/`, `docs/finalisasi-akhir.md`) — dicari di seluruh
  repositori (`find … -iname undefined`), **tidak ditemukan**. Tuntas.
- Berkas cache/log yang ter-*commit* padahal seharusnya tidak (`storage/logs/*.log`,
  `.phpunit.result.cache`, `bootstrap/cache/*.php`) — **tidak ditemukan satu pun**
  (`git ls-files` untuk pola-pola ini kosong).
- Sampah sistem operasi (`.DS_Store`, `Thumbs.db`, `desktop.ini`) — **tidak
  ditemukan** di seluruh repositori.

## 3. Kategori 2 — jangan dihapus dari repo, jangan ikut deploy

| Path/folder | Ukuran | Alasan |
|---|---|---|
| `docs/perancangan/` (10 berkas) | 11 MB | ERD, Use Case Diagram, Activity Diagram, Probis, proposal skripsi PDF, revisi sempro — bahan skripsi dan riwayat rancangan; seluruhnya dilacak git. Tidak dibutuhkan aplikasi berjalan. |
| `docs/Buku Panduan/` (1 berkas) | 19 MB | `Operating Manual SIMAN 2 - Modul Evaluasi Kinerja 2025.pdf` — referensi eksternal (bukan dokumentasi SIMPBI sendiri), dilacak git. |
| `docs/audit/` (4 berkas + laporan ini) | 240 KB | `laporan-audit.md`, `laporan-perbaikan.md`, `laporan-final.md`, `matriks-akses.md` — nilai historis penting untuk skripsi, tetapi memuat rincian celah keamanan (yang sudah ditambal) yang tidak pantas terbaca pegawai umum di server produksi. |
| `Klasifikasi_Temuan_Audit_SIMPBI.docx`, `Pemetaan_Sinkronisasi_Dokumen_SIMPBI.docx` (akar repo) | ± 48,6 KB | Dokumen kerja audit, dilacak git, sama alasannya dengan `docs/audit/`. |
| `tests/` | 775 KB | Test suite — bermanfaat di repositori/CI, tidak diperlukan untuk menjalankan aplikasi di server. |
| `database/database.sqlite` | 335.872 B (berubah sejak sesi audit terakhir — aktivitas pemilik di luar tugas ini, bukan disentuh dalam penyusunan laporan ini) | **Tidak dilacak git** (aman dari commit), tetapi ADA di disk kerja lokal — jangan sampai ikut tersalin ke server bila deploy MySQL (lihat whitelist §6). |
| `database/data/nomor-wa-ketua-tim.xlsx` | 5.953 B | Data pribadi pegawai (nomor WhatsApp, NIP) — **tidak dilacak git** (dicek: hanya `nomor-wa-ketua-tim.contoh.xlsx` dan `README.md` yang dilacak), tetapi ada di disk lokal. Jangan ikut deploy. |
| **`storage/app/public/`** (15 berkas dokumen lama + `.gitignore`) | ± 1,2 MB | **Lihat temuan kritis §1 dan §D.6.** Dokumen bukti permintaan dan BAST bertanggal 15–18 September (sebelum migrasi disk privat C-001); **tidak dilacak git**, tapi ADA di disk lokal repositori kerja. |
| `.git/` | — | **Lihat §C.7 di bawah — temuan paling kritis di laporan ini.** |
| `node_modules/` | 49 MB | Dependensi Node untuk membangun aset (Vite/Tailwind); **dikonfirmasi 0 berkas ter-*commit*** dan tercakup `.gitignore` (`/node_modules`). Dibangun ulang di server/lokal lewat `npm install`, tidak disalin manual. |
| `vendor/` | 142 MB | Dependensi Composer; **dikonfirmasi 0 berkas ter-*commit*** dan tercakup `.gitignore` (`/vendor`). Dibangun ulang di server lewat `composer install --no-dev`, atau diunggah terpisah sebagai bagian paket deploy — bukan dari git. |

### C.7 — Document root: temuan paling kritis

Repositori ini berstruktur Laravel standar: titik masuk sesungguhnya adalah
`public/index.php` (memuat `../vendor/autoload.php` dan `../bootstrap/app.php`
lewat `__DIR__`). **Tidak ada `.htaccess` di akar repositori** yang mengalihkan ke
`public/` — konfirmasi bahwa struktur ini murni mengandalkan *virtual host*/document
root server diarahkan langsung ke folder `public/`.

**Ini tidak dapat diverifikasi dari repositori semata** — sepenuhnya bergantung pada
konfigurasi web server (Apache/Nginx) di lingkungan produksi, di luar jangkauan
laporan ini. **HARUS ditegaskan secara eksplisit sebagai langkah pertama checklist
server**, sebab bila document root keliru diarahkan ke akar repositori (bukan
`public/`), maka lewat URL publik dapat diakses langsung:
- `.git/` — seluruh riwayat kode dan (bila pernah ada, meski hasil §E.1 di bawah
  menyatakan aman) berpotensi metadata sensitif lain di riwayat commit;
- `docs/audit/` — rincian celah keamanan yang sudah diperbaiki, berguna bagi
  penyerang untuk memetakan versi mana yang belum ditambal;
- `vendor/` — kode sumber seluruh paket pihak ketiga, memudahkan pemetaan versi
  untuk eksploitasi yang diketahui;
- `database/database.sqlite` (bila dipakai) dan `database/data/*.xlsx` (data
  pribadi pegawai) bila tidak sengaja ikut tersalin;
- `composer.json`/`composer.lock`/`package.json` — memetakan versi dependensi.

## 4. Checklist Kategori 3 — siapkan ulang untuk produksi

### D.1 — `.env` (jangan pernah disalin dari lingkungan pengembangan)

Perbandingan KUNCI saja (tanpa nilai) antara `.env.example` dan `.env` pengembangan:
- Hanya ada di `.env` (tidak di `.env.example`): **`WHATSAPP_FONNTE_TOKEN`** — kunci
  rahasia gerbang WhatsApp, sengaja tidak dicontohkan di `.env.example` (praktik
  yang benar). Nilainya **tidak dikutip** di laporan ini.
- Hanya ada di `.env.example` (tidak di `.env`): **tidak ada** — seluruh kunci
  `.env.example` juga ada di `.env` pengembangan.

☐ `APP_ENV=production`
☐ `APP_DEBUG=false`
☐ `APP_KEY` — buat baru lewat `php artisan key:generate` DI SERVER PRODUKSI;
  **jangan pernah memakai `APP_KEY` dari `.env` pengembangan.**
☐ `APP_URL` — domain produksi sesungguhnya (dipakai untuk kode QR pada dokumen
  bukti permintaan dan BAST — bila salah, kode QR pada dokumen tercetak akan
  menunjuk ke alamat yang keliru).
☐ `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` —
  kredensial basis data produksi (bukan `sqlite` pengembangan).
☐ `WHATSAPP_DRIVER` dan kunci gerbang yang relevan (`WHATSAPP_FONNTE_TOKEN` atau
  `WHATSAPP_OPENWA_ALAMAT`/`WHATSAPP_OPENWA_SESI`/`WHATSAPP_OPENWA_KUNCI`,
  tergantung gerbang yang dipakai) — kredensial produksi, bukan pengembangan.
☐ `LARAVEL_STORAGE_PATH` — **tidak muncul di `.env` maupun `.env.example` proyek
  ini**; ini murni variabel lingkungan proses yang dipakai skrip verifikasi/pengujian
  selama audit (di luar `.env`). **Tidak relevan untuk produksi** — tidak perlu
  didefinisikan di `.env` server, biarkan aplikasi memakai `storage/` bawaannya.

### D.2 — `public/hot`

☐ Pastikan `public/hot` **tidak ada** sebelum deploy. **Diverifikasi saat ini:
berkas ini TIDAK ADA** di repositori kerja (baik di git maupun di disk lokal).
Jika suatu saat muncul (tertinggal dari sesi `npm run dev`), **keberadaannya di
produksi akan membuat Vite mencoba memuat aset dari server pengembangan lokal dan
merusak tampilan** — jangan pernah membawanya ke server.

### D.3 — `public/build/`

☐ Folder ini **ada** di disk lokal (`assets/`, `manifest.json`) tetapi **tidak
dilacak git** (tercakup `.gitignore`: `/public/build`) — hasil `npm run build`
lokal terakhir. **Tidak bisa diandalkan dari git untuk deploy**: harus dibangun
ulang (`npm install && npm run build`) tepat sebelum upload, atau dibangun di
server, agar konsisten dengan versi kode `main` saat deploy — jangan menyalin
folder `public/build` lokal yang sudah usang begitu saja.

### D.4 — `storage/framework/{cache,sessions,views}`

☐ Struktur folder **sudah benar**: masing-masing punya `.gitignore` lokal
(`*` lalu `!.gitignore`, dan `storage/framework/cache/` tambahan `!data/`) yang
menjaga folder tetap ada di git tanpa ikut membawa isinya. **Isinya (bukan
foldernya) harus dikosongkan sebelum deploy** — di lingkungan kerja lokal saat ini
folder-folder ini berisi cache/sesi/tampilan dari pengembangan aktif, yang tidak
boleh ikut ke server produksi.

### D.5 — Izin tulis folder

☐ `storage/` dan `bootstrap/cache/` harus dapat ditulis oleh proses web server
(mis. `www-data`) di server produksi — **tidak dapat diverifikasi dari sini**,
wajib dicek langsung di server sesudah upload.

### D.6 — Dokumen privat (hasil C-001) dan `storage:link`

☐ Konfigurasi disk privat pada `.env` produksi (`FILESYSTEM_DISK=local` sama
seperti pengembangan) mengarah ke lokasi yang benar — **konsisten**, tidak perlu
diubah.

☐☐☐ **JANGAN PERNAH menjalankan `php artisan storage:link` di server produksi.**
Perintah itu membuat symlink `public/storage` yang membuka kembali celah keamanan
A-002/C-001 yang sudah ditutup — dokumen bukti permintaan dan BAST akan dapat
diakses **tanpa login**. Ini bukan langkah rutin instalasi Laravel yang boleh
dijalankan begitu saja di proyek ini.

☐ **Ditemukan pada repositori kerja saat ini**: `storage/app/public/` masih berisi
15 dokumen (14 bukti permintaan + 1 BAST, bertanggal 15–18 September, sebelum
migrasi C-001) yang tidak tercatat sebagai sudah dipindah. Sebelum server
diizinkan diakses publik: jalankan `php artisan simpbi:pindah-dokumen` (dry-run
dulu untuk melihat daftar, baru `--jalankan`, baru `--hapus-asli`) — **langkah
checklist untuk pemilik, TIDAK dijalankan dalam penyusunan laporan ini.**

### D.7 — Queue worker

☐ `NotifikasiService` dan `KirimPesanWhatsApp` berjalan lewat antrean (queue).
Server produksi butuh proses `php artisan queue:work` berjalan terus-menerus
(mis. dikelola Supervisor), bukan hanya `php artisan serve`.

### D.8 — Scheduler

☐ Perintah `permintaan:lepas-hold` (dan sapuan kedaluwarsa) butuh cron job yang
menjalankan `php artisan schedule:run` setiap menit di server.

### D.9 — Migration

☐ Jalankan `php artisan migrate --force` di server produksi (**bukan**
`migrate:fresh`, yang akan menghapus seluruh data).

### D.10 — Dependensi khusus pengembangan

☐ `composer.json` → `require-dev`: `fakerphp/faker`, `laravel/pail`,
`laravel/pint`, `laravel/sail`, `mockery/mockery`, `nunomaduro/collision`,
`phpunit/phpunit` — seluruhnya standar paket dev Laravel, otomatis tidak ikut
lewat `composer install --no-dev`. Tidak ada dependensi non-standar yang perlu
perhatian khusus.

☐ `package.json` → `dependencies`: **kosong** (axios sudah dihapus pada Batch 6).
`devDependencies` hanya perkakas build (`vite`, `tailwindcss`,
`@tailwindcss/vite`, `laravel-vite-plugin`, `concurrently`) — **tidak ada
dependensi Node yang dibutuhkan saat aplikasi berjalan**; Node.js hanya diperlukan
pada tahap build (`npm run build`), bukan di server produksi itu sendiri, selama
`public/build/` hasil build sudah diunggah.

## 5. Hasil verifikasi tambahan (§E)

**E.1 — Riwayat `.env` di git.** `git log --all --full-history -- .env` **kosong**:
`.env` **tidak pernah ter-*commit*** di riwayat mana pun. Pemeriksaan tambahan pada
pola `.env.*` menemukan 4 commit lama (`901b02d`, `27fca71`, `ca9ec1f`, `acc8c56`)
yang seluruhnya **hanya menyentuh `.env.example`** (diverifikasi lewat
`git show --stat`) — bukan `.env` sungguhan. **Status: AMAN.**

**E.2 — Kredensial hardcode di kode.** `grep -riE "password|secret|api[_-]?key|token"`
pada `app/` dan `config/` menemukan banyak kecocokan, tetapi **seluruhnya adalah
pola kode sah**: nama field (`password`, `password_confirmation`), pembacaan
`env()` (`env('DB_PASSWORD')`, `env('WHATSAPP_FONNTE_TOKEN')`,
`env('AWS_SECRET_ACCESS_KEY')`, dst.), token QR yang dibangkitkan acak
(`Str::random(40)`), dan satu teks tampilan hint demo ("kata sandi: password" pada
`ResetDemo.php` — bukan kredensial sungguhan, hanya pemberitahuan bahwa akun demo
memakai sandi bawaan `password`). **Tidak ditemukan nilai kredensial yang
di-hardcode.** **Status: AMAN.**

**E.3 — Kelengkapan `.gitignore`.** Pola berikut **sudah ada** di `.gitignore`
akar: `.env`, `/vendor`, `/node_modules`, `/storage/*.key`, `/public/hot`,
`/public/build`. Pola berikut **tidak ada secara harfiah di `.gitignore` akar**,
tetapi **tercakup lewat `.gitignore` bertingkat** di masing-masing folder
(pola Laravel standar `*` + `!.gitignore`, diverifikasi ada dan benar):
`storage/framework/cache/*`, `storage/framework/sessions/*`,
`storage/framework/views/*`, `storage/logs/*.log` — jadi **secara fungsional
sudah aman**, meski tidak persis berbentuk pola yang diminta di akar. **Satu
celah kecil ditemukan**: pola `~$*` hanya dicakup **terbatas pada `docs/`**
(`/docs/~$*`), **bukan pola global** — bila berkas kunci Excel sementara muncul
di folder lain (mis. `database/data/`), berkas itu tidak otomatis diabaikan.
Rekomendasi: tambahkan pola global `~$*` (tanpa awalan folder) di `.gitignore` akar.

## 6. Rekomendasi cara deploy yang aman

Cara paling aman **bukan** menyalin seluruh repositori lalu menghapus yang tidak
perlu (berisiko ada yang terlewat, termasuk risiko §C.7 di atas bila document root
sempat salah arah walau sesaat) — melainkan **menyalin HANYA daftar putih
berkas/folder yang benar-benar dibutuhkan aplikasi berjalan**, ke lokasi yang
document root-nya sejak awal diarahkan ke `public/` di dalamnya:

```
app/
bootstrap/            (tanpa isi lama bootstrap/cache/*.php — biarkan kosong,
                        akan diisi ulang otomatis)
config/
database/             (hanya folder migrations/, factories/, seeders/,
                        data/*.contoh.* — TANPA database.sqlite,
                        TANPA database/data/*.xlsx yang berisi data pribadi,
                        bila produksi memakai MySQL)
public/                (termasuk build/ hasil `npm run build` terbaru)
resources/
routes/
storage/                (struktur folder kosong saja — lihat §D.4)
vendor/                (hasil `composer install --no-dev` DI SERVER, atau
                        diunggah terpisah sebagai paket — bukan dari git)
.env                    (dibuat baru di server, lihat §D.1 — JANGAN disalin
                        dari pengembangan)
artisan
composer.json
composer.lock
package.json
package-lock.json      (hanya bila build dijalankan di server, bukan diunggah
                        hasil build-nya)
```

**Tidak ikut ke server**: `.git/`, `docs/`, `tests/`, `*.docx` akar,
`node_modules/` (kecuali build dijalankan di server), `database/database.sqlite`,
`database/data/*.xlsx` (non-`.contoh`), dan seluruh berkas Kategori 1/2 pada
laporan ini.
