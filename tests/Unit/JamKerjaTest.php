<?php

namespace Tests\Unit;

use App\Support\JamKerja;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pengujian perhitungan batas waktu tahapan dalam satuan jam kerja.
 *
 * Kekeliruan di sini tidak terlihat langsung pada tampilan — akibatnya baru
 * muncul berhari-hari kemudian berupa permintaan yang hangus terlalu cepat
 * atau kunci stok yang menggantung terlalu lama. Karena itu batas-batas yang
 * rawan diperiksa satu per satu: pergantian hari, akhir pekan, dan waktu
 * mulai yang berada di luar jam kantor.
 */
class JamKerjaTest extends TestCase
{
    public function test_batas_dalam_hari_yang_sama_tidak_berpindah_hari(): void
    {
        $hasil = JamKerja::batas(4, Carbon::parse('2026-09-08 09:00'));

        $this->assertSame('2026-09-08 13:00', $hasil->format('Y-m-d H:i'));
    }

    public function test_sisa_jam_dilanjutkan_ke_hari_kerja_berikutnya(): void
    {
        // Satu jam tersisa pada Selasa, tiga jam sisanya jatuh Rabu pagi
        $hasil = JamKerja::batas(4, Carbon::parse('2026-09-08 15:00'));

        $this->assertSame('2026-09-09 11:00', $hasil->format('Y-m-d H:i'));
    }

    public function test_akhir_pekan_dilompati(): void
    {
        $hasil = JamKerja::batas(4, Carbon::parse('2026-09-11 15:00'));

        $this->assertSame('2026-09-14 11:00', $hasil->format('Y-m-d H:i'));
    }

    public function test_tahapan_yang_dimulai_di_luar_jam_kerja_baru_berjalan_esok_pagi(): void
    {
        // Jumat pukul 17.00 belum menghabiskan satu menit pun jam kerja
        $hasil = JamKerja::batas(2, Carbon::parse('2026-09-11 17:00'));

        $this->assertSame('2026-09-14 10:00', $hasil->format('Y-m-d H:i'));
    }

    public function test_tahapan_yang_dimulai_pada_akhir_pekan_dihitung_dari_senin(): void
    {
        $hasil = JamKerja::batas(1, Carbon::parse('2026-09-12 10:00'));

        $this->assertSame('2026-09-14 09:00', $hasil->format('Y-m-d H:i'));
    }

    public function test_dua_puluh_empat_jam_kerja_sama_dengan_tiga_hari_kerja(): void
    {
        $hasil = JamKerja::batas(24, Carbon::parse('2026-09-14 08:00'));

        $this->assertSame('2026-09-16 16:00', $hasil->format('Y-m-d H:i'));
    }

    public function test_batas_selalu_jatuh_di_dalam_jam_kerja(): void
    {
        // Diperiksa menyeluruh: berapa pun titik mulainya, hasilnya tidak
        // boleh jatuh di akhir pekan atau di luar pukul 08.00-16.00, sebab
        // batas semacam itu mustahil ditindaklanjuti oleh petugas.
        $mulai = Carbon::parse('2026-09-07 00:00');

        for ($i = 0; $i < 24 * 9; $i++) {
            $hasil = JamKerja::batas(5, $mulai->copy()->addHours($i));

            $this->assertFalse($hasil->isWeekend(), "Jatuh pada akhir pekan: {$hasil}");
            $this->assertGreaterThanOrEqual(JamKerja::MULAI, $hasil->hour, "Terlalu pagi: {$hasil}");
            $this->assertLessThanOrEqual(JamKerja::SELESAI, $hasil->hour, "Terlalu sore: {$hasil}");
        }
    }
}
