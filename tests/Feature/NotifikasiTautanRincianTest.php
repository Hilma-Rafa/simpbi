<?php

namespace Tests\Feature;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Livewire\LoncengNotifikasi;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Notifikasi dalam aplikasi dan tautan lama tidak boleh lagi membuka halaman
 * detail /{id}. Keduanya bermuara pada Daftar Permintaan Barang yang tersaring
 * dengan pop-up Rincian yang langsung terbuka.
 */
class NotifikasiTautanRincianTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function permintaan(string $status = 'menunggu_ketua'): PermintaanBarang
    {
        $tim = $this->buatTim();

        return $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $this->buatBarang(stokFisik: 20, stokHold: 5), 'diminta' => 5]],
            status: $status,
        );
    }

    public function test_tautan_lonceng_menuju_pop_up_rincian_bukan_halaman_detail(): void
    {
        $permintaan = $this->permintaan();

        $notifikasi = Notifikasi::create([
            'user_id'         => $this->buatPengguna('kasubbag')->id,
            'judul'           => 'Uji',
            'pesan'           => 'Uji',
            'tipe'            => 'permintaan',
            'channel'         => 'in_app',
            'referensi_tabel' => 'permintaan_barang',
            'referensi_id'    => $permintaan->id,
        ]);

        $this->actingAs(\App\Models\User::find($notifikasi->user_id));

        $tautan = (new LoncengNotifikasi())->tautan($notifikasi);

        $this->assertNotNull($tautan);
        $this->assertStringContainsString('tableAction=detail', $tautan);
        $this->assertStringContainsString('tableActionRecord=' . $permintaan->id, $tautan);
        $this->assertStringNotContainsString('permintaan-barangs/' . $permintaan->id, $tautan);
    }

    public function test_halaman_detail_lama_mengalihkan_ke_pop_up_rincian(): void
    {
        $permintaan = $this->permintaan();

        $this->actingAs($this->lengkapiAkun($this->buatPengguna('kasubbag')));

        $this->get(PermintaanBarangResource::getUrl('detail', ['record' => $permintaan]))
            ->assertRedirect(PermintaanBarangResource::urlRincian($permintaan));
    }
}
