<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\TerapkanBahasa;
use App\Models\PermintaanBarang;
use App\Models\BastMutasiAset;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
})->middleware(TerapkanBahasa::class)->name('muka');

/**
 * Penukar bahasa halaman muka.
 *
 * Pilihan disimpan di sesi lalu pengunjung dikembalikan ke halaman asalnya,
 * sehingga posisi gulir dan tautan jangkar (#fitur, #alur) tidak hilang hanya
 * karena bahasanya diganti.
 */
Route::get('/bahasa/{kode}', function (string $kode) {
    // Kode di luar daftar ditolak supaya nilai sembarang dari URL tidak
    // pernah tersimpan di sesi dan terbawa ke permintaan berikutnya.
    abort_unless(in_array($kode, TerapkanBahasa::BAHASA_TERSEDIA, true), 404);

    session(['bahasa' => $kode]);

    return back(fallback: route('muka'));
})->name('bahasa.ganti');

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
 * Halaman verifikasi keaslian BAST mutasi aset.
 * Terbuka bagi siapa pun yang memindai QR e-TTD pada dokumen BAST.
 * Hanya BAST yang telah disahkan yang dapat diverifikasi.
 */
Route::get('/verifikasi-bast/{token}', function (string $token) {
    $bast = BastMutasiAset::query()
        ->where('qr_token', $token)
        ->whereNotNull('disahkan_at')
        ->with(['aset', 'timAsal', 'timTujuan', 'disahkanOleh'])
        ->first();

    return view('verifikasi.bast', compact('bast'));
})->name('verifikasi.bast');

/**
 * Pengunduhan berkas BAST mutasi aset (hanya pengguna terautentikasi).
 */
Route::get('/dokumen-bast/{bast}', function (BastMutasiAset $bast) {
    abort_unless($bast->file_bast_path, 404);
    abort_unless(Storage::disk('public')->exists($bast->file_bast_path), 404);

    return Storage::disk('public')->download(
        $bast->file_bast_path,
        $bast->nomor_bast . '.pdf'
    );
})->middleware('auth')->name('bast.unduh');

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