<?php

namespace App\Services;

use App\Models\PermintaanBarang;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pembentukan dokumen bukti permintaan barang.
 *
 * Dokumen dibentuk pada tahap pengesahan akhir oleh Kasubbag Umum,
 * mengikuti format Surat Permintaan ATK dan Komputer Supplies yang
 * berlaku di Sub-Bagian Umum BPS Kota Jakarta Barat.
 *
 * Setiap dokumen dilengkapi kode QR yang mengarah ke halaman verifikasi,
 * sehingga keaslian dokumen dapat diperiksa tanpa menghubungi penerbit.
 */
class DokumenPermintaanService
{
    /**
     * Membentuk berkas PDF bukti permintaan dan mengembalikan path
     * penyimpanannya relatif terhadap disk publik.
     */
    public function buat(PermintaanBarang $permintaan): string
    {
        $permintaan->loadMissing(['tim', 'detail.barang', 'persetujuan.pelaksana']);

        $pdf = Pdf::loadView('pdf.bukti-permintaan', [
            'permintaan'  => $permintaan,
            'qr'          => $this->kodeQr($permintaan),
            'logo'        => $this->logoBase64(),
            'penyerah'    => $this->pelaksana($permintaan, 'penyiapan'),
            'pengesah'    => $this->pelaksana($permintaan, 'pengesahan'),
        ])->setPaper('a4', 'portrait');

        $nama = 'bukti-permintaan/' . $permintaan->kode_permintaan . '.pdf';

        Storage::disk('public')->put($nama, $pdf->output());

        return $nama;
    }

    /**
     * Membuat token verifikasi apabila belum ada.
     */
    public function pastikanToken(PermintaanBarang $permintaan): string
    {
        if (! $permintaan->qr_token) {
            $permintaan->update(['qr_token' => Str::random(40)]);
        }

        return $permintaan->qr_token;
    }

    /**
     * Menghasilkan kode QR sebagai data URI.
     *
     * Menggunakan pustaka chillerlan/php-qrcode yang telah tersedia
     * bersama Filament, dengan keluaran GD sehingga tidak memerlukan
     * ekstensi Imagick. Tingkat koreksi kesalahan ditetapkan tinggi
     * agar kode tetap terbaca meskipun bagian tengahnya tertutup logo.
     */
    protected function kodeQr(PermintaanBarang $permintaan): string
    {
        $token = $this->pastikanToken($permintaan);
        $tautan = route('verifikasi.permintaan', ['token' => $token]);

        try {
            $opsi = new QROptions([
                'eccLevel'   => QRCode::ECC_H,
                'scale'      => 8,
                'imageBase64' => true,
                'quietzoneSize' => 2,
            ]);

            return (new QRCode($opsi))->render($tautan);
        } catch (\Throwable $e) {
            // Apabila pembuatan kode QR gagal, dokumen tetap dapat dibentuk
            return '';
        }
    }

    /** Membaca logo instansi sebagai data URI agar dapat dimuat DomPDF. */
    protected function logoBase64(): string
    {
        $path = public_path('images/logo-bps.png');

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    /** Mengambil nama pelaksana pada tahap tertentu dari riwayat persetujuan. */
    protected function pelaksana(PermintaanBarang $permintaan, string $tahap): ?string
    {
        return $permintaan->persetujuan
            ->firstWhere('tahap', $tahap)
            ?->pelaksana
            ?->name;
    }
}