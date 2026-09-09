<?php

namespace Tests\Feature;

use App\Filament\Pages\Riwayat;
use App\Jobs\KirimPesanWhatsApp;
use App\Models\Notifikasi;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian riwayat pengiriman notifikasi dan aksi kirim ulang (UC-23).
 *
 * Aturan yang dijaga di sini bukan tampilannya, melainkan siapa yang boleh
 * melihat catatan teknis pengiriman dan baris mana yang boleh diulang.
 * Kekeliruan pada aturan kedua berakibat langsung ke penerima: pesan yang
 * sudah sampai terkirim dua kali, atau baris gagal tidak pernah bisa
 * diperbaiki setelah gerbang dibetulkan.
 */
class RiwayatNotifikasiTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function buatNotifikasi(string $channel, ?string $status, array $tambahan = []): Notifikasi
    {
        $penerima = $this->buatPengguna('tim', $this->buatTim(), ['no_hp' => '6281234567890']);

        return Notifikasi::create([
            'user_id'      => $penerima->id,
            'judul'        => 'Permintaan menunggu persetujuan Anda',
            'pesan'        => 'PB-2026-0001 menunggu persetujuan Ketua Tim.',
            'tipe'         => 'permintaan',
            'channel'      => $channel,
            'status_kirim' => $status,
            ...$tambahan,
        ]);
    }

    public function test_hanya_admin_yang_melihat_riwayat_pengiriman_notifikasi(): void
    {
        $this->assertArrayHasKey('notifikasi', Riwayat::jenisTersediaUntuk('admin'));

        foreach (['kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $peran) {
            $this->assertArrayNotHasKey(
                'notifikasi',
                Riwayat::jenisTersediaUntuk($peran),
                "Peran {$peran} tidak berkepentingan atas catatan teknis pengiriman."
            );
        }
    }

    public function test_riwayat_notifikasi_bukan_jenis_bawaan_bagi_admin(): void
    {
        $jenis = array_key_first(Riwayat::jenisTersediaUntuk('admin'));

        $this->assertSame(
            'permintaan',
            $jenis,
            'Jenis bawaan harus tetap riwayat permintaan supaya halaman tidak berubah maknanya bagi Admin.'
        );
    }

    public function test_pesan_whatsapp_yang_gagal_dapat_dikirim_ulang(): void
    {
        Queue::fake();

        $gagal = $this->buatNotifikasi('whatsapp', 'gagal', [
            'error_message' => 'Sesi gerbang belum tersambung ke WhatsApp.',
            'dikirim_at'    => now()->subHour(),
        ]);

        $this->actingAs($this->buatPengguna('admin'));

        Livewire::test(Riwayat::class, ['jenis' => 'notifikasi'])
            ->callTableAction('kirimUlang', $gagal);

        $gagal->refresh();
        $this->assertSame('pending', $gagal->status_kirim, 'Job hanya menggarap baris berstatus menunggu.');
        $this->assertNull($gagal->error_message, 'Alasan kegagalan lama tidak boleh menempel pada percobaan baru.');
        $this->assertNull($gagal->dikirim_at);

        Queue::assertPushed(KirimPesanWhatsApp::class, fn ($job) => $job->notifikasiId === $gagal->id);
    }

    public function test_pesan_yang_sudah_terkirim_tidak_dapat_diulang(): void
    {
        $terkirim = $this->buatNotifikasi('whatsapp', 'terkirim', ['dikirim_at' => now()]);

        $this->actingAs($this->buatPengguna('admin'));

        Livewire::test(Riwayat::class, ['jenis' => 'notifikasi'])
            ->assertTableActionHidden('kirimUlang', $terkirim);
    }

    public function test_notifikasi_dalam_aplikasi_tidak_dapat_diulang(): void
    {
        $dalamAplikasi = $this->buatNotifikasi('in_app', null);

        $this->actingAs($this->buatPengguna('admin'));

        Livewire::test(Riwayat::class, ['jenis' => 'notifikasi'])
            ->assertTableActionHidden('kirimUlang', $dalamAplikasi);
    }

    public function test_kirim_ulang_massal_hanya_menggarap_baris_yang_gagal(): void
    {
        Queue::fake();

        $gagal      = $this->buatNotifikasi('whatsapp', 'gagal', ['error_message' => 'Gerbang mati.']);
        $terkirim   = $this->buatNotifikasi('whatsapp', 'terkirim', ['dikirim_at' => now()]);
        $dalamApl   = $this->buatNotifikasi('in_app', null);

        $this->actingAs($this->buatPengguna('admin'));

        Livewire::test(Riwayat::class, ['jenis' => 'notifikasi'])
            ->set('selectedTableRecords', [$gagal->id, $terkirim->id, $dalamApl->id])
            ->callAction(TestAction::make('kirimUlangTerpilih')->table()->bulk());

        $this->assertSame('pending', $gagal->refresh()->status_kirim);
        $this->assertSame('terkirim', $terkirim->refresh()->status_kirim, 'Baris yang sudah sampai harus dilewati.');
        $this->assertNull($dalamApl->refresh()->status_kirim);

        Queue::assertPushed(KirimPesanWhatsApp::class, 1);
    }

    public function test_baris_seluruh_kanal_tampil_pada_riwayat(): void
    {
        $whatsapp = $this->buatNotifikasi('whatsapp', 'terkirim', ['dikirim_at' => now()]);
        $dalamApl = $this->buatNotifikasi('in_app', null);

        $this->actingAs($this->buatPengguna('admin'));

        Livewire::test(Riwayat::class, ['jenis' => 'notifikasi'])
            ->assertCanSeeTableRecords([$whatsapp, $dalamApl]);
    }
}
