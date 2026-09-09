<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Services\NotifikasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian notifikasi alur mutasi aset (UC-16, UC-17, UC-18).
 *
 * Yang dijaga di sini bukan isi pesannya, melainkan **siapa** yang menerimanya
 * pada setiap tahap. Notifikasi yang salah alamat bukan sekadar mengganggu:
 * pada kanal WhatsApp ia berarti pesan sungguhan terkirim ke ponsel pegawai
 * yang sebenarnya tidak berkepentingan atas tahapan tersebut.
 */
class NotifikasiMutasiAsetTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** @return array<int,string> nama penerima notifikasi terbaru */
    private function penerimaTerakhir(): array
    {
        return Notifikasi::with('user')
            ->where('tipe', 'mutasi')
            ->get()
            ->map(fn (Notifikasi $n) => $n->user->name)
            ->sort()
            ->values()
            ->all();
    }

    public function test_bast_baru_memberitahu_kasubbag_saja(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Sosial');

        $kasubbag = $this->buatPengguna('kasubbag', $asal);
        $gudang   = $this->buatPengguna('petugas_gudang', $asal);
        $ketua    = $this->buatPengguna('ketua_tim', $tujuan);

        $bast = $this->buatBast($asal, $tujuan, $gudang, status: 'menunggu_pengesahan');

        app(NotifikasiService::class)->bastBerubah($bast);

        $this->assertSame([$kasubbag->name], $this->penerimaTerakhir());
        $this->assertNotContains(
            $ketua->name,
            $this->penerimaTerakhir(),
            'Ketua Tim tujuan belum berkepentingan sebelum dokumennya disahkan.'
        );
    }

    public function test_bast_disahkan_memberitahu_ketua_tim_tujuan_saja(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Sosial');

        $this->buatPengguna('kasubbag', $asal);
        $gudang     = $this->buatPengguna('petugas_gudang', $asal);
        $ketuaTujuan = $this->buatPengguna('ketua_tim', $tujuan);
        $ketuaLain   = $this->buatPengguna('ketua_tim', $asal);

        $bast = $this->buatBast($asal, $tujuan, $gudang, status: 'menunggu_konfirmasi');

        app(NotifikasiService::class)->bastBerubah($bast);

        $penerima = $this->penerimaTerakhir();
        $this->assertSame([$ketuaTujuan->name], $penerima);
        $this->assertNotContains($ketuaLain->name, $penerima, 'Ketua Tim unit lain tidak berkepentingan.');
    }

    public function test_mutasi_selesai_memberitahu_kasubbag_dan_petugas_gudang(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Sosial');

        $kasubbag = $this->buatPengguna('kasubbag', $asal);
        $gudang   = $this->buatPengguna('petugas_gudang', $asal);
        $ketua    = $this->buatPengguna('ketua_tim', $tujuan);

        $bast = $this->buatBast($asal, $tujuan, $gudang, status: 'selesai_administratif');

        app(NotifikasiService::class)->bastBerubah($bast);

        $penerima = $this->penerimaTerakhir();
        $this->assertContains($kasubbag->name, $penerima);
        $this->assertContains($gudang->name, $penerima);
        $this->assertNotContains($ketua->name, $penerima, 'Ketua Tim tujuan yang mengonfirmasi tidak perlu diberi tahu.');
    }

    public function test_notifikasi_menunjuk_ke_bast_yang_benar(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Sosial');

        $this->buatPengguna('kasubbag', $asal);
        $gudang = $this->buatPengguna('petugas_gudang', $asal);

        $bast = $this->buatBast($asal, $tujuan, $gudang);

        app(NotifikasiService::class)->bastBerubah($bast);

        $notifikasi = Notifikasi::where('tipe', 'mutasi')->sole();

        // Keterhubungan ke transaksi memakai referensi, bukan kunci asing
        // langsung, sesuai rancangan tabel notifikasi.
        $this->assertSame('bast_mutasi_aset', $notifikasi->referensi_tabel);
        $this->assertSame($bast->id, $notifikasi->referensi_id);
        $this->assertStringContainsString($bast->nomor_bast, $notifikasi->pesan);
        $this->assertStringContainsString($bast->aset->nama_aset, $notifikasi->pesan);
    }

    public function test_pesan_menyebut_unit_asal_dan_tujuan(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Distribusi');

        $this->buatPengguna('kasubbag', $asal);
        $gudang = $this->buatPengguna('petugas_gudang', $asal);

        $bast = $this->buatBast($asal, $tujuan, $gudang);

        app(NotifikasiService::class)->bastBerubah($bast);

        $pesan = Notifikasi::where('tipe', 'mutasi')->sole()->pesan;
        $this->assertStringContainsString('Sub Bagian Umum', $pesan);
        $this->assertStringContainsString('Statistik Distribusi', $pesan);
    }

    /**
     * Penjagaan untuk status yang belum dikenali.
     *
     * Basis data sudah menolak status di luar enum, sehingga keadaan ini tidak
     * mungkin muncul dari pemakaian biasa — statusnya karena itu diubah hanya
     * di memori. Yang dijaga adalah keadaan di masa depan: bila kelak ada
     * status baru ditambahkan ke enum tetapi cabangnya lupa dibuat, sistem
     * cukup tidak menerbitkan notifikasi, bukan berhenti dengan galat di
     * tengah aksi pengguna.
     */
    public function test_status_yang_belum_dikenali_tidak_menerbitkan_notifikasi(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Sosial');

        $this->buatPengguna('kasubbag', $asal);
        $gudang = $this->buatPengguna('petugas_gudang', $asal);

        $bast = $this->buatBast($asal, $tujuan, $gudang);
        $bast->status = 'status_baru_yang_belum_ditangani';

        $jumlah = app(NotifikasiService::class)->bastBerubah($bast);

        $this->assertSame(0, $jumlah);
        $this->assertSame(0, Notifikasi::where('tipe', 'mutasi')->count());
    }
}
