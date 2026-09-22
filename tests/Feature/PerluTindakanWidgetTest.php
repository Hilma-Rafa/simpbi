<?php

namespace Tests\Feature;

use App\Filament\Widgets\PerluTindakan;
use App\Models\PermintaanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Panel "Perlu Tindakan Anda" harus menjadi ringkasan tindakan yang benar-benar
 * dapat dijalankan pengguna saat itu — bukan cocokan status yang lepas dari
 * tombol aksi yang sebenarnya tampil di Daftar Permintaan Barang.
 *
 * Keadaan yang paling menentukan: Ketua Tim berhak "Konfirmasi Penerimaan" pada
 * permintaan berstatus `siap_diambil`, sehingga permintaan itu wajib muncul di
 * panelnya. Sebelumnya panel hanya melihat `menunggu_ketua`, sehingga tindakan
 * yang tombolnya nyata-nyata ada tidak pernah terangkum.
 */
class PerluTindakanWidgetTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** @return array<int,string> kode permintaan pada panel pengguna aktif */
    private function kodePanel(): array
    {
        $widget = Livewire::test(PerluTindakan::class)->instance();

        return collect($widget->getPekerjaanProperty())->pluck('kode')->all();
    }

    private function siapDiambil(\App\Models\Tim $tim): PermintaanBarang
    {
        return $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'siap_diambil',
        );
    }

    public function test_ketua_tim_melihat_permintaan_siap_diambil_yang_dapat_dikonfirmasi(): void
    {
        $tim   = $this->buatTim();
        $ketua = $this->buatPengguna('ketua_tim', $tim);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        $permintaan = $this->siapDiambil($tim);

        $this->actingAs($ketua);

        $this->assertContains(
            $permintaan->kode_permintaan,
            $this->kodePanel(),
            'Ketua Tim berhak Konfirmasi Penerimaan pada siap_diambil, sehingga permintaannya harus terangkum.',
        );
    }

    public function test_tim_juga_melihat_permintaan_siap_diambil_timnya(): void
    {
        $tim        = $this->buatTim();
        $permintaan = $this->siapDiambil($tim);

        $this->actingAs($this->buatPengguna('tim', $tim));

        $this->assertContains($permintaan->kode_permintaan, $this->kodePanel());
    }

    public function test_item_keluar_dari_panel_setelah_status_berubah(): void
    {
        $tim   = $this->buatTim();
        $ketua = $this->buatPengguna('ketua_tim', $tim);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();

        $permintaan = $this->siapDiambil($tim);
        $this->actingAs($ketua);

        $this->assertContains($permintaan->kode_permintaan, $this->kodePanel());

        // Sesudah dikonfirmasi, statusnya bukan lagi tanggung jawab Ketua Tim.
        $permintaan->update(['status' => 'menunggu_pengesahan']);

        $this->assertNotContains($permintaan->kode_permintaan, $this->kodePanel());
    }

    public function test_permintaan_tim_lain_tidak_muncul(): void
    {
        $timSendiri = $this->buatTim('Statistik Sosial');
        $ketua      = $this->buatPengguna('ketua_tim', $timSendiri);
        $timSendiri->forceFill(['ketua_tim_id' => $ketua->id])->save();

        $timLain      = $this->buatTim('Statistik Distribusi');
        $punyaTimLain = $this->siapDiambil($timLain);

        $this->actingAs($ketua);

        $this->assertNotContains($punyaTimLain->kode_permintaan, $this->kodePanel());
    }

    public function test_petugas_gudang_melihat_verifikasi_dan_penyiapan(): void
    {
        $tim = $this->buatTim();

        $verifikasi = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'menunggu_verifikasi');

        $penyiapan = $this->buatPermintaan($tim, $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: 'siap_diproses');

        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $kode = $this->kodePanel();
        $this->assertContains($verifikasi->kode_permintaan, $kode);
        $this->assertContains($penyiapan->kode_permintaan, $kode);
    }

    public function test_tautan_panel_membuka_pop_up_rincian(): void
    {
        $tim   = $this->buatTim();
        $ketua = $this->buatPengguna('ketua_tim', $tim);
        $tim->forceFill(['ketua_tim_id' => $ketua->id])->save();
        $permintaan = $this->siapDiambil($tim);

        $this->actingAs($ketua);

        $pekerjaan = collect(Livewire::test(PerluTindakan::class)->instance()->getPekerjaanProperty())
            ->firstWhere('kode', $permintaan->kode_permintaan);

        $this->assertStringContainsString('tableAction=detail', $pekerjaan['tautan']);
        $this->assertStringContainsString('tableActionRecord=' . $permintaan->id, $pekerjaan['tautan']);
    }
}
