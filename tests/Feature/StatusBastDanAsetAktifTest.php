<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Models\BastMutasiAset;
use App\Models\Notifikasi;
use App\Models\RiwayatPenempatanAset;
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
 * F-006: status BAST dan status aktif aset dijaga di server.
 *
 * Pada alur yang dibalik, Konfirmasi Penerimaan hanya berlaku bagi BAST
 * berstatus menunggu_konfirmasi (langkah pertama), dan Sahkan hanya bagi
 * menunggu_pengesahan (langkah terakhir) — syarat *input* tiap aksi tidak
 * berubah dari sebelum urutan dibalik, hanya status *keluaran* dan efek
 * sampingnya yang tertukar. Diperiksa ulang di dalam transaksi dengan baris
 * BAST terkunci sebelum apa pun ditulis. Pembuatan BAST menolak aset yang
 * tidak aktif.
 */
class StatusBastDanAsetAktifTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN_SUDAH_DIPROSES = 'BAST ini sudah diproses atau statusnya telah berubah. Muat ulang halaman.';

    private const PESAN_NONAKTIF = 'Aset ini tidak aktif dan tidak dapat dimutasi.';

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

    private function bast(string $status = 'menunggu_pengesahan'): BastMutasiAset
    {
        $bast = $this->buatBast($this->asal, $this->tujuan, $this->gudang, $status);
        $bast->aset->catatPenempatanAwal();

        return $bast;
    }

    private function service(): MutasiAsetService
    {
        return app(MutasiAsetService::class);
    }

    /** Menegaskan bahwa pemanggilan ditolak dengan pesan itu. */
    private function harusDitolak(\Closure $panggil, string $pesan): void
    {
        try {
            $panggil();
            $this->fail('Seharusnya ditolak: ' . $pesan);
        } catch (\RuntimeException $e) {
            $this->assertSame($pesan, $e->getMessage());
        }
    }

    // =====================================================================
    // SAHKAN (input tetap menunggu_pengesahan; keluaran kini selesai_administratif)
    // =====================================================================

    public function test_sahkan_dua_kali_berturut_turut_yang_kedua_ditolak_tanpa_tulisan_tambahan(): void
    {
        $bast  = $this->bast();
        $basi  = BastMutasiAset::find($bast->id); // dimuat sebelum pengesahan pertama: statusnya di memori usang

        $this->service()->sahkan($bast, $this->kasubbag->id);

        $riwayat   = RiwayatPenempatanAset::count();
        $notifikasi = Notifikasi::count();
        $disahkanAt = $bast->fresh()->disahkan_at;

        $this->harusDitolak(fn () => $this->service()->sahkan($basi, $this->kasubbag->id), self::PESAN_SUDAH_DIPROSES);

        $this->assertSame($riwayat, RiwayatPenempatanAset::count(), 'Tidak ada baris riwayat tambahan.');
        $this->assertSame($notifikasi, Notifikasi::count(), 'Tidak ada notifikasi tambahan.');
        $this->assertSame('selesai_administratif', $bast->fresh()->status);
        $this->assertTrue($disahkanAt->equalTo($bast->fresh()->disahkan_at));
    }

    public function test_sahkan_pada_status_selain_menunggu_pengesahan_ditolak(): void
    {
        foreach (['menunggu_konfirmasi', 'selesai_administratif'] as $status) {
            $bast = $this->bast($status);
            $asetSebelum = $bast->aset->tim_penempatan_id;
            $riwayat = RiwayatPenempatanAset::count();

            $this->harusDitolak(fn () => $this->service()->sahkan($bast, $this->kasubbag->id), self::PESAN_SUDAH_DIPROSES);

            $this->assertSame($status, $bast->fresh()->status);
            $this->assertSame($asetSebelum, $bast->aset->fresh()->tim_penempatan_id);
            $this->assertSame($riwayat, RiwayatPenempatanAset::count());
        }
    }

    // =====================================================================
    // KONFIRMASI PENERIMAAN (input tetap menunggu_konfirmasi; keluaran kini menunggu_pengesahan)
    // =====================================================================

    public function test_konfirmasi_penerimaan_pada_status_selain_menunggu_konfirmasi_ditolak(): void
    {
        foreach (['menunggu_pengesahan', 'selesai_administratif'] as $status) {
            $bast = $this->bast($status);

            $this->harusDitolak(fn () => $this->service()->konfirmasi($bast, $this->ketuaTujuan->id), self::PESAN_SUDAH_DIPROSES);

            $this->assertSame($status, $bast->fresh()->status);
            $this->assertNull($bast->fresh()->dikonfirmasi_at);
        }
    }

    public function test_konfirmasi_dua_kali_yang_kedua_ditolak(): void
    {
        $bast = $this->bast('menunggu_konfirmasi');
        $basi = BastMutasiAset::find($bast->id);

        $this->service()->konfirmasi($bast, $this->ketuaTujuan->id);
        $sesudahPertama = $bast->fresh()->dikonfirmasi_at;

        $this->harusDitolak(fn () => $this->service()->konfirmasi($basi, $this->ketuaTujuan->id), self::PESAN_SUDAH_DIPROSES);

        $this->assertTrue($sesudahPertama->equalTo($bast->fresh()->dikonfirmasi_at));
        $this->assertSame('menunggu_pengesahan', $bast->fresh()->status);
    }

    public function test_aksi_konfirmasi_menampilkan_penolakan_sebagai_notifikasi_bukan_galat_mentah(): void
    {
        $bast = $this->bast('menunggu_konfirmasi');
        $notifikasi = Notifikasi::count();

        $this->mock(MutasiAsetService::class, function ($mock): void {
            $mock->shouldReceive('konfirmasi')->once()->andThrow(new \RuntimeException(self::PESAN_SUDAH_DIPROSES));
        });

        $this->actingAs($this->ketuaTujuan);
        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('konfirmasi')->table($bast));

        Notification::assertNotified(
            Notification::make()->title('Penerimaan tidak dapat dikonfirmasi')->body(self::PESAN_SUDAH_DIPROSES)->danger()
        );
        $this->assertSame($notifikasi, Notifikasi::count(), 'Tidak ada notifikasi tahapan yang terkirim.');
    }

    // =====================================================================
    // ALUR NORMAL TETAP BERHASIL (kini Konfirmasi dulu, baru Sahkan)
    // =====================================================================

    public function test_alur_normal_konfirmasi_lalu_sahkan_tetap_berhasil(): void
    {
        $bast = $this->bast('menunggu_konfirmasi');

        $this->actingAs($this->ketuaTujuan);
        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('konfirmasi')->table($bast));
        $this->assertSame('menunggu_pengesahan', $bast->fresh()->status);
        $this->assertSame($this->tujuan->id, $bast->aset->fresh()->tim_penempatan_id, 'Aset sudah berpindah pada langkah Konfirmasi.');

        $this->actingAs($this->kasubbag);
        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('sahkan')->table($bast->fresh()));

        $bast->refresh();
        $this->assertSame('selesai_administratif', $bast->status);
        $this->assertSame($this->tujuan->id, $bast->aset->fresh()->tim_penempatan_id, 'Sahkan tidak lagi mengubah penempatan.');
        $this->assertSame(2, $bast->aset->riwayatPenempatan()->count());
    }

    // =====================================================================
    // ASET NONAKTIF
    // =====================================================================

    public function test_pembuatan_bast_untuk_aset_nonaktif_ditolak_di_server(): void
    {
        $aset = $this->buatAset($this->asal, ['status_aktif' => false]);

        $this->harusDitolak(
            fn () => $this->service()->periksaPembuatan($aset->id, $this->asal->id, $this->tujuan->id),
            self::PESAN_NONAKTIF,
        );
    }

    /** Muatan yang dimodifikasi: aset nonaktif dikirim langsung ke pop-up Buat. */
    public function test_muatan_dimodifikasi_dengan_aset_nonaktif_tidak_membentuk_bast(): void
    {
        $aset = $this->buatAset($this->asal, ['status_aktif' => false]);
        $this->actingAs($this->gudang);

        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData(['aset_id' => $aset->id])
            ->setActionData([
                'tim_asal_id'   => $this->asal->id,
                'tim_tujuan_id' => $this->tujuan->id,
                'alasan_mutasi' => 'Redistribusi',
            ])
            ->callMountedAction();

        $this->assertSame(0, BastMutasiAset::count());
    }

    public function test_aset_aktif_tidak_terpengaruh(): void
    {
        $aset = $this->buatAset($this->asal);
        $this->actingAs($this->gudang);

        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData([
                'aset_id'       => $aset->id,
                'tim_asal_id'   => $this->asal->id,
                'tim_tujuan_id' => $this->tujuan->id,
                'alasan_mutasi' => 'Redistribusi',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(1, BastMutasiAset::count());
    }
}
