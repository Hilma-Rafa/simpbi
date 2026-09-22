<?php

namespace Tests\Feature;

use App\Filament\Resources\BarangPersediaans\Pages\EditBarangPersediaan;
use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use App\Services\StokService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Persetujuan sebelum stok fisik diubah (temuan audit T-57).
 *
 * Keputusan rancangannya: SIMPBI tidak memiliki fungsi stok opname maupun
 * koreksi stok tersendiri, sebab rancangan sistem tidak mensyaratkannya.
 * Penyesuaian stok dilakukan lewat Ubah Barang Persediaan oleh pengelola data
 * induk, dan penyesuaian itu **bukan** transaksi mutasi stok.
 *
 * Justru karena bukan transaksi, ia tidak meninggalkan jejak pada Kartu
 * Kendali — dan itulah yang membuat persetujuan di muka diperlukan. Yang
 * dijaga di sini ada dua: penjaganya tidak boleh dapat dilewati, dan ia tidak
 * boleh diam-diam menerbitkan baris mutasi demi terlihat rapi.
 */
class PenyesuaianStokFisikTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs($this->buatPengguna('kasubbag'));
    }

    /** Isian formulir yang sah, dengan stok fisik yang dapat diatur. */
    private function isian(BarangPersediaan $barang, array $tambahan = []): array
    {
        return [
            'kategori_id'  => $barang->kategori_id,
            'kode_barang'  => $barang->kode_barang,
            'nama_barang'  => $barang->nama_barang,
            'satuan'       => $barang->satuan,
            'stok_fisik'   => $barang->stok_fisik,
            'stok_hold'    => $barang->stok_hold,
            'stok_minimum' => $barang->stok_minimum,
            'status_aktif' => $barang->status_aktif,
            ...$tambahan,
        ];
    }

    private function halaman(BarangPersediaan $barang)
    {
        return Livewire::test(EditBarangPersediaan::class, ['record' => $barang->getKey()]);
    }

    // =====================================================================
    // KAPAN PERSETUJUAN DIMINTA
    // =====================================================================

    /** Menyunting kolom lain tidak memunculkan dialog apa pun. */
    public function test_menyunting_tanpa_mengubah_stok_tidak_meminta_persetujuan(): void
    {
        $barang = $this->buatBarang(stokFisik: 40);

        $this->halaman($barang)
            ->fillForm($this->isian($barang, ['nama_barang' => 'Nama Yang Sudah Diubah']))
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionNotMounted('konfirmasiStok');

        $barang->refresh();
        $this->assertSame('Nama Yang Sudah Diubah', $barang->nama_barang);
        $this->assertSame(40, $barang->stok_fisik);
    }

    public function test_mengubah_stok_memunculkan_persetujuan(): void
    {
        $barang = $this->buatBarang(stokFisik: 40);

        $this->halaman($barang)
            ->fillForm($this->isian($barang, ['stok_fisik' => 55]))
            ->call('save')
            ->assertActionMounted('konfirmasiStok');
    }

    /** Nilai lama dan nilai baru benar-benar terbaca pada dialognya. */
    public function test_dialog_menyebut_stok_lama_dan_stok_baru(): void
    {
        $barang = $this->buatBarang(stokFisik: 40);

        /*
         * Isi dialog dibaca dari aksinya sendiri, bukan dari HTML halaman:
         * Filament merakit dialog di sisi peramban, sehingga isinya tidak
         * pernah muncul pada keluaran uji Livewire.
         */
        $halaman = $this->halaman($barang)
            ->fillForm($this->isian($barang, ['stok_fisik' => 55]))
            ->call('save')
            ->assertActionMounted('konfirmasiStok');

        $aksi      = $halaman->instance()->getMountedAction();
        $keterangan = (string) $aksi->getModalDescription();

        $this->assertSame('Perubahan Stok Fisik', (string) $aksi->getModalHeading());
        $this->assertStringContainsString('40 ' . $barang->satuan, $keterangan);
        $this->assertStringContainsString('55 ' . $barang->satuan, $keterangan);
        $this->assertStringContainsString($barang->nama_barang, $keterangan);
        // Kata-katanya tidak boleh menjanjikan pencatatan transaksi.
        $this->assertStringContainsString('bukan transaksi mutasi stok', $keterangan);
        $this->assertStringNotContainsString('koreksi akan dicatat', $keterangan);
    }

    // =====================================================================
    // MEMBATALKAN DAN MELANJUTKAN
    // =====================================================================

    /** Selama dialognya belum disetujui, tidak satu kolom pun tersimpan. */
    public function test_membatalkan_tidak_menyimpan_perubahan(): void
    {
        $barang = $this->buatBarang(stokFisik: 40);

        $this->halaman($barang)
            ->fillForm($this->isian($barang, [
                'stok_fisik'  => 55,
                'nama_barang' => 'Nama Yang Tidak Boleh Tersimpan',
            ]))
            ->call('save')
            ->assertActionMounted('konfirmasiStok')
            ->unmountAction();

        $barang->refresh();
        $this->assertSame(40, $barang->stok_fisik, 'Stok tidak boleh berubah tanpa persetujuan.');
        $this->assertNotSame('Nama Yang Tidak Boleh Tersimpan', $barang->nama_barang);
    }

    public function test_melanjutkan_menyimpan_stok_baru(): void
    {
        $barang = $this->buatBarang(stokFisik: 40);

        $this->halaman($barang)
            ->fillForm($this->isian($barang, ['stok_fisik' => 55]))
            ->call('save')
            ->assertActionMounted('konfirmasiStok')
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(55, $barang->refresh()->stok_fisik);
    }

    /** Penyuntingan kedua pada halaman yang sama tetap ditanyakan. */
    public function test_perubahan_berikutnya_tetap_meminta_persetujuan(): void
    {
        $barang = $this->buatBarang(stokFisik: 40);

        $halaman = $this->halaman($barang)
            ->fillForm($this->isian($barang, ['stok_fisik' => 55]))
            ->call('save')
            ->callMountedAction();

        $this->assertSame(55, $barang->refresh()->stok_fisik);

        $halaman
            ->fillForm($this->isian($barang, ['stok_fisik' => 70]))
            ->call('save')
            ->assertActionMounted('konfirmasiStok');

        $this->assertSame(55, $barang->refresh()->stok_fisik, 'Belum disetujui, belum tersimpan.');
    }

    // =====================================================================
    // BUKU BESAR TIDAK DISENTUH
    // =====================================================================

    /**
     * Penyesuaian data induk tidak boleh menerbitkan baris mutasi.
     *
     * Menerbitkannya akan membuat Kartu Kendali memuat transaksi yang tidak
     * pernah terjadi di gudang — persis yang dilarang keputusan T-57.
     */
    public function test_penyesuaian_tidak_menerbitkan_mutasi_stok(): void
    {
        $barang = $this->buatBarang(stokFisik: 0);
        $gudang = $this->buatPengguna('petugas_gudang');

        app(StokService::class)->tambah(
            $barang->id, 40, 'pembelian', 'NOTA-1', null, $gudang->id, now()->subDays(3)->toDateString(),
        );

        $sebelum = MutasiStok::where('barang_id', $barang->id)->count();
        $saldoSebelum = MutasiStok::where('barang_id', $barang->id)->pluck('saldo_sesudah')->all();

        $this->halaman($barang->refresh())
            ->fillForm($this->isian($barang, ['stok_fisik' => 55]))
            ->call('save')
            ->callMountedAction();

        $this->assertSame(55, $barang->refresh()->stok_fisik);
        $this->assertSame(
            $sebelum,
            MutasiStok::where('barang_id', $barang->id)->count(),
            'Tidak boleh ada baris mutasi baru.',
        );
        $this->assertSame(
            $saldoSebelum,
            MutasiStok::where('barang_id', $barang->id)->pluck('saldo_sesudah')->all(),
            'Saldo pada baris mutasi lama harus tetap utuh.',
        );
    }

    // =====================================================================
    // TAMPILAN YANG BERGANTUNG PADA STOK FISIK
    // =====================================================================

    /**
     * Angka yang dibaca fitur lain ikut mutakhir seketika.
     *
     * Seluruh pembacanya menghitung dari kolom itu langsung — stok tersedia,
     * penanda stok menipis, dan ringkasan dasbor — sehingga tidak ada nilai
     * yang tersimpan terpisah dan dapat tertinggal basi.
     */
    public function test_pembaca_stok_membaca_nilai_terbaru(): void
    {
        $barang = $this->buatBarang(stokFisik: 40, stokHold: 5);
        $barang->forceFill(['stok_minimum' => 50])->save();

        $this->assertSame(35, $barang->stok_tersedia);
        $this->assertTrue($barang->isMenipis());

        $this->halaman($barang)
            ->fillForm($this->isian($barang, ['stok_fisik' => 200]))
            ->call('save')
            ->callMountedAction();

        $segar = BarangPersediaan::findOrFail($barang->id);

        $this->assertSame(200, $segar->stok_fisik);
        $this->assertSame(195, $segar->stok_tersedia);
        $this->assertFalse($segar->isMenipis());
    }

    // =====================================================================
    // HAK AKSES TIDAK BERUBAH
    // =====================================================================

    /** Petugas Gudang tetap tidak dapat membuka halaman ini. */
    public function test_hak_akses_tidak_berubah(): void
    {
        $barang = $this->buatBarang();

        foreach (['petugas_gudang', 'ketua_tim', 'tim'] as $peran) {
            $this->flushSession();
            $this->actingAs($this->lengkapiAkun($this->buatPengguna($peran, $this->buatTim())));

            $this->get(EditBarangPersediaan::getUrl(['record' => $barang]))->assertForbidden();
        }

        foreach (['admin', 'kasubbag'] as $peran) {
            $this->flushSession();
            $this->actingAs($this->lengkapiAkun($this->buatPengguna($peran)));

            $this->get(EditBarangPersediaan::getUrl(['record' => $barang]))->assertOk();
        }
    }
}
