<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporPengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * G-002: Impor Pengguna tidak boleh menurunkan atau menonaktifkan Admin
 * sampai nol Admin aktif tersisa, dan tidak mengubah peran atau status
 * akun pengimpornya sendiri. Baris yang ditolak dilaporkan lewat mekanisme
 * baris gagal yang sudah ada; baris lain tetap diproses.
 */
class ImporPenggunaPenjagaAdminTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const TAJUK = [
        'Username *', 'Nama Lengkap *', 'Peran *', 'Tim Kerja',
        'Email', 'NIP', 'Nomor WhatsApp', 'Aktif',
    ];

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

    private function admin(string $username, array $tambahan = []): User
    {
        return $this->buatPengguna('admin', null, $tambahan + ['username' => $username, 'email' => $username . '@bps.go.id']);
    }

    private function adminAktif(): int
    {
        return User::where('role', 'admin')->where('status_aktif', true)->count();
    }

    /** Baris berkas untuk akun yang sudah ada, dengan peran dan status yang diinginkan. */
    private function baris(User $akun, string $peran, string $aktif = 'Ya', ?string $nama = null): array
    {
        return [$akun->username, $nama ?? $akun->name, $peran, '', $akun->email, '', '', $aktif];
    }

    // =====================================================================
    // AKUN PENGIMPOR SENDIRI
    // =====================================================================

    public function test_baris_yang_mengubah_peran_pengimpor_sendiri_ditolak(): void
    {
        $pengimpor = $this->admin('pengimpor');
        $this->admin('admin.lain');
        $this->actingAs($pengimpor);

        $hasil = $this->impor([self::TAJUK, $this->baris($pengimpor, 'Kasubbag Umum')]);

        $this->assertTrue($hasil->adaGalat());
        $this->assertCount(1, $hasil->galat);
        $this->assertStringContainsString('akun Anda sendiri', $hasil->galat[0]);
        $this->assertSame(0, $hasil->diperbarui);
        $this->assertSame('admin', $pengimpor->refresh()->role);
    }

    public function test_baris_yang_menonaktifkan_pengimpor_sendiri_ditolak(): void
    {
        $pengimpor = $this->admin('pengimpor');
        $this->admin('admin.lain');
        $this->actingAs($pengimpor);

        $hasil = $this->impor([self::TAJUK, $this->baris($pengimpor, 'Admin Sistem', 'Tidak')]);

        $this->assertTrue($hasil->adaGalat());
        $this->assertTrue((bool) $pengimpor->refresh()->status_aktif);
    }

    public function test_kolom_lain_pada_akun_pengimpor_tetap_boleh_diubah(): void
    {
        $pengimpor = $this->admin('pengimpor');
        $this->actingAs($pengimpor);

        $hasil = $this->impor([self::TAJUK, $this->baris($pengimpor, 'Admin Sistem', 'Ya', 'Nama Pengimpor Baru')]);

        $this->assertFalse($hasil->adaGalat());
        $this->assertSame(1, $hasil->diperbarui);
        $this->assertSame('Nama Pengimpor Baru', $pengimpor->refresh()->name);
    }

    // =====================================================================
    // ADMIN AKTIF TIDAK PERNAH NOL
    // =====================================================================

    public function test_baris_yang_menghabiskan_admin_aktif_ditolak_dan_baris_lain_tetap_diproses(): void
    {
        // Pengimpor bukan Admin aktif (mis. sesi Admin yang sudah dinonaktifkan), sehingga tidak dihitung.
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'pengimpor@bps.go.id']));
        $b = $this->admin('admin.b');
        $c = $this->admin('admin.c');
        $biasa = $this->buatPengguna('kasubbag', null, ['username' => 'biasa', 'email' => 'biasa@bps.go.id']);

        $hasil = $this->impor([
            self::TAJUK,
            $this->baris($b, 'Kasubbag Umum'),
            $this->baris($c, 'Kasubbag Umum'),
            $this->baris($biasa, 'Petugas Gudang'),
        ]);

        $this->assertSame(1, $this->adminAktif(), 'Minimal satu Admin aktif harus tersisa.');
        $this->assertSame(2, $hasil->diperbarui);
        $this->assertCount(1, $hasil->galat);
        $this->assertStringContainsString('Admin aktif terakhir', $hasil->galat[0]);
        $this->assertSame('petugas_gudang', $biasa->refresh()->role, 'Baris pengguna biasa tetap diproses.');
    }

    /** Urutan baris berbeda: yang tersisa bisa lain, tetapi Admin aktif tidak pernah nol. */
    public function test_urutan_baris_berbeda_tetap_menyisakan_satu_admin_aktif(): void
    {
        foreach ([['admin.b', 'admin.c'], ['admin.c', 'admin.b']] as $urutan) {
            User::query()->delete();
            $this->actingAs($this->buatPengguna('kasubbag', null, ['username' => 'pengimpor' . implode('', $urutan), 'email' => implode('', $urutan) . '@x.go.id']));
            $akun = ['admin.b' => $this->admin('admin.b'), 'admin.c' => $this->admin('admin.c')];

            $hasil = $this->impor([
                self::TAJUK,
                $this->baris($akun[$urutan[0]], 'Kasubbag Umum', 'Tidak'),
                $this->baris($akun[$urutan[1]], 'Kasubbag Umum', 'Tidak'),
            ]);

            $this->assertSame(1, $this->adminAktif(), 'Urutan ' . implode(' lalu ', $urutan));
            $this->assertCount(1, $hasil->galat);
            $this->assertSame(1, $hasil->diperbarui);
        }
    }

    public function test_admin_yang_sudah_nonaktif_dapat_diturunkan_karena_tidak_mengurangi_admin_aktif(): void
    {
        $aktif    = $this->admin('admin.aktif');
        $nonaktif = $this->admin('admin.nonaktif', ['status_aktif' => false]);
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'pengimpor@bps.go.id']));

        $hasil = $this->impor([self::TAJUK, $this->baris($nonaktif, 'Kasubbag Umum', 'Tidak')]);

        $this->assertFalse($hasil->adaGalat());
        $this->assertSame('kasubbag', $nonaktif->refresh()->role);
        $this->assertSame('admin', $aktif->refresh()->role);
    }

    // =====================================================================
    // HASIL YANG TIDAK MELIBATKAN ADMIN TIDAK BERUBAH
    // =====================================================================

    public function test_impor_pengguna_biasa_dan_akun_baru_tetap_berhasil(): void
    {
        $this->actingAs($this->admin('pengimpor'));
        $biasa = $this->buatPengguna('kasubbag', null, ['username' => 'biasa', 'email' => 'biasa@bps.go.id']);

        $hasil = $this->impor([
            self::TAJUK,
            $this->baris($biasa, 'Petugas Gudang', 'Tidak'),
            ['akun.baru', 'Akun Baru', 'Admin Sistem', '', 'akun.baru@bps.go.id', '', '', 'Ya'],
        ]);

        $this->assertFalse($hasil->adaGalat());
        $this->assertSame(1, $hasil->diperbarui);
        $this->assertSame(1, $hasil->ditambah);
        $this->assertSame('petugas_gudang', $biasa->refresh()->role);
        $this->assertFalse((bool) $biasa->status_aktif);
        $this->assertSame('admin', User::where('username', 'akun.baru')->value('role'));
    }
}
