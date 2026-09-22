<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * G-001: hapus akun.
 *
 * Admin tidak menghapus akunnya sendiri, dan penghapusan tidak boleh
 * menyisakan nol Admin aktif. Dijaga di server, pada aksi tunggal, hapus
 * massal, dan jalur langsung (model). Hapus massal ditolak seluruhnya, bukan
 * sebagian.
 */
class HapusAkunTerjagaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN_SENDIRI = 'Anda tidak dapat menghapus akun Anda sendiri.';

    private const PESAN_TERAKHIR = 'Harus ada minimal satu Admin aktif.';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function akun(string $peran, array $tambahan = []): User
    {
        return $this->buatPengguna($peran, null, $tambahan + ['email' => uniqid($peran . '.') . '@bps.go.id']);
    }

    private function hapusDariHalamanUbah(User $akun)
    {
        return Livewire::test(EditUser::class, ['record' => $akun->getRouteKey()])->callAction(DeleteAction::class);
    }

    private function ditolak(string $pesan): void
    {
        Notification::assertNotified(
            Notification::make()->danger()->title('Tidak dapat dihapus')->body($pesan)->persistent()
        );
    }

    // =====================================================================
    // AKUN SENDIRI
    // =====================================================================

    public function test_admin_tidak_dapat_menghapus_akun_sendiri_walau_ada_admin_aktif_lain(): void
    {
        $admin = $this->akun('admin');
        $this->akun('admin');
        $this->actingAs($admin);

        $this->hapusDariHalamanUbah($admin);

        $this->ditolak(self::PESAN_SENDIRI);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_hapus_massal_berisi_akun_sendiri_ditolak_seluruhnya(): void
    {
        $admin = $this->akun('admin');
        $lain  = $this->akun('admin');
        $biasa = $this->akun('kasubbag');
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)->callTableBulkAction('delete', [$admin, $lain, $biasa]);

        $this->ditolak(self::PESAN_SENDIRI);
        foreach ([$admin, $lain, $biasa] as $akun) {
            $this->assertDatabaseHas('users', ['id' => $akun->id]);
        }
    }

    /** Pengguna non-Admin pun tidak menghapus akunnya sendiri lewat jalur langsung. */
    public function test_jalur_langsung_menolak_penghapusan_akun_yang_sedang_masuk(): void
    {
        $this->akun('admin');
        $kasubbag = $this->akun('kasubbag');
        $this->actingAs($kasubbag);

        $this->assertFalse($kasubbag->delete());
        $this->assertDatabaseHas('users', ['id' => $kasubbag->id]);
    }

    // =====================================================================
    // ADMIN AKTIF TERAKHIR
    // =====================================================================

    public function test_admin_dapat_menghapus_admin_lain_selama_masih_ada_admin_aktif_lain(): void
    {
        $pelaku = $this->akun('admin');
        $lain   = $this->akun('admin');
        $this->actingAs($pelaku);

        $this->hapusDariHalamanUbah($lain);

        $this->assertDatabaseMissing('users', ['id' => $lain->id]);
        $this->assertDatabaseHas('users', ['id' => $pelaku->id]);
    }

    public function test_hapus_massal_beberapa_admin_lain_berhasil_selama_pelaku_tetap_ada(): void
    {
        $pelaku = $this->akun('admin');
        $b = $this->akun('admin');
        $c = $this->akun('admin');
        $this->actingAs($pelaku);

        Livewire::test(ListUsers::class)->callTableBulkAction('delete', [$b, $c]);

        $this->assertDatabaseMissing('users', ['id' => $b->id]);
        $this->assertDatabaseMissing('users', ['id' => $c->id]);
        $this->assertDatabaseHas('users', ['id' => $pelaku->id]);
    }

    /** Pelaku Admin nonaktif (sesi masih hidup) menghapus satu-satunya Admin aktif. */
    public function test_satu_satunya_admin_aktif_tidak_dapat_dihapus_oleh_pelaku_nonaktif(): void
    {
        $pelaku      = $this->akun('admin', ['status_aktif' => false]);
        $satuSatunya = $this->akun('admin');
        $this->actingAs($pelaku);

        $this->hapusDariHalamanUbah($satuSatunya);

        $this->ditolak(self::PESAN_TERAKHIR);
        $this->assertDatabaseHas('users', ['id' => $satuSatunya->id]);
    }

    /** Jalur langsung (model), tanpa aksi Filament, oleh pelaku non-Admin. */
    public function test_jalur_langsung_menolak_penghapusan_satu_satunya_admin_aktif(): void
    {
        $satuSatunya = $this->akun('admin');
        $this->actingAs($this->akun('kasubbag'));

        $this->assertFalse($satuSatunya->delete());
        $this->assertDatabaseHas('users', ['id' => $satuSatunya->id]);

        // Admin nonaktif tidak dihitung: menghapusnya tidak mengurangi Admin aktif.
        $nonaktif = $this->akun('admin', ['status_aktif' => false]);
        $this->assertTrue($nonaktif->delete());
    }

    public function test_hapus_massal_yang_menghabiskan_admin_aktif_ditolak_seluruhnya(): void
    {
        $pelaku = $this->akun('admin', ['status_aktif' => false]);
        $b      = $this->akun('admin');
        $c      = $this->akun('admin');
        $biasa  = $this->akun('kasubbag');
        $this->actingAs($pelaku);

        Livewire::test(ListUsers::class)->callTableBulkAction('delete', [$b, $c, $biasa]);

        $this->ditolak(self::PESAN_TERAKHIR);
        foreach ([$b, $c, $biasa] as $akun) {
            $this->assertDatabaseHas('users', ['id' => $akun->id]);
        }

        // Menyisakan satu Admin aktif diperbolehkan.
        Livewire::test(ListUsers::class)->callTableBulkAction('delete', [$b, $biasa]);

        $this->assertDatabaseMissing('users', ['id' => $b->id]);
        $this->assertDatabaseMissing('users', ['id' => $biasa->id]);
        $this->assertDatabaseHas('users', ['id' => $c->id]);
    }

    // =====================================================================
    // PERLINDUNGAN LAMA DAN PENGGUNA BIASA TIDAK BERUBAH
    // =====================================================================

    public function test_pengguna_biasa_tetap_dapat_dihapus_tunggal_dan_massal(): void
    {
        $this->actingAs($this->akun('admin'));
        $satu = $this->akun('kasubbag');
        $dua  = $this->akun('petugas_gudang');
        $tiga = $this->akun('kasubbag');

        $this->hapusDariHalamanUbah($satu);
        $this->assertDatabaseMissing('users', ['id' => $satu->id]);

        Livewire::test(ListUsers::class)->callTableBulkAction('delete', [$dua, $tiga]);
        $this->assertDatabaseMissing('users', ['id' => $dua->id]);
        $this->assertDatabaseMissing('users', ['id' => $tiga->id]);
    }

    public function test_pengguna_yang_punya_riwayat_tetap_dilindungi_berdampingan_dengan_penjaga_baru(): void
    {
        $this->actingAs($this->akun('admin'));
        $tim = $this->buatTim();
        $berriwayat = $this->buatPengguna('tim', $tim, ['email' => 'riwayat@bps.go.id']);
        $this->buatPermintaan($tim, $berriwayat, [['barang' => $this->buatBarang(), 'diminta' => 1]]);

        $this->hapusDariHalamanUbah($berriwayat);

        $this->assertDatabaseHas('users', ['id' => $berriwayat->id]);
    }
}
