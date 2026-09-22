<?php

namespace Tests\Feature;

use App\Filament\Pages\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-007: tombol Simpan Perubahan pada Pengaturan.
 *
 * Diuji lewat aksi tombolnya (mountAction), bukan ->call('simpan'): yang
 * dipersoalkan adalah dialog yang terbuka sebelum simpan() sempat dipanggil.
 * Setiap langkah uji berjalan sebagai permintaan Livewire tersendiri, sama
 * seperti di peramban, sehingga apa pun yang hanya diingat komponen dari
 * pemuatan awal sudah hilang ketika tombol ditekan.
 */
class PengaturanTombolSimpanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private function peragaan(string $nilai): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->update(['nilai' => $nilai]);
    }

    private function tersimpanPeragaan(): string
    {
        return (string) DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->value('nilai');
    }

    public function test_non_admin_menyimpan_langsung_tanpa_dialog_walau_mode_peragaan_menyala(): void
    {
        // Mode peragaan menyala pada nomor yang tersimpan: dialog lama tetap muncul untuk semua peran.
        $this->peragaan('6281234567890');

        foreach (['kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $urutan => $peran) {
            $tim = in_array($peran, ['ketua_tim', 'tim'], true) ? $this->buatTim('Tim ' . $peran) : null;
            $pengguna = $this->buatPengguna($peran, $tim, ['email' => $peran . '.simpan@bps.go.id']);
            $nomor = '08123400000' . $urutan;

            $this->actingAs($pengguna);

            Livewire::test(Pengaturan::class)
                ->set('data.no_hp', $nomor)
                ->mountAction('simpan')
                ->assertActionNotMounted('simpan');

            $this->assertSame($nomor, $pengguna->refresh()->no_hp, "Nomor {$peran} harus tersimpan langsung.");
        }
    }

    public function test_admin_tanpa_perubahan_peragaan_menyimpan_langsung_saat_mode_mati(): void
    {
        $this->peragaan('');
        $admin = $this->buatPengguna('admin', null, ['email' => 'admin.mati@bps.go.id']);
        $this->actingAs($admin);

        Livewire::test(Pengaturan::class)
            ->set('data.no_hp', '081200001111')
            ->mountAction('simpan')
            ->assertActionNotMounted('simpan');

        $this->assertSame('081200001111', $admin->refresh()->no_hp);
        $this->assertSame('', $this->tersimpanPeragaan());
    }

    public function test_admin_tanpa_perubahan_peragaan_menyimpan_langsung_saat_mode_menyala(): void
    {
        $this->peragaan('6281234567890');
        $admin = $this->buatPengguna('admin', null, ['email' => 'admin.nyala@bps.go.id']);
        $this->actingAs($admin);

        Livewire::test(Pengaturan::class)
            ->set('data.no_hp', '081200002222')
            ->mountAction('simpan')
            ->assertActionNotMounted('simpan');

        $this->assertSame('081200002222', $admin->refresh()->no_hp);
        $this->assertSame('6281234567890', $this->tersimpanPeragaan());
    }

    /** Nomor yang sama sesudah diseragamkan (08xx dan 62xx) bukan perubahan. */
    public function test_nomor_peragaan_yang_sama_dalam_bentuk_lain_tidak_membuka_dialog(): void
    {
        $this->peragaan('6281234567890');
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.bentuk@bps.go.id']));

        Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '081234567890')
            ->mountAction('simpan')
            ->assertActionNotMounted('simpan');

        $this->assertSame('6281234567890', $this->tersimpanPeragaan());
    }

    public function test_admin_mengaktifkan_mode_peragaan_diminta_konfirmasi_dan_tersimpan_setelahnya(): void
    {
        $this->peragaan('');
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.aktifkan@bps.go.id']));

        $uji = Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '081234567890')
            ->mountAction('simpan')
            ->assertActionMounted('simpan');

        $aksi = $uji->instance()->getMountedAction();
        $this->assertSame('Aktifkan mode peragaan?', (string) $aksi->getModalHeading());
        $this->assertSame('Ya, Simpan', $aksi->getModalSubmitActionLabel());

        $this->assertSame('', $this->tersimpanPeragaan(), 'Belum tersimpan sebelum dikonfirmasi.');

        $uji->callMountedAction()->assertActionNotMounted('simpan');

        $this->assertSame('6281234567890', $this->tersimpanPeragaan());
    }

    public function test_admin_mengubah_nomor_peragaan_diminta_konfirmasi_dan_tersimpan_setelahnya(): void
    {
        $this->peragaan('6281234567890');
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.ubah@bps.go.id']));

        $uji = Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '089999999999')
            ->mountAction('simpan')
            ->assertActionMounted('simpan');

        $this->assertSame('Ubah nomor mode peragaan?', (string) $uji->instance()->getMountedAction()->getModalHeading());

        $this->assertSame('6281234567890', $this->tersimpanPeragaan());

        $uji->callMountedAction();

        $this->assertSame('6289999999999', $this->tersimpanPeragaan());
    }

    /** Perilaku existing dipertahankan: mematikan mode peragaan tidak memerlukan konfirmasi. */
    public function test_admin_mematikan_mode_peragaan_menyimpan_langsung(): void
    {
        $this->peragaan('6281234567890');
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.matikan@bps.go.id']));

        Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '')
            ->mountAction('simpan')
            ->assertActionNotMounted('simpan');

        $this->assertSame('', $this->tersimpanPeragaan());
    }
}
