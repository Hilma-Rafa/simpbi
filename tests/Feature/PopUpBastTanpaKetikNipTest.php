<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Models\BastMutasiAset;
use App\Models\Tim;
use App\Models\User;
use App\Services\MutasiAsetService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pop-up Buat/Sahkan/Konfirmasi BAST TANPA ketik NIP (menggantikan rencana
 * lama "dialog rincian + ketik NIP"): rincian wajib tampil di dalam modal,
 * tombol footer langsung menuntaskan aksi sekali klik, tanpa kolom isian
 * teks apa pun selain data pembuatan itu sendiri. Juga menguji penjaga
 * tanda tangan (E) dan pengisian otomatis pihak_penyerah/pihak_penerima (D, G.3).
 */
class PopUpBastTanpaKetikNipTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Tim $asal;

    private Tim $tujuan;

    private User $gudang;

    private User $kasubbag;

    private User $ketuaAsal;

    private User $ketuaTujuan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->asal   = $this->buatTim('Tim Asal');
        $this->tujuan = $this->buatTim('Tim Tujuan');
        $this->gudang   = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $this->kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));
        $this->ketuaAsal = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $this->asal));
        $this->asal->forceFill(['ketua_tim_id' => $this->ketuaAsal->id])->save();
        $this->ketuaTujuan = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $this->tujuan));
        $this->tujuan->forceFill(['ketua_tim_id' => $this->ketuaTujuan->id])->save();
    }

    // =====================================================================
    // DIALOG RINCIAN WAJIB TAMPIL, TANPA KETIK NIP
    // =====================================================================

    public function test_dialog_sahkan_menampilkan_rincian_bast_tanpa_kolom_ketik_nip(): void
    {
        $bast = $this->buatBast($this->asal, $this->tujuan, $this->gudang, status: 'menunggu_pengesahan');
        $this->actingAs($this->kasubbag);

        Livewire::test(ListBastMutasiAsets::class)
            ->mountTableAction('sahkan', $bast)
            ->assertActionMounted(TestAction::make('sahkan')->table($bast))
            ->assertMountedActionModalSee($bast->nomor_bast)
            ->assertMountedActionModalSee($bast->aset->nama_aset)
            ->assertMountedActionModalSee($bast->timAsal->nama_tim)
            ->assertMountedActionModalSee($bast->timTujuan->nama_tim)
            ->assertMountedActionModalSee($bast->alasan_mutasi)
            // Tidak ada lagi ketik NIP (keputusan baru menggantikan rencana B/C).
            ->assertMountedActionModalDontSee('Ketik NIP')
            ->assertMountedActionModalDontSee('Ketik nama tim');
    }

    public function test_dialog_konfirmasi_menampilkan_rincian_bast_tanpa_kolom_ketik_nip(): void
    {
        $bast = $this->buatBast($this->asal, $this->tujuan, $this->gudang, status: 'menunggu_konfirmasi');
        $this->actingAs($this->ketuaTujuan);

        Livewire::test(ListBastMutasiAsets::class)
            ->mountTableAction('konfirmasi', $bast)
            ->assertActionMounted(TestAction::make('konfirmasi')->table($bast))
            ->assertMountedActionModalSee($bast->nomor_bast)
            ->assertMountedActionModalSee($bast->alasan_mutasi)
            ->assertMountedActionModalDontSee('Ketik NIP')
            ->assertMountedActionModalDontSee('Ketik nama tim');
    }

    /** Sekali klik tuntas: tidak ada requiresConfirmation() kedua di atas dialog rincian. */
    public function test_sahkan_tuntas_dengan_satu_kali_callaction_tanpa_data_isian(): void
    {
        $bast = $this->buatBast($this->asal, $this->tujuan, $this->gudang, status: 'menunggu_pengesahan');
        $this->actingAs($this->kasubbag);

        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('sahkan')->table($bast));

        $this->assertSame('selesai_administratif', $bast->fresh()->status);
        Notification::assertNotified(Notification::make()->title('BAST disahkan')->success());
    }

    public function test_konfirmasi_tuntas_dengan_satu_kali_callaction_tanpa_data_isian(): void
    {
        $bast = $this->buatBast($this->asal, $this->tujuan, $this->gudang, status: 'menunggu_konfirmasi');
        $this->actingAs($this->ketuaTujuan);

        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('konfirmasi')->table($bast));

        $this->assertSame('menunggu_pengesahan', $bast->fresh()->status);
        Notification::assertNotified(Notification::make()->title('Penerimaan aset dikonfirmasi')->success());
    }

    // =====================================================================
    // PENJAGA TANDA TANGAN (E)
    // =====================================================================

    public function test_peringatan_tanda_tangan_tampil_saat_tim_tujuan_belum_bertanda_tangan_dan_hilang_setelah_lengkap(): void
    {
        $tujuanBelumTtd = $this->buatTim('Tim Tanpa TTD');
        $ketuaBelumTtd = $this->buatPengguna('ketua_tim', $tujuanBelumTtd);
        $tujuanBelumTtd->forceFill(['ketua_tim_id' => $ketuaBelumTtd->id])->save();
        // Sengaja tidak dipanggil lengkapiAkun(): Ketua Tim ini belum bertanda tangan.

        $aset = $this->buatAset($this->asal);
        $this->actingAs($this->gudang);

        $uji = Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData(['aset_id' => $aset->id, 'tim_tujuan_id' => $tujuanBelumTtd->id]);

        $uji->assertMountedActionModalSeeHtml('belum membubuhkan tanda tangan');
        $uji->assertMountedActionModalSeeHtml($tujuanBelumTtd->nama_tim);

        // Melengkapi tanda tangan Ketua Tim tujuan menghilangkan peringatan.
        $ketuaBelumTtd->forceFill(['tanda_tangan_path' => 'tanda-tangan/uji.png', 'tanda_tangan_at' => now()])->save();

        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData(['aset_id' => $aset->id, 'tim_tujuan_id' => $tujuanBelumTtd->id])
            ->assertMountedActionModalDontSeeHtml('belum membubuhkan tanda tangan');
    }

    public function test_server_menolak_pembuatan_bila_ketua_tim_tujuan_belum_bertanda_tangan(): void
    {
        $tujuanBelumTtd = $this->buatTim('Tim Tanpa TTD');
        $ketuaBelumTtd = $this->buatPengguna('ketua_tim', $tujuanBelumTtd);
        $tujuanBelumTtd->forceFill(['ketua_tim_id' => $ketuaBelumTtd->id])->save();

        $aset = $this->buatAset($this->asal);

        try {
            app(MutasiAsetService::class)->periksaPembuatan($aset->id, $this->asal->id, $tujuanBelumTtd->id);
            $this->fail('Seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($tujuanBelumTtd->nama_tim, $e->getMessage());
            $this->assertStringContainsString('belum membubuhkan tanda tangan', $e->getMessage());
        }

        $this->assertSame(0, BastMutasiAset::count());
    }

    public function test_server_mengizinkan_pembuatan_bila_kedua_ketua_tim_sudah_bertanda_tangan(): void
    {
        $aset = $this->buatAset($this->asal);

        // Tidak melempar apa pun: kedua Ketua Tim (asal, tujuan) sudah
        // bertanda tangan lewat setUp().
        app(MutasiAsetService::class)->periksaPembuatan($aset->id, $this->asal->id, $this->tujuan->id);
        $this->assertTrue(true);
    }

    // =====================================================================
    // PIHAK PENYERAH/PENERIMA TERISI OTOMATIS DARI KETUA TIM (D, G.3)
    // =====================================================================

    public function test_pihak_penyerah_dan_penerima_terisi_otomatis_dari_nama_ketua_tim(): void
    {
        $aset = $this->buatAset($this->asal);
        $this->actingAs($this->gudang);

        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData([
                'aset_id'       => $aset->id,
                'tim_tujuan_id' => $this->tujuan->id,
                'alasan_mutasi' => 'Uji pengisian otomatis',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $bast = BastMutasiAset::sole();
        $this->assertSame($this->ketuaAsal->name, $bast->pihak_penyerah);
        $this->assertSame($this->ketuaTujuan->name, $bast->pihak_penerima);
    }

    /** Field Pihak Penyerah/Penerima tidak lagi diminta di form (D). */
    public function test_form_buat_tidak_lagi_meminta_pihak_penyerah_dan_penerima(): void
    {
        $this->actingAs($this->gudang);

        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->assertMountedActionModalSeeHtml('Alasan Mutasi')
            ->assertMountedActionModalDontSeeHtml('Pihak Penyerah')
            ->assertMountedActionModalDontSeeHtml('Pihak Penerima');
    }
}
