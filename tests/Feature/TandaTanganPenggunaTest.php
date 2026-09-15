<?php

namespace Tests\Feature;

use App\Filament\Pages\Pengaturan;
use App\Support\TandaTangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Penyimpanan tanda tangan pengguna (bahan dokumen bukti permintaan).
 *
 * Tanda tangan datang dari kanvas di peramban sebagai data URI, yaitu kiriman
 * yang sepenuhnya dikendalikan sisi klien. Karena itu yang diuji bukan hanya
 * jalur normalnya, melainkan juga bahwa kiriman yang tidak berbentuk PNG
 * ditolak — kolom ini adalah pintu unggah berkas yang menyamar sebagai kolom
 * teks biasa.
 */
class TandaTanganPenggunaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /**
     * PNG 1×1 piksel yang sah, cukup untuk mewakili hasil goresan kanvas tanpa
     * perlu menyertakan gambar besar di dalam berkas pengujian.
     */
    private function pngSah(): string
    {
        return 'data:image/png;base64,' . base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // =====================================================================
    // PENYIMPANAN
    // =====================================================================

    public function test_tanda_tangan_tersimpan_pada_cakram_tertutup(): void
    {
        $pengguna = $this->buatPengguna('petugas_gudang');

        $lintasan = TandaTangan::simpan($pengguna, $this->pngSah());

        Storage::disk('local')->assertExists($lintasan);
        $this->assertStringStartsWith(TandaTangan::DIREKTORI . '/', $lintasan);

        $pengguna->refresh();
        $this->assertSame($lintasan, $pengguna->tanda_tangan_path);
        $this->assertNotNull($pengguna->tanda_tangan_at);
    }

    /**
     * Hanya satu tanda tangan yang berlaku pada satu waktu. Berkas lama yang
     * tertinggal bukan sekadar sampah: ia tetap berupa tanda tangan pegawai
     * yang masih dapat dibaca siapa pun yang kelak menjangkau cakramnya.
     */
    public function test_menggambar_ulang_menghapus_berkas_sebelumnya(): void
    {
        $pengguna = $this->buatPengguna('ketua_tim', $this->buatTim());

        $lama = TandaTangan::simpan($pengguna, $this->pngSah());
        $baru = TandaTangan::simpan($pengguna->refresh(), $this->pngSah());

        $this->assertNotSame($lama, $baru);
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($baru);
    }

    public function test_penghapusan_membersihkan_berkas_dan_kolomnya(): void
    {
        $pengguna = $this->buatPengguna('kasubbag');
        $lintasan = TandaTangan::simpan($pengguna, $this->pngSah());

        TandaTangan::hapus($pengguna->refresh());

        Storage::disk('local')->assertMissing($lintasan);
        $this->assertNull($pengguna->refresh()->tanda_tangan_path);
        $this->assertFalse(TandaTangan::tersedia($pengguna));
    }

    // =====================================================================
    // PENOLAKAN KIRIMAN YANG TIDAK SAH
    // =====================================================================

    /**
     * @param  string  $kiriman  data URI yang harus ditolak
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('kirimanTidakSah')]
    public function test_kiriman_yang_bukan_png_ditolak(string $kiriman): void
    {
        $pengguna = $this->buatPengguna('petugas_gudang');

        $this->expectException(\InvalidArgumentException::class);

        TandaTangan::simpan($pengguna, $kiriman);
    }

    /** @return array<string,array{string}> */
    public static function kirimanTidakSah(): array
    {
        return [
            'teks biasa'          => ['bukan data uri sama sekali'],
            'jenis lain'          => ['data:image/svg+xml;base64,' . base64_encode('<svg/>')],
            // base64 yang sah, tetapi isinya bukan berkas PNG
            'isi bukan png'       => ['data:image/png;base64,' . base64_encode('halo dunia')],
            'base64 rusak'        => ['data:image/png;base64,@@@@'],
        ];
    }

    public function test_kiriman_melebihi_batas_ukuran_ditolak(): void
    {
        $pengguna = $this->buatPengguna('petugas_gudang');

        $this->expectException(\InvalidArgumentException::class);

        TandaTangan::simpan(
            $pengguna,
            'data:image/png;base64,' . str_repeat('A', TandaTangan::BATAS_BITA),
        );
    }

    // =====================================================================
    // HALAMAN PENGATURAN
    // =====================================================================

    public function test_kolom_tanda_tangan_hanya_untuk_peran_yang_membubuhkannya(): void
    {
        foreach (['petugas_gudang', 'ketua_tim', 'kasubbag'] as $peran) {
            $this->actingAs($this->buatPengguna($peran, $peran === 'ketua_tim' ? $this->buatTim() : null));

            Livewire::test(Pengaturan::class)->assertSee('Tanda Tangan');
        }

        $this->actingAs($this->buatPengguna('admin'));

        Livewire::test(Pengaturan::class)->assertDontSee('Tanda Tangan');
    }

    public function test_tanda_tangan_tersimpan_lewat_halaman_pengaturan(): void
    {
        // Email wajib diisi pada halaman Pengaturan, sehingga pengguna uji
        // harus memilikinya agar yang tergagalkan bukan kolom yang lain.
        $pengguna = $this->buatPengguna('petugas_gudang', null, ['email' => 'gudang.uji@bps.go.id']);
        $this->actingAs($pengguna);

        Livewire::test(Pengaturan::class)
            ->fillForm(['tanda_tangan' => $this->pngSah()])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertTrue(TandaTangan::tersedia($pengguna->refresh()));
    }

    /**
     * Menyimpan formulir untuk urusan lain tidak boleh menghapus tanda tangan
     * yang sudah ada. Kanvas yang tidak disentuh mengirim null, dan null di
     * sini berarti "tidak ada yang berubah", bukan "hapus".
     */
    public function test_menyimpan_tanpa_menggambar_tidak_menghapus_yang_lama(): void
    {
        $pengguna = $this->buatPengguna('petugas_gudang', null, ['email' => 'gudang.lama@bps.go.id']);
        TandaTangan::simpan($pengguna, $this->pngSah());

        $this->actingAs($pengguna->refresh());

        Livewire::test(Pengaturan::class)
            ->fillForm(['name' => 'Nama Sudah Diganti'])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $pengguna->refresh();
        $this->assertSame('Nama Sudah Diganti', $pengguna->name);
        $this->assertTrue(TandaTangan::tersedia($pengguna));
    }

    // =====================================================================
    // PEMANGKASAN RUANG KOSONG
    // =====================================================================

    /**
     * Membuat kanvas dengan satu goresan kecil di sudut, seperti orang yang
     * menandatangani kecil di kanvas yang lebar.
     */
    private function kanvasBergoresan(int $lebar, int $tinggi, int $tebalGores): string
    {
        $img = imagecreatetruecolor($lebar, $tinggi);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagealphablending($img, true);

        $tinta = imagecolorallocate($img, 11, 42, 91);
        imagefilledrectangle($img, 20, 20, 20 + $tebalGores, 20 + $tebalGores, $tinta);

        ob_start();
        imagepng($img);
        $biner = (string) ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($biner);
    }

    /** @return array{0:int,1:int} lebar dan tinggi berkas tersimpan */
    private function ukuranTersimpan(string $lintasan): array
    {
        $img = imagecreatefromstring(Storage::disk('local')->get($lintasan));

        return [imagesx($img), imagesy($img)];
    }

    /**
     * Inti perbaikannya: kanvas yang jauh lebih besar daripada goresannya tidak
     * boleh tersimpan apa adanya. Dokumen mencetak tanda tangan pada tinggi
     * tetap, sehingga sisa ruang yang ikut tersimpan membuat tintanya tercetak
     * mengecil — itulah sebabnya tanda tangan Ketua Tim sempat tampak separuh
     * ukuran tanda tangan Petugas Gudang pada satu surat yang sama.
     */
    public function test_ruang_kosong_di_sekeliling_goresan_dipangkas(): void
    {
        $pengguna = $this->buatPengguna('petugas_gudang');

        $lintasan = TandaTangan::simpan($pengguna, $this->kanvasBergoresan(800, 320, 40));

        [$lebar, $tinggi] = $this->ukuranTersimpan($lintasan);

        $this->assertLessThan(80, $lebar, 'Lebar tersimpan harus mengikuti goresan, bukan kanvasnya.');
        $this->assertLessThan(80, $tinggi);
    }

    /**
     * Dua orang yang menggores sama besar pada kanvas berbeda ukuran harus
     * menghasilkan berkas yang ukurannya kurang lebih sama — itulah yang
     * membuat keduanya tercetak sepadan pada surat.
     */
    public function test_goresan_sama_pada_kanvas_berbeda_menghasilkan_ukuran_sepadan(): void
    {
        $satu = $this->buatPengguna('petugas_gudang');
        $dua  = $this->buatPengguna('kasubbag');

        [$lebarSatu] = $this->ukuranTersimpan(
            TandaTangan::simpan($satu, $this->kanvasBergoresan(400, 160, 40))
        );
        [$lebarDua] = $this->ukuranTersimpan(
            TandaTangan::simpan($dua, $this->kanvasBergoresan(900, 360, 40))
        );

        $this->assertSame(
            $lebarSatu,
            $lebarDua,
            'Ukuran kanvas tidak boleh lagi berpengaruh pada hasil tersimpan.',
        );
    }

    /**
     * Kanvas tanpa satu goresan pun bukan tanda tangan. Membiarkannya tersimpan
     * berarti dokumen terbit dengan ruang tanda tangan yang tampak sengaja
     * dikosongkan.
     */
    public function test_kanvas_tanpa_goresan_ditolak(): void
    {
        $pengguna = $this->buatPengguna('petugas_gudang');

        $kosong = imagecreatetruecolor(400, 160);
        imagealphablending($kosong, false);
        imagesavealpha($kosong, true);
        imagefill($kosong, 0, 0, imagecolorallocatealpha($kosong, 255, 255, 255, 127));
        ob_start();
        imagepng($kosong);
        $biner = (string) ob_get_clean();
        imagedestroy($kosong);

        $this->expectException(\InvalidArgumentException::class);

        TandaTangan::simpan($pengguna, 'data:image/png;base64,' . base64_encode($biner));
    }
}
