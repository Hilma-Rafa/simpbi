<?php

namespace Tests\Feature;

use App\Filament\Widgets\PerluTindakan;
use App\Filament\Widgets\PolaPermintaan;
use App\Filament\Widgets\StatusPermintaanTim;
use App\Filament\Widgets\TrenKonsumsiTim;
use App\Models\BarangPersediaan;
use App\Models\BastMutasiAset;
use App\Models\Kategori;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use App\Services\NotifikasiService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Kasus uji basis path Increment 4 (Modul Dashboard & Monitoring:
 * NotifikasiService dan widget dasbor).
 *
 * Hanya jalur independen yang belum dieksekusi tes lain; nomor jalurnya
 * mengacu ke docs/pengujian/wb-increment-4.md.
 */
class JalurBasisModul4Test extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function layanan(): NotifikasiService
    {
        return app(NotifikasiService::class);
    }

    // =====================================================================
    // 4.1 kirim, 4.4 permintaanBerubah
    // =====================================================================

    /** 4.1 J1: penerima yang hanya berisi null tersaring habis → tidak ada baris. */
    public function test_kirim_tanpa_penerima_tidak_menerbitkan_apa_pun(): void
    {
        $this->assertSame(0, $this->layanan()->kirim([null], 'Judul', 'Isi'));
        $this->assertSame(0, Notifikasi::count());
    }

    /** 4.4 J10: penolakan tanpa catatan tetap memberi tahu pemohon, tanpa frasa alasan. */
    public function test_permintaan_ditolak_tanpa_catatan_tanpa_alasan(): void
    {
        $tim     = $this->buatTim();
        $anggota = $this->buatPengguna('tim', $tim);
        $ketua   = $this->buatPengguna('ketua_tim', $tim);
        $permintaan = $this->buatPermintaan($tim, $anggota, [['barang' => $this->buatBarang(), 'diminta' => 1]], 'ditolak_ketua');

        $this->assertSame(2, $this->layanan()->permintaanBerubah($permintaan));

        $pesan = Notifikasi::where('user_id', $anggota->id)->where('channel', 'in_app')->sole()->pesan;
        $this->assertSame("{$permintaan->kode_permintaan} ditolak. Stok yang dikunci telah dilepaskan.", $pesan);
        $this->assertTrue(Notifikasi::where('user_id', $ketua->id)->where('channel', 'in_app')->exists());
    }

    /** 4.4 J13: status di luar peta penerima tidak menerbitkan notifikasi. */
    public function test_permintaan_berstatus_lain_tidak_menerbitkan_notifikasi(): void
    {
        $tim = $this->buatTim();
        $this->buatPengguna('ketua_tim', $tim);
        $permintaan = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $this->buatBarang(), 'diminta' => 1]]);
        $permintaan->status = 'status_yang_belum_dipetakan';

        $this->assertSame(0, $this->layanan()->permintaanBerubah($permintaan));
        $this->assertSame(0, Notifikasi::count());
    }

    // =====================================================================
    // 4.5 stokMenipis
    // =====================================================================

    /** @return array{0: \App\Models\User, 1: \App\Models\User, 2: \App\Models\User} */
    private function pihakPersediaan(): array
    {
        return [
            $this->buatPengguna('petugas_gudang'),
            $this->buatPengguna('kasubbag'),
            $this->buatPengguna('tim', $this->buatTim()),
        ];
    }

    /** J1 */
    public function test_stok_habis_memberi_tahu_gudang_dan_kasubbag(): void
    {
        [$gudang, $kasubbag, $tim] = $this->pihakPersediaan();
        $barang = $this->buatBarang(stokFisik: 5, stokHold: 5, tambahan: ['stok_minimum' => 2]);

        $this->assertSame(2, $this->layanan()->stokMenipis($barang));

        $this->assertSame(['Stok habis'], Notifikasi::where('user_id', $gudang->id)->pluck('judul')->all());
        $this->assertTrue(Notifikasi::where('user_id', $kasubbag->id)->where('judul', 'Stok habis')->exists());
        $this->assertFalse(Notifikasi::where('user_id', $tim->id)->exists());
    }

    /** J2 */
    public function test_stok_tidak_dipantau_tidak_menerbitkan_peringatan(): void
    {
        $this->pihakPersediaan();

        $this->assertSame(0, $this->layanan()->stokMenipis($this->buatBarang(stokFisik: 1, tambahan: ['stok_minimum' => 0])));
        $this->assertSame(0, Notifikasi::count());
    }

    /** J3 */
    public function test_stok_di_atas_minimum_tidak_menerbitkan_peringatan(): void
    {
        $this->pihakPersediaan();

        $this->assertSame(0, $this->layanan()->stokMenipis($this->buatBarang(stokFisik: 10, tambahan: ['stok_minimum' => 3])));
        $this->assertSame(0, Notifikasi::count());
    }

    /** J4 */
    public function test_stok_menipis_memberi_tahu_gudang_dan_kasubbag(): void
    {
        [$gudang] = $this->pihakPersediaan();
        $barang = $this->buatBarang(stokFisik: 3, tambahan: ['stok_minimum' => 3]);

        $this->assertSame(2, $this->layanan()->stokMenipis($barang));

        $notifikasi = Notifikasi::where('user_id', $gudang->id)->where('channel', 'in_app')->sole();
        $this->assertSame('Stok menipis', $notifikasi->judul);
        $this->assertStringContainsString('tersisa 3 Buah', $notifikasi->pesan);
    }

    // =====================================================================
    // 4.6 bastBerubah
    // =====================================================================

    private function bastBerNupNol(string $status): BastMutasiAset
    {
        $asal   = $this->buatTim('Tim Asal');
        $tujuan = $this->buatTim('Tim Tujuan');
        $this->buatPengguna('kasubbag');
        $this->buatPengguna('ketua_tim', $tujuan);
        $gudang = $this->buatPengguna('petugas_gudang');
        $aset   = $this->buatAset($asal, ['nup' => '0']);

        return BastMutasiAset::create([
            'nomor_bast' => 'BAST-UJI-7001', 'aset_id' => $aset->id, 'tim_asal_id' => $asal->id,
            'tim_tujuan_id' => $tujuan->id, 'alasan_mutasi' => 'Uji', 'pihak_penyerah' => 'Uji',
            'pihak_penerima' => 'Uji', 'status' => $status, 'dibuat_oleh_id' => $gudang->id,
        ]);
    }

    /** J5: NUP '0' bernilai falsy di PHP sehingga tidak disebut pada pesan. */
    public function test_bast_menunggu_pengesahan_nup_nol_tanpa_nup(): void
    {
        $bast = $this->bastBerNupNol('menunggu_pengesahan');

        $this->assertSame(1, $this->layanan()->bastBerubah($bast));

        $pesan = Notifikasi::where('tipe', 'mutasi')->where('channel', 'in_app')->sole()->pesan;
        $this->assertSame("BAST-UJI-7001 untuk mutasi {$bast->aset->nama_aset} dari Tim Asal ke Tim Tujuan menunggu pengesahan Anda.", $pesan);
    }

    /** J6 */
    public function test_bast_menunggu_konfirmasi_nup_nol_tanpa_nup(): void
    {
        $bast = $this->bastBerNupNol('menunggu_konfirmasi');

        $this->assertSame(1, $this->layanan()->bastBerubah($bast));

        $pesan = Notifikasi::where('tipe', 'mutasi')->where('channel', 'in_app')->sole()->pesan;
        $this->assertStringNotContainsString('(0)', $pesan);
        $this->assertStringContainsString($bast->aset->nama_aset . ' dipindahkan ke Tim Tujuan', $pesan);
    }

    // =====================================================================
    // 4.7 TindakanPermintaan::statusUntuk, 4.8 PerluTindakan::kueri
    // =====================================================================

    private function permintaanBerstatus(string $status): PermintaanBarang
    {
        $tim = $this->buatTim(uniqid('Tim '));

        return $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $this->buatBarang(), 'diminta' => 1]], $status);
    }

    /** 4.7 J3 */
    public function test_perlu_tindakan_kasubbag_melihat_persetujuan_dan_pengesahan(): void
    {
        $persetujuan = $this->permintaanBerstatus('menunggu_kasubbag');
        $pengesahan  = $this->permintaanBerstatus('menunggu_pengesahan');
        $this->permintaanBerstatus('menunggu_ketua');
        $this->actingAs($this->buatPengguna('kasubbag'));

        $kode = collect(Livewire::test(PerluTindakan::class)->instance()->getPekerjaanProperty())->pluck('kode')->sort()->values()->all();

        $this->assertSame(collect([$persetujuan->kode_permintaan, $pengesahan->kode_permintaan])->sort()->values()->all(), $kode);
    }

    /** 4.7 J5 dan 4.8 J1: Admin tidak punya status tindakan, sehingga kueri dikosongkan. */
    public function test_perlu_tindakan_admin_tidak_melihat_apa_pun(): void
    {
        $this->permintaanBerstatus('menunggu_kasubbag');
        $this->permintaanBerstatus('siap_diambil');
        $this->actingAs($this->buatPengguna('admin'));

        $widget = Livewire::test(PerluTindakan::class)->instance();

        $this->assertSame(0, $widget->getTotalProperty());
        $this->assertCount(0, $widget->getPekerjaanProperty());
    }

    // =====================================================================
    // 4.10 StatusPermintaanTim::cacah
    // =====================================================================

    /** J1 dan J2: render mencacah (J2), keterangan memakai singgahan (J1); tim lain tidak ikut. */
    public function test_status_permintaan_tim_hanya_mencacah_tim_pengguna(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');
        $anggota = $this->buatPengguna('tim', $tim);
        $this->buatPermintaan($tim, $anggota, [['barang' => $this->buatBarang(), 'diminta' => 1]], 'selesai');
        $this->buatPermintaan($tim, $anggota, [['barang' => $this->buatBarang(), 'diminta' => 1]], 'menunggu_ketua');
        $this->buatPermintaan($lain, $this->buatPengguna('tim', $lain), [['barang' => $this->buatBarang(), 'diminta' => 1]], 'selesai');
        $this->actingAs($this->buatPengguna('ketua_tim', $tim));

        $widget = Livewire::test(StatusPermintaanTim::class)->instance();

        $this->assertSame('Total 2 permintaan tim', $widget->getDescription());
    }

    // =====================================================================
    // 4.12 TrenKonsumsiTim::keluarPerHari, 4.13 PolaPermintaan::cacahPerHari
    // =====================================================================

    /** Dua pengeluaran tim sendiri dari dua kategori, satu pengeluaran tim lain. */
    private function pengeluaranDuaKategori(): array
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');
        $gudang = $this->buatPengguna('petugas_gudang');
        $kategoriLain = Kategori::create(['kode_kategori' => 'K-LAIN', 'kode_akun' => '117112', 'nama_kategori' => 'Bahan Cetak', 'tipe' => 'persediaan']);

        $atk   = $this->buatBarang(stokFisik: 100);
        $cetak = $this->buatBarang(stokFisik: 100, tambahan: ['kategori_id' => $kategoriLain->id]);

        foreach ([[$tim, $atk, 4], [$tim, $cetak, 6], [$lain, $atk, 20]] as [$milik, $barang, $jumlah]) {
            $permintaan = $this->buatPermintaan($milik, $this->buatPengguna('tim', $milik), [['barang' => $barang, 'diminta' => $jumlah, 'final' => $jumlah]], 'siap_diambil');
            app(StokService::class)->konversi($permintaan, $gudang->id);
        }

        return [$tim, $kategoriLain];
    }

    /** 4.12 J1 */
    public function test_tren_konsumsi_tim_penyaring_null_tidak_menyaring_kategori(): void
    {
        [$tim] = $this->pengeluaranDuaKategori();
        $this->actingAs($this->buatPengguna('ketua_tim', $tim));

        $seri = Livewire::test(TrenKonsumsiTim::class)->set('filter', null)->instance()->seri;

        $this->assertSame(10, array_sum($seri['nilai']));
    }

    /** 4.12 J3 */
    public function test_tren_konsumsi_tim_menyaring_kategori(): void
    {
        [$tim, $kategoriLain] = $this->pengeluaranDuaKategori();
        $this->actingAs($this->buatPengguna('ketua_tim', $tim));

        $seri = Livewire::test(TrenKonsumsiTim::class)->set('filter', (string) $kategoriLain->id)->instance()->seri;

        $this->assertSame(6, array_sum($seri['nilai']));
    }

    /** 4.13 J2: periode 'semua' tidak memasang batas tanggal, cakupan tim tetap berlaku. */
    public function test_pola_permintaan_seluruh_periode_tanpa_batas_tanggal(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');
        $anggota = $this->buatPengguna('tim', $tim);
        $lama = $this->buatPermintaan($tim, $anggota, [['barang' => $this->buatBarang(), 'diminta' => 1]]);
        $lama->forceFill(['created_at' => now()->subDays(200)])->save();
        $this->buatPermintaan($tim, $anggota, [['barang' => $this->buatBarang(), 'diminta' => 1]]);
        $this->buatPermintaan($lain, $this->buatPengguna('tim', $lain), [['barang' => $this->buatBarang(), 'diminta' => 1]]);
        $this->actingAs($anggota);

        $widget = Livewire::test(PolaPermintaan::class)->set('filter', 'semua')->instance();
        $metode = new \ReflectionMethod($widget, 'cacahPerHari');
        $metode->setAccessible(true);

        $this->assertSame(2, $metode->invoke($widget)->sum(), 'Permintaan 200 hari lalu ikut; permintaan tim lain tidak.');
    }
}
