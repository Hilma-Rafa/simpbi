<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GantiKataSandi;
use App\Filament\Pages\LengkapiAkun;
use App\Support\TandaTangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pelengkapan data awal akun pada pemakaian pertama.
 *
 * Dua gerbang berurutan menahan pengguna baru: penggantian kata sandi lebih
 * dulu, lalu pelengkapan nomor WhatsApp dan tanda tangan. Urutannya penting,
 * dan yang diwajibkan berbeda menurut peran, sehingga keduanya dijaga di sini.
 */
class LengkapiAkunTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function pngSah(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_pengguna_penandatangan_tanpa_data_wajib_dialihkan(): void
    {
        // Petugas Gudang membubuhkan tanda tangan tergambar pada bukti, jadi
        // wajib punya tanda tangan tersimpan. Nomornya sudah ada, sehingga yang
        // menahannya benar-benar tanda tangan yang belum terdaftar.
        $pengguna = $this->buatPengguna('petugas_gudang', null, ['no_hp' => '6281200000000']);

        $this->actingAs($pengguna)
            ->get(Dashboard::getUrl())
            ->assertRedirect(LengkapiAkun::getUrl());
    }

    public function test_kasubbag_tidak_diwajibkan_tanda_tangan_hanya_nomor_wa(): void
    {
        // Pengesahan Kasubbag memakai e-TTD (nama + QR), bukan gambar tersimpan,
        // sehingga Kasubbag cukup melengkapi nomor WhatsApp — tanpa tanda tangan.
        $bernomorTanpaTtd = $this->buatPengguna('kasubbag', null, ['no_hp' => '6281200000000']);

        $this->actingAs($bernomorTanpaTtd)
            ->get(Dashboard::getUrl())
            ->assertOk();
    }

    public function test_gerbang_kata_sandi_didahulukan_daripada_pelengkapan(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['harus_ganti_sandi' => true]);

        $this->actingAs($pengguna)
            ->get(Dashboard::getUrl())
            ->assertRedirect(GantiKataSandi::getUrl());
    }

    public function test_akun_yang_sudah_lengkap_tidak_dialihkan(): void
    {
        $pengguna = $this->lengkapiAkun($this->buatPengguna('kasubbag'));

        $this->actingAs($pengguna)
            ->get(Dashboard::getUrl())
            ->assertOk();
    }

    public function test_admin_tidak_diwajibkan_melengkapi_apa_pun(): void
    {
        // Admin Sistem tidak menjalankan alur operasional, sehingga tidak
        // diwajibkan bernomor WhatsApp maupun bertanda tangan.
        $this->actingAs($this->buatPengguna('admin'))
            ->get(Dashboard::getUrl())
            ->assertOk();
    }

    public function test_tim_hanya_wajib_nomor_wa_bukan_tanda_tangan(): void
    {
        // Anggota Tim tidak menandatangani dokumen; ia hanya perlu nomor WA.
        $tanpaNomor = $this->buatPengguna('tim', $this->buatTim());
        $this->actingAs($tanpaNomor)
            ->get(Dashboard::getUrl())
            ->assertRedirect(LengkapiAkun::getUrl());

        // Sesi dibersihkan sebelum berganti akun: AuthenticateSession
        // mengeluarkan pengguna begitu sidik kata sandi pada sesi tak lagi
        // cocok, sehingga tanpa ini hasilnya pengalihan ke halaman masuk.
        $this->flushSession();

        $berNomor = $this->buatPengguna('tim', $this->buatTim('Statistik Distribusi'), [
            'no_hp' => '6281200000000',
        ]);
        $this->actingAs($berNomor)
            ->get(Dashboard::getUrl())
            ->assertOk();
    }

    public function test_halaman_menyimpan_nomor_wa_dan_tanda_tangan(): void
    {
        $pengguna = $this->buatPengguna('ketua_tim', $this->buatTim());
        $this->actingAs($pengguna);

        Livewire::test(LengkapiAkun::class)
            ->fillForm([
                'no_hp'        => '081294780409',
                'tanda_tangan' => $this->pngSah(),
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $pengguna->refresh();

        $this->assertSame('6281294780409', $pengguna->no_hp, 'Nomor disimpan dalam bentuk seragam.');
        $this->assertTrue(TandaTangan::terdaftar($pengguna));
    }

    public function test_halaman_menolak_pengiriman_tanpa_data_wajib(): void
    {
        // Ketua Tim wajib kedua-duanya: nomor WhatsApp dan tanda tangan.
        $pengguna = $this->buatPengguna('ketua_tim', $this->buatTim());
        $this->actingAs($pengguna);

        Livewire::test(LengkapiAkun::class)
            ->fillForm(['no_hp' => null, 'tanda_tangan' => null])
            ->call('simpan')
            ->assertHasFormErrors(['no_hp', 'tanda_tangan']);
    }

    // =====================================================================
    // PESAN VALIDASI BERBAHASA INDONESIA
    // =====================================================================

    /**
     * Peran penandatangan (Ketua Tim, Petugas Gudang) mengisi dua kolom wajib,
     * sehingga kedua pesannya diuji sekaligus — termasuk memastikan tidak ada
     * kalimat bawaan Laravel berbahasa Inggris yang tersisa.
     */
    public function test_pesan_wajib_penandatangan_berbahasa_indonesia(): void
    {
        foreach (['ketua_tim', 'petugas_gudang'] as $peran) {
            $this->flushSession();
            $pengguna = $this->buatPengguna($peran, $peran === 'ketua_tim' ? $this->buatTim('Tim ' . $peran) : null);
            $this->actingAs($pengguna);

            Livewire::test(LengkapiAkun::class)
                ->fillForm(['no_hp' => null, 'tanda_tangan' => null])
                ->call('simpan')
                ->assertHasFormErrors(['no_hp', 'tanda_tangan'])
                ->assertSee('Nomor WhatsApp wajib diisi.')
                ->assertSee('Tanda tangan wajib dibubuhkan.')
                ->assertDontSee('field is required');
        }
    }

    /**
     * Peran yang hanya wajib nomor WhatsApp (Anggota Tim, Kasubbag Umum) tidak
     * memiliki kolom tanda tangan, sehingga yang diuji hanya pesan nomornya —
     * dan pesan itu pun harus Bahasa Indonesia.
     */
    public function test_pesan_wajib_nomor_wa_berbahasa_indonesia(): void
    {
        foreach (['tim', 'kasubbag'] as $peran) {
            $this->flushSession();
            $pengguna = $this->buatPengguna($peran, $peran === 'tim' ? $this->buatTim('Tim ' . $peran) : null);
            $this->actingAs($pengguna);

            Livewire::test(LengkapiAkun::class)
                ->fillForm(['no_hp' => null])
                ->call('simpan')
                ->assertHasFormErrors(['no_hp'])
                ->assertSee('Nomor WhatsApp wajib diisi.')
                ->assertDontSee('field is required');
        }
    }

    /** Batas panjang nomor pun berpesan Bahasa Indonesia, bukan "must not be greater than". */
    public function test_pesan_maksimal_panjang_nomor_wa_berbahasa_indonesia(): void
    {
        $pengguna = $this->buatPengguna('kasubbag');
        $this->actingAs($pengguna);

        Livewire::test(LengkapiAkun::class)
            ->fillForm(['no_hp' => str_repeat('8', 25)])
            ->call('simpan')
            ->assertHasFormErrors(['no_hp' => 'max'])
            ->assertSee('Nomor WhatsApp maksimal 20 karakter.')
            ->assertDontSee('must not be greater than');
    }
}
