<?php

namespace Tests\Feature;

use App\Filament\Widgets\KondisiAsetTetapTim;
use App\Filament\Widgets\PolaPermintaan;
use App\Filament\Widgets\StatusPermintaanTim;
use App\Filament\Widgets\TrenKonsumsiTim;
use App\Services\StokService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Dua panel monitoring tambahan pada dasbor Ketua Tim dan Tim, beserta
 * penyesuaian judul dan urutan panel yang sudah ada.
 *
 * Dasbor kedua peran ini sebelumnya hanya memuat "Perlu Tindakan Anda" dan
 * sepasang bagan Pola Permintaan/Status Permintaan Tim Saya; keduanya kini
 * dilengkapi Kondisi Aset Tetap Tim Saya dan Tren Konsumsi Tim Saya tanpa
 * menyentuh dasbor peran pengelola.
 */
class DashboardTimWidgetsTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    // ---------------------------------------------------------------
    // KONDISI ASET TETAP TIM SAYA
    // ---------------------------------------------------------------

    public function test_kondisi_aset_tetap_tim_saya_hanya_untuk_ketua_tim_dan_tim(): void
    {
        $tim = $this->buatTim();

        foreach (['ketua_tim' => true, 'tim' => true, 'admin' => false, 'kasubbag' => false, 'petugas_gudang' => false] as $peran => $boleh) {
            $this->actingAs($this->buatPengguna($peran, in_array($peran, ['tim', 'ketua_tim'], true) ? $tim : null));

            $this->assertSame($boleh, KondisiAsetTetapTim::canView(), "Peran {$peran} seharusnya " . ($boleh ? 'melihat' : 'tidak melihat') . ' panel ini.');
        }
    }

    public function test_kondisi_aset_tetap_tim_saya_hanya_mencacah_aset_tim_pengguna(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');

        $this->buatAset($tim, ['kondisi' => 'baik']);
        $this->buatAset($tim, ['kondisi' => 'rusak_ringan']);
        $this->buatAset($lain, ['kondisi' => 'rusak_berat']);
        $this->buatAset($tim, ['kondisi' => 'baik', 'status_aktif' => false]);

        $this->actingAs($this->buatPengguna('ketua_tim', $tim));

        $deskripsi = Livewire::test(KondisiAsetTetapTim::class)->instance()->getDescription();

        // Dua aset aktif milik tim sendiri; aset tim lain dan aset nonaktif
        // milik tim sendiri tidak ikut tercacah.
        $this->assertSame('Total 2 aset tetap aktif', $deskripsi);
    }

    // ---------------------------------------------------------------
    // TREN KONSUMSI TIM SAYA
    // ---------------------------------------------------------------

    public function test_tren_konsumsi_tim_saya_hanya_untuk_ketua_tim_dan_tim(): void
    {
        $tim = $this->buatTim();

        foreach (['ketua_tim' => true, 'tim' => true, 'admin' => false, 'kasubbag' => false, 'petugas_gudang' => false] as $peran => $boleh) {
            $this->actingAs($this->buatPengguna($peran, in_array($peran, ['tim', 'ketua_tim'], true) ? $tim : null));

            $this->assertSame($boleh, TrenKonsumsiTim::canView(), "Peran {$peran} seharusnya " . ($boleh ? 'melihat' : 'tidak melihat') . ' panel ini.');
        }
    }

    public function test_tren_konsumsi_tim_saya_hanya_menjumlahkan_barang_keluar_tim_pengguna(): void
    {
        $timSendiri = $this->buatTim('Statistik Sosial');
        $timLain    = $this->buatTim('Statistik Distribusi');
        $gudang     = $this->buatPengguna('petugas_gudang');
        $barang     = $this->buatBarang(stokFisik: 100);

        $punyaSendiri = $this->buatPermintaan(
            $timSendiri,
            $this->buatPengguna('tim', $timSendiri),
            [['barang' => $barang, 'diminta' => 7, 'final' => 7]],
        );
        app(StokService::class)->konversi($punyaSendiri, $gudang->id);

        $punyaLain = $this->buatPermintaan(
            $timLain,
            $this->buatPengguna('tim', $timLain),
            [['barang' => $barang, 'diminta' => 20, 'final' => 20]],
        );
        app(StokService::class)->konversi($punyaLain, $gudang->id);

        $this->actingAs($this->buatPengguna('ketua_tim', $timSendiri));

        $seri = Livewire::test(TrenKonsumsiTim::class)->instance()->seri;

        $this->assertSame(7, array_sum($seri['nilai']), 'Hanya barang keluar milik tim pengguna yang terhitung.');
    }

    // ---------------------------------------------------------------
    // JUDUL DAN URUTAN PANEL YANG SUDAH ADA
    // ---------------------------------------------------------------

    public function test_pola_permintaan_menyebut_tim_saya_bagi_ketua_tim_dan_tim(): void
    {
        $tim = $this->buatTim();
        $this->actingAs($this->buatPengguna('tim', $tim));

        $html = (string) Livewire::test(PolaPermintaan::class)->instance()->getHeading();

        $this->assertStringContainsString('Pola Permintaan Tim Saya', $html);
    }

    public function test_pola_permintaan_tetap_judul_lama_bagi_kasubbag(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag'));

        $html = (string) Livewire::test(PolaPermintaan::class)->instance()->getHeading();

        $this->assertStringContainsString('Pola Permintaan', $html);
        $this->assertStringNotContainsString('Tim Saya', $html);
    }

    /**
     * Status Permintaan Tim Saya dipindah ke baris kedua, berpasangan dengan
     * Tren Konsumsi Tim Saya, sehingga baris pertama tidak lagi menyisakan
     * ruang kosong di sebelah Kondisi Aset Tetap Tim Saya yang lebih pendek.
     */
    public function test_status_permintaan_tim_saya_berpindah_ke_baris_kedua(): void
    {
        $reflection = new \ReflectionClass(StatusPermintaanTim::class);
        $sort       = $reflection->getProperty('sort');
        $sort->setAccessible(true);

        $this->assertGreaterThan(
            (new \ReflectionClass(TrenKonsumsiTim::class))->getProperty('sort')->getDefaultValue(),
            $sort->getDefaultValue(),
        );
    }
}
