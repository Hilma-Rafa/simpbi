<?php

namespace Tests\Feature;

use App\Filament\Pages\AsetTetapTimSaya;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Halaman "Aset Tetap Tim Saya" (bacaan saja bagi Ketua Tim dan Tim).
 *
 * Menjawab "aset tetap apa saja yang saat ini berada pada tim saya?" tanpa
 * membuka seluruh data induk aset tetap seperti yang dilihat Admin/Kasubbag.
 */
class AsetTetapTimSayaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** @return array<string,array{0:string}> */
    public static function peranBerhak(): array
    {
        return [
            'ketua_tim' => ['ketua_tim'],
            'tim'       => ['tim'],
        ];
    }

    #[DataProvider('peranBerhak')]
    public function test_ketua_tim_dan_tim_dapat_membuka_halaman(string $peran): void
    {
        $tim = $this->buatTim();

        $this->actingAs($this->buatPengguna($peran, $tim));

        $this->assertTrue(AsetTetapTimSaya::canAccess());
    }

    /** @return array<string,array{0:string}> */
    public static function peranTakBerhak(): array
    {
        return [
            'admin'          => ['admin'],
            'kasubbag'       => ['kasubbag'],
            'petugas_gudang' => ['petugas_gudang'],
        ];
    }

    #[DataProvider('peranTakBerhak')]
    public function test_peran_pengelola_tidak_dapat_membuka_halaman(string $peran): void
    {
        $this->actingAs($this->buatPengguna($peran));

        $this->assertFalse(AsetTetapTimSaya::canAccess());
    }

    public function test_hanya_menampilkan_aset_aktif_milik_tim_pengguna(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');

        $milikTim     = $this->buatAset($tim);
        $milikLain    = $this->buatAset($lain);
        $nonaktif     = $this->buatAset($tim, ['status_aktif' => false]);
        $belumDitaruh = $this->buatAset();

        $this->actingAs($this->buatPengguna('tim', $tim));

        Livewire::test(AsetTetapTimSaya::class)
            ->assertCanSeeTableRecords([$milikTim])
            ->assertCanNotSeeTableRecords([$milikLain, $nonaktif, $belumDitaruh]);
    }

    public function test_data_mengikuti_tim_pengguna_yang_sedang_login(): void
    {
        $timA = $this->buatTim('Statistik Sosial');
        $timB = $this->buatTim('Statistik Distribusi');

        $asetA = $this->buatAset($timA);
        $asetB = $this->buatAset($timB);

        $this->actingAs($this->buatPengguna('ketua_tim', $timA));
        Livewire::test(AsetTetapTimSaya::class)
            ->assertCanSeeTableRecords([$asetA])
            ->assertCanNotSeeTableRecords([$asetB]);

        $this->actingAs($this->buatPengguna('ketua_tim', $timB));
        Livewire::test(AsetTetapTimSaya::class)
            ->assertCanSeeTableRecords([$asetB])
            ->assertCanNotSeeTableRecords([$asetA]);
    }

    public function test_tidak_ada_aksi_pengelolaan_data_induk(): void
    {
        $tim  = $this->buatTim();
        $aset = $this->buatAset($tim);

        $this->actingAs($this->buatPengguna('tim', $tim));

        $test = Livewire::test(AsetTetapTimSaya::class);

        $test->assertActionVisible(TestAction::make('riwayatPenempatan')->table($aset));
        $test->assertActionDoesNotExist('ubah');
        $test->assertActionDoesNotExist('hapus');
    }

    public function test_aksi_riwayat_penempatan_tetap_tersedia(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $aset = $this->buatAset($tim);
        $aset->catatPenempatanAwal();

        $this->actingAs($this->buatPengguna('ketua_tim', $tim));

        Livewire::test(AsetTetapTimSaya::class)
            ->assertActionVisible(TestAction::make('riwayatPenempatan')->table($aset));
    }

    public function test_keadaan_kosong_menjelaskan_tim_belum_memiliki_aset(): void
    {
        $tim = $this->buatTim();

        $this->actingAs($this->buatPengguna('tim', $tim));

        Livewire::test(AsetTetapTimSaya::class)
            ->assertSee('Belum ada aset yang ditempatkan');
    }
}
