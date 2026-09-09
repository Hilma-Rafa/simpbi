<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menerapkan pilihan bahasa pengunjung pada halaman publik.
 *
 * Pilihan disimpan di sesi, bukan di basis data, karena halaman muka terbuka
 * untuk siapa pun dan tidak menuntut pengguna masuk lebih dulu.
 *
 * Middleware ini sengaja tidak dipasang pada seluruh grup "web": panel
 * aplikasi hanya dipakai pegawai internal dan tetap berbahasa Indonesia, jadi
 * mengubah locale seluruh aplikasi justru akan menerjemahkan antarmuka
 * Filament tanpa diminta. Pemasangannya cukup pada rute halaman muka.
 */
class TerapkanBahasa
{
    /** Bahasa yang benar-benar tersedia berkas terjemahannya di folder lang/. */
    public const BAHASA_TERSEDIA = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $bahasa = $request->session()->get('bahasa');

        // Nilai yang tidak dikenal diabaikan diam-diam sehingga halaman tetap
        // tampil dalam bahasa bawaan, bukan gagal dengan galat.
        if (in_array($bahasa, self::BAHASA_TERSEDIA, true)) {
            App::setLocale($bahasa);
        }

        return $next($request);
    }
}
