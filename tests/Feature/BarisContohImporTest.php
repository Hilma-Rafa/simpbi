<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Filament\Resources\AsetTetaps\Pages\ListAsetTetaps;
use App\Filament\Resources\BarangPersediaans\Pages\ListBarangPersediaans;
use App\Filament\Resources\Kategoris\Pages\ListKategoris;
use App\Filament\Resources\Tims\Pages\ListTims;
use App\Filament\Resources\Users\Pages\ListUsers;
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
use App\Services\Impor\PembacaBerkas;
use App\Services\Impor\PembuatTemplate;
use App\Services\StokService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PenulisXlsx;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Baris contoh template yang dibiarkan apa adanya tidak boleh terimpor sebagai
 * data; baris contoh yang diubah pengguna tetap terbaca. Diuji lewat dialog
 * Impor (AksiImpor) di keenam halaman, jalur yang dipakai pengguna.
 */
class BarisContohImporTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-25 10:00:00');
        Filament::setCurrentPanel('admin');
    }

    /** Template apa adanya (baris contoh tetap di baris 2), opsional dengan satu sel diubah. */
    private function unggahan(string $judul, array $kolom, array $ubah = [], array $petunjuk = []): UploadedFile
    {
        $lintasan = app(PembuatTemplate::class)->buat($judul, $kolom, 'template.xlsx', $petunjuk)->getFile()->getPathname();

        if ($ubah !== []) {
            $buku = IOFactory::load($lintasan);
            foreach ($ubah as $sel => $nilai) {
                $buku->getSheet(0)->getCell($sel)->setValueExplicit($nilai, DataType::TYPE_STRING);
            }
            $lintasan = tempnam(sys_get_temp_dir(), 'contoh_diubah_') . '.xlsx';
            (new PenulisXlsx($buku))->save($lintasan);
        }

        return UploadedFile::fake()->createWithContent('template.xlsx', file_get_contents($lintasan));
    }

    private function catatanTerakhir(): string
    {
        // Cara yang sama dengan Notification::assertNotified(): notifikasi
        // dibaca dari komponen Notifications Filament.
        $komponen = new \Filament\Notifications\Livewire\Notifications();
        $komponen->mount();

        return (string) ($komponen->notifications->last()?->toArray()['body'] ?? '');
    }

    private function impor(string $halaman, UploadedFile $berkas, array $data = [], bool $tabel = true)
    {
        $aksi = $tabel ? TestAction::make('impor')->table() : 'impor';

        return Livewire::test($halaman)->callAction($aksi, ['berkas' => $berkas, ...$data])->assertHasNoActionErrors();
    }

    // =====================================================================
    // STOK MASUK — risiko utama: barang contoh ada di data
    // =====================================================================

    private function barangContoh(): BarangPersediaan
    {
        $kategori = Kategori::create(['kode_kategori' => '1010301001', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
        $barang = BarangPersediaan::create([
            'kategori_id' => $kategori->id, 'kode_barang' => '000122', 'nama_barang' => 'Binder Clips No. 105', 'satuan' => 'Dus',
            'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ]);
        app(StokService::class)->tambah($barang->id, 1, 'pembelian', 'NOTA-0', null, $this->buatPengguna('petugas_gudang')->id, '2026-01-02');

        return $barang->refresh();
    }

    public function test_stok_masuk_baris_contoh_dibiarkan_tidak_menambah_stok_maupun_mutasi(): void
    {
        $barang = $this->barangContoh();
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $this->impor(StokMasuk::class, $this->unggahan(ImporStokMasuk::JUDUL, ImporStokMasuk::kolom(), [], ImporStokMasuk::petunjuk()), ['jenis' => 'pengembalian'], false);

        $this->assertSame(1, $barang->refresh()->stok_fisik);
        $this->assertSame(1, MutasiStok::count());
        $this->assertStringContainsString('Baris contoh dilewati.', $this->catatanTerakhir());
    }

    public function test_stok_masuk_baris_contoh_yang_diubah_tetap_terimpor(): void
    {
        $barang = $this->barangContoh();
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        // Kolom D = Jumlah; contoh 34 diubah menjadi 5.
        $this->impor(StokMasuk::class, $this->unggahan(ImporStokMasuk::JUDUL, ImporStokMasuk::kolom(), ['D2' => '5']), ['jenis' => 'pengembalian'], false);

        $this->assertSame(6, $barang->refresh()->stok_fisik);
        $mutasi = MutasiStok::where('sumber', 'pengembalian')->sole();
        $this->assertSame('2026-01-01', $mutasi->tanggal->toDateString());
        $this->assertStringNotContainsString('Baris contoh dilewati.', $this->catatanTerakhir());
    }

    // =====================================================================
    // DATA INDUK
    // =====================================================================

    public function test_tim_kerja_baris_contoh_dilewati_dan_yang_diubah_terimpor(): void
    {
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.contoh.tim@bps.go.id']));
        $pek = Tim::create(['nama_tim' => 'Statistik Pertambangan, Energi dan Konstruksi (PEK)', 'status_aktif' => true]);

        $this->impor(ListTims::class, $this->unggahan(ImporTimKerja::JUDUL, ImporTimKerja::kolom()));
        $this->assertNull($pek->refresh()->external_id);
        $this->assertStringContainsString('Baris contoh dilewati.', $this->catatanTerakhir());

        $this->impor(ListTims::class, $this->unggahan(ImporTimKerja::JUDUL, ImporTimKerja::kolom(), ['B2' => 'TIM-2026-01']));
        $this->assertSame('TIM-2026-01', $pek->refresh()->external_id);
    }

    public function test_pengguna_baris_contoh_dilewati_dan_yang_diubah_terimpor(): void
    {
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.contoh.pengguna@bps.go.id']));
        Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);

        $this->impor(ListUsers::class, $this->unggahan(ImporPengguna::JUDUL, ImporPengguna::kolom()));
        $this->assertFalse(User::where('username', 'wanda.pribadi')->exists());
        $this->assertStringContainsString('Baris contoh dilewati.', $this->catatanTerakhir());

        $this->impor(ListUsers::class, $this->unggahan(ImporPengguna::JUDUL, ImporPengguna::kolom(), ['A2' => 'wanda.baru', 'E2' => 'wanda.baru@bps.go.id']));
        $this->assertTrue(User::where('username', 'wanda.baru')->exists());
    }

    public function test_aset_tetap_baris_contoh_dilewati_dan_yang_diubah_terimpor(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.contoh.aset@bps.go.id']));
        $this->buatTim('Statistik Sosial');
        Kategori::create(['kode_kategori' => 'PM', 'kode_akun' => '1.3.2', 'nama_kategori' => 'Peralatan dan Mesin', 'tipe' => 'aset_tetap']);

        $this->impor(ListAsetTetaps::class, $this->unggahan(ImporAsetTetap::JUDUL, ImporAsetTetap::kolom()));
        $this->assertSame(0, AsetTetap::count());
        $this->assertStringContainsString('Baris contoh dilewati.', $this->catatanTerakhir());

        $this->impor(ListAsetTetaps::class, $this->unggahan(ImporAsetTetap::JUDUL, ImporAsetTetap::kolom(), ['A2' => '3.10.01.00099']));
        $this->assertSame('3.10.01.00099', AsetTetap::sole()->nup);
    }

    public function test_kategori_baris_contoh_dilewati_dan_yang_diubah_terimpor(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.contoh.kategori@bps.go.id']));

        $this->impor(ListKategoris::class, $this->unggahan(ImporKategori::JUDUL, ImporKategori::kolom()));
        $this->assertSame(0, Kategori::count());
        $this->assertStringContainsString('Baris contoh dilewati.', $this->catatanTerakhir());

        $this->impor(ListKategoris::class, $this->unggahan(ImporKategori::JUDUL, ImporKategori::kolom(), ['B2' => 'Alat Tulis Kantor']));
        $this->assertSame('Alat Tulis Kantor', Kategori::sole()->nama_kategori);
    }

    public function test_barang_persediaan_baris_contoh_dilewati_dan_yang_diubah_terimpor(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.contoh.barang@bps.go.id']));
        $kategori = Kategori::create(['kode_kategori' => '1010301001', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
        $barang = BarangPersediaan::create([
            'kategori_id' => $kategori->id, 'kode_barang' => '000122', 'nama_barang' => 'Binder Clips No. 105', 'satuan' => 'Dus',
            'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 3, 'status_aktif' => true,
        ]);

        $this->impor(ListBarangPersediaans::class, $this->unggahan(ImporBarangPersediaan::JUDUL, ImporBarangPersediaan::kolom()));
        $barang->refresh();
        $this->assertSame('Binder Clips No. 105', $barang->nama_barang);
        $this->assertSame(3, $barang->stok_minimum);
        $this->assertStringContainsString('Baris contoh dilewati.', $this->catatanTerakhir());

        $this->impor(ListBarangPersediaans::class, $this->unggahan(ImporBarangPersediaan::JUDUL, ImporBarangPersediaan::kolom(), ['E2' => '4']));
        $this->assertSame(4, $barang->refresh()->stok_minimum);
    }

    public function test_petunjuk_keenam_template_menjelaskan_baris_contoh(): void
    {
        foreach ([ImporTimKerja::class, ImporPengguna::class, ImporAsetTetap::class, ImporKategori::class, ImporBarangPersediaan::class, ImporStokMasuk::class] as $kelas) {
            $lintasan = app(PembuatTemplate::class)->buat($kelas::JUDUL, $kelas::kolom(), 'x.xlsx')->getFile()->getPathname();
            $teks = collect(IOFactory::load($lintasan)->getSheet(1)->toArray())->flatten()->filter()->implode(' ');

            $this->assertStringContainsString('boleh ditimpa dengan data Anda atau dihapus', $teks, $kelas);
            $this->assertStringContainsString('bila dibiarkan apa adanya, baris contoh dilewati saat impor', $teks, $kelas);
        }
    }

    // =====================================================================
    // PEMBACA
    // =====================================================================

    /** Contoh tanggal dinormalkan seperti pembacaan biasa (sel tanggal → Y-m-d); nomor baris lain tidak bergeser. */
    public function test_pembaca_melewati_hanya_baris_yang_sama_persis_dengan_contoh(): void
    {
        $lintasan = app(PembuatTemplate::class)->buat(ImporStokMasuk::JUDUL, ImporStokMasuk::kolom(), 'x.xlsx')->getFile()->getPathname();
        $buku = IOFactory::load($lintasan);
        $lembar = $buku->getSheet(0);
        $lembar->fromArray(['1010301001', '000122', 'BINDER CLIPS NO. 105', '34', '01/01/2026', '', ''], null, 'A3');
        $lembar->getCell('B3')->setValueExplicit('000122', DataType::TYPE_STRING);
        $lembar->getCell('D3')->setValueExplicit('34', DataType::TYPE_STRING);
        $lembar->getCell('E3')->setValueExplicit('01/01/2026', DataType::TYPE_STRING);
        $lembar->fromArray(['1010301001', '000122', 'BINDER CLIPS NO. 105', '35'], null, 'A4');
        $lembar->getCell('B4')->setValueExplicit('000122', DataType::TYPE_STRING);
        $lembar->getCell('D4')->setValueExplicit('35', DataType::TYPE_STRING);
        $berkas = tempnam(sys_get_temp_dir(), 'contoh_pembaca_') . '.xlsx';
        (new PenulisXlsx($buku))->save($berkas);

        $pembaca = (new PembacaBerkas())->lewatiContoh(ImporStokMasuk::kolom());
        $baris = $pembaca->baca($berkas);

        // Baris 2 (contoh asli, tanggal sebagai sel tanggal) dilewati. Baris 3
        // menulis tanggal sebagai teks 01/01/2026, bukan bentuk hasil baca
        // sel tanggal (2026-01-01), sehingga tidak sama persis dan tetap terbaca.
        $this->assertSame([2], $pembaca->contohDilewati);
        $this->assertSame([3, 4], array_column($baris, 'nomor'));

        // Tanpa pengenalan contoh, pembacaan tidak berubah dari sebelumnya.
        $this->assertSame([2, 3, 4], array_column((new PembacaBerkas())->baca($berkas), 'nomor'));
    }

    public function test_kolom_tambahan_yang_terisi_membuat_baris_bukan_contoh(): void
    {
        $kolom = [Kolom::buat('a', 'A', contoh: 'x'), Kolom::buat('b', 'B', contoh: 'y')];
        $lintasan = tempnam(sys_get_temp_dir(), 'contoh_tambahan_') . '.xlsx';
        $buku = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $buku->getActiveSheet()->fromArray([['A', 'B', 'Catatan Saya'], ['x', 'y', ''], ['x', 'y', 'penting']]);
        (new PenulisXlsx($buku))->save($lintasan);

        $pembaca = (new PembacaBerkas())->lewatiContoh($kolom);

        $this->assertSame([3], array_column($pembaca->baca($lintasan), 'nomor'));
        $this->assertSame([2], $pembaca->contohDilewati);
    }
}
