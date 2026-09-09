# Panduan Pemasangan Kanal Notifikasi WhatsApp (UC-23)

Dokumen ini menjelaskan cara menyalakan kanal WhatsApp pada SIMPBI, dari
memasang gerbang sampai memastikan pesan benar-benar sampai. Ditulis untuk
pemasangan pertama kali, dan dapat dipakai kembali ketika nomor gerbang harus
diganti.

Acuan gerbang: **OpenWA 0.23.4** (`github.com/rmyndharis/OpenWA`). Tampilan dan
nama tombolnya dapat berbeda pada versi lain; yang tidak berubah adalah alur
dan endpoint-nya.

---

## 1. Yang perlu dipahami lebih dulu

**Kanal WhatsApp bersifat pelengkap, bukan tulang punggung.** Notifikasi dalam
aplikasi terbit lebih dulu dan tidak bergantung pada keberhasilan WhatsApp.
Kalau gerbang mati atau nomornya diblokir, alur kerja SIMPBI tetap berjalan
utuh — yang hilang hanya pemberitahuan tambahan lewat ponsel.

**Sistem tidak akan mengirim apa pun sebelum dinyalakan dengan sengaja.**
Bawaan pemasangan adalah pelaksana `catat`, yang hanya menulis pesan ke berkas
log. Ini disengaja supaya salinan sistem yang baru dipasang tidak pernah tidak
sengaja mengirim pesan ke nomor pegawai sungguhan.

**Gerbang ini memakai klien WhatsApp tidak resmi.** WhatsApp tidak
mengizinkannya secara resmi, dan selalu ada kemungkinan nomor dibatasi atau
diblokir. Karena itu langkah pertama di bawah bukan soal perangkat lunak,
melainkan soal nomor.

---

## 2. Prasyarat

| Kebutuhan | Keterangan |
| --- | --- |
| **Nomor WhatsApp khusus** | Kartu SIM tersendiri untuk sistem. **Jangan** memakai nomor pribadi maupun nomor dinas yang dipakai berkomunikasi sehari-hari. Bila nomor ini diblokir, tidak ada percakapan penting yang ikut hilang. |
| **Ponsel untuk memindai** | Diperlukan sekali saat pemasangan, dan sesekali bila sesi terputus. |
| **Docker dan Docker Compose** | Gerbang dijalankan sebagai kontainer. |
| **Memori peladen** | Mesin `whatsapp-web.js` memakai sekitar 300–500 MB per sesi. SIMPBI hanya butuh satu sesi. |
| **Akses ke berkas `.env` SIMPBI** | Untuk mengisi alamat gerbang dan kunci API. |

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

Nomor gerbang tertaut ke OpenWA sebagai *perangkat tertaut*, dan WhatsApp
memutus seluruh perangkat tertaut bila ponsel pemegang nomor tidak aktif selama
**14 hari**. Begitu terputus, seluruh pengiriman ditolak sampai kode QR dipindai
ulang.

Karena ponsel yang khusus dipakai untuk ini biasanya jarang disentuh, jadikan
kebiasaan menyalakannya dan membuka WhatsApp sebentar setidaknya dua minggu
sekali. Tanda-tanda sesi terputus mudah dikenali: seluruh notifikasi baru
berstatus **Gagal** dengan keterangan *"Sesi gerbang belum tersambung ke
WhatsApp."*

---

## 3. Memasang gerbang OpenWA

```bash
git clone https://github.com/rmyndharis/OpenWA.git
cd OpenWA
docker compose -f docker-compose.dev.yml up -d
```

Setelah kontainer berjalan:

- Dasbor: <http://localhost:2785>
- API: <http://localhost:2785/api>
- Dokumentasi API interaktif: <http://localhost:2785/api/docs>

**Pilihan mesin.** OpenWA dapat memakai `whatsapp-web.js` (bawaan) atau
`baileys`, diatur lewat variabel `ENGINE_TYPE`. Untuk SIMPBI **pertahankan
bawaannya**: `whatsapp-web.js` menjalankan Chromium sungguhan sehingga lalu
lintasnya menyerupai WhatsApp Web biasa dan risiko pemblokirannya lebih rendah,
dengan imbalan pemakaian memori lebih besar. Penghematan memori `baileys` baru
berarti bila sesinya banyak, sedangkan SIMPBI hanya memakai satu.

**Kunci API pertama.** OpenWA membuat kunci bootstrap saat pertama kali
dijalankan dan menyimpannya pada berkas `.api-key` di dalam direktori kerjanya.
Ambil nilainya dari sana, atau dari dasbor bila versi Anda menyediakannya.
Simpan kunci ini seperti kata sandi — siapa pun yang memilikinya dapat mengirim
pesan atas nama nomor gerbang.

---

## 4. Membuat sesi dan memindai kode QR

Ganti `KUNCI_API` dengan kunci dari langkah sebelumnya.

**Buat sesi bernama `simpbi`:**

```bash
curl -X POST http://localhost:2785/api/sessions \
  -H "Content-Type: application/json" \
  -H "X-API-Key: KUNCI_API" \
  -d '{"name": "simpbi"}'
```

**Jalankan sesinya:**

```bash
curl -X POST http://localhost:2785/api/sessions/simpbi/start \
  -H "X-API-Key: KUNCI_API"
```

**Ambil kode QR-nya:**

```bash
curl http://localhost:2785/api/sessions/simpbi/qr \
  -H "X-API-Key: KUNCI_API"
```

Jawabannya berisi gambar QR dalam bentuk data URL:
`{"qrCode": "data:image/png;base64,...", "status": "qr_ready"}`. Tempelkan
nilai `qrCode` ke bilah alamat peramban untuk menampilkannya, lalu pindai dari
ponsel bernomor khusus tadi melalui **WhatsApp → Perangkat Tertaut → Tautkan
Perangkat**. Lebih mudah lagi bila dasbor OpenWA sudah menampilkan QR-nya
langsung.

Sesi dianggap siap ketika statusnya bukan lagi `qr_ready`. Selama status masih
menunggu pemindaian, setiap pengiriman akan ditolak dengan kode 409 — dan
SIMPBI akan mencatatnya sebagai *"Sesi gerbang belum tersambung ke WhatsApp.
Pindai ulang kode QR pada dasbor OpenWA."*

---

## 5. Menghubungkan SIMPBI ke gerbang

Isikan pada berkas `.env` SIMPBI:

```dotenv
WHATSAPP_DRIVER=openwa
WHATSAPP_OPENWA_ALAMAT=http://127.0.0.1:2785
WHATSAPP_OPENWA_SESI=simpbi
WHATSAPP_OPENWA_KUNCI=KUNCI_API
```

Lalu bersihkan singgahan konfigurasi, karena Laravel menyimpan berkas `.env`
yang sudah dibaca:

```bash
php artisan config:clear
```

Bila gerbang dijalankan di mesin lain, ganti `127.0.0.1` dengan alamat mesin
tersebut dan pastikan porta 2785 hanya terbuka untuk peladen SIMPBI, **bukan
untuk umum**.

---

## 6. Menyalakan kanal dari dalam aplikasi

Penyalaan kanal sengaja **tidak** diatur lewat `.env`, melainkan lewat aplikasi,
supaya dapat dimatikan sewaktu-waktu tanpa akses peladen — misalnya ketika
nomor bermasalah menjelang jam kerja.

Masuk sebagai **Admin Sistem** → menu **Pengaturan** → bagian **Pengaturan
Sistem** → nyalakan **Aktifkan notifikasi WhatsApp** → **Simpan Perubahan**.

Sebelum togel ini menyala, SIMPBI tidak menerbitkan satu pun baris notifikasi
berkanal WhatsApp.

---

## 7. Menjalankan antrean

Pengiriman dikerjakan lewat antrean supaya permintaan yang sedang dikerjakan
pengguna tidak perlu menunggu gerbang menjawab, dan supaya jeda antar pesan
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

## 8. Uji coba pertama

1. Pastikan Ketua Tim yang akan diuji sudah memiliki nomor pada data
   penggunanya. Pengguna tanpa nomor tidak dibuatkan baris WhatsApp sama sekali.
2. Ajukan satu permintaan barang dari akun Tim.
3. Buka **Riwayat → Notifikasi** sebagai Admin.
4. Baris berkanal **WhatsApp** harus berstatus **Terkirim**, dan pesannya masuk
   ke ponsel Ketua Tim.

Bila statusnya **Gagal**, alasannya tertulis tepat di bawah statusnya. Lanjut ke
daftar periksa berikut.

---

## 9. Daftar periksa saat pesan tidak sampai

Keterangan pada kolom status di halaman Riwayat → Notifikasi sudah menyebutkan
penyebabnya. Padanannya:

| Keterangan yang muncul | Penyebab | Tindakan |
| --- | --- | --- |
| Sesi gerbang belum tersambung ke WhatsApp | Sesi terputus atau belum dipindai | Pindai ulang kode QR (langkah 4) |
| Kunci API OpenWA ditolak | `WHATSAPP_OPENWA_KUNCI` salah atau kedaluwarsa | Perbarui kunci di `.env`, lalu `php artisan config:clear` |
| Sesi "…" tidak ditemukan pada gerbang | Nama sesi berbeda dengan yang ada di gerbang | Samakan `WHATSAPP_OPENWA_SESI` dengan nama sesi sebenarnya |
| Gerbang OpenWA tidak dapat dihubungi | Kontainer mati, alamat salah, atau porta terblokir | `docker compose ps`, periksa `WHATSAPP_OPENWA_ALAMAT` |
| Gerbang membatasi laju pengiriman | Pesan terlalu rapat | Perbesar `WHATSAPP_JEDA_DETIK` pada `.env` |
| Gerbang OpenWA mengalami gangguan internal | Masalah di sisi gerbang | Periksa log kontainer OpenWA |
| Nomor WhatsApp pengguna belum diisi atau tidak dikenali | Kolom nomor kosong atau formatnya tidak terbaca | Lengkapi lewat menu Pengguna, format `628…` |
| Gerbang WhatsApp "…" belum terkonfigurasi | Salah satu dari alamat, sesi, atau kunci kosong | Lengkapi keempat variabel pada langkah 5 |

Setelah penyebabnya dibereskan, baris yang gagal dapat dikirim ulang: pilih
barisnya di **Riwayat → Notifikasi** lalu tekan **Kirim Ulang**, atau centang
beberapa baris sekaligus dan gunakan **Kirim Ulang yang Gagal**. Pesan yang
sudah terkirim tidak dapat diulang, supaya penerima tidak menerima pesan yang
sama dua kali.

**Bila status tetap "Menunggu" dan tidak berubah**, kemungkinan besar pekerja
antrean tidak berjalan. Periksa proses `queue:work` pada langkah 7.

---

## 10. Mematikan kanal kembali

Ada dua tingkat, pilih sesuai keperluan:

- **Sementara, dari dalam aplikasi:** matikan togel di menu Pengaturan. Baris
  WhatsApp berhenti diterbitkan, notifikasi dalam aplikasi tetap berjalan.
- **Sampai ke pemasangan:** kembalikan `WHATSAPP_DRIVER=catat` pada `.env` lalu
  `php artisan config:clear`. Pesan hanya ditulis ke `storage/logs` dan tidak
  dikirim ke mana pun. Berguna saat menyiapkan demonstrasi atau ketika nomor
  gerbang sedang bermasalah.

---

## 11. Catatan operasional dan keterbatasan

**Risiko pemblokiran nomor tetap ada.** Dokumentasi OpenWA sendiri menyatakan
*"There is always a non-zero risk of account restriction or ban"* dan
menyarankan memakai nomor yang siap dikorbankan, serta tidak menyarankan
pemakaiannya pada lingkungan teregulasi. Untuk instansi pemerintah, hal ini
perlu disampaikan apa adanya sebagai keterbatasan sistem, bukan disembunyikan.

**Cara memperkecil risiko:** pakai nomor khusus, kirim hanya notifikasi
transaksional yang volumenya rendah, pertahankan jeda antar pesan, dan jangan
sekali-kali memakai gerbang ini untuk pesan siaran.

**Bila suatu saat dibutuhkan jalur resmi,** WhatsApp Cloud API dari Meta dapat
dipasang tanpa mengubah alur notifikasi: cukup menambahkan satu pelaksana baru
yang memenuhi antarmuka `App\Services\WhatsApp\PengirimWhatsApp`, lalu
mendaftarkannya pada `AppServiceProvider` dan memilihnya lewat
`WHATSAPP_DRIVER`. Bagian sistem yang lain tidak perlu disentuh.

**Kalau pesan tidak pernah terkirim pun sistem tetap sah dipakai.** Seluruh
keputusan dan bukti transaksi tersimpan di dalam aplikasi; WhatsApp hanya
mempercepat pemberitahuannya.

---

## Ringkasan berkas terkait

| Berkas | Isi |
| --- | --- |
| `config/whatsapp.php` | Pilihan pelaksana, jeda antar pesan, banyaknya percobaan, kredensial gerbang |
| `app/Services/WhatsApp/PengirimWhatsApp.php` | Kontrak pengirim, agar gerbang dapat diganti |
| `app/Services/WhatsApp/PengirimOpenWa.php` | Pelaksana untuk gerbang OpenWA |
| `app/Services/WhatsApp/PengirimCatat.php` | Pelaksana bawaan, hanya mencatat ke log |
| `app/Jobs/KirimPesanWhatsApp.php` | Pengiriman satu baris notifikasi lewat antrean |
| `app/Services/NotifikasiService.php` | Penerbitan baris notifikasi kedua kanal |
| `tests/Feature/PengirimanWhatsAppTest.php` | Pengujian otomatis seluruh perilaku di atas |
