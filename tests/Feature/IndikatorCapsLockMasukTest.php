<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian penanda Caps Lock di bawah kolom kata sandi pada halaman masuk.
 *
 * Penandanya murni tampilan (Alpine bawaan Filament), sehingga yang dijaga di
 * sini hanya kehadiran dan aksesibilitas markup-nya: baris pengumuman harus
 * sudah ada sejak halaman dimuat agar pembaca layar mengumumkan perubahannya,
 * dan letaknya harus tepat sesudah kolom kata sandi, bukan di tempat lain.
 * Perilaku tombol (keydown/keyup/fokus) diperiksa lewat peramban, bukan di sini.
 */
class IndikatorCapsLockMasukTest extends TestCase
{
    use RefreshDatabase;

    private function halaman(): string
    {
        return $this->get('/admin/login')->assertOk()->getContent();
    }

    public function test_penanda_ada_dengan_atribut_aksesibilitas_yang_benar(): void
    {
        $html = $this->halaman();

        $this->assertStringContainsString('class="fi-simpbi-caps-pesan" role="status" aria-live="polite"', $html);
        $this->assertStringContainsString('Caps Lock aktif', $html);

        // Ikon peringatan bersifat hiasan, jadi disembunyikan dari pembaca layar
        $this->assertMatchesRegularExpression('/<svg[^>]*aria-hidden="true"[^>]*>/', $html);
    }

    public function test_pesan_tersembunyi_sampai_caps_lock_terdeteksi(): void
    {
        $html = $this->halaman();

        $this->assertMatchesRegularExpression(
            '/<span class="fi-simpbi-caps-isi" x-cloak x-show="aktif">/',
            $html,
        );
        $this->assertStringContainsString("getModifierState('CapsLock')", $html);
    }

    public function test_penanda_terletak_sesudah_kolom_kata_sandi_dan_sebelum_ingat_saya(): void
    {
        $html = $this->halaman();

        $sandi = strpos($html, 'autocomplete="current-password"');
        $penanda = strpos($html, 'fi-simpbi-caps-pesan');
        $ingat = strpos($html, 'Tetap masuk di perangkat ini');

        $this->assertNotFalse($sandi);
        $this->assertNotFalse($penanda);
        $this->assertNotFalse($ingat);
        $this->assertTrue($sandi < $penanda && $penanda < $ingat);
    }

    public function test_penanda_tidak_menyentuh_nilai_kata_sandi(): void
    {
        $html = $this->halaman();

        // Kolom sandi tetap memakai atribut bawaan; penanda tidak menambah
        // wire:model atau atribut nilai apa pun pada elemennya sendiri.
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        preg_match('/<div\s+class="fi-simpbi-caps".*?<\/div>/s', $html, $blok);
        $this->assertNotEmpty($blok);
        $this->assertStringNotContainsString('wire:model', $blok[0]);
        $this->assertStringNotContainsString('<input', $blok[0]);
    }
}
