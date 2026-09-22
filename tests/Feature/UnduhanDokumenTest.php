<?php

namespace Tests\Feature;

use App\Filament\Pages\GantiKataSandi;
use App\Filament\Pages\LengkapiAkun;
use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Models\BastMutasiAset;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Rute unduhan dokumen di luar panel (audit A-002, A-004, A-005, A-014).
 *
 * Bukti permintaan dan BAST hanya boleh diunduh oleh yang boleh melihatnya pada
 * daftar resource terkait; BAST hanya setelah disahkan; dan ketiga rute unduhan
 * (termasuk panduan) dikenai gerbang akun yang sama dengan panel.
 */
class UnduhanDokumenTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const BUKTI = 'bukti-permintaan/PB-UJI.pdf';

    private const BAST = 'bast-mutasi/BAST-UJI.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('local')->put(self::BUKTI, '%PDF-bukti');
        Storage::disk('local')->put(self::BAST, '%PDF-bast');

        Filament::setCurrentPanel('admin');
    }

    /** Membuka URL sebagai pengguna tertentu (null = tamu), tanpa terbawa sesi pengguna sebelumnya. */
    private function buka(?User $pengguna, string $url)
    {
        $this->flushSession();
        auth()->forgetGuards();

        return $pengguna
            ? $this->actingAs($pengguna)->get($url)
            : $this->get($url);
    }

    private function aktif(string $peran, ?Tim $tim = null): User
    {
        return $this->lengkapiAkun($this->buatPengguna($peran, $tim));
    }

    private function permintaanTimB(Tim $timB, array $tambahan = []): PermintaanBarang
    {
        return $this->buatPermintaan(
            $timB,
            $this->buatPengguna('tim', $timB),
            [['barang' => $this->buatBarang(), 'diminta' => 1]],
            'selesai',
            ['file_bukti_path' => self::BUKTI, ...$tambahan],
        );
    }

    private function bast(Tim $asal, Tim $tujuan, bool $disahkan): BastMutasiAset
    {
        return $this->buatBast(
            $asal,
            $tujuan,
            $this->buatPengguna('petugas_gudang'),
            $disahkan ? 'menunggu_konfirmasi' : 'menunggu_pengesahan',
            [
                'file_bast_path' => self::BAST,
                'disahkan_at'    => $disahkan ? now() : null,
            ],
        );
    }

    // ------------------------------------------------------------------
    // A-002: bukti permintaan mengikuti hak lihat pada daftar Permintaan Barang
    // ------------------------------------------------------------------

    public function test_bukti_permintaan_tim_lain_tidak_terlihat_oleh_tim_dan_ketua_tim(): void
    {
        $timA = $this->buatTim('Tim A');
        $timB = $this->buatTim('Tim B');
        $permintaan = $this->permintaanTimB($timB);
        $url = route('bukti.unduh', $permintaan);

        $this->buka($this->aktif('tim', $timA), $url)->assertNotFound();
        $this->buka($this->aktif('ketua_tim', $timA), $url)->assertNotFound();
    }

    public function test_bukti_permintaan_dapat_diunduh_oleh_yang_berhak(): void
    {
        $timB = $this->buatTim('Tim B');
        $permintaan = $this->permintaanTimB($timB);
        $url = route('bukti.unduh', $permintaan);

        foreach (['ketua_tim' => $timB, 'tim' => $timB, 'admin' => null, 'kasubbag' => null, 'petugas_gudang' => null] as $peran => $tim) {
            $this->buka($this->aktif($peran, $tim), $url)
                ->assertOk()
                ->assertDownload($permintaan->kode_permintaan . '.pdf');
        }
    }

    public function test_bukti_permintaan_tanpa_berkas_tetap_404(): void
    {
        $timB = $this->buatTim('Tim B');
        $tanpaBerkas = $this->permintaanTimB($timB, ['file_bukti_path' => null]);
        $berkasHilang = $this->permintaanTimB($timB, ['file_bukti_path' => 'bukti-permintaan/tidak-ada.pdf']);
        $admin = $this->aktif('admin');

        $this->buka($admin, route('bukti.unduh', $tanpaBerkas))->assertNotFound();
        $this->buka($admin, route('bukti.unduh', $berkasHilang))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // A-002 dan A-014: BAST mengikuti hak lihat pada daftar Mutasi Aset, dan hanya setelah disahkan
    // ------------------------------------------------------------------

    public function test_bast_disahkan_hanya_untuk_yang_berhak_melihatnya(): void
    {
        $asal = $this->buatTim('Tim Asal');
        $tujuan = $this->buatTim('Tim Tujuan');
        $lain = $this->buatTim('Tim Lain');
        $bast = $this->bast($asal, $tujuan, disahkan: true);
        $url = route('bast.unduh', $bast);

        foreach ([['ketua_tim', $asal], ['tim', $asal], ['ketua_tim', $tujuan], ['tim', $tujuan], ['kasubbag', null], ['petugas_gudang', null]] as [$peran, $tim]) {
            $this->buka($this->aktif($peran, $tim), $url)
                ->assertOk()
                ->assertDownload($bast->nomor_bast . '.pdf');
        }

        $this->buka($this->aktif('ketua_tim', $lain), $url)->assertNotFound();
        $this->buka($this->aktif('tim', $lain), $url)->assertNotFound();

        // Admin Sistem tidak punya akses ke resource Mutasi Aset.
        $this->buka($this->aktif('admin'), $url)->assertForbidden();
    }

    public function test_bast_yang_belum_disahkan_tidak_dapat_diunduh_siapa_pun(): void
    {
        $asal = $this->buatTim('Tim Asal');
        $tujuan = $this->buatTim('Tim Tujuan');
        $bast = $this->bast($asal, $tujuan, disahkan: false);
        $url = route('bast.unduh', $bast);

        foreach ([['ketua_tim', $asal], ['tim', $tujuan], ['kasubbag', null], ['petugas_gudang', null], ['admin', null]] as [$peran, $tim]) {
            $this->buka($this->aktif($peran, $tim), $url)->assertNotFound();
        }
    }

    public function test_tombol_unduh_bast_hanya_tampil_setelah_disahkan(): void
    {
        $asal = $this->buatTim('Tim Asal');
        $tujuan = $this->buatTim('Tim Tujuan');
        $draf = $this->bast($asal, $tujuan, disahkan: false);
        $sah = $this->bast($asal, $tujuan, disahkan: true);

        foreach (['kasubbag', 'petugas_gudang'] as $peran) {
            $this->flushSession();
            auth()->forgetGuards();
            $this->actingAs($this->aktif($peran));

            Livewire::test(ListBastMutasiAsets::class)
                ->assertActionHidden(TestAction::make('unduh')->table($draf))
                ->assertActionVisible(TestAction::make('unduh')->table($sah));
        }
    }

    // ------------------------------------------------------------------
    // A-004: gerbang akun pada ketiga rute
    // ------------------------------------------------------------------

    /** @return array<string, array{0: string}> */
    public static function rute(): array
    {
        return [
            'bukti permintaan' => ['bukti'],
            'BAST'             => ['bast'],
            'panduan'          => ['panduan'],
        ];
    }

    private function alamat(string $jenis): string
    {
        $timB = $this->buatTim('Tim B');

        return match ($jenis) {
            'bukti'   => route('bukti.unduh', $this->permintaanTimB($timB)),
            'bast'    => route('bast.unduh', $this->bast($this->buatTim('Tim Asal'), $timB, disahkan: true)),
            'panduan' => route('pusat-bantuan.unduh-panduan'),
        };
    }

    #[DataProvider('rute')]
    public function test_akun_nonaktif_dengan_sesi_berjalan_ditolak(string $jenis): void
    {
        $url = $this->alamat($jenis);
        $pengguna = $this->aktif('kasubbag');

        $this->buka($pengguna, route('pusat-bantuan.unduh-panduan'))->assertNotFound(); // masih aktif: lolos gerbang

        $pengguna->forceFill(['status_aktif' => false])->save();

        $this->buka($pengguna->refresh(), $url)->assertForbidden();
    }

    #[DataProvider('rute')]
    public function test_akun_wajib_ganti_sandi_dialihkan(string $jenis): void
    {
        $url = $this->alamat($jenis);
        $pengguna = $this->buatPengguna('kasubbag', null, ['harus_ganti_sandi' => true]);

        $this->buka($pengguna, $url)->assertRedirect(GantiKataSandi::getUrl());
    }

    #[DataProvider('rute')]
    public function test_akun_belum_lengkap_dialihkan(string $jenis): void
    {
        $url = $this->alamat($jenis);
        // Petugas Gudang wajib punya nomor WhatsApp dan tanda tangan.
        $pengguna = $this->buatPengguna('petugas_gudang');

        $this->buka($pengguna, $url)->assertRedirect(LengkapiAkun::getUrl());
    }

    // ------------------------------------------------------------------
    // A-005: tamu dialihkan ke halaman masuk, bukan 500
    // ------------------------------------------------------------------

    #[DataProvider('rute')]
    public function test_tamu_dialihkan_ke_halaman_masuk_panel(string $jenis): void
    {
        $url = $this->alamat($jenis);

        $this->buka(null, $url)->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_panduan_tanpa_berkas_tetap_404_bagi_yang_sudah_masuk(): void
    {
        $this->buka($this->aktif('tim', $this->buatTim()), route('pusat-bantuan.unduh-panduan'))
            ->assertNotFound();
    }

    public function test_panduan_dapat_diunduh_semua_peran_bila_berkasnya_ada(): void
    {
        $panduan = config('pusat_bantuan.panduan');
        Storage::disk($panduan['disk'])->put($panduan['path'], '%PDF-panduan');

        foreach (['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $peran) {
            $tim = in_array($peran, ['ketua_tim', 'tim'], true) ? $this->buatTim("Tim {$peran}") : null;

            $this->buka($this->aktif($peran, $tim), route('pusat-bantuan.unduh-panduan'))->assertOk();
        }
    }
}
