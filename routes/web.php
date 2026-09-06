<?php

use Illuminate\Support\Facades\Route;
use App\Models\PermintaanBarang;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// ============================================================
// Tambahan pada routes/web.php
// ============================================================

/**
 * Halaman verifikasi keaslian dokumen.
 * Dapat diakses tanpa autentikasi, karena ditujukan bagi siapa pun
 * yang memindai kode QR pada dokumen bukti permintaan.
 */
Route::get('/verifikasi/{token}', function (string $token) {
    $permintaan = PermintaanBarang::query()
        ->where('qr_token', $token)
        ->whereNotNull('pengesahan_at')
        ->with(['tim', 'detail.barang', 'persetujuan.pelaksana'])
        ->first();

    $pengesah = $permintaan?->persetujuan
        ->firstWhere('tahap', 'pengesahan')
        ?->pelaksana
        ?->name;

    return view('verifikasi.permintaan', compact('permintaan', 'pengesah'));
})->name('verifikasi.permintaan');

/**
 * Pengunduhan berkas bukti permintaan.
 * Hanya dapat diakses oleh pengguna yang telah masuk ke sistem.
 */
Route::get('/bukti-permintaan/{permintaan}', function (PermintaanBarang $permintaan) {
    abort_unless($permintaan->file_bukti_path, 404);
    abort_unless(Storage::disk('public')->exists($permintaan->file_bukti_path), 404);

    return Storage::disk('public')->download(
        $permintaan->file_bukti_path,
        $permintaan->kode_permintaan . '.pdf'
    );
})->middleware('auth')->name('bukti.unduh');