<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian ilustrasi "Alur Permintaan" pada sisi kiri halaman masuk.
 *
 * Ilustrasi ini hanya hiasan yang digerakkan CSS, tetapi dua hal tentangnya
 * tidak boleh lepas tanpa disadari: modulnya harus disembunyikan dari pembaca
 * layar (yang gantinya menerima satu kalimat sr-only), dan lima tahapnya harus
 * tetap memakai istilah fase pada Instruksi §10–§13.
 */
class IlustrasiAlurMasukTest extends TestCase
{
    use RefreshDatabase;

    private const TAHAP = [
        'Persetujuan Ketua Tim',
        'Verifikasi stok fisik',
        'Persetujuan akhir Kasubbag',
        'Penyiapan barang',
        'Pengambilan oleh pemohon',
    ];

    public function test_kelima_tahap_tampil_dengan_istilah_yang_sama(): void
    {
        $respons = $this->get('/admin/login')->assertOk();

        foreach (self::TAHAP as $tahap) {
            $respons->assertSee($tahap);
        }

        $this->assertSame(5, substr_count($respons->getContent(), 'class="fi-alur-baris"'));
    }

    public function test_modul_tersembunyi_dari_pembaca_layar_dan_punya_kalimat_pengganti(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="fi-alur relative" aria-hidden="true">', $html);
        $this->assertStringContainsString(
            'Ilustrasi alur permintaan barang: ' . implode(', ', self::TAHAP) . '.',
            preg_replace('/\s+/', ' ', $html),
        );
    }

    public function test_ilustrasi_tidak_memuat_data_nyata_dan_form_tetap_ada(): void
    {
        $respons = $this->get('/admin/login')->assertOk();

        // Tiga keadaan yang bergantian pada tiap tahap
        $respons->assertSee('Menunggu')->assertSee('Diproses')->assertSee('Selesai');

        // Overline lama diganti kotak ikon; kolom dan tombol masuk tidak berubah
        $respons->assertDontSee('MASUK KE SIMPBI')
            ->assertSee('Selamat Datang')
            ->assertSee('Alamat Email')
            ->assertSee('Kata Sandi')
            ->assertSee('Tetap masuk di perangkat ini');
    }
}
