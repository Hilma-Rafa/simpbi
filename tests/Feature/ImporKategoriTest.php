<?php

namespace Tests\Feature;

use App\Filament\Resources\Kategoris\Pages\ListKategoris;
use App\Models\Kategori;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporKategori;
use App\Services\Impor\PembuatTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Impor Kategori Barang: upsert berkunci Kode Kategori (G-4), kode akun
 * persediaan enam digit (G-2), tipe hanya berganti selama kategori belum
 * dipakai, dan tidak pernah menghapus.
 */
class ImporKategoriTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const TAJUK = ['Kode Kategori', 'Nama Kategori', 'Kode Akun', 'Tipe'];

    /** @param list<list<mixed>> $baris */
    private function impor(array $baris, array $tajuk = self::TAJUK): HasilImpor
    {
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_kategori_') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($lintasan);
        $writer->addRow(Row::fromValues($tajuk));
        foreach ($baris as $b) {
            $writer->addRow(Row::fromValues($b));
        }
        $writer->close();

        $hasil = app(ImporKategori::class)->jalankan($lintasan);
        @unlink($lintasan);

        return $hasil;
    }

    private function kategori(string $kode = '1010301001', array $tambahan = []): Kategori
    {
        return Kategori::create([
            'kode_kategori' => $kode, 'nama_kategori' => 'Alat Tulis', 'kode_akun' => '117111', 'tipe' => 'persediaan', ...$tambahan,
        ]);
    }

    public function test_kategori_baru_ditambahkan(): void
    {
        $hasil = $this->impor([
            ['1010301001', 'Alat Tulis', '117111', 'Persediaan'],
            ['PM', 'Peralatan dan Mesin', '1.3.2', 'Aset Tetap'],
        ]);

        $this->assertSame(2, $hasil->ditambah);
        $this->assertFalse($hasil->adaGalat(), implode(' | ', $hasil->galat));
        $this->assertSame('persediaan', Kategori::where('kode_kategori', '1010301001')->sole()->tipe);
        $this->assertSame('aset_tetap', Kategori::where('kode_kategori', 'PM')->sole()->tipe);
    }

    public function test_kode_yang_sudah_ada_diperbarui_bukan_digandakan(): void
    {
        $lama = $this->kategori();

        $hasil = $this->impor([['1010301001', 'Alat Tulis Kantor', '117111', 'persediaan']]);

        $this->assertSame(1, $hasil->diperbarui);
        $this->assertSame(1, Kategori::count());
        $this->assertSame('Alat Tulis Kantor', $lama->refresh()->nama_kategori);
    }

    /** Kode dibaca dari sel angka (1010301001 sebagai bilangan) tetap cocok. */
    public function test_kode_kategori_dari_sel_angka_tetap_cocok(): void
    {
        $this->kategori();

        $hasil = $this->impor([[1010301001, 'Alat Tulis', 117111, 'Persediaan']]);

        $this->assertSame(1, $hasil->diperbarui);
        $this->assertSame(1, Kategori::count());
    }

    public function test_kode_akun_persediaan_bukan_enam_digit_ditolak(): void
    {
        $hasil = $this->impor([['1010301001', 'Alat Tulis', '1171', 'Persediaan']]);

        $this->assertSame(0, $hasil->berhasil());
        $this->assertStringContainsString('6 digit', $hasil->galat[0]);
        $this->assertSame(0, Kategori::count());
    }

    public function test_tipe_tidak_dikenali_ditolak(): void
    {
        $hasil = $this->impor([['1010301001', 'Alat Tulis', '117111', 'Habis Pakai']]);

        $this->assertStringContainsString('Tipe', $hasil->galat[0]);
        $this->assertSame(0, Kategori::count());
    }

    public function test_kolom_wajib_kosong_ditolak(): void
    {
        $hasil = $this->impor([
            ['', 'Alat Tulis', '117111', 'Persediaan'],
            ['1010301003', '', '117111', 'Persediaan'],
            ['1010301006', 'Ordner', '', 'Persediaan'],
            ['1010301010', 'Perekat', '117111', ''],
        ]);

        $this->assertCount(4, $hasil->galat);
        $this->assertStringContainsString('Baris 2', $hasil->galat[0]);
        $this->assertSame(0, Kategori::count());
    }

    public function test_tipe_kategori_yang_sudah_dipakai_tidak_dapat_diubah(): void
    {
        $kategori = $this->kategori();
        $this->buatBarang(0, 0, ['kategori_id' => $kategori->id]);

        $hasil = $this->impor([['1010301001', 'Alat Tulis', '1.1.7', 'Aset Tetap']]);

        $this->assertSame(0, $hasil->berhasil());
        $this->assertStringContainsString('tipenya', $hasil->galat[0]);
        $this->assertSame('persediaan', $kategori->refresh()->tipe);
    }

    public function test_tipe_kategori_yang_belum_dipakai_boleh_diubah(): void
    {
        $kategori = $this->kategori();

        $hasil = $this->impor([['1010301001', 'Alat Tulis', '1.1.7', 'Aset Tetap']]);

        $this->assertSame(1, $hasil->diperbarui);
        $this->assertSame('aset_tetap', $kategori->refresh()->tipe);
    }

    public function test_kode_ganda_dalam_satu_berkas_ditolak_yang_kedua(): void
    {
        $hasil = $this->impor([
            ['1010301001', 'Alat Tulis', '117111', 'Persediaan'],
            ['1010301001', 'Alat Tulis Ganda', '117111', 'Persediaan'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertStringContainsString('baris 2', $hasil->galat[0]);
        $this->assertSame('Alat Tulis', Kategori::sole()->nama_kategori);
    }

    public function test_kategori_di_luar_berkas_tidak_dihapus(): void
    {
        $this->kategori('1010302001', ['nama_kategori' => 'Kertas HVS']);

        $this->impor([['1010301001', 'Alat Tulis', '117111', 'Persediaan']]);

        $this->assertSame(2, Kategori::count());
    }

    public function test_bentrok_unik_saat_dibuat_menjadi_galat_baris(): void
    {
        Kategori::creating(function (Kategori $model): void {
            if (Kategori::where('kode_kategori', $model->kode_kategori)->exists()) {
                return;
            }
            Kategori::withoutEvents(fn () => Kategori::create([...$model->getAttributes(), 'nama_kategori' => 'Penyusup']));
        });

        $hasil = $this->impor([['1010301001', 'Alat Tulis', '117111', 'Persediaan']]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('sudah dipakai', $hasil->galat[0]);
        $this->assertSame('Penyusup', Kategori::sole()->nama_kategori);
    }

    public function test_template_yang_diunduh_dapat_diimpor_kembali(): void
    {
        $respons = app(PembuatTemplate::class)->buat(ImporKategori::JUDUL, ImporKategori::kolom(), 'Template-Impor-Kategori-Barang.xlsx');

        $hasil = app(ImporKategori::class)->jalankan($respons->getFile()->getPathname());

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame('117111', Kategori::sole()->kode_akun);
    }

    public function test_admin_dan_kasubbag_melihat_tombol_impor(): void
    {
        Filament::setCurrentPanel('admin');

        foreach (['admin', 'kasubbag'] as $peran) {
            $this->actingAs($this->buatPengguna($peran, null, ['email' => $peran . '.impor.kategori@bps.go.id']));

            Livewire::test(ListKategoris::class)->assertTableActionExists('impor');
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
            ->get('/admin/kategoris')
            ->assertForbidden();
    }
}
