<?php

namespace Tests\Feature;

use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use App\Services\DokumenPermintaanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Penelusuran dokumen bukti lewat pemindaian kode QR.
 *
 * Dua lembar diterbitkan untuk satu permintaan: berkas asli, dan lembar
 * berfootnote yang muncul ketika kode QR pada berkas asli dipindai. Kode QR
 * pada lembar berfootnote mengembalikan pemeriksa ke berkas asli, sehingga
 * penelusurannya berakhir pada dokumen apa adanya, bukan berputar pada lembar
 * yang sudah bertanda.
 *
 * Kedua alamatnya terbuka tanpa autentikasi — dokumen bukti beredar ke luar
 * sistem dan justru itulah gunanya dapat diperiksa — sehingga yang dijaga
 * pengujian ini adalah bahwa tokennya benar-benar menjadi kuncinya.
 */
class PemindaianBuktiTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    /** Permintaan yang sudah disahkan, beserta kedua berkas dokumennya. */
    private function permintaanDisahkan(): PermintaanBarang
    {
        $tim   = $this->buatTim();
        $ketua = $this->buatPengguna('ketua_tim', $tim);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        $permintaan = $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(), 'diminta' => 2]],
            status: 'selesai',
            tambahan: ['pengesahan_at' => now()],
        );

        RiwayatPersetujuan::create([
            'permintaan_id' => $permintaan->id,
            'tahap'         => 'pengesahan',
            'pelaksana_id'  => $this->buatPengguna('kasubbag')->id,
            'keputusan'     => 'selesai',
            'waktu'         => now(),
        ]);

        $lintasan = app(DokumenPermintaanService::class)->buat($permintaan);
        $permintaan->update(['file_bukti_path' => $lintasan]);

        return $permintaan->refresh();
    }

    public function test_kedua_berkas_terbentuk_sekaligus(): void
    {
        $permintaan = $this->permintaanDisahkan();

        Storage::disk('local')->assertExists($permintaan->file_bukti_path);
        Storage::disk('local')->assertExists(
            DokumenPermintaanService::lintasanBerfootnote($permintaan)
        );
    }

    public function test_memindai_menampilkan_lembar_berfootnote(): void
    {
        $permintaan = $this->permintaanDisahkan();

        $respons = $this->get(route('bukti.pindai', ['token' => $permintaan->qr_token]));

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', $respons->headers->get('Content-Disposition'));
    }

    public function test_memindai_kode_pada_pita_mengembalikan_berkas_asli(): void
    {
        $permintaan = $this->permintaanDisahkan();

        $respons = $this->get(route('bukti.asli', ['token' => $permintaan->qr_token]));

        $respons->assertOk();
        $this->assertSame(
            Storage::disk('local')->get($permintaan->file_bukti_path),
            $respons->getContent(),
            'Alamat "asli" harus mengirim berkas tanpa pita, bukan lembar berfootnote.',
        );
    }

    /** Kedua lembar tidak boleh berisi bita yang sama persis. */
    public function test_lembar_berfootnote_berbeda_dari_berkas_asli(): void
    {
        $permintaan = $this->permintaanDisahkan();

        $this->assertNotSame(
            Storage::disk('local')->get($permintaan->file_bukti_path),
            Storage::disk('local')->get(DokumenPermintaanService::lintasanBerfootnote($permintaan)),
        );
    }

    public function test_token_yang_tidak_dikenal_ditolak(): void
    {
        $this->permintaanDisahkan();

        $this->get(route('bukti.pindai', ['token' => 'token-karangan']))->assertNotFound();
        $this->get(route('bukti.asli', ['token' => 'token-karangan']))->assertNotFound();
    }

    /**
     * Permintaan yang belum disahkan tidak punya dokumen resmi, sehingga
     * tokennya pun belum boleh membuka apa pun — termasuk bila tokennya sudah
     * terlanjur terbentuk pada tahap sebelumnya.
     */
    public function test_permintaan_yang_belum_disahkan_tidak_dapat_dipindai(): void
    {
        $tim = $this->buatTim();

        $permintaan = $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(), 'diminta' => 2]],
            status: 'menunggu_kasubbag',
        );

        $token = app(DokumenPermintaanService::class)->pastikanToken($permintaan);

        $this->get(route('bukti.pindai', ['token' => $token]))->assertNotFound();
        $this->get(route('bukti.asli', ['token' => $token]))->assertNotFound();
    }

    /**
     * Halaman verifikasi web tetap dipertahankan: ia melayani BAST mutasi aset
     * dan menjadi rujukan bagi pemeriksa yang lebih suka membaca ringkasan
     * ketimbang membuka berkas PDF.
     */
    public function test_halaman_verifikasi_web_tetap_tersedia(): void
    {
        $permintaan = $this->permintaanDisahkan();

        $this->get(route('verifikasi.permintaan', ['token' => $permintaan->qr_token]))
            ->assertOk();
    }
}
