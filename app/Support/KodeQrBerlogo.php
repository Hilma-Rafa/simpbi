<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;

/**
 * Kode QR yang lambangnya menyatu dengan kodenya.
 *
 * Sebelumnya lambang BPS ditumpangkan di atas gambar QR sebagai elemen HTML
 * berlatar putih membulat. Cara itu tidak pernah menyatukan keduanya: kode
 * tetap kode utuh, dan lambang duduk di atasnya sebagai stiker lengkap dengan
 * cincin putihnya — persis yang membuat hasil sistem menyimpang dari e-TTD
 * yang dicontohkan. Ia juga menyembunyikan modul yang masih dianggap ada oleh
 * kodenya, sehingga pembaca QR harus menambal sendiri bagian yang tertutup.
 *
 * Di sini lambang dibubuhkan pada sumbernya. Modul di tengah matriks benar-
 * benar dikosongkan lewat `setLogoSpace()`, jadi kode memang dibentuk dengan
 * rongga itu dan koreksi kesalahan tingkat H sudah memperhitungkannya. Lambang
 * lalu digambar ke dalam rongga tersebut dengan GD. Hasilnya satu berkas PNG
 * tunggal: tidak ada lapisan yang bisa bergeser, dan tidak ada alas putih yang
 * perlu digambar sebab daerah itu memang sudah kosong.
 */
class KodeQrBerlogo
{
    /**
     * Bagian lebar kode yang disediakan bagi lambang.
     *
     * Diukur dari `docs/contoh-ettd-yang-benar.png`: di sana lambang menempati
     * 15,8% lebar kode. Angka ini juga jauh di bawah seperempat luas matriks,
     * batas yang ditolak pustaka karena melampaui daya koreksi kesalahan.
     */
    protected const NISBAH_LAMBANG = 0.16;

    /**
     * Kelonggaran di sekeliling lambang, sebagai bagian dari lebar rongga.
     *
     * Tanpa ini tinta lambang menempel rapat pada modul gelap di kiri dan
     * kanannya, sehingga keduanya tampak berdempet alih-alih bersanding.
     */
    protected const KELONGGARAN = 0.88;

    /** Besar satu modul dalam piksel. */
    protected const SKALA = 8;

    /**
     * Membentuk kode QR sebagai data URI PNG.
     *
     * Data URI, bukan lintasan berkas, sebab DomPDF tidak mengambil gambar
     * lewat jaringan dan berkas sementara berarti sampah yang harus disapu.
     *
     * Mengembalikan rangkaian kosong bila pembentukan gagal: dokumen yang
     * terbit tanpa kode masih dapat ditandatangani basah, sedangkan dokumen
     * yang gagal terbit menghentikan alur permintaan.
     */
    public static function dataUri(string $tautan, ?string $lintasanLambang = null): string
    {
        try {
            $png = static::png($tautan, $lintasanLambang);
        } catch (\Throwable $e) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** Bita PNG kode QR, lengkap dengan lambang bila berkasnya tersedia. */
    protected static function png(string $tautan, ?string $lintasanLambang): string
    {
        $opsi = new QROptions([
            /*
             * Tingkat H memulihkan 30% kode yang rusak. Ia bukan pilihan
             * kenyamanan melainkan syarat: pustaka menolak mengosongkan ruang
             * lambang pada tingkat di bawahnya.
             */
            'eccLevel'        => EccLevel::H,
            'scale'           => static::SKALA,
            /*
             * Empat modul adalah zona sunyi yang disyaratkan standar QR.
             * Sebelumnya hanya dua: pada dokumen hal itu tersamarkan karena
             * kertasnya memang putih, tetapi gambarnya sendiri menjadi tidak
             * sah — pembaca QR bawaan pustaka ini pun gagal menemukan kodenya
             * ketika berkasnya dibaca terlepas dari halamannya.
             */
            'quietzoneSize'   => 4,
            'outputType'      => QROutputInterface::CUSTOM,
            'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
            // Gambar GD dikembalikan apa adanya supaya lambang masih dapat
            // dibubuhkan sebelum kode dipadatkan menjadi PNG.
            'returnResource'  => true,
        ]);

        $qr = new QRCode($opsi);

        // Alamat verifikasi selalu mengandung huruf kecil, sehingga ragam
        // alfanumerik yang lebih padat tidak dapat dipakai.
        $qr->addByteSegment($tautan);

        $matriks = $qr->getQRMatrix();
        $dimensi = $matriks->getVersion()?->getDimension() ?? 0;

        $lambang = static::bacaLambang($lintasanLambang);
        $modul   = $lambang ? static::modulLambang($dimensi) : 0;

        if ($modul > 0) {
            $matriks->setLogoSpace($modul);
        }

        /** @var GdImage $kode */
        $kode = $qr->renderMatrix($matriks);

        if ($lambang) {
            static::bubuhkanLambang($kode, $lambang, $matriks->getSize(), $dimensi, $modul);
            imagedestroy($lambang);
        }

        ob_start();
        imagepng($kode);
        $png = (string) ob_get_clean();

        imagedestroy($kode);

        return $png;
    }

    /**
     * Lebar rongga lambang dalam satuan modul.
     *
     * Dibulatkan ke angka ganjil karena dimensi matriks QR selalu ganjil:
     * rongga bertepi ganjil itulah yang dapat duduk tepat di pusat kode tanpa
     * bergeser setengah modul ke salah satu sisi.
     */
    protected static function modulLambang(int $dimensi): int
    {
        $modul = (int) round($dimensi * static::NISBAH_LAMBANG);

        if ($modul % 2 === 0) {
            $modul++;
        }

        return $modul;
    }

    /**
     * Membaca berkas lambang dan memangkasnya sampai ke tintanya.
     *
     * Berkas lambang menyisakan ruang kosong di sekeliling gambarnya. Bila
     * ikut diperkecil, ruang kosong itu memakan jatah rongga sehingga tintanya
     * tampak jauh lebih kecil daripada contoh yang dijadikan acuan.
     */
    protected static function bacaLambang(?string $lintasan): ?GdImage
    {
        if (! $lintasan || ! is_file($lintasan)) {
            return null;
        }

        $gambar = @imagecreatefrompng($lintasan);

        if ($gambar === false) {
            return null;
        }

        imagealphablending($gambar, false);
        imagesavealpha($gambar, true);

        $lebar  = imagesx($gambar);
        $tinggi = imagesy($gambar);

        $kiri = $lebar;
        $kanan = -1;
        $atas = $tinggi;
        $bawah = -1;

        for ($y = 0; $y < $tinggi; $y++) {
            for ($x = 0; $x < $lebar; $x++) {
                if (((imagecolorat($gambar, $x, $y) >> 24) & 0x7F) >= 120) {
                    continue;
                }

                $kiri  = min($kiri, $x);
                $kanan = max($kanan, $x);
                $atas  = min($atas, $y);
                $bawah = max($bawah, $y);
            }
        }

        if ($kanan < 0) {
            imagedestroy($gambar);

            return null;
        }

        $terpangkas = imagecrop($gambar, [
            'x'      => $kiri,
            'y'      => $atas,
            'width'  => $kanan - $kiri + 1,
            'height' => $bawah - $atas + 1,
        ]);

        imagedestroy($gambar);

        if ($terpangkas === false) {
            return null;
        }

        imagealphablending($terpangkas, false);
        imagesavealpha($terpangkas, true);

        return $terpangkas;
    }

    /**
     * Menggambar lambang ke dalam rongga yang sudah dikosongkan.
     *
     * Letaknya dihitung dari matriks, bukan ditetapkan dalam piksel: ukuran
     * kode berubah mengikuti panjang alamat yang disandikan, sehingga titik
     * pusat yang dipatri akan meleset begitu alamatnya memanjang — itulah yang
     * membuat lambang pada dokumen sebelumnya duduk di bawah-kanan pusat.
     */
    protected static function bubuhkanLambang(
        GdImage $kode,
        GdImage $lambang,
        int $ukuranMatriks,
        int $dimensi,
        int $modul,
    ): void {
        $kuadranSunyi = intdiv($ukuranMatriks - $dimensi, 2);
        $awalModul    = $kuadranSunyi + intdiv($dimensi - $modul, 2);

        $rongga = $modul * static::SKALA;
        $lebar  = (int) round($rongga * static::KELONGGARAN);
        $tinggi = (int) round($lebar * imagesy($lambang) / imagesx($lambang));

        $x = ($awalModul * static::SKALA) + intdiv($rongga - $lebar, 2);
        $y = ($awalModul * static::SKALA) + intdiv($rongga - $tinggi, 2);

        imagealphablending($kode, true);

        imagecopyresampled(
            $kode,
            $lambang,
            $x,
            $y,
            0,
            0,
            $lebar,
            $tinggi,
            imagesx($lambang),
            imagesy($lambang),
        );
    }
}
