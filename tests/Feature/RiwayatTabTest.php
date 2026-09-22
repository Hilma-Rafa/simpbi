<?php

namespace Tests\Feature;

use App\Filament\Pages\KartuKendali;
use App\Filament\Pages\Riwayat;
use App\Filament\Widgets\BarangPerluPerhatian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Perpindahan jenis pada halaman Riwayat, dan tujuan tautan panel dasbor.
 *
 * Keduanya cacat yang tidak menimbulkan galat apa pun sehingga mudah lolos:
 * tab yang baru berpindah pada klik kedua tampak seperti aplikasi yang lambat,
 * dan tautan panel yang menuju halaman tertutup baru ketahuan ketika
 * diklik oleh peran yang memang dituju.
 */
class RiwayatTabTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /**
     * Satu klik harus cukup.
     *
     * Filament menyusun tabelnya pada tahap `booted`, yang berjalan sebelum
     * aksi Livewire dipanggil. Tanpa penyusunan ulang di dalam pilihJenis(),
     * tabel yang dirender setelah penggantian masih tabel jenis sebelumnya.
     */
    public function test_satu_klik_cukup_untuk_berpindah_jenis(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        Livewire::test(Riwayat::class, ['jenis' => 'permintaan'])
            ->assertSet('jenis', 'permintaan')
            ->call('pilihJenis', 'mutasi_stok')
            ->assertSet('jenis', 'mutasi_stok')
            // Kolom "Nomor Dasar" hanya ada pada tabel mutasi stok; kalau
            // tabelnya belum berganti, yang terlihat masih kolom permintaan.
            ->assertSee('Nomor Dasar')
            ->assertDontSee('Tim Pemohon');
    }

    public function test_berpindah_kembali_juga_cukup_satu_klik(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        Livewire::test(Riwayat::class, ['jenis' => 'mutasi_stok'])
            ->call('pilihJenis', 'permintaan')
            ->assertSee('Tim Pemohon')
            ->assertDontSee('Nomor Dasar');
    }

    public function test_jenis_di_luar_kewenangan_diabaikan(): void
    {
        $tim = $this->buatTim();
        $this->actingAs($this->buatPengguna('tim', $tim));

        $komponen = Livewire::test(Riwayat::class);
        $awal = $komponen->get('jenis');

        $komponen->call('pilihJenis', 'notifikasi')
            ->assertSet('jenis', $awal);
    }

    /**
     * Panel hanya tampil bagi Petugas Gudang, sehingga tautannya wajib menuju
     * halaman yang terbuka bagi peran itu. Sebelumnya ia menuju daftar Barang
     * Persediaan, yang tertutup bagi Petugas Gudang — satu-satunya peran yang
     * melihat panel itu adalah satu-satunya yang ditolak ketika mengkliknya.
     */
    public function test_tautan_panel_menuju_halaman_yang_terbuka_bagi_gudang(): void
    {
        $gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $this->actingAs($gudang);

        $this->assertTrue(BarangPerluPerhatian::canView());
        $this->assertTrue(KartuKendali::canAccess());

        $tautan = Livewire::test(BarangPerluPerhatian::class)->instance()->tautanSemua;

        $this->assertSame(KartuKendali::getUrl(), $tautan);

        // Dan alamatnya benar-benar dapat dibuka, bukan sekadar terbentuk.
        $this->get($tautan)->assertOk();
    }
}
