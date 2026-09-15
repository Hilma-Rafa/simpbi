<?php

namespace App\Services\Impor;

/**
 * Keterangan satu kolom pada berkas impor.
 *
 * Dipakai untuk tiga hal sekaligus dari satu sumber: menyusun tajuk template,
 * menulis lembar petunjuk pengisiannya, dan membaca nilai dari berkas yang
 * diunggah kembali. Menyatukannya mencegah keadaan yang paling menjengkelkan
 * bagi pengisi berkas — template yang tajuknya tidak lagi sama dengan yang
 * dicari pengimpor.
 */
class Kolom
{
    public function __construct(
        /** Kunci internal, dipakai pengimpor. */
        public readonly string $kunci,

        /** Tajuk sebagaimana tercetak pada template. */
        public readonly string $judul,

        /** Wajib diisi atau boleh dikosongkan. */
        public readonly bool $wajib = false,

        /** Contoh isian, dicetak sebagai baris contoh pada template. */
        public readonly string $contoh = '',

        /** Penjelasan pada lembar petunjuk. */
        public readonly string $catatan = '',
    ) {}

    public static function buat(
        string $kunci,
        string $judul,
        bool $wajib = false,
        string $contoh = '',
        string $catatan = '',
    ): self {
        return new self($kunci, $judul, $wajib, $contoh, $catatan);
    }

    /** Tajuk kolom ini dalam bentuk seragam. */
    public function tajukSeragam(): string
    {
        return static::seragamkan($this->judul);
    }

    /**
     * Menyeragamkan sebuah tajuk agar pencocokannya tidak bergantung pada
     * penulisan.
     *
     * Dipakai dari dua arah — oleh template ketika menyusun tajuk, dan oleh
     * pembaca berkas ketika mencocokkannya kembali — sehingga aturannya wajib
     * hidup di satu tempat saja. Ketika keduanya sempat menormalkan dengan
     * cara berbeda, tajuk "Nama Tim *" pada template tidak pernah cocok
     * dengan "nama tim" yang dicari pembacanya, dan seluruh impor menghasilkan
     * nol baris tanpa satu pun keterangan mengapa.
     *
     * Bintang penanda kolom wajib ikut dibuang: ia penjelas bagi pembaca
     * manusia, bukan bagian dari nama kolomnya.
     */
    public static function seragamkan(string $tajuk): string
    {
        $bersih = preg_replace('/\s+/u', ' ', $tajuk) ?? '';
        $bersih = rtrim(trim($bersih), ' *');

        return trim(mb_strtolower($bersih));
    }
}
