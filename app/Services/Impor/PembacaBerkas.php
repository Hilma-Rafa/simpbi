<?php

namespace App\Services\Impor;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Reader as PembacaCsv;
use OpenSpout\Reader\XLSX\Reader as PembacaXlsx;

/**
 * Pembacaan berkas impor menjadi baris bertajuk.
 *
 * Berkas yang diunggah pengguna tidak pernah serapi contohnya. Kolomnya bisa
 * tertukar urutan, tajuknya bisa berbeda besar-kecil hurufnya atau kelebihan
 * spasi, dan barisnya bisa kosong di tengah. Semua itu ditangani di sini agar
 * setiap pengimpor hanya berurusan dengan baris yang sudah bersih.
 *
 * Pencocokan kolom memakai tajuknya, bukan urutannya. Pengguna yang menambah
 * satu kolom catatan sendiri di tengah berkas — kebiasaan yang sangat lazim
 * pada lembar sebar kantor — tidak boleh membuat seluruh impornya salah kolom.
 */
class PembacaBerkas
{
    /**
     * Membaca berkas menjadi daftar baris bertajuk.
     *
     * Kunci setiap baris adalah tajuk kolom yang sudah diseragamkan, sehingga
     * "Nama Tim", "nama tim", dan " NAMA TIM " sama-sama menjadi `nama tim`.
     *
     * @return list<array{nomor:int,isi:array<string,string>}>
     */
    public function baca(string $lintasan): array
    {
        $pembaca = str_ends_with(strtolower($lintasan), '.csv')
            ? new PembacaCsv()
            : new PembacaXlsx();

        $pembaca->open($lintasan);

        $tajuk = [];
        $hasil = [];

        foreach ($pembaca->getSheetIterator() as $lembar) {
            $nomorBaris = 0;

            foreach ($lembar->getRowIterator() as $baris) {
                $nomorBaris++;
                $sel = array_map(
                    fn ($c) => $this->teks($c->getValue()),
                    $baris->getCells(),
                );

                if ($nomorBaris === 1) {
                    $tajuk = array_map(fn (string $t) => Kolom::seragamkan($t), $sel);

                    continue;
                }

                // Baris yang seluruh selnya kosong dilewati diam-diam: lembar
                // sebar kerap menyisakan baris kosong di bawah data, dan
                // melaporkannya sebagai galat hanya akan membuat ringkasan
                // impor penuh keluhan yang tidak perlu ditindaklanjuti.
                if (collect($sel)->every(fn (string $v) => $v === '')) {
                    continue;
                }

                $isi = [];
                foreach ($tajuk as $kolom => $nama) {
                    if ($nama === '') {
                        continue;
                    }
                    $isi[$nama] = $sel[$kolom] ?? '';
                }

                $hasil[] = ['nomor' => $nomorBaris, 'isi' => $isi];
            }

            // Hanya lembar pertama yang dibaca. Lembar berikutnya pada template
            // kami berisi petunjuk pengisian, bukan data.
            break;
        }

        $pembaca->close();

        return $hasil;
    }

    protected function teks(mixed $nilai): string
    {
        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (is_bool($nilai)) {
            return $nilai ? '1' : '0';
        }

        // Angka dari lembar sebar kerap datang sebagai float, sehingga NIP
        // 197001012000121001 dapat berubah menjadi 1.9700101200012E+17 bila
        // dirangkai apa adanya. Bilangan bulat karena itu dicetak sebagai
        // bilangan bulat.
        if (is_float($nilai) && floor($nilai) === $nilai && abs($nilai) < PHP_INT_MAX) {
            return (string) (int) $nilai;
        }

        return trim((string) $nilai);
    }
}
