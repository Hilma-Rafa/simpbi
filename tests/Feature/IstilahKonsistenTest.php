<?php

namespace Tests\Feature;

use App\Filament\Pages\Riwayat;
use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Filament\Resources\PermintaanBarangs\Pages\ListPermintaanBarangs;
use App\Filament\Widgets\PerluTindakan;
use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-023: keseragaman istilah dan format tanggal pada tampilan.
 *
 * Keputusan pemilik: nama peran "Kasubbag Umum"; nama tahap ke-3 "Persetujuan
 * akhir Kasubbag"; "Ekspor" (bukan "Export"); tanggal pada tabel/daftar
 * d-m-Y, pada teks dokumen dan dialog d F Y. Yang berubah hanya label yang
 * tampil; nilai status dan kunci peran di kode tidak disentuh.
 */
class IstilahKonsistenTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('id');
        Carbon::setLocale('id');
        Filament::setCurrentPanel('admin');
        Carbon::setTestNow(Carbon::create(2026, 9, 22, 9, 5, 0, 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function permintaan(string $status): PermintaanBarang
    {
        $tim = $this->buatTim();

        return $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim), [['barang' => $this->buatBarang(), 'diminta' => 1]], $status);
    }

    private function rincian(PermintaanBarang $p): string
    {
        return view('filament.partials.detail-permintaan', ['record' => $p->load(['tim', 'detail.barang', 'ketidaksesuaian', 'persetujuan.pelaksana'])])->render();
    }

    // ---------------------------------------------------------------- peran dan tahap ke-3

    public function test_label_peran_kasubbag_umum_tampil_pada_dasbor_dan_pengaturan(): void
    {
        $this->actingAs($this->lengkapiAkun($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.istilah@bps.go.id'])));

        $this->get('/admin')->assertOk()->assertSee('Kasubbag Umum');
        $this->get('/admin/pengaturan')->assertOk()->assertSee('Kasubbag Umum');
    }

    public function test_pengaturan_admin_memuat_nama_tahap_ke_3_yang_sama(): void
    {
        $this->actingAs($this->lengkapiAkun($this->buatPengguna('admin', null, ['email' => 'admin.istilah@bps.go.id'])));

        $this->get('/admin/pengaturan')->assertOk()->assertSee('Persetujuan akhir Kasubbag');
    }

    public function test_dialog_rincian_memakai_nama_tahap_ke_3_dan_peran_yang_sama(): void
    {
        $menunggu = $this->rincian($this->permintaan('menunggu_kasubbag'));
        $this->assertStringContainsString('MENUNGGU PERSETUJUAN AKHIR KASUBBAG', $menunggu);

        $p = $this->permintaan('ditolak_kasubbag');
        RiwayatPersetujuan::create(['permintaan_id' => $p->id, 'tahap' => 'kasubbag', 'keputusan' => 'tolak', 'catatan' => 'Uji', 'pelaksana_id' => $this->buatPengguna('kasubbag')->id, 'waktu' => now()]);
        $ditolak = $this->rincian($p->refresh());

        $this->assertStringContainsString('DITOLAK KASUBBAG UMUM', $ditolak);
        $this->assertStringContainsString('Persetujuan akhir Kasubbag', $ditolak, 'Linimasa tahap.');
        $this->assertStringNotContainsString('Persetujuan Kasubbag', $ditolak);
    }

    public function test_label_status_memakai_nama_yang_sama_pada_seluruh_tampilan(): void
    {
        $this->assertSame('Menunggu Persetujuan akhir Kasubbag', PermintaanBarang::STATUS['menunggu_kasubbag']);
        // Label ini mengalir ke ekspor Riwayat (PDF/Excel beku): label yang lebih panjang menggeser lebar kolom PDF, sehingga sengaja tidak diubah.
        $this->assertSame('Ditolak Kasubbag', PermintaanBarang::STATUS['ditolak_kasubbag']);
        $this->assertSame('Menunggu Kasubbag Umum', PermintaanBarang::labelRingkas('menunggu_kasubbag'));

        // Nilai status di kode dan basis data tidak berubah.
        $this->assertSame(['menunggu_kasubbag', 'ditolak_kasubbag'], array_values(array_intersect(array_keys(PermintaanBarang::STATUS), ['menunggu_kasubbag', 'ditolak_kasubbag'])));
    }

    public function test_daftar_permintaan_dan_panel_perlu_tindakan_memakai_label_baru(): void
    {
        $p = $this->permintaan('menunggu_kasubbag');
        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.daftar@bps.go.id']));
        $this->actingAs($kasubbag);

        Livewire::test(ListPermintaanBarangs::class)->assertSee('Menunggu Kasubbag Umum');
        Livewire::test(PerluTindakan::class)->assertSee('Persetujuan akhir Kasubbag');
        $this->assertNotNull($p);
    }

    public function test_halaman_masuk_dan_muka_memakai_nama_tahap_ke_3_yang_sama(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Persetujuan akhir Kasubbag');
        $this->get('/')->assertOk()->assertSee('Persetujuan akhir Kasubbag')->assertDontSee('Persetujuan Kasubbag');
    }

    // ---------------------------------------------------------------- Ekspor

    public function test_halaman_riwayat_menyebut_ekspor_bukan_export(): void
    {
        $this->actingAs($this->lengkapiAkun($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.ekspor@bps.go.id'])));

        $html = Livewire::test(Riwayat::class)->html();

        $this->assertStringContainsString('tombol Ekspor mengikuti penyaring', $html);
        $this->assertStringContainsString('Ekspor', $html);
        $this->assertStringNotContainsString('Export', $html);
    }

    // ---------------------------------------------------------------- format tanggal

    public function test_tabel_memakai_d_m_y_dan_bukan_singkatan_bulan(): void
    {
        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.tanggal@bps.go.id']));
        $tim = $this->buatTim('Tim Asal');
        $this->buatBast($tim, $this->buatTim('Tim Tujuan'), $kasubbag);
        $this->actingAs($kasubbag);

        $html = Livewire::test(ListBastMutasiAsets::class)->html();

        $this->assertStringContainsString('22-09-2026', $html);
        $this->assertStringNotContainsString('22 Sep 2026', $html);
    }

    public function test_teks_pada_dialog_rincian_memakai_d_f_y_dengan_nama_bulan_indonesia(): void
    {
        $p = $this->permintaan('menunggu_kasubbag');
        RiwayatPersetujuan::create(['permintaan_id' => $p->id, 'tahap' => 'ketua_tim', 'keputusan' => 'setuju', 'catatan' => null, 'pelaksana_id' => $this->buatPengguna('kasubbag')->id, 'waktu' => now()]);

        $html = $this->rincian($p->refresh());

        $this->assertStringContainsString('22 September 2026', $html);
        $this->assertStringNotContainsString('22 Sep 2026', $html);
    }

    public function test_strip_status_sinkronisasi_memakai_d_f_y(): void
    {
        $html = view('filament.partials.status-sinkronisasi', ['syncedAt' => now(), 'baruSinkron' => true])->render();

        $this->assertStringContainsString('22 September 2026, 09:05', $html);
        $this->assertStringNotContainsString('22 Sep 2026', $html);
    }
}
