<?php

namespace Tests\Feature;

use App\Filament\Resources\PermintaanBarangs\Pages\ListPermintaanBarangs;
use App\Models\PermintaanBarang;
use App\Models\User;
use App\Support\TandaTangan;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pembubuhan tanda tangan pada tahapan penyiapan dan penerimaan.
 *
 * Sejak tanda tangan didaftarkan sekali saat melengkapi akun, tak ada lagi
 * kanvas yang digambar pada tahapan. Yang tersisa adalah konfirmasi identitas:
 * pelaksana mengetik NIP-nya sendiri, lalu tanda tangan tersimpan yang
 * dibubuhkan. Aturannya berbeda di kedua tahapan, dan perbedaan itulah yang
 * paling mudah salah diterapkan. Pada penyiapan, yang menandatangani adalah
 * orang yang menekan tombol. Pada penerimaan tidak: dokumen bukti selalu terbit
 * atas nama Ketua Tim, padahal penerimaan boleh dikonfirmasi anggota timnya —
 * sehingga tahapan harus tertahan ketika Ketua Tim belum punya tanda tangan
 * tersimpan.
 *
 * Seluruh tahapan kini dijalankan lewat kaki pop-up Rincian: aksi dimuat
 * sebagai anak dari aksi 'detail', bukan sebagai tombol baris tersendiri.
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

    /** Aksi tahapan sebagai anak dari pop-up Rincian permintaan. */
    private function aksiTahap(string $nama, PermintaanBarang $permintaan): array
    {
        return [
            TestAction::make('detail')->table($permintaan),
            TestAction::make($nama),
        ];
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

    public function test_penyiapan_memakai_tanda_tangan_tersimpan_dan_konfirmasi_nip(): void
    {
        $permintaan = $this->permintaanSiapDiproses();
        $gudang     = $this->buatPengguna('petugas_gudang');
        TandaTangan::simpan($gudang, $this->pngSah());
        $this->actingAs($gudang->refresh());

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('siapkan', $permintaan), data: [
                'konfirmasi_nip' => $gudang->nip,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('siap_diambil', $permintaan->refresh()->status);
    }

    public function test_penyiapan_menolak_nip_yang_tidak_cocok(): void
    {
        $permintaan = $this->permintaanSiapDiproses();
        $gudang     = $this->buatPengguna('petugas_gudang');
        TandaTangan::simpan($gudang, $this->pngSah());
        $this->actingAs($gudang->refresh());

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('siapkan', $permintaan), data: [
                'konfirmasi_nip' => '000000',
            ])
            ->assertHasActionErrors(['konfirmasi_nip']);

        $this->assertSame('siap_diproses', $permintaan->refresh()->status);
    }

    public function test_penyiapan_tertahan_bila_petugas_belum_bertanda_tangan(): void
    {
        $permintaan = $this->permintaanSiapDiproses();
        $gudang     = $this->buatPengguna('petugas_gudang');
        $this->actingAs($gudang);

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('siapkan', $permintaan), data: [
                'konfirmasi_nip' => $gudang->nip,
            ]);

        $this->assertSame(
            'siap_diproses',
            $permintaan->refresh()->status,
            'Tahapan tidak boleh berlanjut tanpa tanda tangan tersimpan.',
        );
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
        TandaTangan::simpan($ketua, $this->pngSah());
        $this->actingAs($ketua->refresh());

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('konfirmasi', $permintaan), data: [
                'sesuai'         => 'ya',
                'konfirmasi_nip' => $ketua->nip,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('menunggu_pengesahan', $permintaan->refresh()->status);
    }

    public function test_anggota_tim_boleh_mengkonfirmasi_bila_ketua_sudah_bertanda_tangan(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);
        TandaTangan::simpan($ketua, $this->pngSah());

        $anggota = $this->buatPengguna('tim', $permintaan->tim);
        $this->actingAs($anggota);

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('konfirmasi', $permintaan), data: [
                'sesuai'         => 'ya',
                'konfirmasi_nip' => $permintaan->tim->nama_tim,
            ])
            ->assertHasNoActionErrors();

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

        $anggota = $this->buatPengguna('tim', $permintaan->tim);
        $this->actingAs($anggota);

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('konfirmasi', $permintaan), data: [
                'sesuai'         => 'ya',
                'konfirmasi_nip' => $permintaan->tim->nama_tim,
            ]);

        $this->assertSame(
            'siap_diambil',
            $permintaan->refresh()->status,
            'Penerimaan tidak boleh tuntas selama Ketua Tim belum punya tanda tangan.',
        );
    }

    /**
     * Penerimaan yang berakhir bermasalah tidak menerbitkan dokumen bukti,
     * sehingga tidak menuntut tanda tangan — tetapi konfirmasi identitasnya
     * tetap diminta agar aksi tidak terpicu tanpa sengaja.
     */
    public function test_ketidaksesuaian_permanen_tetap_dapat_dicatat_tanpa_tanda_tangan(): void
    {
        $permintaan = $this->permintaanSiapDiambil($ketua);
        $anggota    = $this->buatPengguna('tim', $permintaan->tim);
        $this->actingAs($anggota);

        Livewire::test(ListPermintaanBarangs::class)
            ->callAction($this->aksiTahap('konfirmasi', $permintaan), data: [
                'sesuai'         => 'tidak',
                'deskripsi'      => 'Barang tidak sesuai spesifikasi dan tidak tersedia penggantinya.',
                'dapat_diatasi'  => '0',
                'konfirmasi_nip' => $permintaan->tim->nama_tim,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('bermasalah', $permintaan->refresh()->status);
    }
}
