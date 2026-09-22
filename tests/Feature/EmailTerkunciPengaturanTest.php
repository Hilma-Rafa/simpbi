<?php

namespace Tests\Feature;

use App\Filament\Pages\Pengaturan;
use App\Filament\Resources\Users\Pages\EditUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * G-005: email dikunci pada Pengaturan.
 *
 * Email adalah kredensial masuk. Seperti nama dan NIP (A-015), hanya
 * Administrator yang mengubahnya, lewat menu Pengguna. Nomor WhatsApp,
 * tanda tangan, dan ubah kata sandi tidak berubah.
 */
class EmailTerkunciPengaturanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const KETERANGAN = 'Diubah oleh Administrator melalui menu Pengguna.';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** @return array<string, bool> peran => butuh tim */
    private function semuaPeran(): array
    {
        return ['admin' => false, 'kasubbag' => false, 'petugas_gudang' => false, 'ketua_tim' => true, 'tim' => true];
    }

    public function test_email_tampil_tetapi_terkunci_untuk_semua_peran_termasuk_admin(): void
    {
        foreach ($this->semuaPeran() as $peran => $butuhTim) {
            $pengguna = $this->buatPengguna($peran, $butuhTim ? $this->buatTim('Tim ' . $peran) : null, ['email' => $peran . '.terkunci@bps.go.id']);
            $this->actingAs($pengguna);

            Livewire::test(Pengaturan::class)
                ->assertFormFieldIsDisabled('email')
                ->assertFormSet(['email' => $peran . '.terkunci@bps.go.id'])
                ->assertSee(self::KETERANGAN);
        }
    }

    public function test_muatan_yang_dimodifikasi_tidak_mengubah_email_untuk_setiap_peran(): void
    {
        foreach ($this->semuaPeran() as $peran => $butuhTim) {
            $surel = $peran . '.tetap@bps.go.id';
            $pengguna = $this->buatPengguna($peran, $butuhTim ? $this->buatTim('Tim ' . $peran) : null, ['email' => $surel]);
            $this->actingAs($pengguna);

            Livewire::test(Pengaturan::class)
                ->fillForm(['email' => 'peretas@luar.com', 'no_hp' => '081277778888'])
                ->call('simpan')
                ->assertHasNoFormErrors();

            $pengguna->refresh();
            $this->assertSame($surel, $pengguna->email, "Email {$peran} tidak boleh berubah.");
            $this->assertSame('081277778888', $pengguna->no_hp, 'Nomor WhatsApp tetap dapat disimpan.');
        }
    }

    /** Muatan email dikosongkan pun tidak menggagalkan penyimpanan bagian lain. */
    public function test_muatan_email_kosong_diabaikan(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.tetap@bps.go.id']);
        $this->actingAs($pengguna);

        Livewire::test(Pengaturan::class)
            ->fillForm(['email' => '', 'no_hp' => '081266665555'])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame('kasubbag.tetap@bps.go.id', $pengguna->refresh()->email);
        $this->assertSame('081266665555', $pengguna->no_hp);
    }

    public function test_admin_mengubah_email_lewat_form_pengguna_tampil_pada_pengaturan_dan_dipakai_masuk(): void
    {
        $admin  = $this->buatPengguna('admin', null, ['email' => 'admin.email@bps.go.id']);
        $target = $this->buatPengguna('kasubbag', null, ['email' => 'lama@bps.go.id']);

        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['email' => 'baru@bps.go.id'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->actingAs($target->refresh());
        Livewire::test(Pengaturan::class)->assertFormSet(['email' => 'baru@bps.go.id']);

        $guard = auth()->guard('web');
        $this->assertTrue($guard->validate(['email' => 'baru@bps.go.id', 'password' => 'password']), 'Email baru dipakai untuk masuk.');
        $this->assertFalse($guard->validate(['email' => 'lama@bps.go.id', 'password' => 'password']), 'Email lama tidak lagi berlaku.');
    }
}
