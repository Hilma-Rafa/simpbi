<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * G-003: kata sandi pada form Pengguna.
 *
 * Penyebabnya sama dengan A-008: komponen mengganti kata sandi sesudah
 * AuthenticateSession menyimpan hash lama di sesi. Admin yang mengganti
 * sandi akunnya sendiri lewat form ini kini tetap masuk (pembantu yang sama
 * dengan Ganti Kata Sandi dan Pengaturan); mengganti sandi pengguna lain tidak
 * menyentuh sesi Admin, dan sesi lama pengguna itu tidak berlaku lagi.
 */
class SandiFormPenggunaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const SANDI_BARU = 'sandi-baru-123';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function akunLengkap(string $peran): User
    {
        return $this->lengkapiAkun($this->buatPengguna($peran, null, ['email' => uniqid($peran . '.') . '@bps.go.id']));
    }

    private function kunciSesi(): string
    {
        return 'password_hash_' . Filament::getAuthGuard();
    }

    private function sunting(User $akun)
    {
        return Livewire::test(EditUser::class, ['record' => $akun->getRouteKey()]);
    }

    public function test_admin_mengubah_sandi_akunnya_sendiri_tetap_terautentikasi(): void
    {
        $admin = $this->akunLengkap('admin');
        $this->actingAs($admin);

        $this->sunting($admin)
            ->fillForm(['password' => self::SANDI_BARU])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertTrue(Hash::check(self::SANDI_BARU, $admin->password));
        $this->assertSame($admin->password, session($this->kunciSesi()), 'Hash di sesi perangkat ini harus hash kata sandi yang baru.');

        // Permintaan berikutnya dari sesi yang sama terbuka tanpa pengalihan ke halaman masuk.
        auth()->forgetGuards();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_admin_mengubah_sandi_pengguna_lain_tidak_menyentuh_sesinya_dan_memutus_sesi_lama_pengguna_itu(): void
    {
        $admin  = $this->akunLengkap('admin');
        $lain   = $this->akunLengkap('kasubbag');
        $hashLamaLain  = $lain->password;
        $hashSesiAdmin = $admin->password;
        $this->actingAs($admin);
        session([$this->kunciSesi() => $hashSesiAdmin]);

        $this->sunting($lain)
            ->fillForm(['password' => self::SANDI_BARU])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check(self::SANDI_BARU, $lain->refresh()->password));
        $this->assertSame($hashSesiAdmin, session($this->kunciSesi()), 'Sesi Admin tidak boleh disentuh.');

        // Admin tetap terautentikasi.
        $this->flushSession();
        auth()->forgetGuards();
        $this->withSession([$this->kunciSesi() => $hashSesiAdmin])->actingAs($admin->refresh())->get('/admin/users')->assertOk();

        // Sesi lama pengguna lain itu dialihkan ke halaman masuk.
        $this->flushSession();
        auth()->forgetGuards();
        $this->withSession([$this->kunciSesi() => $hashLamaLain])
            ->actingAs($lain->refresh())
            ->get('/admin/pengaturan')
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_perubahan_tanpa_mengisi_sandi_tidak_menyentuh_apa_pun(): void
    {
        $admin = $this->akunLengkap('admin');
        $hashSemula = $admin->password;
        $this->actingAs($admin);
        session([$this->kunciSesi() => 'hash-sesi-semula']);

        $this->sunting($admin)
            ->fillForm(['name' => 'Admin Nama Baru', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertSame('Admin Nama Baru', $admin->name);
        $this->assertSame($hashSemula, $admin->password);
        $this->assertSame('hash-sesi-semula', session($this->kunciSesi()));
    }

    /** Aturan "harus berbeda dari sandi saat ini" sengaja tidak ditambahkan pada form ini. */
    public function test_sandi_yang_sama_dengan_sandi_saat_ini_tidak_ditolak_pada_form_pengguna(): void
    {
        $admin = $this->akunLengkap('admin');
        $this->actingAs($admin);

        $this->sunting($admin)
            ->fillForm(['password' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
