<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Filament\Resources\BarangPersediaans\Pages\CreateBarangPersediaan;
use App\Filament\Resources\BarangPersediaans\Pages\EditBarangPersediaan;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Support\KodeBarang;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Kode barang tepat enam digit angka di setiap titik masuk antarmuka.
 *
 * Ketetapan pemilik: kode barang berasal dari kartu kendali persediaan dan
 * selalu enam digit, termasuk nol di depan. Aturannya berdampingan dengan
 * keunikan per kategori (T-2), bukan menggantikannya.
 */
class KodeBarangEnamDigitTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Kategori $atk;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->atk = Kategori::create(['kode_kategori' => '1010301001', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
    }

    private function sebagaiAdmin(): void
    {
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.enam@bps.go.id']));
    }

    private function isian(string $kode): array
    {
        return [
            'kategori_id' => $this->atk->id, 'kode_barang' => $kode, 'nama_barang' => 'Barang Baru',
            'satuan' => 'Rim', 'stok_fisik' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ];
    }

    /** @return list<array{0:string}> */
    public static function kodeTidakSah(): array
    {
        return [
            'lima digit'      => ['12345'],
            'tujuh digit'     => ['1234567'],
            'berhuruf'        => ['A-1000'],
            'titik di tengah' => ['000.12'],
        ];
    }

    #[DataProvider('kodeTidakSah')]
    public function test_form_buat_menolak_kode_yang_bukan_enam_digit(string $kode): void
    {
        $this->sebagaiAdmin();

        Livewire::test(CreateBarangPersediaan::class)
            ->fillForm($this->isian($kode))
            ->call('create')
            ->assertHasFormErrors(['kode_barang' => 'regex']);

        $this->assertSame(0, BarangPersediaan::count());
    }

    public function test_form_buat_menyimpan_kode_dengan_nol_di_depan_sebagai_teks(): void
    {
        $this->sebagaiAdmin();

        Livewire::test(CreateBarangPersediaan::class)
            ->fillForm($this->isian('000122'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('000122', BarangPersediaan::sole()->kode_barang);
    }

    /**
     * Bentuk diperiksa lebih dulu daripada keunikan: kode tak baku yang
     * kebetulan sama dengan kode lama dijawab "harus 6 digit", bukan
     * "sudah dipakai".
     */
    public function test_form_memeriksa_bentuk_sebelum_keunikan(): void
    {
        $this->sebagaiAdmin();
        BarangPersediaan::create([...$this->isian('12345'), 'stok_hold' => 0]);

        $uji = Livewire::test(CreateBarangPersediaan::class)
            ->fillForm($this->isian('12345'))
            ->call('create')
            ->assertHasFormErrors(['kode_barang' => 'regex']);

        $pesan = implode(' ', $uji->errors()->all());
        $this->assertStringContainsString(KodeBarang::PESAN, $pesan);
        $this->assertStringNotContainsString('sudah dipakai', $pesan);
    }

    public function test_form_ubah_menolak_kode_yang_bukan_enam_digit(): void
    {
        $this->sebagaiAdmin();
        $barang = BarangPersediaan::create([...$this->isian('000122'), 'stok_hold' => 0]);

        Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getRouteKey()])
            ->fillForm(['kode_barang' => '122'])
            ->call('save')
            ->assertHasFormErrors(['kode_barang' => 'regex']);

        $this->assertSame('000122', $barang->refresh()->kode_barang);
    }

    /** Sama seperti JalurBasisModul1Test::bukaDialogBarangBaru(). */
    private function bukaDialogBarangBaru()
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $uji = Livewire::test(StokMasuk::class)->mountAction('catat');
        $halaman = $uji->instance();

        $pengulang = $halaman->getSchema($halaman->getMountedActionSchemaName())
            ->getComponent(fn ($komponen) => $komponen instanceof Repeater);
        $pilihBarang = collect($pengulang->getItems())->first()
            ->getComponent(fn ($komponen) => $komponen instanceof Select && $komponen->getName() === 'barang_id');

        $skema = $halaman->getMountedActionSchemaName();
        $kunci = substr($pilihBarang->getKey(), strlen($skema) + 1);

        return $uji->mountAction(['catat', TestAction::make('createOption')->schemaComponent($kunci, $skema)]);
    }

    public function test_dialog_barang_baru_menolak_kode_yang_bukan_enam_digit(): void
    {
        $this->bukaDialogBarangBaru()
            ->setActionData([
                'kategori_id' => $this->atk->id, 'kode_barang' => '12a456', 'nama_barang' => 'Map Plastik',
                'satuan' => 'Buah', 'stok_minimum' => 0,
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['kode_barang' => 'regex']);

        $this->assertSame(0, BarangPersediaan::count());
    }

    /**
     * Spasi ujung dipangkas sebelum divalidasi (trim(), bukan
     * dehydrateStateUsing()), sehingga " 000122 " diterima sebagai 000122.
     */
    public function test_dialog_barang_baru_memangkas_spasi_sebelum_memeriksa_enam_digit(): void
    {
        $this->bukaDialogBarangBaru()
            ->setActionData([
                'kategori_id' => $this->atk->id, 'kode_barang' => ' 000122 ', 'nama_barang' => 'Map Plastik',
                'satuan' => 'Buah', 'stok_minimum' => 0,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('000122', BarangPersediaan::sole()->kode_barang);
    }

    public function test_pesan_impor_membedakan_kode_yang_kehilangan_nol_di_depan(): void
    {
        $this->assertNull(KodeBarang::pesanGalatImpor('000122'));
        $this->assertStringContainsString('kehilangan nol di depan', KodeBarang::pesanGalatImpor('122'));
        $this->assertStringContainsString('000122', KodeBarang::pesanGalatImpor('122'));
        $this->assertStringContainsString(KodeBarang::PESAN, KodeBarang::pesanGalatImpor('A-122'));
        $this->assertStringContainsString(KodeBarang::PESAN, KodeBarang::pesanGalatImpor('1234567'));
    }
}
