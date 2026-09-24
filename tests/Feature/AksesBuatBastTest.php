<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Models\BastMutasiAset;
use App\Models\Tim;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Perbaikan akses Buat BAST pasca-Fase 0-6 (pembalikan urutan alur Mutasi
 * Aset): pop-up CreateAction yang menggantikan halaman CreateBastMutasiAset
 * kehilangan pemeriksaan peran yang dulu ditegakkan otomatis oleh
 * CreateRecord::mount() (abort_unless(canCreate(), 403)). Ketua Tim dan akun
 * Tim benar-benar dapat membuat BAST — dibuktikan lewat mountAction() +
 * callMountedAction() mentah (bukan callAction(), yang punya pre-cek
 * visibilitasnya sendiri dan karena itu tidak membuktikan apa-apa soal
 * perilaku runtime sungguhan).
 *
 * Dua lapis diperbaiki: ->visible() pada CreateAction (UI, pola yang sama
 * dengan Sahkan/Konfirmasi) dan abort_unless(canCreate(), 403) di
 * handleCreation() (server, jaring pengaman utama, memulihkan mekanisme
 * CreateRecord::mount() yang hilang).
 */
class AksesBuatBastTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Tim $asal;

    private Tim $tujuan;

    private User $ketuaAsal;

    private User $ketuaTujuan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->asal   = $this->buatTim('Tim Asal Akses');
        $this->tujuan = $this->buatTim('Tim Tujuan Akses');
        $this->ketuaAsal = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $this->asal));
        $this->asal->forceFill(['ketua_tim_id' => $this->ketuaAsal->id])->save();
        $this->ketuaTujuan = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $this->tujuan));
        $this->tujuan->forceFill(['ketua_tim_id' => $this->ketuaTujuan->id])->save();
    }

    /** Memanggil pop-up Buat mentah, tanpa pre-cek visibilitas milik callAction(). */
    private function cobaBuatBast(User $pelaku, $aset): \Livewire\Features\SupportTesting\Testable
    {
        $this->actingAs($pelaku);

        return Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData([
                'aset_id'       => $aset->id,
                'tim_tujuan_id' => $this->tujuan->id,
                'alasan_mutasi' => 'Uji akses',
            ]);
    }

    // =====================================================================
    // SERVER: canCreate() DITEGAKKAN TERLEPAS DARI TAMPILAN TOMBOL
    // =====================================================================

    public function test_ketua_tim_tidak_dapat_membuat_bast_lewat_pop_up(): void
    {
        $aset = $this->buatAset($this->asal);

        $this->cobaBuatBast($this->ketuaAsal, $aset)->callMountedAction();

        $this->assertSame(0, BastMutasiAset::count(), 'Ketua Tim tidak boleh berhasil membuat BAST.');
    }

    public function test_akun_tim_tidak_dapat_membuat_bast_lewat_pop_up(): void
    {
        $aset = $this->buatAset($this->asal);
        $akunTim = $this->buatPengguna('tim', $this->asal);

        $this->cobaBuatBast($akunTim, $aset)->callMountedAction();

        $this->assertSame(0, BastMutasiAset::count(), 'Akun Tim tidak boleh berhasil membuat BAST.');
    }

    public function test_kasubbag_tidak_dapat_membuat_bast_lewat_pop_up(): void
    {
        $aset = $this->buatAset($this->asal);
        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));

        $this->cobaBuatBast($kasubbag, $aset)->callMountedAction();

        $this->assertSame(0, BastMutasiAset::count(), 'Kasubbag hanya Sahkan, bukan Buat.');
    }

    /**
     * Server tetap menolak walau field aset_id disuntik langsung (muatan
     * dimodifikasi) — bukti bahwa penjaga tidak bergantung pada apakah
     * tombolnya sempat tampil atau tidak bagi peran ini.
     */
    public function test_service_level_menolak_terlepas_dari_muatan_form(): void
    {
        $aset = $this->buatAset($this->asal);
        $this->actingAs($this->ketuaAsal);

        $lemparan = null;

        try {
            $ref = new \ReflectionMethod(ListBastMutasiAsets::class, 'handleCreation');
            $ref->setAccessible(true);
            $ref->invoke(null, [
                'aset_id'        => $aset->id,
                'tim_asal_id'    => $this->asal->id,
                'tim_tujuan_id'  => $this->tujuan->id,
                'alasan_mutasi'  => 'Uji langsung',
                'pihak_penyerah' => 'Uji',
                'pihak_penerima' => 'Uji',
                'nomor_bast'     => 'BAST-UJI-AKSES-0001',
                'dibuat_oleh_id' => $this->ketuaAsal->id,
                'status'         => 'menunggu_konfirmasi',
            ]);
        } catch (\Throwable $e) {
            $lemparan = $e;
        }

        $this->assertInstanceOf(HttpException::class, $lemparan, 'handleCreation() harus melempar HttpException(403).');
        $this->assertSame(403, $lemparan->getStatusCode());
        $this->assertSame(0, BastMutasiAset::count());
    }

    // =====================================================================
    // KONTROL: PETUGAS GUDANG TIDAK BOLEH REGRESI
    // =====================================================================

    public function test_petugas_gudang_tetap_dapat_membuat_bast(): void
    {
        $aset = $this->buatAset($this->asal);
        $gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));

        $this->cobaBuatBast($gudang, $aset)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(1, BastMutasiAset::count(), 'Petugas Gudang harus tetap bisa membuat BAST (tidak boleh regresi).');
    }

    // =====================================================================
    // UI: TOMBOL DISEMBUNYIKAN DARI PERAN YANG TIDAK BERHAK
    // =====================================================================

    public function test_tombol_buat_bast_tersembunyi_bagi_ketua_tim_dan_tim_tapi_tampil_bagi_gudang(): void
    {
        $this->actingAs($this->ketuaAsal);
        Livewire::test(ListBastMutasiAsets::class)
            ->assertActionHidden('create');

        $this->actingAs($this->buatPengguna('tim', $this->asal));
        Livewire::test(ListBastMutasiAsets::class)
            ->assertActionHidden('create');

        $this->actingAs($this->lengkapiAkun($this->buatPengguna('petugas_gudang')));
        Livewire::test(ListBastMutasiAsets::class)
            ->assertActionVisible('create');
    }

    // =====================================================================
    // A2.4 — SAHKAN/KONFIRMASI: SUDAH TERLINDUNG, DIJAGA AGAR TETAP BEGITU
    // =====================================================================

    /**
     * Bukti investigasi (23-24 September 2026): Sahkan/Konfirmasi TIDAK
     * kena celah yang sama. Filament menegakkan ->visible() genuinely di
     * level mount (isDisabled() -> isHidden(), dicek InteractsWithActions::
     * mountAction() sebelum action dijalankan) — bukan sekadar kosmetik UI.
     * Dibuktikan lewat mountAction()+callMountedAction() mentah (bukan
     * callAction(), yang punya assertActionVisible() bawaan) untuk tiap
     * peran yang salah; status tidak boleh berubah sama sekali.
     */
    public function test_sahkan_tetap_ditolak_untuk_peran_selain_kasubbag(): void
    {
        foreach (['tim', 'ketua_tim', 'admin'] as $peran) {
            $bast = $this->buatBast($this->asal, $this->tujuan, $this->buatPengguna('petugas_gudang'), status: 'menunggu_pengesahan');
            $bast->aset->catatPenempatanAwal();

            $pelaku = match ($peran) {
                'ketua_tim' => $this->tujuan->ketuaTim,
                'tim'       => $this->buatPengguna('tim', $this->asal),
                'admin'     => $this->buatPengguna('admin'),
            };

            $this->actingAs($pelaku);
            try {
                // Admin ditolak lebih awal lagi, di canAccess() halaman itu
                // sendiri (bukan hanya visible() aksi Sahkan) — Livewire::test()
                // sendiri melempar untuk peran itu; keduanya sama-sama bukti
                // penolakan, jadi ditoleransi di sini.
                Livewire::test(ListBastMutasiAsets::class)
                    ->mountAction(TestAction::make('sahkan')->table($bast))
                    ->callMountedAction();
            } catch (\Throwable) {
                // Ditolak lebih awal (mis. canAccess() halaman) — tetap bukti penolakan.
            }

            $this->assertSame('menunggu_pengesahan', $bast->fresh()->status, "Sahkan oleh {$peran} seharusnya tidak berefek.");
        }
    }

    public function test_konfirmasi_tetap_ditolak_untuk_peran_selain_ketua_tim_tujuan(): void
    {
        $timLain = $this->buatTim('Tim Lain Akses');
        $ketuaLain = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $timLain));
        $timLain->forceFill(['ketua_tim_id' => $ketuaLain->id])->save();

        $kasus = [
            'tim_pemohon_sendiri' => fn () => $this->buatPengguna('tim', $this->tujuan),
            'kasubbag'            => fn () => $this->lengkapiAkun($this->buatPengguna('kasubbag')),
            'ketua_tim_lain'      => fn () => $ketuaLain,
            'admin'               => fn () => $this->buatPengguna('admin'),
        ];

        foreach ($kasus as $label => $pembuatPelaku) {
            $bast = $this->buatBast($this->asal, $this->tujuan, $this->buatPengguna('petugas_gudang'), status: 'menunggu_konfirmasi');
            $bast->aset->catatPenempatanAwal();

            $this->actingAs($pembuatPelaku());
            try {
                Livewire::test(ListBastMutasiAsets::class)
                    ->mountAction(TestAction::make('konfirmasi')->table($bast))
                    ->callMountedAction();
            } catch (\Throwable) {
                // Ditolak lebih awal (mis. canAccess() halaman) — tetap bukti penolakan.
            }

            $this->assertSame('menunggu_konfirmasi', $bast->fresh()->status, "Konfirmasi oleh {$label} seharusnya tidak berefek.");
        }
    }

    /** Kontrol: peran yang benar tetap berhasil (tidak boleh ikut regresi). */
    public function test_sahkan_dan_konfirmasi_tetap_berhasil_untuk_peran_yang_benar(): void
    {
        $bast = $this->buatBast($this->asal, $this->tujuan, $this->buatPengguna('petugas_gudang'), status: 'menunggu_konfirmasi');
        $bast->aset->catatPenempatanAwal();

        $this->actingAs($this->ketuaTujuan);
        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('konfirmasi')->table($bast));
        $this->assertSame('menunggu_pengesahan', $bast->fresh()->status);

        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));
        $this->actingAs($kasubbag);
        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('sahkan')->table($bast->fresh()));
        $this->assertSame('selesai_administratif', $bast->fresh()->status);
    }

    // =====================================================================
    // RESOURCE-LEVEL: canCreate()/canAccess() TIDAK BERUBAH
    // =====================================================================

    public function test_can_create_masih_membatasi_ke_petugas_gudang(): void
    {
        $this->actingAs($this->ketuaAsal);
        $this->assertFalse(BastMutasiAsetResource::canCreate());

        $this->actingAs($this->buatPengguna('tim', $this->asal));
        $this->assertFalse(BastMutasiAsetResource::canCreate());

        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $this->assertTrue(BastMutasiAsetResource::canCreate());
    }
}
