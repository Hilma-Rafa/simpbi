<?php

namespace Tests\Feature;

use App\Jobs\KirimPesanWhatsApp;
use App\Models\Notifikasi;
use App\Models\User;
use App\Services\WhatsApp\PengirimanGagal;
use App\Services\WhatsApp\PengirimFonnte;
use App\Services\WhatsApp\PengirimWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian pelaksana pengiriman lewat layanan Fonnte (UC-23).
 *
 * Seluruh jawaban Fonnte disimulasikan, sehingga pengujian ini tidak
 * menghubungi jaringan, tidak memakai kuota, dan tidak memerlukan token.
 *
 * Yang paling perlu dijaga di sini adalah pembacaan keberhasilan: Fonnte
 * menjawab HTTP 200 bahkan ketika pengiriman ditolak, sehingga pelaksana yang
 * hanya memeriksa kode HTTP akan menandai pesan sebagai terkirim padahal tidak
 * pernah sampai — kekeliruan yang tidak akan pernah dilaporkan siapa pun.
 */
class PengirimanFonnteTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function pengirim(): PengirimFonnte
    {
        return new PengirimFonnte('token-uji', 5);
    }

    private function notifikasi(?User $penerima = null, array $tambahan = []): Notifikasi
    {
        $penerima ??= $this->buatPengguna('ketua_tim', $this->buatTim(), ['no_hp' => '6281294780409']);

        return Notifikasi::create([
            'user_id'      => $penerima->id,
            'judul'        => 'Permintaan menunggu persetujuan Anda',
            'pesan'        => 'PB-2026-0001 menunggu persetujuan Ketua Tim.',
            'tipe'         => 'permintaan',
            'channel'      => 'whatsapp',
            'status_kirim' => 'pending',
            ...$tambahan,
        ]);
    }

    /** Jawaban berhasil sebagaimana dicontohkan dokumentasi Fonnte. */
    private function jawabanBerhasil(): array
    {
        return [
            'detail'    => 'success! message in queue',
            'id'        => ['80367170'],
            'process'   => 'pending',
            'requestid' => 2937124,
            'status'    => true,
            'target'    => ['6281294780409'],
        ];
    }

    // =====================================================================
    // KESIAPAN
    // =====================================================================

    public function test_belum_siap_ketika_token_kosong(): void
    {
        config(['whatsapp.fonnte' => ['token' => null, 'batas_detik' => 15]]);
        $this->assertFalse((new PengirimFonnte())->siap());

        config(['whatsapp.fonnte' => ['token' => 'token-uji', 'batas_detik' => 15]]);
        $this->assertTrue((new PengirimFonnte())->siap());
    }

    public function test_namanya_fonnte(): void
    {
        $this->assertSame('fonnte', $this->pengirim()->nama());
    }

    public function test_dipilih_lewat_berkas_konfigurasi(): void
    {
        config(['whatsapp.driver' => 'fonnte', 'whatsapp.fonnte.token' => 'token-uji']);

        // Pengikatan dibuat ulang agar cabang match dibaca lagi
        $this->app->forgetInstance(PengirimWhatsApp::class);

        $this->assertInstanceOf(PengirimFonnte::class, app(PengirimWhatsApp::class));
    }

    // =====================================================================
    // BENTUK PERMINTAAN
    // =====================================================================

    public function test_permintaan_sesuai_dokumentasi_fonnte(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response($this->jawabanBerhasil(), 200)]);

        $penanda = $this->pengirim()->kirim('6281294780409', 'Halo dunia');

        $this->assertSame('80367170', $penanda, 'Penanda pesan diambil dari elemen pertama larik id.');

        Http::assertSent(function (Request $r) {
            return $r->method() === 'POST'
                && $r->url() === 'https://api.fonnte.com/send'
                // Token dikirim apa adanya, TANPA awalan Bearer
                && $r->header('Authorization')[0] === 'token-uji'
                && $r['target'] === '6281294780409'
                && $r['message'] === 'Halo dunia';
        });
    }

    public function test_token_tidak_dikirim_dengan_awalan_bearer(): void
    {
        Http::fake(['*' => Http::response($this->jawabanBerhasil(), 200)]);

        $this->pengirim()->kirim('6281294780409', 'Halo');

        Http::assertSent(fn (Request $r) => ! str_contains($r->header('Authorization')[0], 'Bearer'));
    }

    // =====================================================================
    // PEMBACAAN KEBERHASILAN
    // =====================================================================

    /**
     * Inti perlindungan pelaksana ini: HTTP 200 tidak berarti terkirim.
     */
    public function test_jawaban_200_dengan_status_salah_tetap_dianggap_gagal(): void
    {
        Http::fake(['*' => Http::response([
            'status'    => false,
            'reason'    => 'token invalid',
            'requestid' => 2937124,
        ], 200)]);

        $this->expectException(PengirimanGagal::class);
        $this->expectExceptionMessage('WHATSAPP_FONNTE_TOKEN');

        $this->pengirim()->kirim('6281294780409', 'Halo');
    }

    public function test_kolom_status_berhuruf_besar_juga_terbaca(): void
    {
        // Dokumentasi Fonnte menuliskannya sebagai "Status" pada satu contoh
        Http::fake(['*' => Http::response(['Status' => false, 'reason' => 'token invalid'], 200)]);

        $this->expectException(PengirimanGagal::class);

        $this->pengirim()->kirim('6281294780409', 'Halo');
    }

    public function test_jawaban_tanpa_kolom_status_dianggap_gagal(): void
    {
        // Lebih baik menahan satu pesan yang sebenarnya berhasil daripada
        // menandai terkirim sesuatu yang tidak pernah sampai.
        Http::fake(['*' => Http::response(['detail' => 'entah'], 200)]);

        $this->expectException(PengirimanGagal::class);

        $this->pengirim()->kirim('6281294780409', 'Halo');
    }

    public function test_status_berupa_teks_true_tetap_dianggap_berhasil(): void
    {
        Http::fake(['*' => Http::response(['status' => 'true', 'id' => ['99']], 200)]);

        $this->assertSame('99', $this->pengirim()->kirim('6281294780409', 'Halo'));
    }

    public function test_jawaban_berhasil_tanpa_id_tidak_menggagalkan_pengiriman(): void
    {
        Http::fake(['*' => Http::response(['status' => true, 'detail' => 'success! message in queue'], 200)]);

        $this->assertNull($this->pengirim()->kirim('6281294780409', 'Halo'));
    }

    // =====================================================================
    // PENERJEMAHAN KEGAGALAN
    // =====================================================================

    #[DataProvider('alasanPenolakan')]
    public function test_alasan_penolakan_diterjemahkan_menjadi_tindakan(string $alasan, string $petunjuk): void
    {
        Http::fake(['*' => Http::response(['status' => false, 'reason' => $alasan], 200)]);

        try {
            $this->pengirim()->kirim('6281294780409', 'Halo');
            $this->fail("Alasan \"{$alasan}\" seharusnya melempar PengirimanGagal.");
        } catch (PengirimanGagal $e) {
            $this->assertStringContainsString($petunjuk, $e->getMessage());
            $this->assertStringContainsString($alasan, $e->getMessage(), 'Alasan asli harus ikut tercatat untuk penelusuran.');
        }
    }

    /** @return array<string, array{string, string}> */
    public static function alasanPenolakan(): array
    {
        return [
            'token salah'      => ['token invalid', 'WHATSAPP_FONNTE_TOKEN'],
            'kuota habis'      => ['insufficient quota', 'Isi ulang paket'],
            'nomor ditolak'    => ['target invalid', 'Periksa nomor WhatsApp pengguna'],
            'perangkat putus'  => ['device disconnected', 'Pindai ulang kode QR'],
            'isian kurang'     => ['input invalid', 'isian tidak lengkap'],
            'alasan tak dikenal' => ['something odd', 'Fonnte menolak pengiriman pesan'],
        ];
    }

    public function test_galat_peladen_dilaporkan_beserta_kodenya(): void
    {
        Http::fake(['*' => Http::response('service unavailable', 503)]);

        try {
            $this->pengirim()->kirim('6281294780409', 'Halo');
            $this->fail('HTTP 503 seharusnya melempar PengirimanGagal.');
        } catch (PengirimanGagal $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
        }
    }

    public function test_layanan_tidak_terjangkau_dilaporkan_apa_adanya(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Connection timed out')]);

        $this->expectException(PengirimanGagal::class);
        $this->expectExceptionMessage('tidak dapat dihubungi');

        $this->pengirim()->kirim('6281294780409', 'Halo');
    }

    // =====================================================================
    // LEWAT JOB
    // =====================================================================

    public function test_job_menandai_terkirim_beserta_penanda_fonnte(): void
    {
        Http::fake(['*' => Http::response($this->jawabanBerhasil(), 200)]);

        $notifikasi = $this->notifikasi();
        (new KirimPesanWhatsApp($notifikasi->id))->handle($this->pengirim());

        $notifikasi->refresh();
        $this->assertSame('terkirim', $notifikasi->status_kirim);
        $this->assertNotNull($notifikasi->dikirim_at);
        $this->assertStringContainsString('80367170', $notifikasi->error_message);
    }

    public function test_penolakan_fonnte_ditandai_gagal_tanpa_menjatuhkan_aksi_pengguna(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(['*' => Http::response(['status' => false, 'reason' => 'insufficient quota'], 200)]);

        $notifikasi = $this->notifikasi();
        (new KirimPesanWhatsApp($notifikasi->id))->handle($this->pengirim());

        $notifikasi->refresh();
        $this->assertSame('gagal', $notifikasi->status_kirim);
        $this->assertStringContainsString('Isi ulang paket', $notifikasi->error_message);
    }

    public function test_token_kosong_ditandai_gagal_tanpa_menghubungi_fonnte(): void
    {
        Http::fake();
        config(['whatsapp.fonnte' => ['token' => null, 'batas_detik' => 15]]);

        $notifikasi = $this->notifikasi();
        (new KirimPesanWhatsApp($notifikasi->id))->handle(new PengirimFonnte());

        Http::assertNothingSent();
        $notifikasi->refresh();
        $this->assertSame('gagal', $notifikasi->status_kirim);
        $this->assertStringContainsString('fonnte', $notifikasi->error_message);
        $this->assertStringContainsString('belum terkonfigurasi', $notifikasi->error_message);
    }
}
