# Pemecahan Masalah {#B-MASALAH}

Tabel ini hanya memuat gejala yang benar-benar berasal dari validasi, pesan galat, atau pembatasan peran yang ada di aplikasi — bukan dugaan atau kemungkinan yang belum tentu terjadi.

| Gejala | Penyebab | Tindakan |
|---|---|---|
| "Jumlah melebihi stok tersedia" saat menambah barang ke keranjang | Jumlah yang diminta (ditambah yang sudah ada di keranjang) melebihi stok yang benar-benar tersedia | Kurangi jumlah, atau periksa stok tersedia pada Katalog Barang terlebih dahulu ([[T-TIM-01]]) |
| "Keranjang masih kosong" saat menekan Ajukan Permintaan | Belum ada barang yang ditambahkan ke keranjang | Tambahkan barang lebih dulu lewat [Tambah] pada Katalog Barang |
| "Akun Anda belum terhubung dengan tim kerja" saat mengajukan permintaan | Akun Anda belum diatur ke tim kerja mana pun oleh Administrator | Hubungi Administrator untuk melengkapi data akun ([[T-ADM-01]]) |
| "NIP pemohon harus terdiri atas angka dengan format yang benar" | Kolom NIP Pemohon (opsional) diisi tetapi bukan 18 digit angka | Kosongkan bila tidak tahu NIP pemohon, atau isi 18 digit yang benar |
| "Tidak cocok dengan nama tim Anda. Ketik persis nama tim Anda." saat konfirmasi (akun Tim) | Teks yang diketik pada kolom konfirmasi tidak persis sama dengan nama tim terdaftar | Ketik ulang persis sesuai nama tim yang tertera pada placeholder kolom |
| "Tidak cocok dengan data akun Anda. Ketik persis NIP (atau nama lengkap) Anda." saat konfirmasi (peran selain Tim) | Teks yang diketik tidak cocok dengan NIP (atau nama lengkap bila NIP belum terdata) akun Anda | Ketik ulang persis sesuai placeholder kolom |
| "Jumlah disetujui tidak boleh melebihi jumlah diminta (:max)" pada Persetujuan Akhir | Kasubbag Umum mengisi jumlah disetujui lebih besar dari jumlah yang diminta pemohon | Isi jumlah disetujui ≤ jumlah diminta; gunakan hasil verifikasi Gudang sebagai acuan |
| "Kata sandi baru harus berbeda dari kata sandi saat ini." | Kata sandi baru yang diketik sama dengan kata sandi yang sedang berlaku | Pilih kata sandi lain |
| "Anda tidak dapat menghapus akun Anda sendiri." | Admin Sistem mencoba menghapus akunnya sendiri | Minta Admin Sistem lain melakukannya, atau batalkan penghapusan |
| "Harus ada minimal satu Admin aktif." saat menghapus/menonaktifkan/menurunkan peran akun Admin | Tindakan tersebut akan menyisakan nol akun Admin Sistem aktif di sistem | Aktifkan/naikkan Admin Sistem lain terlebih dahulu sebelum melanjutkan |
| "Aset belum memiliki penempatan. Tetapkan penempatan terlebih dahulu lewat menu Aset Tetap." saat membuat BAST | Aset yang dipilih belum pernah ditempatkan ke tim kerja mana pun | Tetapkan penempatan awal aset lewat Aset Tetap ([[T-KAS-06]]) sebelum membuat BAST |
| "Aset ini masih memiliki BAST yang menunggu pengesahan (...)" saat membuat BAST | Aset yang dipilih masih punya BAST lain berstatus Menunggu Pengesahan dengan tim asal yang sama dengan penempatannya sekarang | Sahkan atau selesaikan BAST yang menunggu itu terlebih dahulu ([[T-KAS-04]]) |
| "Tanda tangan belum tersedia" saat mengonfirmasi penerimaan BAST | Tanda tangan Ketua Tim yang mengonfirmasi belum terdaftar di akunnya | Lengkapi tanda tangan lewat Lengkapi Akun ([[T-MULAI-03]]) atau Pengaturan |
| "Kode barang sudah dipakai pada kategori ini." saat membuat barang baru dari Stok Masuk | Kode barang yang diketik sudah dipakai barang lain pada kategori yang sama | Gunakan kode lain, atau pilih barang yang sudah ada bila memang barang yang sama |
| Tombol [Unduh BAST] tidak muncul pada sebuah baris | BAST itu belum disahkan Kasubbag Umum — tombol baru muncul setelah pengesahan, bukan sejak BAST dibuat | Tunggu hingga BAST disahkan ([[T-KAS-04]]) |
| Halaman menampilkan "403" atau tidak dapat diakses | Peran akun Anda tidak berwenang atas halaman tersebut | Periksa kembali kemampuan peran Anda di [[B-LAMPIRAN]]; hubungi Administrator bila menurut Anda ini keliru |
| Tidak dapat masuk berkali-kali berturut-turut | Halaman masuk membatasi jumlah percobaan dalam rentang waktu tertentu sebagai perlindungan keamanan | Tunggu sesaat sebelum mencoba lagi, atau pastikan alamat email dan kata sandi sudah benar |
| Kartu WhatsApp Pusat Bantuan menampilkan "Nomor WhatsApp belum diatur oleh Administrator." | Admin Sistem belum mengisi nomor WhatsApp bantuan | Hubungi Administrator, atau gunakan kanal Email pada Pusat Bantuan sebagai gantinya |
