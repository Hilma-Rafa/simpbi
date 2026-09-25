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
     * Nilai baris contoh template per tajuk seragam, dalam bentuk yang sama
     * dengan hasil pembacaan biasa; null bila pengenalan baris contoh tidak
     * diaktifkan.
     *
     * @var array<string,string>|null
     */
    protected ?array $contoh = null;

    /** Nomor baris yang dilewati karena sama persis dengan baris contoh. @var list<int> */
    public array $contohDilewati = [];

    /**
     * Mengaktifkan pengenalan baris contoh template.
     *
     * Template selalu membawa satu baris contoh, dan baris itu kerap tertinggal
     * di berkas yang diunggah. Isinya tidak berbahaya karena salah, melainkan
     * karena tampak benar: kode barang contoh pada template Stok Masuk menunjuk
     * barang yang memang ada, sehingga baris yang lupa dihapus dapat menambah
     * stok sungguhan. Baris yang seluruh selnya sama persis dengan contoh
     * karena itu dilewati; baris contoh yang sudah diubah pengguna — satu sel
     * saja — tetap terbaca sebagai data biasa.
     *
     * Contohnya dinormalkan seperti PembuatTemplate menuliskannya dan seperti
     * baris biasa dibaca: teks dipangkas, dan contoh kolom tanggal (DD/MM/YYYY,
     * ditulis sebagai sel tanggal) menjadi Y-m-d.
     *
     * @param  list<Kolom>  $kolom
     */
    public function lewatiContoh(array $kolom): static
    {
        $this->contoh = [];
        foreach ($kolom as $k) {
            $nilai = trim($k->contoh);
            if ($k->format === Kolom::TANGGAL && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $nilai, $m) === 1) {
                $nilai = $m[3] . '-' . $m[2] . '-' . $m[1];
            }
            $this->contoh[$k->tajukSeragam()] = $nilai;
        }

        return $this;
    }

    /**
     * Mencatat dan melaporkan baris yang harus dilewati sebagai baris contoh.
     * Selalu false bila pengenalan contoh tidak diaktifkan.
     *
     * @param  array<string,string>  $isi
     */
    protected function barisContoh(array $isi, int $nomorBaris): bool
    {
        if ($this->contoh === null || ! $this->samaDenganContoh($isi)) {
            return false;
        }

        $this->contohDilewati[] = $nomorBaris;

        return true;
    }

    /**
     * Apakah isi baris sama persis dengan baris contoh: setiap kolom template
     * berisi nilai contohnya, dan kolom lain yang ditambahkan pengguna kosong.
     *
     * @param  array<string,string>  $isi
     */
    protected function samaDenganContoh(array $isi): bool
    {
        foreach ($this->contoh as $tajuk => $nilai) {
            if (($isi[$tajuk] ?? '') !== $nilai) {
                return false;
            }
        }

        foreach ($isi as $tajuk => $nilai) {
            if (! array_key_exists($tajuk, $this->contoh) && $nilai !== '') {
                return false;
            }
        }

        return true;
    }

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

                if ($this->barisContoh($isi, $nomorBaris)) {
                    continue;
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
