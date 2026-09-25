<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporStokMasuk;
use App\Services\KartuKendaliService;
use App\Services\StokService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PenulisXlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Impor Stok Masuk: satu jenis transaksi per berkas (dipilih di pop-up),
 * tanggal, nomor dasar, dan keterangan per baris. Pencatatan lewat
 * StokService::tambah() seperti Catat Stok Masuk manual.
 */
class ImporStokMasukTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const TAJUK = ['Kode Kategori', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Tanggal', 'Nomor Dasar', 'Keterangan'];

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
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_stok_masuk_') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($lintasan);
        $writer->addRow(Row::fromValues(self::TAJUK));
        foreach ($baris as $b) {
            $writer->addRow(Row::fromValues($b));
        }
        $writer->close();

        return $lintasan;
    }

    private function impor(string $jenis, array $baris): HasilImpor
    {
        $lintasan = $this->berkas($baris);
        $hasil = app(ImporStokMasuk::class)->jalankan($lintasan, $jenis, $this->gudang);
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
    // PENCATATAN PER JENIS
    // =====================================================================

    /** @return array<string,array{0:string}> */
    public static function jenisSelainStokAwal(): array
    {
        return ['pembelian' => ['pembelian'], 'transfer masuk' => ['transfer_masuk'], 'pengembalian' => ['pengembalian']];
    }

    #[DataProvider('jenisSelainStokAwal')]
    public function test_setiap_jenis_menambah_stok_dengan_tanggal_dan_nomor_per_baris(string $jenis): void
    {
        $barang = $this->barang();
        app(StokService::class)->tambah($barang->id, 10, 'stok_awal', null, null, $this->gudang, '2026-01-01');

        $hasil = $this->impor($jenis, [
            ['1010301001', '000122', '', '5', '03/02/2026', 'DOK-1', 'Nota pertama'],
            ['1010301001', '000122', '', '7', '2026-04-15', 'DOK-2', ''],
        ]);

        $this->assertSame(2, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame(22, $barang->refresh()->stok_fisik);

        $mutasi = MutasiStok::where('sumber', $jenis)->orderBy('tanggal')->get();
        $this->assertSame(['2026-02-03', '2026-04-15'], $mutasi->map(fn ($m) => $m->tanggal->toDateString())->all());
        $this->assertSame(['DOK-1', 'DOK-2'], $mutasi->pluck('nomor_dasar')->all());
        $this->assertSame(['Nota pertama', null], $mutasi->pluck('keterangan')->all());
        $this->assertSame([15, 22], $mutasi->pluck('saldo_sesudah')->all());
        $this->assertSame([$this->gudang], $mutasi->pluck('petugas_id')->unique()->values()->all());
    }

    public function test_stok_awal_bertanggal_kosong_tercatat_1_januari_dan_tampil_sebagai_stok_awal(): void
    {
        $barang = $this->barang();

        $hasil = $this->impor('stok_awal', [['1010301001', '000122', '', '34', '', '', '']]);

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertFalse($hasil->adaCatatan());
        $this->assertSame('2026-01-01', MutasiStok::sole()->tanggal->toDateString());

        $kartu = app(KartuKendaliService::class)->data($barang->refresh(), 2026);
        $this->assertSame(34, $kartu['awal']);
        $this->assertCount(0, $kartu['mutasi']);
    }

    public function test_stok_awal_bertanggal_lain_tercatat_dengan_catatan(): void
    {
        $barang = $this->barang();
        $this->barang('000123');

        $hasil = $this->impor('stok_awal', [
            ['1010301001', '000122', '', '34', '01/07/2026', '', ''],
            ['1010301001', '000123', '', '5', '01/01/2026', '', ''],
        ]);

        $this->assertSame(2, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame('2026-07-01', MutasiStok::where('barang_id', $barang->id)->sole()->tanggal->toDateString());
        $this->assertCount(1, $hasil->catatan);
        $this->assertStringContainsString('Baris 2 bertanggal selain 1 Januari', $hasil->catatan[0]);

        $kartu = app(KartuKendaliService::class)->data($barang->refresh(), 2026);
        $this->assertSame(0, $kartu['awal']);
        $this->assertCount(1, $kartu['mutasi']);
    }

    // =====================================================================
    // TANGGAL
    // =====================================================================

    public function test_jenis_lain_tanpa_tanggal_ditolak(): void
    {
        $this->barang();

        $hasil = $this->impor('pengembalian', [['1010301001', '000122', '', '5', '', '', '']]);

        $this->assertSame(['Baris 2: Tanggal wajib diisi untuk jenis Pengembalian.'], $hasil->galat);
        $this->assertSame(0, MutasiStok::count());
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function tanggalDitolak(): array
    {
        return [
            'masa depan'      => ['26/09/2026', 'melewati hari ini'],
            'tanggal mustahil' => ['31/02/2026', 'tidak sah'],
            'bukan tanggal'   => ['kemarin', 'tidak sah'],
            'bentuk lain'     => ['2026/01/05', 'tidak sah'],
        ];
    }

    #[DataProvider('tanggalDitolak')]
    public function test_tanggal_masa_depan_dan_tidak_sah_ditolak(string $tanggal, string $pesan): void
    {
        $this->barang();

        $hasil = $this->impor('pengembalian', [['1010301001', '000122', '', '5', $tanggal, '', '']]);

        $this->assertStringContainsString($pesan, $hasil->galat[0]);
        $this->assertSame(0, MutasiStok::count());
    }

    /** Sel tanggal Excel dan teks DD/MM/YYYY sama-sama terbaca. */
    public function test_sel_tanggal_excel_dan_teks_dd_mm_yyyy_terbaca(): void
    {
        $barang = $this->barang();

        $buku = new Spreadsheet();
        $lembar = $buku->getActiveSheet();
        $lembar->fromArray(self::TAJUK);
        $lembar->fromArray(['1010301001', '000122', '', '3', null, 'SEL-1', ''], null, 'A2', true);
        $lembar->setCellValue('E2', TanggalExcel::PHPToExcel(Carbon::create(2026, 3, 9)));
        $lembar->getStyle('E2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $lembar->getCell('B2')->setValueExplicit('000122', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $lembar->fromArray(['1010301001', '000122', '', '4', '10/03/2026', 'TEKS-1', ''], null, 'A3', true);
        $lembar->getCell('B3')->setValueExplicit('000122', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $lembar->getCell('E3')->setValueExplicit('10/03/2026', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_tgl_') . '.xlsx';
        (new PenulisXlsx($buku))->save($lintasan);

        $hasil = app(ImporStokMasuk::class)->jalankan($lintasan, 'pengembalian', $this->gudang);
        @unlink($lintasan);

        $this->assertSame(2, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame(['2026-03-09', '2026-03-10'], MutasiStok::orderBy('tanggal')->get()->map(fn ($m) => $m->tanggal->toDateString())->all());
        $this->assertSame(7, $barang->refresh()->stok_fisik);
    }

    // =====================================================================
    // NOMOR DASAR DAN KETERANGAN (aturan Catat Stok Masuk)
    // =====================================================================

    #[DataProvider('jenisSelainStokAwal')]
    public function test_nomor_dasar_mengikuti_aturan_catat_stok_masuk(string $jenis): void
    {
        $this->barang();

        $hasil = $this->impor($jenis, [['1010301001', '000122', '', '5', '03/02/2026', '', '']]);

        if (in_array($jenis, StokMasuk::SUMBER_WAJIB_NOMOR_DASAR, true)) {
            $this->assertStringContainsString('Nomor Dasar wajib diisi', $hasil->galat[0]);
            $this->assertSame(0, MutasiStok::count());
        } else {
            $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
            $this->assertNull(MutasiStok::sole()->nomor_dasar);
        }
    }

    public function test_nomor_dasar_dan_keterangan_terlalu_panjang_ditolak(): void
    {
        $this->barang();

        $hasil = $this->impor('pembelian', [
            ['1010301001', '000122', '', '5', '03/02/2026', str_repeat('N', 61), ''],
            ['1010301001', '000122', '', '5', '04/02/2026', 'DOK-9', str_repeat('K', 256)],
        ]);

        $this->assertSame(['Baris 2: Nomor Dasar melebihi 60 aksara.', 'Baris 3: Keterangan melebihi 255 aksara.'], $hasil->galat);
        $this->assertSame(0, MutasiStok::count());
    }

    // =====================================================================
    // INPUT GANDA (f) DAN GALAT SALDO (g)
    // =====================================================================

    public function test_baris_sama_persis_ditolak_sebagai_kemungkinan_input_ganda(): void
    {
        $barang = $this->barang();

        $hasil = $this->impor('pembelian', [
            ['1010301001', '000122', '', '5', '03/02/2026', 'DOK-1', ''],
            ['1010301001', '000122', '', '5', '03/02/2026', 'DOK-1', ''],
            ['1010301001', '000122', '', '5', '03/02/2026', 'DOK-2', ''],
            ['1010301001', '000122', '', '5', '04/02/2026', 'DOK-1', ''],
        ]);

        $this->assertSame(3, $hasil->ditambah);
        $this->assertCount(1, $hasil->galat);
        $this->assertStringContainsString('Baris 3', $hasil->galat[0]);
        $this->assertStringContainsString('kemungkinan input ganda', $hasil->galat[0]);
        $this->assertSame(15, $barang->refresh()->stok_fisik);
    }

    /**
     * Stok fisik yang pernah diturunkan lewat Ubah Barang Persediaan (T-57)
     * membuat saldo pembawaan negatif; pemasukan bertanggal mundur lalu ditolak
     * StokService sebagai galat baris, bukan galat mentah.
     */
    public function test_galat_saldo_dari_stok_service_menjadi_galat_baris(): void
    {
        $barang = $this->barang();
        app(StokService::class)->tambah($barang->id, 10, 'pembelian', 'NOTA-1', null, $this->gudang, '2026-05-01');
        $barang->refresh()->forceFill(['stok_fisik' => 0])->save();

        $hasil = $this->impor('pengembalian', [
            ['1010301001', '000122', '', '5', '01/03/2026', '', ''],
        ]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('Baris 2: Transaksi ini membuat sisa stok menjadi negatif', $hasil->galat[0]);
        $this->assertSame(1, MutasiStok::count());
    }

    // =====================================================================
    // JENIS DAN POP-UP
    // =====================================================================

    public function test_jenis_tidak_valid_ditolak_di_server(): void
    {
        $barang = $this->barang();

        $hasil = $this->impor('pemakaian', [['1010301001', '000122', '', '5', '03/02/2026', '', '']]);

        $this->assertSame(['Jenis transaksi "pemakaian" tidak dikenali.'], $hasil->galat);
        $this->assertSame(0, MutasiStok::count());
        $this->assertSame(0, $barang->refresh()->stok_fisik);
    }

    public function test_jenis_tidak_valid_dari_muatan_dimodifikasi_ditolak_dialog(): void
    {
        Filament::setCurrentPanel('admin');
        $this->barang();
        $this->actingAs(\App\Models\User::find($this->gudang));

        $berkas = UploadedFile::fake()->createWithContent('stok.xlsx', file_get_contents($this->berkas([['1010301001', '000122', '', '5', '03/02/2026', '', '']])));

        Livewire::test(StokMasuk::class)
            ->callAction('impor', ['jenis' => 'pemakaian', 'berkas' => $berkas])
            ->assertHasActionErrors(['jenis']);

        $this->assertSame(0, MutasiStok::count());
    }

    public function test_peringatan_stok_awal_tampil_di_pop_up(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(\App\Models\User::find($this->gudang));

        Livewire::test(StokMasuk::class)
            ->mountAction('impor')
            ->assertMountedActionModalDontSee('sistem memakai 1 Januari tahun berjalan')
            ->setActionData(['jenis' => 'stok_awal'])
            ->assertMountedActionModalSee('Jenis Stok Awal')
            ->assertMountedActionModalSee(ImporStokMasuk::CATATAN_STOK_AWAL[0])
            ->assertMountedActionModalSee('isi tanggal go-live')
            ->assertMountedActionModalSee('belum punya stok maupun riwayat transaksi')
            ->assertMountedActionModalDontSee('wajib diisi pada setiap baris berkas')
            ->setActionData(['jenis' => 'pembelian'])
            ->assertMountedActionModalDontSee('sistem memakai 1 Januari tahun berjalan')
            ->assertMountedActionModalSee('Kolom Tanggal wajib diisi pada setiap baris berkas.');
    }

    public function test_pembelian_lewat_dialog_mencatat_sumber_terpilih(): void
    {
        Filament::setCurrentPanel('admin');
        $barang = $this->barang();
        $this->actingAs(\App\Models\User::find($this->gudang));

        $berkas = UploadedFile::fake()->createWithContent('stok.xlsx', file_get_contents($this->berkas([['1010301001', '000122', '', '5', '03/02/2026', 'SPK-01', '']])));

        Livewire::test(StokMasuk::class)
            ->assertActionHasLabel('impor', 'Impor Stok Masuk')
            ->callAction('impor', ['jenis' => 'pembelian', 'berkas' => $berkas])
            ->assertHasNoActionErrors();

        $mutasi = MutasiStok::sole();
        $this->assertSame('pembelian', $mutasi->sumber);
        $this->assertSame('SPK-01', $mutasi->nomor_dasar);
        $this->assertSame(5, $barang->refresh()->stok_fisik);
    }

    /** @return array<string,array{0:string}> */
    public static function peranLain(): array
    {
        return ['admin' => ['admin'], 'kasubbag' => ['kasubbag'], 'ketua tim' => ['ketua_tim'], 'tim' => ['tim']];
    }

    /** Tombol Impor Stok Masuk hanya ada di halaman Stok Masuk, yang tertutup bagi peran lain. */
    #[DataProvider('peranLain')]
    public function test_peran_selain_petugas_gudang_tidak_melihat_tombol(string $peran): void
    {
        $tim = in_array($peran, ['ketua_tim', 'tim'], true) ? $this->buatTim() : null;
        $pengguna = $this->lengkapiAkun($this->buatPengguna($peran, $tim, ['email' => $peran . '.stokmasuk@bps.go.id']));

        $this->actingAs($pengguna)->get(StokMasuk::getUrl())->assertForbidden();
        $this->assertFalse(StokMasuk::canAccess());
    }
}
