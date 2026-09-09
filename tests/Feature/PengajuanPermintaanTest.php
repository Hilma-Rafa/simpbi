<?php

namespace Tests\Feature;

use App\Filament\Pages\KatalogBarang;
use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian pengajuan permintaan barang (UC-08) beserta percabangan UC-09.
 *
 * Yang diuji bukan tampilan katalognya, melainkan akibat dari penekanan tombol
 * ajukan: stok terkunci, permintaan tersimpan pada tahap yang benar, dan
 * riwayat terbentuk. Percabangan pengaju berperan Ketua Tim diuji tersendiri
 * karena aturan itu tidak terlihat di antarmuka — tahap persetujuan hilang
 * begitu saja — sehingga mudah rusak tanpa disadari.
 */
class PengajuanPermintaanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /**
     * Menekan tombol ajukan pada halaman katalog.
     *
     * @param  array<int, array{barang_id:int, jumlah:int}>  $items
     */
    private function ajukan(array $items, string $nama = 'Pemohon Uji', ?string $keperluan = 'Keperluan uji')
    {
        return Livewire::test(KatalogBarang::class)->callAction('ajukan', [
            'nama_pemohon' => $nama,
            'nip_pemohon'  => '199001012020121001',
            'items'        => $items,
            'keperluan'    => $keperluan,
        ]);
    }

    public function test_tim_mengajukan_permintaan_menunggu_persetujuan_ketua(): void
    {
        $tim    = $this->buatTim();
        $anggota = $this->buatPengguna('tim', $tim);
        $barang = $this->buatBarang(stokFisik: 50);

        $this->actingAs($anggota);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 8]]);

        $permintaan = PermintaanBarang::sole();
        $this->assertSame('menunggu_ketua', $permintaan->status);
        $this->assertSame($tim->id, $permintaan->tim_pemohon_id);
        $this->assertSame($anggota->id, $permintaan->pengaju_id);
        $this->assertSame('Pemohon Uji', $permintaan->nama_pemohon, 'Data pemohon diisi manual karena akun Tim bersifat bersama.');
        $this->assertNotNull($permintaan->hold_expired_at, 'Batas waktu tahapan harus ditetapkan saat pengajuan.');

        $this->assertSame(8, $permintaan->detail()->sole()->jumlah_diminta);

        $barang->refresh();
        $this->assertSame(8, $barang->stok_hold, 'Pengajuan harus mengunci stok.');
        $this->assertSame(50, $barang->stok_fisik, 'Stok fisik belum boleh berkurang saat pengajuan.');

        $riwayat = RiwayatPersetujuan::where('permintaan_id', $permintaan->id)->get();
        $this->assertCount(1, $riwayat);
        $this->assertSame('pengajuan', $riwayat->first()->tahap);
    }

    public function test_ketua_tim_yang_mengajukan_melewati_tahap_persetujuan_ketua(): void
    {
        $tim    = $this->buatTim();
        $ketua  = $this->buatPengguna('ketua_tim', $tim);
        $barang = $this->buatBarang(stokFisik: 50);

        $this->actingAs($ketua);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 5]]);

        $permintaan = PermintaanBarang::sole();
        $this->assertSame(
            'menunggu_verifikasi',
            $permintaan->status,
            'Pengaju yang sudah berperan Ketua Tim tidak perlu menyetujui permintaannya sendiri.'
        );

        $riwayat = RiwayatPersetujuan::where('permintaan_id', $permintaan->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $riwayat, 'Tahap yang dilewati tetap harus terekam agar riwayat tidak berlubang.');
        $this->assertSame('pengajuan', $riwayat[0]->tahap);
        $this->assertSame('ketua_tim', $riwayat[1]->tahap);
        $this->assertSame('setuju', $riwayat[1]->keputusan);
        $this->assertStringContainsString('dilewati', $riwayat[1]->catatan);
    }

    public function test_pengajuan_melebihi_stok_tersedia_dibatalkan_seluruhnya(): void
    {
        $tim    = $this->buatTim();
        $anggota = $this->buatPengguna('tim', $tim);

        $cukup  = $this->buatBarang(stokFisik: 50);
        // Tersedia hanya 2 karena 18 sedang dikunci permintaan lain
        $kurang = $this->buatBarang(stokFisik: 20, stokHold: 18);

        $this->actingAs($anggota);
        $this->ajukan([
            ['barang_id' => $cukup->id,  'jumlah' => 5],
            ['barang_id' => $kurang->id, 'jumlah' => 3],
        ]);

        $this->assertSame(0, PermintaanBarang::count(), 'Permintaan tidak boleh tersimpan sebagian.');
        $this->assertSame(0, $cukup->refresh()->stok_hold, 'Kunci pada barang yang cukup harus ikut dibatalkan.');
        $this->assertSame(18, $kurang->refresh()->stok_hold);
    }

    public function test_pengajuan_dengan_keranjang_kosong_tidak_menyimpan_apa_pun(): void
    {
        $tim     = $this->buatTim();
        $anggota = $this->buatPengguna('tim', $tim);

        $this->actingAs($anggota);
        $this->ajukan([]);

        $this->assertSame(0, PermintaanBarang::count());
    }

    public function test_pengguna_tanpa_tim_tidak_dapat_mengajukan(): void
    {
        $tanpaTim = $this->buatPengguna('tim');   // tim_id null
        $barang   = $this->buatBarang(stokFisik: 50);

        $this->actingAs($tanpaTim);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 3]]);

        $this->assertSame(0, PermintaanBarang::count());
        $this->assertSame(0, $barang->refresh()->stok_hold, 'Stok tidak boleh terkunci oleh pengajuan yang ditolak.');
    }
}
