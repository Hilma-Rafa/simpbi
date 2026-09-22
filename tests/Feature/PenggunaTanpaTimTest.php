<?php

namespace Tests\Feature;

use App\Filament\Pages\AsetTetapTimSaya;
use App\Filament\Widgets\KondisiAsetTetapTim;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit A-018: pengguna Tim atau Ketua Tim tanpa tim (tim_id kosong) tidak
 * melihat aset apa pun pada "Aset Tetap Tim Saya" maupun panel kondisinya.
 *
 * Sebelumnya `where('tim_penempatan_id', null)` menjadi `IS NULL`, sehingga
 * yang tampil justru semua aset yang belum ditempatkan.
 */
class PenggunaTanpaTimTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** @return array<string,array{0:string}> */
    public static function peran(): array
    {
        return [
            'ketua_tim' => ['ketua_tim'],
            'tim'       => ['tim'],
        ];
    }

    #[DataProvider('peran')]
    public function test_halaman_kosong_bagi_pengguna_tanpa_tim(string $peran): void
    {
        $tim = $this->buatTim();

        $belumDitempatkan = $this->buatAset();
        $milikTim = $this->buatAset($tim);

        $this->actingAs($this->buatPengguna($peran));

        Livewire::test(AsetTetapTimSaya::class)
            ->assertSuccessful()
            ->assertCountTableRecords(0)
            ->assertCanNotSeeTableRecords([$belumDitempatkan, $milikTim])
            ->assertSee('Belum ada aset yang ditempatkan');
    }

    #[DataProvider('peran')]
    public function test_panel_kondisi_kosong_bagi_pengguna_tanpa_tim(string $peran): void
    {
        $tim = $this->buatTim();

        $this->buatAset(null, ['kondisi' => 'baik']);
        $this->buatAset($tim, ['kondisi' => 'baik']);

        $this->actingAs($this->buatPengguna($peran));

        $panel = Livewire::test(KondisiAsetTetapTim::class);

        $this->assertSame('Belum ada aset tetap', $panel->instance()->getDescription());
        $panel->assertSuccessful()->assertSee('Belum ada aset tetap');
    }

    #[DataProvider('peran')]
    public function test_pengguna_yang_punya_tim_tetap_melihat_aset_timnya(string $peran): void
    {
        $tim = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');

        $milikTim = $this->buatAset($tim, ['kondisi' => 'baik']);
        $this->buatAset($tim, ['kondisi' => 'rusak_ringan']);
        $milikLain = $this->buatAset($lain);
        $belumDitempatkan = $this->buatAset();

        $this->actingAs($this->buatPengguna($peran, $tim));

        Livewire::test(AsetTetapTimSaya::class)
            ->assertCountTableRecords(2)
            ->assertCanSeeTableRecords([$milikTim])
            ->assertCanNotSeeTableRecords([$milikLain, $belumDitempatkan]);

        $this->assertSame(
            'Total 2 aset tetap aktif',
            Livewire::test(KondisiAsetTetapTim::class)->instance()->getDescription(),
        );
    }
}
