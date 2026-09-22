<?php

namespace Tests\Feature;

use App\Filament\Resources\PermintaanBarangs\Pages\ListPermintaanBarangs;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\BarangPersediaan;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use App\Models\User;
use App\Services\StokService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit A-003: jumlah yang disetujui Kasubbag tidak boleh melebihi jumlah yang
 * diminta, dan kunci stok milik satu permintaan tidak boleh ikut menghapus
 * kunci milik permintaan lain.
 *
 * Skenario dasarnya adalah temuan audit: stok fisik 10, terkunci 5 — R1 meminta
 * 2, R2 meminta 3.
 */
class PersetujuanTidakMelebihiDimintaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private Tim $tim;

    private User $anggota;

    private User $gudang;

    private User $kasubbag;

    private BarangPersediaan $barang;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('tanda-tangan/uji.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));

        Filament::setCurrentPanel('admin');

        $this->tim = $this->buatTim('Tim A');
        $ketua = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $this->tim));
        $this->tim->update(['ketua_tim_id' => $ketua->id]);
        $this->anggota = $this->lengkapiAkun($this->buatPengguna('tim', $this->tim));
        $this->gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $this->kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));

        $this->barang = $this->buatBarang(10, 5);
    }

    private function permintaan(int $diminta, string $status): PermintaanBarang
    {
        return $this->buatPermintaan($this->tim, $this->anggota, [['barang' => $this->barang, 'diminta' => $diminta]], $status);
    }

    private function aksi(User $pengguna, PermintaanBarang $permintaan, string $nama, array $data = [])
    {
        $this->flushSession();
        auth()->forgetGuards();
        $this->actingAs($pengguna);

        return Livewire::test(ListPermintaanBarangs::class)
            ->callAction([TestAction::make('detail')->table($permintaan), TestAction::make($nama)], data: $data);
    }

    private function setujui(PermintaanBarang $permintaan, int $final)
    {
        return $this->aksi($this->kasubbag, $permintaan, 'setujuiKasubbag', [
            'items' => [['detail_id' => $permintaan->detail[0]->id, 'jumlah_final' => $final]],
        ]);
    }

    /** Dari siap_diproses sampai selesai: siapkan → konfirmasi penerimaan → sahkan. */
    private function selesaikan(PermintaanBarang $permintaan): void
    {
        $this->aksi($this->gudang, $permintaan, 'siapkan', ['konfirmasi_nip' => $this->gudang->nip]);
        $this->aksi($this->anggota, $permintaan, 'konfirmasi', ['sesuai' => 'ya', 'konfirmasi_nip' => $this->tim->nama_tim]);
        $this->aksi($this->kasubbag, $permintaan, 'sahkan', ['konfirmasi_nip' => $this->kasubbag->nip]);
    }

    private function stok(): array
    {
        return $this->barang->fresh()->only(['stok_fisik', 'stok_hold']);
    }

    public function test_persetujuan_melebihi_diminta_ditolak_dan_stok_tidak_berubah(): void
    {
        $r1 = $this->permintaan(2, 'menunggu_kasubbag');
        $this->permintaan(3, 'siap_diproses');

        $this->setujui($r1, 8)->assertHasActionErrors();

        $this->assertSame('menunggu_kasubbag', $r1->fresh()->status);
        $this->assertNull($r1->detail()->first()->jumlah_final);
        $this->assertSame(['stok_fisik' => 10, 'stok_hold' => 5], $this->stok());
    }

    public function test_pesan_batas_menyebut_jumlah_diminta(): void
    {
        $r1 = $this->permintaan(2, 'menunggu_kasubbag');

        $hasil = $this->setujui($r1, 3)->assertHasActionErrors();

        $this->assertStringContainsString(
            'Jumlah disetujui tidak boleh melebihi jumlah diminta (2).',
            json_encode($hasil->errors()->all(), JSON_UNESCAPED_UNICODE),
        );
    }

    public function test_persetujuan_sesuai_diminta_tidak_mengganggu_kunci_permintaan_lain(): void
    {
        $r1 = $this->permintaan(2, 'menunggu_kasubbag');
        $r2 = $this->permintaan(3, 'siap_diproses');

        $this->setujui($r1, 2);
        $this->assertSame('siap_diproses', $r1->fresh()->status);
        $this->assertSame(['stok_fisik' => 10, 'stok_hold' => 5], $this->stok());

        $this->aksi($this->gudang, $r1, 'siapkan', ['konfirmasi_nip' => $this->gudang->nip]);
        $this->aksi($this->anggota, $r1, 'konfirmasi', ['sesuai' => 'ya', 'konfirmasi_nip' => $this->tim->nama_tim]);

        // Kunci R2 (3) utuh; hanya milik R1 (2) yang berubah menjadi pengeluaran.
        $this->assertSame(['stok_fisik' => 8, 'stok_hold' => 3], $this->stok());

        // R2 dapat diselesaikan sampai Sahkan.
        $this->selesaikan($r2);

        $this->assertSame('selesai', $r2->fresh()->status);
        $this->assertSame(['stok_fisik' => 5, 'stok_hold' => 0], $this->stok());
    }

    public function test_persetujuan_sebagian_melepas_selisih_dan_kedua_permintaan_selesai(): void
    {
        $r1 = $this->permintaan(2, 'menunggu_kasubbag');
        $r2 = $this->permintaan(3, 'siap_diproses');

        $this->setujui($r1, 1);

        // Satu unit dilepas; kunci R2 tidak tersentuh.
        $this->assertSame(['stok_fisik' => 10, 'stok_hold' => 4], $this->stok());

        $this->selesaikan($r1);
        $this->assertSame(['stok_fisik' => 9, 'stok_hold' => 3], $this->stok());

        $this->selesaikan($r2);

        $this->assertSame('selesai', $r1->fresh()->status);
        $this->assertSame('selesai', $r2->fresh()->status);
        $this->assertSame(['stok_fisik' => 6, 'stok_hold' => 0], $this->stok());
    }

    public function test_muatan_yang_dimodifikasi_ditolak_di_server(): void
    {
        $r1 = $this->permintaan(2, 'menunggu_kasubbag');
        $this->permintaan(3, 'siap_diproses');

        // Jumlah diminta pada muatan dinaikkan bersama jumlah disetujui: batas
        // tetap dibaca dari rincian tersimpan.
        $this->aksi($this->kasubbag, $r1, 'setujuiKasubbag', [
            'items' => [['detail_id' => $r1->detail[0]->id, 'jumlah_diminta' => 99, 'jumlah_final' => 99]],
        ])->assertHasActionErrors();

        $this->assertSame('menunggu_kasubbag', $r1->fresh()->status);
        $this->assertSame(['stok_fisik' => 10, 'stok_hold' => 5], $this->stok());
    }

    public function test_penjaga_penangan_menolak_tanpa_mengubah_status_dan_tanpa_galat(): void
    {
        $r1 = $this->permintaan(2, 'menunggu_kasubbag');
        $this->permintaan(3, 'siap_diproses');
        $this->flushSession();
        auth()->forgetGuards();
        $this->actingAs($this->kasubbag);

        // Melewati validasi formulir, seperti muatan yang menembus lapisan pertama.
        (new ReflectionMethod(PermintaanBarangResource::class, 'setujuiKasubbag'))
            ->invoke(null, $r1, ['items' => [['detail_id' => $r1->detail[0]->id, 'jumlah_final' => 3]]]);

        Notification::assertNotified('Persetujuan tidak dapat disimpan');
        $this->assertSame('menunggu_kasubbag', $r1->fresh()->status);
        $this->assertNull($r1->detail()->first()->jumlah_final);
        $this->assertSame(['stok_fisik' => 10, 'stok_hold' => 5], $this->stok());
    }

    public function test_layanan_stok_menolak_jumlah_tersimpan_yang_melebihi_diminta(): void
    {
        $r1 = $this->permintaan(2, 'menunggu_kasubbag');
        $r1->detail()->update(['jumlah_final' => 8]);

        try {
            app(StokService::class)->sesuaikanHold($r1->fresh());
            $this->fail('Penjaga tidak menolak jumlah disetujui yang melebihi diminta.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Jumlah disetujui tidak boleh melebihi jumlah diminta (2).', $e->getMessage());
        }

        $this->assertSame(['stok_fisik' => 10, 'stok_hold' => 5], $this->stok());
    }

    public function test_konversi_dan_release_tidak_menyentuh_kunci_permintaan_lain(): void
    {
        // Data lama yang sudah rusak (final > diminta) tidak boleh menghapus kunci R2.
        $r1 = $this->permintaan(2, 'siap_diambil');
        $this->permintaan(3, 'siap_diproses');
        $r1->detail()->update(['jumlah_final' => 8]);

        app(StokService::class)->release($r1->fresh());

        $this->assertSame(['stok_fisik' => 10, 'stok_hold' => 3], $this->stok());
    }
}
