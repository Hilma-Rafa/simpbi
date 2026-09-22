<?php

namespace Tests\Feature;

use App\Filament\Pages\KatalogBarang;
use App\Models\PermintaanBarang;
use App\Services\StokService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit A-017 (barang nonaktif dan pesan galat) dan A-020 (bentrok kode
 * permintaan) pada pengajuan dari Katalog Barang.
 */
class PengajuanKatalogAturanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN_UMUM = 'Coba lagi, atau hubungi Sub-Bagian Umum bila berulang.';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function ajukan(array $items)
    {
        return Livewire::test(KatalogBarang::class)->callAction('ajukan', [
            'nama_pemohon' => 'Pemohon Uji',
            'nip_pemohon'  => '199001012020121001',
            'items'        => $items,
            'keperluan'    => 'Keperluan uji',
        ]);
    }

    private function gagal(string $isi): Notification
    {
        return Notification::make()->title('Permintaan gagal diajukan')->body($isi)->danger();
    }

    // ------------------------------------------------------------------
    // A-017
    // ------------------------------------------------------------------

    public function test_barang_nonaktif_ditolak_tanpa_permintaan_dan_tanpa_kunci(): void
    {
        $anggota = $this->buatPengguna('tim', $this->buatTim());
        $nonaktif = $this->buatBarang(stokFisik: 20, stokHold: 0, tambahan: ['status_aktif' => false, 'nama_barang' => 'Barang Lama']);
        $aktif = $this->buatBarang(stokFisik: 20);

        $this->actingAs($anggota);
        $this->ajukan([
            ['barang_id' => $aktif->id, 'jumlah' => 2],
            ['barang_id' => $nonaktif->id, 'jumlah' => 1],
        ]);

        Notification::assertNotified($this->gagal('Barang Lama tidak tersedia untuk diminta.'));
        $this->assertSame(0, PermintaanBarang::count());
        $this->assertSame(0, $aktif->refresh()->stok_hold, 'Kunci pada barang lain ikut dibatalkan.');
        $this->assertSame(0, $nonaktif->refresh()->stok_hold);
    }

    public function test_barang_aktif_tetap_berhasil_diajukan(): void
    {
        $anggota = $this->buatPengguna('tim', $this->buatTim());
        $barang = $this->buatBarang(stokFisik: 20);

        $this->actingAs($anggota);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 4]]);

        $this->assertSame(1, PermintaanBarang::count());
        $this->assertSame(4, $barang->refresh()->stok_hold);
    }

    public function test_stok_tidak_cukup_tetap_menampilkan_pesan_bisnisnya(): void
    {
        $anggota = $this->buatPengguna('tim', $this->buatTim());
        $barang = $this->buatBarang(stokFisik: 5, stokHold: 3, tambahan: ['nama_barang' => 'Kertas Uji']);

        $this->actingAs($anggota);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 4]]);

        Notification::assertNotified($this->gagal('Stok Kertas Uji tidak mencukupi. Tersedia 2 Buah.'));
        $this->assertSame(0, PermintaanBarang::count());
    }

    public function test_kegagalan_teknis_menampilkan_pesan_umum_bukan_teks_sql(): void
    {
        $anggota = $this->buatPengguna('tim', $this->buatTim());
        $barang = $this->buatBarang(stokFisik: 20);

        $sql = new QueryException('mysql', 'select * from barang_persediaan where rahasia = ?', ['x'], new \Exception('SQLSTATE[HY000]: General error'));
        $this->mock(StokService::class, function ($mock) use ($sql): void {
            $mock->shouldReceive('hold')->andThrow($sql);
        });

        $this->actingAs($anggota);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 2]]);

        Notification::assertNotified($this->gagal(self::PESAN_UMUM));
        Notification::assertNotNotified($this->gagal($sql->getMessage()));
        $this->assertSame(0, PermintaanBarang::count());
    }

    // ------------------------------------------------------------------
    // A-020: kode permintaan
    // ------------------------------------------------------------------

    /** Menyimulasikan pengajuan lain yang mengambil kode yang sama tepat sebelum penyimpanan. */
    private function rebutKode(int $kali): \Closure
    {
        $sudah = new \stdClass;
        $sudah->n = 0;

        PermintaanBarang::creating(function (PermintaanBarang $model) use ($sudah, $kali): void {
            if ($sudah->n >= $kali) {
                return;
            }

            $sudah->n++;

            PermintaanBarang::withoutEvents(fn () => PermintaanBarang::create([
                'kode_permintaan' => $model->kode_permintaan,
                'tim_pemohon_id'  => $model->tim_pemohon_id,
                'pengaju_id'      => $model->pengaju_id,
                'nama_pemohon'    => 'Pengajuan Lain',
                'status'          => 'menunggu_ketua',
            ]));
        });

        return fn (): int => $sudah->n;
    }

    public function test_bentrok_kode_sekali_diulang_lalu_berhasil(): void
    {
        $anggota = $this->buatPengguna('tim', $this->buatTim());
        $barang = $this->buatBarang(stokFisik: 20);
        $bentrok = $this->rebutKode(1);

        $this->actingAs($anggota);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 3]]);

        $this->assertSame(1, $bentrok(), 'Bentrok terjadi tepat sekali.');
        // Pada pemasangan sungguhan baris pengajuan lain sudah ter-commit sehingga
        // kode baru bernomor berikutnya; di sini baris penyusup ikut tergulung
        // bersama transaksi uji, jadi yang dibuktikan adalah pengulangannya: tetap
        // satu permintaan, tanpa kunci ganda, dan tanpa pesan gagal.
        $this->assertSame(1, PermintaanBarang::where('nama_pemohon', 'Pemohon Uji')->count());
        $this->assertSame(3, $barang->refresh()->stok_hold, 'Kunci tidak boleh terpasang dua kali.');
        Notification::assertNotNotified($this->gagal(self::PESAN_UMUM));
    }

    public function test_kode_berikutnya_dibangun_dari_nomor_terbesar_yang_ada(): void
    {
        $tim = $this->buatTim();
        $anggota = $this->buatPengguna('tim', $tim);
        $tahun = now()->format('Y');

        // Celah: 0001 dan 0007 ada, 0002 s.d. 0006 tidak.
        foreach ([1, 7] as $urut) {
            PermintaanBarang::create([
                'kode_permintaan' => sprintf('PB-%s-%04d', $tahun, $urut),
                'tim_pemohon_id'  => $tim->id,
                'pengaju_id'      => $anggota->id,
                'nama_pemohon'    => 'Lama',
                'status'          => 'selesai',
            ]);
        }

        $barang = $this->buatBarang(stokFisik: 20);
        $this->actingAs($anggota);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 1]]);

        $this->assertTrue(PermintaanBarang::where('kode_permintaan', "PB-{$tahun}-0008")->exists());
    }

    public function test_bentrok_kode_berulang_berakhir_pesan_umum_tanpa_kunci(): void
    {
        $anggota = $this->buatPengguna('tim', $this->buatTim());
        $barang = $this->buatBarang(stokFisik: 20);
        $bentrok = $this->rebutKode(10);

        $this->actingAs($anggota);
        $this->ajukan([['barang_id' => $barang->id, 'jumlah' => 3]]);

        $this->assertSame(3, $bentrok(), 'Percobaan dibatasi tiga kali.');
        $this->assertSame(0, PermintaanBarang::where('nama_pemohon', 'Pemohon Uji')->count());
        $this->assertSame(0, $barang->refresh()->stok_hold);
        Notification::assertNotified($this->gagal(self::PESAN_UMUM));
    }
}
