<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-010: Admin tidak dapat mengunci dirinya sendiri.
 *
 * Kolom peran dan status pada akun sendiri tampil tetapi terkunci; server
 * menolak muatan yang dimodifikasi, dan menolak perubahan yang membuat
 * sistem kehilangan Admin aktif terakhirnya.
 */
class AdminTidakMengunciDiriTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN_SENDIRI = 'Anda tidak dapat menonaktifkan atau mengubah peran akun Anda sendiri.';

    private const PESAN_TERAKHIR = 'Harus ada minimal satu Admin aktif.';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** Form pengguna mewajibkan email, sedangkan buatPengguna() tidak mengisinya. */
    private function akun(string $peran, array $tambahan = []): User
    {
        return $this->buatPengguna($peran, null, $tambahan + ['email' => uniqid($peran . '.') . '@bps.go.id']);
    }

    private function sunting(User $akun)
    {
        return Livewire::test(EditUser::class, ['record' => $akun->getRouteKey()]);
    }

    private function pesanDitolak($uji, string $pesan): void
    {
        Notification::assertNotified(
            Notification::make()->danger()->title('Perubahan ditolak')->body($pesan)
        );
    }

    // =====================================================================
    // AKUN SENDIRI
    // =====================================================================

    public function test_admin_melihat_kolom_peran_dan_status_akun_sendiri_terkunci(): void
    {
        $admin = $this->akun('admin');
        $this->actingAs($admin);

        $this->sunting($admin)
            ->assertFormFieldIsDisabled('role')
            ->assertFormFieldIsDisabled('status_aktif')
            ->assertSee(self::PESAN_SENDIRI);
    }

    public function test_muatan_yang_dimodifikasi_pada_akun_sendiri_ditolak_dan_tidak_berubah(): void
    {
        $admin = $this->akun('admin', ['name' => 'Admin Awal']);
        $this->actingAs($admin);

        $uji = $this->sunting($admin)
            ->fillForm(['role' => 'kasubbag', 'status_aktif' => false, 'name' => 'Nama Dari Muatan Palsu'])
            ->call('save');

        $this->pesanDitolak($uji, self::PESAN_SENDIRI);

        $admin->refresh();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue((bool) $admin->status_aktif);
        $this->assertSame('Admin Awal', $admin->name, 'Penyimpanan ditolak seluruhnya.');
    }

    public function test_hanya_peran_atau_hanya_status_yang_dimodifikasi_juga_ditolak(): void
    {
        $admin = $this->akun('admin');
        $this->actingAs($admin);

        $this->sunting($admin)->fillForm(['role' => 'petugas_gudang'])->call('save');
        $this->assertSame('admin', $admin->refresh()->role);

        $this->sunting($admin)->fillForm(['status_aktif' => false])->call('save');
        $this->assertTrue((bool) $admin->refresh()->status_aktif);
    }

    public function test_admin_tetap_dapat_mengubah_data_lain_akun_sendiri(): void
    {
        $admin = $this->akun('admin', ['name' => 'Admin Awal']);
        $this->actingAs($admin);

        $this->sunting($admin)
            ->fillForm(['name' => 'Admin Nama Baru', 'no_hp' => '081200004444'])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertSame('Admin Nama Baru', $admin->name);
        $this->assertSame('081200004444', $admin->no_hp);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue((bool) $admin->status_aktif);
    }

    // =====================================================================
    // AKUN ADMIN LAIN
    // =====================================================================

    public function test_admin_dapat_menonaktifkan_admin_lain_selama_masih_ada_admin_aktif_lain(): void
    {
        $pelaku = $this->akun('admin');
        $lain   = $this->akun('admin');
        $this->actingAs($pelaku);

        $this->sunting($lain)
            ->assertFormFieldIsEnabled('role')
            ->assertFormFieldIsEnabled('status_aktif')
            ->fillForm(['status_aktif' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse((bool) $lain->refresh()->status_aktif);
    }

    public function test_admin_dapat_mengubah_peran_admin_lain_selama_masih_ada_admin_aktif_lain(): void
    {
        $pelaku = $this->akun('admin');
        $lain   = $this->akun('admin');
        $this->actingAs($pelaku);

        $this->sunting($lain)
            ->fillForm(['role' => 'kasubbag'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('kasubbag', $lain->refresh()->role);
    }

    /**
     * Pelaku adalah Admin yang sudah dinonaktifkan tetapi sesinya masih hidup,
     * sehingga satu-satunya Admin aktif adalah akun yang sedang disunting.
     */
    public function test_perubahan_yang_menghabiskan_admin_aktif_ditolak(): void
    {
        $pelaku = $this->akun('admin', ['status_aktif' => false]);
        $satuSatunya = $this->akun('admin');
        $this->actingAs($pelaku);

        $uji = $this->sunting($satuSatunya)->fillForm(['status_aktif' => false])->call('save');
        $this->pesanDitolak($uji, self::PESAN_TERAKHIR);
        $this->assertTrue((bool) $satuSatunya->refresh()->status_aktif);

        $uji = $this->sunting($satuSatunya)->fillForm(['role' => 'kasubbag'])->call('save');
        $this->pesanDitolak($uji, self::PESAN_TERAKHIR);
        $this->assertSame('admin', $satuSatunya->refresh()->role);

        // Perubahan lain pada Admin aktif terakhir tetap boleh.
        $this->sunting($satuSatunya)->fillForm(['name' => 'Admin Terakhir'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin Terakhir', $satuSatunya->refresh()->name);
    }

    // =====================================================================
    // FORM BUAT DAN UBAH PENGGUNA LAIN TETAP BERFUNGSI
    // =====================================================================

    public function test_form_buat_pengguna_tidak_terkunci_dan_berfungsi(): void
    {
        $this->actingAs($this->akun('admin'));

        Livewire::test(CreateUser::class)
            ->assertFormFieldIsEnabled('role')
            ->assertFormFieldIsEnabled('status_aktif')
            ->fillForm([
                'username' => 'kasubbag.baru', 'name' => 'Kasubbag Baru', 'email' => 'kasubbag.baru@bps.go.id',
                'password' => 'sandi-awal-123', 'role' => 'kasubbag', 'status_aktif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('kasubbag', User::where('email', 'kasubbag.baru@bps.go.id')->value('role'));
    }

    public function test_ubah_pengguna_lain_yang_bukan_admin_tetap_berfungsi(): void
    {
        $this->actingAs($this->akun('admin'));
        $tim = $this->buatTim();
        $lain = $this->buatPengguna('tim', $tim, ['email' => 'tim.lain@bps.go.id']);

        $this->sunting($lain)
            ->assertFormFieldIsEnabled('role')
            ->fillForm(['name' => 'Nama Baru Tim', 'role' => 'ketua_tim', 'status_aktif' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $lain->refresh();
        $this->assertSame('Nama Baru Tim', $lain->name);
        $this->assertSame('ketua_tim', $lain->role);
        $this->assertFalse((bool) $lain->status_aktif);
    }

    public function test_konstanta_pesan_sesuai_permintaan(): void
    {
        $this->assertSame(self::PESAN_SENDIRI, UserForm::PESAN_AKUN_SENDIRI);
    }
}
