<?php

namespace Tests\Feature;

use App\Support\Cadangan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/**
 * Perkakas pemeliharaan: pencadangan, pemulihan, dan reset demo.
 *
 * Seluruh pengujian di sini dijalankan di atas direktori `storage` sementara
 * lewat `useStoragePath()`, dan basis data SQLite yang berdiri sendiri di
 * dalamnya. Tanpa pemisahan itu, pengujian pemulihan akan mengosongkan
 * direktori dokumen yang sesungguhnya — tanda tangan pegawai dan bukti
 * permintaan yang sudah disahkan — sebab pemulihan memang bertugas menimpa.
 *
 * Rangkaian uji memakai SQLite `:memory:`, yang tidak punya berkas untuk
 * ditimpa, sehingga di sini basis datanya sengaja dipindahkan ke berkas.
 */
class PemeliharaanDataTest extends TestCase
{
    private string $storage;

    private string $berkasDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'simpbi-uji-' . Str::random(10);

        foreach (array_values(Cadangan::DATA) as $relatif) {
            @mkdir($this->storage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relatif), 0777, true);
        }

        $this->app->useStoragePath($this->storage);

        $this->berkasDb = $this->storage . DIRECTORY_SEPARATOR . 'uji.sqlite';
        touch($this->berkasDb);

        // Seluruh kelas ini memeriksa cadangan dan pemulihan pada berkas SQLite
        // miliknya sendiri, apa pun basis data yang dipakai rangkaian uji
        // lainnya. Tanpa baris ini, di bawah MySQL uji-uji ini berjalan pada
        // basis data rangkaian uji dan berharap penggeraknya "sqlite".
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $this->berkasDb]);
        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        $this->hapusDirektori($this->storage);

        parent::tearDown();
    }

    public function test_arsip_terbentuk_bercap_waktu_beserta_manifesnya(): void
    {
        $hasil = Cadangan::buat();

        $this->assertFileExists($hasil['lintasan']);
        $this->assertMatchesRegularExpression(
            '/simpbi-backup-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/',
            $hasil['lintasan'],
            'Nama arsip harus bercap waktu agar tidak menimpa cadangan sebelumnya.',
        );

        $manifes = Cadangan::periksa($hasil['lintasan']);

        $this->assertSame('SIMPBI', $manifes['aplikasi']);
        $this->assertSame('sqlite', $manifes['penggerak']);
        $this->assertSame('basis-data.sqlite', $manifes['berkas_basis_data']);
        $this->assertGreaterThan(0, $manifes['bita_curahan'], 'Curahan basis data tidak boleh kosong.');
    }

    /**
     * Cadangan berpindah tempat dan berganti tangan, sehingga kredensial tidak
     * boleh ikut di dalamnya.
     */
    public function test_arsip_tidak_memuat_env_maupun_kredensial(): void
    {
        $hasil = Cadangan::buat();

        $zip = new ZipArchive;
        $zip->open($hasil['lintasan']);

        $anggota = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $anggota[] = $zip->getNameIndex($i);
        }

        $zip->close();

        foreach ($anggota as $nama) {
            $this->assertStringNotContainsString('.env', $nama, "Arsip memuat {$nama}.");
            $this->assertStringNotContainsString('config/', $nama, "Arsip memuat {$nama}.");
        }

        $isi = (string) file_get_contents($hasil['lintasan']);

        foreach (['APP_KEY', 'WHATSAPP_FONNTE_TOKEN', 'DB_PASSWORD'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $isi, "Arsip memuat {$rahasia}.");
        }
    }

    /**
     * Yang dipulihkan bukan hanya basis data.
     *
     * Tanda tangan dan dokumen yang sudah terbit hanya tersimpan sebagai
     * berkas, dan dokumen sengaja dibekukan saat terbit sehingga tidak dapat
     * dibentuk ulang secara setara.
     */
    public function test_basis_data_dan_berkas_kembali_seperti_semula(): void
    {
        $dokumen = storage_path('app/private/bukti-permintaan/PB-UJI.pdf');
        file_put_contents($dokumen, '%PDF-uji');

        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => 'sebelum']);

        $arsip = Cadangan::buat()['lintasan'];

        // Rusakkan keduanya: isi basis data dan berkas dokumennya.
        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => 'sesudah']);
        unlink($dokumen);
        file_put_contents(storage_path('app/private/bukti-permintaan/NYASAR.pdf'), 'x');

        Cadangan::pulihkan($arsip, Cadangan::periksa($arsip));
        DB::purge('sqlite');

        $this->assertSame('sebelum', DB::table('pengaturan')->where('kunci', 'wa_aktif')->value('nilai'));
        $this->assertFileExists($dokumen, 'Dokumen yang dihapus harus kembali.');
        $this->assertFileDoesNotExist(
            storage_path('app/private/bukti-permintaan/NYASAR.pdf'),
            'Berkas yang tidak ada di dalam cadangan harus ikut tersapu.',
        );
    }

    /**
     * Arsip yang tidak lolos pemeriksaan tidak boleh sampai pada tahap yang
     * menyentuh data.
     */
    #[DataProvider('arsipTidakSah')]
    public function test_pemulihan_menolak_arsip_yang_tidak_sah(string $bentuk, string $pesan): void
    {
        $berkas = $this->storage . DIRECTORY_SEPARATOR . 'rusak.zip';

        match ($bentuk) {
            'bukan-zip'      => file_put_contents($berkas, 'ini bukan arsip'),
            'tanpa-manifes'  => $this->buatZip($berkas, ['sembarang.txt' => 'halo']),
            'penggerak-lain' => $this->buatZip($berkas, [
                Cadangan::MANIFES => json_encode([
                    'aplikasi' => 'SIMPBI', 'versi_manifes' => 1, 'penggerak' => 'mysql',
                    'basis_data' => 'x', 'berkas_basis_data' => 'basis-data.sql',
                ]),
                'basis-data.sql' => '-- curahan',
            ]),
            'tanpa-curahan'  => $this->buatZip($berkas, [
                Cadangan::MANIFES => json_encode([
                    'aplikasi' => 'SIMPBI', 'versi_manifes' => 1, 'penggerak' => 'sqlite',
                    'basis_data' => 'x', 'berkas_basis_data' => 'basis-data.sqlite',
                ]),
            ]),
        };

        $sebelum = DB::table('pengaturan')->count();

        $this->artisan('simpbi:restore', ['berkas' => $berkas, '--paksa' => true])
            ->expectsOutputToContain($pesan)
            ->assertFailed();

        $this->assertSame($sebelum, DB::table('pengaturan')->count(), 'Data tidak boleh tersentuh.');
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function arsipTidakSah(): array
    {
        return [
            'bukan arsip'            => ['bukan-zip', 'bukan arsip yang dapat dibaca'],
            'tanpa manifes'          => ['tanpa-manifes', 'bukan cadangan SIMPBI'],
            'penggerak tidak cocok'  => ['penggerak-lain', 'tidak saling dapat dibaca'],
            'curahan tidak ada'      => ['tanpa-curahan', 'tidak memuat curahan basis data'],
        ];
    }

    public function test_pemulihan_meminta_konfirmasi_sebelum_menimpa(): void
    {
        $arsip = Cadangan::buat()['lintasan'];

        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => 'tidak-boleh-hilang']);

        $this->artisan('simpbi:restore', ['berkas' => $arsip])
            ->expectsConfirmation('Lanjutkan pemulihan?', 'no')
            ->expectsOutputToContain('Dibatalkan')
            ->assertSuccessful();

        $this->assertSame(
            'tidak-boleh-hilang',
            DB::table('pengaturan')->where('kunci', 'wa_aktif')->value('nilai'),
            'Menjawab tidak harus meninggalkan data apa adanya.',
        );
    }

    /**
     * Penjaga produksi tidak dapat dilewati opsi apa pun, termasuk --paksa.
     *
     * Reset menghapus seluruh riwayat permintaan, pengesahan, dan dokumen; pada
     * peladen sungguhan tidak ada keadaan yang membenarkannya.
     */
    public function test_reset_demo_ditolak_pada_lingkungan_produksi(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $sebelum = DB::table('pengaturan')->count();

        $this->artisan('simpbi:reset-demo', ['--paksa' => true])
            ->expectsOutputToContain('APP_ENV=production')
            ->assertFailed();

        $this->assertSame($sebelum, DB::table('pengaturan')->count(), 'Tidak satu baris pun boleh hilang.');
    }

    public function test_reset_demo_meminta_konfirmasi(): void
    {
        $this->artisan('simpbi:reset-demo')
            ->expectsConfirmation('Lanjutkan reset demo?', 'no')
            ->expectsOutputToContain('Dibatalkan')
            ->assertSuccessful();

        $this->assertTrue(
            DB::getSchemaBuilder()->hasTable('pengaturan'),
            'Menjawab tidak harus meninggalkan tabel apa adanya.',
        );
    }

    /** Menyapu direktori dokumen tanpa menghapus direktorinya sendiri. */
    public function test_penyapuan_dokumen_menyisakan_direktorinya(): void
    {
        $direktori = storage_path('app/private/bukti-permintaan');
        file_put_contents($direktori . DIRECTORY_SEPARATOR . 'a.pdf', 'a');
        file_put_contents($direktori . DIRECTORY_SEPARATOR . 'b.pdf', 'b');

        $this->assertSame(2, Cadangan::kosongkanDirektori($direktori));
        $this->assertDirectoryExists($direktori);
        $this->assertCount(0, glob($direktori . DIRECTORY_SEPARATOR . '*'));
    }

    /** @param array<string,string> $isi */
    private function buatZip(string $lintasan, array $isi): void
    {
        $zip = new ZipArchive;
        $zip->open($lintasan, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($isi as $nama => $data) {
            $zip->addFromString($nama, $data);
        }

        $zip->close();
    }

    private function hapusDirektori(string $direktori): void
    {
        if (! is_dir($direktori)) {
            return;
        }

        $isi = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($direktori, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($isi as $berkas) {
            $berkas->isDir() ? @rmdir($berkas->getPathname()) : @unlink($berkas->getPathname());
        }

        @rmdir($direktori);
    }
}
