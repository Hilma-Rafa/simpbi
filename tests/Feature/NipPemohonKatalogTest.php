<?php

namespace Tests\Feature;

use App\Filament\Pages\KatalogBarang;
use App\Models\PermintaanBarang;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * G-006: format NIP Pemohon pada pengajuan dari Katalog.
 *
 * Kolomnya tetap opsional seperti semula; bila diisi harus 18 angka (spasi
 * diabaikan saat memeriksa, dibuang saat disimpan). Nama Pemohon tetap bebas.
 */
class NipPemohonKatalogTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN = 'NIP pemohon harus terdiri atas angka dengan format yang benar.';

    private ?\App\Models\BarangPersediaan $barang = null;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function ajukan(?string $nip, string $nama = 'Pemohon Uji')
    {
        $this->barang ??= $this->buatBarang(stokFisik: 100);

        return Livewire::test(KatalogBarang::class)->callAction('ajukan', [
            'nama_pemohon' => $nama,
            'nip_pemohon'  => $nip,
            'items'        => [['barang_id' => $this->barang->id, 'jumlah' => 1]],
            'keperluan'    => 'Keperluan uji',
        ]);
    }

    protected function setUpAkun(): void
    {
        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));
    }

    public function test_nip_delapan_belas_angka_diterima_dan_tersimpan_apa_adanya(): void
    {
        $this->setUpAkun();

        $this->ajukan('198001012010011001')->assertHasNoActionErrors();

        $this->assertSame('198001012010011001', PermintaanBarang::sole()->nip_pemohon);
    }

    public function test_spasi_diabaikan_saat_memeriksa_dan_dibuang_saat_disimpan(): void
    {
        $this->setUpAkun();

        $this->ajukan('19800101 201001 1 001')->assertHasNoActionErrors();

        $this->assertSame('198001012010011001', PermintaanBarang::sole()->nip_pemohon);
    }

    public function test_huruf_terlalu_pendek_dan_terlalu_panjang_ditolak_di_server(): void
    {
        $this->setUpAkun();

        foreach (['19800101201001100A', 'ABCDEFGHIJKLMNOPQR', '19800101201001100', '1980010120100110011', 'NIP-198001012010011', '1980-01-01-2010-01-1'] as $salah) {
            $uji = $this->ajukan($salah);

            $uji->assertHasActionErrors(['nip_pemohon']);
            $this->assertStringContainsString(self::PESAN, $uji->errors()->first('mountedActions.0.data.nip_pemohon'), $salah);
        }

        $this->assertSame(0, PermintaanBarang::count(), 'Tidak ada permintaan terbentuk dari NIP yang salah.');
    }

    public function test_kosong_berperilaku_seperti_sebelumnya(): void
    {
        $this->setUpAkun();

        $this->ajukan(null)->assertHasNoActionErrors();
        $this->ajukan('')->assertHasNoActionErrors();

        $this->assertSame(2, PermintaanBarang::count());
        $this->assertNull(PermintaanBarang::orderBy('id')->first()->nip_pemohon);
        $this->assertNull(PermintaanBarang::orderByDesc('id')->first()->nip_pemohon);
    }

    public function test_nama_pemohon_tetap_bebas_diisi(): void
    {
        $this->setUpAkun();

        $this->ajukan('198001012010011001', 'Siapa Saja 123 / Bebas')->assertHasNoActionErrors();

        $this->assertSame('Siapa Saja 123 / Bebas', PermintaanBarang::sole()->nama_pemohon);
    }
}
