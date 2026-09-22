<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Kewenangan membuka panel SIMPBI.
 *
 * Dua hal dijaga di sini, dan keduanya pernah terbuka tanpa disadari.
 *
 * Pertama, model pengguna wajib mengumumkan kewenangannya lewat FilamentUser.
 * Filament menolak dengan 403 setiap pengguna yang modelnya tidak
 * melakukannya — kecuali ketika aplikasi berjalan pada lingkungan `local`.
 * Akibatnya sistem tampak baik-baik saja sepanjang pengembangan, lalu menolak
 * seluruh pengguna begitu dipasang di peladen. Pengujian ini menahan keadaan
 * itu terulang seandainya antarmuka modelnya kelak dirapikan orang lain.
 *
 * Kedua, akun yang dinonaktifkan Administrator harus benar-benar kehilangan
 * akses, termasuk sesi yang sudah terlanjur berjalan ketika ia dinonaktifkan.
 */
class AksesPanelTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    public function test_model_pengguna_mengumumkan_kewenangan_panel(): void
    {
        $this->assertInstanceOf(
            FilamentUser::class,
            $this->buatPengguna('admin'),
            'Tanpa FilamentUser, seluruh pengguna ditolak 403 di luar lingkungan local.',
        );
    }

    public function test_akun_aktif_dapat_membuka_panel(): void
    {
        $this->actingAs($this->lengkapiAkun($this->buatPengguna('kasubbag')))
            ->get(Dashboard::getUrl())
            ->assertOk();
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['status_aktif' => false]);

        $this->actingAs($pengguna)
            ->get(Dashboard::getUrl())
            ->assertForbidden();
    }

    /**
     * Penonaktifan berlaku seketika. Menunggu sesinya habis sendiri berarti
     * akun yang baru saja dicabut haknya masih dapat menyetujui permintaan
     * atau mengeluarkan barang selama berjam-jam sesudahnya.
     */
    public function test_penonaktifan_berlaku_pada_sesi_yang_sedang_berjalan(): void
    {
        $pengguna = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));

        $this->actingAs($pengguna)->get(Dashboard::getUrl())->assertOk();

        $pengguna->forceFill(['status_aktif' => false])->save();

        $this->actingAs($pengguna->refresh())->get(Dashboard::getUrl())->assertForbidden();
    }

    public function test_kewenangan_dibaca_dari_status_aktif(): void
    {
        $panel = Filament::getPanel('admin');

        $aktif = $this->buatPengguna('tim', $this->buatTim());
        $mati  = $this->buatPengguna('tim', $this->buatTim('Statistik Distribusi'), ['status_aktif' => false]);

        $this->assertTrue($aktif->canAccessPanel($panel));
        $this->assertFalse($mati->canAccessPanel($panel));
    }
}
