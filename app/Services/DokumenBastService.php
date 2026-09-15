<?php

namespace App\Services;

use App\Models\BastMutasiAset;
use App\Support\KodeQrBerlogo;
use Barryvdh\DomPDF\Facade\Pdf;
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

        /*
         * Kedua kode pada dokumen ini menuju alamat yang sama, yaitu halaman
         * verifikasi BAST. Berbeda dengan bukti permintaan, BAST tidak memiliki
         * berkas "asli" yang terbuka tanpa masuk sistem, sehingga tidak ada
         * tujuan kedua yang dapat dirujuk catatan kakinya.
         *
         * Alamat dihitung sekali dan dipakai berdua supaya pemeriksaan "kedua
         * kode menuju tempat yang sama" tidak bergantung pada dua pemanggilan
         * yang kebetulan menghasilkan hal serupa.
         */
        $tautan = $bast->disahkan_at
            ? $this->tautan('verifikasi.bast', $this->pastikanToken($bast))
            : null;

        $pdf = Pdf::loadView('pdf.bast-mutasi', [
            'bast'       => $bast,
            'qr'         => $tautan ? $this->kodeQr($tautan) : '',
            'qrFootnote' => $tautan ? $this->kodeQr($tautan, berlambang: false) : '',
            'logo'       => $this->logoBase64(),
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
     * Kode QR sebagai data URI.
     *
     * Lambang instansi dibubuhkan ke dalam kodenya sendiri — modul di pusat
     * matriks benar-benar dikosongkan lebih dulu — bukan ditumpangkan sebagai
     * gambar kedua di atasnya seperti sebelumnya. Lihat {@see KodeQrBerlogo}.
     *
     * Hanya stempel Kasubbag yang berlambang. Kode pada catatan kaki dibiarkan
     * polos, mengikuti dokumen ber-TTE yang dijadikan acuan dan dokumen bukti
     * permintaan: lambang adalah penanda tanda tangan, sedangkan kode di
     * catatan kaki hanyalah penunjuk.
     */
    protected function kodeQr(string $tautan, bool $berlambang = true): string
    {
        return KodeQrBerlogo::dataUri(
            $tautan,
            $berlambang ? public_path('images/logo-bps.png') : null,
        );
    }

    /**
     * Alamat verifikasi yang disandikan ke dalam kode QR.
     *
     * Dibentuk dari `config('app.url')`, bukan dari `route()` apa adanya.
     * `route()` mengambil akar alamat dari permintaan HTTP yang sedang
     * berjalan, sehingga BAST yang disahkan lewat panel di `127.0.0.1:8000`
     * membawa alamat itu ke dalam kodenya — alamat yang pada ponsel pemindainya
     * menunjuk balik ke ponsel itu sendiri. Dokumen BAST beredar ke luar sistem
     * dan membeku begitu disahkan, jadi alamatnya harus berasal dari tetapan
     * pemasangan, bukan dari mesin yang kebetulan menekan tombolnya.
     *
     * Kembarannya ada di {@see DokumenPermintaanService::tautan()}. Keduanya
     * sengaja berdiri sendiri: dokumen bukti permintaan sudah terbukti benar
     * dan tidak disentuh untuk keperluan BAST.
     */
    protected function tautan(string $rute, string $token): string
    {
        $akar = rtrim((string) config('app.url'), '/');

        if ($akar === '') {
            return route($rute, ['token' => $token]);
        }

        return $akar . route($rute, ['token' => $token], absolute: false);
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
