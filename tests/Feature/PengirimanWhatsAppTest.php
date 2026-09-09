<?php

namespace Tests\Feature;

use App\Jobs\KirimPesanWhatsApp;
use App\Models\Notifikasi;
use App\Models\User;
use App\Services\NotifikasiService;
use App\Services\WhatsApp\PengirimanGagal;
use App\Services\WhatsApp\PengirimCatat;
use App\Services\WhatsApp\PengirimOpenWa;
use App\Services\WhatsApp\PengirimWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian kanal notifikasi WhatsApp (UC-23).
 *
 * Seluruh jawaban gerbang disimulasikan, sehingga pengujian ini tidak pernah
 * menghubungi jaringan, tidak memerlukan nomor WhatsApp, dan tidak menanggung
 * risiko pemblokiran nomor.
 *
 * Yang dijaga ada tiga lapis: bentuk permintaan yang dikirim ke gerbang harus
 * sesuai spesifikasinya, kegagalan harus diterjemahkan menjadi keterangan yang
 * dapat ditindaklanjuti petugas, dan kegagalan kanal ini tidak boleh pernah
 * menjatuhkan alur transaksi yang sedang dikerjakan pengguna.
 */
class PengirimanWhatsAppTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const ALAMAT = 'http://gerbang.uji:2785';

    private function pengirim(): PengirimOpenWa
    {
        return new PengirimOpenWa(self::ALAMAT, 'simpbi', 'kunci-rahasia', 5);
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

    // =====================================================================
    // KESIAPAN KONFIGURASI
    // =====================================================================

    /**
     * Kesiapan diuji lewat berkas konfigurasi, bukan lewat argumen pembuat,
     * sebab begitulah keadaannya di lapangan: nilai yang belum diisi pada .env
     * datang sebagai null dan pelaksana harus mengenali dirinya belum siap.
     */
    public function test_pelaksana_belum_siap_ketika_kredensial_tidak_lengkap(): void
    {
        $lengkap = ['alamat' => self::ALAMAT, 'sesi' => 'simpbi', 'kunci_api' => 'kunci', 'batas_detik' => 5];

        config(['whatsapp.openwa' => $lengkap]);
        $this->assertTrue((new PengirimOpenWa())->siap(), 'Konfigurasi lengkap seharusnya siap.');

        foreach (['alamat', 'sesi', 'kunci_api'] as $kunci) {
            config(['whatsapp.openwa' => [...$lengkap, $kunci => null]]);

            $this->assertFalse(
                (new PengirimOpenWa())->siap(),
                "Konfigurasi tanpa {$kunci} seharusnya dianggap belum siap."
            );
        }
    }

    public function test_pelaksana_bawaan_adalah_pencatat_log(): void
    {
        // Bawaan wajib pencatat, supaya pemasangan baru tidak pernah tidak
        // sengaja mengirim pesan ke nomor pegawai sungguhan.
        $this->assertInstanceOf(PengirimCatat::class, app(PengirimWhatsApp::class));
        $this->assertSame('catat', config('whatsapp.driver'));
    }

    // =====================================================================
    // BENTUK PERMINTAAN
    // =====================================================================

    public function test_permintaan_ke_gerbang_sesuai_spesifikasi(): void
    {
        Http::fake([self::ALAMAT . '/*' => Http::response(['messageId' => 'true_ABC', 'timestamp' => 1789], 201)]);

        $penanda = $this->pengirim()->kirim('6281294780409', 'Halo dunia');

        $this->assertSame('true_ABC', $penanda, 'Penanda pesan dari gerbang harus dikembalikan.');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::ALAMAT . '/api/sessions/simpbi/messages/send-text'
            && $r->header('X-API-Key')[0] === 'kunci-rahasia'
            && $r['chatId'] === '6281294780409@c.us'
            && $r['text'] === 'Halo dunia');
    }

    public function test_nama_sesi_yang_mengandung_karakter_khusus_tetap_aman(): void
    {
        Http::fake(['*' => Http::response(['messageId' => 'x'], 201)]);

        (new PengirimOpenWa(self::ALAMAT, 'sesi utama/1', 'kunci', 5))->kirim('628123456789', 'Halo');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/sessions/sesi%20utama%2F1/messages/send-text'));
    }

    // =====================================================================
    // PENERJEMAHAN KEGAGALAN
    // =====================================================================

    /**
     * Keterangan kegagalan tersimpan pada kolom error_message dan dibaca
     * petugas, sehingga harus menyebut tindakan yang perlu diambil.
     *
     * Tiap kode diuji sebagai metode tersendiri, bukan dalam satu perulangan,
     * sebab Http::fake() menumpuk stub di dalam satu metode sehingga jawaban
     * pertama akan dipakai untuk seluruh kode berikutnya.
     */
    #[DataProvider('kodeKegagalan')]
    public function test_kode_jawaban_diterjemahkan_menjadi_keterangan_yang_dapat_ditindaklanjuti(
        int $kode,
        string $petunjuk,
    ): void {
        Http::fake(['*' => Http::response(['message' => 'ditolak'], $kode)]);

        try {
            $this->pengirim()->kirim('6281294780409', 'Halo');
            $this->fail("HTTP {$kode} seharusnya melempar PengirimanGagal.");
        } catch (PengirimanGagal $e) {
            $this->assertStringContainsString($petunjuk, $e->getMessage(), "Keterangan untuk HTTP {$kode}.");
            $this->assertStringContainsString("HTTP {$kode}", $e->getMessage(), 'Kode aslinya harus ikut tercatat untuk penelusuran.');
        }
    }

    /** @return array<string, array{int, string}> */
    public static function kodeKegagalan(): array
    {
        return [
            'permintaan ditolak'      => [400, 'sesi tidak aktif atau nomor tujuan tidak sah'],
            'kunci ditolak'           => [401, 'WHATSAPP_OPENWA_KUNCI'],
            'kunci tidak berwenang'   => [403, 'WHATSAPP_OPENWA_KUNCI'],
            'sesi tidak ada'          => [404, 'tidak ditemukan pada gerbang'],
            'sesi belum tersambung'   => [409, 'Pindai ulang kode QR'],
            'laju dibatasi'           => [429, 'Perbesar jeda antar pesan'],
            'gangguan peladen'        => [500, 'gangguan internal'],
            'layanan tidak tersedia'  => [503, 'gangguan internal'],
        ];
    }

    public function test_gerbang_yang_tidak_terjangkau_dilaporkan_apa_adanya(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

        $this->expectException(PengirimanGagal::class);
        $this->expectExceptionMessage('tidak dapat dihubungi');

        $this->pengirim()->kirim('6281294780409', 'Halo');
    }

    // =====================================================================
    // JOB PENGIRIMAN
    // =====================================================================

    public function test_job_menandai_terkirim_beserta_penanda_gerbang(): void
    {
        Http::fake(['*' => Http::response(['messageId' => 'true_XYZ'], 201)]);

        $notifikasi = $this->notifikasi();
        (new KirimPesanWhatsApp($notifikasi->id))->handle($this->pengirim());

        $notifikasi->refresh();
        $this->assertSame('terkirim', $notifikasi->status_kirim);
        $this->assertNotNull($notifikasi->dikirim_at);
        $this->assertStringContainsString('true_XYZ', $notifikasi->error_message);
    }

    public function test_isi_pesan_memuat_judul_dan_penutup_sistem(): void
    {
        Http::fake(['*' => Http::response(['messageId' => 'x'], 201)]);

        (new KirimPesanWhatsApp($this->notifikasi()->id))->handle($this->pengirim());

        Http::assertSent(function (Request $r) {
            // Penerima belum tentu mengenali nomor pengirim, sehingga pesan
            // harus menyebutkan asalnya.
            return str_contains($r['text'], '*Permintaan menunggu persetujuan Anda*')
                && str_contains($r['text'], 'PB-2026-0001')
                && str_contains($r['text'], 'SIMPBI');
        });
    }

    public function test_job_tidak_mengirim_dua_kali_untuk_baris_yang_sama(): void
    {
        Http::fake();

        $notifikasi = $this->notifikasi(tambahan: ['status_kirim' => 'terkirim', 'dikirim_at' => now()]);

        (new KirimPesanWhatsApp($notifikasi->id))->handle($this->pengirim());

        Http::assertNothingSent();
        $this->assertSame('terkirim', $notifikasi->refresh()->status_kirim);
    }

    public function test_job_melewati_baris_yang_sudah_terhapus(): void
    {
        Http::fake();

        (new KirimPesanWhatsApp(999999))->handle($this->pengirim());

        Http::assertNothingSent();
    }

    public function test_penerima_tanpa_nomor_ditandai_gagal_tanpa_menghubungi_gerbang(): void
    {
        Http::fake();

        $tanpaNomor = $this->buatPengguna('kasubbag');   // no_hp null
        $notifikasi = $this->notifikasi($tanpaNomor);

        (new KirimPesanWhatsApp($notifikasi->id))->handle($this->pengirim());

        Http::assertNothingSent();
        $notifikasi->refresh();
        $this->assertSame('gagal', $notifikasi->status_kirim);
        $this->assertStringContainsString('belum diisi', $notifikasi->error_message);
    }

    public function test_gerbang_yang_belum_terkonfigurasi_ditandai_gagal_dengan_namanya(): void
    {
        Http::fake();

        $notifikasi = $this->notifikasi();
        $belumSiap  = new PengirimOpenWa(null, null, null, 5);

        (new KirimPesanWhatsApp($notifikasi->id))->handle($belumSiap);

        Http::assertNothingSent();
        $notifikasi->refresh();
        $this->assertSame('gagal', $notifikasi->status_kirim);
        $this->assertStringContainsString('openwa', $notifikasi->error_message);
        $this->assertStringContainsString('belum terkonfigurasi', $notifikasi->error_message);
    }

    /**
     * Inti dari perlindungan alur transaksi: pada sambungan sync, job berjalan
     * di dalam permintaan HTTP yang sama dengan aksi persetujuan pengguna.
     * Galat yang dilempar akan menggagalkan aksi itu, padahal yang bermasalah
     * hanya pemberitahuannya.
     */
    public function test_kegagalan_gerbang_tidak_menjatuhkan_aksi_pengguna_pada_antrean_sync(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(['*' => Http::response('gateway down', 502)]);

        $notifikasi = $this->notifikasi();

        (new KirimPesanWhatsApp($notifikasi->id))->handle($this->pengirim());

        $notifikasi->refresh();
        $this->assertSame('gagal', $notifikasi->status_kirim);
        $this->assertStringContainsString('gangguan internal', $notifikasi->error_message);
    }

    public function test_kegagalan_gerbang_dilempar_ulang_ketika_antrean_berjalan_di_latar_belakang(): void
    {
        // Dengan pekerja antrean sungguhan, galat harus naik agar percobaan
        // ulang bawaan Laravel bekerja.
        config(['queue.default' => 'database']);
        Http::fake(['*' => Http::response('gateway down', 502)]);

        $notifikasi = $this->notifikasi();

        try {
            (new KirimPesanWhatsApp($notifikasi->id))->handle($this->pengirim());
            $this->fail('Galat seharusnya dilempar ulang agar antrean mencoba lagi.');
        } catch (PengirimanGagal) {
            $this->assertSame('gagal', $notifikasi->refresh()->status_kirim, 'Status tetap tercatat sebelum galat dilempar.');
        }
    }

    public function test_kegagalan_terakhir_ditandai_lewat_failed(): void
    {
        $notifikasi = $this->notifikasi();

        (new KirimPesanWhatsApp($notifikasi->id))->failed(new PengirimanGagal('Gerbang tidak dapat dihubungi.'));

        $notifikasi->refresh();
        $this->assertSame('gagal', $notifikasi->status_kirim);
        $this->assertSame('Gerbang tidak dapat dihubungi.', $notifikasi->error_message);
    }

    // =====================================================================
    // PENERBITAN BARIS DAN PENYALAAN KANAL
    // =====================================================================

    public function test_kanal_mati_tidak_menerbitkan_baris_whatsapp(): void
    {
        Queue::fake();

        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => '0']);

        $penerima = $this->buatPengguna('kasubbag', null, ['no_hp' => '6281294780409']);
        app(NotifikasiService::class)->kirim([$penerima], 'Judul', 'Isi', 'permintaan');

        $this->assertSame(1, Notifikasi::where('channel', 'in_app')->count(), 'Notifikasi dalam aplikasi tetap terbit.');
        $this->assertSame(0, Notifikasi::where('channel', 'whatsapp')->count());
        Queue::assertNothingPushed();
    }

    public function test_kanal_hidup_menerbitkan_baris_dan_mengantre_pengiriman(): void
    {
        Queue::fake();

        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => '1']);

        $tim      = $this->buatTim();
        $berNomor = $this->buatPengguna('ketua_tim', $tim, ['no_hp' => '6281294780409']);
        $tanpa    = $this->buatPengguna('tim', $tim);   // no_hp null

        app(NotifikasiService::class)->kirim([$berNomor, $tanpa], 'Judul', 'Isi', 'permintaan', 'permintaan_barang', 7);

        $this->assertSame(2, Notifikasi::where('channel', 'in_app')->count(), 'Kanal dalam aplikasi tidak menyaring nomor.');

        $whatsapp = Notifikasi::where('channel', 'whatsapp')->get();
        $this->assertCount(1, $whatsapp, 'Pengguna tanpa nomor tidak dibuatkan baris yang sudah pasti gagal.');
        $this->assertSame($berNomor->id, $whatsapp->first()->user_id);
        $this->assertSame('pending', $whatsapp->first()->status_kirim);
        $this->assertSame('permintaan_barang', $whatsapp->first()->referensi_tabel);
        $this->assertSame(7, $whatsapp->first()->referensi_id);

        Queue::assertPushed(KirimPesanWhatsApp::class, 1);
    }

    public function test_lonceng_dalam_aplikasi_tidak_menampilkan_baris_whatsapp(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => '1']);
        Queue::fake();

        $penerima = $this->buatPengguna('kasubbag', null, ['no_hp' => '6281294780409']);
        app(NotifikasiService::class)->kirim([$penerima], 'Judul', 'Isi', 'permintaan');

        $this->assertSame(
            1,
            Notifikasi::query()->where('user_id', $penerima->id)->dalamAplikasi()->count(),
            'Satu kejadian menghasilkan dua baris, tetapi lonceng hanya menampilkan yang dalam aplikasi.'
        );
    }
}
