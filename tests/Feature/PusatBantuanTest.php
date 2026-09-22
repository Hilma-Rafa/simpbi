<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PusatBantuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Halaman Pusat Bantuan (panduan penggunaan + kontak Sub-Bagian Umum).
 *
 * Halaman ini terbuka bagi seluruh peran tanpa pembatasan tambahan — sama
 * seperti Pengaturan — sehingga yang paling penting diuji di sini adalah dua
 * hal yang sumber datanya berasal dari luar kode: ketersediaan berkas panduan
 * di filesystem, dan nomor WhatsApp bantuan dari tabel pengaturan.
 */
class PusatBantuanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // =====================================================================
    // AKSES
    // =====================================================================

    public function test_seluruh_peran_dapat_membuka_pusat_bantuan(): void
    {
        foreach (['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $peran) {
            // Sesi dibersihkan tiap putaran: AuthenticateSession mengeluarkan
            // pengguna begitu sidik kata sandi pada sesi tidak lagi cocok,
            // sehingga berganti akun tanpa membersihkan sesi berakhir pada
            // pengalihan ke halaman masuk (lihat AksesPanelTest).
            $this->flushSession();

            $pengguna = $this->lengkapiAkun($this->buatPengguna($peran, in_array($peran, ['ketua_tim', 'tim']) ? $this->buatTim() : null));
            $this->actingAs($pengguna);

            $this->get(PusatBantuan::getUrl())->assertOk();
        }
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(PusatBantuan::getUrl())->assertRedirect();
    }

    public function test_tidak_ada_item_baru_di_menu_samping(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $this->assertFalse(PusatBantuan::shouldRegisterNavigation());
    }

    // =====================================================================
    // PANDUAN PENGGUNAAN
    // =====================================================================

    public function test_panduan_tersedia_menampilkan_tombol_unduh_dan_badge(): void
    {
        Storage::disk('local')->put('panduan/Panduan-Penggunaan-SIMPBI.pdf', str_repeat('a', 1024 * 1024 * 2));

        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));

        Livewire::test(PusatBantuan::class)
            ->assertSee('Unduh Panduan')
            ->assertSee('PDF')
            ->assertDontSee('Panduan belum tersedia');
    }

    public function test_panduan_tidak_ada_menampilkan_keadaan_kosong_tanpa_tombol(): void
    {
        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));

        Livewire::test(PusatBantuan::class)
            ->assertSee('Panduan belum tersedia. Silakan hubungi Sub-Bagian Umum.')
            ->assertDontSee('Unduh Panduan');
    }

    public function test_unduh_panduan_mengalirkan_berkas_bila_ada(): void
    {
        Storage::disk('local')->put('panduan/Panduan-Penggunaan-SIMPBI.pdf', 'isi-panduan');

        $this->actingAs($this->buatPengguna('admin'));

        $this->get(route('pusat-bantuan.unduh-panduan'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_unduh_panduan_404_bila_berkas_tidak_ada(): void
    {
        $this->actingAs($this->buatPengguna('admin'));

        $this->get(route('pusat-bantuan.unduh-panduan'))->assertNotFound();
    }

    /**
     * Rute ini memakai middleware 'auth' bawaan Laravel — pola yang sama
     * persis dipakai bast.unduh dan bukti.unduh yang sudah ada — sehingga
     * tamu memang tidak pernah sampai mengunduh berkasnya. Perilaku
     * tepatnya (RouteNotFoundException, bukan redirect mulus ke halaman
     * masuk) berasal dari middleware bawaan itu sendiri yang mencari rute
     * bernama "login" yang belum terdaftar di aplikasi ini — panel Filament
     * memakai rute masuknya sendiri, bukan rute "login" generik. Ini celah
     * pra-ada pada ketiga rute unduh tersebut, di luar cakupan pembenahan
     * ini; dilaporkan pada ringkasan pekerjaan, bukan diperbaiki diam-diam
     * di sini karena akan menyentuh mekanisme yang dipakai bersama.
     */
    public function test_unduh_panduan_menolak_tamu(): void
    {
        $status = $this->get(route('pusat-bantuan.unduh-panduan'))->getStatusCode();

        $this->assertNotSame(200, $status, 'Tamu tidak boleh berhasil mengunduh panduan.');
    }

    // =====================================================================
    // KONTAK WHATSAPP
    // =====================================================================

    public function test_nomor_whatsapp_terisi_menampilkan_tombol_chat(): void
    {
        DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->update(['nilai' => '6281238096104']);

        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));

        Livewire::test(PusatBantuan::class)
            ->assertSee('+62 812-3809-6104')
            ->assertSee('Chat Sekarang')
            ->assertDontSee('Nomor WhatsApp belum diatur');
    }

    public function test_nomor_whatsapp_kosong_menampilkan_keterangan_tanpa_tombol(): void
    {
        DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->update(['nilai' => '']);

        $this->actingAs($this->buatPengguna('tim', $this->buatTim()));

        Livewire::test(PusatBantuan::class)
            ->assertSee('Nomor WhatsApp belum diatur oleh Administrator.')
            ->assertDontSee('Chat Sekarang');
    }

    public function test_tautan_whatsapp_tanpa_pesan_otomatis(): void
    {
        DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->update(['nilai' => '6281238096104']);

        $this->actingAs($this->buatPengguna('admin'));

        $tautan = Livewire::test(PusatBantuan::class)->instance()->tautanWhatsApp();

        $this->assertSame('https://wa.me/6281238096104', $tautan);
    }

    public function test_mengubah_nomor_di_pengaturan_tercermin_di_pusat_bantuan(): void
    {
        DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->update(['nilai' => '6281111111111']);
        $this->actingAs($this->buatPengguna('admin'));
        $this->assertSame('+62 811-1111-1111', Livewire::test(PusatBantuan::class)->instance()->nomorWhatsAppTampilan());

        DB::table('pengaturan')->where('kunci', 'kontak_bantuan_wa')->update(['nilai' => '6282222222222']);
        $this->assertSame('+62 822-2222-2222', Livewire::test(PusatBantuan::class)->instance()->nomorWhatsAppTampilan());
    }

    // =====================================================================
    // KONTAK EMAIL & JAM OPERASIONAL
    // =====================================================================

    public function test_email_dan_jam_operasional_dari_konfigurasi(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag'));

        $halaman = Livewire::test(PusatBantuan::class);

        $halaman->assertSee('sub-bagianumum@bps.go.id')
            ->assertSee('Senin – Kamis')
            ->assertSee('08.00 – 16.00')
            ->assertSee('Jumat')
            ->assertSee('08.00 – 16.30')
            ->assertSee('Sabtu – Minggu')
            ->assertSee('Libur');
    }

    // =====================================================================
    // DROPDOWN PROFIL
    // =====================================================================

    public function test_item_pusat_bantuan_tampil_di_dropdown_untuk_semua_peran(): void
    {
        foreach (['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $peran) {
            $this->flushSession();

            $pengguna = $this->lengkapiAkun($this->buatPengguna($peran, in_array($peran, ['ketua_tim', 'tim']) ? $this->buatTim() : null));
            $this->actingAs($pengguna);

            $this->get(Dashboard::getUrl())
                ->assertOk()
                ->assertSee('Pusat Bantuan')
                ->assertSee('PANDUAN & KONTAK');
        }
    }
}
