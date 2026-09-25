<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\RiwayatPersetujuan;
use App\Models\Tim;
use App\Models\User;
use App\Services\Impor\HasilImpor;
use App\Services\Impor\ImporPengguna;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Kasus uji basis path Increment 1 (Modul Pengguna & Data Induk).
 *
 * Berkas ini hanya berisi jalur independen yang belum dieksekusi tes lain;
 * nomor jalurnya mengacu ke docs/pengujian/wb-increment-1.md. Tiap tes
 * disusun supaya urutan keputusan yang dilalui persis sama dengan jalurnya,
 * sehingga penegasan yang dipakai adalah akibat yang hanya muncul lewat jalur
 * itu, bukan sekadar "tidak galat".
 */
class JalurBasisModul1Test extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const TAJUK = [
        'Username *', 'Nama Lengkap *', 'Peran *', 'Tim Kerja',
        'Email', 'NIP', 'Nomor WhatsApp', 'Aktif',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** Form pengguna mewajibkan email, sedangkan buatPengguna() tidak mengisinya. */
    private function akun(string $peran, array $tambahan = [], ?Tim $tim = null): User
    {
        return $this->buatPengguna($peran, $tim, $tambahan + ['email' => uniqid($peran . '.') . '@bps.go.id']);
    }

    /** @param list<list<string>> $baris */
    private function impor(array $baris): HasilImpor
    {
        $lintasan = tempnam(sys_get_temp_dir(), 'uji_basis_') . '.xlsx';
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

    // =====================================================================
    // 1.2 EditUser::beforeSave
    // =====================================================================

    /**
     * J3: muatan status aktif null pada Admin aktif terakhir (pengguna lain).
     *
     * Node 4 benar sehingga $aktifBaru diambil dari status tersimpan (true),
     * lalu node 15 salah dan penyimpanan diteruskan. Menurut aturan "Harus ada
     * minimal satu Admin aktif" perubahan ini wajib ditolak, sebab Toggle
     * menyimpan null sebagai false. Tes ini sengaja menegaskan aturan itu.
     */
    public function test_ubah_user_status_null_pada_admin_aktif_terakhir_ditolak(): void
    {
        $pelaku      = $this->akun('admin', ['status_aktif' => false]);
        $satuSatunya = $this->akun('admin');
        $this->actingAs($pelaku);

        Livewire::test(EditUser::class, ['record' => $satuSatunya->getRouteKey()])
            ->set('data.status_aktif', null)
            ->call('save');

        $this->assertTrue(
            (bool) $satuSatunya->refresh()->status_aktif,
            'Admin aktif terakhir tidak boleh menjadi nonaktif lewat muatan status null.',
        );
        $this->assertSame(1, User::where('role', 'admin')->where('status_aktif', true)->count());
    }

    /**
     * Setelah perbaikan, J11 (T-1b): kunci status_aktif dihapus dari muatan
     * untuk Admin aktif terakhir milik pengguna lain. Kolom itu didehidrasi
     * dan tersimpan false, sehingga penjaga wajib membacanya sebagai false
     * (node 8) dan menolak perubahan.
     */
    public function test_ubah_user_kunci_status_hilang_pada_admin_aktif_terakhir_ditolak(): void
    {
        $pelaku      = $this->akun('admin', ['status_aktif' => false]);
        $satuSatunya = $this->akun('admin');
        $this->actingAs($pelaku);

        $uji = Livewire::test(EditUser::class, ['record' => $satuSatunya->getRouteKey()]);
        $muatan = $uji->get('data');
        unset($muatan['status_aktif']);
        $uji->set('data', $muatan)->call('save');

        $uji->assertNotified('Perubahan ditolak');
        $this->assertTrue((bool) $satuSatunya->refresh()->status_aktif);
        $this->assertSame(1, User::where('role', 'admin')->where('status_aktif', true)->count());
    }

    /**
     * Setelah perbaikan, J10: pada akun sendiri kolom status tidak
     * didehidrasi, sehingga kunci yang hilang memang berarti "tidak berubah"
     * (node 7) dan perubahan kolom lain tetap tersimpan.
     */
    public function test_ubah_user_akun_sendiri_kunci_status_hilang_memakai_status_tersimpan(): void
    {
        $admin = $this->akun('admin', ['name' => 'Admin Awal']);
        $this->actingAs($admin);

        $uji = Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()]);
        $muatan = $uji->get('data');
        unset($muatan['status_aktif']);
        $muatan['name'] = 'Admin Tanpa Kunci Status';
        $uji->set('data', $muatan)->call('save')->assertHasNoFormErrors()->assertNotNotified('Perubahan ditolak');

        $admin->refresh();
        $this->assertSame('Admin Tanpa Kunci Status', $admin->name);
        $this->assertTrue((bool) $admin->status_aktif);
    }

    /**
     * J8: Admin lain yang sudah nonaktif (node 11 salah) tidak dihitung sebagai
     * Admin aktif terakhir, walaupun pelakunya sendiri Admin nonaktif dan tidak
     * ada Admin aktif lain sama sekali.
     */
    public function test_ubah_user_admin_nonaktif_tidak_dihitung_admin_terakhir(): void
    {
        $pelaku   = $this->akun('admin', ['status_aktif' => false]);
        $nonaktif = $this->akun('admin', ['status_aktif' => false]);
        $this->actingAs($pelaku);

        Livewire::test(EditUser::class, ['record' => $nonaktif->getRouteKey()])
            ->fillForm(['role' => 'kasubbag'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotNotified('Perubahan ditolak');

        $this->assertSame('kasubbag', $nonaktif->refresh()->role);
    }

    // =====================================================================
    // 1.6 User::punyaRiwayat
    // =====================================================================

    /** J2: tidak pernah mengajukan, tetapi pernah menjadi pelaksana tahapan. */
    public function test_punya_riwayat_pelaksana_persetujuan(): void
    {
        $tim     = $this->buatTim();
        $pemohon = $this->buatPengguna('tim', $tim);
        $ketua   = $this->buatPengguna('ketua_tim', $tim);
        $permintaan = $this->buatPermintaan($tim, $pemohon, [['barang' => $this->buatBarang(), 'diminta' => 1]]);

        RiwayatPersetujuan::create([
            'permintaan_id' => $permintaan->id,
            'tahap'         => 'ketua_tim',
            'pelaksana_id'  => $ketua->id,
            'keputusan'     => 'setuju',
            'waktu'         => now(),
        ]);

        $this->assertTrue($ketua->punyaRiwayat());
        $this->assertFalse($ketua->delete());
        $this->assertDatabaseHas('users', ['id' => $ketua->id]);
    }

    /** J4: satu-satunya jejaknya adalah BAST yang dibuatnya. */
    public function test_punya_riwayat_pembuat_bast(): void
    {
        $pembuat = $this->buatPengguna('petugas_gudang');
        $this->buatBast($this->buatTim('Asal'), $this->buatTim('Tujuan'), $pembuat);

        $this->assertTrue($pembuat->punyaRiwayat());
        $this->assertFalse($pembuat->delete());
        $this->assertDatabaseHas('users', ['id' => $pembuat->id]);
    }

    // =====================================================================
    // 1.7 ImporPengguna::jalankan, 1.8 pesanPenjagaAdmin, 1.9 bacaYaTidak
    // =====================================================================

    /** J1: berkas yang hanya berisi tajuk tidak menjalankan badan perulangan sama sekali. */
    public function test_impor_berkas_tanpa_baris_data(): void
    {
        $hasil = $this->impor([self::TAJUK]);

        $this->assertSame(0, $hasil->ditambah);
        $this->assertSame(0, $hasil->diperbarui);
        $this->assertSame([], $hasil->galat);
        $this->assertSame(0, User::count());
    }

    /** J2: username kosong ditolak di node 4, sebelum nama diperiksa. */
    public function test_impor_username_kosong_ditolak(): void
    {
        $hasil = $this->impor([self::TAJUK, ['', 'Tanpa Username', 'Admin Sistem', '', 'x@bps.go.id', '', '', 'Ya']]);

        $this->assertSame(['Username dan Nama Lengkap wajib diisi.'], array_map(fn ($g) => preg_replace('/^Baris \d+: /', '', $g), $hasil->galat));
        $this->assertSame(0, User::count());
    }

    /** J3: username terisi, nama kosong ditolak di node 5. */
    public function test_impor_nama_kosong_ditolak(): void
    {
        $hasil = $this->impor([self::TAJUK, ['tanpa.nama', '', 'Admin Sistem', '', 'x@bps.go.id', '', '', 'Ya']]);

        $this->assertCount(1, $hasil->galat);
        $this->assertStringContainsString('Username dan Nama Lengkap wajib diisi.', $hasil->galat[0]);
        $this->assertNull(User::where('username', 'tanpa.nama')->first());
    }

    /** 1.7 J10 dan 1.9 J4: nilai Aktif yang tidak dikenali ditolak (bacaYaTidak mengembalikan null). */
    public function test_impor_kolom_aktif_tidak_dikenali_ditolak(): void
    {
        $hasil = $this->impor([self::TAJUK, ['ragu', 'Ragu Ragu', 'Admin Sistem', '', 'ragu@bps.go.id', '', '', 'Mungkin']]);

        $this->assertCount(1, $hasil->galat);
        $this->assertStringContainsString('Kolom Aktif berisi "Mungkin"', $hasil->galat[0]);
        $this->assertNull(User::where('username', 'ragu')->first());
    }

    /** 1.9 J1: kolom Aktif kosong berarti Ya. */
    public function test_impor_kolom_aktif_kosong_berarti_aktif(): void
    {
        $hasil = $this->impor([self::TAJUK, ['aktif.kosong', 'Aktif Kosong', 'Admin Sistem', '', 'aktif.kosong@bps.go.id', '', '', '']]);

        $this->assertSame([], $hasil->galat);
        $this->assertTrue((bool) User::where('username', 'aktif.kosong')->value('status_aktif'));
    }

    /** J16: peran Tim bertim, akun baru, bukan ketua — ketua tim tidak berubah (node 36 salah). */
    public function test_impor_peran_tim_bertim_bukan_ketua(): void
    {
        $tim = Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);

        $hasil = $this->impor([self::TAJUK, ['anggota', 'Anggota Tim', 'Tim', 'Statistik Sosial', 'anggota@bps.go.id', '', '', 'Ya']]);

        $this->assertSame(1, $hasil->ditambah);
        $this->assertSame([], $hasil->galat);
        $this->assertSame($tim->id, User::where('username', 'anggota')->value('tim_id'));
        $this->assertNull($tim->refresh()->ketua_tim_id);
    }

    /** J18: peran non-tim boleh menyertakan tim yang terdaftar; timnya tercatat. */
    public function test_impor_peran_non_tim_dengan_tim_terisi(): void
    {
        $tim = Tim::create(['nama_tim' => 'Statistik Sosial', 'status_aktif' => true]);

        $hasil = $this->impor([self::TAJUK, ['kasubbag.bertim', 'Kasubbag Bertim', 'Kasubbag Umum', 'Statistik Sosial', 'kasubbag.bertim@bps.go.id', '', '', 'Ya']]);

        $this->assertSame([], $hasil->galat);
        $pengguna = User::where('username', 'kasubbag.bertim')->sole();
        $this->assertSame('kasubbag', $pengguna->role);
        $this->assertSame($tim->id, $pengguna->tim_id);
        $this->assertNull($tim->refresh()->ketua_tim_id);
    }

    /** J19: NIP terisi pada peran non-tim tanpa tim, akun baru. */
    public function test_impor_nip_terisi_tanpa_tim(): void
    {
        $hasil = $this->impor([self::TAJUK, ['gudang.nip', 'Gudang Ber-NIP', 'Petugas Gudang', '', 'gudang.nip@bps.go.id', '198001012010011001', '', 'Ya']]);

        $this->assertSame([], $hasil->galat);
        $this->assertSame('198001012010011001', User::where('username', 'gudang.nip')->value('nip'));
    }

    /** 1.8 J8: Admin aktif terakhir dinonaktifkan dengan peran tetap Admin → ditolak (node 11 benar). */
    public function test_impor_menonaktifkan_admin_aktif_terakhir_ditolak(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'pengimpor@bps.go.id']));
        $satuSatunya = $this->buatPengguna('admin', null, ['username' => 'admin.tunggal', 'email' => 'admin.tunggal@bps.go.id']);

        $hasil = $this->impor([self::TAJUK, ['admin.tunggal', $satuSatunya->name, 'Admin Sistem', '', 'admin.tunggal@bps.go.id', '', '', 'Tidak']]);

        $this->assertCount(1, $hasil->galat);
        $this->assertStringContainsString('Admin aktif terakhir', $hasil->galat[0]);
        $this->assertTrue((bool) $satuSatunya->refresh()->status_aktif);
    }

    /** 1.8 J10: Admin aktif lain diimpor ulang dengan peran dan status yang sama → diterima. */
    public function test_impor_ulang_admin_lain_tanpa_perubahan_peran_status(): void
    {
        $this->actingAs($this->buatPengguna('kasubbag', null, ['email' => 'pengimpor@bps.go.id']));
        $admin = $this->buatPengguna('admin', null, ['username' => 'admin.tetap', 'email' => 'admin.tetap@bps.go.id']);

        $hasil = $this->impor([self::TAJUK, ['admin.tetap', 'Admin Nama Baru', 'Admin Sistem', '', 'admin.tetap@bps.go.id', '', '', 'Ya']]);

        $this->assertSame([], $hasil->galat);
        $this->assertSame(1, $hasil->diperbarui);
        $admin->refresh();
        $this->assertSame('Admin Nama Baru', $admin->name);
        $this->assertSame('admin', $admin->role);
    }

    // =====================================================================
    // 1.10 StokMasuk aturan kode_barang, 1.11 simpanBarangBaru
    // =====================================================================

    /** Membuka dialog Barang Baru milik baris barang pertama pada formulir Catat Stok Masuk. */
    private function bukaDialogBarangBaru()
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $uji = Livewire::test(StokMasuk::class)->mountAction('catat');
        $halaman = $uji->instance();

        $pengulang = $halaman->getSchema($halaman->getMountedActionSchemaName())
            ->getComponent(fn ($komponen) => $komponen instanceof Repeater);
        $pilihBarang = collect($pengulang->getItems())->first()
            ->getComponent(fn ($komponen) => $komponen instanceof Select && $komponen->getName() === 'barang_id');

        // Kunci komponen ditulis relatif terhadap skema aksi induknya, dan aksi
        // induk disebut ulang supaya dialog Barang Baru terpasang bertumpuk di
        // atas formulir Catat, persis seperti ketika tombol "+" ditekan.
        $skema = $halaman->getMountedActionSchemaName();
        $kunci = substr($pilihBarang->getKey(), strlen($skema) + 1);

        return $uji->mountAction(['catat', TestAction::make('createOption')->schemaComponent($kunci, $skema)]);
    }

    private function kategoriPersediaan(): Kategori
    {
        return Kategori::create(['kode_kategori' => 'K-1', 'kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan']);
    }

    /** J1: kode yang belum dipakai pada kategorinya lolos aturan dan barangnya terbentuk. */
    public function test_aturan_kode_barang_baru_lolos(): void
    {
        $kategori = $this->kategoriPersediaan();

        $this->bukaDialogBarangBaru()
            ->setActionData([
                'kategori_id' => $kategori->id, 'kode_barang' => '000900', 'nama_barang' => 'Map Plastik',
                'satuan' => 'Buah', 'stok_minimum' => 0,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('barang_persediaan', ['kategori_id' => $kategori->id, 'kode_barang' => '000900', 'stok_fisik' => 0]);
    }

    /** J2: kode yang sudah dipakai pada kategori yang sama (berspasi ujung) ditolak aturan. */
    public function test_aturan_kode_barang_ganda_ditolak(): void
    {
        $kategori = $this->kategoriPersediaan();
        BarangPersediaan::create([
            'kategori_id' => $kategori->id, 'kode_barang' => '000100', 'nama_barang' => 'Barang Lama',
            'satuan' => 'Buah', 'stok_fisik' => 0, 'stok_hold' => 0, 'stok_minimum' => 0, 'status_aktif' => true,
        ]);

        $uji = $this->bukaDialogBarangBaru()
            ->setActionData([
                'kategori_id' => $kategori->id, 'kode_barang' => ' 000100 ', 'nama_barang' => 'Barang Ganda',
                'satuan' => 'Buah', 'stok_minimum' => 0,
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['kode_barang']);

        // Kode berspasi ujung lolos aturan enam digit setelah dipangkas, sehingga
        // yang menolaknya harus aturan keunikan (jalur J2), bukan aturan bentuk.
        $pesan = implode(' ', $uji->errors()->all());
        $this->assertStringContainsString('Kode barang sudah dipakai pada kategori ini.', $pesan);
        $this->assertStringNotContainsString('6 digit', $pesan);

        $this->assertSame(1, BarangPersediaan::where('kode_barang', '000100')->count());
    }

    /** 1.11 J3: data tanpa kunci kode_barang memakai string kosong (sisi kanan ??). */
    public function test_simpan_barang_baru_tanpa_kunci_kode(): void
    {
        $kategori = $this->kategoriPersediaan();

        $id = StokMasuk::simpanBarangBaru([
            'kategori_id' => $kategori->id, 'nama_barang' => 'Tanpa Kode', 'satuan' => 'Buah', 'stok_minimum' => 0,
        ]);

        $barang = BarangPersediaan::findOrFail($id);
        $this->assertSame('', $barang->kode_barang);
        $this->assertSame(0, $barang->stok_fisik);
    }
}
