# Panduan Pemasangan Kanal Notifikasi WhatsApp (UC-23)

Dokumen ini menjelaskan cara menyalakan kanal WhatsApp pada SIMPBI, dari
menautkan nomor sampai memastikan pesan benar-benar sampai. Ditulis untuk
pemasangan pertama kali, dan dapat dipakai kembali ketika nomor gerbang harus
diganti.

Gerbang yang dipakai adalah **Fonnte**, layanan WhatsApp gateway yang berjalan
sepenuhnya di peladen penyedianya. Tidak ada perangkat lunak yang perlu dipasang
di komputer maupun peladen SIMPBI — tidak ada Docker, tidak ada kontainer, tidak
ada kebutuhan memori tambahan. Yang dibutuhkan hanya satu nomor WhatsApp dan
satu token.

Bagi yang lebih memilih memasang gerbangnya sendiri, jalur OpenWA tetap
didukung sistem dan diringkas pada **Lampiran A**.

---

## 1. Yang perlu dipahami lebih dulu

**Kanal WhatsApp bersifat pelengkap, bukan tulang punggung.** Notifikasi dalam
aplikasi terbit lebih dulu dan tidak bergantung pada keberhasilan WhatsApp.
Kalau gerbang bermasalah atau nomornya diblokir, alur kerja SIMPBI tetap
berjalan utuh — yang hilang hanya pemberitahuan tambahan lewat ponsel.

**Sistem tidak akan mengirim apa pun sebelum dinyalakan dengan sengaja.**
Bawaan pemasangan adalah pelaksana `catat`, yang hanya menulis pesan ke berkas
log. Ini disengaja supaya salinan sistem yang baru dipasang tidak pernah tidak
sengaja mengirim pesan ke nomor pegawai sungguhan.

**Layanan ini memakai klien WhatsApp tidak resmi.** Fonnte menautkan nomor Anda
sebagai perangkat tertaut, cara yang sama dengan gerbang yang dipasang sendiri.
Karena itu, meskipun tidak ada yang perlu dipasang, **risiko nomor dibatasi atau
diblokir tetap ada**. Langkah pertama di bawah karena itu bukan soal perangkat
lunak, melainkan soal nomor.

---

## 2. Prasyarat

| Kebutuhan | Keterangan |
| --- | --- |
| **Nomor WhatsApp khusus** | Kartu SIM tersendiri untuk sistem. **Jangan** memakai nomor pribadi maupun nomor dinas yang dipakai berkomunikasi sehari-hari. Bila nomor ini diblokir, tidak ada percakapan penting yang ikut hilang. |
| **Ponsel untuk memindai** | Diperlukan sekali saat pemasangan, dan sesekali bila perangkat terputus. |
| **Akun Fonnte** | Pendaftaran gratis di <https://fonnte.com>. |
| **Akses ke berkas `.env` SIMPBI** | Untuk mengisi token. |

Tidak ada kebutuhan Docker, virtualisasi, maupun memori tambahan pada komputer
Anda — seluruh gerbang berjalan di sisi Fonnte.

### Berapa nomor yang sebenarnya dibutuhkan

**Satu nomor baru, untuk seluruh sistem.** Yang menanggung risiko hanyalah akun
yang melakukan otomasi, yaitu pengirimnya. Para penerima — Ketua Tim, Kasubbag,
Petugas Gudang, dan akun Tim — cukup memakai nomor yang sudah mereka pakai
sehari-hari, sebab menerima pesan adalah kegiatan WhatsApp yang biasa dan tidak
berisiko. Tidak ada seorang pun yang perlu menyediakan nomor kedua.

Sebaiknya nomor gerbang menjadi milik Sub Bagian Umum, bukan milik pribadi
seseorang, agar tidak ikut berpindah ketika pegawai yang bersangkutan berganti
tugas.

### Menjaga sesi tetap hidup

Nomor gerbang tertaut ke Fonnte sebagai *perangkat tertaut*, dan WhatsApp
memutus seluruh perangkat tertaut bila ponsel pemegang nomor tidak aktif selama
**14 hari**. Begitu terputus, seluruh pengiriman ditolak sampai kode QR dipindai
ulang di dasbor Fonnte.

Karena ponsel yang khusus dipakai untuk ini biasanya jarang disentuh, jadikan
kebiasaan menyalakannya dan membuka WhatsApp sebentar setidaknya dua minggu
sekali. Bila demonstrasi sistem masih beberapa minggu lagi, **pindai kode QR
mendekati harinya**, bukan jauh-jauh hari.

---

## 3. Menautkan nomor di Fonnte

1. **Daftar dan masuk** ke dasbor Fonnte (<https://fonnte.com>).
2. **Tambahkan perangkat** pada halaman *Device*, memakai nomor WhatsApp khusus
   yang sudah disiapkan.
3. **Pindai kode QR** yang ditampilkan, dari ponsel bernomor tersebut, lewat
   **WhatsApp → Perangkat Tertaut → Tautkan Perangkat**.
4. **Salin token perangkat** dari halaman *Token (API key)*.

> **Perhatikan jenis tokennya.** Fonnte membedakan **token perangkat** (device
> token) dan **token akun** (account token). Yang dipakai untuk mengirim pesan
> adalah **token perangkat**. Bila keliru, pengiriman akan ditolak dan SIMPBI
> mencatatnya sebagai *"Token Fonnte ditolak. Periksa WHATSAPP_FONNTE_TOKEN pada
> berkas .env."*

Perlakukan token seperti kata sandi: siapa pun yang memilikinya dapat mengirim
pesan atas nama nomor gerbang. Token **tidak boleh** ikut masuk ke repositori —
tempatnya hanya di berkas `.env`, yang sudah diabaikan Git.

### Paket dan kuota

Fonnte menyediakan paket gratis yang memadai untuk pengembangan dan pengujian,
dengan batas jumlah pesan per bulan dan tanda air pada pesan yang dikirim.
Volume SIMPBI sangat kecil — belasan pesan sehari pada pemakaian normal —
sehingga paket gratis umumnya cukup untuk uji coba dan demonstrasi. Bila tanda
air itu mengganggu pada tangkapan layar untuk laporan, paket berbayar termurah
sudah menghilangkannya. Periksa ketentuan terbaru pada halaman harga Fonnte,
sebab paket dan batasnya dapat berubah.

---

## 4. Menghubungkan SIMPBI ke Fonnte

Isikan pada berkas `.env` SIMPBI:

```dotenv
WHATSAPP_DRIVER=fonnte
WHATSAPP_FONNTE_TOKEN=token-perangkat-dari-dasbor-fonnte
```

Lalu bersihkan singgahan konfigurasi, karena Laravel menyimpan berkas `.env`
yang sudah dibaca:

```bash
php artisan config:clear
```

Tidak ada alamat peladen yang perlu diisi: alamat API Fonnte sudah tetap di
dalam kode. Batas waktu satu panggilan dapat diubah lewat
`WHATSAPP_FONNTE_BATAS_DETIK` bila diperlukan, bawaannya 15 detik.

> **Pastikan `APP_URL` benar.** Notifikasi yang menuntut tindakan menyertakan
> tautan langsung ke halaman transaksinya, dan tautan itu dibentuk dari
> `APP_URL`. Selama nilainya masih `http://localhost`, tautan yang terkirim
> tidak dapat dibuka penerima. Isi dengan alamat sebenarnya begitu sistem
> ditempatkan di peladen, misalnya `APP_URL=https://simpbi.bps3174.go.id`.

---

## 5. Menyalakan kanal dari dalam aplikasi

Penyalaan kanal sengaja **tidak** diatur lewat `.env`, melainkan lewat aplikasi,
supaya dapat dimatikan sewaktu-waktu tanpa akses peladen — misalnya ketika nomor
bermasalah menjelang jam kerja.

Masuk sebagai **Admin Sistem** → menu **Pengaturan** → bagian **Pengaturan
Sistem** → nyalakan **Aktifkan notifikasi WhatsApp** → **Simpan Perubahan**.

Sebelum togel ini menyala, SIMPBI tidak menerbitkan satu pun baris notifikasi
berkanal WhatsApp.

---

## 6. Menjalankan antrean

Pengiriman dikerjakan lewat antrean supaya permintaan yang sedang dikerjakan
pengguna tidak perlu menunggu Fonnte menjawab, dan supaya jeda antar pesan
benar-benar berlaku.

Pada `.env`:

```dotenv
QUEUE_CONNECTION=database
```

Tabel `jobs` sudah tersedia dari migrasi bawaan, jadi tidak ada migrasi
tambahan. Jalankan pekerjanya:

```bash
php artisan queue:work --queue=whatsapp --tries=3
```

Proses ini harus **terus berjalan**. Di Linux biasanya dijaga oleh Supervisor;
di Windows dapat dijalankan sebagai layanan (misalnya lewat NSSM) atau lewat
Task Scheduler yang menjalankannya saat mesin menyala.

Selama `QUEUE_CONNECTION` masih `sync`, sistem tetap bekerja tetapi pengiriman
terjadi langsung di dalam permintaan HTTP, sehingga jeda antar pesan tidak
berlaku dan percobaan ulang otomatis tidak jalan.

> **Terkait.** SIMPBI juga memiliki pekerjaan terjadwal `permintaan:lepas-hold`
> yang melepaskan kunci stok pada permintaan yang melewati batas waktu. Agar
> berjalan, penjadwal Laravel juga perlu aktif — `php artisan schedule:work`
> saat pengembangan, atau satu entri cron/Task Scheduler yang memanggil
> `php artisan schedule:run` setiap menit di lingkungan sebenarnya.

---

## 7. Uji coba pertama

1. Pastikan Ketua Tim yang akan diuji sudah memiliki nomor pada data
   penggunanya. Pengguna tanpa nomor tidak dibuatkan baris WhatsApp sama sekali.
2. Ajukan satu permintaan barang dari akun Tim.
3. Buka **Riwayat → Notifikasi** sebagai Admin.
4. Baris berkanal **WhatsApp** harus berstatus **Terkirim**, dan pesannya masuk
   ke ponsel Ketua Tim.

Bila statusnya **Gagal**, alasannya tertulis tepat di bawah statusnya. Lanjut ke
daftar periksa berikut.

---

## 8. Daftar periksa saat pesan tidak sampai

Keterangan pada kolom status di halaman Riwayat → Notifikasi sudah menyebutkan
penyebabnya. Padanannya:

| Keterangan yang muncul | Penyebab | Tindakan |
| --- | --- | --- |
| Token Fonnte ditolak | Token salah, kedaluwarsa, atau tertukar dengan token akun | Salin ulang **token perangkat** dari dasbor, perbarui `.env`, lalu `php artisan config:clear` |
| Kuota pengiriman Fonnte habis | Batas paket terlampaui | Isi ulang paket pada dasbor Fonnte |
| Perangkat pada Fonnte belum tersambung | Nomor terputus dari Fonnte, biasanya karena ponsel lama tidak aktif | Pindai ulang kode QR pada halaman Device |
| Nomor tujuan ditolak Fonnte | Nomor penerima tidak sah atau bukan pengguna WhatsApp | Perbaiki nomor lewat menu Pengguna, format `628…` |
| Permintaan ditolak Fonnte karena isian tidak lengkap | Ada parameter yang tidak terkirim | Periksa log aplikasi; laporkan bila berulang |
| Layanan Fonnte tidak dapat dihubungi | Jaringan peladen terputus atau Fonnte sedang bermasalah | Periksa koneksi internet peladen, coba lagi beberapa saat kemudian |
| Layanan Fonnte mengembalikan galat (HTTP 5xx) | Gangguan di sisi Fonnte | Tunggu dan kirim ulang |
| Nomor WhatsApp pengguna belum diisi atau tidak dikenali | Kolom nomor kosong atau formatnya tidak terbaca | Lengkapi lewat menu Pengguna |
| Gerbang WhatsApp "fonnte" belum terkonfigurasi | `WHATSAPP_FONNTE_TOKEN` kosong | Isi token pada langkah 4 |

Setiap keterangan selalu menyertakan alasan asli dari Fonnte di dalam tanda
kurung, untuk penelusuran lebih lanjut.

Setelah penyebabnya dibereskan, baris yang gagal dapat dikirim ulang: pilih
barisnya di **Riwayat → Notifikasi** lalu tekan **Kirim Ulang**, atau centang
beberapa baris sekaligus dan gunakan **Kirim Ulang yang Gagal**. Pesan yang
sudah terkirim tidak dapat diulang, supaya penerima tidak menerima pesan yang
sama dua kali.

**Bila status tetap "Menunggu" dan tidak berubah**, kemungkinan besar pekerja
antrean tidak berjalan. Periksa proses `queue:work` pada langkah 6.

**Bila status "Terkirim" tetapi pesan tidak sampai**, periksa riwayat pengiriman
pada dasbor Fonnte. Status Terkirim berarti Fonnte sudah menerima dan mengantre
pesannya; pengantaran ke ponsel penerima terjadi setelah itu dan berada di luar
jangkauan SIMPBI.

---

## 9. Mematikan kanal kembali

Ada dua tingkat, pilih sesuai keperluan:

- **Sementara, dari dalam aplikasi:** matikan togel di menu Pengaturan. Baris
  WhatsApp berhenti diterbitkan, notifikasi dalam aplikasi tetap berjalan.
- **Sampai ke pemasangan:** kembalikan `WHATSAPP_DRIVER=catat` pada `.env` lalu
  `php artisan config:clear`. Pesan hanya ditulis ke `storage/logs` dan tidak
  dikirim ke mana pun. Berguna saat menyiapkan demonstrasi atau ketika nomor
  gerbang sedang bermasalah.

---

## 10. Catatan operasional dan keterbatasan

**Risiko pemblokiran nomor tetap ada.** Memakai layanan yang di-hosting memang
menghilangkan kerepotan memasang gerbang, tetapi tidak mengubah kenyataan bahwa
nomor tertaut sebagai perangkat WhatsApp Web dan otomasinya tidak diizinkan
secara resmi. Untuk instansi pemerintah, hal ini perlu disampaikan apa adanya
sebagai keterbatasan sistem, bukan disembunyikan.

**Cara memperkecil risiko:** pakai nomor khusus, kirim hanya notifikasi
transaksional yang volumenya rendah, pertahankan jeda antar pesan
(`WHATSAPP_JEDA_DETIK`, bawaan 5 detik), dan jangan sekali-kali memakai gerbang
ini untuk pesan siaran.

**Ketergantungan pada pihak ketiga.** Berbeda dengan gerbang yang dipasang
sendiri, ketersediaan kanal ini bergantung pada layanan Fonnte dan pada paket
yang masih aktif. Kalau layanannya bermasalah, notifikasi dalam aplikasi tetap
jalan dan alur kerja tidak terganggu.

**Bila suatu saat dibutuhkan jalur resmi,** WhatsApp Cloud API dari Meta dapat
dipasang tanpa mengubah alur notifikasi: cukup menambahkan satu pelaksana baru
yang memenuhi antarmuka `App\Services\WhatsApp\PengirimWhatsApp`, lalu
mendaftarkannya pada `AppServiceProvider` dan memilihnya lewat
`WHATSAPP_DRIVER`. Bagian sistem yang lain tidak perlu disentuh.

**Kalau pesan tidak pernah terkirim pun sistem tetap sah dipakai.** Seluruh
keputusan dan bukti transaksi tersimpan di dalam aplikasi; WhatsApp hanya
mempercepat pemberitahuannya.

---

## Lampiran A — Alternatif: gerbang OpenWA yang dipasang sendiri

Pelaksana `openwa` tetap tersedia di dalam sistem bagi yang ingin menjalankan
gerbangnya sendiri, misalnya karena tidak ingin bergantung pada layanan pihak
ketiga. Konsekuensinya: butuh Docker, sekitar 300–500 MB memori untuk satu sesi,
dan pemeliharaan kontainer menjadi tanggung jawab sendiri.

```bash
git clone https://github.com/rmyndharis/OpenWA.git
cd OpenWA
docker compose -f docker-compose.dev.yml up -d
```

Dasbor pada <http://localhost:2785>, dokumentasi API pada `/api/docs`. Kunci API
bootstrap tersimpan pada berkas `.api-key` di direktori kerjanya. Pertahankan
mesin bawaan `whatsapp-web.js` — risiko pemblokirannya lebih rendah dibanding
`baileys` karena menjalankan Chromium sungguhan.

Membuat sesi dan mengambil kode QR:

```bash
curl -X POST http://localhost:2785/api/sessions \
  -H "Content-Type: application/json" -H "X-API-Key: KUNCI_API" \
  -d '{"name": "simpbi"}'

curl -X POST http://localhost:2785/api/sessions/simpbi/start -H "X-API-Key: KUNCI_API"

curl http://localhost:2785/api/sessions/simpbi/qr -H "X-API-Key: KUNCI_API"
```

Lalu pada `.env` SIMPBI:

```dotenv
WHATSAPP_DRIVER=openwa
WHATSAPP_OPENWA_ALAMAT=http://127.0.0.1:2785
WHATSAPP_OPENWA_SESI=simpbi
WHATSAPP_OPENWA_KUNCI=KUNCI_API
```

Langkah 5 sampai 9 pada panduan utama berlaku sama. Keterangan kegagalannya
berbeda kata tetapi setara maknanya — misalnya *"Sesi gerbang belum tersambung
ke WhatsApp. Pindai ulang kode QR pada dasbor OpenWA."*

Bila porta gerbang dibuka dari mesin lain, pastikan hanya peladen SIMPBI yang
dapat menjangkaunya, **bukan umum**.

---

## Ringkasan berkas terkait

| Berkas | Isi |
| --- | --- |
| `config/whatsapp.php` | Pilihan pelaksana, jeda antar pesan, banyaknya percobaan, kredensial tiap gerbang |
| `app/Services/WhatsApp/PengirimWhatsApp.php` | Kontrak pengirim, agar gerbang dapat diganti |
| `app/Services/WhatsApp/PengirimFonnte.php` | Pelaksana untuk layanan Fonnte |
| `app/Services/WhatsApp/PengirimOpenWa.php` | Pelaksana untuk gerbang OpenWA yang dipasang sendiri |
| `app/Services/WhatsApp/PengirimCatat.php` | Pelaksana bawaan, hanya mencatat ke log |
| `app/Jobs/KirimPesanWhatsApp.php` | Pengiriman satu baris notifikasi lewat antrean |
| `app/Services/NotifikasiService.php` | Penerbitan baris notifikasi kedua kanal |
| `app/Filament/Pages/Riwayat.php` | Riwayat pengiriman dan aksi kirim ulang |
| `tests/Feature/PengirimanFonnteTest.php` | Pengujian otomatis pelaksana Fonnte |
| `tests/Feature/PengirimanWhatsAppTest.php` | Pengujian otomatis kontrak, job, dan pelaksana OpenWA |
