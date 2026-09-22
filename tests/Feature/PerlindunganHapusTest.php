<?php

namespace Tests\Feature;

use App\Filament\Resources\AsetTetaps\Pages\ListAsetTetaps;
use App\Filament\Resources\BarangPersediaans\Pages\ListBarangPersediaans;
use App\Filament\Resources\Kategoris\Pages\ListKategoris;
use App\Filament\Resources\Tims\Pages\ListTims;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AsetTetap;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\RiwayatPenempatanAset;
use App\Models\MutasiStok;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Perlindungan data induk yang sudah punya riwayat.
 *
 * Tiga tabel induk dirujuk catatan yang tidak boleh hilang, dan masing-masing
 * punya alasannya sendiri:
 *
 * - Barang persediaan dirujuk buku besar mutasi dengan kunci asing **berantai**,
 *   sehingga menghapus barangnya ikut menghapus seluruh riwayat stoknya —
 *   padahal riwayat itulah sumber Kartu Kendali. Inilah yang paling berbahaya:
 *   penghapusannya berhasil, dan kehilangannya tidak terlihat.
 * - Pengguna dan tim dirujuk dengan kunci asing **penolak**, sehingga
 *   penghapusannya gagal — tetapi gagalnya berupa galat basis data mentah di
 *   layar, bukan keterangan yang dapat dibaca.
 * - Aset tetap dirujuk keduanya sekaligus: riwayat penempatannya berantai
 *   (jejaknya hilang diam-diam), sedangkan BAST mutasi menolak (galat mentah).
 * - Kategori dirujuk barang persediaan dan aset tetap, keduanya menolak. Tidak
 *   ada data yang terancam hilang; yang diperbaiki cara sistem mengatakannya.
 *
 * Penjaganya dipasang pada peristiwa `deleting` model, bukan pada tombol, agar
 * jalur mana pun ikut terjaga. Pengujian di sini sengaja menempuh dua jalur:
 * langsung lewat model, dan lewat aksi massal Filament yang sebenarnya.
 */
class PerlindunganHapusTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    // =====================================================================
    // BARANG PERSEDIAAN
    // =====================================================================

    public function test_barang_dengan_riwayat_mutasi_tidak_dapat_dihapus(): void
    {
        $barang = $this->barangBermutasi();

        $this->assertTrue($barang->punyaRiwayat());
        $this->assertFalse($barang->delete(), 'Penghapusan harus ditolak, bukan dilaksanakan.');

        $this->assertDatabaseHas('barang_persediaan', ['id' => $barang->id]);
    }

    /**
     * Yang paling penting: buku besar tidak boleh ikut hilang.
     *
     * Kunci asingnya berantai, jadi seandainya penghapusan lolos, seluruh baris
     * mutasi barang itu lenyap tanpa satu pun galat.
     */
    public function test_buku_besar_tidak_ikut_terhapus_ketika_penghapusan_ditolak(): void
    {
        $barang = $this->barangBermutasi();
        $jumlah = MutasiStok::where('barang_id', $barang->id)->count();

        $this->assertGreaterThan(0, $jumlah);

        $barang->delete();

        $this->assertSame(
            $jumlah,
            MutasiStok::where('barang_id', $barang->id)->count(),
            'Buku besar mutasi harus utuh setelah penghapusan ditolak.',
        );
    }

    public function test_barang_tanpa_riwayat_mutasi_tetap_dapat_dihapus(): void
    {
        $barang = $this->buatBarang(stokFisik: 0);

        $this->assertFalse($barang->punyaRiwayat());
        $this->assertTrue($barang->delete());

        $this->assertDatabaseMissing('barang_persediaan', ['id' => $barang->id]);
    }

    /**
     * Aksi massal tidak boleh menjadi jalan pintas.
     *
     * Filament menghapus data terpilih satu per satu ketika `fetchSelectedRecords`
     * menyala, sehingga peristiwa model ikut berjalan. Bila suatu saat aksi itu
     * disetel mengambil jalan kueri massal, peristiwa modelnya terlewati tanpa
     * suara — pengujian inilah yang akan menangkapnya.
     */
    public function test_hapus_massal_melewati_barang_yang_punya_riwayat(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $berriwayat = $this->barangBermutasi();
        $bersih     = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000999']);
        $mutasi     = MutasiStok::count();

        Livewire::test(ListBarangPersediaans::class)
            ->callTableBulkAction('delete', [$berriwayat, $bersih]);

        $this->assertDatabaseHas('barang_persediaan', ['id' => $berriwayat->id]);
        $this->assertDatabaseMissing('barang_persediaan', ['id' => $bersih->id]);
        $this->assertSame($mutasi, MutasiStok::count(), 'Tidak satu baris buku besar pun boleh hilang.');
    }

    // =====================================================================
    // PENGGUNA
    // =====================================================================

    public function test_pengguna_yang_pernah_bertindak_tidak_dapat_dihapus(): void
    {
        $petugas = $this->buatPengguna('petugas_gudang');

        app(StokService::class)->tambah(
            barangId: $this->buatBarang(stokFisik: 0)->id,
            jumlah: 5,
            sumber: 'pembelian',
            nomorDasar: 'UJI-001',
            keterangan: null,
            petugasId: $petugas->id,
        );

        $this->assertTrue($petugas->punyaRiwayat());
        $this->assertFalse($petugas->delete());

        $this->assertDatabaseHas('users', ['id' => $petugas->id]);
    }

    public function test_pengguna_tanpa_riwayat_tetap_dapat_dihapus(): void
    {
        $pengguna = $this->buatPengguna('kasubbag');

        $this->assertFalse($pengguna->punyaRiwayat());
        $this->assertTrue($pengguna->delete());

        $this->assertDatabaseMissing('users', ['id' => $pengguna->id]);
    }

    public function test_hapus_massal_melewati_pengguna_yang_punya_riwayat(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $tim        = $this->buatTim();
        $berriwayat = $this->buatPengguna('tim', $tim);
        $bersih     = $this->buatPengguna('admin');

        $this->buatPermintaan($tim, $berriwayat, [['barang' => $this->buatBarang(), 'diminta' => 1]]);

        Livewire::test(ListUsers::class)
            ->callTableBulkAction('delete', [$berriwayat, $bersih]);

        $this->assertDatabaseHas('users', ['id' => $berriwayat->id]);
        $this->assertDatabaseMissing('users', ['id' => $bersih->id]);
    }

    // =====================================================================
    // TIM KERJA
    // =====================================================================

    public function test_tim_yang_pernah_mengajukan_permintaan_tidak_dapat_dihapus(): void
    {
        $tim = $this->buatTim('Statistik Sosial');

        $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(), 'diminta' => 2]],
        );

        $this->assertTrue($tim->punyaRiwayat());
        $this->assertFalse($tim->delete());

        $this->assertDatabaseHas('tim', ['id' => $tim->id]);
    }

    public function test_tim_tanpa_riwayat_tetap_dapat_dihapus(): void
    {
        $tim = $this->buatTim('Tim Belum Terpakai');

        $this->assertFalse($tim->punyaRiwayat());
        $this->assertTrue($tim->delete());

        $this->assertDatabaseMissing('tim', ['id' => $tim->id]);
    }

    public function test_hapus_massal_melewati_tim_yang_punya_riwayat(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $berriwayat = $this->buatTim('Statistik Sosial');
        $bersih     = $this->buatTim('Tim Belum Terpakai');

        $this->buatPermintaan(
            $berriwayat,
            $this->buatPengguna('tim', $berriwayat),
            [['barang' => $this->buatBarang(), 'diminta' => 1]],
        );

        Livewire::test(ListTims::class)
            ->callTableBulkAction('delete', [$berriwayat, $bersih]);

        $this->assertDatabaseHas('tim', ['id' => $berriwayat->id]);
        $this->assertDatabaseMissing('tim', ['id' => $bersih->id]);
    }

    // =====================================================================
    // ASET TETAP
    // =====================================================================

    public function test_aset_dengan_riwayat_penempatan_tidak_dapat_dihapus(): void
    {
        $aset = $this->asetBerriwayat();

        $this->assertTrue($aset->punyaRiwayat());
        $this->assertFalse($aset->delete(), 'Penghapusan harus ditolak, bukan dilaksanakan.');

        $this->assertDatabaseHas('aset_tetap', ['id' => $aset->id]);
    }

    /**
     * Yang paling penting: jejak penempatan tidak boleh ikut hilang.
     *
     * Kunci asing `riwayat_penempatan_aset.aset_id` berantai, jadi seandainya
     * penghapusan lolos, seluruh baris riwayat aset itu lenyap tanpa satu pun
     * galat — beserta keterangan ke tim mana aset pernah ditempatkan.
     */
    public function test_riwayat_penempatan_tidak_ikut_terhapus_ketika_penghapusan_ditolak(): void
    {
        $aset   = $this->asetBerriwayat();
        $jumlah = RiwayatPenempatanAset::where('aset_id', $aset->id)->count();

        $this->assertGreaterThan(0, $jumlah);

        $aset->delete();

        $this->assertSame(
            $jumlah,
            RiwayatPenempatanAset::where('aset_id', $aset->id)->count(),
            'Riwayat penempatan harus utuh setelah penghapusan ditolak.',
        );
    }

    public function test_aset_tanpa_riwayat_tetap_dapat_dihapus(): void
    {
        $aset = $this->buatAset();

        $this->assertFalse($aset->punyaRiwayat());
        $this->assertTrue($aset->delete());

        $this->assertDatabaseMissing('aset_tetap', ['id' => $aset->id]);
    }

    public function test_hapus_massal_melewati_aset_yang_punya_riwayat(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $berriwayat = $this->asetBerriwayat();
        $bersih     = $this->buatAset();
        $riwayat    = RiwayatPenempatanAset::count();

        Livewire::test(ListAsetTetaps::class)
            ->callTableBulkAction('delete', [$berriwayat, $bersih]);

        $this->assertDatabaseHas('aset_tetap', ['id' => $berriwayat->id]);
        $this->assertDatabaseMissing('aset_tetap', ['id' => $bersih->id]);
        $this->assertSame(
            $riwayat,
            RiwayatPenempatanAset::count(),
            'Tidak satu baris riwayat penempatan pun boleh hilang.',
        );
    }

    /**
     * Aset yang tercantum pada BAST pun tertahan.
     *
     * Kunci asingnya menolak, bukan berantai, sehingga tanpa penjaga ini
     * penggunanya melihat galat basis data mentah alih-alih keterangan.
     */
    public function test_aset_yang_tercantum_pada_bast_juga_tidak_dapat_dihapus(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Sosial');
        $bast   = $this->buatBast($asal, $tujuan, $this->buatPengguna('petugas_gudang'));

        $aset = AsetTetap::findOrFail($bast->aset_id);

        $this->assertTrue($aset->punyaRiwayat());
        $this->assertFalse($aset->delete());

        $this->assertDatabaseHas('aset_tetap', ['id' => $aset->id]);
        $this->assertDatabaseHas('bast_mutasi_aset', ['id' => $bast->id]);
    }

    /** Satu aset beserta satu baris riwayat penempatannya. */
    private function asetBerriwayat(): AsetTetap
    {
        $tim  = $this->buatTim();
        $aset = $this->buatAset($tim);

        RiwayatPenempatanAset::create([
            'aset_id'       => $aset->id,
            'tim_id'        => $tim->id,
            'tanggal_mulai' => now()->toDateString(),
            'jenis'         => 'penempatan_awal',
        ]);

        return $aset->refresh();
    }

    // =====================================================================
    // KATEGORI
    // =====================================================================

    public function test_kategori_yang_dipakai_barang_tidak_dapat_dihapus(): void
    {
        $barang   = $this->buatBarang(stokFisik: 0);
        $kategori = Kategori::findOrFail($barang->kategori_id);

        $this->assertTrue($kategori->punyaRiwayat());
        $this->assertFalse($kategori->delete(), 'Penghapusan harus ditolak, bukan dilaksanakan.');

        $this->assertDatabaseHas('kategori', ['id' => $kategori->id]);
        $this->assertDatabaseHas('barang_persediaan', ['id' => $barang->id]);
    }

    /**
     * Penolakannya tidak boleh berupa galat basis data mentah.
     *
     * Sebelum perlindungan ini, kunci asing penolak pada basis data yang
     * melakukannya — dan penggunanya melihat `QueryException` di layar alih-alih
     * keterangan. Kini penghapusannya ditolak lebih dulu, sebelum sampai ke
     * basis data.
     */
    public function test_penolakan_kategori_bukan_berupa_galat_basis_data(): void
    {
        $kategori = Kategori::findOrFail($this->buatBarang(stokFisik: 0)->kategori_id);

        try {
            $hasil = $kategori->delete();
        } catch (\Throwable $e) {
            $this->fail('Penghapusan melempar ' . $e::class . ' alih-alih ditolak dengan tenang.');
        }

        $this->assertFalse($hasil);
    }

    /** Kategori aset tetap ikut terlindungi, sebab kunci asingnya juga menolak. */
    public function test_kategori_yang_dipakai_aset_tetap_juga_tidak_dapat_dihapus(): void
    {
        $aset     = $this->buatAset();
        $kategori = Kategori::findOrFail($aset->kategori_id);

        $this->assertTrue($kategori->punyaRiwayat());
        $this->assertFalse($kategori->delete());

        $this->assertDatabaseHas('kategori', ['id' => $kategori->id]);
        $this->assertDatabaseHas('aset_tetap', ['id' => $aset->id]);
    }

    public function test_kategori_yang_belum_dipakai_tetap_dapat_dihapus(): void
    {
        $kategori = Kategori::create([
            'kode_akun'     => '9.9.9',
            'kode_kategori' => 'UJI9',
            'nama_kategori' => 'Kategori Belum Terpakai',
            'tipe'          => 'persediaan',
        ]);

        $this->assertFalse($kategori->punyaRiwayat());
        $this->assertTrue($kategori->delete());

        $this->assertDatabaseMissing('kategori', ['id' => $kategori->id]);
    }

    public function test_hapus_massal_melewati_kategori_yang_masih_dipakai(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $barang  = $this->buatBarang(stokFisik: 0);
        $dipakai = Kategori::findOrFail($barang->kategori_id);
        $bersih  = Kategori::create([
            'kode_akun'     => '9.9.9',
            'kode_kategori' => 'UJI9',
            'nama_kategori' => 'Kategori Belum Terpakai',
            'tipe'          => 'persediaan',
        ]);

        Livewire::test(ListKategoris::class)
            ->callTableBulkAction('delete', [$dipakai, $bersih]);

        $this->assertDatabaseHas('kategori', ['id' => $dipakai->id]);
        $this->assertDatabaseMissing('kategori', ['id' => $bersih->id]);
        $this->assertDatabaseHas('barang_persediaan', ['id' => $barang->id]);
    }

    /** Satu barang beserta satu baris buku besarnya. */
    private function barangBermutasi(): BarangPersediaan
    {
        $barang = $this->buatBarang(stokFisik: 0);

        app(StokService::class)->tambah(
            barangId: $barang->id,
            jumlah: 10,
            sumber: 'pembelian',
            nomorDasar: 'UJI-001',
            keterangan: null,
            petugasId: $this->buatPengguna('petugas_gudang')->id,
        );

        return $barang->refresh();
    }
}
