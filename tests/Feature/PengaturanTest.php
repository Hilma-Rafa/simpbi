<?php

namespace Tests\Feature;

use App\Filament\Pages\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Halaman Pengaturan sesudah pembenahan UI/UX-nya.
 *
 * Yang diuji bukan tampilannya, melainkan perilaku yang dipertahankan dan
 * yang secara eksplisit ditambahkan: verifikasi kata sandi saat ini yang
 * sebelumnya tidak ada sama sekali, keadaan kosong tetap berarti "tidak
 * diubah", pembagian akses per peran, dan dialog konfirmasi mode peragaan
 * yang hanya tampil ketika memang mengaktifkan atau mengubah nomornya.
 */
class PengaturanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    // =====================================================================
    // AKSES PER PERAN
    // =====================================================================

    public function test_hanya_admin_yang_melihat_pengaturan_sistem(): void
    {
        foreach (['admin' => true, 'kasubbag' => false, 'petugas_gudang' => false, 'ketua_tim' => false, 'tim' => false] as $peran => $boleh) {
            $this->actingAs($this->buatPengguna($peran, $peran === 'ketua_tim' || $peran === 'tim' ? $this->buatTim() : null));

            $test = Livewire::test(Pengaturan::class);

            if ($boleh) {
                $test->assertSee('Batas Waktu Alur')->assertSee('Notifikasi WhatsApp');
            } else {
                $test->assertDontSee('Batas Waktu Alur')->assertDontSee('Notifikasi WhatsApp');
            }
        }
    }

    public function test_seluruh_peran_melihat_akun_saya_dan_ubah_kata_sandi(): void
    {
        foreach (['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $peran) {
            $this->actingAs($this->buatPengguna($peran, in_array($peran, ['ketua_tim', 'tim']) ? $this->buatTim() : null));

            Livewire::test(Pengaturan::class)
                ->assertSee('Akun Saya')
                ->assertSee('Ubah Kata Sandi');
        }
    }

    /**
     * Penjagaan sisi server: sekalipun bagian Pengaturan Sistem disembunyikan
     * dari peran non-Admin, nilai yang dikirim tidak boleh tersimpan bila
     * suatu saat berhasil dikirim juga (mis. lewat permintaan yang dipalsukan).
     */
    public function test_non_admin_tidak_dapat_mengubah_pengaturan_sistem_lewat_server(): void
    {
        $semula = DB::table('pengaturan')->where('kunci', 'batas_ketua_jam')->value('nilai');

        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.uji@bps.go.id']));

        Livewire::test(Pengaturan::class)
            ->set('data.name', 'Nama Kasubbag Baru')
            ->set('data.batas_ketua_jam', '999')
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame(
            $semula,
            DB::table('pengaturan')->where('kunci', 'batas_ketua_jam')->value('nilai'),
            'Nilai batas waktu tidak boleh berubah walau dikirim oleh peran non-Admin.'
        );
    }

    // =====================================================================
    // UBAH KATA SANDI
    // =====================================================================

    public function test_kata_sandi_saat_ini_yang_salah_ditolak(): void
    {
        $pengguna = $this->buatPengguna('tim', $this->buatTim());
        $this->actingAs($pengguna);

        Livewire::test(Pengaturan::class)
            ->set('sedangUbahSandi', true)
            ->fillForm([
                'current_password'      => 'sandi-salah',
                'password'              => 'kata-sandi-baru',
                'password_confirmation' => 'kata-sandi-baru',
            ])
            ->call('simpan')
            ->assertHasFormErrors(['current_password']);

        $this->assertTrue(Hash::check('password', $pengguna->refresh()->password), 'Kata sandi lama tidak boleh berubah.');
    }

    public function test_kata_sandi_saat_ini_yang_benar_mengganti_kata_sandi(): void
    {
        $pengguna = $this->buatPengguna('tim', $this->buatTim(), ['email' => 'tim.sandi@bps.go.id']);
        $this->actingAs($pengguna);

        Livewire::test(Pengaturan::class)
            ->set('sedangUbahSandi', true)
            ->fillForm([
                'current_password'      => 'password',
                'password'              => 'kata-sandi-baru',
                'password_confirmation' => 'kata-sandi-baru',
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('kata-sandi-baru', $pengguna->refresh()->password));
    }

    /** Kolom kosong tetap berarti "tidak diubah", sekalipun bagiannya sudah dibuka. */
    public function test_kata_sandi_baru_kosong_tidak_mengubah_apa_apa(): void
    {
        $pengguna = $this->buatPengguna('tim', $this->buatTim(), ['email' => 'tim.kosong@bps.go.id']);
        $sandiSemula = $pengguna->password;
        $this->actingAs($pengguna);

        Livewire::test(Pengaturan::class)
            ->set('sedangUbahSandi', true)
            ->fillForm([
                'no_hp'    => '081299998888',
                'password' => '',
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $pengguna->refresh();
        $this->assertSame($sandiSemula, $pengguna->password);
        $this->assertSame('081299998888', $pengguna->no_hp);
    }

    /** Bagian kata sandi tertutup sampai tombolnya ditekan. */
    public function test_kolom_kata_sandi_tersembunyi_sampai_tombol_ditekan(): void
    {
        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));

        Livewire::test(Pengaturan::class)
            ->assertDontSee('Kata Sandi Saat Ini')
            ->set('sedangUbahSandi', true)
            ->assertSee('Kata Sandi Saat Ini');
    }

    // =====================================================================
    // MODE PERAGAAN
    // =====================================================================

    public function test_konfirmasi_diperlukan_saat_mengaktifkan_mode_peragaan(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->update(['nilai' => '']);
        $this->actingAs($this->buatPengguna('admin'));

        $test = Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '081234567890');

        $this->assertTrue($test->instance()->simpanAction()->isConfirmationRequired());
    }

    public function test_konfirmasi_diperlukan_saat_mengubah_nomor_peragaan(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->update(['nilai' => '6281234567890']);
        $this->actingAs($this->buatPengguna('admin'));

        $test = Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '089999999999');

        $this->assertTrue($test->instance()->simpanAction()->isConfirmationRequired());
    }

    public function test_konfirmasi_tidak_diperlukan_saat_mematikan_mode_peragaan(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->update(['nilai' => '6281234567890']);
        $this->actingAs($this->buatPengguna('admin'));

        $test = Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '');

        $this->assertFalse($test->instance()->simpanAction()->isConfirmationRequired());
    }

    public function test_konfirmasi_tidak_diperlukan_bila_nomor_tidak_berubah(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->update(['nilai' => '6281234567890']);
        $this->actingAs($this->buatPengguna('admin'));

        $test = Livewire::test(Pengaturan::class);

        $this->assertFalse($test->instance()->simpanAction()->isConfirmationRequired());
    }

    public function test_konfirmasi_tidak_pernah_diperlukan_bagi_non_admin(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag'));

        $test = Livewire::test(Pengaturan::class);

        $this->assertFalse($test->instance()->simpanAction()->isConfirmationRequired());
    }

    /** Toggle mode peragaan murni representasi field existing, bukan setting baru. */
    public function test_mode_peragaan_on_menyimpan_ke_field_existing(): void
    {
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.on@bps.go.id']));

        Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '081234567890')
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame(
            '6281234567890',
            DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->value('nilai'),
        );
    }

    public function test_mode_peragaan_off_mengosongkan_field_existing(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->update(['nilai' => '6281234567890']);
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.off@bps.go.id']));

        Livewire::test(Pengaturan::class)
            ->set('data.wa_alihkan_ke', '')
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame(
            '',
            DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->value('nilai'),
        );
    }

    // =====================================================================
    // BATAS WAKTU ALUR
    // =====================================================================

    public function test_konversi_jam_ke_hari_kerja_benar(): void
    {
        $this->assertSame('2', Pengaturan::keHariKerja(16));
        $this->assertSame('1,5', Pengaturan::keHariKerja(12));
        $this->assertSame('1', Pengaturan::keHariKerja(8));
    }

    public function test_total_jam_alur_mengikuti_nilai_formulir_yang_berjalan(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $test = Livewire::test(Pengaturan::class)
            ->set('data.batas_ketua_jam', '10');

        $this->assertGreaterThan(40, $test->instance()->totalJamAlur());
    }

    public function test_batas_waktu_tersimpan_dengan_nama_kolom_yang_sama(): void
    {
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.batas@bps.go.id']));

        Livewire::test(Pengaturan::class)
            ->set('data.batas_ketua_jam', '24')
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame('24', DB::table('pengaturan')->where('kunci', 'batas_ketua_jam')->value('nilai'));
    }
}
