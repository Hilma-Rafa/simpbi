<?php

namespace Tests\Feature;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Livewire\LoncengNotifikasi;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Perbaikan pasca-Batch 8: mengklik satu notifikasi pada lonceng menandainya
 * dibaca SEKALIGUS membuka tujuannya.
 *
 * Sebelum perbaikan, barisnya berupa <a href> polos yang juga memikul
 * wire:click: navigasi lewat href berjalan seketika di peramban sementara
 * permintaan Livewire yang menandai dibaca baru selesai belakangan, sehingga
 * penandaannya kerap tidak sempat tersimpan sebelum halaman berpindah. Uji
 * ini memanggil tandaiDibaca() langsung (mewakili wire:click.prevent yang
 * kini menunggu permintaan itu selesai sebelum peramban berpindah), sehingga
 * yang diperiksa persis apa yang seharusnya sudah tersimpan begitu Livewire
 * selesai memproses — bukan seberapa cepat browser berpindah halaman.
 */
class TandaiDibacaLoncengTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // tautan() pada komponen menanyakan kewenangan kepada resource, dan
        // pertanyaan itu hanya dapat dijawab di dalam sebuah panel Filament.
        Filament::setCurrentPanel('admin');
    }

    private function buatNotifikasi(User $penerima, string $judul, array $tambahan = []): Notifikasi
    {
        return Notifikasi::create([
            'user_id' => $penerima->id,
            'judul'   => $judul,
            'pesan'   => $judul . ' menunggu tindakan Anda.',
            'tipe'    => 'permintaan',
            'channel' => 'in_app',
            ...$tambahan,
        ]);
    }

    private function permintaan(): PermintaanBarang
    {
        $tim = $this->buatTim();

        return $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'menunggu_ketua',
        );
    }

    public function test_klik_menandai_dibaca_mengurangi_badge_dan_mengalihkan_ke_tujuan(): void
    {
        $kasubbag   = $this->buatPengguna('kasubbag');
        $permintaan = $this->permintaan();
        $notifikasi = $this->buatNotifikasi($kasubbag, $permintaan->kode_permintaan, [
            'referensi_tabel' => 'permintaan_barang',
            'referensi_id'    => $permintaan->id,
        ]);

        $lonceng = Livewire::actingAs($kasubbag)->test(LoncengNotifikasi::class);
        $this->assertSame(1, $lonceng->instance()->jumlahBelumDibaca);

        $lonceng->call('tandaiDibaca', $notifikasi->id)
            ->assertRedirect(PermintaanBarangResource::urlRincian($permintaan));

        $this->assertNotNull($notifikasi->fresh()->dibaca_at, 'Kolom dibaca_at harus terisi begitu Livewire selesai memproses.');
        $this->assertSame(0, $lonceng->instance()->jumlahBelumDibaca, 'Badge berkurang tanpa menunggu pemeriksaan berkala berikutnya.');
    }

    public function test_notifikasi_lain_milik_pengguna_yang_sama_tidak_ikut_tertandai(): void
    {
        $kasubbag = $this->buatPengguna('kasubbag');
        $a        = $this->buatNotifikasi($kasubbag, 'PB-2026-0001');
        $b        = $this->buatNotifikasi($kasubbag, 'PB-2026-0002');

        Livewire::actingAs($kasubbag)->test(LoncengNotifikasi::class)->call('tandaiDibaca', $a->id);

        $this->assertNotNull($a->fresh()->dibaca_at);
        $this->assertNull($b->fresh()->dibaca_at, 'Hanya notifikasi yang diklik yang boleh tertandai.');
    }

    public function test_tidak_dapat_menandai_dibaca_notifikasi_milik_pengguna_lain(): void
    {
        $kasubbag       = $this->buatPengguna('kasubbag');
        $orangLain      = $this->buatPengguna('tim', $this->buatTim('Statistik Produksi'));
        $milikOrangLain = $this->buatNotifikasi($orangLain, 'PB-2026-9999');

        Livewire::actingAs($kasubbag)
            ->test(LoncengNotifikasi::class)
            ->call('tandaiDibaca', $milikOrangLain->id);

        $this->assertNull($milikOrangLain->fresh()->dibaca_at, 'Id milik pengguna lain tidak boleh dapat ditebak untuk menandai notifikasinya.');
    }
}
