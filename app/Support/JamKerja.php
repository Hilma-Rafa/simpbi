<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Perhitungan batas waktu tahapan dalam satuan jam kerja.
 *
 * Tabel pengaturan sejak awal menamai batas tahapan sebagai "jam kerja"
 * (lihat keterangan pada migrasi pengaturan), tetapi penetapannya memakai
 * now()->addHours(), yaitu jam dinding biasa. Akibatnya batas empat jam yang
 * mulai berjalan pukul 15.00 jatuh pukul 19.00 — di luar jam kerja, ketika
 * tidak seorang pun dapat menindaklanjuti — dan tahapan yang dimulai Jumat
 * sore hangus pada akhir pekan. Kelas ini menutup selisih itu: hitungan hanya
 * berjalan pada hari kerja pukul 08.00 sampai 16.00, sesuai waktu operasional
 * pada Instruksi §17.
 *
 * Jam operasional ditulis sebagai tetapan, bukan disimpan pada tabel
 * pengaturan, karena Instruksi menetapkannya sebagai jam kantor yang berlaku
 * untuk seluruh sistem — bukan parameter yang diatur per pemasangan seperti
 * lama batas tiap tahapan.
 */
class JamKerja
{
    /** Jam mulai operasional (Instruksi §17). */
    public const MULAI = 8;

    /** Jam selesai operasional (Instruksi §17). */
    public const SELESAI = 16;

    /** Banyaknya jam kerja dalam satu hari. */
    public const JAM_PER_HARI = self::SELESAI - self::MULAI;

    /**
     * Menghitung batas waktu setelah sekian jam kerja berjalan.
     *
     * Hitungan dimulai dari titik jam kerja terdekat, sehingga tahapan yang
     * dimulai pukul 17.00 baru mulai "berdetak" pukul 08.00 keesokan harinya.
     * Sisa jam yang tidak tertampung hari itu dilanjutkan ke hari kerja
     * berikutnya, melompati Sabtu dan Minggu.
     */
    public static function batas(int|float $jam, ?CarbonInterface $dari = null): Carbon
    {
        $waktu     = static::geserKeJamKerja($dari ? Carbon::instance($dari) : now());
        $sisaMenit = (int) round($jam * 60);

        while ($sisaMenit > 0) {
            $akhirHari = $waktu->copy()->setTime(self::SELESAI, 0);

            // Selisih dihitung dari cap waktu, bukan diffInMinutes(), agar
            // hasilnya tetap bilangan bulat tak bertanda pada semua versi Carbon.
            $tersedia = (int) round(($akhirHari->getTimestamp() - $waktu->getTimestamp()) / 60);

            if ($sisaMenit <= $tersedia) {
                return $waktu->addMinutes($sisaMenit);
            }

            $sisaMenit -= $tersedia;
            $waktu = static::geserKeJamKerja($waktu->copy()->addDay()->setTime(self::MULAI, 0));
        }

        return $waktu;
    }

    /**
     * Menggeser satu waktu ke titik jam kerja terdekat yang belum terlewat.
     *
     * Waktu yang sudah berada di dalam jam kerja dikembalikan apa adanya;
     * selebihnya dimajukan ke pukul 08.00 pada hari kerja berikutnya.
     */
    public static function geserKeJamKerja(CarbonInterface $waktu): Carbon
    {
        $hasil = Carbon::instance($waktu)->copy();

        // Pengulangan diperlukan karena satu penggeseran dapat memunculkan
        // syarat berikutnya: Jumat pukul 17.00 menjadi Sabtu pukul 08.00,
        // yang masih harus digeser lagi ke Senin.
        while (true) {
            if ($hasil->isWeekend()) {
                $hasil = $hasil->addDay()->setTime(self::MULAI, 0);

                continue;
            }

            if ($hasil->hour < self::MULAI) {
                return $hasil->setTime(self::MULAI, 0);
            }

            if ($hasil->hour >= self::SELESAI) {
                $hasil = $hasil->addDay()->setTime(self::MULAI, 0);

                continue;
            }

            return $hasil;
        }
    }
}
