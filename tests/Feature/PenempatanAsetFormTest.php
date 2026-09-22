<?php

namespace Tests\Feature;

use App\Filament\Resources\AsetTetaps\Pages\CreateAsetTetap;
use App\Filament\Resources\AsetTetaps\Pages\EditAsetTetap;
use App\Models\AsetTetap;
use App\Models\Kategori;
use App\Models\RiwayatPenempatanAset;
use App\Services\MutasiAsetService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * F-002 (opsi B) dan F-003: form Aset Tetap.
 *
 * F-002: aset yang sudah ditempatkan hanya berpindah lewat Mutasi Aset
 * (BAST); pada form Ubah kolom penempatan terkunci dan tidak ikut disimpan.
 * Form Buat, dan aset yang belum pernah ditempatkan, tetap dapat memilihnya.
 * F-003: ID Eksternal dan Waktu Sinkronisasi diisi impor dan sinkronisasi,
 * bukan diketik: tampil tetapi tidak dapat diubah dan tidak ikut disimpan.
 */
class PenempatanAsetFormTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const KETERANGAN = 'Penempatan diubah melalui Mutasi Aset (BAST).';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.aset@bps.go.id']));
    }

    private function isianAset(?int $timId, array $tambahan = []): array
    {
        $kategori = Kategori::firstOrCreate(
            ['kode_kategori' => 'PM'],
            ['kode_akun' => '1.3.2', 'nama_kategori' => 'Peralatan dan Mesin', 'tipe' => 'aset_tetap'],
        );

        return [
            'nup'               => 'NUP-F-0001',
            'nama_aset'         => 'Laptop Uji',
            'kategori_id'       => $kategori->id,
            'tim_penempatan_id' => $timId,
            'kondisi'           => 'baik',
            'status_aktif'      => true,
            'sumber_data'       => 'manual',
            ...$tambahan,
        ];
    }

    private function sunting(AsetTetap $aset)
    {
        return Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()]);
    }

    // =====================================================================
    // F-002: PENEMPATAN
    // =====================================================================

    public function test_form_ubah_mengunci_penempatan_aset_yang_sudah_ditempatkan(): void
    {
        $aset = $this->buatAset($this->buatTim('Tim Satu'));

        $this->sunting($aset)
            ->assertFormFieldIsDisabled('tim_penempatan_id')
            ->assertSee(self::KETERANGAN);
    }

    public function test_form_ubah_tidak_mengubah_penempatan_walau_muatan_dimodifikasi_sementara_kolom_lain_berhasil(): void
    {
        $asal = $this->buatTim('Tim Satu');
        $lain = $this->buatTim('Tim Dua');
        $aset = $this->buatAset($asal);
        $aset->catatPenempatanAwal();

        $this->sunting($aset)
            ->fillForm(['tim_penempatan_id' => $lain->id, 'nama_aset' => 'Nama Aset Baru', 'kondisi' => 'rusak_ringan'])
            ->call('save')
            ->assertHasNoFormErrors();

        $aset->refresh();
        $this->assertSame($asal->id, $aset->tim_penempatan_id, 'Penempatan tidak boleh berpindah lewat form Ubah.');
        $this->assertSame('Nama Aset Baru', $aset->nama_aset);
        $this->assertSame('rusak_ringan', $aset->kondisi);
        $this->assertSame(1, $aset->riwayatPenempatan()->count(), 'Tidak ada baris riwayat baru.');
        $this->assertSame($asal->id, $aset->riwayatPenempatan()->sole()->tim_id);
    }

    /** Aset yang belum pernah ditempatkan tetap dapat diberi penempatan awal lewat Ubah (keputusan pemilik). */
    public function test_aset_yang_belum_ditempatkan_masih_dapat_diberi_penempatan_awal_lewat_ubah(): void
    {
        $tim  = $this->buatTim('Tim Satu');
        $lain = $this->buatTim('Tim Dua');
        $aset = $this->buatAset(null);

        $this->sunting($aset)
            ->assertFormFieldIsEnabled('tim_penempatan_id')
            ->fillForm(['tim_penempatan_id' => $tim->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $aset->refresh();
        $this->assertSame($tim->id, $aset->tim_penempatan_id);
        $this->assertSame('penempatan_awal', $aset->riwayatPenempatan()->sole()->jenis);

        // Sesudah ditempatkan, kolomnya terkunci.
        $this->sunting($aset)
            ->assertFormFieldIsDisabled('tim_penempatan_id')
            ->fillForm(['tim_penempatan_id' => $lain->id])
            ->call('save');

        $this->assertSame($tim->id, $aset->refresh()->tim_penempatan_id);
        $this->assertSame(1, $aset->riwayatPenempatan()->count());
    }

    public function test_form_buat_tetap_dapat_memilih_penempatan(): void
    {
        $tim = $this->buatTim('Tim Satu');

        Livewire::test(CreateAsetTetap::class)
            ->assertFormFieldIsEnabled('tim_penempatan_id')
            ->fillForm($this->isianAset($tim->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset = AsetTetap::sole();
        $this->assertSame($tim->id, $aset->tim_penempatan_id);
        $this->assertSame('penempatan_awal', $aset->riwayatPenempatan()->sole()->jenis);
    }

    public function test_mutasi_aset_bast_tetap_memindahkan_aset_dan_menulis_riwayat(): void
    {
        $asal    = $this->buatTim('Tim Satu');
        $tujuan  = $this->buatTim('Tim Dua');
        $gudang  = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));
        $bast = $this->buatBast($asal, $tujuan, $gudang);
        $aset = $bast->aset;
        $aset->catatPenempatanAwal();

        app(MutasiAsetService::class)->sahkan($bast, $kasubbag->id);

        $this->assertSame($tujuan->id, $aset->refresh()->tim_penempatan_id);
        $this->assertSame(2, $aset->riwayatPenempatan()->count());
        $this->assertSame('mutasi', $aset->riwayatPenempatan()->latest('id')->first()->jenis);
        $this->assertNotNull(RiwayatPenempatanAset::where('bast_id', $bast->id)->first());
    }

    // =====================================================================
    // F-003: SINKRONISASI
    // =====================================================================

    public function test_form_ubah_mengunci_kolom_sinkronisasi_dan_tidak_menyimpannya(): void
    {
        $lama = now()->subDays(3)->startOfSecond();
        $aset = $this->buatAset($this->buatTim(), ['external_id' => 'BMN-ASLI', 'synced_at' => $lama]);

        $this->sunting($aset)
            ->assertFormFieldIsDisabled('external_id')
            ->assertFormFieldIsDisabled('synced_at')
            ->fillForm(['external_id' => 'HACK-XYZ', 'synced_at' => '2030-01-01 00:00:00', 'nama_aset' => 'Nama Baru Sinkron'])
            ->call('save')
            ->assertHasNoFormErrors();

        $aset->refresh();
        $this->assertSame('BMN-ASLI', $aset->external_id);
        $this->assertTrue(\Illuminate\Support\Carbon::parse($aset->synced_at)->equalTo($lama));
        $this->assertSame('Nama Baru Sinkron', $aset->nama_aset);
    }

    public function test_form_buat_berhasil_dengan_kolom_sinkronisasi_kosong(): void
    {
        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset(null, ['external_id' => 'HACK-BUAT', 'synced_at' => '2030-01-01 00:00:00']))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset = AsetTetap::sole();
        $this->assertNull($aset->external_id, 'Muatan yang dimodifikasi tidak ikut disimpan.');
        $this->assertNull($aset->synced_at);
    }
}
