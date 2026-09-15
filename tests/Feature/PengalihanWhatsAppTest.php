<?php

namespace Tests\Feature;

use App\Jobs\KirimPesanWhatsApp;
use App\Models\Notifikasi;
use App\Models\User;
use App\Services\NotifikasiService;
use App\Services\WhatsApp\PengirimWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengalihan notifikasi WhatsApp ke satu nomor untuk keperluan peragaan.
 *
 * Yang dijaga pengujian ini ada dua hal yang sama pentingnya. Pertama, selama
 * pengalihan menyala tidak ada satu pun pesan yang boleh sampai ke nomor
 * pegawai sungguhan — itulah seluruh alasan fiturnya ada. Kedua, ketika
 * pengalihan dikosongkan kembali, perilakunya harus persis seperti sebelum
 * fitur ini ada, sebab keadaan itulah yang berlaku sehari-hari.
 */
class PengalihanWhatsAppTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const NOMOR_PERAGAAN = '6281238096104';
    private const NOMOR_PEGAWAI  = '6281294780409';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => '1']);
    }

    private function alihkanKe(?string $nomor): void
    {
        DB::table('pengaturan')
            ->where('kunci', 'wa_alihkan_ke')
            ->update(['nilai' => $nomor ?? '']);
    }

    /**
     * Gerbang tiruan yang hanya mencatat ke mana dan apa yang dikirim.
     *
     * Dipasang ke container, bukan dipanggil langsung, supaya yang diuji tetap
     * jalur sebenarnya: job mengambil pelaksana dari container persis seperti
     * di lapangan.
     */
    private function gerbangTiruan(): object
    {
        $rekam = new class
        {
            /** @var list<string> */
            public array $tujuan = [];

            /** @var list<string> */
            public array $pesan = [];
        };

        $this->app->bind(PengirimWhatsApp::class, fn () => new class($rekam) implements PengirimWhatsApp
        {
            public function __construct(private object $rekam) {}

            public function nama(): string
            {
                return 'tiruan';
            }

            public function siap(): bool
            {
                return true;
            }

            public function kirim(string $tujuan, string $pesan): ?string
            {
                $this->rekam->tujuan[] = $tujuan;
                $this->rekam->pesan[]  = $pesan;

                return 'uji-1';
            }
        });

        return $rekam;
    }

    private function notifikasiUntuk(User $penerima): Notifikasi
    {
        return Notifikasi::create([
            'user_id'      => $penerima->id,
            'judul'        => 'Permintaan menunggu persetujuan Anda',
            'pesan'        => 'PB-2026-0001 menunggu persetujuan Ketua Tim.',
            'tipe'         => 'permintaan',
            'channel'      => 'whatsapp',
            'status_kirim' => 'pending',
        ]);
    }

    // =====================================================================
    // TUJUAN PENGIRIMAN
    // =====================================================================

    public function test_pengalihan_menyala_membelokkan_pesan_dari_nomor_pegawai(): void
    {
        $rekam = $this->gerbangTiruan();
        $this->alihkanKe(self::NOMOR_PERAGAAN);

        $ketua = $this->buatPengguna('ketua_tim', $this->buatTim(), ['no_hp' => self::NOMOR_PEGAWAI]);

        (new KirimPesanWhatsApp($this->notifikasiUntuk($ketua)->id))->handle(app(PengirimWhatsApp::class));

        $this->assertSame([self::NOMOR_PERAGAAN], $rekam->tujuan);
        $this->assertNotContains(
            self::NOMOR_PEGAWAI,
            $rekam->tujuan,
            'Nomor pegawai sungguhan tidak boleh tersentuh selama peragaan.'
        );
    }

    public function test_pengalihan_kosong_mengirim_ke_nomor_pengguna_seperti_biasa(): void
    {
        $rekam = $this->gerbangTiruan();
        $this->alihkanKe(null);

        $ketua = $this->buatPengguna('ketua_tim', $this->buatTim(), ['no_hp' => self::NOMOR_PEGAWAI]);

        (new KirimPesanWhatsApp($this->notifikasiUntuk($ketua)->id))->handle(app(PengirimWhatsApp::class));

        $this->assertSame([self::NOMOR_PEGAWAI], $rekam->tujuan);
    }

    /**
     * Salah ketik tidak boleh menghentikan notifikasi. Nomor yang tidak masuk
     * akal diperlakukan sebagai pengalihan yang mati, sehingga pesan kembali
     * mengalir ke penerima sebenarnya alih-alih gagal terkirim.
     */
    public function test_nomor_pengalihan_yang_tidak_masuk_akal_dianggap_mati(): void
    {
        $rekam = $this->gerbangTiruan();
        $this->alihkanKe('123');

        $ketua = $this->buatPengguna('ketua_tim', $this->buatTim(), ['no_hp' => self::NOMOR_PEGAWAI]);

        (new KirimPesanWhatsApp($this->notifikasiUntuk($ketua)->id))->handle(app(PengirimWhatsApp::class));

        $this->assertSame([self::NOMOR_PEGAWAI], $rekam->tujuan);
    }

    // =====================================================================
    // ISI PESAN
    // =====================================================================

    public function test_pesan_yang_dialihkan_menyebut_penerima_sebenarnya(): void
    {
        $rekam = $this->gerbangTiruan();
        $this->alihkanKe(self::NOMOR_PERAGAAN);

        $tim   = $this->buatTim('Statistik Sosial');
        $ketua = $this->buatPengguna('ketua_tim', $tim, [
            'name'  => 'Wanda Pribadi',
            'no_hp' => self::NOMOR_PEGAWAI,
        ]);

        (new KirimPesanWhatsApp($this->notifikasiUntuk($ketua)->id))->handle(app(PengirimWhatsApp::class));

        $this->assertStringStartsWith(
            '[Demo — seharusnya untuk Wanda Pribadi (Ketua Tim Statistik Sosial)]',
            $rekam->pesan[0],
        );
        $this->assertStringContainsString('Permintaan menunggu persetujuan Anda', $rekam->pesan[0]);
    }

    public function test_pesan_biasa_tidak_diberi_awalan_peragaan(): void
    {
        $rekam = $this->gerbangTiruan();
        $this->alihkanKe(null);

        $ketua = $this->buatPengguna('ketua_tim', $this->buatTim(), ['no_hp' => self::NOMOR_PEGAWAI]);

        (new KirimPesanWhatsApp($this->notifikasiUntuk($ketua)->id))->handle(app(PengirimWhatsApp::class));

        $this->assertStringNotContainsString('[Demo', $rekam->pesan[0]);
    }

    // =====================================================================
    // PENERBITAN BARIS
    // =====================================================================

    /**
     * Akun peragaan Kasubbag dan Petugas Gudang belum tentu berisi nomor.
     * Tanpa pengecualian ini, tahap-tahap itu tidak memunculkan pesan apa pun
     * saat diperagakan sehingga alurnya tampak terputus di tengah.
     */
    public function test_pengalihan_menyala_menerbitkan_baris_bagi_pengguna_tanpa_nomor(): void
    {
        Queue::fake();
        $this->alihkanKe(self::NOMOR_PERAGAAN);

        $gudang = $this->buatPengguna('petugas_gudang');   // no_hp null

        app(NotifikasiService::class)->kirim([$gudang], 'Judul', 'Isi', 'permintaan');

        $this->assertSame(1, Notifikasi::where('channel', 'whatsapp')->count());
        Queue::assertPushed(KirimPesanWhatsApp::class, 1);
    }

    public function test_pengalihan_kosong_tetap_melewati_pengguna_tanpa_nomor(): void
    {
        Queue::fake();
        $this->alihkanKe(null);

        $gudang = $this->buatPengguna('petugas_gudang');   // no_hp null

        app(NotifikasiService::class)->kirim([$gudang], 'Judul', 'Isi', 'permintaan');

        $this->assertSame(0, Notifikasi::where('channel', 'whatsapp')->count());
        Queue::assertNothingPushed();
    }
}
