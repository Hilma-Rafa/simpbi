<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GantiKataSandi;
use App\Filament\Pages\LengkapiAkun;
use App\Filament\Pages\Pengaturan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-008 dan C-004: penggantian kata sandi dan sesi.
 *
 * Sebabnya: AuthenticateSession adalah middleware persisten Livewire, yang
 * berjalan sebelum komponen bekerja. Hash kata sandi di sesi karenanya tetap
 * hash lama begitu komponen menggantinya, dan permintaan berikutnya
 * melemparkan pemiliknya ke halaman masuk. Yang diuji di sini: perangkat
 * yang mengganti tetap masuk, perangkat lain tidak, dan kata sandi yang sama
 * dengan yang sedang berlaku ditolak.
 *
 * Dua sesi terpisah disimulasikan dengan dua permintaan HTTP yang masing-masing
 * membawa hash sandi sesinya sendiri.
 */
class GantiSandiSesiTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const SANDI_BARU = 'sandi-baru-123';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function penggunaLengkap(string $peran = 'kasubbag', array $tambahan = []): User
    {
        return $this->lengkapiAkun($this->buatPengguna($peran, null, $tambahan + ['email' => uniqid($peran . '.') . '@bps.go.id']));
    }

    private function gantiLewatPengaturan(User $pengguna, string $baru = self::SANDI_BARU)
    {
        $this->actingAs($pengguna);

        return Livewire::test(Pengaturan::class)
            ->set('sedangUbahSandi', true)
            ->fillForm([
                'current_password'      => 'password',
                'password'              => $baru,
                'password_confirmation' => $baru,
            ])
            ->call('simpan');
    }

    // =====================================================================
    // (a) PERANGKAT YANG MENGGANTI TETAP MASUK
    // =====================================================================

    public function test_ganti_kata_sandi_wajib_menyimpan_hash_baru_di_sesi_dan_memberi_notifikasi(): void
    {
        $pengguna = $this->penggunaLengkap();
        $pengguna->forceFill(['harus_ganti_sandi' => true])->save();
        $this->actingAs($pengguna->refresh());

        Livewire::test(GantiKataSandi::class)
            ->fillForm(['password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
            ->call('simpan')
            ->assertHasNoFormErrors()
            ->assertNotified('Kata sandi berhasil diubah')
            ->assertRedirect(Dashboard::getUrl());

        $pengguna->refresh();
        $this->assertTrue(Hash::check(self::SANDI_BARU, $pengguna->password));
        $this->assertFalse((bool) $pengguna->harus_ganti_sandi);
        $this->assertSame(
            $pengguna->password,
            session('password_hash_' . Filament::getAuthGuard()),
            'Hash di sesi perangkat ini harus hash kata sandi yang baru.',
        );
    }

    public function test_pengaturan_menyimpan_hash_baru_di_sesi_dan_tetap_di_halaman_dengan_notifikasi(): void
    {
        $pengguna = $this->penggunaLengkap();

        $this->gantiLewatPengaturan($pengguna)
            ->assertHasNoFormErrors()
            ->assertNotified('Pengaturan tersimpan')
            ->assertNoRedirect();

        $pengguna->refresh();
        $this->assertTrue(Hash::check(self::SANDI_BARU, $pengguna->password));
        $this->assertSame($pengguna->password, session('password_hash_' . Filament::getAuthGuard()));
    }

    /** Kolom kata sandi dikosongkan: tidak ada yang berubah, hash sesi tidak disentuh. */
    public function test_pengaturan_tanpa_kata_sandi_baru_tidak_menyentuh_hash_sesi(): void
    {
        $pengguna = $this->penggunaLengkap();
        $this->actingAs($pengguna);
        session(['password_hash_' . Filament::getAuthGuard() => 'hash-sesi-semula']);

        Livewire::test(Pengaturan::class)
            ->fillForm(['no_hp' => '081200003333'])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame('hash-sesi-semula', session('password_hash_' . Filament::getAuthGuard()));
    }

    // =====================================================================
    // (b) KATA SANDI YANG SAMA DENGAN SAAT INI DITOLAK
    // =====================================================================

    public function test_ganti_kata_sandi_wajib_menolak_sandi_yang_sama_dengan_saat_ini(): void
    {
        $pengguna = $this->penggunaLengkap();
        $pengguna->forceFill(['harus_ganti_sandi' => true])->save();
        $this->actingAs($pengguna->refresh());

        $uji = Livewire::test(GantiKataSandi::class)
            ->fillForm(['password' => 'password', 'password_confirmation' => 'password'])
            ->call('simpan')
            ->assertHasFormErrors(['password']);

        $this->assertStringContainsString(
            'Kata sandi baru harus berbeda dari kata sandi saat ini.',
            $uji->errors()->first('data.password'),
        );
        $this->assertTrue((bool) $pengguna->refresh()->harus_ganti_sandi, 'Kewajiban ganti sandi tidak boleh gugur.');
        $this->assertTrue(Hash::check('password', $pengguna->password));
    }

    public function test_pengaturan_menolak_sandi_baru_yang_sama_dengan_saat_ini(): void
    {
        $pengguna = $this->penggunaLengkap();

        $uji = $this->gantiLewatPengaturan($pengguna, 'password')->assertHasFormErrors(['password']);

        $this->assertStringContainsString(
            'Kata sandi baru harus berbeda dari kata sandi saat ini.',
            $uji->errors()->first('data.password'),
        );
        $this->assertTrue(Hash::check('password', $pengguna->refresh()->password));
    }

    // =====================================================================
    // (c) SESI LAIN TIDAK BERLAKU, DI PANEL DAN DI TIGA RUTE UNDUHAN
    // =====================================================================

    /** @return array<string, string> nama => alamat, dengan rekaman yang sudah dibuat */
    private function alamatDiuji(User $pengguna): array
    {
        $tim   = $this->buatTim('Tim Uji Sesi');
        $bast  = $this->buatBast($tim, $this->buatTim('Tim Tujuan Sesi'), $pengguna);
        $perm  = $this->buatPermintaan($tim, $pengguna, [['barang' => $this->buatBarang(), 'diminta' => 1]]);

        return [
            'panel (Pengaturan)'   => '/admin/pengaturan',
            'unduh BAST'           => '/dokumen-bast/' . $bast->getKey(),
            'unduh bukti'          => '/bukti-permintaan/' . $perm->getKey(),
            'unduh panduan'        => '/pusat-bantuan/panduan',
        ];
    }

    private function bukaDenganSesi(User $pengguna, string $hashSesi, string $alamat)
    {
        $this->flushSession();
        auth()->forgetGuards();

        return $this->withSession(['password_hash_' . Filament::getAuthGuard() => $hashSesi])
            ->actingAs($pengguna)
            ->get($alamat);
    }

    public function test_sesi_lain_tidak_berlaku_sedangkan_sesi_yang_mengganti_tetap_sah(): void
    {
        $pengguna = $this->penggunaLengkap();
        $hashLama = $pengguna->password;
        $alamat   = $this->alamatDiuji($pengguna);

        // Sesi A mengganti kata sandi.
        $this->gantiLewatPengaturan($pengguna)->assertHasNoFormErrors();
        $pengguna->refresh();
        $hashSesiA = session('password_hash_' . Filament::getAuthGuard());
        $this->assertSame($pengguna->password, $hashSesiA);
        $this->assertNotSame($hashLama, $pengguna->password);

        $masuk = route('filament.admin.auth.login');

        foreach ($alamat as $nama => $url) {
            // Sesi B: masih membawa hash lama.
            $this->bukaDenganSesi($pengguna, $hashLama, $url)
                ->assertRedirect($masuk);

            // Sesi A: membawa hash baru, tetap sah (200, atau 403/404 dari rutenya sendiri, tetapi bukan pengalihan ke halaman masuk).
            $tanggapan = $this->bukaDenganSesi($pengguna, $hashSesiA, $url);
            $this->assertFalse(
                $tanggapan->isRedirect() && str_contains((string) $tanggapan->headers->get('Location'), '/login'),
                "Sesi yang mengganti kata sandi tidak boleh dialihkan ke halaman masuk pada {$nama}.",
            );
        }
    }

    /**
     * C-004 berdiri sendiri: kata sandi diganti dari perangkat lain (langsung
     * di basis data, tanpa Livewire), lalu sesi ini membawa hash lama.
     */
    public function test_tiga_rute_unduhan_memutus_sesi_yang_hash_sandinya_usang(): void
    {
        $pengguna = $this->penggunaLengkap();
        $hashLama = $pengguna->password;
        $unduhan  = collect($this->alamatDiuji($pengguna))->except('panel (Pengaturan)');
        $this->assertCount(3, $unduhan);

        $pengguna->forceFill(['password' => 'sandi-diganti-di-perangkat-lain'])->save();
        $hashBaru = $pengguna->refresh()->password;

        foreach ($unduhan as $nama => $url) {
            $this->bukaDenganSesi($pengguna, $hashLama, $url)->assertRedirect(route('filament.admin.auth.login'));

            $tanggapan = $this->bukaDenganSesi($pengguna, $hashBaru, $url);
            $this->assertFalse(
                $tanggapan->isRedirect() && str_contains((string) $tanggapan->headers->get('Location'), '/login'),
                "Sesi dengan hash yang sesuai tidak boleh dialihkan ke halaman masuk pada {$nama}.",
            );
        }
    }

    // =====================================================================
    // (d) ALUR WAJIB GANTI SANDI LANJUT KE TUJUAN NORMAL
    // =====================================================================

    public function test_setelah_ganti_sandi_wajib_akun_lengkap_lanjut_ke_dasbor(): void
    {
        $pengguna = $this->penggunaLengkap();
        $pengguna->forceFill(['harus_ganti_sandi' => true])->save();
        $this->actingAs($pengguna->refresh());

        Livewire::test(GantiKataSandi::class)
            ->fillForm(['password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
            ->call('simpan')
            ->assertRedirect(Dashboard::getUrl());

        // Permintaan berikutnya dari sesi yang sama: tetap masuk, tidak ditahan gerbang sandi.
        auth()->forgetGuards();
        $this->actingAs($pengguna->refresh())->get(Dashboard::getUrl())->assertOk();
    }

    public function test_setelah_ganti_sandi_wajib_akun_belum_lengkap_lanjut_ke_lengkapi_akun(): void
    {
        $pengguna = $this->penggunaLengkap('petugas_gudang');
        $pengguna->forceFill(['harus_ganti_sandi' => true, 'no_hp' => null])->save();
        $this->actingAs($pengguna->refresh());

        Livewire::test(GantiKataSandi::class)
            ->fillForm(['password' => self::SANDI_BARU, 'password_confirmation' => self::SANDI_BARU])
            ->call('simpan')
            ->assertRedirect(Dashboard::getUrl());

        auth()->forgetGuards();
        $this->actingAs($pengguna->refresh())
            ->get(Dashboard::getUrl())
            ->assertRedirect(LengkapiAkun::getUrl());
    }
}
