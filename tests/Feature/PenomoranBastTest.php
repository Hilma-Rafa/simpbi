<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Models\BastMutasiAset;
use App\Models\Tim;
use App\Models\User;
use App\Services\DokumenBastService;
use App\Services\MutasiAsetService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit A-020: nomor BAST dibangun dari nomor terbesar tahun itu (bukan
 * jumlah baris), dan bentrok UNIQUE diulang dengan nomor baru.
 *
 * Konkurensi sungguhan tidak dapat diuji di sini; bentrok disimulasikan dengan
 * menyisipkan baris bernomor sama tepat sebelum penyimpanan.
 */
class PenomoranBastTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Tim $asal;

    private Tim $tujuan;

    private User $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Filament::setCurrentPanel('admin');

        $this->asal = $this->buatTim('Tim Asal');
        $this->tujuan = $this->buatTim('Tim Tujuan');
        $this->gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));

        // Penjaga tanda tangan (E): pembuatan lewat pop-up butuh Ketua Tim
        // asal dan tujuan yang sudah bertanda tangan.
        $ketuaAsal = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $this->asal));
        $this->asal->forceFill(['ketua_tim_id' => $ketuaAsal->id])->save();
        $ketuaTujuan = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $this->tujuan));
        $this->tujuan->forceFill(['ketua_tim_id' => $ketuaTujuan->id])->save();
    }

    private function nomor(int $urut, ?int $tahun = null): string
    {
        return sprintf('BAST-%d-%04d', $tahun ?? now()->year, $urut);
    }

    private function bastBernomor(string $nomor): BastMutasiAset
    {
        return $this->buatBast($this->asal, $this->tujuan, $this->gudang, tambahan: ['nomor_bast' => $nomor]);
    }

    private function buatLewatForm()
    {
        $aset = $this->buatAset($this->asal);
        $this->actingAs($this->gudang);

        return Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData([
                'aset_id'       => $aset->id,
                'tim_asal_id'   => $this->asal->id,
                'tim_tujuan_id' => $this->tujuan->id,
                'alasan_mutasi' => 'Uji penomoran',
            ])
            ->callMountedAction();
    }

    public function test_nomor_pertama_tahun_ini_adalah_0001(): void
    {
        $this->assertSame($this->nomor(1), app(MutasiAsetService::class)->nomorBaru());
    }

    public function test_celah_nomor_tidak_menyebabkan_bentrok(): void
    {
        foreach ([1, 2, 3] as $urut) {
            $this->bastBernomor($this->nomor($urut));
        }
        BastMutasiAset::where('nomor_bast', $this->nomor(2))->delete();

        // Dua baris tersisa; dihitung dari jumlah, nomor berikutnya 0003 dan bentrok.
        $this->assertSame($this->nomor(4), app(MutasiAsetService::class)->nomorBaru());
    }

    public function test_nomor_tahun_lain_tidak_ikut_dihitung(): void
    {
        $this->bastBernomor($this->nomor(9, now()->year - 1));

        $this->assertSame($this->nomor(1), app(MutasiAsetService::class)->nomorBaru());
    }

    public function test_pembuatan_lewat_form_setelah_ada_celah_berhasil(): void
    {
        $this->bastBernomor($this->nomor(1));
        $this->bastBernomor($this->nomor(2));
        $this->bastBernomor($this->nomor(3));
        BastMutasiAset::where('nomor_bast', $this->nomor(2))->delete();

        $this->buatLewatForm()->assertHasNoActionErrors();

        $this->assertTrue(BastMutasiAset::where('nomor_bast', $this->nomor(4))->exists());
        $this->assertSame(3, BastMutasiAset::count());
    }

    /**
     * Menyisipkan "pembuatan lain" yang mengambil nomor yang sama tepat sebelum
     * penyimpanan: sesudah nomor dihitung dan di luar transaksi percobaan, seperti
     * pembuatan bersamaan yang sudah terkomit. (Disisipkan di dalam transaksi
     * percobaan, barisnya ikut dibatalkan bersama percobaan yang bentrok.) Pembuatan
     * lain itu untuk aset lain: aset yang sama kini diblokir oleh A-011.
     */
    private function rebutNomor(int $kali): \Closure
    {
        $sudah = new \stdClass;
        $sudah->n = 0;
        $sisipkan = fn (string $nomor) => $this->bastBernomor($nomor)->update(['alasan_mutasi' => 'Pembuatan lain']);

        $this->app->bind(MutasiAsetService::class, fn ($app) => new class($app->make(DokumenBastService::class), $sudah, $kali, $sisipkan) extends MutasiAsetService
        {
            public function __construct(DokumenBastService $dokumen, private \stdClass $sudah, private int $kali, private \Closure $sisipkan)
            {
                parent::__construct($dokumen);
            }

            public function nomorBaru(): string
            {
                $nomor = parent::nomorBaru();

                if ($this->sudah->n < $this->kali) {
                    $this->sudah->n++;
                    ($this->sisipkan)($nomor);
                }

                return $nomor;
            }
        });

        return fn (): int => $sudah->n;
    }

    public function test_bentrok_sekali_diulang_dengan_nomor_berikutnya(): void
    {
        $bentrok = $this->rebutNomor(1);

        $this->buatLewatForm()->assertHasNoActionErrors();

        $this->assertSame(1, $bentrok());
        $this->assertSame(
            [$this->nomor(1), $this->nomor(2)],
            BastMutasiAset::orderBy('id')->pluck('nomor_bast')->all(),
        );
        $this->assertSame('Uji penomoran', BastMutasiAset::where('nomor_bast', $this->nomor(2))->value('alasan_mutasi'));
    }

    public function test_bentrok_berulang_berhenti_setelah_tiga_kali_dengan_pesan_umum(): void
    {
        $bentrok = $this->rebutNomor(10);

        $this->buatLewatForm();

        $this->assertSame(3, $bentrok(), 'Percobaan dibatasi tiga kali.');
        $this->assertSame(0, BastMutasiAset::where('alasan_mutasi', 'Uji penomoran')->count());
        Notification::assertNotified(
            Notification::make()
                ->title('Nomor BAST gagal diterbitkan')
                ->body('Coba lagi, atau hubungi Sub-Bagian Umum bila berulang.')
                ->danger()
        );
    }
}
