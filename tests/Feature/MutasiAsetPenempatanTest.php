<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\Pages\CreateBastMutasiAset;
use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Models\BastMutasiAset;
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
 * Audit A-011: Tim Asal pada BAST mutasi selalu penempatan aset saat ini
 * (diperiksa di server), aset tanpa penempatan tidak dapat dimutasi, BAST baru
 * diblokir selama BAST sebelumnya menunggu pengesahan, dan Sahkan memeriksa
 * ulang penempatan aset secara atomik.
 */
class MutasiAsetPenempatanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN_TANPA_PENEMPATAN = 'Aset ini belum memiliki penempatan. Tetapkan penempatan terlebih dahulu lewat menu Aset Tetap.';

    private const PESAN_USANG = 'Penempatan aset telah berubah. Muat ulang formulir dan coba lagi.';

    private const PESAN_SAHKAN_USANG = 'Penempatan aset sudah berubah sejak BAST dibuat, sehingga BAST ini tidak dapat disahkan. Buat BAST baru bila mutasi masih diperlukan.';

    private Tim $tim1;

    private Tim $tim2;

    private Tim $tim3;

    private User $gudang;

    private User $kasubbag;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->tim1 = $this->buatTim('Tim Satu');
        $this->tim2 = $this->buatTim('Tim Dua');
        $this->tim3 = $this->buatTim('Tim Tiga');
        $this->gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $this->kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));
    }

    /** Mengisi form Buat BAST; `$timAsal` = null berarti tidak mengirim Tim Asal. */
    private function isiForm(int $asetId, ?int $timAsal, int $tujuan, string $alasan = 'Redistribusi'): \Livewire\Features\SupportTesting\Testable
    {
        $this->actingAs($this->gudang);

        return Livewire::test(CreateBastMutasiAset::class)->fillForm([
            'aset_id'        => $asetId,
            'tim_asal_id'    => $timAsal,
            'tim_tujuan_id'  => $tujuan,
            'alasan_mutasi'  => $alasan,
            'pihak_penyerah' => 'Penyerah',
            'pihak_penerima' => 'Penerima',
        ]);
    }

    private function sahkanLewatTabel(BastMutasiAset $bast): void
    {
        $this->actingAs($this->kasubbag);

        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('sahkan')->table($bast));
    }

    private function pesanBlokir(BastMutasiAset $bast): string
    {
        return "Aset ini masih memiliki BAST yang menunggu pengesahan ({$bast->nomor_bast}). Sahkan BAST tersebut terlebih dahulu.";
    }

    private function ditolak(Notification $notifikasi): void
    {
        Notification::assertNotified($notifikasi);
    }

    // =====================================================================
    // FORM: TIM ASAL
    // =====================================================================

    public function test_tim_asal_diturunkan_dari_penempatan_aset_dan_tidak_dapat_diubah(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->actingAs($this->gudang);

        Livewire::test(CreateBastMutasiAset::class)
            ->assertFormFieldIsDisabled('tim_asal_id')
            ->set('data.aset_id', $aset->id)
            ->assertSet('data.tim_asal_id', $this->tim1->id);
    }

    public function test_pembuatan_normal_menyimpan_asal_dari_penempatan_aset(): void
    {
        $aset = $this->buatAset($this->tim1);

        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id)->call('create')->assertHasNoFormErrors();

        $bast = BastMutasiAset::sole();
        $this->assertSame($this->tim1->id, $bast->tim_asal_id);
        $this->assertSame('menunggu_pengesahan', $bast->status);
    }

    public function test_muatan_dengan_tim_asal_berbeda_ditolak_dan_tidak_membentuk_bast(): void
    {
        $aset = $this->buatAset($this->tim1);

        $this->isiForm($aset->id, $this->tim3->id, $this->tim2->id)->call('create');

        $this->assertSame(0, BastMutasiAset::count());
        $this->ditolak(Notification::make()->title('BAST tidak dapat dibuat')->body(self::PESAN_USANG)->danger());
    }

    public function test_formulir_usang_karena_aset_sudah_pindah_ditolak(): void
    {
        $aset = $this->buatAset($this->tim1);
        $form = $this->isiForm($aset->id, $this->tim1->id, $this->tim3->id);

        // Aset dipindah lewat menu Aset Tetap sesudah formulir dibuka.
        $aset->update(['tim_penempatan_id' => $this->tim2->id]);

        $form->call('create');

        $this->assertSame(0, BastMutasiAset::count());
        $this->ditolak(Notification::make()->title('BAST tidak dapat dibuat')->body(self::PESAN_USANG)->danger());
    }

    // =====================================================================
    // ASET TANPA PENEMPATAN
    // =====================================================================

    public function test_aset_tanpa_penempatan_ditolak_pada_form(): void
    {
        $aset = $this->buatAset();

        $this->isiForm($aset->id, null, $this->tim2->id)
            ->call('create')
            ->assertHasFormErrors(['aset_id' => self::PESAN_TANPA_PENEMPATAN]);

        $this->assertSame(0, BastMutasiAset::count());
    }

    public function test_memilih_aset_tanpa_penempatan_menampilkan_peringatan(): void
    {
        $aset = $this->buatAset();
        $this->actingAs($this->gudang);

        Livewire::test(CreateBastMutasiAset::class)->set('data.aset_id', $aset->id);

        Notification::assertNotified(
            Notification::make()->title('Aset belum memiliki penempatan')->body(self::PESAN_TANPA_PENEMPATAN)->warning()
        );
    }

    public function test_server_menolak_aset_tanpa_penempatan_tanpa_bergantung_pada_form(): void
    {
        $aset = $this->buatAset();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(self::PESAN_TANPA_PENEMPATAN);

        app(MutasiAsetService::class)->periksaPembuatan($aset->id, $this->tim1->id);
    }

    // =====================================================================
    // SKENARIO AUDIT: BLOKIR BAST GANDA
    // =====================================================================

    public function test_skenario_audit_asal_salah_ditolak_asal_benar_dibuat_bast_kedua_diblokir(): void
    {
        $aset = $this->buatAset($this->tim1);

        // Asal tim 3, padahal aset di tim 1.
        $this->isiForm($aset->id, $this->tim3->id, $this->tim2->id)->call('create');
        $this->assertSame(0, BastMutasiAset::count());

        // Asal tim 1: dibuat.
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Pertama')->call('create')->assertHasNoFormErrors();
        $pertama = BastMutasiAset::sole();

        // BAST kedua untuk aset yang sama selagi yang pertama menunggu pengesahan.
        $this->isiForm($aset->id, $this->tim1->id, $this->tim3->id, 'Kedua')
            ->call('create')
            ->assertHasFormErrors(['aset_id' => $this->pesanBlokir($pertama)]);

        $this->assertSame(1, BastMutasiAset::count());
    }

    public function test_server_memblokir_bast_kedua_tanpa_bergantung_pada_form(): void
    {
        $aset = $this->buatAset($this->tim1);
        $pertama = $this->buatBast($this->tim1, $this->tim2, $this->gudang, tambahan: ['aset_id' => $aset->id]);

        try {
            app(MutasiAsetService::class)->periksaPembuatan($aset->id, $this->tim1->id);
            $this->fail('Seharusnya diblokir.');
        } catch (\RuntimeException $e) {
            $this->assertSame(\RuntimeException::class, $e::class);
            $this->assertSame($this->pesanBlokir($pertama), $e->getMessage());
        }
    }

    public function test_form_menampilkan_kesalahan_pada_aset_yang_masih_punya_bast_menunggu(): void
    {
        $aset = $this->buatAset($this->tim1);
        $bast = $this->buatBast($this->tim1, $this->tim2, $this->gudang, tambahan: ['aset_id' => $aset->id]);

        $this->isiForm($aset->id, $this->tim1->id, $this->tim3->id)
            ->call('create')
            ->assertHasFormErrors(['aset_id' => $this->pesanBlokir($bast)]);

        $this->assertSame(1, BastMutasiAset::count());
        $this->assertSame($bast->id, BastMutasiAset::sole()->id);
    }

    public function test_bast_yang_sudah_disahkan_atau_tuntas_tidak_memblokir(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->buatBast($this->tim1, $this->tim2, $this->gudang, 'menunggu_konfirmasi', ['aset_id' => $aset->id]);
        $this->buatBast($this->tim1, $this->tim2, $this->gudang, 'selesai_administratif', ['aset_id' => $aset->id]);

        $this->assertNull(app(MutasiAsetService::class)->pesanAsetTidakDapatDimutasi($aset->refresh()));

        $this->isiForm($aset->id, $this->tim1->id, $this->tim3->id)->call('create')->assertHasNoFormErrors();
        $this->assertSame(3, BastMutasiAset::count());
    }

    public function test_bast_usang_yang_asalnya_bukan_penempatan_sekarang_tidak_memblokir(): void
    {
        $aset = $this->buatAset($this->tim1);
        $usang = $this->buatBast($this->tim1, $this->tim2, $this->gudang, tambahan: ['aset_id' => $aset->id]);

        // Aset dipindah ke tim 3 lewat menu Aset Tetap; asal BAST (tim 1) tak lagi sama.
        $aset->update(['tim_penempatan_id' => $this->tim3->id]);

        $this->isiForm($aset->id, $this->tim3->id, $this->tim2->id, 'Baru')->call('create')->assertHasNoFormErrors();

        $this->assertSame(2, BastMutasiAset::count());
        $this->assertSame('menunggu_pengesahan', $usang->fresh()->status);
    }

    // =====================================================================
    // RIWAYAT BERURUTAN
    // =====================================================================

    public function test_setelah_bast_pertama_disahkan_bast_baru_dari_tim_tujuan_diperbolehkan_dan_riwayat_berurutan(): void
    {
        $aset = $this->buatAset($this->tim1);
        $aset->catatPenempatanAwal();

        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Pertama')->call('create')->assertHasNoFormErrors();
        $pertama = BastMutasiAset::where('alasan_mutasi', 'Pertama')->sole();

        $this->sahkanLewatTabel($pertama);

        $this->assertSame('menunggu_konfirmasi', $pertama->fresh()->status);
        $this->assertSame($this->tim2->id, $aset->fresh()->tim_penempatan_id);

        // Menunggu konfirmasi tidak memblokir; asal kini tim 2.
        $this->isiForm($aset->id, $this->tim2->id, $this->tim3->id, 'Kedua')->call('create')->assertHasNoFormErrors();
        $kedua = BastMutasiAset::where('alasan_mutasi', 'Kedua')->sole();

        $this->sahkanLewatTabel($kedua);

        $this->assertSame($this->tim3->id, $aset->fresh()->tim_penempatan_id);

        $riwayat = RiwayatPenempatanAset::where('aset_id', $aset->id)->orderBy('id')->get();

        $this->assertSame(
            [$this->tim1->id, $this->tim2->id, $this->tim3->id],
            $riwayat->pluck('tim_id')->all(),
            'Tim 1, lalu tim 2, lalu tim 3; tidak ada yang hilang.',
        );
        $this->assertSame(['penempatan_awal', 'mutasi', 'mutasi'], $riwayat->pluck('jenis')->all());
        $this->assertSame([null, $pertama->id, $kedua->id], $riwayat->pluck('bast_id')->all());
        $this->assertNotNull($riwayat[0]->tanggal_selesai);
        $this->assertNotNull($riwayat[1]->tanggal_selesai);
        $this->assertNull($riwayat[2]->tanggal_selesai, 'Hanya penempatan terakhir yang masih berjalan.');
    }

    // =====================================================================
    // SAHKAN: PERIKSA ULANG PENEMPATAN
    // =====================================================================

    public function test_sahkan_ditolak_bila_penempatan_berubah_dan_tidak_ada_yang_berubah(): void
    {
        $aset = $this->buatAset($this->tim1);
        $aset->catatPenempatanAwal();

        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id)->call('create')->assertHasNoFormErrors();
        $bast = BastMutasiAset::sole();
        $berkas = $bast->file_bast_path;

        // Penempatan berubah lewat menu Aset Tetap antara pembuatan dan pengesahan.
        $aset->update(['tim_penempatan_id' => $this->tim3->id]);
        $riwayatSebelum = RiwayatPenempatanAset::orderBy('id')->get()->toArray();

        $this->sahkanLewatTabel($bast);

        $this->ditolak(Notification::make()->title('BAST tidak dapat disahkan')->body(self::PESAN_SAHKAN_USANG)->danger());

        $bast->refresh();
        $this->assertSame('menunggu_pengesahan', $bast->status);
        $this->assertNull($bast->disahkan_oleh_id);
        $this->assertNull($bast->disahkan_at);
        $this->assertSame($berkas, $bast->file_bast_path);
        $this->assertSame($this->tim3->id, $aset->fresh()->tim_penempatan_id, 'Aset tidak berpindah.');
        $this->assertSame($riwayatSebelum, RiwayatPenempatanAset::orderBy('id')->get()->toArray(), 'Riwayat tidak berubah.');
    }

    public function test_sahkan_di_layanan_melempar_pesan_bisnis_sebelum_menulis_apa_pun(): void
    {
        $aset = $this->buatAset($this->tim1);
        $bast = $this->buatBast($this->tim1, $this->tim2, $this->gudang, tambahan: ['aset_id' => $aset->id]);
        $aset->update(['tim_penempatan_id' => $this->tim3->id]);

        try {
            app(MutasiAsetService::class)->sahkan($bast, $this->kasubbag->id);
            $this->fail('Seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertSame(\RuntimeException::class, $e::class);
            $this->assertSame(self::PESAN_SAHKAN_USANG, $e->getMessage());
        }

        $this->assertSame('menunggu_pengesahan', $bast->fresh()->status);
        $this->assertSame(0, RiwayatPenempatanAset::count());
    }

    public function test_bast_usang_yang_ditolak_tidak_memblokir_bast_baru_untuk_aset_yang_sama(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Usang')->call('create')->assertHasNoFormErrors();
        $usang = BastMutasiAset::where('alasan_mutasi', 'Usang')->sole();

        $aset->update(['tim_penempatan_id' => $this->tim3->id]);
        $this->sahkanLewatTabel($usang);
        $this->assertSame('menunggu_pengesahan', $usang->fresh()->status);

        // BAST usang itu tetap menunggu_pengesahan selamanya, tetapi tidak memblokir.
        $this->isiForm($aset->id, $this->tim3->id, $this->tim1->id, 'Baru')->call('create')->assertHasNoFormErrors();
        $baru = BastMutasiAset::where('alasan_mutasi', 'Baru')->sole();

        $this->sahkanLewatTabel($baru);

        $this->assertSame('menunggu_konfirmasi', $baru->fresh()->status);
        $this->assertSame($this->tim1->id, $aset->fresh()->tim_penempatan_id);
    }

    public function test_sahkan_normal_tetap_berhasil(): void
    {
        $aset = $this->buatAset($this->tim1);
        $aset->catatPenempatanAwal();
        $bast = $this->buatBast($this->tim1, $this->tim2, $this->gudang, tambahan: ['aset_id' => $aset->id]);

        $this->sahkanLewatTabel($bast);

        $bast->refresh();
        $this->assertSame('menunggu_konfirmasi', $bast->status);
        $this->assertSame($this->kasubbag->id, $bast->disahkan_oleh_id);
        $this->assertNotNull($bast->disahkan_at);
        $this->assertSame($this->tim2->id, $aset->fresh()->tim_penempatan_id);
        $this->assertSame(2, RiwayatPenempatanAset::where('aset_id', $aset->id)->count());
        Notification::assertNotified(Notification::make()->title('BAST disahkan')->success());
    }

    public function test_galat_teknis_saat_sahkan_tidak_ditelan(): void
    {
        $aset = $this->buatAset($this->tim1);
        $bast = $this->buatBast($this->tim1, $this->tim2, $this->gudang, tambahan: ['aset_id' => $aset->id]);

        // Kolom hilang pada BAST berarti galat teknis, bukan penolakan aturan bisnis.
        $bast->aset_id = 999999;

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(MutasiAsetService::class)->sahkan($bast, $this->kasubbag->id);
    }
}
