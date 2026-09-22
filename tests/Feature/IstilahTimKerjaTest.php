<?php

namespace Tests\Feature;

use App\Filament\Resources\AsetTetaps\Pages\ListAsetTetaps;
use App\Filament\Resources\Tims\Pages\ListTims;
use App\Filament\Resources\Tims\TimResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Keseragaman istilah "Tim Kerja" (temuan audit T-19).
 *
 * Delapan satuan organisasi di BPS Kota Jakarta Barat disebut **tim kerja**,
 * mengikuti struktur pasca reformasi birokrasi. Sistem sempat menyebutnya
 * dengan dua nama sekaligus — "Unit Penempatan" pada modul aset tetap dan
 * "unit pemohon" pada modul permintaan — sehingga pembaca dapat mengira
 * keduanya dua hal yang berbeda.
 *
 * Yang dijaga di sini bukan perilaku sistem melainkan kosakatanya, sebab
 * istilah yang bergeser pada dokumen keluaran resmi tidak menimbulkan galat
 * apa pun dan karena itu tidak akan pernah ketahuan oleh pengujian biasa.
 *
 * Kata "unit" sendiri tidak dilarang: ia tetap sah sebagai satuan ukur barang,
 * misalnya "45 unit". Yang dijaga hanyalah dua rangkaian kata yang sudah pasti
 * merujuk tim kerja.
 */
class IstilahTimKerjaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** Rangkaian kata yang tidak boleh lagi muncul untuk menyebut tim kerja. */
    private const ISTILAH_TERLARANG = ['Unit Penempatan', 'Unit Kerja', 'unit kerja', 'unit pemohon'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs($this->buatPengguna('admin'));
    }

    /**
     * Penjagaan pada tingkat berkas sumber.
     *
     * Label, keterangan, dan teks dokumen tersebar di puluhan berkas, dan
     * sebagiannya hanya muncul pada keadaan yang jarang terjadi — pesan
     * kesalahan, keadaan kosong, isian cadangan ketika relasinya kosong.
     * Memeriksa sumbernya menjangkau seluruhnya sekaligus, termasuk yang tidak
     * pernah dirender oleh uji mana pun.
     */
    public function test_istilah_unit_tidak_lagi_dipakai_untuk_menyebut_tim_kerja(): void
    {
        $berkas = Finder::create()
            ->files()
            ->in([app_path(), resource_path('views')])
            ->name(['*.php', '*.blade.php']);

        $temuan = [];

        /** @var SplFileInfo $b */
        foreach ($berkas as $b) {
            $isi = $b->getContents();

            foreach (self::ISTILAH_TERLARANG as $istilah) {
                if (str_contains($isi, $istilah)) {
                    $temuan[] = $istilah . ' → ' . $b->getRelativePathname();
                }
            }
        }

        $this->assertSame(
            [],
            $temuan,
            "Istilah berikut masih dipakai untuk menyebut tim kerja:\n" . implode("\n", $temuan),
        );
    }

    /**
     * Kata "unit" sebagai satuan ukur sengaja dibiarkan.
     *
     * Uji ini menahan penggantian menyeluruh yang tidak melihat konteks, yaitu
     * kekeliruan yang justru paling mungkin terjadi ketika istilah diseragamkan
     * dengan pencarian-dan-ganti.
     */
    public function test_kata_unit_sebagai_satuan_ukur_tetap_dipakai(): void
    {
        $this->assertStringContainsString(
            "' unit'",
            file_get_contents(app_path('Filament/Pages/StokMasuk.php')),
            'Ringkasan stok masuk menghitung barang dalam satuan unit, bukan menyebut tim kerja.',
        );
    }

    // =====================================================================
    // TAMPILAN
    // =====================================================================

    public function test_daftar_aset_tetap_memakai_label_tim_kerja(): void
    {
        $this->buatAset($this->buatTim('Statistik Sosial'));

        Livewire::test(ListAsetTetaps::class)
            ->assertSee('Tim Kerja')
            ->assertDontSee('Unit Penempatan');
    }

    public function test_daftar_tim_memakai_label_tim_kerja(): void
    {
        $this->buatTim('Statistik Sosial');

        $this->assertSame('Tim Kerja', TimResource::getNavigationLabel());
        $this->assertSame('Tim Kerja', TimResource::getModelLabel());
        $this->assertSame('Tim Kerja', TimResource::getPluralModelLabel());

        Livewire::test(ListTims::class)->assertSee('Tim Kerja');
    }
}
