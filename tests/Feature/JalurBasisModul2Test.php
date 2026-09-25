<?php

namespace Tests\Feature;

use App\Models\MutasiStok;
use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use App\Services\KedaluwarsaService;
use App\Services\StokService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Kasus uji basis path Increment 2 (Modul Permintaan Barang: StokService dan
 * KedaluwarsaService).
 *
 * Hanya jalur independen yang belum dieksekusi tes lain; nomor jalurnya
 * mengacu ke docs/pengujian/wb-increment-2.md.
 */
class JalurBasisModul2Test extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function stok(): StokService
    {
        return app(StokService::class);
    }

    /** Permintaan tanpa rincian sama sekali, untuk jalur yang tidak memasuki perulangan. */
    private function permintaanKosong(string $status = 'siap_diambil', array $tambahan = []): PermintaanBarang
    {
        $tim = $this->buatTim();

        return $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [], $status, $tambahan);
    }

    // =====================================================================
    // 2.1 hold, 2.2 release, 2.3 pesanAturan
    // =====================================================================

    /** 2.1 J1: tanpa item, badan perulangan tidak dijalankan dan tidak ada kunci yang berubah. */
    public function test_hold_tanpa_item_tidak_mengubah_apa_pun(): void
    {
        $barang = $this->buatBarang(stokFisik: 10, stokHold: 3);

        $this->stok()->hold([]);

        $this->assertSame(3, $barang->refresh()->stok_hold);
    }

    /** 2.2 J1: permintaan tanpa rincian tetap ditandai sudah dilepas. */
    public function test_release_tanpa_rincian_hanya_menandai_waktu(): void
    {
        $permintaan = $this->permintaanKosong('ditolak_ketua');

        $this->stok()->release($permintaan);

        $this->assertNotNull($permintaan->refresh()->hold_released_at);
    }

    /** 2.3 J2: QueryException adalah turunan RuntimeException, tetapi bukan penolakan aturan. */
    public function test_pesan_aturan_galat_teknis_mengembalikan_null(): void
    {
        $galat = new QueryException('sqlite', 'insert into x values (?)', [1], new \Exception('NOT NULL constraint failed'));

        $this->assertInstanceOf(RuntimeException::class, $galat);
        $this->assertNull(StokService::pesanAturan($galat));
    }

    // =====================================================================
    // 2.4 pastikanTidakMelebihiDiminta, 2.5 sesuaikanHold
    // =====================================================================

    /** 2.4 J1 */
    public function test_pastikan_tidak_melebihi_tanpa_rincian(): void
    {
        $this->stok()->pastikanTidakMelebihiDiminta($this->permintaanKosong('menunggu_kasubbag'), [1 => 99]);

        $this->addToAssertionCount(1);
    }

    /** 2.4 J2: rincian yang tidak diberi jumlah (null) dilewati, bukan dianggap nol. */
    public function test_pastikan_tidak_melebihi_melewati_jumlah_null(): void
    {
        $tim = $this->buatTim();
        $permintaan = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $this->buatBarang(), 'diminta' => 2]], 'menunggu_kasubbag');
        $detail = $permintaan->detail->first();

        $this->stok()->pastikanTidakMelebihiDiminta($permintaan, [$detail->id => null]);

        $this->addToAssertionCount(1);
    }

    /** 2.5 J1 */
    public function test_sesuaikan_hold_tanpa_rincian(): void
    {
        $barang = $this->buatBarang(stokFisik: 10, stokHold: 4);

        $this->stok()->sesuaikanHold($this->permintaanKosong('siap_diproses'));

        $this->assertSame(4, $barang->refresh()->stok_hold);
    }

    /** 2.5 J2: rincian yang belum diputuskan tidak melepas kunci apa pun. */
    public function test_sesuaikan_hold_melewati_rincian_tanpa_jumlah_final(): void
    {
        $tim    = $this->buatTim();
        $barang = $this->buatBarang(stokFisik: 30, stokHold: 10);
        $permintaan = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $barang, 'diminta' => 10]], 'siap_diproses');

        $this->stok()->sesuaikanHold($permintaan);

        $this->assertSame(10, $barang->refresh()->stok_hold);
    }

    // =====================================================================
    // 2.6 konversi, 2.8 terbitkanNomorBon
    // =====================================================================

    /** 2.6 J1: tanpa rincian tidak ada mutasi, tetapi nomor bon tetap diterbitkan di awal transaksi. */
    public function test_konversi_tanpa_rincian_tidak_mencatat_mutasi(): void
    {
        $permintaan = $this->permintaanKosong();

        $this->stok()->konversi($permintaan, $this->buatPengguna('petugas_gudang')->id);

        $this->assertSame(0, MutasiStok::count());
        $this->assertSame('001', $permintaan->refresh()->nomor_bon);
    }

    /** 2.6 J4: rincian yang disetujui nol dilewati; stok dan buku besar barang itu tidak berubah. */
    public function test_konversi_melewati_rincian_berjumlah_nol(): void
    {
        $tim    = $this->buatTim();
        $barang = $this->buatBarang(stokFisik: 30, stokHold: 0);
        $permintaan = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $barang, 'diminta' => 5, 'final' => 0]], 'siap_diambil');

        $this->stok()->konversi($permintaan, $this->buatPengguna('petugas_gudang')->id);

        $this->assertSame(0, MutasiStok::where('barang_id', $barang->id)->count());
        $this->assertSame(30, $barang->refresh()->stok_fisik);
    }

    /** 2.8 J1: permintaan yang sudah bernomor bon tidak menghabiskan nomor baru. */
    public function test_nomor_bon_yang_sudah_ada_tidak_berganti(): void
    {
        $tim    = $this->buatTim();
        $barang = $this->buatBarang(stokFisik: 30, stokHold: 2);
        $permintaan = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $barang, 'diminta' => 2]], 'siap_diambil', [
            'nomor_bon' => '007',
            'tahun_bon' => (int) now()->year,
        ]);

        $this->stok()->konversi($permintaan, $this->buatPengguna('petugas_gudang')->id);

        $this->assertSame('007', $permintaan->refresh()->nomor_bon);
        $this->assertSame('007', MutasiStok::where('barang_id', $barang->id)->sole()->nomor_dasar);
    }

    // =====================================================================
    // 2.9 hitungUlangSaldo, 2.10 tanggalMutasiTerakhir
    // =====================================================================

    /** 2.9 J1: tanpa baris buku besar, stok fisik menjadi saldo awal yang diberikan. */
    public function test_hitung_ulang_saldo_tanpa_mutasi(): void
    {
        $barang = $this->buatBarang(stokFisik: 5);

        StokService::hitungUlangSaldo($barang->id, 7);

        $this->assertSame(7, $barang->refresh()->stok_fisik);
    }

    /** 2.9 J2: baris pertama sudah membuat saldo negatif → ditolak sebelum apa pun ditulis. */
    public function test_hitung_ulang_saldo_negatif_ditolak(): void
    {
        $barang = $this->buatBarang(stokFisik: 0);
        MutasiStok::create([
            'barang_id' => $barang->id, 'tanggal' => now()->toDateString(), 'jenis' => 'keluar', 'jumlah' => -3,
            'saldo_sesudah' => 0, 'sumber' => 'pemakaian', 'petugas_id' => $this->buatPengguna('petugas_gudang')->id,
        ]);

        try {
            StokService::hitungUlangSaldo($barang->id, 0);
            $this->fail('Saldo negatif seharusnya ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sisa stok menjadi negatif', $e->getMessage());
        }

        $this->assertSame(0, $barang->refresh()->stok_fisik);
    }

    /** 2.9 J4: baris yang saldonya sudah benar tidak ditulis ulang. */
    public function test_hitung_ulang_saldo_tidak_menulis_baris_yang_sudah_benar(): void
    {
        $barang = $this->buatBarang(stokFisik: 0);
        $this->stok()->tambah($barang->id, 5, 'pembelian', 'NOTA-1', null, $this->buatPengguna('petugas_gudang')->id);

        DB::enableQueryLog();
        StokService::hitungUlangSaldo($barang->id, 0);
        $tulis = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'update') && str_contains($q['query'], 'mutasi_stok'));
        DB::disableQueryLog();

        $this->assertCount(0, $tulis, 'Baris yang saldonya tidak berubah tidak boleh ditulis ulang.');
        $this->assertSame(5, $barang->refresh()->stok_fisik);
    }

    /** 2.10 J1 */
    public function test_tanggal_mutasi_terakhir_barang_bermutasi(): void
    {
        $barang = $this->buatBarang(stokFisik: 0);
        $petugas = $this->buatPengguna('petugas_gudang')->id;
        $this->stok()->tambah($barang->id, 2, 'pembelian', 'N-1', null, $petugas, '2026-01-05');
        $this->stok()->tambah($barang->id, 3, 'pembelian', 'N-2', null, $petugas, '2026-02-10');

        $this->assertSame('2026-02-10', StokService::tanggalMutasiTerakhir($barang->id));
    }

    /** 2.10 J2 */
    public function test_tanggal_mutasi_terakhir_barang_tanpa_mutasi(): void
    {
        $this->assertNull(StokService::tanggalMutasiTerakhir($this->buatBarang()->id));
    }

    // =====================================================================
    // 2.11 sapuBilaPerlu, 2.12 sapu, 2.13 tahapTerakhir
    // =====================================================================

    /** Permintaan berjalan yang batas waktunya sudah lewat, beserta barang terkuncinya. */
    private function kedaluwarsa(): array
    {
        $tim    = $this->buatTim(uniqid('Tim '));
        $barang = $this->buatBarang(stokFisik: 20, stokHold: 5);
        $permintaan = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $barang, 'diminta' => 5]], 'menunggu_kasubbag', [
            'hold_expired_at' => now()->subHour(),
        ]);

        return [$permintaan, $barang];
    }

    /** Mengubah baris permintaan sesudah daftar dibaca, sebelum transaksi sapuan menguncinya. */
    private function ubahSesudahDibaca(callable $ubah): void
    {
        $sekali = false;
        PermintaanBarang::retrieved(function (PermintaanBarang $model) use (&$sekali, $ubah): void {
            if ($sekali) {
                return;
            }

            $sekali = true;
            $ubah(DB::table('permintaan_barang')->where('id', $model->id));
        });
    }

    /** 2.11 J1 dan J2: panggilan pertama menyapu; panggilan berikutnya dalam jeda tidak. */
    public function test_sapu_bila_perlu_dalam_jeda_tidak_menyapu(): void
    {
        $layanan = app(KedaluwarsaService::class);

        [$pertama] = $this->kedaluwarsa();
        $this->assertCount(1, $layanan->sapuBilaPerlu());
        $this->assertSame('kedaluwarsa', $pertama->refresh()->status);

        [$kedua] = $this->kedaluwarsa();
        $this->assertCount(0, $layanan->sapuBilaPerlu());
        $this->assertSame('menunggu_kasubbag', $kedua->refresh()->status, 'Dalam jeda 60 detik tidak boleh ada sapuan kedua.');
    }

    /** 2.12 J2: baris terhapus sesudah daftar dibaca (node 4 benar). */
    public function test_sapu_melewati_permintaan_yang_terhapus_sejak_daftar_dibaca(): void
    {
        [$permintaan, $barang] = $this->kedaluwarsa();
        $this->ubahSesudahDibaca(fn ($baris) => $baris->delete());

        $hasil = app(KedaluwarsaService::class)->sapu();

        $this->assertCount(0, $hasil);
        $this->assertSame(0, RiwayatPersetujuan::count());
        $this->assertSame(5, $barang->refresh()->stok_hold);
    }

    /** 2.12 J4: batas waktu dikosongkan sesudah daftar dibaca (node 6 benar). */
    public function test_sapu_melewati_permintaan_yang_batasnya_dikosongkan(): void
    {
        [$permintaan, $barang] = $this->kedaluwarsa();
        $this->ubahSesudahDibaca(fn ($baris) => $baris->update(['hold_expired_at' => null]));

        $hasil = app(KedaluwarsaService::class)->sapu();

        $this->assertCount(0, $hasil);
        $this->assertSame('menunggu_kasubbag', $permintaan->refresh()->status);
        $this->assertSame(5, $barang->refresh()->stok_hold);
    }

    /** 2.12 J5: batas waktu diperpanjang sesudah daftar dibaca (node 7 benar). */
    public function test_sapu_melewati_permintaan_yang_batasnya_diperpanjang(): void
    {
        [$permintaan, $barang] = $this->kedaluwarsa();
        $this->ubahSesudahDibaca(fn ($baris) => $baris->update(['hold_expired_at' => now()->addDay()]));

        $hasil = app(KedaluwarsaService::class)->sapu();

        $this->assertCount(0, $hasil);
        $this->assertSame('menunggu_kasubbag', $permintaan->refresh()->status);
        $this->assertSame(5, $barang->refresh()->stok_hold);
    }

    /** 2.12 J8: exception di dalam transaksi dilaporkan gagal dan seluruh perubahan digulung. */
    public function test_sapu_melaporkan_kegagalan_transaksi(): void
    {
        [$permintaan, $barang] = $this->kedaluwarsa();

        $this->mock(StokService::class, function ($mock): void {
            $mock->shouldReceive('release')->once()->andThrow(new RuntimeException('kunci baris gagal'));
        });

        $hasil = app(KedaluwarsaService::class)->sapu();

        $this->assertCount(1, $hasil);
        $this->assertFalse($hasil->first()['berhasil']);
        $this->assertSame('kunci baris gagal', $hasil->first()['pesan']);
        $this->assertSame('menunggu_kasubbag', $permintaan->refresh()->status);
        $this->assertSame(0, RiwayatPersetujuan::count());
        $this->assertSame(5, $barang->refresh()->stok_hold);
    }

    /** 2.13 J6 */
    public function test_tahap_terakhir_status_lain_memakai_bawaan(): void
    {
        $this->assertSame('ketua_tim', KedaluwarsaService::tahapTerakhir('bermasalah'));
    }
}
