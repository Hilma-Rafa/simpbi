<?php

/*
 * Teks halaman muka (welcome.blade.php).
 *
 * Dipisahkan dari tampilan supaya pengunjung dapat berpindah bahasa lewat
 * tombol bendera di navbar. Yang diterjemahkan hanya halaman muka: panel
 * aplikasi dipakai pegawai internal dan tetap berbahasa Indonesia, sehingga
 * tidak ada gunanya menyalin seluruh istilahnya ke bahasa Inggris.
 *
 * Kunci ditulis dalam bahasa Indonesia mengikuti kode proyek yang lain, dan
 * dikelompokkan per bagian halaman agar mudah ditemukan saat menyunting.
 */

return [

    'meta' => [
        'judul' => 'SIMPBI — Sistem Informasi Manajemen Permintaan Barang dan Inventaris',
        'deskripsi' => 'SIMPBI — Sistem Informasi Manajemen Permintaan Barang dan Inventaris. Sub-Bagian Umum, Badan Pusat Statistik Kota Jakarta Barat.',
    ],

    'nav' => [
        'tentang' => 'Tentang',
        'fitur' => 'Fitur',
        'alur' => 'Alur',
        'verifikasi' => 'Verifikasi',
        'masuk' => 'Masuk ke Sistem',
        'buka_menu' => 'Buka menu',
        'tutup_menu' => 'Tutup menu',
        'bahasa' => 'Bahasa',
    ],

    /* Nama bahasa sengaja ditulis dalam bahasanya sendiri dan sama pada kedua
       berkas. Pengunjung yang belum paham bahasa halaman tetap mengenali
       "English" atau "Indonesia" sebagai nama, bukan sebagai terjemahan. */
    'bahasa' => [
        'id' => 'Indonesia',
        'en' => 'English',
    ],

    'hero' => [
        'badge' => 'Sistem Internal',
        'badge_satker' => ' · BPS Kota Jakarta Barat',
        'nama_panjang' => 'Sistem Informasi Manajemen Permintaan Barang dan Inventaris',
        'deskripsi' => 'Kelola permintaan, ketersediaan persediaan, dan distribusi barang dalam satu proses yang terintegrasi dan dapat ditelusuri.',
        'tombol_masuk' => 'Masuk ke Sistem',
        'tombol_alur' => 'Lihat Alur',
        'satker' => 'Sub-Bagian Umum · Badan Pusat Statistik Kota Jakarta Barat',
        'angka' => [
            ['5', 'Peran Pengguna'],
            ['8', 'Tim Kerja'],
            ['6', 'Tahap Persetujuan'],
            ['2', 'Kanal Notifikasi'],
        ],
    ],

    'tentang' => [
        'label' => 'Mengapa SIMPBI',
        'judul' => 'Satu proses yang jelas, dari permintaan hingga pengesahan.',
        'paragraf' => 'Menggantikan pencatatan manual yang tersebar dengan alur kerja yang terstandar, dapat dipantau, dan dapat ditelusuri kembali.',
        'kartu' => [
            ['judul' => 'Stok lebih mudah dipantau', 'isi' => 'Informasi stok fisik, HOLD, dan tersedia dapat dilihat dengan lebih terstruktur.'],
            ['judul' => 'Permintaan lebih terstandar', 'isi' => 'Katalog menjadi acuan barang yang digunakan dalam setiap pengajuan.'],
            ['judul' => 'Proses lebih mudah ditelusuri', 'isi' => 'Setiap permintaan memiliki status dan riwayat proses yang tercatat.'],
        ],
    ],

    'fitur' => [
        'label' => 'Fitur',
        'judul' => 'Dirancang untuk pekerjaan harian tim kerja.',
        'kartu' => [
            ['judul' => 'Katalog Barang', 'isi' => 'Daftar barang persediaan terstandar sebagai acuan pengajuan.'],
            ['judul' => 'Permintaan & Persetujuan', 'isi' => 'Alur pengajuan berjenjang dengan persetujuan sesuai kewenangan.'],
            ['judul' => 'Pengendalian Stok', 'isi' => 'Mekanisme HOLD, RELEASE, dan konversi menjaga stok tetap akurat.'],
            ['judul' => 'Monitoring & Laporan', 'isi' => 'Dashboard per peran dan laporan yang dapat diekspor.'],
            ['judul' => 'Pencatatan Mutasi Aset', 'isi' => 'Penempatan, redistribusi, dan mutasi aset tetap peralatan & mesin.'],
            ['judul' => 'BAST + Verifikasi QR', 'isi' => 'Berita acara serah terima dengan tanda tangan digital dan QR.'],
        ],
    ],

    'alur' => [
        'label' => 'Alur Permintaan',
        'judul' => 'Tujuh tahap, satu jejak yang jelas.',
        'peran' => 'Ketua Tim',
        'paragraf' => 'Untuk pengaju berperan :peran, tahap persetujuan Ketua dilewati dan permintaan langsung menuju verifikasi gudang.',
        'tahap' => [
            'Pengajuan',
            'Persetujuan Ketua',
            'Verifikasi Gudang',
            'Persetujuan Kasubbag',
            'Penyiapan',
            'Penerimaan',
            'Pengesahan',
        ],
        'catatan_ketua' => 'Dilewati untuk pengaju Ketua Tim',
        'selesai' => 'Selesai & terarsip',
    ],

    'verifikasi' => [
        'label' => 'Verifikasi Dokumen',
        'judul' => 'Setiap dokumen resmi dapat dipindai dan diperiksa keasliannya.',
        'paragraf' => 'Bukti permintaan dan BAST mutasi aset dilengkapi kode QR. Pemindaian mengarah ke halaman verifikasi yang menampilkan status keaslian dokumen tanpa membuka data sensitif.',
        'daftar' => [
            'Nomor & jenis dokumen',
            'Tanggal pengesahan',
            'Status keaslian',
        ],
        'demo' => [
            'instansi' => 'BADAN PUSAT STATISTIK',
            'kota' => 'Kota Jakarta Barat',
            'label' => 'Demo',
            'jenis' => 'Bukti Permintaan Barang',
            'status' => 'Dokumen sah',
            'token' => 'Token:',
            'catatan' => 'Contoh tampilan. Bukan dokumen resmi.',
        ],
    ],

    'ajakan' => [
        'judul' => 'Masuk untuk mulai bekerja.',
        'paragraf' => 'Akses SIMPBI menggunakan akun yang telah diberikan oleh administrator sistem.',
        'tombol' => 'Masuk ke Sistem',
    ],

    'kaki' => [
        'satker' => 'Sub-Bagian Umum · BPS Kota Jakarta Barat',
        'hak_cipta' => '© :tahun Badan Pusat Statistik Kota Jakarta Barat. Sistem internal.',
    ],

];
