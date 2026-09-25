<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporStokMasuk;
use App\Services\Impor\PembuatTemplate;
use App\Services\KartuKendaliService;
use App\Services\StokService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Impor Stok Masuk berjenis Stok Awal (semula Impor Stok Awal, F.1): setiap
 * baris sah dicatat lewat StokService::tambah() bersumber Stok Awal, sehingga
 * stok fisik, buku besar mutasi, dan kartu kendali tetap konsisten; barang yang
 * sudah pernah memiliki stok ditolak. Sejak Impor Stok Masuk, tanggal dan nomor
 * dasar ikut per baris berkas, dan jenis dipilih di pop-up.
 */
class ImporStokAwalTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const TAJUK = ['Kode Kategori', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Tanggal', 'Nomor Dasar'];

    private Kategori $atk;

    private int $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-25 10:00:00');
        $this->atk = Kategori::create(['kode_kategori' => '1010301001', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
        $this->gudang = $this->buatPengguna('petugas_gudang')->id;
    }

    /** @param list<list<mixed>> $baris */
    private function berkas(array $baris): string
    {
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_stok_awal_') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($lintasan);
        $writer->addRow(Row::fromValues(self::TAJUK));
        foreach ($baris as $b) {
            $writer->addRow(Row::fromValues($b));
        }
        $writer->close();

        return $lintasan;
    }

    /**
     * Baris ditulis empat kolom seperti semula; tanggal dan nomor dasar yang
     * dulu diisi di pop-up kini ditambahkan ke setiap baris.
     *
     * @param list<list<mixed>> $baris
     */
    private function impor(array $baris, string $tanggal = '2026-01-01', ?string $nomor = null): HasilImpor
    {
        $lintasan = $this->berkas(array_map(fn (array $b) => [...$b, $tanggal, (string) $nomor], $baris));
        $hasil = app(ImporStokMasuk::class)->jalankan($lintasan, 'stok_awal', $this->gudang);
        @unlink($lintasan);

        return $hasil;
    }

    private function barang(string $kode = '000122', array $tambahan = []): BarangPersediaan
    {
        return BarangPersediaan::create([
            'kategori_id' => $this->atk->id, 'kode_barang' => $kode, 'nama_barang' => 'Binder ' . $kode, 'satuan' => 'Dus',
            'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 0, 'status_aktif' => true, ...$tambahan,
        ]);
    }

    // =====================================================================
    // PENCATATAN
    // =====================================================================

    public function test_impor_menambah_stok_dan_mencatat_mutasi_stok_awal(): void
    {
        $barang = $this->barang();

        $hasil = $this->impor([['1010301001', '000122', 'Binder', '34']], '2026-02-03', 'BA-01');

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame(34, $barang->refresh()->stok_fisik);
        $mutasi = MutasiStok::sole();
        $this->assertSame('stok_awal', $mutasi->sumber);
        $this->assertSame('masuk', $mutasi->jenis);
        $this->assertSame(34, $mutasi->jumlah);
        $this->assertSame(34, $mutasi->saldo_sesudah);
        $this->assertSame('BA-01', $mutasi->nomor_dasar);
        $this->assertSame($this->gudang, $mutasi->petugas_id);
        $this->assertSame('2026-02-03', $mutasi->tanggal->toDateString());
    }

    /** Tanggal 1 Januari: saldo tampil sebagai Stok Awal kartu, bukan baris transaksi. */
    public function test_kartu_kendali_menampilkan_saldo_awal_1_januari(): void
    {
        $barang = $this->barang();
        $this->impor([['1010301001', '000122', '', '34']], '2026-01-01');

        $kartu = app(KartuKendaliService::class)->data($barang->refresh(), 2026);

        $this->assertSame(34, $kartu['awal']);
        $this->assertCount(0, $kartu['mutasi']);
        $this->assertSame(34, $kartu['akhir']);
    }

    public function test_kartu_kendali_menampilkan_saldo_benar_setelah_transaksi_berikutnya(): void
    {
        $barang = $this->barang();
        $this->impor([['1010301001', '000122', '', '34']], '2026-02-03');
        app(StokService::class)->tambah($barang->id, 6, 'pembelian', 'NOTA-9', null, $this->gudang, '2026-03-01');

        $kartu = app(KartuKendaliService::class)->data($barang->refresh(), 2026);

        $this->assertSame(0, $kartu['awal']);
        $this->assertSame([34, 40], $kartu['mutasi']->pluck('saldo_sesudah')->all());
        $this->assertSame(40, $kartu['akhir']);
        $this->assertSame(40, $barang->stok_fisik);
    }

    // =====================================================================
    // PENGAMAN STOK GANDA
    // =====================================================================

    public function test_berkas_yang_sama_diimpor_dua_kali_tidak_menggandakan_stok(): void
    {
        $barang = $this->barang();
        $lintasan = $this->berkas([['1010301001', '000122', '', '34', '2026-01-01', '']]);

        $pertama = app(ImporStokMasuk::class)->jalankan($lintasan, 'stok_awal', $this->gudang);
        $kedua = app(ImporStokMasuk::class)->jalankan($lintasan, 'stok_awal', $this->gudang);
        @unlink($lintasan);

        $this->assertSame(1, $pertama->ditambah);
        $this->assertSame(0, $kedua->ditambah);
        $this->assertStringContainsString('sudah memiliki stok atau transaksi', $kedua->galat[0]);
        $this->assertSame(34, $barang->refresh()->stok_fisik);
        $this->assertSame(1, MutasiStok::count());
    }

    public function test_barang_berstok_fisik_tanpa_mutasi_ditolak(): void
    {
        $barang = $this->barang('000122', ['stok_fisik' => 5]);

        $hasil = $this->impor([['1010301001', '000122', '', '34']]);

        $this->assertStringContainsString('sudah memiliki stok atau transaksi', $hasil->galat[0]);
        $this->assertSame(5, $barang->refresh()->stok_fisik);
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_barang_yang_pernah_bermutasi_walau_kini_nol_ditolak(): void
    {
        $barang = $this->barang();
        app(StokService::class)->tambah($barang->id, 3, 'pembelian', 'NOTA-1', null, $this->gudang, '2026-01-05');
        $keluar = $this->buatPermintaan($tim = $this->buatTim(), $this->buatPengguna('tim', $tim), [['barang' => $barang->refresh(), 'diminta' => 3]], status: 'siap_diambil');
        app(StokService::class)->konversi($keluar, $this->gudang);
        $this->assertSame(0, $barang->refresh()->stok_fisik);

        $hasil = $this->impor([['1010301001', '000122', '', '34']]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertSame(0, $barang->refresh()->stok_fisik);
        $this->assertSame(2, MutasiStok::count());
    }

    // =====================================================================
    // VALIDASI PER BARIS
    // =====================================================================

    public function test_barang_tak_terdaftar_ditolak(): void
    {
        $hasil = $this->impor([['1010301001', '000999', '', '34']]);

        $this->assertStringContainsString('belum terdaftar', $hasil->galat[0]);
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_kategori_aset_tetap_ditolak(): void
    {
        $aset = Kategori::create(['kode_kategori' => 'PM', 'kode_akun' => '1.3.2', 'nama_kategori' => 'Peralatan dan Mesin', 'tipe' => 'aset_tetap']);
        BarangPersediaan::create([
            'kategori_id' => $aset->id, 'kode_barang' => '000122', 'nama_barang' => 'Salah Tempat', 'satuan' => 'Unit',
            'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ]);

        $hasil = $this->impor([['PM', '000122', '', '34']]);

        $this->assertStringContainsString('belum terdaftar sebagai barang persediaan', $hasil->galat[0]);
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_kode_yang_kehilangan_nol_di_depan_ditolak(): void
    {
        $this->barang();

        $hasil = $this->impor([['1010301001', 122, '', '34']]);

        $this->assertStringContainsString('kehilangan nol di depan', $hasil->galat[0]);
        $this->assertSame(0, MutasiStok::count());
    }

    /** @return array<string,array{0:mixed}> */
    public static function jumlahTidakSah(): array
    {
        return ['nol' => ['0'], 'negatif' => ['-5'], 'bukan angka' => ['tiga puluh'], 'pecahan' => ['2.5'], 'kosong' => ['']];
    }

    #[DataProvider('jumlahTidakSah')]
    public function test_jumlah_tidak_sah_ditolak(mixed $jumlah): void
    {
        $barang = $this->barang();

        $hasil = $this->impor([['1010301001', '000122', '', $jumlah]]);

        $this->assertStringContainsString('Jumlah berisi', $hasil->galat[0]);
        $this->assertSame(0, $barang->refresh()->stok_fisik);
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_baris_ganda_dalam_satu_berkas_ditolak_yang_kedua(): void
    {
        $barang = $this->barang();

        $hasil = $this->impor([
            ['1010301001', '000122', '', '34'],
            ['1010301001', '000122', '', '10'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertStringContainsString('baris 2', $hasil->galat[0]);
        $this->assertSame(34, $barang->refresh()->stok_fisik);
    }

    public function test_barang_nonaktif_ditolak(): void
    {
        $this->barang('000122', ['status_aktif' => false]);

        $hasil = $this->impor([['1010301001', '000122', '', '34']]);

        $this->assertStringContainsString('nonaktif', $hasil->galat[0]);
        $this->assertSame(0, MutasiStok::count());
    }

    /** Aturan tanggal Catat Stok Masuk (tidak melewati hari ini) juga ditegakkan di peladen. */
    public function test_tanggal_di_masa_depan_ditolak(): void
    {
        $barang = $this->barang();

        $hasil = $this->impor([['1010301001', '000122', '', '34']], '2026-09-26');

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('melewati hari ini', $hasil->galat[0]);
        $this->assertSame(0, $barang->refresh()->stok_fisik);
    }

    public function test_baris_sah_tetap_masuk_walau_baris_lain_gagal(): void
    {
        $this->barang('000122');
        $this->barang('000123');

        $hasil = $this->impor([
            ['1010301001', '000122', '', '0'],
            ['1010301001', '000123', '', '12'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertCount(1, $hasil->galat);
        $this->assertSame(12, BarangPersediaan::where('kode_barang', '000123')->sole()->stok_fisik);
    }

    public function test_template_yang_diunduh_dapat_diimpor_kembali(): void
    {
        $this->barang();
        $respons = app(PembuatTemplate::class)->buat(ImporStokMasuk::JUDUL, ImporStokMasuk::kolom(), 'Template-Impor-Stok-Masuk.xlsx');

        $hasil = app(ImporStokMasuk::class)->jalankan($respons->getFile()->getPathname(), 'stok_awal', $this->gudang);

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame(34, BarangPersediaan::sole()->stok_fisik);
    }

    // =====================================================================
    // AKSI DAN AKSES
    // =====================================================================

    public function test_petugas_gudang_mengimpor_lewat_dialog_stok_masuk(): void
    {
        Filament::setCurrentPanel('admin');
        $barang = $this->barang();
        $this->actingAs(\App\Models\User::find($this->gudang));

        $berkas = UploadedFile::fake()->createWithContent('stok-awal.xlsx', file_get_contents($this->berkas([['1010301001', '000122', '', '34', '', '']])));

        Livewire::test(StokMasuk::class)
            ->assertActionExists('impor')
            ->assertActionHasLabel('impor', 'Impor Stok Masuk')
            ->callAction('impor', ['jenis' => 'stok_awal', 'berkas' => $berkas])
            ->assertHasNoActionErrors();

        $this->assertSame(34, $barang->refresh()->stok_fisik);
        $this->assertSame('stok_awal', MutasiStok::sole()->sumber);
        $this->assertSame($this->gudang, MutasiStok::sole()->petugas_id);
    }

    /** Tanggal kini per baris; yang wajib di pop-up adalah jenis transaksinya. */
    public function test_dialog_menolak_tanpa_jenis_transaksi(): void
    {
        Filament::setCurrentPanel('admin');
        $this->barang();
        $this->actingAs(\App\Models\User::find($this->gudang));

        $berkas = UploadedFile::fake()->createWithContent('stok-awal.xlsx', file_get_contents($this->berkas([['1010301001', '000122', '', '34', '', '']])));

        Livewire::test(StokMasuk::class)
            ->callAction('impor', ['berkas' => $berkas])
            ->assertHasActionErrors(['jenis']);

        $this->assertSame(0, MutasiStok::count());
    }

    /** @return array<string,array{0:string}> */
    public static function peranTanpaAkses(): array
    {
        return ['admin' => ['admin'], 'kasubbag' => ['kasubbag'], 'ketua tim' => ['ketua_tim'], 'tim' => ['tim']];
    }

    #[DataProvider('peranTanpaAkses')]
    public function test_peran_selain_petugas_gudang_tidak_dapat_membuka_stok_masuk(string $peran): void
    {
        $tim = in_array($peran, ['ketua_tim', 'tim'], true) ? $this->buatTim() : null;

        $this->actingAs($this->lengkapiAkun($this->buatPengguna($peran, $tim, ['email' => $peran . '.stokawal@bps.go.id'])))
            ->get(StokMasuk::getUrl())
            ->assertForbidden();
    }
}
