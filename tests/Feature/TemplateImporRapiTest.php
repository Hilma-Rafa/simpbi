<?php

namespace Tests\Feature;

use App\Models\AsetTetap;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Models\Tim;
use App\Models\User;
use App\Services\Impor\ImporAsetTetap;
use App\Services\Impor\ImporBarangPersediaan;
use App\Services\Impor\ImporKategori;
use App\Services\Impor\ImporPengguna;
use App\Services\Impor\ImporStokMasuk;
use App\Services\Impor\ImporTimKerja;
use App\Services\Impor\Kolom;
use App\Services\Impor\PembuatTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PenulisXlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Template impor yang dirapikan (tabel bergaris, format sel Teks/Tanggal,
 * tajuk dibekukan, filter) tanpa menggeser struktur yang dibaca PembacaBerkas:
 * lembar Data tetap pertama, tajuk tetap baris 1 dengan teks yang sama, dan
 * baris contoh tetap baris 2.
 */
class TemplateImporRapiTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** @return array<string,array{0:string,1:class-string,2:string}> */
    public static function template(): array
    {
        return [
            'Tim Kerja'         => [ImporTimKerja::JUDUL, ImporTimKerja::class, 'Template-Impor-Tim-Kerja.xlsx'],
            'Pengguna'          => [ImporPengguna::JUDUL, ImporPengguna::class, 'Template-Impor-Pengguna.xlsx'],
            'Aset Tetap'        => [ImporAsetTetap::JUDUL, ImporAsetTetap::class, 'Template-Sinkronisasi-Aset-Tetap.xlsx'],
            'Kategori Barang'   => [ImporKategori::JUDUL, ImporKategori::class, 'Template-Impor-Kategori-Barang.xlsx'],
            'Barang Persediaan' => [ImporBarangPersediaan::JUDUL, ImporBarangPersediaan::class, 'Template-Impor-Barang-Persediaan.xlsx'],
            'Stok Masuk'        => [ImporStokMasuk::JUDUL, ImporStokMasuk::class, 'Template-Impor-Stok-Masuk.xlsx'],
        ];
    }

    private function unduh(string $judul, string $kelas, string $nama): string
    {
        $petunjuk = $kelas === ImporStokMasuk::class ? ImporStokMasuk::petunjuk() : [];

        return app(PembuatTemplate::class)->buat($judul, $kelas::kolom(), $nama, $petunjuk)->getFile()->getPathname();
    }

    #[DataProvider('template')]
    public function test_struktur_yang_dibaca_tetap_dan_rupanya_rapi(string $judul, string $kelas, string $nama): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        $kolom = $kelas::kolom();
        $buku = IOFactory::load($this->unduh($judul, $kelas, $nama));
        $data = $buku->getSheet(0);

        $this->assertSame(['Data', 'Petunjuk'], $buku->getSheetNames());
        $this->assertSame(0, $buku->getActiveSheetIndex());

        foreach ($kolom as $i => $k) {
            $huruf = chr(65 + $i);
            // Tajuk baris 1 sama persis dengan template sebelumnya.
            $this->assertSame($k->judul . ($k->wajib ? ' *' : ''), $data->getCell($huruf . '1')->getValue());

            // Baris contoh tetap baris 2; contoh tanggal menjadi tanggal sungguhan.
            if ($k->format === Kolom::TANGGAL) {
                $this->assertSame('2026-01-01', TanggalExcel::excelToDateTimeObject($data->getCell($huruf . '2')->getValue())->format('Y-m-d'));
            } else {
                $this->assertSame($k->contoh === '' ? null : $k->contoh, $data->getCell($huruf . '2')->getValue());
            }

            $format = $data->getStyle($huruf . '1000')->getNumberFormat()->getFormatCode();
            $this->assertSame(match ($k->format) {
                Kolom::TEKS    => '@',
                Kolom::TANGGAL => 'dd/mm/yyyy',
                default        => 'General',
            }, $format, $k->judul);
            $this->assertSame('thin', $data->getStyle($huruf . '1000')->getBorders()->getBottom()->getBorderStyle());
        }

        $this->assertTrue($data->getStyle('A1')->getFont()->getBold());
        $this->assertSame('1557A6', $data->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('A2', $data->getFreezePane());
        $this->assertSame('A1:' . chr(64 + count($kolom)) . '1', $data->getAutoFilter()->getRange());

        // Area isian hanya berformat: tidak ada satu nilai pun di bawah baris contoh.
        $berisi = [];
        for ($baris = 3; $baris <= PembuatTemplate::BARIS_AKHIR; $baris++) {
            foreach ($kolom as $i => $k) {
                if ($data->getCell(chr(65 + $i) . $baris)->getValue() !== null) {
                    $berisi[] = chr(65 + $i) . $baris;
                }
            }
        }
        $this->assertSame([], $berisi, 'Sel area isian di bawah baris contoh harus kosong');
    }

    public function test_petunjuk_stok_masuk_memuat_jenis_dan_catatan_stok_awal(): void
    {
        $buku = IOFactory::load($this->unduh(ImporStokMasuk::JUDUL, ImporStokMasuk::class, 'Template-Impor-Stok-Masuk.xlsx'));
        $teks = collect($buku->getSheet(1)->toArray())->flatten()->filter()->implode(' ');

        foreach (['Stok Awal', 'Pembelian', 'Transfer Masuk', 'Pengembalian'] as $jenis) {
            $this->assertStringContainsString($jenis, $teks);
        }
        $this->assertStringContainsString('dipilih di pop-up Impor Stok Masuk, bukan di berkas', $teks);
        foreach (ImporStokMasuk::CATATAN_STOK_AWAL as $catatan) {
            $this->assertStringContainsString($catatan, $teks);
        }
    }

    /**
     * Mengetik ke sel seperti Excel: sel berformat Teks menyimpan ketikan apa
     * adanya, sel berformat tanggal mengubah DD/MM/YYYY menjadi tanggal, dan
     * sel Umum mengubah ketikan angka menjadi angka.
     *
     * @param  list<list<string>>  $baris
     */
    private function isiSepertiExcel(string $lintasan, array $baris): string
    {
        $buku = IOFactory::load($lintasan);
        $lembar = $buku->getSheet(0);
        $lembar->removeRow(2);   // baris contoh dihapus, sesuai petunjuk

        foreach ($baris as $r => $isi) {
            foreach ($isi as $c => $nilai) {
                if ($nilai === '') {
                    continue;
                }
                $sel = $lembar->getCell(chr(65 + $c) . ($r + 2));
                $format = $lembar->getStyle($sel->getCoordinate())->getNumberFormat()->getFormatCode();
                if ($format === '@') {
                    $sel->setValueExplicit($nilai, DataType::TYPE_STRING);
                } elseif ($format === 'dd/mm/yyyy' && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $nilai, $m)) {
                    $sel->setValue(TanggalExcel::PHPToExcel(Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])));
                } else {
                    $sel->setValue(is_numeric($nilai) ? $nilai + 0 : $nilai);
                }
            }
        }

        $hasil = tempnam(sys_get_temp_dir(), 'uji_isi_') . '.xlsx';
        (new PenulisXlsx($buku))->save($hasil);

        return $hasil;
    }

    public function test_template_barang_diisi_seperti_excel_menjaga_nol_di_depan(): void
    {
        Kategori::create(['kode_kategori' => '0101', 'kode_akun' => '117111', 'nama_kategori' => 'Uji Nol', 'tipe' => 'persediaan']);
        $isian = $this->isiSepertiExcel($this->unduh(ImporBarangPersediaan::JUDUL, ImporBarangPersediaan::class, 'x.xlsx'), [
            ['0101', '000122', 'Binder', 'Dus', '2'],
            ['0101', '001234', 'Map', 'Pak', ''],
        ]);

        $hasil = app(ImporBarangPersediaan::class)->jalankan($isian);

        $this->assertSame(2, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame(['000122', '001234'], BarangPersediaan::orderBy('kode_barang')->pluck('kode_barang')->all());
    }

    public function test_template_stok_masuk_diisi_seperti_excel_membaca_tanggal_dan_kode(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        $kategori = Kategori::create(['kode_kategori' => '1010301001', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
        $barang = BarangPersediaan::create([
            'kategori_id' => $kategori->id, 'kode_barang' => '000122', 'nama_barang' => 'Binder', 'satuan' => 'Dus',
            'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ]);
        $isian = $this->isiSepertiExcel($this->unduh(ImporStokMasuk::JUDUL, ImporStokMasuk::class, 'x.xlsx'), [
            ['1010301001', '000122', 'Binder', '5', '03/02/2026', '0045/SPK/2026', ''],
        ]);

        $hasil = app(ImporStokMasuk::class)->jalankan($isian, 'pembelian', $this->buatPengguna('petugas_gudang')->id);

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $mutasi = MutasiStok::sole();
        $this->assertSame('2026-02-03', $mutasi->tanggal->toDateString());
        $this->assertSame('0045/SPK/2026', $mutasi->nomor_dasar);
        $this->assertSame(5, $barang->refresh()->stok_fisik);
    }

    public function test_template_pengguna_diisi_seperti_excel_menjaga_nip_dan_nomor_hp(): void
    {
        Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);
        $isian = $this->isiSepertiExcel($this->unduh(ImporPengguna::JUDUL, ImporPengguna::class, 'x.xlsx'), [
            ['wanda.pribadi', 'Wanda Pribadi', 'Ketua Tim', 'Statistik Sosial', 'wanda.pribadi@bps.go.id', '099001012015011001', '081234567890', 'Ya'],
        ]);

        $hasil = app(ImporPengguna::class)->jalankan($isian);

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $pengguna = User::where('username', 'wanda.pribadi')->sole();
        $this->assertSame('099001012015011001', $pengguna->nip);
        $this->assertStringEndsWith('81234567890', (string) $pengguna->no_hp);
    }

    public function test_template_aset_dan_tim_diisi_seperti_excel_menjaga_kode(): void
    {
        $tim = $this->buatTim('Statistik Sosial');
        Kategori::create(['kode_kategori' => 'PM', 'kode_akun' => '1.3.2', 'nama_kategori' => 'Peralatan dan Mesin', 'tipe' => 'aset_tetap']);

        $isianTim = $this->isiSepertiExcel($this->unduh(ImporTimKerja::JUDUL, ImporTimKerja::class, 'x.xlsx'), [
            ['Statistik Sosial', '007', 'Ya'],
        ]);
        $isianAset = $this->isiSepertiExcel($this->unduh(ImporAsetTetap::JUDUL, ImporAsetTetap::class, 'x.xlsx'), [
            ['0012', 'Laptop', 'Peralatan dan Mesin', 'Baik', 'Statistik Sosial', '000041'],
        ]);

        $this->assertSame(1, app(ImporTimKerja::class)->jalankan($isianTim)->diperbarui);
        $this->assertSame(1, app(ImporAsetTetap::class)->jalankan($isianAset)->ditambah);
        $this->assertSame('007', $tim->refresh()->external_id);
        $aset = AsetTetap::sole();
        $this->assertSame('0012', $aset->nup);
        $this->assertSame('000041', $aset->external_id);
    }
}
