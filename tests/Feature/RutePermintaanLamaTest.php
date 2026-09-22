<?php

namespace Tests\Feature;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\PermintaanBarang;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-026: rute lama GET /admin/permintaan-barangs/{record}.
 *
 * Rute dipertahankan sebagai pengalih agar tautan lama (mis. pesan WhatsApp
 * lama) tetap mendarat pada pop-up Rincian; hanya tampilan
 * filament.pages.detail-permintaan yang tidak pernah dirender dan dihapus.
 * Perilakunya harus sama dengan Tabel 2 matriks-akses.md untuk semua peran:
 * 302 bagi yang berhak, 404 bagi tim lain, dan tidak pernah 500.
 */
class RutePermintaanLamaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private PermintaanBarang $permintaan;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $tim = $this->buatTim('Tim Pemilik');
        $this->permintaan = $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(), 'diminta' => 1]],
        );
    }

    private function buka(string $peran, ?string $namaTim = null)
    {
        $tim = $namaTim ? $this->buatTim($namaTim) : null;
        $pengguna = $this->lengkapiAkun($this->buatPengguna($peran, $tim));
        $this->flushSession();
        auth()->forgetGuards();

        return $this->actingAs($pengguna)->get('/admin/permintaan-barangs/' . $this->permintaan->getKey());
    }

    public function test_peran_yang_berhak_dialihkan_ke_pop_up_rincian(): void
    {
        $tujuan = PermintaanBarangResource::urlRincian($this->permintaan);

        foreach (['admin', 'kasubbag', 'petugas_gudang'] as $peran) {
            $this->buka($peran)->assertStatus(302)->assertRedirect($tujuan);
        }
    }

    public function test_ketua_tim_dan_tim_milik_permintaan_dialihkan_sedangkan_tim_lain_mendapat_404(): void
    {
        $tujuan = PermintaanBarangResource::urlRincian($this->permintaan);

        // Tim yang sama dengan pemilik permintaan.
        foreach (['ketua_tim', 'tim'] as $peran) {
            $pengguna = $this->lengkapiAkun($this->buatPengguna($peran, $this->permintaan->tim));
            $this->flushSession();
            auth()->forgetGuards();
            $this->actingAs($pengguna)->get('/admin/permintaan-barangs/' . $this->permintaan->getKey())
                ->assertStatus(302)->assertRedirect($tujuan);
        }

        // Tim lain: tidak dapat mengalihkan diri ke permintaan milik tim lain.
        foreach (['ketua_tim', 'tim'] as $urutan => $peran) {
            $this->buka($peran, 'Tim Lain ' . $urutan)->assertNotFound();
        }
    }

    public function test_tamu_dialihkan_ke_halaman_masuk_dan_tidak_pernah_500(): void
    {
        $this->flushSession();
        auth()->forgetGuards();

        $this->get('/admin/permintaan-barangs/' . $this->permintaan->getKey())
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertNotSame(500, $this->buka('kasubbag')->getStatusCode());
    }

    public function test_permintaan_yang_tidak_ada_mendapat_404(): void
    {
        $pengguna = $this->lengkapiAkun($this->buatPengguna('kasubbag'));

        $this->actingAs($pengguna)->get('/admin/permintaan-barangs/999999')->assertNotFound();
    }
}
