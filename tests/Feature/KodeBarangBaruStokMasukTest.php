<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit A-021: "Barang Baru" pada Stok Masuk (hak Petugas Gudang yang
 * dipertahankan) menolak kode ganda pada kategori yang sama, sesuai indeks
 * unik (kategori_id, kode_barang) pada basis data.
 */
class KodeBarangBaruStokMasukTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Kategori $kategori;

    private Kategori $lain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategori = Kategori::create(['kode_kategori' => 'K-1', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
        $this->lain = Kategori::create(['kode_kategori' => 'K-2', 'kode_akun' => '117112', 'nama_kategori' => 'Bahan Cetak', 'tipe' => 'persediaan']);

        BarangPersediaan::create([
            'kategori_id' => $this->kategori->id, 'kode_barang' => 'A-100', 'nama_barang' => 'Barang Lama',
            'satuan' => 'Buah', 'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ]);
    }

    private function isian(int $kategoriId, string $kode): array
    {
        return [
            'kategori_id'  => $kategoriId,
            'kode_barang'  => $kode,
            'nama_barang'  => 'Barang Baru',
            'satuan'       => 'Rim',
            'stok_minimum' => 0,
        ];
    }

    public function test_kode_yang_sama_pada_kategori_yang_sama_terdeteksi_termasuk_spasi_ujung(): void
    {
        $this->assertTrue(StokMasuk::kodeBarangSudahDipakai($this->kategori->id, 'A-100'));
        $this->assertTrue(StokMasuk::kodeBarangSudahDipakai($this->kategori->id, 'A-100  '));
        $this->assertTrue(StokMasuk::kodeBarangSudahDipakai($this->kategori->id, ' A-100'));
    }

    public function test_kode_yang_sama_pada_kategori_lain_atau_kode_baru_diterima(): void
    {
        $this->assertFalse(StokMasuk::kodeBarangSudahDipakai($this->lain->id, 'A-100'));
        $this->assertFalse(StokMasuk::kodeBarangSudahDipakai($this->kategori->id, 'A-101'));
    }

    public function test_barang_baru_berhasil_dibuat_berstok_nol_dengan_kode_terpangkas(): void
    {
        $id = StokMasuk::simpanBarangBaru($this->isian($this->lain->id, 'A-100 '));

        $barang = BarangPersediaan::findOrFail($id);
        $this->assertSame('A-100', $barang->kode_barang);
        $this->assertSame($this->lain->id, $barang->kategori_id);
        $this->assertSame(0, $barang->stok_fisik);
        $this->assertSame(0, $barang->stok_hold);
        $this->assertTrue((bool) $barang->status_aktif);
    }

    public function test_pelanggaran_unique_yang_lolos_validasi_menjadi_pesan_bukan_sql(): void
    {
        // Bentrok yang lolos aturan form: baris ber-kode sama muncul tepat sebelum penyimpanan.
        BarangPersediaan::creating(function (BarangPersediaan $model): void {
            if (BarangPersediaan::where('kode_barang', 'B-200')->exists()) {
                return;
            }

            BarangPersediaan::withoutEvents(fn () => BarangPersediaan::create([
                ...$model->getAttributes(),
                'nama_barang' => 'Penyusup',
            ]));
        });

        try {
            StokMasuk::simpanBarangBaru($this->isian($this->kategori->id, 'B-200'));
            $this->fail('Pelanggaran UNIQUE seharusnya menghentikan pembuatan.');
        } catch (Halt) {
            // Dialog tetap terbuka; pesan tampil sebagai notifikasi.
        }

        Notification::assertNotified(
            Notification::make()->title('Kode barang sudah dipakai pada kategori ini.')->danger()
        );
        $this->assertSame(1, BarangPersediaan::where('kode_barang', 'B-200')->count());
    }
}
