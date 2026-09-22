<?php

namespace Tests\Feature;

use App\Filament\Resources\BarangPersediaans\Pages\CreateBarangPersediaan;
use App\Filament\Resources\Kategoris\Pages\CreateKategori;
use App\Filament\Resources\Tims\Pages\CreateTim;
use App\Filament\Resources\Users\Pages\CreateUser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-009: pesan validasi bawaan Laravel berbahasa Indonesia.
 *
 * `lang/id/validation.php` menerjemahkan seluruh kunci berkas bawaan dengan
 * penanda tempat yang sama. Yang diuji bukan aturan validasinya (tidak
 * berubah), melainkan bahasa pesannya pada form-form kunci.
 */
class PesanValidasiIndonesiaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bahasa dipasang tegas: berkas .env lain dapat memakai locale `en`.
        app()->setLocale('id');
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.bahasa@bps.go.id']));
    }

    private function pesan($uji, string $kunci): string
    {
        return (string) $uji->errors()->first('data.' . $kunci);
    }

    private function harusIndonesia(string $pesan, string $potongan): void
    {
        $this->assertNotSame('', $pesan, 'Kesalahan validasi yang diharapkan tidak muncul.');
        $this->assertStringContainsString($potongan, $pesan);
        $this->assertStringNotContainsString('field is required', $pesan);
        $this->assertDoesNotMatchRegularExpression('/\b(The|must|has already been taken)\b/', $pesan);
    }

    public function test_form_pengguna_menampilkan_pesan_wajib_diisi_dan_email_dalam_bahasa_indonesia(): void
    {
        $uji = Livewire::test(CreateUser::class)->call('create');

        foreach (['name', 'username', 'email', 'password', 'role'] as $kolom) {
            $this->harusIndonesia($this->pesan($uji, $kolom), 'wajib diisi');
        }

        $uji = Livewire::test(CreateUser::class)
            ->fillForm(['username' => 'abc', 'name' => 'Nama', 'email' => 'bukan-email', 'password' => 'sandi-awal-123', 'role' => 'kasubbag'])
            ->call('create');

        $this->harusIndonesia($this->pesan($uji, 'email'), 'alamat email yang valid');
    }

    public function test_email_yang_sudah_terdaftar_dilaporkan_dalam_bahasa_indonesia(): void
    {
        $uji = Livewire::test(CreateUser::class)
            ->fillForm(['username' => 'baru', 'name' => 'Nama', 'email' => 'admin.bahasa@bps.go.id', 'password' => 'sandi-awal-123', 'role' => 'kasubbag'])
            ->call('create');

        $this->harusIndonesia($this->pesan($uji, 'email'), 'sudah digunakan');
    }

    public function test_form_tim_kerja_menampilkan_pesan_wajib_diisi_dalam_bahasa_indonesia(): void
    {
        $uji = Livewire::test(CreateTim::class)->call('create');

        $this->harusIndonesia($this->pesan($uji, 'nama_tim'), 'wajib diisi');
    }

    public function test_form_kategori_menampilkan_pesan_wajib_diisi_dalam_bahasa_indonesia(): void
    {
        $uji = Livewire::test(CreateKategori::class)->call('create');

        $this->assertTrue($uji->errors()->isNotEmpty(), 'Form Kategori kosong harus gagal validasi.');

        foreach ($uji->errors()->all() as $pesan) {
            $this->harusIndonesia($pesan, 'wajib diisi');
        }
    }

    public function test_form_barang_persediaan_menampilkan_pesan_dalam_bahasa_indonesia(): void
    {
        $uji = Livewire::test(CreateBarangPersediaan::class)->call('create');

        $this->assertTrue($uji->errors()->isNotEmpty(), 'Form Barang Persediaan kosong harus gagal validasi.');

        foreach ($uji->errors()->all() as $pesan) {
            $this->harusIndonesia($pesan, 'wajib diisi');
        }
    }

    public function test_batas_panjang_dan_angka_bawaan_laravel_memakai_terjemahan(): void
    {
        $this->assertSame('Kolom nama wajib diisi.', __('validation.required', ['attribute' => 'nama']));
        $this->assertSame('Kolom nama tidak boleh terdiri atas lebih dari 5 karakter.', __('validation.max.string', ['attribute' => 'nama', 'max' => 5]));
        $this->assertSame('Kolom jumlah harus bernilai sekurang-kurangnya 1.', __('validation.min.numeric', ['attribute' => 'jumlah', 'min' => 1]));
        $this->assertSame('Kolom email harus berupa alamat email yang valid.', __('validation.email', ['attribute' => 'email']));
        $this->assertSame('email sudah digunakan.', __('validation.unique', ['attribute' => 'email']));
    }

    public function test_seluruh_kunci_bawaan_punya_terjemahan_dengan_penanda_tempat_yang_sama(): void
    {
        $bawaan = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $id     = require lang_path('id/validation.php');

        $rata = function (array $a) use (&$rata): array {
            $hasil = [];
            foreach ($a as $k => $v) {
                foreach (is_array($v) ? $rata($v) : ['' => $v] as $kk => $vv) {
                    $hasil[$kk === '' ? $k : "$k.$kk"] = $vv;
                }
            }

            return $hasil;
        };

        $kunci = fn (array $a) => array_filter(array_keys($rata($a)), fn ($k) => ! str_starts_with($k, 'custom') && ! str_starts_with($k, 'attributes'));

        $this->assertSame([], array_values(array_diff($kunci($bawaan), $kunci($id))), 'Ada kunci bawaan yang belum diterjemahkan.');

        foreach ($kunci($bawaan) as $k) {
            preg_match_all('/:\w+/', $rata($bawaan)[$k], $asli);
            preg_match_all('/:\w+/', $rata($id)[$k], $terjemah);
            sort($asli[0]);
            sort($terjemah[0]);

            $this->assertSame($asli[0], $terjemah[0], "Penanda tempat pada kunci {$k} harus sama dengan berkas bawaan.");
        }
    }
}
