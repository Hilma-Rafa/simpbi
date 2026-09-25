<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pemeriksaan batas waktu tahapan permintaan barang.
//
// Jalur utama penegakan kedaluwarsa adalah middleware SapuPermintaanKedaluwarsa,
// yang berjalan setiap kali panel dibuka. Penjadwal ini menjadi cadangan untuk
// keadaan tidak ada pengguna yang membuka aplikasi, misalnya di luar jam kerja,
// supaya kunci stok tidak menggantung semalaman.
//
// Pembatasan weekdays()/between() dilepaskan: batas waktu tahapan dihitung
// sebagai jam kerja, sehingga tidak perlu lagi dibatasi kedua kalinya saat
// penegakan. Membatasinya justru membuat permintaan yang lewat batas Jumat
// sore baru ditandai kedaluwarsa Senin pagi.
Schedule::command('permintaan:lepas-hold')->everyTenMinutes();
Schedule::command('queue:work --queue=default,whatsapp --tries=3 --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();