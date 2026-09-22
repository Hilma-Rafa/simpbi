<?php

namespace Tests\Feature;

use App\Console\Commands\PindahkanDokumen;
use App\Models\BastMutasiAset;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit C-001: perintah sekali-pakai simpbi:pindah-dokumen memindahkan dokumen
 * lama dari disk public ke disk privat, bekerja dari catatan basis data.
 */
class PindahDokumenTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Tim $tim;

    private User $pengaju;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->tim = $this->buatTim();
        $this->pengaju = $this->buatPengguna('tim', $this->tim);
    }

    private function bukti(string $kode, bool $adaAsli = true, bool $adaFootnote = true): PermintaanBarang
    {
        $p = $this->buatPermintaan($this->tim, $this->pengaju, [
            ['barang' => $this->buatBarang(), 'diminta' => 1],
        ], status: 'selesai', tambahan: [
            'kode_permintaan' => $kode,
            'file_bukti_path' => "bukti-permintaan/{$kode}.pdf",
        ]);

        $adaAsli && Storage::disk('public')->put("bukti-permintaan/{$kode}.pdf", "%PDF-asli-{$kode}");
        $adaFootnote && Storage::disk('public')->put("bukti-permintaan/{$kode}-berfootnote.pdf", "%PDF-foot-{$kode}");

        return $p;
    }

    private function bast(string $nomor, bool $ada = true): BastMutasiAset
    {
        $b = $this->buatBast($this->tim, $this->buatTim('Tim Lain'), $this->pengaju, tambahan: [
            'nomor_bast'     => $nomor,
            'file_bast_path' => "bast-mutasi/{$nomor}.pdf",
        ]);

        $ada && Storage::disk('public')->put("bast-mutasi/{$nomor}.pdf", "%PDF-bast-{$nomor}");

        return $b;
    }

    private function siapkan(): void
    {
        $this->bukti('PB-A');
        $this->bukti('PB-B');
        $this->bast('BAST-A');
    }

    public function test_tanpa_opsi_hanya_menampilkan_rencana(): void
    {
        $this->siapkan();

        $this->artisan('simpbi:pindah-dokumen')
            ->expectsOutputToContain('Rencana pemindahan')
            ->expectsOutputToContain('akan disalin bukti-permintaan/PB-A.pdf')
            ->expectsOutputToContain('akan disalin bukti-permintaan/PB-A-berfootnote.pdf')
            ->expectsOutputToContain('akan disalin bast-mutasi/BAST-A.pdf')
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('local')->allFiles(), 'Rencana tidak boleh mengubah apa pun.');
        $this->assertCount(5, Storage::disk('public')->allFiles());
    }

    public function test_jalankan_menyalin_dan_memverifikasi_tanpa_menghapus_asli(): void
    {
        $this->siapkan();

        $this->artisan('simpbi:pindah-dokumen', ['--jalankan' => true])
            ->expectsOutputToContain('terverifikasi')
            ->assertSuccessful();

        foreach (Storage::disk('public')->allFiles() as $berkas) {
            Storage::disk('local')->assertExists($berkas);
            $this->assertSame(Storage::disk('public')->get($berkas), Storage::disk('local')->get($berkas));
        }

        $this->assertCount(5, Storage::disk('public')->allFiles(), 'Asli tetap ada tanpa --hapus-asli.');
    }

    public function test_hapus_asli_tanpa_jalankan_ditolak(): void
    {
        $this->siapkan();

        $this->artisan('simpbi:pindah-dokumen', ['--hapus-asli' => true])
            ->expectsOutputToContain('hanya dapat dipakai bersama --jalankan')
            ->assertFailed();

        $this->assertCount(5, Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_hapus_asli_menghapus_hanya_setelah_verifikasi_berhasil(): void
    {
        $this->siapkan();

        $this->artisan('simpbi:pindah-dokumen', ['--jalankan' => true, '--hapus-asli' => true])
            ->expectsOutputToContain('dihapus asli')
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertCount(5, Storage::disk('local')->allFiles());
        $this->assertSame('%PDF-asli-PB-A', Storage::disk('local')->get('bukti-permintaan/PB-A.pdf'));
    }

    public function test_dijalankan_berulang_kali_aman(): void
    {
        $this->siapkan();

        $this->artisan('simpbi:pindah-dokumen', ['--jalankan' => true, '--hapus-asli' => true])->assertSuccessful();
        $this->artisan('simpbi:pindah-dokumen', ['--jalankan' => true, '--hapus-asli' => true])
            ->expectsOutputToContain('sudah ada')
            ->assertSuccessful();
        $this->artisan('simpbi:pindah-dokumen')->assertSuccessful();

        $this->assertCount(5, Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_berkas_yang_hilang_dilaporkan_tanpa_menghentikan_perintah(): void
    {
        $this->bukti('PB-A');
        $this->bukti('PB-HILANG', adaAsli: false, adaFootnote: false);
        $this->bast('BAST-HILANG', ada: false);
        $this->bast('BAST-ADA');

        $this->artisan('simpbi:pindah-dokumen', ['--jalankan' => true, '--hapus-asli' => true])
            ->expectsOutputToContain('bukti-permintaan/PB-HILANG.pdf (tidak ada di sumber maupun di tujuan)')
            ->expectsOutputToContain('bast-mutasi/BAST-HILANG.pdf (tidak ada di sumber maupun di tujuan)')
            ->assertSuccessful();

        // Yang ada tetap dipindahkan.
        Storage::disk('local')->assertExists('bukti-permintaan/PB-A.pdf');
        Storage::disk('local')->assertExists('bast-mutasi/BAST-ADA.pdf');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_berkas_di_tujuan_yang_berbeda_tidak_ditimpa_dan_asli_tidak_dihapus(): void
    {
        $this->bukti('PB-A');
        Storage::disk('local')->put('bukti-permintaan/PB-A.pdf', 'ISI-LAIN');

        $this->artisan('simpbi:pindah-dokumen', ['--jalankan' => true, '--hapus-asli' => true])
            ->expectsOutputToContain('BERBEDA')
            ->assertFailed();

        $this->assertSame('ISI-LAIN', Storage::disk('local')->get('bukti-permintaan/PB-A.pdf'), 'Tidak ditimpa.');
        Storage::disk('public')->assertExists('bukti-permintaan/PB-A.pdf');
        // Berkas lain pada permintaan yang sama tetap diproses.
        Storage::disk('local')->assertExists('bukti-permintaan/PB-A-berfootnote.pdf');
    }

    public function test_hanya_dokumen_yang_tercatat_di_basis_data_yang_dipindahkan(): void
    {
        $this->bukti('PB-A');
        Storage::disk('public')->put('bukti-permintaan/LIAR.pdf', 'tak tercatat');
        Storage::disk('public')->put('gambar/logo.png', 'aset publik lain');

        $this->artisan('simpbi:pindah-dokumen', ['--jalankan' => true, '--hapus-asli' => true])->assertSuccessful();

        Storage::disk('local')->assertMissing('bukti-permintaan/LIAR.pdf');
        Storage::disk('public')->assertExists('bukti-permintaan/LIAR.pdf');
        Storage::disk('public')->assertExists('gambar/logo.png');
    }

    public function test_salinan_yang_gagal_verifikasi_dibuang_dan_asli_tidak_disentuh(): void
    {
        $asal = Mockery::mock(Filesystem::class);
        $asal->shouldReceive('exists')->with('x.pdf')->andReturn(true);
        $asal->shouldReceive('readStream')->with('x.pdf')->andReturnUsing(fn () => $this->aliran('isi-utuh'));
        $asal->shouldNotReceive('delete');

        $tujuan = Mockery::mock(Filesystem::class);
        $tujuan->shouldReceive('exists')->with('x.pdf')->andReturn(false);
        $tujuan->shouldReceive('writeStream')->once()->andReturn(true);
        // Salinan terpotong: ukuran dan sha256 tidak cocok.
        $tujuan->shouldReceive('readStream')->with('x.pdf')->andReturnUsing(fn () => $this->aliran('isi'));
        $tujuan->shouldReceive('delete')->once()->with('x.pdf');

        $perintah = new PindahkanDokumen;
        $perintah->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $hitung = ['disalin' => 0, 'rencana' => 0, 'sudah' => 0, 'hilang' => 0, 'berbeda' => 0, 'gagal' => 0, 'dihapus' => 0];

        $proses = new \ReflectionMethod($perintah, 'proses');
        $proses->setAccessible(true);
        $proses->invokeArgs($perintah, ['x.pdf', $asal, $tujuan, true, true, &$hitung]);

        $this->assertSame(1, $hitung['gagal']);
        $this->assertSame(0, $hitung['disalin']);
        $this->assertSame(0, $hitung['dihapus']);
    }

    /** @return resource */
    private function aliran(string $isi)
    {
        $s = fopen('php://memory', 'r+');
        fwrite($s, $isi);
        rewind($s);

        return $s;
    }
}
