<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BarangPalingDiminta;
use App\Filament\Widgets\PermintaanPerTim;
use App\Filament\Widgets\TrenKonsumsiKategori;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Models\Tim;
use App\Models\User;
use App\Services\StokService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Panel monitoring pola permintaan dan tren konsumsi (temuan audit T-09).
 *
 * Yang dijaga di sini adalah **besaran yang dihitung**, bukan tampilannya.
 * Keempat kebutuhan monitoring pada rancangan menyebut angka yang berbeda-beda
 * — berapa sering diminta, berapa banyak diminta, berapa banyak disalurkan,
 * dan kapan ramainya — dan kekeliruan yang paling mudah terjadi adalah
 * menukar salah satunya dengan yang lain. Penukaran semacam itu tidak
 * menimbulkan galat apa pun: panelnya tetap tampil, angkanya tetap masuk akal,
 * dan hanya salah.
 *
 * Karena itu tiap uji menyiapkan angka yang sengaja dibuat berbeda antara
 * frekuensi dan volume, sehingga panel yang menghitung besaran yang keliru
 * pasti ketahuan.
 */
class MonitoringPolaPermintaanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function kasubbag(): User
    {
        return $this->buatPengguna('kasubbag');
    }

    // =====================================================================
    // KEBUTUHAN 1 — BARANG PALING SERING DIMINTA
    // =====================================================================

    /**
     * Peringkat memakai frekuensi, bukan volume.
     *
     * Kertas diminta pada tiga permintaan masing-masing 1 rim; Tinta diminta
     * sekali sebanyak 50. Bila panel keliru mengurutkan menurut volume, Tinta
     * akan naik ke puncak.
     */
    public function test_peringkat_barang_memakai_frekuensi_permintaan(): void
    {
        $this->actingAs($this->kasubbag());

        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);

        $kertas = $this->buatBarang(tambahan: ['nama_barang' => 'Kertas HVS A4']);
        $tinta  = $this->buatBarang(tambahan: ['nama_barang' => 'Tinta Printer']);

        foreach (range(1, 3) as $ke) {
            $this->buatPermintaan($tim, $pengaju, [['barang' => $kertas, 'diminta' => 1]]);
        }
        $this->buatPermintaan($tim, $pengaju, [['barang' => $tinta, 'diminta' => 50]]);

        $daftar = Livewire::test(BarangPalingDiminta::class)->instance()->barang;

        $this->assertSame('Kertas HVS A4', $daftar->first()['nama']);
        $this->assertSame(3, $daftar->first()['frekuensi']);
        $this->assertSame('3 Buah', $daftar->first()['totalDiminta']);

        $this->assertSame('Tinta Printer', $daftar->last()['nama']);
        $this->assertSame(1, $daftar->last()['frekuensi']);
        $this->assertSame('50 Buah', $daftar->last()['totalDiminta']);
    }

    /** Permintaan di luar periode tidak ikut terhitung. */
    public function test_peringkat_barang_mengikuti_periode_terpilih(): void
    {
        $this->actingAs($this->kasubbag());

        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang();

        $lama = $this->buatPermintaan($tim, $pengaju, [['barang' => $barang, 'diminta' => 1]]);
        $lama->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->buatPermintaan($tim, $pengaju, [['barang' => $barang, 'diminta' => 1]]);

        $panel = Livewire::test(BarangPalingDiminta::class);

        $this->assertSame(1, $panel->instance()->barang->first()['frekuensi']);

        $panel->set('periode', 'semua');
        $this->assertSame(2, $panel->instance()->barang->first()['frekuensi']);
    }

    public function test_peringkat_barang_kosong_ketika_belum_ada_permintaan(): void
    {
        $this->actingAs($this->kasubbag());
        $this->buatBarang();

        Livewire::test(BarangPalingDiminta::class)
            ->assertSee('Belum ada permintaan pada periode ini');
    }

    // =====================================================================
    // KEBUTUHAN 3 — PENGELUARAN PER TIM KERJA
    // =====================================================================

    /**
     * Kedua besaran hidup berdampingan pada satu panel, dan keduanya berbeda.
     *
     * Tim A mengajukan dua permintaan kecil (total 3 unit), Tim B satu
     * permintaan besar (20 unit). Pada besaran "jumlah permintaan" Tim A
     * unggul; pada "barang disalurkan" Tim B yang unggul.
     */
    public function test_panel_tim_kerja_membedakan_jumlah_permintaan_dan_barang_disalurkan(): void
    {
        $this->actingAs($this->kasubbag());

        $timA = $this->buatTim('Statistik Sosial');
        $timB = $this->buatTim('Statistik Distribusi');
        $gudang = $this->buatPengguna('petugas_gudang');

        $barang = $this->buatBarang(stokFisik: 500);

        foreach ([1, 2] as $jumlah) {
            $permintaan = $this->buatPermintaan(
                $timA,
                $this->buatPengguna('tim', $timA),
                [['barang' => $barang, 'diminta' => $jumlah, 'final' => $jumlah]],
            );
            app(StokService::class)->konversi($permintaan, $gudang->id);
        }

        $besar = $this->buatPermintaan(
            $timB,
            $this->buatPengguna('tim', $timB),
            [['barang' => $barang, 'diminta' => 20, 'final' => 20]],
        );
        app(StokService::class)->konversi($besar, $gudang->id);

        $panel = Livewire::test(PermintaanPerTim::class);

        // Besaran bawaan: banyaknya permintaan.
        $perTim = $panel->instance()->tim->keyBy('nama');
        $this->assertSame(2, $perTim['Statistik Sosial']['jumlah']);
        $this->assertSame(1, $perTim['Statistik Distribusi']['jumlah']);
        $this->assertSame('Permintaan per Tim Kerja', $panel->instance()->judul);
        $this->assertSame('permintaan', $panel->instance()->satuan);

        // Besaran kedua: banyaknya barang yang benar-benar keluar.
        $panel->set('metrik', 'keluar');
        $perTim = $panel->instance()->tim->keyBy('nama');
        $this->assertSame(3, $perTim['Statistik Sosial']['jumlah']);
        $this->assertSame(20, $perTim['Statistik Distribusi']['jumlah']);
        $this->assertSame('Pengeluaran per Tim Kerja', $panel->instance()->judul);
        $this->assertSame('unit barang', $panel->instance()->satuan);
    }

    /**
     * Permintaan yang tidak pernah menghasilkan pengeluaran tidak boleh
     * terhitung sebagai barang disalurkan.
     */
    public function test_permintaan_tanpa_pengeluaran_tidak_terhitung_sebagai_barang_disalurkan(): void
    {
        $this->actingAs($this->kasubbag());

        $tim    = $this->buatTim('Statistik Sosial');
        $barang = $this->buatBarang();

        $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $barang, 'diminta' => 9]],
            'ditolak_kasubbag',
        );

        $panel = Livewire::test(PermintaanPerTim::class)->set('metrik', 'keluar');

        $this->assertSame(0, $panel->instance()->tim->keyBy('nama')['Statistik Sosial']['jumlah']);
        $this->assertSame(0, $panel->instance()->total);
    }

    /**
     * Tautan baris mengikuti besaran yang sedang ditampilkan.
     *
     * Pada "jumlah permintaan" angkanya adalah banyaknya dokumen, dan daftar
     * permintaan tersaring tim menjelaskan angka itu. Pada "barang disalurkan"
     * tidak ada halaman yang mewakilinya, sehingga barisnya sengaja tanpa
     * tautan — mengarahkan ke daftar dokumen di bawah angka yang berbunyi unit
     * barang justru membingungkan.
     */
    public function test_tautan_baris_mengikuti_metrik(): void
    {
        $this->actingAs($this->kasubbag());
        $this->buatTim('Statistik Sosial');

        $panel = Livewire::test(PermintaanPerTim::class);

        $this->assertNotNull($panel->instance()->tim->first()['tautan']);

        $panel->set('metrik', 'keluar');
        $this->assertNull($panel->instance()->tim->first()['tautan']);

        // Judul panel tetap punya tujuan yang masuk akal: rekap barang keluar.
        $this->assertStringContainsString('kartu-kendali', $panel->instance()->tautanSemua);
    }

    /**
     * Kedua besaran mengamati rentang waktu yang sama.
     *
     * Satu menyaring kolom bertanda waktu, satunya kolom bertanggal. Tanpa
     * patokan awal hari, berganti besaran diam-diam menggeser jendelanya
     * hampir sehari — dan transaksi di tepi periode masuk pada satu besaran
     * tetapi hilang pada besaran lain.
     */
    public function test_batas_periode_kedua_metrik_sama(): void
    {
        $this->actingAs($this->kasubbag());

        $panel = Livewire::test(PermintaanPerTim::class)->instance();

        $batas = (new \ReflectionMethod($panel, 'batasWaktu'))->invoke($panel);

        $this->assertSame(
            now()->subDays(30)->startOfDay()->toDateTimeString(),
            $batas->toDateTimeString(),
            'Batas periode harus dipatok pada awal hari.',
        );

        $panel->periode = '90';
        $this->assertSame(
            now()->subDays(90)->startOfDay()->toDateTimeString(),
            (new \ReflectionMethod($panel, 'batasWaktu'))->invoke($panel)->toDateTimeString(),
        );

        $panel->periode = 'semua';
        $this->assertNull((new \ReflectionMethod($panel, 'batasWaktu'))->invoke($panel));
    }

    /** Kaki panel peringkat barang tidak menyebut angkanya sebagai permintaan. */
    public function test_kaki_panel_peringkat_tidak_menyebut_jumlah_permintaan(): void
    {
        $this->actingAs($this->kasubbag());

        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $satu    = $this->buatBarang(tambahan: ['nama_barang' => 'Kertas']);
        $dua     = $this->buatBarang(tambahan: ['nama_barang' => 'Tinta']);

        // Satu permintaan, dua barang: frekuensinya 2, dokumennya tetap 1.
        $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $satu, 'diminta' => 1],
            ['barang' => $dua, 'diminta' => 1],
        ]);

        $panel = Livewire::test(BarangPalingDiminta::class);

        $this->assertSame(2, $panel->instance()->totalFrekuensi);
        $panel->assertSee('kali diminta pada peringkat ini')
            ->assertDontSee('permintaan barang pada periode ini');
    }

    // =====================================================================
    // KEBUTUHAN 2 — TREN KONSUMSI PER KATEGORI
    // =====================================================================

    public function test_tren_konsumsi_menjumlahkan_barang_yang_benar_benar_keluar(): void
    {
        $this->actingAs($this->kasubbag());

        $tim    = $this->buatTim();
        $gudang = $this->buatPengguna('petugas_gudang');
        $barang = $this->buatBarang(stokFisik: 100);

        $permintaan = $this->buatPermintaan(
            $tim,
            $this->buatPengguna('tim', $tim),
            [['barang' => $barang, 'diminta' => 7, 'final' => 7]],
        );
        app(StokService::class)->konversi($permintaan, $gudang->id);

        $seri = Livewire::test(TrenKonsumsiKategori::class)->instance()->seri;

        $this->assertSame(7, array_sum($seri['nilai']));
        $this->assertCount(12, $seri['label'], 'Dua belas bulan terakhir selalu digambarkan.');
    }

    /** Penyaring kategori benar-benar mempersempit datanya. */
    public function test_tren_konsumsi_menyaring_menurut_kategori(): void
    {
        $this->actingAs($this->kasubbag());

        $tim    = $this->buatTim();
        $gudang = $this->buatPengguna('petugas_gudang');

        $lain = Kategori::create([
            'kode_akun'     => '117112',
            'kode_kategori' => '1010302001',
            'nama_kategori' => 'Kertas dan Cover',
            'tipe'          => 'persediaan',
        ]);

        $bawaan = $this->buatBarang(stokFisik: 100);
        $khusus = $this->buatBarang(stokFisik: 100, tambahan: ['kategori_id' => $lain->id]);

        foreach ([[$bawaan, 4], [$khusus, 11]] as [$barang, $jumlah]) {
            $permintaan = $this->buatPermintaan(
                $tim,
                $this->buatPengguna('tim', $tim),
                [['barang' => $barang, 'diminta' => $jumlah, 'final' => $jumlah]],
            );
            app(StokService::class)->konversi($permintaan, $gudang->id);
        }

        $panel = Livewire::test(TrenKonsumsiKategori::class);

        $this->assertSame(15, array_sum($panel->instance()->seri['nilai']));

        $panel->set('filter', (string) $lain->id);
        $this->assertSame(11, array_sum($panel->instance()->seri['nilai']));
    }

    // ---------------------------------------------------------------
    // PERIODE TAHUN KALENDER
    // ---------------------------------------------------------------

    /**
     * Mencatat satu pengeluaran pada tanggal tertentu, menembus penjaga
     * tanggal mundur pada StokService dengan menulis langsung ke buku besar.
     */
    private function catatKeluar(string $tanggal, int $jumlah, ?int $kategoriId = null): void
    {
        $barang = $this->buatBarang(
            stokFisik: 1000,
            tambahan: $kategoriId ? ['kategori_id' => $kategoriId] : [],
        );

        MutasiStok::create([
            'barang_id'     => $barang->id,
            'tanggal'       => $tanggal,
            'jenis'         => 'keluar',
            'jumlah'        => -$jumlah,
            'saldo_sesudah' => 0,
            'sumber'        => 'pemakaian',
            'petugas_id'    => $this->buatPengguna('petugas_gudang')->id,
        ]);
    }

    /** Selalu dua belas bulan, berurutan Januari sampai Desember. */
    public function test_tren_konsumsi_selalu_dua_belas_bulan_januari_sampai_desember(): void
    {
        $this->actingAs($this->kasubbag());
        $this->catatKeluar(now()->year . '-06-15', 5);

        $seri = Livewire::test(TrenKonsumsiKategori::class)->instance()->seri;

        $this->assertCount(12, $seri['label']);
        $this->assertCount(12, $seri['nilai']);
        $this->assertSame('Jan', $seri['label'][0]);
        $this->assertSame('Des', $seri['label'][11]);
        // Tidak satu pun label memuat tahun: seluruh batang satu tahun yang sama.
        $this->assertSame([], preg_grep('/\d{4}/', $seri['label']));
    }

    /** Bulan tanpa transaksi tetap digambar dengan nilai nol. */
    public function test_bulan_tanpa_transaksi_bernilai_nol(): void
    {
        $this->actingAs($this->kasubbag());
        $this->catatKeluar(now()->year . '-03-10', 7);

        $seri = Livewire::test(TrenKonsumsiKategori::class)->instance()->seri;

        $this->assertSame(7, $seri['nilai'][2], 'Maret memuat transaksinya.');
        $this->assertSame(0, $seri['nilai'][0], 'Januari kosong, bukan hilang.');
        $this->assertSame(0, $seri['nilai'][11], 'Desember kosong, bukan hilang.');
        $this->assertSame(7, array_sum($seri['nilai']));
    }

    /** Transaksi Januari dan Desember jatuh pada bulannya sendiri. */
    public function test_transaksi_januari_dan_desember_masuk_bulannya(): void
    {
        $this->actingAs($this->kasubbag());
        $tahun = now()->year;

        $this->catatKeluar($tahun . '-01-01', 3);
        $this->catatKeluar($tahun . '-12-31', 9);

        $seri = Livewire::test(TrenKonsumsiKategori::class)->instance()->seri;

        $this->assertSame(3, $seri['nilai'][0]);
        $this->assertSame(9, $seri['nilai'][11]);
    }

    /**
     * Tahun tidak boleh tercampur.
     *
     * 31 Desember tahun lalu dan 1 Januari tahun depan sengaja dipakai sebagai
     * penjaga batas: keduanya berjarak satu hari dari periode ini, dan justru
     * di titik itulah kebocoran periode biasanya terjadi.
     */
    public function test_tahun_lain_tidak_bocor_ke_dalam_periode(): void
    {
        $this->actingAs($this->kasubbag());
        $tahun = now()->year;

        $this->catatKeluar(($tahun - 1) . '-12-31', 100);
        $this->catatKeluar(($tahun + 1) . '-01-01', 200);
        $this->catatKeluar($tahun . '-07-04', 6);

        $seri = Livewire::test(TrenKonsumsiKategori::class)->instance()->seri;

        $this->assertSame(6, array_sum($seri['nilai']), 'Hanya transaksi tahun ini yang terhitung.');
        $this->assertSame(6, $seri['nilai'][6], 'Juli.');
        $this->assertSame(0, $seri['nilai'][0]);
        $this->assertSame(0, $seri['nilai'][11]);
    }

    /** Penyaring kategori tetap bekerja pada periode tahun kalender. */
    public function test_penyaring_kategori_tetap_bekerja_pada_tahun_kalender(): void
    {
        $this->actingAs($this->kasubbag());
        $tahun = now()->year;

        $lain = Kategori::create([
            'kode_akun'     => '117112',
            'kode_kategori' => '1010302002',
            'nama_kategori' => 'Kertas dan Cover',
            'tipe'          => 'persediaan',
        ]);

        $this->catatKeluar($tahun . '-02-10', 4);
        $this->catatKeluar($tahun . '-09-20', 11, $lain->id);

        $panel = Livewire::test(TrenKonsumsiKategori::class);

        $this->assertSame(15, array_sum($panel->instance()->seri['nilai']));

        $panel->set('filter', (string) $lain->id);
        $seri = $panel->instance()->seri;

        $this->assertSame(11, array_sum($seri['nilai']));
        $this->assertSame(11, $seri['nilai'][8], 'September.');
        $this->assertCount(12, $seri['label'], 'Menyaring kategori tidak memotong bulan.');
    }

    public function test_tren_konsumsi_kosong_ketika_belum_ada_barang_keluar(): void
    {
        $this->actingAs($this->kasubbag());
        $this->buatBarang();

        $this->assertSame([], Livewire::test(TrenKonsumsiKategori::class)->instance()->seri['nilai']);
    }

    // =====================================================================
    // HAK LIHAT
    // =====================================================================

    /**
     * Dasbor tiap peran tetap terangkai setelah panel baru ditambahkan.
     *
     * Panel baru hanya tampil bagi Kasubbag, tetapi kekeliruan pada urutan
     * atau lebar kolomnya dapat menggeser susunan panel peran lain. Merender
     * kelima dasbor adalah cara termurah memastikan tidak ada yang patah.
     */
    public function test_dasbor_seluruh_peran_tetap_terangkai(): void
    {
        $tim = $this->buatTim();

        foreach (['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim'] as $role) {
            // Sesi dibersihkan tiap putaran: AuthenticateSession mengeluarkan
            // pengguna begitu sidik kata sandi pada sesi tidak lagi cocok,
            // sehingga berganti akun tanpa membersihkan sesi berakhir pada
            // pengalihan ke halaman masuk, bukan pada dasbor yang hendak diuji.
            $this->flushSession();

            $this->actingAs($this->lengkapiAkun($this->buatPengguna(
                $role,
                in_array($role, ['tim', 'ketua_tim'], true) ? $tim : null,
            )));

            $this->get(Dashboard::getUrl())->assertOk();
        }
    }

    /**
     * Ketiga panel analisis hanya untuk Kasubbag Umum, mengikuti UC-22 yang
     * menetapkannya sebagai aktor analisis pola permintaan dan tren konsumsi.
     */
    public function test_panel_analisis_hanya_untuk_kasubbag(): void
    {
        $tim = $this->buatTim();

        $peran = [
            'kasubbag'       => true,
            'admin'          => false,
            'petugas_gudang' => false,
            'ketua_tim'      => false,
            'tim'            => false,
        ];

        foreach ($peran as $role => $boleh) {
            $this->actingAs($this->buatPengguna(
                $role,
                in_array($role, ['tim', 'ketua_tim'], true) ? $tim : null,
            ));

            foreach ([BarangPalingDiminta::class, TrenKonsumsiKategori::class, PermintaanPerTim::class] as $panel) {
                $this->assertSame(
                    $boleh,
                    $panel::canView(),
                    $panel . ' pada peran ' . $role,
                );
            }
        }
    }
}
