<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\TerapkanBahasa;
use App\Models\PermintaanBarang;
use App\Models\BastMutasiAset;
use App\Services\DokumenPermintaanService;
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
 * yang memindai kode QR pada dokumen bukti permintaan. Karena pembacanya
 * belum tentu pegawai dan belum tentu berbahasa Indonesia, halaman ini ikut
 * mengikuti pilihan bahasa pengunjung seperti halaman muka.
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
})->middleware(TerapkanBahasa::class)->name('verifikasi.permintaan');

/**
 * Lembar hasil pemindaian kode QR pada dokumen bukti permintaan.
 *
 * Terbuka tanpa autentikasi, sebab yang memindainya belum tentu pegawai —
 * dokumen bukti beredar ke luar sistem dan justru itulah gunanya dapat
 * diperiksa. Yang menjadi kunci adalah tokennya sendiri: empat puluh aksara
 * acak yang hanya diketahui dari dokumen fisiknya, dan hanya berlaku untuk
 * permintaan yang sudah disahkan.
 *
 * Berkas dikirim inline, bukan sebagai unduhan, karena pemindai kode QR
 * membukanya di peramban ponsel dan yang diharapkan pemindai adalah melihat
 * dokumennya seketika.
 */
Route::get('/bukti/{token}', function (string $token) {
    $permintaan = PermintaanBarang::query()
        ->where('qr_token', $token)
        ->whereNotNull('pengesahan_at')
        ->firstOrFail();

    $lintasan = DokumenPermintaanService::lintasanBerfootnote($permintaan);

    abort_unless(Storage::disk('public')->exists($lintasan), 404);

    return response(Storage::disk('public')->get($lintasan), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $permintaan->kode_permintaan . '.pdf"',
    ]);
})->name('bukti.pindai');

/**
 * Berkas asli, yaitu lembar yang sama tanpa pita tanda tangan elektronik.
 *
 * Inilah tujuan kode QR pada pita: memindainya mengembalikan pemeriksa ke
 * dokumen apa adanya, sehingga penelusuran tidak berputar pada lembar yang
 * sudah bertanda.
 */
Route::get('/bukti/{token}/asli', function (string $token) {
    $permintaan = PermintaanBarang::query()
        ->where('qr_token', $token)
        ->whereNotNull('pengesahan_at')
        ->firstOrFail();

    abort_unless(
        $permintaan->file_bukti_path && Storage::disk('public')->exists($permintaan->file_bukti_path),
        404,
    );

    return response(Storage::disk('public')->get($permintaan->file_bukti_path), 200, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $permintaan->kode_permintaan . '.pdf"',
    ]);
})->name('bukti.asli');

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
})->middleware(TerapkanBahasa::class)->name('verifikasi.bast');

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