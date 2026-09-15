<?php

namespace Tests\Feature;

use App\Models\Tim;
use App\Services\Impor\ImporTimKerja;
use App\Services\Impor\PembuatTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Impor daftar tim kerja beserta templatnya.
 *
 * Yang paling menentukan pada sebuah impor bukan jalur mulusnya, melainkan apa
 * yang terjadi pada berkas yang diisi manusia: kolom tertukar urutan, tajuk
 * berbeda besar-kecil hurufnya, baris kosong di tengah, dan berkas yang sama
 * diunggah dua kali. Keempatnya diuji di sini.
 */
class ImporTimKerjaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /**
     * Menulis berkas xlsx sementara dan mengembalikan lintasannya.
     *
     * @param  list<list<string>>  $baris
     */
    private function berkas(array $baris): string
    {
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_impor_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($lintasan);

        foreach ($baris as $b) {
            $writer->addRow(Row::fromValues($b));
        }

        $writer->close();

        return $lintasan;
    }

    private function impor(array $baris): \App\Services\Impor\HasilImpor
    {
        $lintasan = $this->berkas($baris);
        $hasil = app(ImporTimKerja::class)->jalankan($lintasan);
        @unlink($lintasan);

        return $hasil;
    }

    // =====================================================================
    // JALUR NORMAL
    // =====================================================================

    public function test_baris_baru_tercatat(): void
    {
        $hasil = $this->impor([
            ['Nama Tim *', 'Aktif'],
            ['Statistik Sosial', 'Ya'],
            ['Statistik Distribusi', 'Ya'],
            ['Tim Lama', 'Tidak'],
        ]);

        $this->assertSame(3, $hasil->ditambah);
        $this->assertSame(0, $hasil->diperbarui);
        $this->assertFalse($hasil->adaGalat());

        $this->assertSame(3, Tim::count());
        $this->assertFalse((bool) Tim::where('nama_tim', 'Tim Lama')->value('status_aktif'));
    }

    /**
     * Mengimpor ulang berkas yang sama tidak boleh melahirkan tim kembar —
     * tim kembar akan memecah anggotanya ke dua tempat tanpa ada yang sadar.
     */
    public function test_mengimpor_ulang_memperbarui_bukan_menggandakan(): void
    {
        $isi = [
            ['Nama Tim *', 'Aktif'],
            ['Statistik Sosial', 'Ya'],
        ];

        $this->impor($isi);
        $kedua = $this->impor([
            ['Nama Tim *', 'Aktif'],
            ['Statistik Sosial', 'Tidak'],
        ]);

        $this->assertSame(0, $kedua->ditambah);
        $this->assertSame(1, $kedua->diperbarui);
        $this->assertSame(1, Tim::count());
        $this->assertFalse((bool) Tim::sole()->status_aktif);
    }

    /** Anggota dan riwayat tim yang sudah ada tidak boleh tersentuh impor. */
    public function test_impor_ulang_tidak_melepas_anggota_tim(): void
    {
        $tim = Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);
        $anggota = $this->buatPengguna('tim', $tim);

        $this->impor([
            ['Nama Tim *', 'Aktif'],
            ['Statistik Sosial', 'Ya'],
        ]);

        $this->assertSame($tim->id, $anggota->refresh()->tim_id);
        $this->assertSame(1, Tim::count());
    }

    // =====================================================================
    // BERKAS YANG DIISI MANUSIA
    // =====================================================================

    /**
     * Kolom dicocokkan menurut tajuknya, bukan urutannya. Menambah kolom
     * catatan sendiri di tengah berkas adalah kebiasaan yang sangat lazim pada
     * lembar sebar kantor.
     */
    public function test_urutan_kolom_boleh_berbeda(): void
    {
        $hasil = $this->impor([
            ['Aktif', 'Catatan Saya', 'Nama Tim'],
            ['Tidak', 'entah apa', 'Statistik Sosial'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertSame('Statistik Sosial', Tim::sole()->nama_tim);
        $this->assertFalse((bool) Tim::sole()->status_aktif);
    }

    public function test_tajuk_beda_huruf_dan_spasi_tetap_dikenali(): void
    {
        $hasil = $this->impor([
            ['  NAMA   TIM  ', 'aktif'],
            ['Statistik Sosial', 'YA'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertTrue((bool) Tim::sole()->status_aktif);
    }

    public function test_baris_kosong_di_tengah_dilewati_tanpa_keluhan(): void
    {
        $hasil = $this->impor([
            ['Nama Tim', 'Aktif'],
            ['Statistik Sosial', 'Ya'],
            ['', ''],
            ['Statistik Distribusi', 'Ya'],
        ]);

        $this->assertSame(2, $hasil->ditambah);
        $this->assertFalse($hasil->adaGalat(), 'Baris kosong bukan galat yang perlu ditindaklanjuti.');
    }

    public function test_kolom_aktif_kosong_berarti_aktif(): void
    {
        $this->impor([
            ['Nama Tim', 'Aktif'],
            ['Statistik Sosial', ''],
        ]);

        $this->assertTrue((bool) Tim::sole()->status_aktif);
    }

    // =====================================================================
    // BARIS YANG DITOLAK
    // =====================================================================

    /**
     * Baris yang sah tetap masuk meski ada baris lain yang bermasalah: satu
     * kesalahan ketik tidak boleh menahan tim-tim lainnya.
     */
    public function test_baris_bermasalah_dilaporkan_tanpa_menahan_yang_lain(): void
    {
        $hasil = $this->impor([
            ['Nama Tim', 'Aktif'],
            ['Statistik Sosial', 'Ya'],
            ['', 'Ya'],
            ['Statistik Distribusi', 'mungkin'],
            ['Statistik Pertanian dan Industri', 'Ya'],
        ]);

        $this->assertSame(2, $hasil->ditambah);
        $this->assertCount(2, $hasil->galat);
        $this->assertStringContainsString('Baris 3', $hasil->galat[0]);
        $this->assertStringContainsString('Baris 4', $hasil->galat[1]);
        $this->assertStringContainsString('mungkin', $hasil->galat[1]);
    }

    /**
     * Nilai yang tidak dapat ditafsirkan tidak boleh ditebak: menonaktifkan
     * tim tanpa disadari akan menyembunyikannya dari seluruh formulir.
     */
    public function test_nilai_aktif_yang_asing_tidak_ditebak(): void
    {
        $hasil = $this->impor([
            ['Nama Tim', 'Aktif'],
            ['Statistik Sosial', 'kadang-kadang'],
        ]);

        $this->assertSame(0, Tim::count());
        $this->assertTrue($hasil->adaGalat());
    }

    // =====================================================================
    // TEMPLATE
    // =====================================================================

    /**
     * Templat dan pembacanya lahir dari satu daftar kolom yang sama, sehingga
     * berkas yang diunduh lalu diisi wajib dapat diimpor kembali apa adanya.
     * Inilah pengujian yang paling menentukan: kalau tajuknya sampai berbeda,
     * pengguna akan menyalahkan dirinya sendiri atas kegagalan yang bukan
     * salahnya.
     */
    public function test_template_yang_diunduh_dapat_diimpor_kembali(): void
    {
        $respons = app(PembuatTemplate::class)->buat(
            ImporTimKerja::JUDUL,
            ImporTimKerja::kolom(),
            'Template-Impor-Tim-Kerja.xlsx',
        );

        $lintasan = $respons->getFile()->getPathname();

        // Templat berisi tajuk dan satu baris contoh; contohnya harus terbaca
        // sebagai data yang sah.
        $hasil = app(ImporTimKerja::class)->jalankan($lintasan);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertFalse($hasil->adaGalat());
        $this->assertSame(
            'Statistik Pertambangan, Energi dan Konstruksi (PEK)',
            Tim::sole()->nama_tim,
        );
    }

    /** Lembar petunjuk tidak boleh ikut terbaca sebagai data. */
    public function test_lembar_petunjuk_tidak_ikut_diimpor(): void
    {
        $respons = app(PembuatTemplate::class)->buat(
            ImporTimKerja::JUDUL,
            ImporTimKerja::kolom(),
            'Template-Impor-Tim-Kerja.xlsx',
        );

        app(ImporTimKerja::class)->jalankan($respons->getFile()->getPathname());

        $this->assertSame(1, Tim::count(), 'Hanya baris contoh pada lembar Data yang boleh terbaca.');
    }
}
