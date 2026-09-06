<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pemeriksaan batas waktu tahapan permintaan barang
Schedule::command('permintaan:lepas-hold')
    ->hourly()
    ->weekdays()
    ->between('08:00', '16:00');