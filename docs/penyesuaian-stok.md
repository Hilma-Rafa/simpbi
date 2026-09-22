# Penyesuaian Stok Fisik (T-57)

## Kesimpulan

> Penyesuaian stok fisik dilakukan melalui fungsi **Ubah Barang Persediaan** oleh
> **Admin Sistem** atau **Kasubbag Umum**. Sistem memberikan konfirmasi sebelum
> perubahan dan menyelaraskan informasi yang bergantung pada stok fisik.
> Penyesuaian ini merupakan **perubahan data induk dan bukan transaksi mutasi
> stok**, sehingga tidak menghasilkan baris baru pada Kartu Kendali.

SIMPBI **tidak** memiliki fungsi Stok Opname maupun Koreksi Stok tersendiri.

---

## A. Mengapa tidak ada fungsi Stok Opname tersendiri

Rancangan sistem tidak mensyaratkannya. Penelusuran terhadap Use Case Diagram,
Activity Diagram, ERD, kedua dokumen proses bisnis, revisi sempro, dan Analisis
Kebutuhan tidak menemukan satu pun use case, diagram, maupun langkah proses
untuk stok opname atau koreksi stok.

Satu-satunya "pengecekan fisik" yang dirancang adalah **UC-10 / P.2.10–P.2.11**,
dan itu merupakan bagian dari alur permintaan barang:

> *"Petugas Gudang menginput hasil pengecekan fisik berupa jumlah barang yang
> benar-benar tersedia, kondisi, dan keterangan tambahan jika ada selisih antara
> stok sistem dan stok fisik."*

Selisih itu disimpan pada `detail_permintaan_barang.jumlah_verif_fisik` dan
`kondisi_verif` sebagai **bahan keputusan Kasubbag Umum**, bukan sebagai
penyesuaian persediaan — dan fungsi itu sudah terimplementasi.

Nilai `koreksi` dan `stok_opname` pada tabel mutasi stok disediakan sebagai ruang
bagi pengembangan lanjutan dan belum dipakai pada ruang lingkup penelitian ini.
Keberadaan nilai enum tidak dengan sendirinya mewajibkan adanya fungsi khusus.

---

## B. Siapa yang dapat melakukannya

| Peran | Ubah Barang Persediaan |
|---|---|
| Admin Sistem | ✅ |
| Kasubbag Umum | ✅ |
| Petugas Gudang | ❌ |
| Ketua Tim | ❌ |
| Tim | ❌ |

Pembagian ini mengikuti kewenangan data induk yang sudah berlaku: Kasubbag Umum
adalah pengelola inventaris, sedangkan Petugas Gudang menangani transaksi —
pencatatan stok masuk dan penyiapan barang. Petugas Gudang menyampaikan hasil
pengecekan fisiknya melalui tahap verifikasi pada alur permintaan.

---

## C. Konfirmasi sebelum perubahan

Ketika nilai **Stok Fisik** pada formulir berbeda dengan yang tersimpan,
penyimpanan ditahan dan sistem menampilkan dialog:

> **Perubahan Stok Fisik**
>
> Stok fisik *{nama barang}* akan diubah dari **{stok lama} {satuan}** menjadi
> **{stok baru} {satuan}**.
>
> Perubahan ini akan memengaruhi jumlah stok tersedia, katalog permintaan barang,
> serta ringkasan dan peringatan stok pada dasbor.
>
> Penyesuaian ini merupakan perubahan data induk, bukan transaksi mutasi stok,
> sehingga tidak menghasilkan baris baru pada Kartu Kendali.
>
> Pastikan jumlah tersebut sesuai dengan kondisi fisik barang di gudang sebelum
> melanjutkan.
>
> **[Ya, Simpan Perubahan]** **[Batal]**

Sifatnya:

- **Menjangkau seluruh jalur simpan.** Penjaganya dipasang pada kait penyimpanan,
  bukan pada tombolnya, sehingga tombol Simpan, tombol Enter, dan pintasan papan
  tik sama-sama melewatinya.
- **Membatalkan berarti tidak ada yang tersimpan.** Transaksi basis data digulung
  balik seluruhnya — bukan hanya stoknya, tetapi juga kolom lain yang ikut diubah
  pada penyuntingan yang sama.
- **Hanya muncul ketika stok fisik benar-benar berubah.** Menyunting nama, satuan,
  atau stok minimum saja tidak memicunya.
- **Ditanyakan setiap kali.** Penyuntingan kedua pada halaman yang sama tetap
  dimintai persetujuan.

Kata-katanya sengaja tidak menjanjikan pencatatan transaksi. Kalimat seperti
"koreksi akan dicatat" akan membuat pengguna mencarinya di Kartu Kendali —
tempat perubahan ini tidak akan pernah muncul.

---

## D. Informasi yang ikut mutakhir

Seluruh pembaca stok menghitung langsung dari kolom `stok_fisik`; tidak ada nilai
turunan yang disimpan terpisah dan dapat tertinggal basi.

| Tampilan | Yang dibaca |
|---|---|
| Tabel Barang Persediaan | `stok_fisik`, dan stok tersedia (`stok_fisik − stok_hold`) |
| Katalog Barang | stok tersedia, tombol permintaan, batas jumlah yang boleh diminta |
| Panel **Kondisi Stok** (dasbor) | jumlah stok fisik dan terkunci per kategori |
| Panel **Barang Perlu Perhatian** (dasbor) | stok terendah dan barang yang mencapai stok minimum |
| Ringkasan Kasubbag | Stok Kritis dan Barang Tidak Tersedia |
| Notifikasi stok menipis | ambang `stok_minimum` |

---

## E. Keterbatasan — dinyatakan terus terang

**Kartu Kendali tidak memuat penyesuaian ini.** Kartu Kendali bersumber dari tabel
`mutasi_stok`, yaitu catatan transaksi barang masuk dan keluar. Penyesuaian lewat
Ubah Barang Persediaan adalah perubahan data induk dan **tidak menerbitkan baris
mutasi**, sehingga:

1. selisihnya tidak tampil sebagai transaksi pada kartu;
2. segera sesudah penyesuaian, jumlah stok tercatat dapat berbeda dari kolom
   Sisa pada kartu;
3. pada transaksi stok berikutnya, selisih itu terserap sebagai saldo pembawaan,
   dan kolom Sisa pada baris-baris sebelumnya ikut bergeser — kartu yang dicetak
   sebelum penyesuaian karena itu dapat berbeda dari kartu yang dicetak
   sesudahnya, meskipun tidak ada transaksi yang dibatalkan.

Ini konsekuensi yang disengaja dari keputusan T-57, bukan cacat: menerbitkan
baris mutasi hanya agar kartu terlihat selaras berarti mencatat transaksi barang
yang tidak pernah terjadi di gudang.

**Kartu Kendali karena itu merepresentasikan transaksi mutasi yang tercatat,
bukan histori setiap perubahan manual pada stok fisik.**

Konsekuensinya bagi prosedur: penyesuaian stok sebaiknya didahului kesepakatan
tertulis di luar sistem — berita acara pemeriksaan fisik atau catatan setara —
sebab sistem tidak menyimpan alasan maupun pelaku penyesuaiannya.

**Catatan teknis.** Menurunkan stok fisik sampai di bawah jumlah seluruh mutasi
yang tercatat membuat saldo pembawaan menjadi negatif. Transaksi stok masuk
berikutnya untuk barang tersebut akan ditolak dengan pesan bahwa sisa stok
menjadi negatif pada tanggal tertentu — pesan itu menunjuk tanggal transaksi,
bukan penyesuaian yang menjadi sebabnya.
