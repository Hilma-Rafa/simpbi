<?php

namespace Tests\Feature;

use App\Models\BastMutasiAset;
use App\Services\MutasiAsetService;
use App\Support\TandaTangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Tanda tangan pihak penyerah dan penerima pada BAST mutasi aset diambil dari
 * akun masing-masing — bukan digambar ulang saat proses. Pihak penyerah adalah
 * Ketua Tim kerja asal; pihak penerima adalah Ketua Tim kerja tujuan yang
 * mengkonfirmasi penerimaan. Desain, tata letak, dan e-TTD Kasubbag tidak
 * berubah; ruang tanda tangan yang memang sudah tersedia kini terisi dari
 * sumber yang benar.
 */
class BastTandaTanganTersimpanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function pngSah(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    // =====================================================================
    // TEMPLATE DOKUMEN — RUANG TANDA TANGAN TERISI DARI SIGNATURE TERSIMPAN
    // =====================================================================

    public function test_dokumen_menampilkan_nama_dan_tanda_tangan_dari_akun_bukan_teks_operator(): void
    {
        $operator = $this->buatPengguna('petugas_gudang'); // pihak_penyerah = nama operator
        $bast = $this->buatBast($this->buatTim('Asal'), $this->buatTim('Tujuan'), $operator)
            ->load(['timAsal', 'timTujuan', 'aset.kategori', 'disahkanOleh']);

        $html = view('pdf.bast-mutasi', [
            'bast'         => $bast,
            'qr'           => '',
            'qrFootnote'   => '',
            'logo'         => '',
            'namaPenyerah' => 'Ketua Asal Uji',
            'namaPenerima' => 'Ketua Tujuan Uji',
            'ttdPenyerah'  => 'data:image/png;base64,PENYERAH',
            'ttdPenerima'  => 'data:image/png;base64,PENERIMA',
        ])->render();

        $this->assertStringContainsString('gambar-ttd', $html);
        $this->assertStringContainsString('data:image/png;base64,PENYERAH', $html);
        $this->assertStringContainsString('data:image/png;base64,PENERIMA', $html);

        // Nama berasal dari akun Ketua Tim, bukan dari kolom yang diketik operator.
        $this->assertStringContainsString('Ketua Asal Uji', $html);
        $this->assertStringContainsString('Ketua Tujuan Uji', $html);
        $this->assertStringNotContainsString($operator->name, $html);
    }

    public function test_dokumen_tidak_menampilkan_tanda_tangan_penerima_sebelum_konfirmasi(): void
    {
        $bast = $this->buatBast($this->buatTim('Asal'), $this->buatTim('Tujuan'), $this->buatPengguna('petugas_gudang'))
            ->load(['timAsal', 'timTujuan', 'aset.kategori', 'disahkanOleh']);

        $html = view('pdf.bast-mutasi', [
            'bast'         => $bast,
            'qr'           => '',
            'qrFootnote'   => '',
            'logo'         => '',
            'namaPenyerah' => 'Ketua Asal Uji',
            'namaPenerima' => 'Ketua Tujuan Uji',
            'ttdPenyerah'  => 'data:image/png;base64,PENYERAH',
            'ttdPenerima'  => '',
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,PENYERAH', $html);
        $this->assertStringNotContainsString('data:image/png;base64,PENERIMA', $html);
    }

    // =====================================================================
    // PIPELINE — SUMBER SIGNATURE DARI AKUN KETUA TIM MASING-MASING
    // =====================================================================

    public function test_konfirmasi_membentuk_ulang_dokumen_dengan_signature_penerima(): void
    {
        $asal   = $this->buatTim('Statistik Sosial');
        $tujuan = $this->buatTim('Statistik Distribusi');

        $ketuaAsal = $this->buatPengguna('ketua_tim', $asal);
        TandaTangan::simpan($ketuaAsal, $this->pngSah());
        $asal->forceFill(['ketua_tim_id' => $ketuaAsal->id])->save();

        $ketuaTujuan = $this->buatPengguna('ketua_tim', $tujuan);
        TandaTangan::simpan($ketuaTujuan, $this->pngSah());
        $tujuan->forceFill(['ketua_tim_id' => $ketuaTujuan->id])->save();

        $bast = $this->buatBast($asal, $tujuan, $this->buatPengguna('petugas_gudang'), status: 'menunggu_pengesahan');

        app(MutasiAsetService::class)->sahkan($bast, $this->buatPengguna('kasubbag')->id);
        app(MutasiAsetService::class)->konfirmasi($bast->refresh(), $ketuaTujuan->id);

        $bast->refresh();

        $this->assertSame('selesai_administratif', $bast->status);
        $this->assertNotNull($bast->file_bast_path);
        $this->assertTrue(
            Storage::disk('local')->exists($bast->file_bast_path),
            'Dokumen BAST harus dibentuk ulang setelah konfirmasi penerima.',
        );
    }
}
