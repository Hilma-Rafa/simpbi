<?php

namespace Tests\Feature;

use App\Filament\Pages\KartuKendali;
use App\Filament\Pages\Riwayat;
use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Filament\Resources\Kategoris\Pages\ListKategoris;
use App\Filament\Support\GayaUnduh;
use App\Services\Impor\ImporKategori;
use App\Services\Impor\PembuatTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Support\Enums\IconPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Seluruh tombol pengunduhan memakai rupa yang sama dengan Unduh Bukti
 * (GayaUnduh), keputusan pemilik G-8. Yang diuji hanya rupanya; isi berkas
 * tidak disentuh.
 */
class GayaTombolUnduhTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function assertGayaUnduh(Action|ActionGroup $aksi): void
    {
        $this->assertSame(GayaUnduh::IKON, $aksi->getIcon());
        $this->assertSame(IconPosition::Before, $aksi->getIconPosition());
        $this->assertSame('primary', $aksi->getColor());
        $this->assertTrue($aksi->isOutlined());
        $this->assertSame(
            $aksi instanceof ActionGroup ? ActionGroup::BUTTON_VIEW : Action::BUTTON_VIEW,
            $aksi instanceof ActionGroup ? $aksi->getTriggerView() : $aksi->getView(),
        );
    }

    public function test_unduh_bukti_menjadi_acuan(): void
    {
        $this->assertGayaUnduh(PermintaanBarangResource::aksiUnduhBukti());
        $this->assertSame('Unduh Bukti', PermintaanBarangResource::aksiUnduhBukti()->getLabel());
    }

    /** AksiImpor dipakai kelima halaman impor, sehingga satu halaman cukup mewakili. */
    public function test_unduh_template_pada_dialog_impor(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.unduh@bps.go.id']));

        $aksi = Livewire::test(ListKategoris::class)->instance()->getTable()->getAction('impor');
        $unduh = collect($aksi->getExtraModalFooterActions())->first(fn (Action $a) => $a->getName() === 'unduhTemplate');

        $this->assertNotNull($unduh);
        $this->assertGayaUnduh($unduh);
        $this->assertSame('Unduh Template', $unduh->getLabel());
    }

    /** Template yang diunduh tetap berkas xlsx dengan tajuk yang sama. */
    public function test_unduh_template_tetap_mengunduh_berkas_yang_sama(): void
    {
        $respons = app(PembuatTemplate::class)->buat(ImporKategori::JUDUL, ImporKategori::kolom(), 'Template-Impor-Kategori-Barang.xlsx');

        $this->assertStringContainsString('Template-Impor-Kategori-Barang.xlsx', $respons->headers->get('content-disposition'));
        $this->assertSame("PK\x03\x04", substr(file_get_contents($respons->getFile()->getPathname()), 0, 4));
    }

    public function test_unduh_bast_ekspor_kartu_kendali_dan_ekspor_riwayat(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.unduh@bps.go.id']));

        $bast = Livewire::test(ListBastMutasiAsets::class)->instance();
        $this->assertGayaUnduh($bast->getTable()->getAction('unduh'));

        $kartu = Livewire::test(KartuKendali::class)->instance();
        $this->assertGayaUnduh($kartu->getTable()->getAction('ekspor'));

        $riwayat = Livewire::test(Riwayat::class)->instance();
        $grup = collect($riwayat->getCachedHeaderActions())->first(fn ($a) => $a instanceof ActionGroup);
        $this->assertNotNull($grup);
        $this->assertGayaUnduh($grup);
        $this->assertSame('Ekspor', $grup->getLabel());
    }

    public function test_unduh_panduan_disalin_dari_gaya_unduh(): void
    {
        $blade = file_get_contents(resource_path('views/filament/pages/pusat-bantuan.blade.php'));

        $this->assertStringContainsString('icon="' . GayaUnduh::IKON . '"', $blade);
        $this->assertStringContainsString('outlined', $blade);
        $this->assertStringContainsString('GayaUnduh', $blade);
    }
}
