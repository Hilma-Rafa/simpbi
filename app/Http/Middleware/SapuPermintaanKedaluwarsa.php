<?php

namespace App\Http\Middleware;

use App\Services\KedaluwarsaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyapu permintaan yang melewati batas waktu sebelum halaman panel dibuka.
 *
 * Penjadwal Laravel hanya berjalan bila ada cron yang memicunya, sehingga
 * pada pemasangan tanpa cron status kedaluwarsa tidak pernah diterapkan dan
 * permintaan yang sudah lewat batas tetap tampil pada daftar aktif. Sapuan
 * di sini menjamin apa yang dilihat pengguna selalu sesuai keadaan waktu,
 * tanpa bergantung pada layanan di luar aplikasi.
 *
 * Dijalankan SEBELUM permintaan diteruskan, supaya perubahan status sudah
 * terpakai oleh kueri halaman yang sedang dimuat, bukan baru terlihat pada
 * pemuatan berikutnya. Frekuensinya ditahan oleh KedaluwarsaService.
 */
class SapuPermintaanKedaluwarsa
{
    public function __construct(protected KedaluwarsaService $kedaluwarsa) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->kedaluwarsa->sapuBilaPerlu();

        return $next($request);
    }
}
