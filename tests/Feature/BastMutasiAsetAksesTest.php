<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Akses Tim pada halaman Mutasi Aset.
 *
 * Tim baru diberi akses lihat pada penataan Inventaris ini; pembatasan
 * datanya mengikuti pola yang sudah berlaku bagi Ketua Tim (Instruksi §54),
 * yaitu hanya mutasi yang melibatkan tim penggunanya sendiri.
 */
class BastMutasiAsetAksesTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_tim_dapat_membuka_daftar_mutasi_aset(): void
    {
        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));

        $this->assertTrue(BastMutasiAsetResource::canAccess());
    }

    public function test_tim_hanya_melihat_mutasi_yang_melibatkan_timnya(): void
    {
        $timSendiri = $this->buatTim('Statistik Sosial');
        $timLain    = $this->buatTim('Statistik Distribusi');
        $ketiga     = $this->buatTim('Sub Bagian Umum');
        $petugas    = $this->buatPengguna('petugas_gudang');

        $sebagaiAsal    = $this->buatBast($timSendiri, $timLain, $petugas);
        $sebagaiTujuan  = $this->buatBast($timLain, $timSendiri, $petugas);
        $tidakTerlibat  = $this->buatBast($timLain, $ketiga, $petugas);

        $this->actingAs($this->buatPengguna('tim', $timSendiri));

        Livewire::test(ListBastMutasiAsets::class)
            ->assertCanSeeTableRecords([$sebagaiAsal, $sebagaiTujuan])
            ->assertCanNotSeeTableRecords([$tidakTerlibat]);
    }

    public function test_tim_tidak_dapat_membuat_bast_baru(): void
    {
        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));

        $this->assertFalse(BastMutasiAsetResource::canCreate());
    }
}
