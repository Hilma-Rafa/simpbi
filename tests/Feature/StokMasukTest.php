<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Models\MutasiStok;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian pencatatan stok masuk (UC-07).
 *
 * Yang dijaga terutama adalah kewajiban mengisi Nomor Dasar. Kolom itu terbit
 * apa adanya pada kartu kendali sebagai "Nomor Dasar M/K", dan bila kosong,
 * transaksi penambahan stok tidak dapat ditelusuri ke bukti mana pun — persis
 * kelemahan kartu kendali manual yang hendak diperbaiki penelitian ini.
 */
class StokMasukTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function catat(array $data)
    {
        return Livewire::test(StokMasuk::class)->callAction('catat', $data);
    }

    public function test_hanya_petugas_gudang_yang_dapat_membuka_halaman(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $this->assertTrue(StokMasuk::canAccess());

        foreach (['admin', 'kasubbag', 'ketua_tim', 'tim'] as $peran) {
            $this->actingAs($this->buatPengguna($peran));
            $this->assertFalse(StokMasuk::canAccess(), "Peran {$peran} tidak berwenang mencatat stok masuk.");
        }
    }

    #[DataProvider('sumberBerdokumen')]
    public function test_nomor_dasar_wajib_untuk_sumber_yang_berdokumen(string $sumber): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 10);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 5,
            'sumber'      => $sumber,
            'nomor_dasar' => null,
        ])->assertHasActionErrors(['nomor_dasar']);

        $this->assertSame(10, $barang->refresh()->stok_fisik, 'Stok tidak boleh bertambah bila borangnya ditolak.');
        $this->assertSame(0, MutasiStok::count());
    }

    /** @return array<string, array{string}> */
    public static function sumberBerdokumen(): array
    {
        return [
            'pembelian'      => ['pembelian'],
            'transfer masuk' => ['transfer_masuk'],
        ];
    }

    #[DataProvider('sumberTanpaDokumen')]
    public function test_nomor_dasar_tidak_wajib_untuk_sumber_tanpa_dokumen(string $sumber): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 10);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 5,
            'sumber'      => $sumber,
            'nomor_dasar' => null,
        ])->assertHasNoActionErrors();

        $this->assertSame(15, $barang->refresh()->stok_fisik);
        $this->assertNull(MutasiStok::sole()->nomor_dasar);
    }

    /** @return array<string, array{string}> */
    public static function sumberTanpaDokumen(): array
    {
        return [
            'stok awal'    => ['stok_awal'],
            'pengembalian' => ['pengembalian'],
        ];
    }

    public function test_pembelian_dengan_nomor_dasar_tercatat_pada_buku_besar(): void
    {
        $petugas = $this->buatPengguna('petugas_gudang');
        $this->actingAs($petugas);
        $barang = $this->buatBarang(stokFisik: 5);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 20,
            'sumber'      => 'pembelian',
            'nomor_dasar' => '34/F/HI/VIII/2026',
            'keterangan'  => 'Pengadaan triwulan pertama',
        ])->assertHasNoActionErrors();

        $mutasi = MutasiStok::sole();
        $this->assertSame('masuk', $mutasi->jenis);
        $this->assertSame(20, $mutasi->jumlah);
        $this->assertSame(25, $mutasi->saldo_sesudah);
        $this->assertSame('pembelian', $mutasi->sumber);
        $this->assertSame('34/F/HI/VIII/2026', $mutasi->nomor_dasar);
        $this->assertSame($petugas->id, $mutasi->petugas_id);
        $this->assertSame(25, $barang->refresh()->stok_fisik);
    }

    public function test_nomor_dasar_yang_terlalu_panjang_ditolak(): void
    {
        // Batasnya mengikuti lebar kolom nomor_dasar pada tabel mutasi_stok
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang();

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 1,
            'sumber'      => 'pembelian',
            'nomor_dasar' => str_repeat('X', 61),
        ])->assertHasActionErrors(['nomor_dasar']);
    }
}
