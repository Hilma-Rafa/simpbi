<?php

namespace Tests\Feature;

use App\Filament\Resources\BarangPersediaans\Pages\CreateBarangPersediaan;
use App\Filament\Resources\BarangPersediaans\Pages\EditBarangPersediaan;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit A-030: "Stok Terkunci" dikelola mekanisme HOLD, sehingga pada form
 * Barang Persediaan ia hanya ditampilkan dan tidak ikut disimpan. Penyesuaian
 * stok fisik lewat form yang sama tetap berlaku (docs/penyesuaian-stok.md).
 */
class StokTerkunciBacaSajaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs($this->buatPengguna('kasubbag'));
    }

    private function isian(BarangPersediaan $barang, array $tambahan = []): array
    {
        return [
            'kategori_id'  => $barang->kategori_id,
            'kode_barang'  => $barang->kode_barang,
            'nama_barang'  => $barang->nama_barang,
            'satuan'       => $barang->satuan,
            'stok_fisik'   => $barang->stok_fisik,
            'stok_minimum' => $barang->stok_minimum,
            'status_aktif' => $barang->status_aktif,
            ...$tambahan,
        ];
    }

    public function test_ubah_tidak_mengubah_stok_terkunci_walau_muatan_dimodifikasi(): void
    {
        $barang = $this->buatBarang(stokFisik: 40, stokHold: 7);

        Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getKey()])
            ->fillForm($this->isian($barang, ['nama_barang' => 'Nama Baru']))
            // Muatan Livewire dimodifikasi langsung, melewati kolom yang dinonaktifkan.
            ->set('data.stok_hold', 99)
            ->call('save')
            ->assertHasNoFormErrors();

        $barang->refresh();
        $this->assertSame('Nama Baru', $barang->nama_barang);
        $this->assertSame(7, $barang->stok_hold);
        $this->assertSame(40, $barang->stok_fisik);
    }

    public function test_kolom_stok_terkunci_tetap_tampil_dan_tidak_dapat_diubah(): void
    {
        $barang = $this->buatBarang(stokFisik: 40, stokHold: 7);

        Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getKey()])
            ->assertFormFieldExists('stok_hold', fn ($kolom): bool => $kolom->isDisabled() && $kolom->getLabel() === 'Stok Terkunci')
            ->assertFormSet(['stok_hold' => 7]);
    }

    public function test_stok_fisik_tetap_dapat_diubah_dengan_konfirmasi(): void
    {
        $barang = $this->buatBarang(stokFisik: 40, stokHold: 7);

        Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getKey()])
            ->fillForm($this->isian($barang, ['stok_fisik' => 55]))
            ->call('save')
            ->assertActionMounted('konfirmasiStok')
            ->callMountedAction();

        $barang->refresh();
        $this->assertSame(55, $barang->stok_fisik);
        $this->assertSame(7, $barang->stok_hold);
    }

    public function test_barang_baru_berhasil_dibuat_dengan_stok_terkunci_nol(): void
    {
        $kategori = Kategori::create(['kode_kategori' => 'K-BARU', 'kode_akun' => '117111', 'nama_kategori' => 'Kategori Uji', 'tipe' => 'persediaan']);

        Livewire::test(CreateBarangPersediaan::class)
            ->fillForm([
                'kategori_id'  => $kategori->id,
                'kode_barang'  => 'BR-001',
                'nama_barang'  => 'Barang Baru',
                'satuan'       => 'Buah',
                'stok_fisik'   => 12,
                'stok_minimum' => 0,
                'status_aktif' => true,
            ])
            // Nilai yang dipaksakan lewat muatan pun tidak boleh tersimpan.
            ->set('data.stok_hold', 50)
            ->call('create')
            ->assertHasNoFormErrors();

        $barang = BarangPersediaan::where('kode_barang', 'BR-001')->sole();
        $this->assertSame(0, $barang->stok_hold);
        $this->assertSame(12, $barang->stok_fisik);
    }
}
