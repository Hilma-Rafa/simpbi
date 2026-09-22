<?php

namespace Tests\Feature;

use App\Models\MutasiStok;
use App\Models\PermintaanBarang;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Penomoran bon pengeluaran barang persediaan.
 *
 * Kartu kendali Sub-Bagian Umum menomori pengeluaran dengan urutan berjalan
 * sendiri — 035, 037, 038 — dan nomor itulah yang mengisi kolom "Nomor Dasar
 * M/K". Satu permintaan adalah satu bon, sehingga nomor yang sama muncul pada
 * kartu setiap barang yang diminta dalam permintaan tersebut.
 */
class NomorBonPengeluaranTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** Mengeluarkan sebuah permintaan berisi sejumlah barang. */
    private function keluarkan(array $rincian): PermintaanBarang
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $petugas = $this->buatPengguna('petugas_gudang');

        $permintaan = $this->buatPermintaan($tim, $pengaju, $rincian, status: 'siap_diambil');

        app(StokService::class)->konversi($permintaan, $petugas->id);

        return $permintaan->refresh();
    }

    public function test_permintaan_pertama_tahun_ini_bernomor_001(): void
    {
        $permintaan = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        $this->assertSame('001', $permintaan->nomor_bon);
        $this->assertSame((int) now()->year, (int) $permintaan->tahun_bon);
        $this->assertSame('001', MutasiStok::where('jenis', 'keluar')->sole()->nomor_dasar);
    }

    public function test_nomor_berlanjut_pada_permintaan_berikutnya(): void
    {
        $pertama = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);
        $kedua = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        $this->assertSame('001', $pertama->nomor_bon);
        $this->assertSame('002', $kedua->nomor_bon);
    }

    /**
     * Satu permintaan adalah satu bon. Nomor yang sama harus muncul pada kartu
     * kendali setiap barang di dalamnya — itulah yang membuat kartu-kartu itu
     * dapat ditelusuri balik ke satu dokumen pengeluaran.
     */
    public function test_satu_permintaan_memakai_satu_nomor_untuk_semua_barangnya(): void
    {
        $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
            ['barang' => $this->buatBarang(stokFisik: 30, stokHold: 8), 'diminta' => 8],
            ['barang' => $this->buatBarang(stokFisik: 15, stokHold: 3), 'diminta' => 3],
        ]);

        $nomor = MutasiStok::where('jenis', 'keluar')->pluck('nomor_dasar')->unique();

        $this->assertCount(3, MutasiStok::where('jenis', 'keluar')->get());
        $this->assertCount(1, $nomor, 'Ketiga barang harus memakai nomor bon yang sama.');
        $this->assertSame('001', $nomor->first());
    }

    /** Kode permintaan tidak boleh hilang; ia pindah ke kolom keterangan. */
    public function test_kode_permintaan_tetap_tercatat_pada_keterangan(): void
    {
        $permintaan = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        $baris = MutasiStok::where('jenis', 'keluar')->sole();

        $this->assertStringContainsString($permintaan->kode_permintaan, $baris->keterangan);
        $this->assertSame('permintaan_barang', $baris->referensi_tabel);
        $this->assertSame($permintaan->id, $baris->referensi_id);
    }

    public function test_penomoran_dimulai_ulang_setiap_tahun(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 20));
        $akhirTahun = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        Carbon::setTestNow(Carbon::create(2027, 1, 5));
        $tahunBaru = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        Carbon::setTestNow();

        $this->assertSame('001', $akhirTahun->nomor_bon);
        $this->assertSame(2026, (int) $akhirTahun->tahun_bon);

        $this->assertSame('001', $tahunBaru->nomor_bon);
        $this->assertSame(2027, (int) $tahunBaru->tahun_bon);
    }

    /**
     * Nomor terakhir dicari sebagai angka, bukan sebagai teks. Diuji dengan
     * melompati seribu, sebab di situlah urutan teks mulai keliru: sebagai teks
     * "999" berada di atas "1000", sehingga nomor berikutnya akan mengulang
     * nomor yang sudah terpakai.
     */
    public function test_penomoran_tetap_benar_setelah_melewati_seribu(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);

        $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $this->buatBarang(), 'diminta' => 1],
        ])->forceFill(['nomor_bon' => '1000', 'tahun_bon' => (int) now()->year])->save();

        $berikutnya = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        $this->assertSame('1001', $berikutnya->nomor_bon);
    }

    /**
     * Regresi A-001: kueri pencari nomor terakhir harus sah di SQLite maupun
     * MySQL/MariaDB, dan urutannya numerik pada batas satu ke dua digit
     * (009, 010, 011), bukan urutan teks.
     */
    public function test_penomoran_berurutan_melewati_batas_satu_ke_dua_digit(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);

        $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $this->buatBarang(), 'diminta' => 1],
        ])->forceFill(['nomor_bon' => '008', 'tahun_bon' => (int) now()->year])->save();

        $nomor = [];

        foreach (range(1, 3) as $urut) {
            $nomor[] = $this->keluarkan([
                ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
            ])->nomor_bon;
        }

        $this->assertSame(['009', '010', '011'], $nomor);
    }

    /** Nomor lama yang belum berlapis nol tetap dibandingkan sebagai angka: 10 di atas 9. */
    public function test_nomor_tanpa_lapis_nol_dibandingkan_sebagai_angka(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);

        foreach (['9', '10'] as $lama) {
            $this->buatPermintaan($tim, $pengaju, [
                ['barang' => $this->buatBarang(), 'diminta' => 1],
            ])->forceFill(['nomor_bon' => $lama, 'tahun_bon' => (int) now()->year])->save();
        }

        $berikutnya = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        $this->assertSame('011', $berikutnya->nomor_bon, 'Sebagai teks "9" berada di atas "10".');
    }

    /**
     * Permintaan yang berakhir tanpa pengeluaran barang tidak boleh
     * menghabiskan nomor: bon hanya lahir ketika barang benar-benar keluar.
     */
    public function test_permintaan_tanpa_pengeluaran_tidak_mendapat_nomor(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);

        $ditolak = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $this->buatBarang(), 'diminta' => 2],
        ], status: 'ditolak_ketua');

        $this->assertNull($ditolak->nomor_bon);

        $keluar = $this->keluarkan([
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5],
        ]);

        $this->assertSame('001', $keluar->nomor_bon, 'Nomor pertama tidak boleh terlewat.');
    }
}
