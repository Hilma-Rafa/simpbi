<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Models\AsetTetap;
use App\Models\BastMutasiAset;
use App\Models\Tim;
use App\Models\User;
use App\Services\DokumenBastService;
use App\Services\MutasiAsetService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Kasus uji basis path Increment 3 (Modul Mutasi Aset).
 *
 * Hanya jalur independen yang belum dieksekusi tes lain; nomor jalurnya
 * mengacu ke docs/pengujian/wb-increment-3.md.
 */
class JalurBasisModul3Test extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Tim $asal;

    private Tim $tujuan;

    private User $gudang;

    private User $ketuaTujuan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->asal   = $this->buatTim('Tim Asal');
        $this->tujuan = $this->buatTim('Tim Tujuan');
        $this->gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));

        $this->ketuai($this->asal, bertandaTangan: true);
        $this->ketuaTujuan = $this->ketuai($this->tujuan, bertandaTangan: true);
    }

    private function ketuai(Tim $tim, bool $bertandaTangan): User
    {
        $ketua = $this->buatPengguna('ketua_tim', $tim);

        if ($bertandaTangan) {
            $ketua = $this->lengkapiAkun($ketua);
        }

        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        return $ketua;
    }

    /** Memanggil handleCreation() apa adanya, sebagai Petugas Gudang. */
    private function buatLangsung(array $data): mixed
    {
        $this->actingAs($this->gudang);

        $metode = new \ReflectionMethod(ListBastMutasiAsets::class, 'handleCreation');
        $metode->setAccessible(true);

        return $metode->invoke(null, $data + [
            'nomor_bast'     => app(MutasiAsetService::class)->nomorBaru(),
            'dibuat_oleh_id' => $this->gudang->id,
            'status'         => 'menunggu_konfirmasi',
            'alasan_mutasi'  => 'Uji jalur',
            'pihak_penyerah' => 'Uji',
            'pihak_penerima' => 'Uji',
        ]);
    }

    private function ditolakDenganPesan(callable $aksi, string $pesan): void
    {
        try {
            $aksi();
            $this->fail('Pembuatan seharusnya dihentikan.');
        } catch (Halt) {
            // Pop-up tetap terbuka; penolakannya tampil sebagai notifikasi.
        }

        Notification::assertNotified(Notification::make()->title('BAST tidak dapat dibuat')->body($pesan)->danger());
        $this->assertSame(0, BastMutasiAset::count());
    }

    // =====================================================================
    // 3.4 periksaPembuatan, 3.5 kunciDanPastikanStatus
    // =====================================================================

    /** 3.4 J1 */
    public function test_periksa_pembuatan_aset_tidak_ditemukan(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aset tidak ditemukan.');

        app(MutasiAsetService::class)->periksaPembuatan(999999, $this->asal->id, $this->tujuan->id);
    }

    /** 3.4 J8: tanpa tim tujuan (node 11 salah), hanya tanda tangan Ketua Tim asal yang diperiksa. */
    public function test_periksa_pembuatan_tanpa_tim_tujuan_hanya_memeriksa_tim_asal(): void
    {
        $aset = $this->buatAset($this->asal);

        app(MutasiAsetService::class)->periksaPembuatan($aset->id, $this->asal->id, null);

        $this->addToAssertionCount(1);
    }

    /** 3.5 J1: model BAST yang dipegang pemanggil sudah tidak punya baris. */
    public function test_sahkan_bast_yang_barisnya_hilang_ditolak(): void
    {
        $bast = $this->buatBast($this->asal, $this->tujuan, $this->gudang, 'menunggu_pengesahan');
        DB::table('bast_mutasi_aset')->where('id', $bast->id)->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BAST ini sudah diproses atau statusnya telah berubah.');

        app(MutasiAsetService::class)->sahkan($bast, $this->buatPengguna('kasubbag')->id);
    }

    // =====================================================================
    // 3.8 ListBastMutasiAsets::handleCreation
    // =====================================================================

    /** J6: tanpa tim_tujuan_id, pelanggaran NOT NULL adalah galat teknis dan dilempar ulang. */
    public function test_buat_bast_tanpa_tim_tujuan_melempar_galat_teknis(): void
    {
        $aset = $this->buatAset($this->asal);

        $this->expectException(QueryException::class);

        $this->buatLangsung(['aset_id' => $aset->id, 'tim_asal_id' => $this->asal->id]);
    }

    /** J7: tanpa aset_id (sisi kanan ?? = 0) → penolakan aturan. */
    public function test_buat_bast_tanpa_aset_id_ditolak_dengan_notifikasi(): void
    {
        $this->ditolakDenganPesan(
            fn () => $this->buatLangsung(['tim_asal_id' => $this->asal->id, 'tim_tujuan_id' => $this->tujuan->id]),
            'Aset tidak ditemukan.',
        );
    }

    /** J8: tanpa tim_asal_id (null) → dianggap penempatan berubah. */
    public function test_buat_bast_tanpa_tim_asal_ditolak_dengan_notifikasi(): void
    {
        $aset = $this->buatAset($this->asal);

        $this->ditolakDenganPesan(
            fn () => $this->buatLangsung(['aset_id' => $aset->id, 'tim_tujuan_id' => $this->tujuan->id]),
            'Penempatan aset telah berubah. Muat ulang formulir dan coba lagi.',
        );
    }

    /** J9: tanpa tim_tujuan_id pada aset yang masih terblokir → ditolak sebelum menyentuh basis data. */
    public function test_buat_bast_tanpa_tim_tujuan_pada_aset_terblokir_ditolak(): void
    {
        $aset = $this->buatAset($this->asal);
        $menunggu = BastMutasiAset::create([
            'nomor_bast' => 'BAST-UJI-9001', 'aset_id' => $aset->id, 'tim_asal_id' => $this->asal->id,
            'tim_tujuan_id' => $this->tujuan->id, 'alasan_mutasi' => 'Terdahulu', 'pihak_penyerah' => 'Uji',
            'pihak_penerima' => 'Uji', 'status' => 'menunggu_konfirmasi', 'dibuat_oleh_id' => $this->gudang->id,
        ]);

        try {
            $this->buatLangsung(['aset_id' => $aset->id, 'tim_asal_id' => $this->asal->id]);
            $this->fail('Pembuatan seharusnya dihentikan.');
        } catch (Halt) {
        }

        Notification::assertNotified(Notification::make()->title('BAST tidak dapat dibuat')
            ->body("Aset ini masih memiliki BAST yang menunggu konfirmasi penerimaan ({$menunggu->nomor_bast}). Konfirmasikan penerimaan BAST tersebut terlebih dahulu.")
            ->danger());
        $this->assertSame(1, BastMutasiAset::count());
    }

    // =====================================================================
    // 3.9 BastMutasiAsetForm::pesanTandaTangan
    // =====================================================================

    /** J3: aset belum dipilih, tim tujuan yang ketuanya belum bertanda tangan sudah dipilih. */
    public function test_peringatan_tanda_tangan_saat_hanya_tim_tujuan_dipilih(): void
    {
        $tanpaTtd = $this->buatTim('Tim Tanpa TTD');
        $this->ketuai($tanpaTtd, bertandaTangan: false);
        $this->actingAs($this->gudang);

        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData(['tim_tujuan_id' => $tanpaTtd->id])
            ->assertMountedActionModalSeeHtml('belum membubuhkan tanda tangan')
            ->assertMountedActionModalSeeHtml('Tim Tanpa TTD');
    }

    // =====================================================================
    // 3.10 aksi Konfirmasi Penerimaan
    // =====================================================================

    private function bastMenungguKonfirmasi(Tim $tujuan): BastMutasiAset
    {
        $aset = $this->buatAset($this->asal);

        return BastMutasiAset::create([
            'nomor_bast' => 'BAST-UJI-9101', 'aset_id' => $aset->id, 'tim_asal_id' => $this->asal->id,
            'tim_tujuan_id' => $tujuan->id, 'alasan_mutasi' => 'Uji konfirmasi', 'pihak_penyerah' => 'Uji',
            'pihak_penerima' => 'Uji', 'status' => 'menunggu_konfirmasi', 'dibuat_oleh_id' => $this->gudang->id,
        ]);
    }

    /** J1: Ketua Tim tujuan belum bertanda tangan → dihentikan, BAST dan aset tidak berubah. */
    public function test_konfirmasi_tanpa_tanda_tangan_penerima_dihentikan(): void
    {
        $tanpaTtd = $this->buatTim('Tim Tanpa TTD');
        $ketua = $this->ketuai($tanpaTtd, bertandaTangan: false);
        $bast = $this->bastMenungguKonfirmasi($tanpaTtd);
        $this->actingAs($ketua);

        Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('konfirmasi')->table($bast));

        Notification::assertNotified('Tanda tangan belum tersedia');
        $this->assertSame('menunggu_konfirmasi', $bast->refresh()->status);
        $this->assertSame($this->asal->id, AsetTetap::find($bast->aset_id)->tim_penempatan_id);
    }

    /** J4: galat teknis (turunan RuntimeException) saat dokumen dibentuk ulang tidak disamarkan. */
    public function test_konfirmasi_galat_teknis_dokumen_dilempar_ulang(): void
    {
        $bast = $this->bastMenungguKonfirmasi($this->tujuan);

        $this->app->bind(DokumenBastService::class, fn () => new class extends DokumenBastService {
            public function __construct() {}

            public function buat(BastMutasiAset $bast): string
            {
                throw new \UnexpectedValueException('Penyimpanan dokumen tidak dapat ditulis.');
            }
        });

        $this->actingAs($this->ketuaTujuan);

        try {
            Livewire::test(ListBastMutasiAsets::class)->callAction(TestAction::make('konfirmasi')->table($bast));
            $this->fail('Galat teknis seharusnya menjalar.');
        } catch (\UnexpectedValueException $e) {
            $this->assertSame('Penyimpanan dokumen tidak dapat ditulis.', $e->getMessage());
        }

        $this->assertSame('menunggu_konfirmasi', $bast->refresh()->status, 'Transaksi konfirmasi harus digulung.');
        $this->assertSame($this->asal->id, AsetTetap::find($bast->aset_id)->tim_penempatan_id);
    }
}
