<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use App\Services\KedaluwarsaService;
use App\Services\NotifikasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit A-006: permintaan yang gugur karena batas waktu diberitahukan kepada
 * pemohon (dan anggota timnya), sekali saja, tanpa membahayakan sapuannya.
 */
class NotifikasiKedaluwarsaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function kedaluwarsa(array $penerima = []): array
    {
        $tim     = $this->buatTim();
        $pemohon = $this->buatPengguna('tim', $tim, $penerima);
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 10);

        $permintaan = $this->buatPermintaan($tim, $pemohon, [
            ['barang' => $barang, 'diminta' => 10],
        ], status: 'menunggu_ketua', tambahan: [
            'hold_expired_at' => now()->subHour(),
        ]);

        return [$permintaan, $pemohon, $barang];
    }

    public function test_sapuan_memberi_tahu_pemohon_tepat_satu_kali(): void
    {
        [$permintaan, $pemohon, $barang] = $this->kedaluwarsa();

        app(KedaluwarsaService::class)->sapu();

        $this->assertSame('kedaluwarsa', $permintaan->fresh()->status);
        $this->assertSame(0, $barang->fresh()->stok_hold, 'Kunci harus dilepas.');

        $notifikasi = Notifikasi::where('user_id', $pemohon->id)->where('channel', 'in_app')->get();
        $this->assertCount(1, $notifikasi);
        $this->assertSame('Permintaan kedaluwarsa', $notifikasi->first()->judul);
        $this->assertStringContainsString($permintaan->kode_permintaan, $notifikasi->first()->pesan);
        $this->assertSame($permintaan->id, $notifikasi->first()->referensi_id);
    }

    public function test_sapuan_kedua_tidak_menambah_notifikasi(): void
    {
        [, $pemohon] = $this->kedaluwarsa();

        $pertama = app(KedaluwarsaService::class)->sapu();
        $kedua   = app(KedaluwarsaService::class)->sapu();

        $this->assertCount(1, $pertama);
        $this->assertCount(0, $kedua);
        $this->assertSame(1, Notifikasi::where('user_id', $pemohon->id)->count());
    }

    public function test_penerima_whatsapp_tercatat_satu_kali_bila_kanal_menyala(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => '1']);
        [, $pemohon] = $this->kedaluwarsa(['no_hp' => '6281294780409']);

        app(KedaluwarsaService::class)->sapu();
        app(KedaluwarsaService::class)->sapu();

        $this->assertSame(1, Notifikasi::where('user_id', $pemohon->id)->where('channel', 'whatsapp')->count());
        $this->assertSame(1, Notifikasi::where('user_id', $pemohon->id)->where('channel', 'in_app')->count());
    }

    public function test_permintaan_yang_sudah_ditangani_sejak_daftar_dibaca_dilewati(): void
    {
        [$permintaan, $pemohon, $barang] = $this->kedaluwarsa();

        // Menyimulasikan sapuan/pengguna lain yang lebih dulu menangani permintaan
        // ini, setelah daftar dibaca tetapi sebelum transaksi sapuan ini mulai.
        $sekali = false;
        PermintaanBarang::retrieved(function (PermintaanBarang $model) use (&$sekali): void {
            if ($sekali) {
                return;
            }

            $sekali = true;
            DB::table('permintaan_barang')->where('id', $model->id)->update(['status' => 'ditolak_ketua']);
        });

        $hasil = app(KedaluwarsaService::class)->sapu();

        $this->assertCount(0, $hasil, 'Yang tidak diubah sapuan ini tidak boleh dilaporkan.');
        $this->assertSame('ditolak_ketua', $permintaan->fresh()->status);
        $this->assertSame(10, $barang->fresh()->stok_hold, 'Kunci tidak boleh dilepas dua kali.');
        $this->assertSame(0, Notifikasi::where('user_id', $pemohon->id)->count());
    }

    public function test_kegagalan_notifikasi_tidak_menggagalkan_sapuan(): void
    {
        [$permintaan, , $barang] = $this->kedaluwarsa();

        $this->mock(NotifikasiService::class, function ($mock): void {
            $mock->shouldReceive('permintaanBerubah')->once()->andThrow(new RuntimeException('gerbang WhatsApp mati'));
        });

        $hasil = app(KedaluwarsaService::class)->sapu();

        $this->assertCount(1, $hasil);
        $this->assertTrue($hasil->first()['berhasil']);
        $this->assertSame('kedaluwarsa', $permintaan->fresh()->status);
        $this->assertSame(0, $barang->fresh()->stok_hold);
    }
}
