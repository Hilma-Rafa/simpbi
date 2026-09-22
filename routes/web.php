<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Http\Middleware\PaksaGantiKataSandi;
use App\Http\Middleware\PaksaLengkapiAkun;
use App\Http\Middleware\TerapkanBahasa;
use App\Models\PermintaanBarang;
use App\Models\BastMutasiAset;
use App\Services\DokumenPermintaanService;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
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

    abort_unless(Storage::disk('local')->exists($lintasan), 404);

    return response(Storage::disk('local')->get($lintasan), 200, [
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
        $permintaan->file_bukti_path && Storage::disk('local')->exists($permintaan->file_bukti_path),
        404,
    );

    return response(Storage::disk('local')->get($permintaan->file_bukti_path), 200, [
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
 * Gerbang akun untuk rute unduhan di luar panel.
 *
 * Sama dengan yang dikenakan pada panel: AuthenticateSession memutus sesi di
 * perangkat lain sesudah pemilik akun mengganti kata sandinya (tanpa ini sesi
 * lama masih dapat mengunduh sampai sesinya habis); Authenticate milik
 * Filament mengalihkan tamu ke halaman masuk panel dan menolak (403) akun yang
 * tidak lagi boleh membuka panel, misalnya yang sudah dinonaktifkan; dua
 * lainnya menahan akun yang wajib mengganti sandi atau belum melengkapi datanya.
 */
$gerbangAkun = [AuthenticateSession::class, Authenticate::class, PaksaGantiKataSandi::class, PaksaLengkapiAkun::class];

/**
 * Pengunduhan berkas BAST mutasi aset.
 *
 * Hanya BAST yang sudah disahkan (draf tidak beredar), dan hanya bagi yang
 * berhak melihatnya pada daftar Mutasi Aset: aturan peran dan tim dibaca dari
 * BastMutasiAsetResource, bukan ditulis ulang di sini.
 */
Route::get('/dokumen-bast/{bast}', function (BastMutasiAset $bast) {
    abort_unless($bast->disahkan_at, 404);
    abort_unless(BastMutasiAsetResource::canAccess(), 403);
    abort_unless(BastMutasiAsetResource::getEloquentQuery()->whereKey($bast->getKey())->exists(), 404);
    abort_unless($bast->file_bast_path, 404);
    abort_unless(Storage::disk('local')->exists($bast->file_bast_path), 404);

    return Storage::disk('local')->download(
        $bast->file_bast_path,
        $bast->nomor_bast . '.pdf'
    );
})->middleware($gerbangAkun)->name('bast.unduh');

/**
 * Pengunduhan berkas bukti permintaan.
 * Hanya bagi yang berhak melihat permintaan itu pada daftar Permintaan Barang:
 * aturan peran dan tim dibaca dari PermintaanBarangResource.
 */
Route::get('/bukti-permintaan/{permintaan}', function (PermintaanBarang $permintaan) {
    abort_unless(PermintaanBarangResource::canAccess(), 403);
    abort_unless(PermintaanBarangResource::getEloquentQuery()->whereKey($permintaan->getKey())->exists(), 404);
    abort_unless($permintaan->file_bukti_path, 404);
    abort_unless(Storage::disk('local')->exists($permintaan->file_bukti_path), 404);

    return Storage::disk('local')->download(
        $permintaan->file_bukti_path,
        $permintaan->kode_permintaan . '.pdf'
    );
})->middleware($gerbangAkun)->name('bukti.unduh');

/**
 * Pengunduhan berkas panduan penggunaan SIMPBI, dari Pusat Bantuan.
 *
 * Sama seperti bast.unduh/bukti.unduh, ia memakai gerbang akun; bedanya, tanpa
 * pembatasan peran — seluruh peran berhak membaca panduannya sendiri.
 * Disk-nya 'local' (storage/app/private), bukan 'public', sehingga berkas
 * ini tidak pernah punya tautan langsung yang bisa dibuka tanpa sesi.
 * Lokasi berkas dan namanya berasal dari config/pusat_bantuan.php, satu
 * tempat yang sama dipakai halaman Pusat Bantuan untuk menampilkan kartunya.
 */
Route::get('/pusat-bantuan/panduan', function () {
    $panduan = config('pusat_bantuan.panduan');

    abort_unless(Storage::disk($panduan['disk'])->exists($panduan['path']), 404);

    $ekstensi = pathinfo($panduan['path'], PATHINFO_EXTENSION);

    return Storage::disk($panduan['disk'])->download(
        $panduan['path'],
        $panduan['nama_tampilan'] . ($ekstensi ? '.' . $ekstensi : '')
    );
})->middleware($gerbangAkun)->name('pusat-bantuan.unduh-panduan');