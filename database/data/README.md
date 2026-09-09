# Sumber Data Seeder

Folder ini menampung berkas sumber (Excel/CSV) yang dibaca oleh seeder pada
`database/seeders`. Berkas di sini bukan berkas yang diunggah pengguna saat
sistem berjalan — berkas unggahan tersimpan di `storage/app`.

## Aturan data pribadi

Berkas yang memuat data pribadi pegawai **tidak ikut masuk repositori**.
`.gitignore` mengabaikan seluruh `*.xlsx` dan `*.csv` di folder ini, kecuali
berkas berakhiran `.contoh.xlsx` / `.contoh.csv` yang hanya berisi struktur
kolom dengan data karangan. Dengan begitu pembimbing, penguji, atau siapa pun
yang menyalin repositori tetap mengetahui bentuk datanya tanpa ikut menerima
nomor WhatsApp dan NIP pegawai yang sebenarnya.

Salin berkas contoh, isi dengan data sebenarnya, lalu simpan dengan nama tanpa
`.contoh` agar terbaca seeder dan tetap diabaikan Git.

## nomor-wa-ketua-tim.xlsx

Daftar Ketua Tim dari delapan tim kerja BPS Kota Jakarta Barat beserta nomor
WhatsApp-nya, dipakai untuk mengisi kolom `no_hp` pada tabel `users` sebagai
tujuan pengiriman notifikasi kanal WhatsApp (UC-23).

| Kolom | Keterangan |
| --- | --- |
| `No` | Nomor urut, tidak dipakai sistem |
| `Tim` | Nama tim kerja; harus sama persis dengan `tim.nama_tim` |
| `Ketua Tim` | Nama lengkap, mengisi `users.name` |
| `Nomor WhatsApp` | Format E.164 tanpa tanda plus, contoh `628129xxxxxxx` |
| `E-Mail` | Alamat surel dinas, mengisi `users.email` |
| `NIP` | 18 digit, mengisi `users.nip` dan menjadi kunci pencocokan baris |

### Hal yang perlu diperhatikan saat menyunting

**Format kolom nomor sebagai Teks.** Bila diketik sebagai angka biasa, nol di
depan hilang dan nomor panjang berubah menjadi notasi ilmiah.

**Jangan awali nomor dengan tanda plus.** Pada berkas yang diunduh dari Google
Spreadsheet, enam dari delapan nomor tersimpan sebagai rumus `=+62…` karena
tanda plus di awal sel ditafsirkan sebagai awal rumus, sementara dua sisanya
tersimpan sebagai bilangan bulat. Nilai yang terbaca karena itu tidak seragam,
sehingga pembaca berkas wajib menyaring seluruh karakter bukan angka sebelum
memakai nilainya.

**Pencocokan memakai NIP, bukan nama tim.** Penulisan nama tim pada berkas
sumber dapat berbeda tipis dari `tim.nama_tim` — misalnya "Statistik Pertanian
dan Industi" yang kehilangan satu huruf dibanding "Statistik Pertanian dan
Industri" pada `TimSeeder`. NIP bersifat unik dan stabil, sehingga lebih aman
dijadikan kunci pencocokan.
