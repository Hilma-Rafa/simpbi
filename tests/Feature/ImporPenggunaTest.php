<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GantiKataSandi;
use App\Models\Tim;
use App\Models\User;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporPengguna;
use App\Services\Impor\PembuatTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Impor daftar pengguna, kata sandi awalnya, dan pemaksaan penggantiannya.
 *
 * Dua hal yang paling menentukan di sini bersifat keamanan, bukan kenyamanan:
 * kata sandi seragam hasil impor tidak boleh bertahan melewati kesempatan
 * masuk pertama, dan impor ulang tidak boleh menyetel ulang kata sandi akun
 * yang sudah dipakai orang.
 */
class ImporPenggunaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** @param list<list<string>> $baris */
    private function impor(array $baris): HasilImpor
    {
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_impor_') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($lintasan);
        foreach ($baris as $b) {
            $writer->addRow(Row::fromValues($b));
        }
        $writer->close();

        $hasil = app(ImporPengguna::class)->jalankan($lintasan);
        @unlink($lintasan);

        return $hasil;
    }

    private const TAJUK = [
        'Username *', 'Nama Lengkap *', 'Peran *', 'Tim Kerja',
        'Email', 'NIP', 'Nomor WhatsApp', 'Aktif',
    ];

    // =====================================================================
    // JALUR NORMAL
    // =====================================================================

    public function test_akun_baru_lahir_dengan_sandi_awal_dan_wajib_ganti(): void
    {
        Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);

        $hasil = $this->impor([
            self::TAJUK,
            ['wanda.pribadi', 'Wanda Pribadi', 'Ketua Tim', 'Statistik Sosial',
                'wanda@bps.go.id', '199001012015011001', '081234567890', 'Ya'],
        ]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertFalse($hasil->adaGalat());

        $pengguna = User::where('username', 'wanda.pribadi')->sole();

        $this->assertTrue(Hash::check(config('impor.sandi_awal'), $pengguna->password));
        $this->assertTrue((bool) $pengguna->harus_ganti_sandi);
        $this->assertSame('ketua_tim', $pengguna->role);
        // Nomor diseragamkan seperti ketika diketik lewat halaman Pengaturan.
        $this->assertSame('6281234567890', $pengguna->no_hp);
    }

    /** Pengguna berperan Ketua Tim sekaligus ditetapkan sebagai ketua timnya. */
    public function test_ketua_tim_langsung_terpasang_pada_timnya(): void
    {
        $tim = Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);
        $this->assertNull($tim->ketua_tim_id);

        $this->impor([
            self::TAJUK,
            ['wanda.pribadi', 'Wanda Pribadi', 'Ketua Tim', 'Statistik Sosial', 'wanda.pribadi@bps.go.id', '', '', 'Ya'],
        ]);

        $this->assertSame(
            User::where('username', 'wanda.pribadi')->value('id'),
            $tim->refresh()->ketua_tim_id,
        );
    }

    public function test_peran_boleh_ditulis_sebagai_label_maupun_nilainya(): void
    {
        $hasil = $this->impor([
            self::TAJUK,
            ['gudang1', 'Petugas Satu', 'Petugas Gudang', '', 'gudang1@bps.go.id', '', '', 'Ya'],
            ['gudang2', 'Petugas Dua', 'petugas_gudang', '', 'gudang2@bps.go.id', '', '', 'Ya'],
        ]);

        $this->assertSame(2, $hasil->ditambah);
        $this->assertSame(
            ['petugas_gudang', 'petugas_gudang'],
            User::whereIn('username', ['gudang1', 'gudang2'])->orderBy('username')->pluck('role')->all(),
        );
    }

    // =====================================================================
    // IMPOR ULANG
    // =====================================================================

    /**
     * Berkas impor kerap diunggah ulang setelah diperbaiki. Menyetel ulang
     * kata sandi diam-diam akan mengunci seluruh pengguna dari akunnya sendiri
     * tanpa ada yang menduga penyebabnya.
     */
    public function test_impor_ulang_tidak_menyentuh_kata_sandi(): void
    {
        $tim = Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);

        $pengguna = User::create([
            'username'          => 'wanda.pribadi',
            'name'              => 'Wanda',
            'email'             => 'wanda.pribadi@bps.go.id',
            'role'              => 'tim',
            'tim_id'            => $tim->id,
            'password'          => 'SandiPilihanSendiri1',
            'harus_ganti_sandi' => false,
            'status_aktif'      => true,
        ]);

        $hasil = $this->impor([
            self::TAJUK,
            ['wanda.pribadi', 'Wanda Pribadi', 'Ketua Tim', 'Statistik Sosial', 'wanda.pribadi@bps.go.id', '', '', 'Ya'],
        ]);

        $pengguna->refresh();

        $this->assertSame(0, $hasil->ditambah);
        $this->assertSame(1, $hasil->diperbarui);

        // Data diri dan penempatan diperbarui...
        $this->assertSame('Wanda Pribadi', $pengguna->name);
        $this->assertSame('ketua_tim', $pengguna->role);

        // ...tetapi kata sandinya tetap milik pemiliknya.
        $this->assertTrue(Hash::check('SandiPilihanSendiri1', $pengguna->password));
        $this->assertFalse((bool) $pengguna->harus_ganti_sandi);
    }

    // =====================================================================
    // BARIS YANG DITOLAK
    // =====================================================================

    public function test_peran_bertim_wajib_disertai_tim_kerja(): void
    {
        $hasil = $this->impor([
            self::TAJUK,
            ['tanpa.tim', 'Tanpa Tim', 'Ketua Tim', '', '', '', '', 'Ya'],
        ]);

        $this->assertSame(0, User::count());
        $this->assertStringContainsString('wajib disertai Tim Kerja', $hasil->galat[0]);
    }

    public function test_tim_yang_belum_terdaftar_ditolak_dengan_petunjuk(): void
    {
        $hasil = $this->impor([
            self::TAJUK,
            ['orang', 'Orang', 'Tim', 'Tim Yang Tidak Ada', '', '', '', 'Ya'],
        ]);

        $this->assertSame(0, User::count());
        $this->assertStringContainsString('Impor tim kerjanya lebih dulu', $hasil->galat[0]);
    }

    public function test_peran_asing_ditolak_beserta_daftar_pilihannya(): void
    {
        $hasil = $this->impor([
            self::TAJUK,
            ['orang', 'Orang', 'Bendahara', '', '', '', '', 'Ya'],
        ]);

        $this->assertSame(0, User::count());
        $this->assertStringContainsString('Kasubbag Umum', $hasil->galat[0]);
    }

    public function test_email_yang_sudah_dipakai_akun_lain_ditolak(): void
    {
        $this->buatPengguna('kasubbag', null, ['email' => 'dipakai@bps.go.id']);

        $hasil = $this->impor([
            self::TAJUK,
            ['orang.baru', 'Orang Baru', 'Admin Sistem', '', 'dipakai@bps.go.id', '', '', 'Ya'],
        ]);

        $this->assertNull(User::where('username', 'orang.baru')->first());
        $this->assertStringContainsString('sudah dipakai akun lain', $hasil->galat[0]);
    }

    /**
     * Email wajib, sebab halaman masuk memakainya sebagai kredensial.
     *
     * Sebelumnya kolom ini boleh kosong dan barisnya tetap diterima. Akun yang
     * lahir darinya tersimpan rapi, tampak sah pada daftar pengguna, dan tidak
     * akan pernah dapat masuk — tanpa satu pun keterangan mengapa.
     */
    public function test_baris_tanpa_email_ditolak_karena_email_dipakai_untuk_masuk(): void
    {
        $hasil = $this->impor([
            self::TAJUK,
            ['tanpa.email', 'Tanpa Email', 'Admin Sistem', '', '', '', '', 'Ya'],
        ]);

        $this->assertNull(
            User::where('username', 'tanpa.email')->first(),
            'Akun yang tidak akan pernah bisa masuk tidak boleh terbentuk.',
        );
        $this->assertStringContainsString('Email', $hasil->galat[0]);
        $this->assertStringContainsString('masuk ke sistem', $hasil->galat[0]);
    }

    public function test_email_yang_bukan_alamat_surel_ditolak(): void
    {
        $hasil = $this->impor([
            self::TAJUK,
            ['email.rusak', 'Email Rusak', 'Admin Sistem', '', 'bukan-surel', '', '', 'Ya'],
        ]);

        $this->assertNull(User::where('username', 'email.rusak')->first());
        $this->assertStringContainsString('tidak berbentuk alamat surel', $hasil->galat[0]);
    }

    public function test_email_yang_sah_tetap_diterima(): void
    {
        $hasil = $this->impor([
            self::TAJUK,
            ['email.sah', 'Email Sah', 'Admin Sistem', '', 'email.sah@bps.go.id', '', '', 'Ya'],
        ]);

        $this->assertSame([], $hasil->galat);
        $this->assertSame(
            'email.sah@bps.go.id',
            User::where('username', 'email.sah')->value('email'),
        );
    }

    // =====================================================================
    // PEMAKSAAN GANTI KATA SANDI
    // =====================================================================

    public function test_pengguna_bertanda_wajib_ganti_dialihkan_ke_halaman_penggantian(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['harus_ganti_sandi' => true]);

        $this->actingAs($pengguna)
            ->get(Dashboard::getUrl())
            ->assertRedirect(GantiKataSandi::getUrl());
    }

    public function test_halaman_penggantian_itu_sendiri_tidak_dialihkan(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['harus_ganti_sandi' => true]);

        $this->actingAs($pengguna)
            ->get(GantiKataSandi::getUrl())
            ->assertOk();
    }

    public function test_pengguna_biasa_tidak_terganggu(): void
    {
        $pengguna = $this->buatPengguna('kasubbag');

        $this->actingAs($pengguna)
            ->get(Dashboard::getUrl())
            ->assertOk();
    }

    public function test_mengganti_kata_sandi_melepaskan_penandanya(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['harus_ganti_sandi' => true]);
        $this->actingAs($pengguna);

        Livewire::test(GantiKataSandi::class)
            ->fillForm([
                'password'              => 'SandiBaruYangPanjang1',
                'password_confirmation' => 'SandiBaruYangPanjang1',
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $pengguna->refresh();

        $this->assertFalse((bool) $pengguna->harus_ganti_sandi);
        $this->assertTrue(Hash::check('SandiBaruYangPanjang1', $pengguna->password));
    }

    public function test_kata_sandi_ulangan_yang_tidak_cocok_ditolak(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['harus_ganti_sandi' => true]);
        $this->actingAs($pengguna);

        Livewire::test(GantiKataSandi::class)
            ->fillForm([
                'password'              => 'SandiBaruYangPanjang1',
                'password_confirmation' => 'SandiLainYangBerbeda1',
            ])
            ->call('simpan')
            ->assertHasFormErrors(['password']);

        $this->assertTrue((bool) $pengguna->refresh()->harus_ganti_sandi);
    }

    // =====================================================================
    // TEMPLATE
    // =====================================================================

    /** Templat yang diunduh wajib dapat diimpor kembali apa adanya. */
    public function test_template_yang_diunduh_dapat_diimpor_kembali(): void
    {
        Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);

        $respons = app(PembuatTemplate::class)->buat(
            ImporPengguna::JUDUL,
            ImporPengguna::kolom(),
            'Template-Impor-Pengguna.xlsx',
        );

        $hasil = app(ImporPengguna::class)->jalankan($respons->getFile()->getPathname());

        $this->assertSame(1, $hasil->ditambah, implode(' | ', $hasil->galat));
        $this->assertSame('Wanda Pribadi', User::where('username', 'wanda.pribadi')->value('name'));
    }
}
