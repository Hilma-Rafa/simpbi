<?php

namespace Tests\Feature;

use App\Filament\Pages\Riwayat;
use App\Filament\Resources\PermintaanBarangs\Pages\ListPermintaanBarangs;
use App\Models\PermintaanBarang;
use App\Services\KedaluwarsaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian dialog rincian permintaan dan penyajian status kedaluwarsa.
 *
 * Rincian dibuka sebagai dialog, bukan halaman (Instruksi §41), dan aksinya
 * sengaja tidak ditampilkan sebagai tombol — hanya dipicu oleh klik pada
 * baris. Susunan semacam itu mudah patah tanpa disadari: aksi yang tidak
 * terdaftar membuat baris menjadi tidak melakukan apa-apa ketika ditekan,
 * dan tidak ada tombol yang hilang sebagai tanda. Karena itu pemasangannya
 * diperiksa dari kedua daftar yang memakainya.
 */
class DialogRincianPermintaanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** Membuat satu permintaan yang sudah melewati batas waktu verifikasi gudang. */
    protected function buatPermintaanKedaluwarsa(): PermintaanBarang
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 10);

        $permintaan = $this->buatPermintaan(
            $tim,
            $pengaju,
            [['barang' => $barang, 'diminta' => 10]],
            status: 'menunggu_verifikasi',
            tambahan: ['hold_expired_at' => now()->subHours(3)],
        );

        app(KedaluwarsaService::class)->sapu();

        return $permintaan->refresh();
    }

    public function test_dialog_rincian_dapat_dibuka_dari_daftar_permintaan(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang();

        $permintaan = $this->buatPermintaan(
            $tim,
            $pengaju,
            [['barang' => $barang, 'diminta' => 5]],
        );

        $this->actingAs($this->buatPengguna('petugas_gudang'));

        Livewire::test(ListPermintaanBarangs::class)
            ->mountTableAction('detail', $permintaan)
            ->assertActionMounted(TestAction::make('detail')->table($permintaan))
            ->assertMountedActionModalSee($permintaan->kode_permintaan);
    }

    public function test_dialog_rincian_dapat_dibuka_dari_riwayat(): void
    {
        $permintaan = $this->buatPermintaanKedaluwarsa();

        $this->actingAs($this->buatPengguna('petugas_gudang'));

        Livewire::test(Riwayat::class, ['jenis' => 'permintaan'])
            ->mountTableAction('detail', $permintaan)
            ->assertActionMounted(TestAction::make('detail')->table($permintaan))
            ->assertMountedActionModalSee($permintaan->kode_permintaan);
    }

    public function test_status_menyebut_tahap_tempat_permintaan_terhenti(): void
    {
        $permintaan = $this->buatPermintaanKedaluwarsa();

        $this->assertSame('kedaluwarsa', $permintaan->status);

        $this->actingAs($this->buatPengguna('petugas_gudang'));

        Livewire::test(Riwayat::class, ['jenis' => 'permintaan'])
            ->mountTableAction('detail', $permintaan)
            // Label polos "KEDALUWARSA" tidak memberi tahu siapa yang
            // seharusnya menindaklanjuti; tahapnya harus ikut terbaca.
            ->assertMountedActionModalSee('KEDALUWARSA PADA VERIFIKASI GUDANG')
            ->assertMountedActionModalSee('Lewat batas waktu')
            // Tahap sesudah titik berhenti ditampilkan sebagai tidak tercapai
            ->assertMountedActionModalSee('Tidak sampai tahap ini')
            // Catatan sistem tidak boleh tampil sebagai penolakan oleh seseorang
            ->assertMountedActionModalDontSee('Alasan penolakan');
    }

    public function test_riwayat_mencatat_saat_batas_jatuh_bukan_saat_sapuan(): void
    {
        $batas = now()->subHours(3)->startOfMinute();

        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 10);

        $permintaan = $this->buatPermintaan(
            $tim,
            $pengaju,
            [['barang' => $barang, 'diminta' => 10]],
            status: 'menunggu_verifikasi',
            tambahan: ['hold_expired_at' => $batas],
        );

        app(KedaluwarsaService::class)->sapu();

        $baris = $permintaan->persetujuan()->latest('waktu')->first();

        $this->assertSame('verifikasi', $baris->tahap);
        $this->assertSame(
            $batas->format('Y-m-d H:i'),
            $baris->waktu->format('Y-m-d H:i'),
            'Riwayat harus mencatat saat batas jatuh, bukan saat sapuan berjalan.',
        );
    }
}
