<?php

namespace Tests\Feature;

use App\Filament\Resources\AsetTetaps\Pages\CreateAsetTetap;
use App\Filament\Resources\AsetTetaps\Pages\EditAsetTetap;
use App\Models\AsetTetap;
use App\Models\Kategori;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Perbaikan pasca-Batch 8: NUP yang sudah dipakai aset lain menampilkan pesan
 * validasi di form Aset Tetap, bukan galat basis data mentah (pola yang sama
 * dengan A-021 pada "Barang Baru" Stok Masuk). Kolom `nup` unik murni pada
 * dirinya sendiri (migration: `$table->string('nup', 30)->unique()`), sehingga
 * aturan formnya cukup `unique(ignoreRecord: true)`, tanpa penyaring kolom lain.
 */
class NupUnikAsetTetapTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.nup@bps.go.id']));
    }

    private function isianAset(string $nup, array $tambahan = []): array
    {
        $kategori = Kategori::firstOrCreate(
            ['kode_kategori' => 'PM'],
            ['kode_akun' => '1.3.2', 'nama_kategori' => 'Peralatan dan Mesin', 'tipe' => 'aset_tetap'],
        );

        return [
            'nup'               => $nup,
            'nama_aset'         => 'Aset Uji',
            'kategori_id'       => $kategori->id,
            // Batch F: tim kerja kini wajib pada form Buat, tidak terkait
            // keunikan NUP yang diuji di berkas ini — diisi sekadar supaya
            // submit tidak tertolak oleh aturan yang berbeda.
            'tim_penempatan_id' => $this->buatTim('Tim Uji NUP ' . $nup)->id,
            'kondisi'           => 'baik',
            'status_aktif'      => true,
            ...$tambahan,
        ];
    }

    // =====================================================================
    // FORM BUAT
    // =====================================================================

    public function test_nup_yang_sudah_dipakai_ditolak_di_form_tanpa_galat_500(): void
    {
        $ada = $this->buatAset(null, ['nup' => 'NUP-BENTROK-0001']);

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset('NUP-BENTROK-0001'))
            ->call('create')
            ->assertHasFormErrors(['nup' => 'unique']);

        // Tidak ada baris baru; hanya aset yang sudah ada sebelumnya.
        $this->assertSame(1, AsetTetap::where('nup', 'NUP-BENTROK-0001')->count());
        $this->assertSame($ada->id, AsetTetap::where('nup', 'NUP-BENTROK-0001')->sole()->id);
    }

    public function test_nup_dengan_spasi_ujung_tetap_terdeteksi_bentrok(): void
    {
        $this->buatAset(null, ['nup' => 'NUP-SPASI-0001']);

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset('  NUP-SPASI-0001  '))
            ->call('create')
            ->assertHasFormErrors(['nup' => 'unique']);

        $this->assertSame(1, AsetTetap::where('nup', 'NUP-SPASI-0001')->count());
    }

    public function test_nup_baru_tetap_berhasil_dibuat(): void
    {
        $this->buatAset(null, ['nup' => 'NUP-LAMA-0001']);

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset('NUP-BARU-0001'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, AsetTetap::count());
        $this->assertNotNull(AsetTetap::where('nup', 'NUP-BARU-0001')->first());
    }

    /**
     * Jaring pengaman di server: pelanggaran UNIQUE yang lolos aturan form
     * (dua penyimpanan bersamaan persis) menjadi notifikasi, bukan galat basis
     * data mentah. Bentroknya disimulasikan lewat kait `creating`, tepat
     * sebelum baris ini tersimpan — pola yang sama dengan uji A-021.
     */
    public function test_pelanggaran_unique_yang_lolos_validasi_form_menjadi_pesan_bukan_500(): void
    {
        AsetTetap::creating(function (AsetTetap $model): void {
            if ($model->nup !== 'NUP-RACE-0001' || AsetTetap::where('nup', 'NUP-RACE-0001')->exists()) {
                return;
            }

            AsetTetap::withoutEvents(fn () => AsetTetap::create([
                ...$model->getAttributes(),
                'nama_aset' => 'Penyusup',
            ]));
        });

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset('NUP-RACE-0001'))
            ->call('create')
            ->assertHasNoFormErrors();

        Notification::assertNotified(
            Notification::make()->title('NUP sudah dipakai oleh aset lain.')->danger()
        );
        $this->assertSame(1, AsetTetap::where('nup', 'NUP-RACE-0001')->count());
        $this->assertSame('Penyusup', AsetTetap::where('nup', 'NUP-RACE-0001')->sole()->nama_aset);
    }

    // =====================================================================
    // FORM UBAH
    // =====================================================================

    public function test_form_ubah_tidak_menganggap_aset_bentrok_dengan_nup_miliknya_sendiri(): void
    {
        $aset = $this->buatAset(null, ['nup' => 'NUP-SENDIRI-0001']);

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['nup' => 'NUP-SENDIRI-0001', 'nama_aset' => 'Nama Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nama Baru', $aset->refresh()->nama_aset);
    }

    public function test_form_ubah_menolak_nup_milik_aset_lain(): void
    {
        $a = $this->buatAset(null, ['nup' => 'NUP-A-0001']);
        $b = $this->buatAset(null, ['nup' => 'NUP-B-0001']);

        Livewire::test(EditAsetTetap::class, ['record' => $b->getKey()])
            ->fillForm(['nup' => 'NUP-A-0001'])
            ->call('save')
            ->assertHasFormErrors(['nup' => 'unique']);

        $this->assertSame('NUP-B-0001', $b->refresh()->nup, 'NUP aset B tidak boleh berubah.');
    }
}
