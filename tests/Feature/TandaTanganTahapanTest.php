<?php

namespace Tests\Feature;

use App\Filament\Resources\PermintaanBarangs\Pages\ListPermintaanBarangs;
use App\Models\PermintaanBarang;
use App\Models\User;
use App\Support\TandaTangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pembubuhan tanda tangan pada tahapan penyiapan dan penerimaan.
 *
 * Aturannya berbeda di kedua tahapan, dan perbedaan itulah yang paling mudah
 * salah diterapkan. Pada penyiapan, yang menandatangani adalah orang yang
 * menekan tombol. Pada penerimaan tidak: dokumen bukti selalu terbit atas nama
 * Ketua Tim, padahal penerimaan boleh dikonfirmasi anggota timnya. Anggota tim
 * karena itu tidak pernah boleh menggambarkan tanda tangan atasannya, dan
 * tahapan justru harus tertahan ketika Ketua Tim belum punya tanda tangan
 * tersimpan.
 */
class TandaTanganTahapanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function pngSah(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** Permintaan yang menunggu penyiapan oleh Petugas Gudang. */
    private function permintaanSiapDiproses(): PermintaanBarang
    {
        $tim = $this->buatTim();

        return $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'siap_diproses',
        );
    }

    // =====================================================================
    // TAHAP PENYIAPAN — YANG MENEKAN TOMBOL YANG MENANDATANGANI
    // =====================================================================

    public function test_penyiapan_tertahan_bila_petugas_belum_bertanda_tangan(): void
    {
        $permintaan = $this->permintaanSiapDiproses();
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('siapkan', $permintaan)
            ->setTableActionData(['tanda_tangan' => null])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['tanda_tangan']);

        $this->assertSame('siap_diproses', $permintaan->refresh()->status);
    }

    public function test_goresan_baru_tersimpan_dan_tahapan_berlanjut(): void
    {
        $permintaan = $this->permintaanSiapDiproses();
        $gudang     = $this->buatPengguna('petugas_gudang');
        $this->actingAs($gudang);

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('siapkan', $permintaan)
            ->setTableActionData(['tanda_tangan' => $this->pngSah()])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('siap_diambil', $permintaan->refresh()->status);
        $this->assertTrue(TandaTangan::tersedia($gudang->refresh()));
    }

    /**
     * Petugas yang sudah menyimpan tanda tangan tidak diminta menggores lagi.
     * Inilah inti keputusan "digambar sekali lalu diingat": seorang Petugas
     * Gudang dapat menyiapkan puluhan permintaan dalam sehari.
     */
    public function test_petugas_yang_sudah_bertanda_tangan_tidak_perlu_menggores_lagi(): void
    {
        $gudang = $this->buatPengguna('petugas_gudang');
        TandaTangan::simpan($gudang, $this->pngSah());

        $permintaan = $this->permintaanSiapDiproses();
        $this->actingAs($gudang->refresh());

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('siapkan', $permintaan)
            ->setTableActionData(['tanda_tangan' => null])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('siap_diambil', $permintaan->refresh()->status);
    }

    // =====================================================================
    // TAHAP PENERIMAAN — DOKUMEN SELALU ATAS NAMA KETUA TIM
    // =====================================================================

    /** Permintaan siap diambil, beserta tim yang sudah punya Ketua Tim. */
    private function permintaanSiapDiambil(?User &$ketua = null): PermintaanBarang
    {
        $tim   = $this->buatTim();
        $ketua = $this->buatPengguna('ketua_tim', $tim);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        return $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'siap_diambil',
        );
    }

    public function test_ketua_tim_menandatangani_sendiri_saat_mengkonfirmasi(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);
        $this->actingAs($ketua);

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('konfirmasi', $permintaan)
            ->setTableActionData(['sesuai' => 'ya', 'tanda_tangan' => $this->pngSah()])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('menunggu_pengesahan', $permintaan->refresh()->status);
        $this->assertTrue(TandaTangan::tersedia($ketua->refresh()));
    }

    public function test_anggota_tim_boleh_mengkonfirmasi_bila_ketua_sudah_bertanda_tangan(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);
        TandaTangan::simpan($ketua, $this->pngSah());

        $this->actingAs($this->buatPengguna('tim', $permintaan->tim));

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('konfirmasi', $permintaan)
            ->setTableActionData(['sesuai' => 'ya'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('menunggu_pengesahan', $permintaan->refresh()->status);
    }

    /**
     * Keadaan yang paling menentukan: tahapan harus berhenti, bukan berlanjut
     * dengan kotak tanda tangan kosong pada dokumen.
     */
    public function test_anggota_tim_tertahan_bila_ketua_belum_bertanda_tangan(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);
        $this->assertFalse(TandaTangan::tersedia($ketua));

        $this->actingAs($this->buatPengguna('tim', $permintaan->tim));

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('konfirmasi', $permintaan)
            ->setTableActionData(['sesuai' => 'ya'])
            ->callMountedTableAction();

        $this->assertSame(
            'siap_diambil',
            $permintaan->refresh()->status,
            'Penerimaan tidak boleh tuntas selama Ketua Tim belum punya tanda tangan.',
        );
    }

    /**
     * Anggota tim tidak boleh menggambarkan tanda tangan Ketua Timnya. Kiriman
     * semacam itu — yang hanya mungkin datang dari luar antarmuka — tidak boleh
     * berakhir tersimpan pada akun Ketua Tim.
     */
    public function test_goresan_dari_anggota_tim_tidak_disimpan_atas_nama_ketua(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);

        $this->actingAs($this->buatPengguna('tim', $permintaan->tim));

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('konfirmasi', $permintaan)
            ->setTableActionData(['sesuai' => 'ya', 'tanda_tangan' => $this->pngSah()])
            ->callMountedTableAction();

        $this->assertFalse(
            TandaTangan::tersedia($ketua->refresh()),
            'Tanda tangan Ketua Tim hanya boleh lahir dari goresan Ketua Tim sendiri.',
        );
        $this->assertSame('siap_diambil', $permintaan->refresh()->status);
    }

    /**
     * Penerimaan yang berakhir bermasalah tidak menerbitkan dokumen bukti,
     * sehingga menahannya karena tanda tangan hanya akan mengurung permintaan
     * yang justru perlu segera dihentikan.
     */
    public function test_ketidaksesuaian_permanen_tetap_dapat_dicatat_tanpa_tanda_tangan(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);
        $this->actingAs($this->buatPengguna('tim', $permintaan->tim));

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('konfirmasi', $permintaan)
            ->setTableActionData([
                'sesuai'        => 'tidak',
                'deskripsi'     => 'Barang tidak sesuai spesifikasi dan tidak tersedia penggantinya.',
                'dapat_diatasi' => '0',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('bermasalah', $permintaan->refresh()->status);
    }

    // =====================================================================
    // KIRIMAN KANVAS YANG TIDAK TERBACA
    // =====================================================================

    /**
     * Kanvas berukuran nol mengembalikan "data:," dari toDataURL(), bukan PNG.
     * Sebelumnya kiriman itu melempar InvalidArgumentException sampai ke
     * permukaan dan pengguna melihat halaman galat 500 di tengah penyiapan
     * barang. Yang dibutuhkannya adalah keterangan apa yang harus dilakukan.
     */
    public function test_kiriman_kanvas_kosong_tidak_menjatuhkan_halaman(): void
    {
        $permintaan = $this->permintaanSiapDiproses();
        $gudang     = $this->buatPengguna('petugas_gudang');
        $this->actingAs($gudang);

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('siapkan', $permintaan)
            ->setTableActionData(['tanda_tangan' => 'data:,'])
            ->callMountedTableAction();

        $this->assertSame(
            'siap_diproses',
            $permintaan->refresh()->status,
            'Tahapan tidak boleh berlanjut dengan tanda tangan yang tidak terbaca.',
        );
        $this->assertFalse(TandaTangan::tersedia($gudang->refresh()));
    }

    /** Berlaku juga pada konfirmasi penerimaan oleh Ketua Tim. */
    public function test_kiriman_kanvas_kosong_pada_penerimaan_juga_ditahan(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);
        $this->actingAs($ketua);

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('konfirmasi', $permintaan)
            ->setTableActionData(['sesuai' => 'ya', 'tanda_tangan' => 'data:,'])
            ->callMountedTableAction();

        $this->assertSame('siap_diambil', $permintaan->refresh()->status);
        $this->assertFalse(TandaTangan::tersedia($ketua->refresh()));
    }
}
