<?php

namespace Tests\Feature;

use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian mekanisme pengendalian stok HOLD - RELEASE - KONVERSI.
 *
 * Mekanisme inilah inti dari sistem: stok yang sedang diproses harus terkunci
 * agar tidak dijanjikan dua kali kepada tim berbeda, tetapi stok fisik tidak
 * boleh berkurang sebelum barangnya benar-benar diserahkan. Kekeliruan di sini
 * tidak menimbulkan galat yang terlihat, melainkan diam-diam membuat catatan
 * persediaan tidak sesuai kenyataan di gudang, sehingga justru bagian ini yang
 * paling perlu dijaga oleh pengujian otomatis.
 */
class PengendalianStokTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private StokService $stok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stok = app(StokService::class);
    }

    public function test_hold_mengunci_stok_tanpa_mengurangi_stok_fisik(): void
    {
        $barang = $this->buatBarang(stokFisik: 100);

        $this->stok->hold([$barang->id => 30]);

        $barang->refresh();
        $this->assertSame(100, $barang->stok_fisik, 'Stok fisik tidak boleh berubah saat dikunci.');
        $this->assertSame(30, $barang->stok_hold);
        $this->assertSame(70, $barang->stok_tersedia);
    }

    public function test_hold_ditolak_ketika_stok_tersedia_tidak_cukup(): void
    {
        // Tersedia hanya 20 karena 80 sedang dikunci permintaan lain
        $barang = $this->buatBarang(stokFisik: 100, stokHold: 80);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak mencukupi');

        $this->stok->hold([$barang->id => 21]);
    }

    public function test_hold_kedua_tidak_dapat_melampaui_sisa_stok_tersedia(): void
    {
        $barang = $this->buatBarang(stokFisik: 10);

        $this->stok->hold([$barang->id => 6]);

        try {
            $this->stok->hold([$barang->id => 5]);
            $this->fail('Kunci kedua seharusnya ditolak karena sisa tersedia hanya 4.');
        } catch (\RuntimeException) {
            // Kunci pertama tidak boleh ikut batal
            $this->assertSame(6, $barang->refresh()->stok_hold);
        }
    }

    public function test_release_melepas_kunci_dan_menandai_waktu_pelepasan(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 50, stokHold: 15);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 15],
        ]);

        $this->stok->release($permintaan);

        $barang->refresh();
        $this->assertSame(0, $barang->stok_hold);
        $this->assertSame(50, $barang->stok_fisik, 'Pelepasan kunci tidak boleh menyentuh stok fisik.');
        $this->assertNotNull($permintaan->refresh()->hold_released_at);
    }

    public function test_release_ganda_tidak_membuat_kunci_menjadi_negatif(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 50, stokHold: 10);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10],
        ]);

        $this->stok->release($permintaan);
        $this->stok->release($permintaan);

        $this->assertSame(0, $barang->refresh()->stok_hold);
    }

    public function test_konversi_mengurangi_stok_fisik_dan_mencatat_mutasi_keluar(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $petugas = $this->buatPengguna('petugas_gudang');
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 12);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 12],
        ], status: 'siap_diambil');

        $this->stok->konversi($permintaan, $petugas->id);

        $barang->refresh();
        $this->assertSame(28, $barang->stok_fisik);
        $this->assertSame(0, $barang->stok_hold);

        $mutasi = MutasiStok::where('barang_id', $barang->id)->sole();
        $this->assertSame('keluar', $mutasi->jenis);
        $this->assertSame(-12, $mutasi->jumlah);
        $this->assertSame(28, $mutasi->saldo_sesudah, 'Saldo berjalan harus dicatat saat transaksi terjadi.');
        $this->assertSame('pemakaian', $mutasi->sumber);
        // Kolom "Nomor Dasar M/K" pada kartu kendali memakai nomor bon,
        // mengikuti penomoran Sub-Bagian Umum; kode permintaannya pindah ke
        // keterangan supaya penelusuran balik tidak hilang.
        $this->assertSame($permintaan->refresh()->nomor_bon, $mutasi->nomor_dasar);
        $this->assertStringContainsString($permintaan->kode_permintaan, $mutasi->keterangan);
        $this->assertSame('permintaan_barang', $mutasi->referensi_tabel);
        $this->assertSame($permintaan->id, $mutasi->referensi_id);
        $this->assertSame($petugas->id, $mutasi->petugas_id);
    }

    public function test_konversi_memakai_jumlah_final_ketika_disetujui_sebagian(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $petugas = $this->buatPengguna('petugas_gudang');
        $barang  = $this->buatBarang(stokFisik: 30, stokHold: 10);

        // Diminta 10, tetapi Kasubbag hanya menyetujui 4
        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10, 'final' => 4],
        ], status: 'siap_diambil');

        $this->stok->konversi($permintaan, $petugas->id);

        $barang->refresh();
        $this->assertSame(26, $barang->stok_fisik, 'Yang keluar harus sejumlah yang disetujui, bukan yang diminta.');
        $this->assertSame(-4, MutasiStok::where('barang_id', $barang->id)->sole()->jumlah);
    }

    public function test_persetujuan_sebagian_melepas_selisih_kunci(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 30, stokHold: 10);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10, 'final' => 4],
        ], status: 'siap_diproses');

        $this->stok->sesuaikanHold($permintaan);

        $barang->refresh();
        $this->assertSame(4, $barang->stok_hold, 'Selisih 6 harus kembali tersedia bagi tim lain.');
        $this->assertSame(30, $barang->stok_fisik);
        $this->assertSame(26, $barang->stok_tersedia);
    }

    public function test_persetujuan_penuh_tidak_mengubah_kunci(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 30, stokHold: 10);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10, 'final' => 10],
        ]);

        $this->stok->sesuaikanHold($permintaan);

        $this->assertSame(10, $barang->refresh()->stok_hold);
    }

    public function test_stok_masuk_menambah_stok_dan_mencatat_saldo_berjalan(): void
    {
        $petugas = $this->buatPengguna('petugas_gudang');
        $barang  = $this->buatBarang(stokFisik: 5);

        $this->stok->tambah(
            barangId: $barang->id,
            jumlah: 20,
            sumber: 'pembelian',
            nomorDasar: '34/F/HI/VIII/2026',
            keterangan: 'Pengadaan triwulan pertama',
            petugasId: $petugas->id,
        );

        $this->assertSame(25, $barang->refresh()->stok_fisik);

        $mutasi = MutasiStok::where('barang_id', $barang->id)->sole();
        $this->assertSame('masuk', $mutasi->jenis);
        $this->assertSame(20, $mutasi->jumlah);
        $this->assertSame(25, $mutasi->saldo_sesudah);
        $this->assertSame('34/F/HI/VIII/2026', $mutasi->nomor_dasar);
    }

    public function test_seluruh_daur_hidup_stok_kembali_konsisten(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $petugas = $this->buatPengguna('petugas_gudang');
        $barang  = $this->buatBarang(stokFisik: 100);

        // Dua permintaan berjalan bersamaan atas barang yang sama
        $this->stok->hold([$barang->id => 30]);
        $this->stok->hold([$barang->id => 20]);
        $this->assertSame(50, $barang->refresh()->stok_hold);
        $this->assertSame(50, $barang->stok_tersedia);

        $ditolak = $this->buatPermintaan($tim, $pengaju, [['barang' => $barang, 'diminta' => 20]]);
        $this->stok->release($ditolak);
        $this->assertSame(30, $barang->refresh()->stok_hold, 'Hanya kunci milik permintaan yang ditolak yang dilepas.');

        $diterima = $this->buatPermintaan($tim, $pengaju, [['barang' => $barang, 'diminta' => 30]]);
        $this->stok->konversi($diterima, $petugas->id);

        $barang->refresh();
        $this->assertSame(70, $barang->stok_fisik);
        $this->assertSame(0, $barang->stok_hold);
        $this->assertSame(70, $barang->stok_tersedia, 'Setelah semua selesai, tidak boleh ada stok yang tertinggal terkunci.');

        // Buku besar harus menjelaskan selisih 100 menjadi 70
        $this->assertSame(-30, (int) MutasiStok::where('barang_id', $barang->id)->sum('jumlah'));
        $this->assertSame(
            $barang->stok_fisik,
            (int) MutasiStok::where('barang_id', $barang->id)->latest('id')->first()->saldo_sesudah,
            'Saldo terakhir pada buku besar harus sama dengan stok fisik.'
        );
    }

    public function test_hold_barang_yang_tidak_ada_menggagalkan_seluruh_pengajuan(): void
    {
        $barang = $this->buatBarang(stokFisik: 10);

        try {
            // Barang kedua tidak ada; keseluruhan pengajuan harus batal
            $this->stok->hold([$barang->id => 5, 999999 => 1]);
            $this->fail('Pengajuan atas barang yang tidak ada seharusnya gagal.');
        } catch (\Throwable) {
            $this->assertSame(
                0,
                BarangPersediaan::find($barang->id)->stok_hold,
                'Kunci pada barang pertama harus ikut dibatalkan karena satu transaksi.'
            );
        }
    }
}
