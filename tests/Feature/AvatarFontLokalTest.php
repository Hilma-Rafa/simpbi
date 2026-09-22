<?php

namespace Tests\Feature;

use App\Filament\Pages\Pengaturan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-019: avatar dan font tidak lagi diminta ke pihak ketiga.
 *
 * Avatar kini berupa inisial lokal (CSS/markup), bukan gambar dari
 * ui-avatars.com. Inter dimuat dari berkas lokal (aset Filament) dengan nama
 * keluarga tetap "Inter"; hanya Plus Jakarta Sans dan Space Grotesk masih dari
 * fonts.bunny.net, sebab berkasnya belum tersedia secara lokal.
 */
class AvatarFontLokalTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_inisial_mengikuti_logika_penyedia_avatar_bawaan(): void
    {
        $this->assertSame('AS', Pengaturan::inisial('Administrator Sistem'));
        $this->assertSame('SA', Pengaturan::inisial('[SISTEM] Admin'));
        $this->assertSame('TS', Pengaturan::inisial('Tim Statistik Sosial'));
        $this->assertSame('B', Pengaturan::inisial('budi'));
        $this->assertSame('', Pengaturan::inisial('   '));
    }

    public function test_panel_tidak_meminta_avatar_ke_ui_avatars_dan_memakai_inisial_lokal(): void
    {
        $pengguna = $this->lengkapiAkun($this->buatPengguna('kasubbag', null, ['name' => 'Kasubbag Umum', 'email' => 'kasubbag.avatar@bps.go.id']));
        $this->actingAs($pengguna);

        foreach (['/admin', '/admin/pengaturan'] as $halaman) {
            $html = $this->get($halaman)->assertOk()->getContent();

            $this->assertStringNotContainsString('ui-avatars.com', $html, $halaman);
            $this->assertMatchesRegularExpression('#<span class="simpbi-avatar-inisial[^"]*" aria-hidden="true">KU</span>#', $html, $halaman);
        }
    }

    public function test_avatar_bilah_atas_dan_dropdown_memakai_inisial_yang_sama(): void
    {
        $pengguna = $this->lengkapiAkun($this->buatPengguna('petugas_gudang', null, ['name' => 'Petugas Gudang', 'email' => 'gudang.avatar@bps.go.id']));
        $this->actingAs($pengguna);

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#<span class="simpbi-avatar-inisial"[^>]*>PG</span>#', $html), 'Pemicu bilah atas.');
        $this->assertSame(1, preg_match_all('#<span class="simpbi-profil-avatar"[^>]*>PG</span>#', $html), 'Avatar pada dropdown profil.');
    }

    public function test_inter_dimuat_dari_berkas_lokal_dengan_nama_keluarga_tetap_inter(): void
    {
        foreach (['/admin/login', '/'] as $halaman) {
            $html = $this->get($halaman)->assertOk()->getContent();

            $this->assertSame(7, substr_count($html, "font-family:'Inter'"), "{$halaman}: satu deklarasi per subset aksara.");
            $this->assertStringContainsString('/fonts/filament/filament/inter/inter-latin-wght-normal-', $html);
            $this->assertStringNotContainsString('family=inter', $html, "{$halaman}: Inter tidak lagi diminta ke fonts.bunny.net.");
        }
    }

    public function test_dua_font_lain_masih_dari_bunny_karena_berkas_lokalnya_belum_ada(): void
    {
        foreach (['/admin/login', '/'] as $halaman) {
            $html = $this->get($halaman)->assertOk()->getContent();

            $this->assertStringContainsString('fonts.bunny.net/css?family=plus-jakarta-sans:600,700,800|space-grotesk:500,700', $html, $halaman);
        }
    }

    public function test_penyedia_font_panel_tidak_memuat_apa_pun_dari_pihak_ketiga(): void
    {
        $this->assertStringNotContainsString('bunny', (string) Filament::getPanel('admin')->getFontHtml());
        $this->assertSame('Inter', Filament::getPanel('admin')->getFontFamily());
    }

    public function test_berkas_font_lokal_yang_dirujuk_benar_benar_ada(): void
    {
        $html = $this->get('/admin/login')->getContent();
        preg_match_all("#url\('[^']*/fonts/filament/filament/inter/([^']+\.woff2)'\)#", $html, $cocok);

        $this->assertCount(7, $cocok[1]);
        foreach ($cocok[1] as $berkas) {
            $this->assertFileExists(public_path('fonts/filament/filament/inter/' . $berkas));
        }
    }
}
