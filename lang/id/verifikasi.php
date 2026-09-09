<?php

/*
 * Teks kedua halaman verifikasi keaslian dokumen.
 *
 * Halaman ini dibuka orang yang memindai kode QR pada selembar dokumen cetak,
 * jadi pembacanya belum tentu pegawai dan belum tentu berbahasa Indonesia —
 * itulah sebabnya keduanya ikut diterjemahkan, tidak seperti panel aplikasi
 * yang hanya dipakai internal.
 */

return [

    'judul_permintaan' => 'Verifikasi Dokumen — BPS Kota Jakarta Barat',
    'judul_bast' => 'Verifikasi BAST — BPS Kota Jakarta Barat',

    'instansi' => 'BADAN PUSAT STATISTIK KOTA JAKARTA BARAT',
    'subjudul' => 'Verifikasi Keaslian Dokumen',

    'sah' => 'Dokumen Sah',
    'tidak_ditemukan' => 'Dokumen Tidak Ditemukan',

    'imbauan' => 'Apabila Anda memperoleh dokumen ini dari pihak yang mengatasnamakan Sub Bagian Umum BPS Kota Jakarta Barat, mohon lakukan konfirmasi secara langsung.',
    'kaki' => 'Halaman verifikasi ini dihasilkan secara otomatis oleh sistem.',

    'permintaan' => [
        'sah_isi' => 'Dokumen terdaftar pada Sistem Informasi Manajemen Permintaan Barang dan Inventaris.',
        'tidak_isi' => 'Kode verifikasi tidak terdaftar pada sistem. Dokumen tidak dapat dipastikan keasliannya.',
        'nomor' => 'Nomor Dokumen',
        'tim' => 'Tim Pemohon',
        'pemohon' => 'Nama Pemohon',
        'tanggal' => 'Tanggal Pengajuan',
        'disahkan_pada' => 'Disahkan Pada',
        'disahkan_oleh' => 'Disahkan Oleh',
        'rincian' => 'Rincian Barang',
        'kolom_nama' => 'Nama Barang',
        'kolom_jumlah' => 'Jumlah',
        'kolom_satuan' => 'Satuan',
    ],

    'bast' => [
        'sah_isi' => 'Berita Acara Serah Terima mutasi aset terdaftar dan telah disahkan pada sistem.',
        'tidak_isi' => 'Kode verifikasi tidak terdaftar atau dokumen belum disahkan. Keaslian tidak dapat dipastikan.',
        'nomor' => 'Nomor BAST',
        'jenis' => 'Jenis Dokumen',
        'jenis_nilai' => 'Berita Acara Serah Terima Mutasi Aset',
        'aset' => 'Aset',
        'aset_nilai' => ':nama (NUP :nup)',
        'tim_asal' => 'Tim Asal',
        'tim_tujuan' => 'Tim Tujuan',
        'disahkan_pada' => 'Disahkan Pada',
        'disahkan_oleh' => 'Disahkan Oleh',
        'status' => 'Status',
        'status_selesai' => 'Selesai administratif (telah dikonfirmasi penerima)',
        'status_menunggu' => 'Menunggu konfirmasi penerima',
    ],

    /* Nama jabatan yang dipakai bila kolom pengesah kosong. Ditaruh di sini,
       bukan ditulis langsung di tampilan, karena keduanya memakainya. */
    'pengesah_bawaan' => 'Kepala Sub Bagian Umum',

];
