<?php

namespace Tests\Feature;

use App\Filament\Resources\BarangPersediaans\Pages\ListBarangPersediaans;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporBarangPersediaan;
use App\Services\Impor\PembuatTemplate;
use App\Services\StokService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Impor Barang Persediaan: data induk saja.
 *
 * Invarian yang paling dijaga (G-5): impor tidak pernah menulis stok_fisik,
 * stok_hold, maupun mutasi_stok. Barang baru lahir berstok nol; kolom stok
 * pada berkas diabaikan dan dicatat.
 */
class ImporBarangPersediaanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const TAJUK = ['Kode Kategori', 'Kode Barang', 'Nama Barang', 'Satuan', 'Stok Minimum'];

    private Kategori $atk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->atk = Kategori::create(['kode_kategori' => '1010301001', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
    }

    /** @param list<list<mixed>> $baris */
    private function impor(array $baris, array $tajuk = self::TAJUK): HasilImpor
    {
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_barang_') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($lintasan);
        $writer->addRow(Row::fromValues($tajuk));
        foreach ($baris as $b) {
            $writer->addRow(Row::fromValues($b));
        }
        $writer->close();

        $hasil = app(ImporBarangPersediaan::class)->jalankan($lintasan);
        @unlink($lintasan);

        return $hasil;
    }

    private function barangLama(string $kode = '000122', int $fisik = 40, int $hold = 5): BarangPersediaan
    {
        return BarangPersediaan::create([
            'kategori_id' => $this->atk->id, 'kode_barang' => $kode, 'nama_barang' => 'Binder Lama', 'satuan' => 'Dus',
            'stok_fisik' => $fisik, 'stok_hold' => $hold, 'stok_minimum' => 3, 'status_aktif' => true,
        ]);
    }

    // =====================================================================
    // DATA INDUK
    // =====================================================================

    public function test_barang_baru_ditambahkan_dengan_kode_enam_digit(): void
    {
        $hasil = $this->impor([['1010301001', '000122', 'BINDER CLIPS NO. 105', 'Dus', '2']]);

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $barang = BarangPersediaan::sole();
        $this->assertSame('000122', $barang->kode_barang);
        $this->assertSame($this->atk->id, $barang->kategori_id);
        $this->assertSame(2, $barang->stok_minimum);
        $this->assertTrue((bool) $barang->status_aktif);
    }

    public function test_barang_yang_sudah_ada_diperbarui_bukan_digandakan(): void
    {
        $lama = $this->barangLama();

        $hasil = $this->impor([['1010301001', '000122', 'BINDER CLIPS NO. 105', 'Kotak', '']]);

        $this->assertSame(1, $hasil->diperbarui);
        $this->assertSame(1, BarangPersediaan::count());
        $lama->refresh();
        $this->assertSame('BINDER CLIPS NO. 105', $lama->nama_barang);
        $this->assertSame('Kotak', $lama->satuan);
        // Stok Minimum dikosongkan: nilai tersimpan tidak diubah.
        $this->assertSame(3, $lama->stok_minimum);
    }

    public function test_kode_sama_pada_kategori_lain_adalah_barang_lain(): void
    {
        $this->barangLama();
        Kategori::create(['kode_kategori' => '1010301012', 'kode_akun' => '117111', 'nama_kategori' => 'Staples', 'tipe' => 'persediaan']);

        $hasil = $this->impor([['1010301012', '000122', 'Isi Staples', 'Dus', '0']]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertSame(2, BarangPersediaan::where('kode_barang', '000122')->count());
    }

    public function test_barang_di_luar_berkas_tidak_dihapus_dan_status_aktif_tidak_ditimpa(): void
    {
        $nonaktif = $this->barangLama('000200');
        $nonaktif->forceFill(['status_aktif' => false])->save();
        $this->barangLama('000300');

        $this->impor([['1010301001', '000200', 'Binder Diperbarui', 'Dus', '0']]);

        $this->assertSame(2, BarangPersediaan::count());
        $this->assertFalse((bool) $nonaktif->refresh()->status_aktif);
    }

    // =====================================================================
    // STOK — INVARIAN G-5
    // =====================================================================

    public function test_stok_barang_lama_tidak_berubah_dan_tidak_ada_mutasi_baru(): void
    {
        $lama = $this->barangLama('000122', 40, 5);
        app(StokService::class)->tambah($lama->id, 10, 'pembelian', 'NOTA-1', null, $this->buatPengguna('petugas_gudang')->id, '2026-01-10');
        $lama->refresh();
        $fisik = $lama->stok_fisik;
        $jumlahMutasi = MutasiStok::count();
        $mutasiSebelum = MutasiStok::orderBy('id')->get()->toArray();

        $this->impor(
            [['1010301001', '000122', 'Binder Baru', 'Dus', '1', '999', '999']],
            [...self::TAJUK, 'Stok Fisik', 'Stok Terkunci'],
        );

        $lama->refresh();
        $this->assertSame($fisik, $lama->stok_fisik);
        $this->assertSame(5, $lama->stok_hold);
        $this->assertSame($jumlahMutasi, MutasiStok::count());
        $this->assertSame($mutasiSebelum, MutasiStok::orderBy('id')->get()->toArray());
    }

    public function test_barang_baru_lahir_berstok_nol_tanpa_mutasi(): void
    {
        $this->impor(
            [['1010301001', '000122', 'Binder', 'Dus', '0', '75']],
            [...self::TAJUK, 'Stok Fisik'],
        );

        $barang = BarangPersediaan::sole();
        $this->assertSame(0, $barang->stok_fisik);
        $this->assertSame(0, $barang->stok_hold);
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_kolom_stok_pada_berkas_diabaikan_dan_dicatat(): void
    {
        $hasil = $this->impor(
            [['1010301001', '000122', 'Binder', 'Dus', '0', '75']],
            [...self::TAJUK, 'Stok Fisik'],
        );

        $this->assertTrue($hasil->adaCatatan());
        $this->assertStringContainsString('stok fisik', $hasil->catatan[0]);
        $this->assertStringContainsString('Stok Masuk', $hasil->catatan[0]);
    }

    public function test_tanpa_kolom_stok_tidak_ada_catatan(): void
    {
        $hasil = $this->impor([['1010301001', '000122', 'Binder', 'Dus', '0']]);

        $this->assertFalse($hasil->adaCatatan());
    }

    // =====================================================================
    // VALIDASI PER BARIS
    // =====================================================================

    public function test_kategori_belum_terdaftar_ditolak(): void
    {
        $hasil = $this->impor([['1019999999', '000122', 'Binder', 'Dus', '0']]);

        $this->assertStringContainsString('belum terdaftar sebagai kategori persediaan', $hasil->galat[0]);
        $this->assertSame(0, BarangPersediaan::count());
    }

    public function test_kategori_aset_tetap_ditolak(): void
    {
        Kategori::create(['kode_kategori' => 'PM', 'kode_akun' => '1.3.2', 'nama_kategori' => 'Peralatan dan Mesin', 'tipe' => 'aset_tetap']);

        $hasil = $this->impor([['PM', '000122', 'Laptop', 'Unit', '0']]);

        $this->assertStringContainsString('kategori persediaan', $hasil->galat[0]);
        $this->assertSame(0, BarangPersediaan::count());
    }

    /** Sel angka 000122 terbaca 122 oleh lembar sebar; ditolak dengan pesan khusus, tidak ditambal. */
    public function test_kode_yang_kehilangan_nol_di_depan_ditolak_dengan_pesan_khusus(): void
    {
        $hasil = $this->impor([['1010301001', 122, 'Binder', 'Dus', '0']]);

        $this->assertStringContainsString('kehilangan nol di depan', $hasil->galat[0]);
        $this->assertStringContainsString('000122', $hasil->galat[0]);
        $this->assertSame(0, BarangPersediaan::count());
    }

    /** @return array<string,array{0:string}> */
    public static function kodeTidakSah(): array
    {
        return ['tujuh digit' => ['1234567'], 'berhuruf' => ['A-0001'], 'kosong' => ['']];
    }

    #[DataProvider('kodeTidakSah')]
    public function test_kode_barang_tidak_sah_ditolak(string $kode): void
    {
        $hasil = $this->impor([['1010301001', $kode, 'Binder', 'Dus', '0']]);

        $this->assertSame(0, $hasil->berhasil());
        $this->assertCount(1, $hasil->galat);
        $this->assertSame(0, BarangPersediaan::count());
    }

    public function test_kolom_wajib_lain_dan_stok_minimum_divalidasi(): void
    {
        $hasil = $this->impor([
            ['', '000101', 'Binder', 'Dus', '0'],
            ['1010301001', '000102', '', 'Dus', '0'],
            ['1010301001', '000103', 'Binder', '', '0'],
            ['1010301001', '000104', 'Binder', 'Dus', '-1'],
            ['1010301001', '000105', 'Binder', 'Dus', 'dua'],
        ]);

        $this->assertCount(5, $hasil->galat);
        $this->assertStringContainsString('Stok Minimum', $hasil->galat[3]);
        $this->assertSame(0, BarangPersediaan::count());
    }

    public function test_barang_ganda_dalam_satu_berkas_ditolak_yang_kedua(): void
    {
        $hasil = $this->impor([
            ['1010301001', '000122', 'Binder Pertama', 'Dus', '0'],
            ['1010301001', '000122', 'Binder Kedua', 'Dus', '0'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertStringContainsString('baris 2', $hasil->galat[0]);
        $this->assertSame('Binder Pertama', BarangPersediaan::sole()->nama_barang);
    }

    public function test_baris_bermasalah_tidak_menahan_baris_lain(): void
    {
        $hasil = $this->impor([
            ['1010301001', '122', 'Binder', 'Dus', '0'],
            ['1010301001', '000123', 'Binder 111', 'Dus', '0'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertStringContainsString('Baris 2', $hasil->galat[0]);
    }

    public function test_bentrok_unik_saat_dibuat_menjadi_galat_baris(): void
    {
        BarangPersediaan::creating(function (BarangPersediaan $model): void {
            if (BarangPersediaan::where('kode_barang', $model->kode_barang)->exists()) {
                return;
            }
            BarangPersediaan::withoutEvents(fn () => BarangPersediaan::create([...$model->getAttributes(), 'nama_barang' => 'Penyusup']));
        });

        $hasil = $this->impor([['1010301001', '000122', 'Binder', 'Dus', '0']]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('sudah dipakai', $hasil->galat[0]);
    }

    public function test_template_yang_diunduh_dapat_diimpor_kembali(): void
    {
        $respons = app(PembuatTemplate::class)->buat(ImporBarangPersediaan::JUDUL, ImporBarangPersediaan::kolom(), 'Template-Impor-Barang-Persediaan.xlsx');

        $hasil = app(ImporBarangPersediaan::class)->jalankan($respons->getFile()->getPathname());

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame('000122', BarangPersediaan::sole()->kode_barang);
        $this->assertSame(0, BarangPersediaan::sole()->stok_fisik);
    }

    // =====================================================================
    // AKSES
    // =====================================================================

    public function test_admin_dan_kasubbag_melihat_tombol_impor(): void
    {
        Filament::setCurrentPanel('admin');

        foreach (['admin', 'kasubbag'] as $peran) {
            $this->actingAs($this->buatPengguna($peran, null, ['email' => $peran . '.impor.barang@bps.go.id']));

            Livewire::test(ListBarangPersediaans::class)->assertTableActionExists('impor');
        }
    }

    /** @return array<string,array{0:string}> */
    public static function peranTanpaAkses(): array
    {
        return ['petugas gudang' => ['petugas_gudang'], 'ketua tim' => ['ketua_tim'], 'tim' => ['tim']];
    }

    #[DataProvider('peranTanpaAkses')]
    public function test_peran_lain_tidak_dapat_membuka_halaman_impor(string $peran): void
    {
        $tim = $peran === 'petugas_gudang' ? null : $this->buatTim();

        $this->actingAs($this->lengkapiAkun($this->buatPengguna($peran, $tim)))
            ->get('/admin/barang-persediaans')
            ->assertForbidden();
    }
}
