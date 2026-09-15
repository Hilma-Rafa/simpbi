<?php

namespace App\Services\Impor;

/**
 * Ringkasan hasil satu kali impor.
 *
 * Impor berkas yang diisi manusia hampir tidak pernah berhasil seluruhnya pada
 * percobaan pertama, dan yang paling dibutuhkan pengisinya bukan pesan
 * "gagal" melainkan keterangan baris mana yang bermasalah dan mengapa. Karena
 * itu galat dikumpulkan beserta nomor barisnya, bukan dilempar pada kesalahan
 * pertama: satu kali unggah sebaiknya menunjukkan seluruh perbaikan yang
 * perlu dilakukan, bukan satu per satu.
 */
class HasilImpor
{
    public int $ditambah = 0;

    public int $diperbarui = 0;

    public int $dilewati = 0;

    /** @var list<string> */
    public array $galat = [];

    public function catatGalat(int $nomorBaris, string $pesan): void
    {
        $this->galat[] = 'Baris ' . $nomorBaris . ': ' . $pesan;
    }

    public function adaGalat(): bool
    {
        return $this->galat !== [];
    }

    /** Banyaknya baris yang benar-benar masuk ke basis data. */
    public function berhasil(): int
    {
        return $this->ditambah + $this->diperbarui;
    }

    /** Kalimat ringkas untuk judul pemberitahuan. */
    public function judul(): string
    {
        if ($this->berhasil() === 0 && $this->adaGalat()) {
            return 'Tidak ada baris yang dapat diimpor';
        }

        return 'Impor selesai';
    }

    /** Uraian hasil, disusun hanya dari bagian yang benar-benar terjadi. */
    public function uraian(): string
    {
        $bagian = [];

        if ($this->ditambah > 0) {
            $bagian[] = $this->ditambah . ' baris baru';
        }
        if ($this->diperbarui > 0) {
            $bagian[] = $this->diperbarui . ' diperbarui';
        }
        if ($this->dilewati > 0) {
            $bagian[] = $this->dilewati . ' dilewati';
        }
        if ($this->adaGalat()) {
            $bagian[] = count($this->galat) . ' bermasalah';
        }

        return $bagian === [] ? 'Berkas tidak memuat baris data.' : implode(' · ', $bagian) . '.';
    }
}
