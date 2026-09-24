<?php

namespace Tests\Feature;

use App\Models\BastMutasiAset;
use App\Services\DokumenBastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Kode QR e-TTD pada dokumen BAST mutasi aset.
 *
 * BAST beredar ke luar sistem dan membeku begitu disahkan, sehingga alamat di
 * dalam kodenya harus berasal dari tetapan pemasangan — bukan dari mesin yang
 * kebetulan menekan tombol pengesahan. Yang diperiksa di sini adalah gambar
 * kodenya sendiri: dibaca ulang dengan pemindai, bukan sekadar dipastikan
 * terbentuk.
 */
class DokumenBastTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /**
     * Token dan akar alamat dipatri, tidak diacak.
     *
     * Gambar kode QR adalah fungsi murni dari muatannya, sehingga muatan yang
     * tetap menghasilkan gambar yang tetap pula. Itu penting karena pembaca QR
     * bawaan pustaka chillerlan — satu-satunya pembaca yang tersedia di sini —
     * gagal menemukan kode pada sekitar satu dari enam puluh gambar. Kodenya
     * sendiri baik-baik saja: yang gagal dibacanya tetap terbaca pemindai
     * sungguhan. Dengan token acak, kegagalan pembaca itu muncul sebagai
     * pengujian yang gagal sesekali tanpa ada yang rusak.
     *
     * Muatan yang dipakai di sini sudah diperiksa terbaca, berlambang maupun
     * polos. Panjangnya empat puluh aksara, sama dengan token sungguhan.
     *
     * Akar alamat ikut dipatri sebab `phpunit.xml` tidak menetapkan `APP_URL`;
     * tanpa ini muatan kode akan berubah mengikuti `.env` tiap pengembang.
     */
    private const TOKEN_UJI = 'ujidokumenbastmutasiaset0000000000000000';

    private const AKAR_UJI = 'https://simpbi.contoh.go.id';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        config(['app.url' => static::AKAR_UJI]);
    }

    /**
     * BAST yang sudah disahkan, satu-satunya keadaan yang memunculkan kode QR.
     *
     * Pada alur yang dibalik, disahkan_at hanya pernah terisi bersamaan dengan
     * status selesai_administratif (Sahkan kini langkah terakhir) — berbeda
     * dari sebelum urutan dibalik, saat disahkan_at bisa terisi pada status
     * menunggu_konfirmasi (Sahkan langkah pertama). dikonfirmasi_at ikut
     * disertakan supaya keadaan ini konsisten dengan urutan baru.
     */
    private function bastDisahkan(): BastMutasiAset
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Sosial');

        return $this->buatBast(
            $asal,
            $tujuan,
            $this->buatPengguna('petugas_gudang'),
            status: 'selesai_administratif',
            tambahan: [
                'dikonfirmasi_at'      => now()->subMinute(),
                'dikonfirmasi_oleh_id' => $this->buatPengguna('ketua_tim', $tujuan)->id,
                'disahkan_at'          => now(),
                'disahkan_oleh_id'     => $this->buatPengguna('kasubbag')->id,
                'qr_token'             => static::TOKEN_UJI,
            ],
        );
    }

    /**
     * Alamat di dalam kode berasal dari `app.url`, bukan dari host permintaan.
     *
     * Pengujian berjalan di atas host bawaan Laravel; bila alamat masih diambil
     * dari permintaan yang sedang berjalan, host itulah yang akan terbaca.
     */
    public function test_alamat_dalam_kode_qr_berasal_dari_app_url(): void
    {
        $bast = $this->bastDisahkan();
        $data = $this->dataTampilan($bast);

        $alamat = $this->bacaKodeQr($data['qr']);

        $this->assertSame(
            static::AKAR_UJI . "/verifikasi-bast/{$bast->refresh()->qr_token}",
            $alamat,
        );
        $this->assertStringNotContainsString('localhost', $alamat);
        $this->assertStringNotContainsString('127.0.0.1', $alamat);
        $this->assertStringNotContainsString('null', $alamat);
    }

    /**
     * Lambang menyatu di dalam kode, bukan ditumpangkan di atasnya.
     *
     * Selama lambang masih berupa gambar kedua yang diletakkan di atas kode
     * oleh CSS, gambar kodenya sendiri tetap hitam-putih dan pengujian ini
     * gagal.
     */
    public function test_lambang_menyatu_di_pusat_kode_dan_kode_tetap_terbaca(): void
    {
        $data = $this->dataTampilan($this->bastDisahkan());

        $this->assertTrue(
            $this->berwarnaDiPusat($data['qr']),
            'Kode e-TTD BAST tidak berlambang.',
        );
        $this->assertNotSame('', $this->bacaKodeQr($data['qr']));
    }

    /**
     * Kode pada catatan kaki menuju alamat yang sama dengan stempel, dan
     * dibiarkan polos.
     *
     * BAST tidak punya berkas "asli" yang terbuka tanpa masuk sistem, sehingga
     * tidak ada tujuan kedua yang dapat dirujuk catatan kakinya — keduanya
     * memang sengaja menuju halaman verifikasi yang sama.
     */
    public function test_kode_catatan_kaki_menuju_halaman_verifikasi_yang_sama(): void
    {
        $data = $this->dataTampilan($this->bastDisahkan());

        $this->assertSame(
            $this->bacaKodeQr($data['qr']),
            $this->bacaKodeQr($data['qrFootnote']),
        );

        $this->assertFalse(
            $this->berwarnaDiPusat($data['qrFootnote']),
            'Kode catatan kaki seharusnya tanpa lambang.',
        );
    }

    /**
     * Jabatan kedua pihak dibaca dari relasi tim, bukan dikarang.
     *
     * Kolom pihak penyerah dan penerima hanyalah teks bebas yang diketik
     * operator, sehingga jabatannya tidak tersimpan di mana pun; yang pasti
     * diketahui sistem hanyalah tim kerja asal dan tim kerja tujuan asetnya.
     */
    public function test_jabatan_kedua_pihak_dibaca_dari_relasi_tim(): void
    {
        $bast = $this->bastDisahkan();

        $keluaran = $this->dataTampilan($bast);
        $tampilan = view('pdf.bast-mutasi', $keluaran)->render();

        $this->assertStringContainsString('Tim Kerja ' . $bast->timAsal->nama_tim, $tampilan);
        $this->assertStringContainsString('Ketua Tim ' . $bast->timTujuan->nama_tim, $tampilan);
    }

    /**
     * Catatan kaki BAST pun tidak boleh mengaku memakai sertifikat elektronik.
     *
     * Alasannya sama dengan bukti permintaan: yang ada token acak dan halaman
     * verifikasi, bukan sertifikat yang diterbitkan siapa pun.
     */
    public function test_catatan_kaki_tidak_mengklaim_sertifikat_elektronik(): void
    {
        $tampilan = view('pdf.bast-mutasi', $this->dataTampilan($this->bastDisahkan()))->render();

        $this->assertStringContainsString(
            'telah disahkan secara elektronik melalui Sistem Informasi Manajemen Permintaan Barang dan Inventaris',
            $tampilan,
        );
        $this->assertStringNotContainsString('sertifikat elektronik', $tampilan);
        $this->assertStringNotContainsString('BSrE', $tampilan);
    }

    /** BAST yang belum disahkan terbit tanpa kode, sebagaimana sebelumnya. */
    public function test_bast_belum_disahkan_terbit_tanpa_kode(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $bast   = $this->buatBast($asal, $this->buatTim('Statistik Sosial'), $this->buatPengguna('petugas_gudang'));

        $data = $this->dataTampilan($bast);

        $this->assertSame('', $data['qr']);
        $this->assertNull($bast->refresh()->qr_token);
    }

    public function test_dokumen_terbentuk_dan_tersimpan(): void
    {
        $bast = $this->bastDisahkan();

        $lintasan = app(DokumenBastService::class)->buat($bast);

        Storage::disk('local')->assertExists($lintasan);
        $this->assertSame('bast-mutasi/' . $bast->nomor_bast . '.pdf', $lintasan);
    }

    /** Membaca kembali alamat yang tersandi di dalam gambar kode QR. */
    private function bacaKodeQr(string $dataUri): string
    {
        return (string) (new \chillerlan\QRCode\QRCode)->readFromBlob($this->pngDari($dataUri));
    }

    /**
     * Apakah pusat gambar mengandung tinta berwarna. Kode QR seluruhnya
     * hitam-putih, sehingga satu piksel berwarna pun hanya mungkin berasal dari
     * lambang yang dibubuhkan ke dalamnya.
     */
    private function berwarnaDiPusat(string $dataUri): bool
    {
        $gambar  = imagecreatefromstring($this->pngDari($dataUri));
        $pusat   = intdiv(imagesx($gambar), 2);
        $jangkau = intdiv(imagesx($gambar), 20);

        for ($y = $pusat - $jangkau; $y <= $pusat + $jangkau; $y++) {
            for ($x = $pusat - $jangkau; $x <= $pusat + $jangkau; $x++) {
                $warna = imagecolorat($gambar, $x, $y);

                if (max(($warna >> 16) & 0xFF, ($warna >> 8) & 0xFF, $warna & 0xFF)
                    - min(($warna >> 16) & 0xFF, ($warna >> 8) & 0xFF, $warna & 0xFF) > 40) {
                    imagedestroy($gambar);

                    return true;
                }
            }
        }

        imagedestroy($gambar);

        return false;
    }

    /** Bita PNG di balik sebuah data URI. */
    private function pngDari(string $dataUri): string
    {
        return (string) base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
    }

    /**
     * Data yang diserahkan layanan kepada tampilan, tanpa perlu membongkar PDF
     * hasilnya — isi PDF terkompresi sehingga memeriksanya rapuh dan sulit
     * dibaca ketika pengujian gagal.
     *
     * @return array<string,mixed>
     */
    private function dataTampilan(BastMutasiAset $bast): array
    {
        $ditangkap = [];

        \Illuminate\Support\Facades\View::creator(
            'pdf.bast-mutasi',
            function ($view) use (&$ditangkap) {
                $ditangkap = $view->getData();
            },
        );

        app(DokumenBastService::class)->buat($bast);

        return $ditangkap;
    }
}
