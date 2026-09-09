SIMPBI --- MASTER DESIGN & IMPLEMENTATION BRIEF

UI/UX, Produk, Arsitektur, Workflow, Role, Use Case, Data, dan Aturan Implementasi

Sistem: SIMPBI --- Sistem Informasi Manajemen Permintaan Barang dan
Inventaris
Instansi: Sub-Bagian Umum, Badan Pusat Statistik Kota Jakarta Barat
Konteks: Skripsi D-IV Komputasi Statistik, Politeknik Statistika
STIS
Target implementasi: Laravel 12 + Filament 5 + Tailwind CSS + MySQL
8
Tujuan file: menjadi single source of truth untuk implementasi di
IDE/Claude, terutama agar implementasi UI/UX tidak menyimpang dari
rancangan skripsi.

0. ATURAN PALING PENTING --- JANGAN MENGUBAH RANCANGAN

Claude/AI coding assistant bertindak sebagai IMPLEMENTER, bukan
sebagai system analyst yang bebas mendesain ulang sistem.

Seluruh implementasi harus mengikuti rancangan yang sudah dibuat.

DILARANG mengubah tanpa persetujuan pengguna/pembimbing

BPMN / proses bisnis;

Business Process yang sudah disepakati;

Use Case Diagram;

Activity Diagram;

ERD / struktur basis data;

jumlah role/aktor;

nama role/aktor;

kewenangan dan hak akses setiap role;

workflow persetujuan;

status transaksi;

mekanisme stok;

relasi antartabel;

scope dan batasan sistem;

jumlah unit/tim yang telah ditetapkan;

fitur yang sudah masuk rancangan;

aturan bisnis yang telah ditetapkan;

arsitektur three-tier yang telah dirancang.

Jangan melakukan hal berikut

Jangan membuat role baru.

Jangan menghapus role.

Jangan memecah satu role menjadi beberapa role.

Jangan menggabungkan role.

Jangan membuat approval baru.

Jangan menghapus tahapan approval.

Jangan membuat workflow baru.

Jangan menambahkan tabel baru hanya demi kebutuhan UI.

Jangan mengubah ERD agar sebuah chart atau halaman lebih mudah
dibuat.

Jangan mengubah status transaksi karena kebutuhan visual.

Jangan menambahkan fitur "umum pada aplikasi modern" jika tidak ada
dalam rancangan.

Jangan membuat data dummy seolah-olah data produksi.

Jangan mengubah proses bisnis hanya karena Filament memiliki pola UI
tertentu.

Jika UI membutuhkan sesuatu yang tampaknya bertentangan dengan rancangan

Jangan langsung mengubah rancangan.

Sampaikan sebagai:

"Kebutuhan UI ini membutuhkan perubahan pada rancangan X. Saya tidak
mengubahnya. Berikut opsi implementasi yang tetap mempertahankan
rancangan."

Prioritas:

Rancangan skripsi
        ↓
BPMN / Proses Bisnis
        ↓
Use Case
        ↓
Activity Diagram
        ↓
ERD / Data Model
        ↓
Authorization & Business Logic
        ↓
Backend
        ↓
UI / UX

UI mengikuti sistem, bukan sistem mengikuti UI.

1. SUMBER ACUAN DAN PRIORITAS

Dokumen implementasi ini menggabungkan:

Dokumen rancangan yang diberikan pengguna dalam ZIP:

Arsitektur Sistem.docx

Probis Permintaan Barang di BPS Kota Jakarta Barat.docx

Probis Usulan Permintaan Barang.docx

Probis Usulan Pencatatan Administratif Aset Tetap Kategori Peralatan dan Mesin.docx

Analisis Kebutuhan.docx

Use Case Diagram.docx

Activity DIagram.docx

Rancangan/brief implementasi yang telah dibuat sebelumnya.

Referensi visual yang dikirim sebelumnya:

contoh dokumen permintaan dengan tanggal, QR, dan blok
nama/tanda tangan;

screenshot website/dashboard milik teman sebagai inspirasi;

website referensi https://tmskripsi.biz.id/.

Catatan implementasi proyek yang telah dibuat sebelumnya.

Jika terdapat perbedaan antara contoh visual dan rancangan
sistem, rancangan sistem selalu menang.

Jika terdapat perbedaan antara kebutuhan visual dan workflow, workflow
selalu menang.

2. IDENTITAS SISTEM

Nama

SIMPBI

Kepanjangan

Sistem Informasi Manajemen Permintaan Barang dan Inventaris

Instansi

Sub-Bagian Umum --- Badan Pusat Statistik Kota Jakarta Barat

Karakter produk

SIMPBI adalah sistem internal pemerintahan untuk:

manajemen permintaan barang persediaan;

pengendalian stok;

distribusi/pengeluaran barang;

monitoring;

pelaporan;

pencatatan administratif penempatan, redistribusi, dan mutasi aset
tetap kategori peralatan dan mesin.

SIMPBI bukan sistem pengadaan barang.

SIMPBI bukan pengganti SIMAK-BMN.

3. SCOPE SISTEM

3.1 Barang persediaan

Objek utama:

alat tulis;

kertas;

amplop;

ordner dan map;

tinta dan toner printer;

bahan kegiatan statistik;

barang persediaan lain yang sesuai master.

Sistem mencakup:

Katalog
→ Permintaan
→ Persetujuan
→ Verifikasi fisik
→ Persetujuan akhir
→ Penyiapan
→ Pengambilan
→ Konfirmasi
→ Pengesahan
→ Monitoring / laporan

3.2 Aset tetap --- Peralatan dan Mesin

Mencakup aspek administratif:

penempatan;

redistribusi;

mutasi antar unit kerja;

BAST;

pengesahan BAST;

konfirmasi penerimaan;

riwayat penempatan/mutasi.

Aset tetap tidak masuk ke alur permintaan barang persediaan.

3.3 Di luar scope

pengadaan barang dari pihak eksternal;

penggantian SIMAK-BMN;

barang sementara/titipan/hibah yang belum tercatat sebagai BMN;

pengujian kinerja teknis;

analisis dampak finansial;

workflow procurement eksternal.

4. ROLE / AKTOR --- TETAP 5, JANGAN DIUBAH

Sistem memiliki 5 aktor utama:

No                      Aktor                   Kewenangan inti

1                       Admin Sistem        Mengelola akun
pengguna, data induk,
dan pengaturan sistem

2                       Tim                 Mengajukan permintaan
barang dan
mengonfirmasi
penerimaan

3                       Ketua Tim           Menyetujui/menolak
permintaan dari timnya;
pada modul aset
mengonfirmasi
penerimaan aset

4                       Petugas Gudang      Verifikasi stok fisik,
penyiapan barang, stok
masuk, dan pencatatan
administratif tertentu

Aturan akun Tim

Akun Tim adalah akun bersama yang digunakan anggota tim.

Nama dan NIP pemohon diisi pada setiap pengajuan.

Jangan membuat role "Anggota Tim" baru.

Catatan penting

Pada transaksi permintaan barang, aktor yang terlibat langsung adalah:

Tim;

Ketua Tim;

Petugas Gudang;

Kasubbag Umum.

Admin Sistem tetap merupakan aktor sistem, tetapi tidak menjalankan alur
operasional permintaan.

5. DELAPAN UNIT / TIM KERJA

Tetap gunakan 8 unit berikut:

Sub Bagian Umum

Statistik Sosial

Statistik Pertanian dan Industri

Statistik Pertambangan, Energi dan Konstruksi

Statistik Distribusi

Neraca Wilayah dan Analisis Statistik

Integrasi Pengolahan dan Diseminasi Statistik

Pembinaan Statistik Sektoral

Jangan menambah/menghapus unit hanya untuk membuat chart terlihat lebih
penuh.

6. USE CASE --- 25 USE CASE, JANGAN DIUBAH

Sistem memiliki 25 use case.

ID                      Use Case                Aktor Utama

UC-01                   Login                   Admin Sistem, Tim,
Ketua Tim, Petugas
Gudang, Kasubbag Umum

UC-02                   Mengelola Data Pengguna Admin Sistem

UC-03                   Mengelola Master Barang Admin Sistem
Persediaan

UC-04                   Mengelola Master Aset   Admin Sistem
Tetap Kategori
Peralatan dan Mesin

UC-05                   Mengelola Kategori      Admin Sistem
Barang

UC-06                   Mengelola Data Tim      Admin Sistem

UC-07                   Mencatat Stok Masuk     Petugas Gudang
Barang

UC-08                   Mengajukan Permintaan   Tim, Ketua Tim
Barang

UC-09                   Menyetujui Permintaan   Ketua Tim
oleh Ketua Tim

UC-10                   Memverifikasi Stok      Petugas Gudang
Fisik Barang

UC-11                   Memberikan Persetujuan  Kasubbag Umum
Akhir Permintaan

UC-12                   Menyiapkan Barang       Petugas Gudang
Permintaan

UC-13                   Mengambil dan           Tim, Ketua Tim
Mengonfirmasi
Penerimaan Barang

UC-14                   Mencatat                Petugas Gudang
Ketidaksesuaian Barang

UC-15                   Memberikan Pengesahan   Kasubbag Umum
Final Transaksi

UC-16                   Membuat Form BAST       Petugas Gudang
Mutasi Aset

UC-17                   Mengesahkan BAST Mutasi Kasubbag Umum
Aset

UC-18                   Mengonfirmasi           Ketua Tim
Penerimaan Aset

UC-19                   Melihat Dashboard       Kasubbag Umum; Ketua
Monitoring              Tim, Petugas Gudang
sebagai aktor lain

UC-20                   Melihat Riwayat         Tim, Ketua Tim, Petugas
Transaksi Permintaan    Gudang, Kasubbag Umum

UC-21                   Melihat Riwayat Mutasi  Petugas Gudang,
Aset                    Kasubbag Umum, Ketua
Tim

UC-22                   Melihat Pola Permintaan Kasubbag Umum
dan Tren

UC-23                   Mengelola Notifikasi    Semua 5 aktor

UC-24                   Mengekspor atau         Kasubbag Umum; Petugas
Mencetak Laporan        Gudang sebagai aktor
lain

Relasi penting

UC-01 menjadi include pada use case yang membutuhkan login.

UC-22 memiliki pola tampilan yang dapat berbagi komponen dengan
dashboard monitoring.

UC-25 merupakan extend dari riwayat transaksi untuk melihat detail
item/transaksi.

Jangan membuat UC-26 hanya karena sebuah halaman membutuhkan nama
fitur baru.

7. ACTIVITY DIAGRAM --- 22 DIAGRAM

Rancangan menyebut terdapat 25 use case, tetapi Activity Diagram dibuat
untuk 22 use case.

Tiga yang tidak dibuat sebagai diagram terpisah:

UC-05 Mengelola Kategori Barang --- alurnya identik dengan CRUD
master lain.

UC-22 Melihat Pola Permintaan dan Tren --- polanya identik dengan
UC-19.

UC-25 Melihat Detail Transaksi --- extend sederhana dari riwayat.

Implementasi UI boleh menggunakan komponen yang sama, tetapi jangan
mengubah arti use case.

8. PROSES BISNIS PERMINTAAN BARANG --- WAJIB DIPERTAHANKAN

Fase 1 --- Login & Pengajuan

Login
→ Katalog
→ pilih barang
→ keranjang
→ isi pemohon + keperluan
→ submit

Sistem melakukan HOLD stok.

Percabangan pengaju

Jika pengaju adalah Ketua Tim:

Submit
→ langsung Petugas Gudang
→ verifikasi stok fisik

Tahap persetujuan Ketua Tim dilewati.

Jika pengaju adalah Tim:

Submit
→ menunggu persetujuan Ketua Tim

9. FASE 2 --- PERSETUJUAN KETUA TIM

Jika pengaju adalah akun Tim:

Ketua Tim membuka notifikasi
→ melihat detail
→ setuju / tolak

Jika ditolak:

status ditolak
→ RELEASE HOLD
→ notifikasi ke Tim
→ selesai

Jika disetujui:

status menunggu_verifikasi
→ notifikasi Petugas Gudang

Jangan menambahkan approval lain di antara Ketua Tim dan Gudang.

10. FASE 3 --- VERIFIKASI STOK FISIK

Petugas Gudang:

membuka detail;

melihat data stok sistem;

melakukan pengecekan fisik;

memasukkan:

jumlah tersedia;

kondisi;

keterangan selisih jika ada.

Sistem menyimpan hasil verifikasi dan mengirim notifikasi ke Kasubbag
Umum.

Data stok sistem bukan pengganti pengecekan fisik.

11. FASE 4 --- PERSETUJUAN AKHIR KASUBBAG

Kasubbag Umum:

membuka notifikasi;

meninjau laporan fisik;

menentukan keputusan.

Bisa:

menyetujui penuh;

menyetujui sebagian/partial;

menolak.

Jika partial:

jumlah final < jumlah diminta
→ sebagian HOLD di-release
→ HOLD disesuaikan dengan jumlah final

Jika ditolak:

RELEASE HOLD
→ status ditolak
→ proses berakhir

Jika disetujui:

status siap_diproses
→ notifikasi Petugas Gudang

12. FASE 5 --- PENYIAPAN BARANG

Petugas Gudang:

buka tugas
→ ambil barang fisik
→ siapkan sesuai jumlah final
→ konfirmasi siap diambil

Sistem:

status siap_diambil
→ catat petugas
→ notifikasi Tim/Ketua Tim sesuai pengaju

13. FASE 6 --- PENGAMBILAN & KONFIRMASI

Pemohon mengambil dan memeriksa barang.

Jika sesuai:

konfirmasi penerimaan
→ KONVERSI HOLD
→ stok fisik berkurang permanen
→ mutasi stok tercatat
→ dashboard diperbarui
→ notifikasi Kasubbag

Jika tidak sesuai:

Petugas Gudang mengevaluasi.

Jika dapat diatasi langsung:

tukar/lengkapi
→ pemohon cek ulang
→ loop

Jika tidak dapat diatasi:

catat ketidaksesuaian
→ status bermasalah
→ RELEASE HOLD
→ notifikasi Kasubbag
→ proses berakhir

Jangan membuat pengguna mengajukan permintaan baru jika
ketidaksesuaian masih dapat diselesaikan secara operasional di gudang.

14. FASE 7 --- PENGESAHAN FINAL

Kasubbag Umum:

buka transaksi
→ pengesahan akhir
→ tanda tangan digital

Sistem:

status selesai
→ arsip transaksi
→ laporan diperbarui

15. STATUS PERMINTAAN --- JANGAN DIUBAH

Gunakan status berikut:

menunggu_ketua
menunggu_verifikasi
menunggu_kasubbag
siap_diproses
siap_diambil
menunggu_pengesahan
selesai
ditolak_ketua
ditolak_kasubbag
bermasalah
kedaluwarsa

Makna ringkas

Status                              Makna

menunggu_ketua                    menunggu keputusan Ketua Tim

menunggu_verifikasi               menunggu pengecekan fisik Gudang

menunggu_kasubbag                 menunggu persetujuan akhir Kasubbag

siap_diproses                     sudah disetujui dan menunggu
penyiapan

siap_diambil                      barang sudah disiapkan

menunggu_pengesahan               menunggu pengesahan final

selesai                           transaksi selesai

ditolak_ketua                     ditolak Ketua Tim

ditolak_kasubbag                  ditolak Kasubbag

bermasalah                        terdapat ketidaksesuaian yang tidak
dapat diatasi langsung

kedaluwarsa                       tahapan melewati batas waktu

16. PENGENDALIAN STOK --- HOLD / RELEASE / KONVERSI

Tiga konsep stok wajib dipertahankan.

HOLD

Terjadi saat permintaan disubmit.

stok_fisik tetap
stok_hold bertambah

RELEASE

Terjadi jika:

ditolak;

bermasalah;

kedaluwarsa;

partial approval untuk bagian yang tidak jadi diberikan.

stok_fisik tetap
stok_hold berkurang

KONVERSI

Terjadi setelah penerimaan dikonfirmasi.

stok_hold berkurang
stok_fisik berkurang
mutasi_stok tercatat

Rumus

stok_tersedia = stok_fisik - stok_hold

stok_tersedia bukan kolom penyimpanan terpisah.

Jangan menyimpan nilai turunan yang sama sebagai kolom baru hanya demi
memudahkan chart.

17. BATAS WAKTU

Nilai sementara yang telah dirancang:

Tahap                     Nilai sementara

Persetujuan Ketua Tim               3 jam
Verifikasi                          4 jam
Persetujuan Kasubbag                3 jam
Penyiapan                           4 jam
Pengambilan                        10 jam

Nilai ini masih memerlukan konfirmasi narasumber.

Perhitungan:

berlaku per tahap;

bukan akumulatif;

pemeriksaan terjadwal pada hari kerja;

waktu operasional sementara 08.00--16.00.

Jika melewati batas:

RELEASE HOLD
→ status kedaluwarsa

18. MODUL ASET TETAP --- ALUR BERBEDA DARI PERMINTAAN BARANG

Modul ini melibatkan:

Ketua Tim;

Kasubbag Umum;

Petugas Gudang.

Tahap awal --- koordinasi di luar sistem

Tim asal dan tim tujuan berkoordinasi dengan Sub-Bagian Umum.

Hasil koordinasi:

NUP;

tim asal;

tim tujuan;

alasan mutasi.

Tahap ini di luar sistem.

Fase 1 --- Generate BAST

Petugas Gudang:

login
→ Buat BAST Mutasi
→ isi NUP
→ tim asal
→ tim tujuan
→ alasan
→ pihak penyerah
→ pihak penerima
→ Kasubbag sebagai pihak yang mengetahui
→ submit

Sistem:

validasi
→ nomor BAST otomatis
→ generate PDF
→ status Menunggu Pengesahan
→ notifikasi Kasubbag

Posisi aset belum berubah pada fase ini.

Fase 2 --- Pengesahan BAST

Kasubbag:

buka notifikasi
→ lihat detail + preview
→ Sahkan

Sistem:

embed e-TTD
→ embed QR verifikasi
→ update penempatan aset
→ simpan riwayat mutasi
→ status menunggu konfirmasi
→ notifikasi Ketua Tim tujuan
→ notifikasi Petugas Gudang

Tahap manual

Serah-terima fisik aset dilakukan di luar sistem.

Fase 3 --- Konfirmasi

Ketua Tim tujuan:

buka detail mutasi
→ unduh BAST
→ pastikan aset sudah diterima
→ konfirmasi penerimaan

Sistem:

catat timestamp
→ riwayat mutasi
→ notifikasi Petugas Gudang
→ status selesai administratif

Aturan penting modul aset

Dokumen rancangan menyatakan:

sistem men-generate BAST;

mencatat BAST sah;

mencatat konfirmasi penerimaan;

tidak ada workflow approval tambahan atau jalur penolakan pada
subsistem mutasi aset.

Jangan membuat modul aset menggunakan workflow approval permintaan
barang.

19. USE CASE → MENU / UI MAPPING

Gunakan mapping ini tanpa mengubah use case:

Area UI                    Use Case

Login                      UC-01
Pengguna                   UC-02
Barang Persediaan          UC-03
Aset Tetap                 UC-04
Kategori Barang            UC-05
Tim                        UC-06
Stok Masuk                 UC-07
Katalog + Keranjang        UC-08
Approval Ketua             UC-09
Verifikasi Gudang          UC-10
Approval Kasubbag          UC-11
Penyiapan                  UC-12
Penerimaan                 UC-13
Ketidaksesuaian            UC-14
Pengesahan Final           UC-15
Buat BAST                  UC-16
Pengesahan BAST            UC-17
Konfirmasi Aset            UC-18
Dashboard Monitoring       UC-19
Riwayat Permintaan         UC-20
Riwayat Mutasi Aset        UC-21
Pola Permintaan & Tren     UC-22
Notifikasi                 UC-23
Laporan / Export / Cetak   UC-24
Detail Transaksi           UC-25

Satu halaman boleh mengimplementasikan beberapa use case, tetapi
jangan menciptakan use case baru hanya karena nama menu berbeda.

20. BASIS DATA / ERD --- JANGAN DIROMBAK DEMI UI

Rancangan yang digunakan memiliki kelompok tabel berikut.

Modul pengguna & katalog

users

tim

kategori

barang_persediaan

mutasi_stok

pengaturan

Modul permintaan

permintaan_barang

detail_permintaan_barang

riwayat_persetujuan

ketidaksesuaian_barang

Modul aset

aset_tetap

bast_mutasi_aset

riwayat_penempatan_aset

Modul notifikasi

notifikasi

Total baseline: 14 tabel.

Aturan

Jangan membuat activity_logs hanya untuk widget "Aktivitas
Terbaru".

Jangan membuat tabel keranjang; keranjang bersifat sementara.

Jangan membuat tabel stok_tersedia.

Jangan membuat tabel dashboard.

Jangan membuat tabel statistik.

Jangan membuat tabel chart.

Jangan membuat tabel role baru.

Jangan membuat tabel "approval" baru jika riwayat persetujuan sudah
menjadi sumbernya.

Catatan desain

Beberapa nilai yang secara teoritis dapat diturunkan memang sengaja
disimpan untuk kebutuhan rancangan/performa, misalnya:

saldo_sesudah pada mutasi_stok;

tim_penempatan_id pada aset_tetap.

Jangan menghapus atau menormalisasi ulang atribut tersebut hanya
karena AI menganggapnya redundant.

21. MASTER DATA BARANG

Struktur inti yang telah digunakan:

barang_persediaan

Field penting:

id
kategori_id
kode_barang
nama_barang
satuan
stok_fisik
stok_hold
stok_minimum
status_aktif

Aturan kode barang

Kode barang mengikuti kategori dan kondisi data aktual.

Jangan mengasumsikan kode barang harus unik global jika rancangan
menyatakan unik dalam kategori.

Stok minimum

stok_minimum = 0

berarti:

barang tidak dimonitor terhadap stok minimum.

Jika > 0:

barang dipantau.

Peringatan muncul ketika:

stok_tersedia <= stok_minimum

Jangan menampilkan istilah "barang tidak punya stok minimum" jika
maknanya sebenarnya "belum dimonitor".

22. MUTASI STOK / KARTU KENDALI

mutasi_stok menjadi dasar pencatatan pergerakan persediaan.

Jenis:

masuk
keluar
koreksi

Sumber dapat mencakup:

pembelian
transfer_masuk
stok_awal
pemakaian
pengembalian
reklasifikasi_aset
stok_opname

Kartu kendali barang persediaan merupakan sumber penting dalam konteks
penelitian.

Konteks data aktual

Berkas Kartu Kendali Barang Persediaan Tahun 2025 yang menjadi bahan
analisis memiliki:

119 lembar kerja;

471 baris transaksi;

variasi penulisan seperti "Pemakaian", "pemakaian", "PEMAKAIAN",
"Pemakian".

Hal ini menjadi alasan penting untuk standardisasi katalog dan
transaksi.

23. UI/UX --- ARAH DESAIN

SIMPBI jangan terasa seperti CRUD Laravel/Filament default.

Target:

profesional;

terpercaya;

modern;

institusional;

rapi;

compact untuk pekerjaan harian;

cukup premium untuk presentasi;

realistis untuk sistem internal pemerintahan.

Prinsip:

Tahu kondisi → tahu tindakan → tahu hasil.

Jangan membuat dashboard ramai hanya supaya terlihat "canggih".

24. REFERENSI VISUAL SEBELUMNYA

24.1 Contoh dokumen

Gunakan screenshot/dokumen yang sebelumnya dikirim sebagai referensi
untuk:

komposisi dokumen resmi;

tanggal;

QR;

identitas penandatangan;

posisi tanda tangan;

kesan dokumen administrasi BPS.

Bukan untuk menyalin mentah.

24.2 Screenshot website/dashboard teman

Gunakan sebagai inspirasi:

header;

hero;

KPI cards;

panel dashboard;

empty state;

kalender;

struktur visual;

hierarki informasi.

Jangan menyalin identitas, copywriting, logo, data, atau layout persis.

24.3 Website referensi

https://tmskripsi.biz.id/

Gunakan hanya sebagai inspirasi visual.

Yang boleh diinspirasi:

navy/dark blue;

orange/yellow sebagai accent;

hero kuat;

card putih;

typography;

iconography;

visual hierarchy;

landing page yang menjual nilai sistem.

Yang tidak boleh disalin:

layout identik;

copywriting;

logo;

ilustrasi;

aset;

animasi;

struktur halaman secara identik.

25. DESIGN TOKENS

Warna

Primary Navy        #0B2A5B
Primary Blue        #1557A6
Blue Light          #EAF3FB
Accent Orange       #F59E0B
Accent Orange Light #FFF7E6
Success             #16A34A
Danger              #DC2626
Warning             #D97706
Info                #2563EB
Neutral             #6B7280
Background          #F8FAFC
Card                #FFFFFF
Border              #E5E7EB

Penggunaan

Navy = identitas, heading, navigation.

Blue = primary action/informasi.

Orange = accent/perhatian.

Green = selesai/aman.

Red = ditolak/bermasalah.

Gray = netral/kedaluwarsa.

Jangan membuat semua card berwarna.

26. TYPOGRAPHY

Prioritas:

Inter, system-ui, sans-serif

Hierarki:

Elemen                 Ukuran    Weight

Hero                36--52 px       700
Page title          22--28 px       700
Section heading     18--22 px       600
Card title          14--16 px       600
Body                    14 px       400
Secondary           12--13 px       400
Table               13--14 px   400/500

Dashboard lebih compact daripada landing page.

27. SHAPE & SPACING

Skala:

4 / 8 / 12 / 16 / 20 / 24 / 32 / 40 / 48

Radius:

Input/button : 8px
Card         : 12–16px
Hero         : 20–28px
Modal        : 12–16px
Badge        : pill

Gunakan border tipis dan shadow ringan.

Hindari:

glassmorphism berat;

gradient berlebihan;

shadow besar;

ornamen yang mengganggu pekerjaan.

28. LOGIN PAGE

Login adalah pintu resmi ke sistem.

Konsep desktop dua sisi

┌──────────────────────────────┬──────────────────────────────┐
│                              │                              │
│          [LOGO BPS]          │      Selamat Datang          │
│                              │      di SIMPBI               │
│          SIMPBI              │                              │
│   BPS Kota Jakarta Barat     │      Email                   │
│                              │      [________________]      │
│ Sistem Informasi Manajemen   │                              │
│ Permintaan Barang dan        │      Password                │
│ Inventaris                   │      [________________]      │
│                              │                              │
│                              │      [      MASUK      ]     │
└──────────────────────────────┴──────────────────────────────┘

Branding panel:

navy;

logo BPS;

SIMPBI;

BPS Kota Jakarta Barat;

deskripsi.

Form:

putih;

fokus;

tidak ramai.

Field:

Email;

Password;

toggle tampil password;

Masuk;

loading;

validation;

error.

Tidak perlu:

registrasi publik;

Google login;

OTP;

kecuali scope benar-benar berubah.

29. LANDING PAGE

Landing page berbeda dari dashboard.

Tujuan:

menjelaskan masalah;

menjelaskan solusi;

menjelaskan alur;

menjelaskan manfaat;

mengarahkan ke login.

Navbar

[LOGO BPS] SIMPBI

Tentang   Fitur   Alur   Verifikasi

                      [ Masuk ke Sistem ]

Hero

SIMPBI

Sistem Informasi Manajemen
Permintaan Barang dan Inventaris

Kelola permintaan, ketersediaan persediaan,
dan distribusi barang dalam satu proses
yang terintegrasi dan dapat ditelusuri.

[ Masuk ke Sistem ]   [ Lihat Alur ]

Sub-identitas:

Sub-Bagian Umum
Badan Pusat Statistik Kota Jakarta Barat

Jangan membuat klaim peningkatan efisiensi dalam persentase jika belum
diuji.

30. LANDING --- SECTION YANG DIREKOMENDASIKAN

Mengapa SIMPBI?

Stok lebih mudah dipantau

Informasi stok fisik, HOLD, dan tersedia dapat dilihat dengan lebih
terstruktur.

Permintaan lebih terstandar

Katalog menjadi acuan barang yang digunakan dalam pengajuan.

Proses lebih mudah ditelusuri

Permintaan memiliki status dan riwayat proses.

Fitur

Katalog Barang

Permintaan & Persetujuan

Pengendalian Stok

Monitoring & Laporan

Pencatatan Mutasi Aset

BAST + QR Verification

Alur

Tampilkan alur sesuai rancangan, bukan alur marketing baru.

Pengajuan
→ Persetujuan Ketua
→ Verifikasi Gudang
→ Persetujuan Kasubbag
→ Penyiapan
→ Penerimaan
→ Pengesahan

Untuk pengaju Ketua Tim, jelaskan bahwa persetujuan Ketua dilewati.

31. DUMMY QR --- WAJIB ADA UNTUK DEMO

Gunakan contoh:

SIMPBI-DEMO-2026-0001

Contoh URL development:

http://localhost/verifikasi/SIMPBI-DEMO-2026-0001

Tampilkan label:

DEMO / DUMMY --- BUKAN DOKUMEN RESMI

QR dummy tidak boleh memakai data personal nyata.

QR produksi

Gunakan:

qr_token

dari transaksi yang memang tersimpan.

Jangan hardcode token dummy pada dokumen produksi.

32. CONTOH DOKUMEN BUKTI

Gunakan gaya administrasi resmi.

BADAN PUSAT STATISTIK
KOTA JAKARTA BARAT

BUKTI PERMINTAAN BARANG

Kode Permintaan : PB-2026-0001

Tanggal         : 16 Februari 2026

[ QR CODE ]

DEMO / DUMMY

Token:
SIMPBI-DEMO-2026-0001

Untuk dokumen nyata:

gunakan identitas transaksi sebenarnya;

gunakan QR berdasarkan qr_token;

jangan menampilkan data dummy.

33. HALAMAN VERIFIKASI QR

Rute konsep:

/verifikasi/{token}

Informasi minimum yang aman:

valid/tidak valid;

nomor dokumen;

jenis dokumen;

tanggal;

identitas penandatangan/pengesah sesuai kebutuhan;

waktu pengesahan;

status dokumen.

Jangan membuka data sensitif yang tidak diperlukan hanya karena QR dapat
dipindai publik.

34. DASHBOARD --- PRINSIP UTAMA

Dashboard harus menjawab:

"Apa yang harus saya lakukan sekarang?"

Bukan:

"Berapa banyak grafik yang bisa kita tampilkan?"

Dashboard role-specific.

Jangan memaksakan grafik ketika data belum mencukupi.

35. DASHBOARD KASUBBAG UMUM

KPI:

Menunggu Persetujuan

Menunggu Pengesahan

Stok Kritis

Barang Tidak Tersedia

Panel:

Perlu Tindakan Anda

Kondisi Stok

Permintaan per Unit Kerja

Distribusi Status Permintaan

Barang Paling Sering Diminta

Pola Permintaan & Tren

Perlu Tindakan

Hanya item yang benar-benar membutuhkan aksi Kasubbag.

Contoh:

PB-2026-0002
Statistik Sosial · 3 item

Persetujuan Akhir
Tersisa 45 menit

[ Proses → ]

36. DASHBOARD PETUGAS GUDANG

KPI:

Perlu Verifikasi

Perlu Disiapkan

Siap Diambil

Permintaan Bermasalah

Panel:

Perlu Tindakan

Kondisi Stok

Barang dengan Stok Terendah

Barang Mendekati Stok Minimum

37. DASHBOARD KETUA TIM

KPI:

Menunggu Persetujuan

Sedang Diproses

Selesai

Panel:

Perlu Tindakan

Status Permintaan Tim Saya

Semua transaksi dibatasi pada tim_id pengguna.

38. DASHBOARD TIM

KPI:

Menunggu Persetujuan

Sedang Diproses

Siap Diambil

Selesai

Panel:

Perlu Tindakan

Jangan membuat banyak chart jika transaksi pengguna sedikit.

39. DASHBOARD ADMIN SISTEM

KPI:

Pengguna Aktif

Tim Kerja Aktif

Barang Persediaan

Kategori Barang

Panel yang relevan:

Kelengkapan Data Induk

Contoh:

3 pengguna belum terhubung dengan tim
7 barang belum ditetapkan stok minimum

Gunakan istilah:

belum ditetapkan stok minimum

bukan istilah yang menyiratkan bahwa semua barang wajib dimonitor.

Jika Admin tidak memiliki workflow operasional, jangan memaksakan "Perlu
Tindakan Anda" yang berisi approval.

40. PANEL "PERLU TINDAKAN ANDA"

Gunakan sebagai daftar pekerjaan prioritas.

Maksimal:

5 item

Urutan:

melewati batas;

deadline paling dekat;

deadline berikutnya.

Tidak perlu:

search;

filter;

pagination;

di dashboard.

Halaman utama/menu tetap menyediakan daftar lengkap.

Isi item

Kode
Tim
Jumlah item
Tahap
Sisa waktu
Aksi

Aksi sesuai role:

Role             Contoh aksi

Kasubbag         Proses
Ketua Tim        Tinjau
Petugas Gudang   Verifikasi / Siapkan
Tim              Lihat / Konfirmasi

41. DETAIL PERMINTAAN

Gunakan satu tombol Detail.

Satu klik membuka satu dialog/modal.

Jangan memisahkan:

[Rincian] [Riwayat]

jika informasi tersebut dapat ditampilkan dalam satu dialog.

Isi:

STATUS

Informasi Permintaan
────────────────────
Kode
Tim
Pemohon
NIP
Keperluan

Daftar Barang
────────────────────
Barang | Diminta | Diverifikasi | Final | Kondisi

Ketidaksesuaian
────────────────────

Riwayat Proses
────────────────────
Pengajuan
↓
Persetujuan Ketua
↓
Verifikasi
↓
Persetujuan Akhir
↓
Penyiapan
↓
Penerimaan
↓
Pengesahan

Dialog jangan memenuhi seluruh layar tanpa alasan.

Gunakan modal yang nyaman dibaca dengan backdrop ringan.

42. AKSI TABLE

Table permintaan:

Kode Permintaan

Tim Pemohon

Pemohon

Barang

Status

Batas Waktu

Diajukan

Aksi:

Detail

aksi workflow yang memang sesuai role

Jangan menyembunyikan aksi secara paksa dengan CSS jika menyebabkan
accessibility/maintainability buruk.

Jika tombol Detail tetap muncul bersama aksi workflow, itu tidak masalah
selama fungsinya jelas.

43. VISUALISASI

Permintaan per Unit Kerja

Horizontal bar.

Tampilkan seluruh 8 tim, termasuk yang bernilai 0 bila konteksnya
perbandingan unit.

Filter:

30 hari terakhir
90 hari terakhir
Seluruh periode

Distribusi Status Permintaan

Kategori:

Selesai

Berjalan

Ditolak

Kedaluwarsa

Barang Paling Sering Diminta

Top 10 dapat digunakan sebagai visual ringkas dashboard.

Untuk UC-22 yang bersifat analitis, jangan membatasi informasi hanya ke
top 10 jika use case membutuhkan distribusi yang lebih luas.

Pengeluaran per Kategori

Sumber:

mutasi_stok

dengan:

jenis = keluar

Jangan menampilkan chart ini sebagai insight utama jika data transaksi
belum mencukupi.

44. KONDISI STOK

Tampilkan:

Stok Fisik
Stok HOLD
Stok Tersedia

Rumus:

stok_tersedia = stok_fisik - stok_hold

Jangan membuat nilai stok tersedia menjadi sumber data terpisah.

Visual:

fisik = blue;

hold = neutral/orange;

tersedia = primary/success.

45. MASALAH LAYOUT DASHBOARD YANG SUDAH DITEMUKAN

Kebutuhan visual yang pernah dicoba:

KIRI
Perlu Tindakan
Permintaan per Unit Kerja

KANAN
Kondisi Stok

Masalah:

Filament grid standar tidak selalu mendukung masonry/row-span independen
secara stabil.

Solusi yang diprioritaskan

Jangan membuat hack CSS yang rapuh.

Jika komposisi ini memang harus dipertahankan, gunakan:

custom dashboard wrapper/grid yang terkontrol; atau

composite widget yang menggabungkan panel-panel tersebut.

Lebih baik layout sederhana dan stabil daripada masonry yang merusak
responsive.

46. EMPTY / LOADING / ERROR STATE

Empty

[icon]

Belum ada permintaan

Belum ada permintaan barang pada periode ini.

Untuk tindakan:

Tidak ada tindakan yang perlu dilakukan

Seluruh pekerjaan Anda saat ini sudah tertangani.

Loading

Gunakan:

Memuat...
Menyimpan...
Memproses...
Menghasilkan dokumen...
Memverifikasi...

Button disable saat proses.

Error

Jangan tampilkan exception mentah.

Buruk:

SQLSTATE[23000]...

Lebih baik:

Permintaan tidak dapat diproses.

Stok barang tidak mencukupi.

47. RESPONSIVE

Desktop menjadi target utama.

Tablet:

widget dapat satu kolom;

table horizontal scroll.

Mobile:

KPI 1--2 kolom;

timeline vertical;

table horizontal scroll;

tombol utama mudah dijangkau.

Landing:

hero 2 kolom → 1 kolom
feature 4 kolom → 1 kolom
timeline horizontal → vertical
navbar → mobile menu

48. ACCESSIBILITY

Pastikan:

kontras memadai;

focus state terlihat;

tombol memiliki label;

icon-only button memiliki tooltip/aria-label;

status tidak hanya dibedakan dengan warna;

keyboard navigation masuk akal.

49. NOTIFIKASI

UC-23 berlaku untuk semua 5 role.

Notifikasi harus berdasarkan event workflow yang memang ada.

Contoh event:

permintaan diajukan;

permintaan disetujui;

permintaan ditolak;

siap diambil;

pengesahan;

stok menipis jika aturan bisnis memang digunakan.

Jangan membuat tabel baru hanya untuk "aktivitas terbaru" jika sumber
datanya sudah tersedia melalui notifikasi/riwayat.

50. LAPORAN / EXPORT

UC-24 mendukung:

laporan permintaan;

laporan mutasi;

laporan stok;

laporan operasional lain yang sudah dirancang.

Parameter dapat mencakup:

periode;

tim;

kategori.

Output:

PDF;

Excel;

cetak.

Jika tidak ada data untuk parameter:

Data tidak ditemukan.
Silakan ubah parameter laporan.

51. DATA DUMMY UNTUK DEMO

Dummy boleh digunakan untuk:

screenshot;

testing;

demo;

seeding lokal.

Dummy tidak boleh dipresentasikan sebagai data aktual.

Jika menggunakan dummy:

beri konteks jelas;

gunakan nama fiktif;

jangan gunakan NIP pribadi nyata;

gunakan QR token DEMO;

jangan mencampurkan dummy dengan data produksi tanpa penanda.

52. PRINSIP IMPLEMENTASI LARAVEL + FILAMENT

Gunakan:

Laravel 12;

Filament 5;

Tailwind CSS;

MySQL 8.

Prioritaskan API/native component Filament.

Custom CSS hanya jika benar-benar diperlukan.

Jangan membuat CSS global yang merusak komponen Filament.

Jangan mengubah migration hanya agar komponen Filament lebih mudah
dibuat.

53. AUTHORIZATION

Hak akses harus selalu berasal dari role yang telah ditetapkan.

Prinsip:

role → authorization → query scope → UI

Bukan:

UI disembunyikan → dianggap aman

Hiding menu/button hanya pelengkap.

Backend tetap wajib memeriksa hak akses.

54. CONTOH SCOPE PER ROLE

Ketua Tim

Hanya transaksi timnya.

Tim

Hanya transaksi sesuai konteks tim/akun yang digunakan.

Petugas Gudang

Melihat pekerjaan gudang dan data yang memang diperlukan.

Kasubbag

Monitoring menyeluruh sesuai hak akses.

Admin Sistem

Data master dan administrasi sistem.

Jangan memberikan akses lintas role hanya karena halaman lebih mudah
dibuat.

55. DATA VISUALISASI HARUS REAL

Jangan:

$total = 28;

jika angka tersebut tidak berasal dari database.

Gunakan query aktual.

Jika data kosong:

tampilkan 0;

tampilkan empty state;

jangan membuat random data.

Jika data terlalu sedikit untuk insight:

Data belum mencukupi untuk menampilkan pola.

56. DASHBOARD TIDAK BOLEH MENGADA-ADA

Jangan menambahkan:

"28 pengguna aktif" jika akun aktual tidak 28;

"aktivitas terbaru" jika tidak ada sumber data yang dirancang;

tren historis palsu;

persentase efisiensi yang belum diuji;

grafik hanya demi memenuhi ruang.

57. DOKUMEN REFERENSI BPS

Dokumen resmi yang ditunjukkan sebelumnya menjadi inspirasi untuk gaya
administrasi.

Untuk bukti transaksi:

gunakan struktur formal;

tanggal;

nomor/kode;

QR;

identitas pihak;

pengesahan.

Jangan mengarang format hukum baru.

Jika ada format surat resmi yang sudah ditetapkan, ikuti format
tersebut.

58. MENU SISTEM

Kelompok menu yang disarankan:

DASHBOARD
  Dashboard

PERMINTAAN & DISTRIBUSI
  Katalog Barang
  Permintaan Barang

PERSEDIAAN
  Barang Persediaan
  Kategori Barang
  Stok Masuk

INVENTARIS
  Aset Tetap

MONITORING
  Laporan
  Riwayat Transaksi
  Riwayat Mutasi Aset

ADMINISTRASI
  Tim
  Pengguna
  Pengaturan

Menu harus mengikuti hak akses.

Jangan membuat menu "Mutasi Stok" baru jika fungsi kartu kendali memang
telah dirancang melekat pada data barang/riwayat yang tersedia.

59. URUTAN IMPLEMENTASI

Kerjakan bertahap.

Tahap 1 --- Fondasi visual

Theme
→ warna
→ typography
→ logo
→ sidebar
→ header

Tahap 2 --- Akses

Login

Tahap 3 --- Landing

Landing page
→ hero
→ masalah
→ solusi
→ fitur
→ alur
→ role
→ QR
→ CTA

Tahap 4 --- Komponen umum

Badge
Button
Modal
Empty state
Loading
Error
Notification

Tahap 5 --- Dashboard

Kasubbag
→ Petugas Gudang
→ Ketua Tim
→ Tim
→ Admin Sistem

Tahap 6 --- Dokumen

QR
→ verifikasi
→ bukti permintaan
→ BAST

Tahap 7 --- Responsive

Tahap 8 --- Final polish

Jangan mengerjakan semua dashboard sekaligus.

60. CHECKLIST SEBELUM MENGUBAH KODE

Sebelum Claude/AI melakukan perubahan, tanyakan:

Apakah perubahan ini mengubah?

BPMN?

Use Case?

Activity Diagram?

ERD?

Role?

Hak akses?

Status?

Workflow?

Business rule?

Scope?

Jika YA, jangan lakukan otomatis.

Minta persetujuan.

Jika TIDAK, lanjutkan implementasi.

61. CHECKLIST UI/UX

Branding

Logo BPS resmi.

SIMPBI konsisten.

BPS Kota Jakarta Barat jelas.

warna konsisten.

typography konsisten.

Login

branding panel;

email;

password;

toggle password;

validasi;

loading;

error;

responsive.

Landing

navbar;

hero;

masalah;

solusi;

fitur;

alur;

role;

angka faktual jika digunakan;

QR verification;

CTA;

footer.

Dashboard

role-specific;

KPI relevan;

Perlu Tindakan;

visualisasi punya tujuan;

data aktual;

empty state;

loading;

error;

responsive.

Workflow

HOLD benar;

RELEASE benar;

KONVERSI benar;

partial approval benar;

expiry benar;

role restriction benar.

QR

dummy QR tersedia;

label DEMO/DUMMY;

token dummy jelas;

production memakai qr_token;

verifikasi tidak membuka data sensitif.

62. CHECKLIST "JANGAN SAMPAI KELEWAT"

Sebelum menyatakan implementasi selesai, pastikan:

5 role tetap 5 role.

25 use case tetap 25 use case.

8 unit/tim tetap 8.

BPMN tidak berubah.

Activity Diagram tidak berubah.

ERD tidak diubah demi UI.

alur permintaan tidak berubah.

alur aset tidak disamakan dengan alur permintaan.

HOLD/RELEASE/KONVERSI tetap.

partial approval tetap.

pengaju Ketua Tim tetap melewati approval Ketua.

ketidaksesuaian tetap memiliki jalur penyelesaian langsung.

ketidaksesuaian yang tidak dapat diatasi menjadi bermasalah.

expiry me-release HOLD.

pengesahan final tetap di Kasubbag.

BAST aset tetap tanpa workflow approval tambahan.

QR dummy tersedia.

QR production memakai token transaksi.

dashboard role-specific.

tidak ada data statistik palsu.

tidak ada tabel baru hanya demi UI.

tidak ada role baru.

tidak ada workflow baru.

63. INSTRUKSI LANGSUNG UNTUK CLAUDE / AI CODING ASSISTANT

Gunakan dokumen ini sebagai MASTER CONSTRAINT + DESIGN SYSTEM.

Kalimat kerja:

Implementasikan sistem SIMPBI berdasarkan rancangan yang sudah ada.
Jangan mendesain ulang proses bisnis. Jangan mengubah BPMN, Use Case,
Activity Diagram, ERD, role, hak akses, status, workflow, atau
business rule. Fokus pada implementasi teknis dan UI/UX yang
merepresentasikan rancangan tersebut secara akurat.

Jika menemukan konflik:

STOP. Jangan mengubah rancangan. Jelaskan konflik dan berikan opsi
implementasi yang mempertahankan rancangan.

Jika ingin menambah tabel:

STOP. Jelaskan mengapa tabel tersebut diperlukan dan cek apakah
kebutuhan dapat dipenuhi dari tabel/relasi yang sudah ada.

Jika ingin menambah role:

STOP. Tidak diperbolehkan tanpa persetujuan eksplisit.

Jika ingin mengubah workflow:

STOP. Tidak diperbolehkan tanpa persetujuan eksplisit.

Jika ingin membuat dummy data:

Tandai sebagai DEMO/DUMMY dan jangan membuatnya terlihat seperti data
produksi.

64. REFERENSI VISUAL

Website inspirasi:

https://tmskripsi.biz.id/

Gunakan hanya sebagai referensi visual.

Identitas final harus tetap:

SIMPBI --- Sistem Informasi Manajemen Permintaan Barang dan
Inventaris
Sub-Bagian Umum --- Badan Pusat Statistik Kota Jakarta Barat

65. PENUTUP

SIMPBI harus terlihat seperti sistem yang benar-benar siap dipakai
oleh unit kerja, bukan sekadar CRUD untuk memenuhi tugas skripsi.

Namun kualitas visual tidak boleh dibayar dengan mengubah rancangan
penelitian.

Prinsip final:

Rancangan adalah sumber kebenaran. UI/UX adalah cara menyajikannya.
Kode adalah cara mewujudkannya.

Jangan membalik urutan tersebut.