<?php

namespace Tests\Feature;

use App\Models\AsetTetap;
use App\Models\Kategori;
use App\Models\RiwayatPenempatanAset;
use App\Models\Tim;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporAsetTetap;
use App\Services\Impor\PembuatTemplate;
use App\Services\MutasiAsetService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Sinkronisasi berkala satu arah berbasis berkas untuk aset tetap (temuan T-02).
 *
 * Yang dijaga di sini bukan keberhasilan membaca berkas, melainkan **batas
 * kewenangannya**. Sebuah sinkronisasi yang keliru tidak gagal dengan berisik:
 * ia berhasil, melaporkan angka yang meyakinkan, dan diam-diam membatalkan
 * BAST yang sudah disahkan atau memindahkan aset ke tim kerja yang salah.
 * Karena itu tiap uji menyiapkan keadaan yang sengaja berbeda antara isi
 * berkas dan keadaan SIMPBI, sehingga penimpaan yang tidak seharusnya terjadi
 * pasti terlihat.
 */
class SinkronisasiAsetTetapTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const TAJUK = ['NUP', 'Nama Aset', 'Kategori', 'Kondisi', 'Tim Kerja Penempatan', 'ID Sumber'];

    protected function setUp(): void
    {
        parent::setUp();

        // Pengesahan BAST menulis PDF; jangan sampai jatuh ke disk sungguhan.
        Storage::fake('public');
        Storage::fake('local');
    }

    /** @param list<list<string>> $baris */
    private function berkas(array $baris): string
    {
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_sinkron_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($lintasan);

        foreach ($baris as $b) {
            $writer->addRow(Row::fromValues($b));
        }

        $writer->close();

        return $lintasan;
    }

    /** @param list<list<string>> $data baris data saja, tanpa tajuk */
    private function sinkron(array $data): HasilImpor
    {
        $lintasan = $this->berkas([self::TAJUK, ...$data]);
        $hasil = app(ImporAsetTetap::class)->jalankan($lintasan);
        @unlink($lintasan);

        return $hasil;
    }

    private function kategoriAset(string $nama = 'Peralatan dan Mesin'): Kategori
    {
        return Kategori::firstOrCreate(
            ['kode_kategori' => 'PM'],
            ['kode_akun' => '1.3.2', 'nama_kategori' => $nama, 'tipe' => 'aset_tetap'],
        );
    }

    // =====================================================================
    // PENAMBAHAN DAN PEMBARUAN
    // =====================================================================

    public function test_aset_baru_ditambahkan_beserta_provenansnya(): void
    {
        $this->kategoriAset();
        $tim = $this->buatTim('Statistik Sosial');

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop Lenovo ThinkPad E14', 'Peralatan dan Mesin', 'Baik', 'Statistik Sosial', 'BMN-001'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertFalse($hasil->adaGalat());

        $aset = AsetTetap::sole();
        $this->assertSame('Laptop Lenovo ThinkPad E14', $aset->nama_aset);
        $this->assertSame('baik', $aset->kondisi);
        $this->assertSame($tim->id, $aset->tim_penempatan_id);
        $this->assertSame('impor', $aset->sumber_data);
        $this->assertSame('BMN-001', $aset->external_id);
        $this->assertNotNull($aset->synced_at);
    }

    public function test_nup_yang_sudah_ada_diperbarui_bukan_digandakan(): void
    {
        $this->kategoriAset();
        $aset = $this->buatAset(null, ['nup' => '3.10.01.00001', 'nama_aset' => 'Nama Lama']);

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Nama Baru Dari Sumber', 'Peralatan dan Mesin', '', '', ''],
        ]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertSame(1, $hasil->diperbarui);
        $this->assertSame(1, AsetTetap::count(), 'NUP yang sama tidak boleh melahirkan aset kedua.');
        $this->assertSame('Nama Baru Dari Sumber', $aset->refresh()->nama_aset);
    }

    /** Mengunggah berkas yang sama dua kali tidak menambah apa pun. */
    public function test_sinkronisasi_berulang_tidak_menggandakan(): void
    {
        $this->kategoriAset();
        $baris = [['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', '']];

        $this->sinkron($baris);
        $kedua = $this->sinkron($baris);

        $this->assertSame(0, $kedua->ditambah);
        $this->assertSame(1, $kedua->diperbarui);
        $this->assertSame(1, AsetTetap::count());
    }

    /**
     * Perbaikan pasca-Batch 8: pencarian NUP yang sudah ada dan pembuatan
     * baris baru bukan satu kesatuan atomik, sehingga dua unggahan yang
     * bersamaan persis dapat sama-sama tidak menemukan barisnya. Bentroknya
     * disimulasikan lewat kait `creating`, tepat sebelum baris ini tersimpan
     * (pola yang sama dengan uji A-021) — baris itu ditolak dengan pesan
     * jelas, baris lain pada berkas yang sama tetap diproses.
     */
    public function test_nup_bentrok_akibat_kondisi_pacu_ditolak_baris_lain_tetap_diproses(): void
    {
        $this->kategoriAset();

        AsetTetap::creating(function (AsetTetap $model): void {
            if ($model->nup !== '3.10.01.00002' || AsetTetap::where('nup', '3.10.01.00002')->exists()) {
                return;
            }

            AsetTetap::withoutEvents(fn () => AsetTetap::create([
                ...$model->getAttributes(),
                'nama_aset' => 'Penyusup',
            ]));
        });

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', ''],
            ['3.10.01.00002', 'Printer', 'Peralatan dan Mesin', '', '', ''],
        ]);

        $this->assertSame(1, $hasil->ditambah, 'Baris pertama tetap masuk walau baris kedua bentrok.');
        $this->assertTrue($hasil->adaGalat());
        $this->assertStringContainsString('Baris 3: NUP sudah dipakai oleh aset lain.', $hasil->galat[0]);
        $this->assertNotNull(AsetTetap::where('nup', '3.10.01.00001')->first());
        $this->assertSame('Penyusup', AsetTetap::where('nup', '3.10.01.00002')->sole()->nama_aset);
    }

    public function test_synced_at_diperbarui_pada_setiap_putaran(): void
    {
        $this->kategoriAset();
        $aset = $this->buatAset(null, ['nup' => '3.10.01.00001', 'synced_at' => now()->subYear()]);

        // Kolom synced_at tidak di-cast pada model; Filament yang memformatnya
        // saat ditampilkan. Di sini nilainya dibandingkan sebagai waktu.
        $lama = Carbon::parse($aset->synced_at);

        $this->sinkron([['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', '']]);

        $this->assertTrue(Carbon::parse($aset->refresh()->synced_at)->gt($lama));
    }

    // =====================================================================
    // BATAS KEWENANGAN — YANG TIDAK BOLEH DITIMPA
    // =====================================================================

    /**
     * Penempatan aset yang sudah tercatat tidak boleh berpindah dari berkas.
     * Perpindahan antar tim kerja hanya sah lewat BAST mutasi.
     */
    public function test_penempatan_aset_yang_sudah_ada_tidak_ditimpa(): void
    {
        $this->kategoriAset();
        $asal  = $this->buatTim('Statistik Sosial');
        $lain  = $this->buatTim('Statistik Distribusi');
        $aset  = $this->buatAset($asal, ['nup' => '3.10.01.00001']);

        $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', 'Statistik Distribusi', ''],
        ]);

        $this->assertSame(
            $asal->id,
            $aset->refresh()->tim_penempatan_id,
            'Berkas sumber tidak boleh memindahkan aset yang sudah ditempatkan.',
        );
        $this->assertNotSame($lain->id, $aset->tim_penempatan_id);
    }

    /** Aset yang sengaja dinonaktifkan tidak boleh dihidupkan kembali berkas. */
    public function test_status_aktif_tidak_ditimpa(): void
    {
        $this->kategoriAset();
        $aset = $this->buatAset(null, ['nup' => '3.10.01.00001', 'status_aktif' => false]);

        $this->sinkron([['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', '']]);

        $this->assertFalse((bool) $aset->refresh()->status_aktif);
    }

    /**
     * Riwayat penempatan dan BAST yang sudah terbit tetap utuh.
     *
     * Urutan alur dibalik (A): aset kini berpindah pada langkah Konfirmasi
     * Penerimaan, bukan lagi pada Sahkan.
     */
    public function test_riwayat_penempatan_dan_bast_tidak_tersentuh(): void
    {
        $this->kategoriAset();
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Distribusi');
        $ketuaTujuan = $this->buatPengguna('ketua_tim', $tujuan);
        $bast   = $this->buatBast($asal, $tujuan, $this->buatPengguna('petugas_gudang'), status: 'menunggu_konfirmasi');

        app(MutasiAsetService::class)->konfirmasi($bast, $ketuaTujuan->id);

        $aset            = AsetTetap::findOrFail($bast->aset_id);
        $penempatanAkhir = $aset->tim_penempatan_id;
        $jumlahRiwayat   = RiwayatPenempatanAset::where('aset_id', $aset->id)->count();

        $this->sinkron([
            [$aset->nup, 'Nama Dari Sumber', 'Peralatan dan Mesin', '', 'Sub Bagian Umum', 'BMN-9'],
        ]);

        $aset->refresh();

        $this->assertSame('Nama Dari Sumber', $aset->nama_aset, 'Nama tetap boleh diperbarui.');
        $this->assertSame($penempatanAkhir, $aset->tim_penempatan_id, 'Mutasi BAST tidak boleh dibatalkan.');
        $this->assertSame($jumlahRiwayat, RiwayatPenempatanAset::where('aset_id', $aset->id)->count());
        // konfirmasi() menghentikan BAST pada tahap menunggu pengesahan
        // Kasubbag; yang diuji di sini adalah bahwa sinkronisasi tidak
        // menggesernya.
        $this->assertSame('menunggu_pengesahan', $bast->refresh()->status);
    }

    /** Kolom yang dikosongkan berarti tidak diubah, bukan dikosongkan. */
    public function test_kolom_kosong_tidak_menimpa_nilai_tersimpan(): void
    {
        $this->kategoriAset();
        $aset = $this->buatAset(null, [
            'nup'         => '3.10.01.00001',
            'kondisi'     => 'rusak_ringan',
            'external_id' => 'BMN-LAMA',
        ]);

        $this->sinkron([['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', '']]);

        $aset->refresh();
        $this->assertSame('rusak_ringan', $aset->kondisi);
        $this->assertSame('BMN-LAMA', $aset->external_id);
    }

    /** Aset yang tidak muncul pada berkas tidak dihapus. */
    public function test_aset_di_luar_berkas_tidak_dihapus(): void
    {
        $this->kategoriAset();
        $lain = $this->buatAset(null, ['nup' => '3.10.01.00099']);

        $this->sinkron([['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', '']]);

        $this->assertDatabaseHas('aset_tetap', ['id' => $lain->id]);
        $this->assertSame(2, AsetTetap::count());
    }

    // =====================================================================
    // KETERPADUAN DENGAN T-10
    // =====================================================================

    public function test_aset_baru_memperoleh_penempatan_awal(): void
    {
        $this->kategoriAset();
        $tim = $this->buatTim('Statistik Sosial');

        $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', 'Statistik Sosial', ''],
        ]);

        $aset = AsetTetap::sole();

        $this->assertDatabaseHas('riwayat_penempatan_aset', [
            'aset_id'         => $aset->id,
            'tim_id'          => $tim->id,
            'jenis'           => 'penempatan_awal',
            'tanggal_selesai' => null,
        ]);
        $this->assertSame(1, $aset->riwayatPenempatan()->count());
    }

    /** Tanpa tim kerja, tidak ada riwayat yang dikarang. */
    public function test_aset_baru_tanpa_tim_tidak_memperoleh_riwayat(): void
    {
        $this->kategoriAset();

        $this->sinkron([['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', '']]);

        $this->assertSame(0, RiwayatPenempatanAset::count());
        $this->assertNull(AsetTetap::sole()->tim_penempatan_id);
    }

    // =====================================================================
    // BARIS YANG DITOLAK
    // =====================================================================

    public function test_kategori_yang_belum_terdaftar_ditolak(): void
    {
        $this->kategoriAset();

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Kategori Yang Belum Ada', '', '', ''],
        ]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertSame(0, AsetTetap::count());
        $this->assertStringContainsString('belum terdaftar', $hasil->galat[0]);
        $this->assertSame(1, Kategori::count(), 'Kategori tidak boleh dibuat diam-diam.');
    }

    /** Kategori persediaan bukan kategori aset tetap. */
    public function test_kategori_persediaan_tidak_diterima(): void
    {
        $this->kategoriAset();
        Kategori::create([
            'kode_akun'     => '117111',
            'kode_kategori' => '1010301001',
            'nama_kategori' => 'Alat Tulis',
            'tipe'          => 'persediaan',
        ]);

        $hasil = $this->sinkron([['3.10.01.00001', 'Laptop', 'Alat Tulis', '', '', '']]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('belum terdaftar', $hasil->galat[0]);
    }

    public function test_nup_kosong_ditolak(): void
    {
        $this->kategoriAset();

        $hasil = $this->sinkron([['', 'Laptop', 'Peralatan dan Mesin', '', '', '']]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('NUP', $hasil->galat[0]);
    }

    public function test_kondisi_yang_tidak_dikenali_ditolak(): void
    {
        $this->kategoriAset();

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', 'Setengah Rusak', '', ''],
        ]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('Setengah Rusak', $hasil->galat[0]);
    }

    public function test_tim_penempatan_yang_belum_terdaftar_ditolak(): void
    {
        $this->kategoriAset();

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', 'Tim Yang Tidak Ada', ''],
        ]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertStringContainsString('belum terdaftar', $hasil->galat[0]);
    }

    /** Satu baris bermasalah tidak menahan baris lain yang sudah benar. */
    public function test_baris_bermasalah_tidak_menahan_baris_lain(): void
    {
        $this->kategoriAset();

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', ''],
            ['', 'Tanpa NUP', 'Peralatan dan Mesin', '', '', ''],
            ['3.10.01.00002', 'Printer', 'Peralatan dan Mesin', '', '', ''],
        ]);

        $this->assertSame(2, $hasil->ditambah);
        $this->assertCount(1, $hasil->galat);
        $this->assertStringContainsString('Baris 3', $hasil->galat[0]);
    }

    // =====================================================================
    // TEMPLATE
    // =====================================================================

    public function test_template_yang_diunduh_dapat_disinkronkan_kembali(): void
    {
        $this->kategoriAset();
        $this->buatTim('Statistik Sosial');

        $respons = app(PembuatTemplate::class)->buat(
            ImporAsetTetap::JUDUL,
            ImporAsetTetap::kolom(),
            'Template-Sinkronisasi-Aset-Tetap.xlsx',
        );

        $hasil = app(ImporAsetTetap::class)->jalankan($respons->getFile()->getPathname());

        $this->assertSame(1, $hasil->ditambah);
        $this->assertFalse($hasil->adaGalat(), implode(' | ', $hasil->galat));
        $this->assertSame('3.10.01.00001', AsetTetap::sole()->nup);
    }

    // =====================================================================
    // PENCOCOKAN NAMA TANPA PEKA HURUF
    // =====================================================================

    /**
     * Kapitalisasi berbeda menunjuk kategori yang sama.
     *
     * Pada SQLite pembandingan `=` bersifat biner, sehingga tanpa penyeragaman
     * berkas yang menulis "PERALATAN DAN MESIN" akan ditolak seolah kategorinya
     * belum terdaftar.
     */
    public function test_kategori_cocok_walau_kapitalisasinya_berbeda(): void
    {
        $this->kategoriAset();

        foreach (['PERALATAN DAN MESIN', 'peralatan dan mesin', '  Peralatan Dan Mesin  '] as $i => $tulisan) {
            $hasil = $this->sinkron([
                ['3.10.01.0000' . $i, 'Laptop', $tulisan, '', '', ''],
            ]);

            $this->assertFalse($hasil->adaGalat(), 'Gagal pada penulisan: ' . $tulisan);
            $this->assertSame(1, $hasil->ditambah);
        }

        $this->assertSame(3, AsetTetap::count());
        $this->assertSame(1, Kategori::count(), 'Kategori tidak boleh bertambah.');
    }

    public function test_tim_penempatan_cocok_walau_kapitalisasinya_berbeda(): void
    {
        $this->kategoriAset();
        $tim = $this->buatTim('Statistik Sosial');

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', 'STATISTIK SOSIAL', ''],
        ]);

        $this->assertFalse($hasil->adaGalat(), implode(' | ', $hasil->galat));
        $this->assertSame($tim->id, AsetTetap::sole()->tim_penempatan_id);
        $this->assertSame(1, Tim::count(), 'Tim kerja tidak boleh bertambah.');
    }

    // =====================================================================
    // KOLOM YANG SENGAJA DIABAIKAN DILAPORKAN
    // =====================================================================

    public function test_kolom_penempatan_yang_diabaikan_dilaporkan(): void
    {
        $this->kategoriAset();
        $this->buatTim('Statistik Distribusi');
        $this->buatAset($this->buatTim('Statistik Sosial'), ['nup' => '3.10.01.00001']);

        $hasil = $this->sinkron([
            ['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', 'Statistik Distribusi', ''],
        ]);

        $this->assertSame(1, $hasil->diperbarui);
        $this->assertTrue($hasil->adaCatatan());
        $this->assertStringContainsString('Tim Kerja Penempatan', $hasil->catatan[0]);
        $this->assertStringContainsString('BAST mutasi', $hasil->catatan[0]);
    }

    /** Tanpa kolom penempatan yang diabaikan, tidak ada catatan yang dibuat. */
    public function test_tanpa_kolom_diabaikan_tidak_ada_catatan(): void
    {
        $this->kategoriAset();

        $hasil = $this->sinkron([['3.10.01.00001', 'Laptop', 'Peralatan dan Mesin', '', '', '']]);

        $this->assertFalse($hasil->adaCatatan());
    }

    // =====================================================================
    // TIM KERJA — PROVENANS
    // =====================================================================

    public function test_sinkronisasi_tim_kerja_mencatat_provenans(): void
    {
        $lintasan = $this->berkas([
            ['Nama Tim', 'ID Sumber', 'Aktif'],
            ['Statistik Sosial', 'TIM-01', 'Ya'],
        ]);

        $hasil = app(\App\Services\Impor\ImporTimKerja::class)->jalankan($lintasan);
        @unlink($lintasan);

        $this->assertSame(1, $hasil->ditambah);

        $tim = Tim::sole();
        $this->assertSame('TIM-01', $tim->external_id);
        $this->assertNotNull($tim->synced_at);
    }

    /**
     * Berkas yang menulis nama tim dengan kapitalisasi berbeda memperbarui tim
     * yang sudah ada, bukan melahirkan tim kembar.
     *
     * Inilah akibat terburuk dari pencocokan yang peka huruf: sinkronisasi
     * tidak pernah menghapus, sehingga tim kembar itu menetap dan permintaan
     * berikutnya dapat menunjuk tim yang keliru.
     */
    public function test_tim_kerja_cocok_walau_kapitalisasinya_berbeda(): void
    {
        $this->buatTim('Statistik Sosial');

        foreach (['STATISTIK SOSIAL', 'statistik sosial', 'Statistik  Sosial'] as $tulisan) {
            $lintasan = $this->berkas([
                ['Nama Tim', 'ID Sumber', 'Aktif'],
                [$tulisan, '', 'Ya'],
            ]);

            $hasil = app(\App\Services\Impor\ImporTimKerja::class)->jalankan($lintasan);
            @unlink($lintasan);

            $this->assertSame(0, $hasil->ditambah, 'Gagal pada penulisan: ' . $tulisan);
            $this->assertSame(1, $hasil->diperbarui);
        }

        $this->assertSame(1, Tim::count(), 'Tidak boleh ada tim kerja kembar.');
        $this->assertSame('Statistik Sosial', Tim::sole()->nama_tim, 'Ejaan resmi tidak diubah.');
    }

    /** ID Sumber yang dikosongkan tidak menghapus penanda yang tersimpan. */
    public function test_id_sumber_tim_kosong_tidak_menimpa(): void
    {
        $tim = $this->buatTim('Statistik Sosial');
        $tim->forceFill(['external_id' => 'TIM-LAMA'])->save();

        $lintasan = $this->berkas([
            ['Nama Tim', 'ID Sumber', 'Aktif'],
            ['Statistik Sosial', '', 'Ya'],
        ]);

        app(\App\Services\Impor\ImporTimKerja::class)->jalankan($lintasan);
        @unlink($lintasan);

        $tim->refresh();
        $this->assertSame('TIM-LAMA', $tim->external_id);
        $this->assertNotNull($tim->synced_at);
    }
}
