<?php

namespace Tests\Feature;

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
 * diblokir selama BAST sebelumnya menunggu konfirmasi penerimaan, dan
 * Konfirmasi Penerimaan memeriksa ulang penempatan aset secara atomik.
 *
 * Urutan alur dibalik: Buat -> menunggu_konfirmasi -> Konfirmasi Penerimaan
 * (Ketua Tim tujuan, aset berpindah di sini) -> menunggu_pengesahan -> Sahkan
 * (Kasubbag, finalisasi) -> selesai_administratif. Pembuatan BAST kini lewat
 * pop-up CreateAction pada ListBastMutasiAsets (B), bukan halaman terpisah.
 */
class MutasiAsetPenempatanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN_TANPA_PENEMPATAN = 'Aset ini belum memiliki penempatan. Tetapkan penempatan terlebih dahulu lewat menu Aset Tetap.';

    private const PESAN_USANG = 'Penempatan aset telah berubah. Muat ulang formulir dan coba lagi.';

    private const PESAN_KONFIRMASI_USANG = 'Penempatan aset sudah berubah sejak BAST dibuat, sehingga penerimaan ini tidak dapat dikonfirmasi. Buat BAST baru bila mutasi masih diperlukan.';

    private Tim $tim1;

    private Tim $tim2;

    private Tim $tim3;

    private User $gudang;

    private User $kasubbag;

    private User $ketua1;

    private User $ketua2;

    private User $ketua3;

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

        // Penjaga tanda tangan (E): ketiga tim butuh Ketua Tim yang sudah
        // bertanda tangan, sebab BAST antara tim mana pun di sini bisa dibuat.
        $this->ketua1 = $this->tandaiKetua($this->tim1);
        $this->ketua2 = $this->tandaiKetua($this->tim2);
        $this->ketua3 = $this->tandaiKetua($this->tim3);
    }

    /** Ketua Tim bertanda tangan pada sebuah tim (E) — dipakai di seluruh berkas ini. */
    private function tandaiKetua(Tim $tim): User
    {
        $ketua = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $tim));
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        return $ketua;
    }

    /** Mengisi dan mengirim pop-up "Buat BAST"; `$timAsal` = null berarti tidak mengirim Tim Asal. */
    private function isiForm(int $asetId, ?int $timAsal, int $tujuan, string $alasan = 'Redistribusi'): \Livewire\Features\SupportTesting\Testable
    {
        $this->actingAs($this->gudang);

        return Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData([
                'aset_id'       => $asetId,
                'tim_asal_id'   => $timAsal,
                'tim_tujuan_id' => $tujuan,
                'alasan_mutasi' => $alasan,
            ]);
    }

    private function buatBast(string $status = 'menunggu_konfirmasi', string $alasan = 'Redistribusi'): \Livewire\Features\SupportTesting\Testable
    {
        return $this->isiForm($this->buatAset($this->tim1)->id, $this->tim1->id, $this->tim2->id, $alasan);
    }

    private function konfirmasiLewatTabel(BastMutasiAset $bast): void
    {
        $ketuaTujuan = $bast->timTujuan->ketuaTim;
        $this->actingAs($ketuaTujuan);

        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('konfirmasi')->table($bast));
    }

    private function sahkanLewatTabel(BastMutasiAset $bast): void
    {
        $this->actingAs($this->kasubbag);

        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('sahkan')->table($bast));
    }

    private function pesanBlokir(BastMutasiAset $bast): string
    {
        return "Aset ini masih memiliki BAST yang menunggu konfirmasi penerimaan ({$bast->nomor_bast}). Konfirmasikan penerimaan BAST tersebut terlebih dahulu.";
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

        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->assertActionDataSet(['tim_asal_id' => null])
            ->setActionData(['aset_id' => $aset->id])
            ->assertActionDataSet(['tim_asal_id' => $this->tim1->id]);
    }

    public function test_pembuatan_normal_menyimpan_asal_dari_penempatan_aset(): void
    {
        $aset = $this->buatAset($this->tim1);

        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id)->callMountedAction()->assertHasNoActionErrors();

        $bast = BastMutasiAset::sole();
        $this->assertSame($this->tim1->id, $bast->tim_asal_id);
        $this->assertSame('menunggu_konfirmasi', $bast->status);
    }

    public function test_muatan_dengan_tim_asal_berbeda_ditolak_dan_tidak_membentuk_bast(): void
    {
        $aset = $this->buatAset($this->tim1);

        $this->isiForm($aset->id, $this->tim3->id, $this->tim2->id)->callMountedAction();

        $this->assertSame(0, BastMutasiAset::count());
        $this->ditolak(Notification::make()->title('BAST tidak dapat dibuat')->body(self::PESAN_USANG)->danger());
    }

    public function test_formulir_usang_karena_aset_sudah_pindah_ditolak(): void
    {
        $aset = $this->buatAset($this->tim1);
        $form = $this->isiForm($aset->id, $this->tim1->id, $this->tim3->id);

        // Aset dipindah lewat menu Aset Tetap sesudah pop-up dibuka.
        $aset->update(['tim_penempatan_id' => $this->tim2->id]);

        $form->callMountedAction();

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
            ->callMountedAction()
            ->assertHasActionErrors(['aset_id' => self::PESAN_TANPA_PENEMPATAN]);

        $this->assertSame(0, BastMutasiAset::count());
    }

    public function test_memilih_aset_tanpa_penempatan_menampilkan_peringatan(): void
    {
        $aset = $this->buatAset();
        $this->actingAs($this->gudang);

        Livewire::test(ListBastMutasiAsets::class)->mountAction('create')->setActionData(['aset_id' => $aset->id]);

        Notification::assertNotified(
            Notification::make()->title('Aset belum memiliki penempatan')->body(self::PESAN_TANPA_PENEMPATAN)->warning()
        );
    }

    public function test_server_menolak_aset_tanpa_penempatan_tanpa_bergantung_pada_form(): void
    {
        $aset = $this->buatAset();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(self::PESAN_TANPA_PENEMPATAN);

        app(MutasiAsetService::class)->periksaPembuatan($aset->id, $this->tim1->id, $this->tim2->id);
    }

    // =====================================================================
    // SKENARIO AUDIT: BLOKIR BAST GANDA
    // =====================================================================

    public function test_skenario_audit_asal_salah_ditolak_asal_benar_dibuat_bast_kedua_diblokir(): void
    {
        $aset = $this->buatAset($this->tim1);

        // Asal tim 3, padahal aset di tim 1.
        $this->isiForm($aset->id, $this->tim3->id, $this->tim2->id)->callMountedAction();
        $this->assertSame(0, BastMutasiAset::count());

        // Asal tim 1: dibuat.
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Pertama')->callMountedAction()->assertHasNoActionErrors();
        $pertama = BastMutasiAset::sole();

        // BAST kedua untuk aset yang sama selagi yang pertama menunggu konfirmasi.
        $this->isiForm($aset->id, $this->tim1->id, $this->tim3->id, 'Kedua')
            ->callMountedAction()
            ->assertHasActionErrors(['aset_id' => $this->pesanBlokir($pertama)]);

        $this->assertSame(1, BastMutasiAset::count());
    }

    public function test_server_memblokir_bast_kedua_tanpa_bergantung_pada_form(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Pertama')->callMountedAction()->assertHasNoActionErrors();
        $pertama = BastMutasiAset::sole();

        try {
            app(MutasiAsetService::class)->periksaPembuatan($aset->id, $this->tim1->id, $this->tim3->id);
            $this->fail('Seharusnya diblokir.');
        } catch (\RuntimeException $e) {
            $this->assertSame(\RuntimeException::class, $e::class);
            $this->assertSame($this->pesanBlokir($pertama), $e->getMessage());
        }
    }

    public function test_form_menampilkan_kesalahan_pada_aset_yang_masih_punya_bast_menunggu(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Pertama')->callMountedAction()->assertHasNoActionErrors();
        $bast = BastMutasiAset::sole();

        $this->isiForm($aset->id, $this->tim1->id, $this->tim3->id)
            ->callMountedAction()
            ->assertHasActionErrors(['aset_id' => $this->pesanBlokir($bast)]);

        $this->assertSame(1, BastMutasiAset::count());
        $this->assertSame($bast->id, BastMutasiAset::sole()->id);
    }

    public function test_bast_yang_sudah_dikonfirmasi_atau_tuntas_tidak_memblokir(): void
    {
        $aset = $this->buatAset($this->tim1);

        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Sudah dikonfirmasi')->callMountedAction()->assertHasNoActionErrors();
        $sudahDikonfirmasi = BastMutasiAset::where('alasan_mutasi', 'Sudah dikonfirmasi')->sole();
        $this->konfirmasiLewatTabel($sudahDikonfirmasi);

        // Aset kini di tim2; buat & tuntaskan BAST kedua dari tim2 ke tim3.
        $this->isiForm($aset->fresh()->id, $this->tim2->id, $this->tim3->id, 'Tuntas')->callMountedAction()->assertHasNoActionErrors();
        $tuntas = BastMutasiAset::where('alasan_mutasi', 'Tuntas')->sole();
        $this->konfirmasiLewatTabel($tuntas);
        $this->sahkanLewatTabel($tuntas->fresh());

        $this->assertNull(app(MutasiAsetService::class)->pesanAsetTidakDapatDimutasi($aset->refresh()));

        $this->isiForm($aset->id, $this->tim3->id, $this->tim1->id, 'Ketiga')->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame(3, BastMutasiAset::count());
    }

    public function test_bast_usang_yang_asalnya_bukan_penempatan_sekarang_tidak_memblokir(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Usang')->callMountedAction()->assertHasNoActionErrors();
        $usang = BastMutasiAset::where('alasan_mutasi', 'Usang')->sole();

        // Aset dipindah ke tim 3 lewat menu Aset Tetap; asal BAST (tim 1) tak lagi sama.
        $aset->update(['tim_penempatan_id' => $this->tim3->id]);

        $this->isiForm($aset->id, $this->tim3->id, $this->tim2->id, 'Baru')->callMountedAction()->assertHasNoActionErrors();

        $this->assertSame(2, BastMutasiAset::count());
        $this->assertSame('menunggu_konfirmasi', $usang->fresh()->status);
    }

    // =====================================================================
    // RIWAYAT BERURUTAN
    // =====================================================================

    public function test_setelah_bast_pertama_dikonfirmasi_bast_baru_dari_tim_tujuan_diperbolehkan_dan_riwayat_berurutan(): void
    {
        $aset = $this->buatAset($this->tim1);
        $aset->catatPenempatanAwal();

        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Pertama')->callMountedAction()->assertHasNoActionErrors();
        $pertama = BastMutasiAset::where('alasan_mutasi', 'Pertama')->sole();

        $this->konfirmasiLewatTabel($pertama);

        $this->assertSame('menunggu_pengesahan', $pertama->fresh()->status);
        $this->assertSame($this->tim2->id, $aset->fresh()->tim_penempatan_id);

        // Menunggu pengesahan tidak memblokir; asal kini tim 2.
        $this->isiForm($aset->fresh()->id, $this->tim2->id, $this->tim3->id, 'Kedua')->callMountedAction()->assertHasNoActionErrors();
        $kedua = BastMutasiAset::where('alasan_mutasi', 'Kedua')->sole();

        $this->konfirmasiLewatTabel($kedua);

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
    // KONFIRMASI: PERIKSA ULANG PENEMPATAN (dipindah dari sahkan() lama)
    // =====================================================================

    public function test_konfirmasi_ditolak_bila_penempatan_berubah_dan_tidak_ada_yang_berubah(): void
    {
        $aset = $this->buatAset($this->tim1);
        $aset->catatPenempatanAwal();

        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id)->callMountedAction()->assertHasNoActionErrors();
        $bast = BastMutasiAset::sole();
        $berkas = $bast->file_bast_path;

        // Penempatan berubah lewat menu Aset Tetap antara pembuatan dan konfirmasi.
        $aset->update(['tim_penempatan_id' => $this->tim3->id]);
        $riwayatSebelum = RiwayatPenempatanAset::orderBy('id')->get()->toArray();

        $this->konfirmasiLewatTabel($bast);

        $this->ditolak(Notification::make()->title('Penerimaan tidak dapat dikonfirmasi')->body(self::PESAN_KONFIRMASI_USANG)->danger());

        $bast->refresh();
        $this->assertSame('menunggu_konfirmasi', $bast->status);
        $this->assertNull($bast->dikonfirmasi_oleh_id);
        $this->assertNull($bast->dikonfirmasi_at);
        $this->assertSame($berkas, $bast->file_bast_path);
        $this->assertSame($this->tim3->id, $aset->fresh()->tim_penempatan_id, 'Aset tidak berpindah.');
        $this->assertSame($riwayatSebelum, RiwayatPenempatanAset::orderBy('id')->get()->toArray(), 'Riwayat tidak berubah.');
    }

    public function test_konfirmasi_di_layanan_melempar_pesan_bisnis_sebelum_menulis_apa_pun(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id)->callMountedAction()->assertHasNoActionErrors();
        $bast = BastMutasiAset::sole();
        $aset->update(['tim_penempatan_id' => $this->tim3->id]);

        try {
            app(MutasiAsetService::class)->konfirmasi($bast, $this->ketua2->id);
            $this->fail('Seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertSame(\RuntimeException::class, $e::class);
            $this->assertSame(self::PESAN_KONFIRMASI_USANG, $e->getMessage());
        }

        $this->assertSame('menunggu_konfirmasi', $bast->fresh()->status);
        $this->assertSame(0, RiwayatPenempatanAset::count());
    }

    public function test_bast_usang_yang_ditolak_tidak_memblokir_bast_baru_untuk_aset_yang_sama(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id, 'Usang')->callMountedAction()->assertHasNoActionErrors();
        $usang = BastMutasiAset::where('alasan_mutasi', 'Usang')->sole();

        $aset->update(['tim_penempatan_id' => $this->tim3->id]);
        $this->konfirmasiLewatTabel($usang);
        $this->assertSame('menunggu_konfirmasi', $usang->fresh()->status);

        // BAST usang itu tetap menunggu_konfirmasi selamanya, tetapi tidak memblokir.
        $this->isiForm($aset->id, $this->tim3->id, $this->tim1->id, 'Baru')->callMountedAction()->assertHasNoActionErrors();
        $baru = BastMutasiAset::where('alasan_mutasi', 'Baru')->sole();

        $this->konfirmasiLewatTabel($baru);

        $this->assertSame('menunggu_pengesahan', $baru->fresh()->status);
        $this->assertSame($this->tim1->id, $aset->fresh()->tim_penempatan_id);
    }

    public function test_konfirmasi_normal_tetap_berhasil(): void
    {
        $aset = $this->buatAset($this->tim1);
        $aset->catatPenempatanAwal();
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id)->callMountedAction()->assertHasNoActionErrors();
        $bast = BastMutasiAset::sole();

        $this->konfirmasiLewatTabel($bast);

        $bast->refresh();
        $this->assertSame('menunggu_pengesahan', $bast->status);
        $this->assertSame($this->ketua2->id, $bast->dikonfirmasi_oleh_id);
        $this->assertNotNull($bast->dikonfirmasi_at);
        $this->assertSame($this->tim2->id, $aset->fresh()->tim_penempatan_id);
        $this->assertSame(2, RiwayatPenempatanAset::where('aset_id', $aset->id)->count());
        Notification::assertNotified(Notification::make()->title('Penerimaan aset dikonfirmasi')->success());
    }

    public function test_galat_teknis_saat_konfirmasi_tidak_ditelan(): void
    {
        $aset = $this->buatAset($this->tim1);
        $this->isiForm($aset->id, $this->tim1->id, $this->tim2->id)->callMountedAction()->assertHasNoActionErrors();
        $bast = BastMutasiAset::sole();

        // Kolom hilang pada BAST berarti galat teknis, bukan penolakan aturan bisnis.
        $bast->aset_id = 999999;

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(MutasiAsetService::class)->konfirmasi($bast, $this->ketua2->id);
    }
}
