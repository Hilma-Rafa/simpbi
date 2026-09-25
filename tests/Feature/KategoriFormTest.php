<?php

namespace Tests\Feature;

use App\Filament\Resources\BarangPersediaans\Pages\CreateBarangPersediaan;
use App\Filament\Resources\Kategoris\Pages\CreateKategori;
use App\Filament\Resources\Kategoris\Pages\EditKategori;
use App\Filament\Resources\Kategoris\Schemas\KategoriForm;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Form Kategori Barang: kode akun (bagian A) dan keunikan kode kategori
 * (temuan sampingan A.6-1), serta pilihan kategori pada form Barang
 * Persediaan yang hanya memuat kategori persediaan (A.6-2).
 */
class KategoriFormTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.kategori@bps.go.id']));
    }

    private function isian(array $tambahan = []): array
    {
        return [
            'nama_kategori' => 'Alat Tulis', 'kode_kategori' => '1010301001',
            'kode_akun' => '117111', 'tipe' => 'persediaan', ...$tambahan,
        ];
    }

    private function kategori(array $tambahan = []): Kategori
    {
        return Kategori::create($this->isian($tambahan));
    }

    // ---------------------------------------------------------------- kode akun

    public function test_kode_akun_persediaan_enam_digit_diterima(): void
    {
        Livewire::test(CreateKategori::class)
            ->fillForm($this->isian())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('117111', Kategori::sole()->kode_akun);
    }

    public function test_kode_akun_persediaan_yang_bukan_enam_digit_ditolak(): void
    {
        foreach (['1171', '1171110', '1.1.7', 'ABCDEF'] as $kode) {
            Livewire::test(CreateKategori::class)
                ->fillForm($this->isian(['kode_akun' => $kode]))
                ->call('create')
                ->assertHasFormErrors(['kode_akun' => 'regex']);
        }

        $this->assertSame(0, Kategori::count());
    }

    /** Aset tetap bebas: data berjalannya memakai notasi bertitik (G-2, opsi A-1). */
    public function test_kode_akun_aset_tetap_bebas_bentuknya(): void
    {
        Livewire::test(CreateKategori::class)
            ->fillForm($this->isian(['kode_kategori' => 'PM', 'kode_akun' => '1.3.2', 'tipe' => 'aset_tetap']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('1.3.2', Kategori::sole()->kode_akun);
    }

    public function test_teks_bantuan_kode_akun_tampil(): void
    {
        Livewire::test(CreateKategori::class)
            ->assertSee('Bagan Akun Standar')
            ->assertSee('117111 untuk Barang Konsumsi');
    }

    // ------------------------------------------------------------ kode kategori

    public function test_kode_kategori_ganda_ditolak_dengan_pesan(): void
    {
        $this->kategori();

        Livewire::test(CreateKategori::class)
            ->fillForm($this->isian(['nama_kategori' => 'Kembaran', 'kode_kategori' => ' 1010301001 ']))
            ->call('create')
            ->assertHasFormErrors(['kode_kategori' => 'unique'])
            ->assertSee(KategoriForm::PESAN_KODE_DIPAKAI);

        $this->assertSame(1, Kategori::count());
    }

    public function test_ubah_tanpa_mengganti_kode_kategori_tidak_ditolak(): void
    {
        $kategori = $this->kategori();

        Livewire::test(EditKategori::class, ['record' => $kategori->getKey()])
            ->fillForm(['nama_kategori' => 'Alat Tulis Kantor'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Alat Tulis Kantor', $kategori->refresh()->nama_kategori);
    }

    public function test_ubah_menjadi_kode_kategori_lain_ditolak(): void
    {
        $this->kategori();
        $kedua = $this->kategori(['nama_kategori' => 'Kertas HVS', 'kode_kategori' => '1010302001']);

        Livewire::test(EditKategori::class, ['record' => $kedua->getKey()])
            ->fillForm(['kode_kategori' => '1010301001'])
            ->call('save')
            ->assertHasFormErrors(['kode_kategori' => 'unique']);

        $this->assertSame('1010302001', $kedua->refresh()->kode_kategori);
    }

    /** Jaring pengaman pada Buat: bentrok yang lolos validasi disimulasikan lewat kait `creating`. */
    public function test_buat_pelanggaran_unique_yang_lolos_validasi_menjadi_pesan(): void
    {
        Kategori::creating(function (Kategori $model): void {
            if (Kategori::where('kode_kategori', $model->kode_kategori)->exists()) {
                return;
            }

            Kategori::withoutEvents(fn () => Kategori::create([...$model->getAttributes(), 'nama_kategori' => 'Penyusup']));
        });

        Livewire::test(CreateKategori::class)
            ->fillForm($this->isian())
            ->call('create')
            ->assertHasNoFormErrors();

        Notification::assertNotified(Notification::make()->title(KategoriForm::PESAN_KODE_DIPAKAI)->danger());
        $this->assertSame('Penyusup', Kategori::sole()->nama_kategori);
    }

    /** Jaring pengaman pada Ubah, disimulasikan lewat kait `updating`. */
    public function test_ubah_pelanggaran_unique_yang_lolos_validasi_menjadi_pesan(): void
    {
        $kategori = $this->kategori();

        Kategori::updating(function (Kategori $model): void {
            if ($model->kode_kategori !== '1010399999' || Kategori::where('kode_kategori', '1010399999')->exists()) {
                return;
            }

            Kategori::withoutEvents(fn () => Kategori::create($this->isian(['nama_kategori' => 'Penyusup', 'kode_kategori' => '1010399999'])));
        });

        Livewire::test(EditKategori::class, ['record' => $kategori->getKey()])
            ->fillForm(['kode_kategori' => '1010399999'])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNotified(Notification::make()->title(KategoriForm::PESAN_KODE_DIPAKAI)->danger());
        $this->assertSame('1010301001', $kategori->refresh()->kode_kategori);
    }

    // --------------------------------------------- pilihan kategori pada barang

    public function test_form_barang_hanya_menerima_kategori_persediaan(): void
    {
        $persediaan = $this->kategori();
        $aset = $this->kategori(['nama_kategori' => 'Peralatan dan Mesin', 'kode_kategori' => 'PM', 'kode_akun' => '1.3.2', 'tipe' => 'aset_tetap']);

        $isianBarang = fn (Kategori $k): array => [
            'kategori_id' => $k->id, 'kode_barang' => '000122', 'nama_barang' => 'Binder Clips',
            'satuan' => 'Dus', 'stok_fisik' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ];

        Livewire::test(CreateBarangPersediaan::class)
            ->assertFormFieldExists('kategori_id', fn ($medan): bool => array_key_exists($persediaan->id, $medan->getOptions())
                && ! array_key_exists($aset->id, $medan->getOptions()))
            ->fillForm($isianBarang($aset))
            ->call('create')
            ->assertHasFormErrors(['kategori_id']);

        $this->assertSame(0, BarangPersediaan::count());
    }
}
