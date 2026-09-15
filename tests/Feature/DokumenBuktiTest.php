<?php

namespace Tests\Feature;

use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use App\Models\User;
use App\Services\DokumenPermintaanService;
use App\Support\TandaTangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pembentukan dokumen bukti permintaan beserta tanda tangannya.
 *
 * Dokumen ini adalah keluaran yang dilihat pihak luar, sehingga yang dijaga
 * bukan sekadar "berkasnya terbentuk" melainkan siapa yang tercantum di
 * dalamnya. Satu kekeliruan yang mudah terjadi: mencantumkan orang yang
 * menekan tombol konfirmasi, padahal yang bertanggung jawab atas barang yang
 * masuk ke unit adalah Ketua Timnya.
 */
class DokumenBuktiTest extends TestCase
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
    private const TOKEN_UJI = 'ujidokumenbuktipermintaan000000000000000';

    private const AKAR_UJI = 'https://simpbi.contoh.go.id';

    private function pngSah(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        config(['app.url' => static::AKAR_UJI]);
    }

    /**
     * Permintaan yang sudah melewati seluruh tahapan, lengkap dengan riwayat
     * pelaksana yang menjadi sumber nama pada dokumen.
     *
     * @return array{0: PermintaanBarang, 1: User, 2: User, 3: User}
     */
    private function permintaanTuntas(): array
    {
        $tim   = $this->buatTim('Statistik Sosial');
        $ketua = $this->buatPengguna('ketua_tim', $tim, ['name' => 'Wanda Pribadi']);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        $gudang   = $this->buatPengguna('petugas_gudang', null, ['name' => 'Budi Santoso']);
        $kasubbag = $this->buatPengguna('kasubbag', null, ['name' => 'Siti Rahmawati']);

        $permintaan = $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim, ['name' => 'Anggota Biasa']),
            [['barang' => $this->buatBarang(), 'diminta' => 3]],
            status: 'menunggu_pengesahan',
            tambahan: ['qr_token' => static::TOKEN_UJI],
        );

        foreach ([['penyiapan', $gudang], ['pengesahan', $kasubbag]] as [$tahap, $pelaksana]) {
            RiwayatPersetujuan::create([
                'permintaan_id' => $permintaan->id,
                'tahap'         => $tahap,
                'pelaksana_id'  => $pelaksana->id,
                'keputusan'     => 'selesai',
                'catatan'       => null,
                'waktu'         => now(),
            ]);
        }

        return [$permintaan->refresh(), $gudang, $ketua, $kasubbag];
    }

    /** Membaca isi berkas PDF yang terbentuk sebagai teks mentah. */
    private function isiPdf(string $lintasan): string
    {
        return Storage::disk('public')->get($lintasan);
    }

    public function test_dokumen_terbentuk_dan_tersimpan(): void
    {
        [$permintaan] = $this->permintaanTuntas();

        $lintasan = app(DokumenPermintaanService::class)->buat($permintaan);

        Storage::disk('public')->assertExists($lintasan);
        $this->assertStringStartsWith('%PDF', $this->isiPdf($lintasan));
        $this->assertSame(
            'bukti-permintaan/' . $permintaan->kode_permintaan . '.pdf',
            $lintasan,
        );
    }

    /**
     * Yang menerima adalah Ketua Tim, bukan anggota yang mengajukan maupun
     * yang mengkonfirmasi penerimaan.
     */
    public function test_pihak_penerima_adalah_ketua_tim_bukan_pengaju(): void
    {
        [$permintaan, , $ketua] = $this->permintaanTuntas();

        $data = $this->dataTampilan($permintaan);

        $this->assertSame($ketua->name, $data['penerima']);
        $this->assertNotSame($permintaan->nama_pemohon, $data['penerima']);
    }

    public function test_tanda_tangan_tersimpan_ikut_dibubuhkan(): void
    {
        [$permintaan, $gudang, $ketua] = $this->permintaanTuntas();

        TandaTangan::simpan($gudang, $this->pngSah());
        TandaTangan::simpan($ketua, $this->pngSah());

        $data = $this->dataTampilan($permintaan->refresh());

        $this->assertStringStartsWith('data:image/png;base64,', $data['ttdPenyerah']);
        $this->assertStringStartsWith('data:image/png;base64,', $data['ttdPenerima']);
    }

    /**
     * Dokumen tetap harus terbentuk meski tanda tangan belum ada. Tahapan alur
     * memang sudah menahannya lebih dulu, tetapi dokumen yang gagal terbentuk
     * jauh lebih merugikan daripada dokumen dengan ruang tanda tangan kosong —
     * yang terakhir masih dapat ditandatangani basah.
     */
    public function test_dokumen_tetap_terbentuk_tanpa_tanda_tangan(): void
    {
        [$permintaan] = $this->permintaanTuntas();

        $data = $this->dataTampilan($permintaan);

        $this->assertNull($data['ttdPenyerah']);
        $this->assertNull($data['ttdPenerima']);

        $lintasan = app(DokumenPermintaanService::class)->buat($permintaan);
        Storage::disk('public')->assertExists($lintasan);
    }

    public function test_kode_qr_terbentuk_sebagai_tanda_tangan_elektronik_kasubbag(): void
    {
        [$permintaan] = $this->permintaanTuntas();

        $data = $this->dataTampilan($permintaan);

        $this->assertStringStartsWith('data:image/', $data['qr']);
        $this->assertNotNull($permintaan->refresh()->qr_token);
    }

    /**
     * Alamat di dalam kode QR harus berasal dari tetapan pemasangan, bukan dari
     * mesin yang kebetulan menyahkan dokumennya.
     *
     * Dokumen bukti membeku begitu terbit dan beredar ke luar sistem. Ketika
     * alamatnya diambil dari permintaan HTTP yang sedang berjalan, dokumen yang
     * disahkan lewat panel di `127.0.0.1:8000` membawa alamat itu selamanya —
     * alamat yang pada ponsel pemindainya menunjuk balik ke ponsel itu sendiri.
     */
    public function test_alamat_dalam_kode_qr_berasal_dari_app_url(): void
    {
        [$permintaan] = $this->permintaanTuntas();

        // Permintaan uji berjalan pada host bawaan Laravel, yang berbeda dari
        // akar alamat yang dipatri di setUp; bila alamat masih diambil dari
        // permintaan, host bawaan itulah yang akan muncul di dalam kodenya.
        $lembar = $this->semuaTampilan($permintaan);
        $token  = $permintaan->refresh()->qr_token;

        $alamat = $this->bacaKodeQr($lembar[0]['qr']);

        $this->assertSame(static::AKAR_UJI . "/bukti/{$token}", $alamat);
        $this->assertStringNotContainsString('localhost', $alamat);
        $this->assertStringNotContainsString('127.0.0.1', $alamat);
    }

    /**
     * Kode pada catatan kaki menuju berkas asli di kedua lembar, sesuai bunyi
     * keterangan di sampingnya.
     */
    public function test_kode_catatan_kaki_menuju_berkas_asli_pada_kedua_lembar(): void
    {
        [$permintaan] = $this->permintaanTuntas();

        $lembar = $this->semuaTampilan($permintaan);
        $token  = $permintaan->refresh()->qr_token;

        foreach ($lembar as $i => $data) {
            $this->assertSame(
                static::AKAR_UJI . "/bukti/{$token}/asli",
                $this->bacaKodeQr($data['qrFootnote']),
                "Kode catatan kaki pada lembar ke-{$i} tidak menuju berkas asli.",
            );
        }
    }

    /**
     * Lambang menyatu di dalam kode, bukan ditumpangkan di atasnya.
     *
     * Yang diperiksa adalah gambar kodenya sendiri: ada tinta berwarna di
     * pusatnya, dan kode itu tetap terbaca pemindai meski pusatnya terisi.
     * Selama lambang masih ditumpangkan sebagai elemen HTML, gambar kode tidak
     * akan pernah berwarna dan pengujian ini gagal.
     */
    public function test_lambang_menyatu_di_pusat_kode_dan_kode_tetap_terbaca(): void
    {
        [$permintaan] = $this->permintaanTuntas();

        $lembar = $this->semuaTampilan($permintaan);

        $this->assertTrue(
            $this->berwarnaDiPusat($lembar[0]['qr']),
            'Stempel Kasubbag tidak berlambang.',
        );
        $this->assertNotSame('', $this->bacaKodeQr($lembar[0]['qr']));

        // Kode catatan kaki sengaja dibiarkan polos, mengikuti dokumen acuan.
        $this->assertFalse(
            $this->berwarnaDiPusat($lembar[0]['qrFootnote']),
            'Kode catatan kaki seharusnya tanpa lambang.',
        );
    }

    /** Membaca kembali alamat yang tersandi di dalam gambar kode QR. */
    private function bacaKodeQr(string $dataUri): string
    {
        return (string) (new \chillerlan\QRCode\QRCode)->readFromBlob($this->pngDari($dataUri));
    }

    /**
     * Apakah pusat gambar mengandung tinta berwarna.
     *
     * Kode QR seluruhnya hitam-putih, sehingga satu piksel berwarna pun hanya
     * mungkin berasal dari lambang yang dibubuhkan ke dalamnya.
     */
    private function berwarnaDiPusat(string $dataUri): bool
    {
        $gambar = imagecreatefromstring($this->pngDari($dataUri));
        $pusat  = intdiv(imagesx($gambar), 2);
        $jangkau = intdiv(imagesx($gambar), 20);

        for ($y = $pusat - $jangkau; $y <= $pusat + $jangkau; $y++) {
            for ($x = $pusat - $jangkau; $x <= $pusat + $jangkau; $x++) {
                $warna = imagecolorat($gambar, $x, $y);
                $r = ($warna >> 16) & 0xFF;
                $g = ($warna >> 8) & 0xFF;
                $b = $warna & 0xFF;

                if (max($r, $g, $b) - min($r, $g, $b) > 40) {
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
     * Mengambil data yang diserahkan layanan kepada tampilan, tanpa perlu
     * membongkar PDF hasilnya — isi PDF terkompresi sehingga memeriksa teks di
     * dalamnya rapuh dan sulit dibaca ketika pengujian gagal.
     *
     * @return array<string,mixed>
     */
    private function dataTampilan(PermintaanBarang $permintaan): array
    {
        return $this->semuaTampilan($permintaan)[0];
    }

    /**
     * Data kedua lembar yang dibentuk sekali jalan: berkas asli lebih dulu,
     * lalu lembar berfootnote. Keduanya perlu diperiksa terpisah karena tujuan
     * kode QR stempelnya memang berbeda.
     *
     * @return array<int,array<string,mixed>>
     */
    private function semuaTampilan(PermintaanBarang $permintaan): array
    {
        $ditangkap = [];

        \Illuminate\Support\Facades\View::creator(
            'pdf.bukti-permintaan',
            function ($view) use (&$ditangkap) {
                $ditangkap[] = $view->getData();
            },
        );

        app(DokumenPermintaanService::class)->buat($permintaan);

        return $ditangkap;
    }
}
