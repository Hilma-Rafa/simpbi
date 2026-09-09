<?php

namespace App\Services;

use App\Models\BastMutasiAset;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pembentukan dokumen BAST mutasi aset tetap.
 *
 * Mengikuti pola DokumenPermintaanService: dokumen PDF dibentuk oleh sistem
 * (BAST digital murni, bukan hasil pindai TTD basah), dilengkapi kode QR yang
 * mengarah ke halaman verifikasi. e-TTD direpresentasikan sebagai nama
 * pengesah + QR + waktu pengesahan (Instruksi §18), bukan gambar tanda tangan.
 */
class DokumenBastService
{
    public function buat(BastMutasiAset $bast): string
    {
        $bast->loadMissing(['aset.kategori', 'timAsal', 'timTujuan', 'dibuatOleh', 'disahkanOleh']);

        $pdf = Pdf::loadView('pdf.bast-mutasi', [
            'bast' => $bast,
            'qr'   => $bast->disahkan_at ? $this->kodeQr($bast) : '',
            'logo' => $this->logoBase64(),
        ])->setPaper('a4', 'portrait');

        $nama = 'bast-mutasi/' . $bast->nomor_bast . '.pdf';

        Storage::disk('public')->put($nama, $pdf->output());

        return $nama;
    }

    /** Membuat token verifikasi apabila belum ada. */
    public function pastikanToken(BastMutasiAset $bast): string
    {
        if (! $bast->qr_token) {
            $bast->update(['qr_token' => Str::random(40)]);
        }

        return $bast->qr_token;
    }

    /**
     * Kode QR e-TTD sebagai data URI. QR mengarah ke URL verifikasi berbasis
     * token sehingga mekanisme dummy (token acak) dapat langsung digantikan
     * token/URL produksi tanpa mengubah tata letak dokumen.
     */
    protected function kodeQr(BastMutasiAset $bast): string
    {
        $token = $this->pastikanToken($bast);
        $tautan = route('verifikasi.bast', ['token' => $token]);

        try {
            $opsi = new QROptions([
                'eccLevel'      => QRCode::ECC_H,
                'scale'         => 8,
                'imageBase64'   => true,
                'quietzoneSize' => 2,
            ]);

            return (new QRCode($opsi))->render($tautan);
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function logoBase64(): string
    {
        $path = public_path('images/logo-bps.png');

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
