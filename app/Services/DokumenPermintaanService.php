<?php

namespace App\Services;

use App\Models\PermintaanBarang;
use App\Models\User;
use App\Support\KodeQrBerlogo;
use App\Support\TandaTangan;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $permintaan->loadMissing(['tim.ketuaTim', 'detail.barang', 'persetujuan.pelaksana']);

        $penyerah = $this->pelaksana($permintaan, 'penyiapan');

        /*
         * Pihak yang menerima selalu Ketua Tim pemohon, bukan orang yang
         * menekan tombol konfirmasi. Penerimaan boleh dikonfirmasi anggota
         * tim, tetapi yang bertanggung jawab atas barang yang masuk ke unit
         * adalah ketuanya, dan itulah yang tercermin pada surat.
         */
        $penerima = $permintaan->tim?->ketuaTim;

        $token = $this->pastikanToken($permintaan);

        $bersama = [
            'permintaan'  => $permintaan,
            'logo'        => $this->logoBase64(),
            'penyerah'    => $penyerah?->name,
            'ttdPenyerah' => TandaTangan::dataUri($penyerah),
            'penerima'    => $penerima?->name,
            'ttdPenerima' => TandaTangan::dataUri($penerima),
            'pengesah'    => $this->pelaksana($permintaan, 'pengesahan')?->name,
        ];

        /*
         * Dua berkas dibentuk sekaligus, bukan satu yang diturunkan dari yang
         * lain belakangan.
         *
         * Keduanya kini serupa benar — sama-sama bercatatan kaki — dan hanya
         * berbeda pada tujuan kode QR stempel Kasubbag. Membentuk yang kedua
         * saat dipindai bukan pilihan: tanda tangan dibaca dari akun
         * penggunanya, sehingga hasilnya akan ikut berubah bila seseorang
         * menggambar ulang tanda tangannya, padahal dokumen yang sudah disahkan
         * seharusnya membeku.
         *
         * Karena itu keduanya dibekukan pada saat yang sama, dari bahan yang
         * sama persis.
         */
        $asli = 'bukti-permintaan/' . $permintaan->kode_permintaan . '.pdf';
        $berfootnote = static::lintasanBerfootnote($permintaan);

        /*
         * Kode pada catatan kaki menuju berkas asli pada kedua lembar, sesuai
         * bunyi keterangannya sendiri: "menampilkan file asli". Dengan begitu
         * pemindai tidak perlu tahu lembar mana yang sedang dipegangnya —
         * memindai catatan kakinya selalu berujung pada dokumen apa adanya.
         */
        $keAsli = $this->kodeQr($this->tautan('bukti.asli', $token), berlambang: false);

        // Dokumen asli: memindai stempel Kasubbag membawa pemindainya ke versi
        // berfootnote, sebagaimana lazimnya dokumen ber-TTE.
        Storage::disk('public')->put($asli, Pdf::loadView('pdf.bukti-permintaan', [
            ...$bersama,
            'qr'         => $this->kodeQr($this->tautan('bukti.pindai', $token)),
            'qrFootnote' => $keAsli,
        ])->setPaper('a4', 'portrait')->output());

        /*
         * Versi berfootnote: stempel Kasubbag pun menuju dokumen asli, supaya
         * penelusuran berakhir pada berkas apa adanya alih-alih berputar pada
         * lembar yang sudah bertanda.
         */
        Storage::disk('public')->put($berfootnote, Pdf::loadView('pdf.bukti-permintaan', [
            ...$bersama,
            'qr'         => $keAsli,
            'qrFootnote' => $keAsli,
        ])->setPaper('a4', 'portrait')->output());

        return $asli;
    }

    /**
     * Lintasan berkas versi berfootnote, yaitu lembar yang muncul ketika kode
     * QR pada dokumen asli dipindai.
     */
    public static function lintasanBerfootnote(PermintaanBarang $permintaan): string
    {
        return 'bukti-permintaan/' . $permintaan->kode_permintaan . '-berfootnote.pdf';
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
     * Alamat verifikasi yang disandikan ke dalam kode QR.
     *
     * Dibentuk dari `config('app.url')`, bukan dari `route()` apa adanya.
     * `route()` mengambil akar alamat dari permintaan HTTP yang sedang
     * berjalan, sehingga dokumen yang disahkan lewat panel di `127.0.0.1:8000`
     * membawa alamat itu ke dalam kodenya — alamat yang menunjuk balik ke
     * mesin pemindainya sendiri dan karena itu tidak pernah terbuka dari
     * ponsel. Dokumen ini beredar ke luar sistem dan membeku begitu terbit,
     * jadi alamatnya harus berasal dari tetapan pemasangan, bukan dari mesin
     * yang kebetulan menekan tombolnya.
     *
     * Ketika `APP_URL` belum diisi, alamat permintaan dipakai sebagai jalan
     * terakhir agar kode tetap terbentuk — lebih baik alamat yang perlu
     * diperbaiki daripada kode yang tidak menuju ke mana pun.
     */
    protected function tautan(string $rute, string $token): string
    {
        $akar = rtrim((string) config('app.url'), '/');

        if ($akar === '') {
            return route($rute, ['token' => $token]);
        }

        return $akar . route($rute, ['token' => $token], absolute: false);
    }

    /**
     * Menghasilkan kode QR sebagai data URI.
     *
     * Lambang instansi dibubuhkan pada kodenya sendiri, bukan ditumpangkan
     * sebagai elemen HTML di atasnya — lihat {@see KodeQrBerlogo} untuk
     * alasannya.
     *
     * Hanya stempel Kasubbag yang berlambang. Kode pada catatan kaki dibiarkan
     * polos, mengikuti dokumen ber-TTE yang dijadikan acuan: lambang adalah
     * penanda tanda tangan, sedangkan kode di catatan kaki hanyalah penunjuk
     * berkas. Lagi pula kode itu tercetak hanya selebar tiga sentimeter,
     * sehingga setiap modul yang tidak perlu dikorbankan lebih baik disisakan
     * bagi pemindainya.
     */
    protected function kodeQr(string $tautan, bool $berlambang = true): string
    {
        return KodeQrBerlogo::dataUri(
            $tautan,
            $berlambang ? public_path('images/logo-bps.png') : null,
        );
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
    /**
     * Pelaksana sebuah tahapan.
     *
     * Mengembalikan penggunanya, bukan sekadar namanya, sebab dokumen kini
     * membutuhkan dua hal darinya: nama yang tercetak di bawah garis, dan
     * tanda tangan yang dibubuhkan di atasnya.
     */
    protected function pelaksana(PermintaanBarang $permintaan, string $tahap): ?User
    {
        return $permintaan->persetujuan
            ->firstWhere('tahap', $tahap)
            ?->pelaksana;
    }
}