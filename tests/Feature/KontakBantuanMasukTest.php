<?php

namespace Tests\Feature;

use App\Support\KontakBantuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengujian tautan bantuan pada kaki halaman masuk.
 *
 * SIMPBI sengaja tidak menyediakan pemulihan kata sandi mandiri, sehingga
 * tautan ini satu-satunya jalan keluar pengguna yang terkunci. Kegagalannya
 * tidak akan dilaporkan siapa pun — orang yang terkunci justru tidak dapat
 * masuk untuk mengeluh — jadi keadaan nomor yang kosong maupun yang tidak
 * masuk akal diperiksa di sini.
 */
class KontakBantuanMasukTest extends TestCase
{
    use RefreshDatabase;

    protected function setelNomor(?string $nomor): void
    {
        DB::table('pengaturan')
            ->where('kunci', 'kontak_bantuan_wa')
            ->update(['nilai' => (string) $nomor]);
    }

    public function test_nomor_lokal_diubah_menjadi_tautan_wa_me(): void
    {
        $this->setelNomor('081234567890');

        $tautan = KontakBantuan::tautanWhatsApp();

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $tautan);
    }

    public function test_nomor_dengan_tanda_plus_dan_spasi_tetap_terbaca(): void
    {
        $this->setelNomor('+62 812-3456-7890');

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', KontakBantuan::tautanWhatsApp());
    }

    public function test_nomor_kosong_tidak_menghasilkan_tautan(): void
    {
        $this->setelNomor('');

        $this->assertNull(KontakBantuan::tautanWhatsApp());
    }

    public function test_nomor_yang_tidak_masuk_akal_tidak_menghasilkan_tautan(): void
    {
        $this->setelNomor('123');

        $this->assertNull(KontakBantuan::tautanWhatsApp());
    }

    public function test_halaman_masuk_menampilkan_tautan_ketika_nomor_terisi(): void
    {
        $this->setelNomor('081234567890');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Hubungi Sub-Bagian Umum')
            ->assertSee('https://wa.me/6281234567890', escape: false);
    }

    public function test_halaman_masuk_tanpa_nomor_tidak_memasang_tautan_mati(): void
    {
        $this->setelNomor('');

        $this->get('/admin/login')
            ->assertOk()
            // Kalimatnya tetap ada sebagai keterangan biasa
            ->assertSee('Hubungi Sub-Bagian Umum bila mengalami kendala masuk.')
            ->assertDontSee('wa.me');
    }
}
