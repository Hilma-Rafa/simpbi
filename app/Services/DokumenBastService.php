<?php

namespace App\Services;

use App\Models\BastMutasiAset;
use App\Support\KodeQrBerlogo;
use App\Support\TandaTangan;
use App\Support\TautanVerifikasi;
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
        $bast->loadMissing([
            'aset.kategori', 'timAsal.ketuaTim', 'timTujuan.ketuaTim',
            'dibuatOleh', 'disahkanOleh', 'dikonfirmasiOleh',
        ]);

        /*
         * Identitas dan tanda tangan kedua pihak diambil dari akun masing-masing
         * — nama, sekaligus tanda tangannya — bukan dari kolom pihak_penyerah /
         * pihak_penerima yang diketik operator. Operator pembuat BAST bukan
         * otomatis pemilik tanda tangan; ia hanya mencatat transaksinya.
         *
         * Pihak penyerah adalah Ketua Tim kerja asal. Pihak penerima adalah
         * Ketua Tim kerja tujuan; sesudah konfirmasi, dirujuk lewat user_id yang
         * benar-benar mengkonfirmasi (dikonfirmasi_oleh_id) sebagai rujukan yang
         * stabil, dan sebelum konfirmasi cukup dari Ketua Tim tim tujuan agar
         * namanya sudah terbaca. Tanda tangan penerima baru dibubuhkan setelah
         * ia mengkonfirmasi (UC-18) — ia tidak menggambar apa pun.
         *
         * Susunan, tata letak, wording, urutan, QR, nomor, dan e-TTD Kasubbag
         * tidak berubah: hanya sumber nama dan tanda tangan pihak yang dibetulkan.
         */
        $penyerah = $bast->timAsal?->ketuaTim;
        $penerima = $bast->dikonfirmasiOleh ?? $bast->timTujuan?->ketuaTim;

        $namaPenyerah = $penyerah?->name ?? '';
        $namaPenerima = $penerima?->name ?? '';

        $ttdPenyerah = TandaTangan::dataUri($penyerah);
        $ttdPenerima = $bast->dikonfirmasi_at
            ? TandaTangan::dataUri($penerima)
            : null;

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
            ? TautanVerifikasi::untuk('verifikasi.bast', $this->pastikanToken($bast))
            : null;

        $pdf = Pdf::loadView('pdf.bast-mutasi', [
            'bast'        => $bast,
            'qr'          => $tautan ? $this->kodeQr($tautan) : '',
            'qrFootnote'  => $tautan ? $this->kodeQr($tautan, berlambang: false) : '',
            'logo'         => $this->logoBase64(),
            'namaPenyerah' => $namaPenyerah,
            'namaPenerima' => $namaPenerima,
            'ttdPenyerah'  => $ttdPenyerah ?? '',
            'ttdPenerima'  => $ttdPenerima ?? '',
        ])->setPaper('a4', 'portrait');

        $nama = 'bast-mutasi/' . $bast->nomor_bast . '.pdf';

        // Disk privat: dokumen memuat tanda tangan dan hanya dilayani rute yang
        // dijaga (routes/web.php), tidak pernah lewat /storage.
        Storage::disk('local')->put($nama, $pdf->output());

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

    protected function logoBase64(): string
    {
        $path = public_path('images/logo-bps.png');

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
