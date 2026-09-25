<?php

namespace Tests\Feature;

use App\Filament\Resources\BarangPersediaans\Pages\CreateBarangPersediaan;
use App\Filament\Resources\BarangPersediaans\Pages\EditBarangPersediaan;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * T-2: keunikan kode barang per kategori pada form Buat dan Ubah Barang
 * Persediaan.
 *
 * Sebelumnya form hanya mewajibkan kode terisi; kode ganda dalam satu
 * kategori baru ketahuan sebagai pelanggaran indeks unik
 * (kategori_id, kode_barang) saat menyimpan. Pesannya disamakan dengan dialog
 * Barang Baru di Stok Masuk.
 */
class KodeBarangUnikFormTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN = 'Kode barang sudah dipakai pada kategori ini.';

    private Kategori $atk;

    private Kategori $cetak;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.kode@bps.go.id']));

        $this->atk   = Kategori::create(['kode_kategori' => 'K-1', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
        $this->cetak = Kategori::create(['kode_kategori' => 'K-2', 'kode_akun' => '117112', 'nama_kategori' => 'Bahan Cetak', 'tipe' => 'persediaan']);
    }

    private function barang(Kategori $kategori, string $kode, string $nama = 'Barang Lama'): BarangPersediaan
    {
        return BarangPersediaan::create([
            'kategori_id' => $kategori->id, 'kode_barang' => $kode, 'nama_barang' => $nama, 'satuan' => 'Buah',
            'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ]);
    }

    private function isian(Kategori $kategori, string $kode, array $tambahan = []): array
    {
        return [
            'kategori_id' => $kategori->id, 'kode_barang' => $kode, 'nama_barang' => 'Barang Baru',
            'satuan' => 'Rim', 'stok_fisik' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
            ...$tambahan,
        ];
    }

    // =====================================================================
    // FORM BUAT
    // =====================================================================

    public function test_buat_kode_ganda_pada_kategori_yang_sama_ditolak(): void
    {
        $this->barang($this->atk, '000100');

        Livewire::test(CreateBarangPersediaan::class)
            ->fillForm($this->isian($this->atk, '000100'))
            ->call('create')
            ->assertHasFormErrors(['kode_barang' => 'unique'])
            ->assertSee(self::PESAN);

        $this->assertSame(1, BarangPersediaan::where('kode_barang', '000100')->count());
    }

    /** Spasi ujung dipangkas sebelum dibandingkan, sehingga hasilnya sama di SQLite dan MySQL. */
    public function test_buat_kode_ganda_berspasi_ujung_tetap_ditolak(): void
    {
        $this->barang($this->atk, '000100');

        Livewire::test(CreateBarangPersediaan::class)
            ->fillForm($this->isian($this->atk, ' 000100 '))
            ->call('create')
            ->assertHasFormErrors(['kode_barang' => 'unique']);

        $this->assertSame(1, BarangPersediaan::count());
    }

    public function test_buat_kode_yang_sama_pada_kategori_lain_diterima(): void
    {
        $this->barang($this->atk, '000100');

        Livewire::test(CreateBarangPersediaan::class)
            ->fillForm($this->isian($this->cetak, '000100'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, BarangPersediaan::where('kode_barang', '000100')->count());
        $this->assertTrue(BarangPersediaan::where('kategori_id', $this->cetak->id)->where('kode_barang', '000100')->exists());
    }

    /**
     * Jaring pengaman: bentrok yang lolos aturan formulir (dua penyimpanan
     * bersamaan) menjadi notifikasi, bukan galat SQL. Bentroknya disimulasikan
     * lewat kait `creating`, pola yang sama dengan uji NUP Aset Tetap.
     */
    public function test_buat_pelanggaran_unique_yang_lolos_validasi_menjadi_pesan(): void
    {
        BarangPersediaan::creating(function (BarangPersediaan $model): void {
            if ($model->kode_barang !== '000200' || BarangPersediaan::where('kode_barang', '000200')->exists()) {
                return;
            }

            BarangPersediaan::withoutEvents(fn () => BarangPersediaan::create([...$model->getAttributes(), 'nama_barang' => 'Penyusup']));
        });

        Livewire::test(CreateBarangPersediaan::class)
            ->fillForm($this->isian($this->atk, '000200'))
            ->call('create')
            ->assertHasNoFormErrors();

        Notification::assertNotified(Notification::make()->title(self::PESAN)->danger());
        $this->assertSame('Penyusup', BarangPersediaan::where('kode_barang', '000200')->sole()->nama_barang);
    }

    // =====================================================================
    // FORM UBAH
    // =====================================================================

    public function test_ubah_menjadi_kode_milik_barang_lain_sekategori_ditolak(): void
    {
        $this->barang($this->atk, '000100');
        $diubah = $this->barang($this->atk, '000101', 'Barang Kedua');

        Livewire::test(EditBarangPersediaan::class, ['record' => $diubah->getKey()])
            ->fillForm(['kode_barang' => '000100'])
            ->call('save')
            ->assertHasFormErrors(['kode_barang' => 'unique']);

        $this->assertSame('000101', $diubah->refresh()->kode_barang);
    }

    public function test_ubah_tanpa_mengganti_kode_tidak_ditolak(): void
    {
        $barang = $this->barang($this->atk, '000100');

        Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getKey()])
            ->fillForm(['nama_barang' => 'Nama Diperbarui'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nama Diperbarui', $barang->refresh()->nama_barang);
        $this->assertSame('000100', $barang->kode_barang);
    }

    public function test_ubah_ke_kode_yang_dipakai_kategori_lain_diterima(): void
    {
        $this->barang($this->cetak, '000100');
        $barang = $this->barang($this->atk, '000101');

        Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getKey()])
            ->fillForm(['kode_barang' => '000100'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('000100', $barang->refresh()->kode_barang);
    }

    /** Jaring pengaman pada Ubah, disimulasikan lewat kait `updating`. */
    public function test_ubah_pelanggaran_unique_yang_lolos_validasi_menjadi_pesan(): void
    {
        $barang = $this->barang($this->atk, '000101');

        BarangPersediaan::updating(function (BarangPersediaan $model): void {
            if ($model->kode_barang !== '000300' || BarangPersediaan::where('kode_barang', '000300')->exists()) {
                return;
            }

            $this->barang($this->atk, '000300', 'Penyusup');
        });

        Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getKey()])
            ->fillForm(['kode_barang' => '000300'])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNotified(Notification::make()->title(self::PESAN)->danger());
        $this->assertSame('000101', $barang->refresh()->kode_barang);
    }
}
