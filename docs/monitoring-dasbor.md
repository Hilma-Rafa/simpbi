# Metrik Monitoring pada Dasbor SIMPBI

Dokumen ini menerangkan **apa yang sebenarnya dihitung** tiap panel analisis.
Keempat panel di bawah sekilas menjawab pertanyaan yang mirip, padahal
besarannya berbeda-beda — dan kekeliruan membacanya tidak menimbulkan galat apa
pun: panelnya tetap tampil, angkanya tetap masuk akal, dan hanya salah.

Seluruh panel pada halaman ini hanya tampil bagi **Kasubbag Umum**, kecuali Pola
Permintaan yang tampil bagi seluruh peran dengan cakupan data mengikuti
kewenangan masing-masing.

---

## Ringkasan

| Panel | Yang dihitung | Sumber | Satuan |
|---|---|---|---|
| Pola Permintaan | Banyaknya **dokumen permintaan** per satuan waktu | `permintaan_barang.created_at` | permintaan |
| Barang Paling Sering Diminta | **Berapa kali** tiap barang muncul pada permintaan | `detail_permintaan_barang` | kali diminta |
| Permintaan per Tim Kerja | Banyaknya **dokumen permintaan** tiap tim | `permintaan_barang` | permintaan |
| Pengeluaran per Tim Kerja | **Volume barang** yang keluar untuk tiap tim | `mutasi_stok` (`jenis = keluar`) | unit barang |
| Tren Konsumsi | **Volume barang** yang keluar per bulan, dapat disaring per kategori | `mutasi_stok` (`jenis = keluar`) | unit barang |

**"Paling sering diminta" bukan "paling banyak keluar".** Keduanya sengaja
dibedakan dan tidak boleh disamakan:

- *paling sering diminta* mengukur **frekuensi permintaan** — sisi pemohon,
  termasuk permintaan yang akhirnya ditolak atau kedaluwarsa;
- *paling banyak keluar* mengukur **volume yang benar-benar meninggalkan
  gudang** — sisi persediaan, hanya yang sudah diterima pemohon.

Peringkat barang menurut volume keluar tidak disajikan di dasbor. Angkanya sudah
ada pada kolom **Keluar** halaman Kartu Kendali, yang memang rekap operasionalnya.

---

## Barang Paling Sering Diminta

Peringkat memakai **frekuensi**, yaitu banyaknya permintaan yang memuat barang
tersebut. Jumlah yang diminta ditampilkan sebagai angka pendamping, sebab satu
barang yang diminta sepuluh kali masing-masing satu rim berbeda artinya bagi
perencanaan pengadaan daripada barang yang diminta sekali sebanyak sepuluh rim.

Kaki panel berbunyi **"N kali diminta pada peringkat ini"**. Angka itu:

- menjumlahkan frekuensi **sepuluh barang teratas saja**, bukan seluruh katalog;
- **bukan** banyaknya dokumen permintaan — satu permintaan berisi tiga barang
  menyumbang tiga.

Karena itu penyebutannya tidak boleh memakai kata "permintaan" begitu saja.

---

## Pengeluaran per Tim Kerja

Panel *Permintaan per Tim Kerja* menyediakan dua besaran lewat satu pemilih,
sebab rancangan memang meminta keduanya dan sumbunya sama:

| Pilihan | Yang dihitung |
|---|---|
| Jumlah permintaan | Banyaknya dokumen permintaan tim tersebut |
| Barang disalurkan | Jumlah unit barang yang keluar untuk tim tersebut |

### Keterbatasan yang diketahui: satuan tercampur

Pada besaran **Barang disalurkan**, angka yang dijumlahkan adalah kuantitas
mentah dari buku besar mutasi stok, **tanpa memperhatikan satuan barangnya**.
300 lembar kertas dan 2 dus tinta terbaca sebagai `302 unit barang`.

Ini disengaja dan tidak akan diubah: SIMPBI tidak menyimpan faktor konversi
antar satuan, dan mengarangnya akan menghasilkan angka yang tampak lebih rapi
tetapi tidak dapat dipertanggungjawabkan. Konsekuensinya, tim yang banyak
meminta barang bersatuan kecil akan selalu tampak paling boros pada panel ini.

Perbandingan yang memperhatikan satuan tersedia per barang pada halaman **Kartu
Kendali**, di mana tiap baris memakai satuannya sendiri. Istilah "unit barang"
di sini mengikuti penyebutan yang sudah dipakai ringkasan halaman Stok Masuk.

### Tautan baris

Pada besaran *Jumlah permintaan*, tiap baris tertaut ke daftar Permintaan Barang
yang sudah tersaring pada tim tersebut — dokumen-dokumen itulah angkanya.

Pada besaran *Barang disalurkan*, **baris sengaja tidak tertaut**. Buku besar
mutasi stok tidak dapat disaring per tim kerja, dan mengarahkan pembaca ke
daftar permintaan akan menampilkan belasan dokumen di bawah angka yang berbunyi
ratusan unit. Sebagai gantinya, judul panel mengarah ke **Kartu Kendali**, rekap
barang keluar yang memang tersedia.

---

## Tren Konsumsi

Menjumlahkan barang yang **benar-benar keluar** per bulan selama dua belas bulan
terakhir, dapat disaring per kategori persediaan. Sumbernya buku besar mutasi
stok — sama dengan Kartu Kendali — sehingga angkanya tidak mungkin berselisih
dengan kartu.

Bukan jumlah yang *diminta*: permintaan yang ditolak atau kedaluwarsa tidak
pernah mengurangi persediaan, sehingga memasukkannya akan menggambarkan konsumsi
yang tidak pernah terjadi.

Berbentuk batang, bukan garis. Konsumsi adalah jumlah yang terkumpul sepanjang
satu bulan; garis akan menyiratkan adanya nilai antara dua bulan yang dapat
dibaca, padahal tidak ada.

---

## Periode pengamatan

Panel yang menawarkan pilihan periode memakai definisi yang sama: **30 hari**,
**90 hari**, dan **seluruh periode**. Batasnya dipatok pada **awal hari**, bukan
pada jam saat panel dibuka.

Patokan itu penting karena panel *Permintaan per Tim Kerja* menyaring dua kolom
yang berbeda jenisnya — `permintaan_barang.created_at` bertanda waktu, sedangkan
`mutasi_stok.tanggal` hanya bertanggal. Tanpa patokan awal hari, berganti besaran
diam-diam menggeser jendela pengamatan hampir sehari, dan transaksi di tepi
periode masuk pada satu besaran tetapi hilang pada besaran lain.

---

## Ketersediaan data

Sistem masih relatif baru dan datanya belum banyak. Panel yang belum punya data
menampilkan keadaan kosong beserta keterangannya, bukan grafik nol. Tidak ada
data contoh yang ditanam untuk membuat grafiknya terlihat berisi.
