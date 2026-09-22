<?php

namespace Tests\Feature;

use App\Filament\Resources\PermintaanBarangs\Pages\ListPermintaanBarangs;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\PermintaanBarang;
use App\Models\User;
use App\Support\TandaTangan;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-015b: konfirmasi pada akun peran Tim.
 *
 * Akun peran Tim adalah akun bersama satu tim dan tidak punya NIP. Pada
 * langkah yang meminta ketik NIP, akun itu mengetik NAMA TIM-nya; peran lain
 * tetap mengetik NIP. Tanda tangan dan nama pada dokumen tetap milik Ketua
 * Tim, dibubuhkan otomatis siapa pun yang mengonfirmasi.
 */
class KonfirmasiAkunTimTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function pngSah(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    private function aksiTahap(string $nama, PermintaanBarang $permintaan): array
    {
        return [TestAction::make('detail')->table($permintaan), TestAction::make($nama)];
    }

    /** Tim, Ketua Tim bertanda tangan, dan permintaan yang sudah siap diambil. */
    private function siapDiambil(bool $ketuaBertandaTangan = true, string $namaTim = 'Statistik Sosial'): array
    {
        $tim   = $this->buatTim($namaTim);
        $ketua = $this->buatPengguna('ketua_tim', $tim);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        if ($ketuaBertandaTangan) {
            TandaTangan::simpan($ketua, $this->pngSah());
        }

        $permintaan = $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'siap_diambil',
        );

        return [$permintaan, $ketua->refresh(), $this->buatPengguna('tim', $tim)];
    }

    private function konfirmasi(User $pengguna, PermintaanBarang $permintaan, string $isian)
    {
        auth()->forgetGuards();
        $this->actingAs($pengguna->refresh());

        return Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('konfirmasi', $permintaan), data: [
                'sesuai'         => 'ya',
                'konfirmasi_nip' => $isian,
            ]);
    }

    // =====================================================================
    // AKUN TIM MENGETIK NAMA TIM
    // =====================================================================

    public function test_akun_tim_mengetik_nama_timnya_lalu_konfirmasi_berhasil(): void
    {
        [$permintaan, , $akunTim] = $this->siapDiambil();

        $this->konfirmasi($akunTim, $permintaan, 'Statistik Sosial')->assertHasNoActionErrors();

        $this->assertSame('menunggu_pengesahan', $permintaan->refresh()->status);
    }

    public function test_huruf_besar_kecil_dan_spasi_diabaikan(): void
    {
        [$permintaan, , $akunTim] = $this->siapDiambil();

        $this->konfirmasi($akunTim, $permintaan, "  STATISTIK \t  sosial  ")->assertHasNoActionErrors();

        $this->assertSame('menunggu_pengesahan', $permintaan->refresh()->status);
    }

    public function test_nama_tim_lain_nip_dan_nama_akun_ditolak_di_server(): void
    {
        [$permintaan, $ketua, $akunTim] = $this->siapDiambil();
        $this->buatTim('Sub Bagian Umum');

        foreach (['Sub Bagian Umum', $akunTim->nip, $ketua->nip, $akunTim->name, 'Statistik', ''] as $salah) {
            $this->konfirmasi($akunTim, $permintaan, (string) $salah)->assertHasActionErrors(['konfirmasi_nip']);

            $this->assertSame('siap_diambil', $permintaan->refresh()->status, 'Isian "' . $salah . '" tidak boleh meloloskan konfirmasi.');
        }
    }

    // =====================================================================
    // PERAN LAIN TETAP MEMAKAI NIP
    // =====================================================================

    public function test_ketua_tim_tetap_mengetik_nip_dan_nama_tim_ditolak(): void
    {
        [$permintaan, $ketua] = $this->siapDiambil();

        $this->konfirmasi($ketua, $permintaan, 'Statistik Sosial')->assertHasActionErrors(['konfirmasi_nip']);
        $this->assertSame('siap_diambil', $permintaan->refresh()->status);

        $this->konfirmasi($ketua, $permintaan, $ketua->nip)->assertHasNoActionErrors();
        $this->assertSame('menunggu_pengesahan', $permintaan->refresh()->status);
    }

    public function test_petugas_gudang_dan_kasubbag_tetap_mengetik_nip(): void
    {
        $tim = $this->buatTim();
        $permintaan = $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'siap_diproses',
        );
        $gudang = $this->buatPengguna('petugas_gudang');
        TandaTangan::simpan($gudang, $this->pngSah());

        auth()->forgetGuards();
        $this->actingAs($gudang->refresh());
        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('siapkan', $permintaan), data: ['konfirmasi_nip' => $tim->nama_tim])
            ->assertHasActionErrors(['konfirmasi_nip']);
        $this->assertSame('siap_diproses', $permintaan->refresh()->status);

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('siapkan', $permintaan), data: ['konfirmasi_nip' => $gudang->nip])
            ->assertHasNoActionErrors();
        $this->assertSame('siap_diambil', $permintaan->refresh()->status);
    }

    // =====================================================================
    // AKUN TIM TANPA TIM
    // =====================================================================

    /**
     * Akun peran Tim tanpa tim tidak akan pernah melihat permintaan pada
     * daftar, sehingga aturannya dijalankan langsung: hasilnya penolakan
     * dengan pesan yang jelas, bukan galat.
     */
    public function test_akun_tim_tanpa_tim_ditolak_dengan_pesan_jelas(): void
    {
        $tanpaTim = $this->buatPengguna('tim', null);
        auth()->setUser($tanpaTim);

        $metode = new \ReflectionMethod(PermintaanBarangResource::class, 'bidangKonfirmasiNip');
        $metode->setAccessible(true);
        $bidang = $metode->invoke(null);

        $pesan = [];
        foreach ($bidang->getValidationRules() as $aturan) {
            if ($aturan instanceof \Closure) {
                $aturan('konfirmasi_nip', 'Statistik Sosial', function (string $galat) use (&$pesan): void {
                    $pesan[] = $galat;
                });
            }
        }

        $this->assertCount(1, $pesan);
        $this->assertStringContainsString('belum terhubung ke tim kerja', $pesan[0]);
        $this->assertSame('Ketik nama tim Anda untuk konfirmasi', $bidang->getLabel());
    }

    public function test_label_akun_tim_nama_tim_dan_label_peran_lain_tidak_berubah(): void
    {
        $tim = $this->buatTim('Statistik Sosial');
        $ambilLabel = function (User $pengguna): string {
            auth()->setUser($pengguna);
            $metode = new \ReflectionMethod(PermintaanBarangResource::class, 'bidangKonfirmasiNip');
            $metode->setAccessible(true);

            return $metode->invoke(null)->getLabel();
        };

        $this->assertSame('Ketik nama tim Anda untuk konfirmasi', $ambilLabel($this->buatPengguna('tim', $tim)));
        $this->assertSame('Ketik NIP Anda untuk mengonfirmasi', $ambilLabel($this->buatPengguna('ketua_tim', $tim)));
        $this->assertSame('Ketik NIP Anda untuk mengonfirmasi', $ambilLabel($this->buatPengguna('kasubbag')));
        $this->assertSame(
            'Ketik nama lengkap Anda untuk mengonfirmasi',
            $ambilLabel($this->buatPengguna('petugas_gudang', null, ['nip' => null])),
            'Peran lain tanpa NIP tetap memakai nama lengkap seperti sebelumnya.',
        );
    }

    // =====================================================================
    // TANDA TANGAN DAN NAMA PADA DOKUMEN TETAP MILIK KETUA TIM
    // =====================================================================

    public function test_ketua_tanpa_tanda_tangan_menahan_konfirmasi_akun_tim_dengan_pesan_yang_sama(): void
    {
        [$permintaan, , $akunTim] = $this->siapDiambil(ketuaBertandaTangan: false);

        $this->konfirmasi($akunTim, $permintaan, 'Statistik Sosial')->assertHasNoActionErrors();

        $this->assertSame('siap_diambil', $permintaan->refresh()->status);
        Notification::assertNotified(
            Notification::make()
                ->title('Tanda tangan belum tersedia')
                ->body(User::find($permintaan->tim->ketua_tim_id)->name . ' belum menyimpan tanda tangan, sehingga dokumen bukti '
                    . 'tidak akan dapat diterbitkan. Mintalah beliau melengkapinya melalui pelengkapan akun.')
                ->danger()
                ->persistent()
        );
    }

    /** Dokumen dibentuk dari data yang sama, siapa pun yang mengonfirmasi penerimaan. */
    public function test_dokumen_yang_terbit_identik_baik_dikonfirmasi_akun_tim_maupun_ketua_tim(): void
    {
        $tim    = $this->buatTim('Statistik Sosial');
        $ketua  = $this->buatPengguna('ketua_tim', $tim);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();
        TandaTangan::simpan($ketua, $this->pngSah());
        $akunTim = $this->buatPengguna('tim', $tim);
        $gudang  = $this->buatPengguna('petugas_gudang');
        TandaTangan::simpan($gudang, $this->pngSah());
        $kasubbag = $this->buatPengguna('kasubbag');

        $tangkapan = [];
        View::composer('pdf.bukti-permintaan', function ($tampilan) use (&$tangkapan): void {
            $tangkapan[] = $tampilan->getData();
        });

        $jalankan = function (User $pengonfirmasi, string $isian) use ($tim, $akunTim, $gudang, $kasubbag): void {
            $permintaan = $this->buatPermintaan(
                $tim,
                $akunTim,
                [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
                status: 'siap_diproses',
            );

            foreach ([
                [$gudang, 'siapkan', ['konfirmasi_nip' => $gudang->nip]],
                [$pengonfirmasi, 'konfirmasi', ['sesuai' => 'ya', 'konfirmasi_nip' => $isian]],
                [$kasubbag, 'sahkan', ['catatan' => 'Sah', 'konfirmasi_nip' => $kasubbag->nip]],
            ] as [$pelaku, $aksi, $data]) {
                auth()->forgetGuards();
                $this->flushSession();
                $this->actingAs($pelaku->refresh());
                Livewire::test(ListPermintaanBarangs::class)->callAction($this->aksiTahap($aksi, $permintaan), data: $data);
            }

            $this->assertSame('selesai', $permintaan->refresh()->status);
        };

        $jalankan($ketua, $ketua->nip);          // dokumen 1: dikonfirmasi Ketua Tim
        $jalankan($akunTim, 'statistik sosial');  // dokumen 2: dikonfirmasi akun Tim

        // Tiap dokumen membentuk dua lembar; ambil lembar pertama masing-masing.
        $this->assertCount(4, $tangkapan);
        $kunci = ['penyerah', 'ttdPenyerah', 'penerima', 'ttdPenerima', 'pengesah'];
        $satu  = array_intersect_key($tangkapan[0], array_flip($kunci));
        $dua   = array_intersect_key($tangkapan[2], array_flip($kunci));

        $this->assertSame($satu, $dua, 'Nama dan tanda tangan pada dokumen harus identik.');
        $this->assertSame($ketua->name, $dua['penerima'], 'Penerima pada dokumen adalah Ketua Tim, bukan akun Tim.');
        $this->assertSame(TandaTangan::dataUri($ketua), $dua['ttdPenerima']);
        $this->assertNotNull($dua['ttdPenerima']);
        $this->assertNotSame($akunTim->name, $dua['penerima']);
        $this->assertNotSame($akunTim->name, $dua['penyerah']);
    }
}
