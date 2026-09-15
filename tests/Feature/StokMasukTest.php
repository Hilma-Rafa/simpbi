<?php

namespace Tests\Feature;

use App\Filament\Pages\StokMasuk;
use App\Models\MutasiStok;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian pencatatan stok masuk (UC-07).
 *
 * Yang dijaga terutama adalah kewajiban mengisi Nomor Dasar. Kolom itu terbit
 * apa adanya pada kartu kendali sebagai "Nomor Dasar M/K", dan bila kosong,
 * transaksi penambahan stok tidak dapat ditelusuri ke bukti mana pun — persis
 * kelemahan kartu kendali manual yang hendak diperbaiki penelitian ini.
 */
class StokMasukTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /**
     * Mencatat satu nota.
     *
     * Formulir kini berbentuk satu dokumen berisi banyak barang, sehingga
     * kolom barang dan jumlah berada di dalam pengulangnya. Penolong ini
     * menerima bentuk datar yang lebih enak dibaca pada pengujian, lalu
     * membungkusnya menjadi satu baris pengulang.
     */
    private function catat(array $data)
    {
        $barang = [[
            'barang_id'  => $data['barang_id'] ?? null,
            'jumlah'     => $data['jumlah'] ?? null,
            'keterangan' => $data['keterangan'] ?? null,
        ]];

        unset($data['barang_id'], $data['jumlah'], $data['keterangan']);

        return $this->konfirmasi(
            Livewire::test(StokMasuk::class)->callAction('catat', [...$data, 'barang' => $barang])
        );
    }

    /**
     * Menekan "Ya, Simpan" pada dialog konfirmasi, bila dialognya memang
     * terbuka.
     *
     * Sejak Simpan hanya membuka konfirmasi, pengujian aturan pencatatan di
     * berkas ini menekan keduanya sekaligus — yang diuji di sana aturannya,
     * bukan dialognya. Dialognya sendiri diuji tersendiri di bawah. Formulir
     * yang ditolak validasi tidak pernah sampai ke dialog, sehingga keadaannya
     * dibiarkan apa adanya supaya galatnya tetap dapat diperiksa.
     */
    private function konfirmasi($uji)
    {
        $terpasang = collect($uji->get('mountedActions'))->pluck('name')->last();

        return $terpasang === 'konfirmasiCatat' ? $uji->callMountedAction() : $uji;
    }

    /** Mencatat satu nota berisi beberapa barang sekaligus. */
    private function catatNota(array $bersama, array $barang)
    {
        return $this->konfirmasi(
            Livewire::test(StokMasuk::class)->callAction('catat', [...$bersama, 'barang' => $barang])
        );
    }

    public function test_hanya_petugas_gudang_yang_dapat_membuka_halaman(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $this->assertTrue(StokMasuk::canAccess());

        foreach (['admin', 'kasubbag', 'ketua_tim', 'tim'] as $peran) {
            $this->actingAs($this->buatPengguna($peran));
            $this->assertFalse(StokMasuk::canAccess(), "Peran {$peran} tidak berwenang mencatat stok masuk.");
        }
    }

    #[DataProvider('sumberBerdokumen')]
    public function test_nomor_dasar_wajib_untuk_sumber_yang_berdokumen(string $sumber): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 10);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 5,
            'sumber'      => $sumber,
            'nomor_dasar' => null,
        ])->assertHasActionErrors(['nomor_dasar']);

        $this->assertSame(10, $barang->refresh()->stok_fisik, 'Stok tidak boleh bertambah bila borangnya ditolak.');
        $this->assertSame(0, MutasiStok::count());
    }

    /** @return array<string, array{string}> */
    public static function sumberBerdokumen(): array
    {
        return [
            'pembelian'      => ['pembelian'],
            'transfer masuk' => ['transfer_masuk'],
        ];
    }

    #[DataProvider('sumberTanpaDokumen')]
    public function test_nomor_dasar_tidak_wajib_untuk_sumber_tanpa_dokumen(string $sumber): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 10);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 5,
            'sumber'      => $sumber,
            'nomor_dasar' => null,
        ])->assertHasNoActionErrors();

        $this->assertSame(15, $barang->refresh()->stok_fisik);
        $this->assertNull(MutasiStok::sole()->nomor_dasar);
    }

    /** @return array<string, array{string}> */
    public static function sumberTanpaDokumen(): array
    {
        return [
            'stok awal'    => ['stok_awal'],
            'pengembalian' => ['pengembalian'],
        ];
    }

    public function test_pembelian_dengan_nomor_dasar_tercatat_pada_buku_besar(): void
    {
        $petugas = $this->buatPengguna('petugas_gudang');
        $this->actingAs($petugas);
        $barang = $this->buatBarang(stokFisik: 5);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 20,
            'sumber'      => 'pembelian',
            'nomor_dasar' => '34/F/HI/VIII/2026',
            'keterangan'  => 'Pengadaan triwulan pertama',
        ])->assertHasNoActionErrors();

        $mutasi = MutasiStok::sole();
        $this->assertSame('masuk', $mutasi->jenis);
        $this->assertSame(20, $mutasi->jumlah);
        $this->assertSame(25, $mutasi->saldo_sesudah);
        $this->assertSame('pembelian', $mutasi->sumber);
        $this->assertSame('34/F/HI/VIII/2026', $mutasi->nomor_dasar);
        $this->assertSame($petugas->id, $mutasi->petugas_id);
        $this->assertSame(25, $barang->refresh()->stok_fisik);
    }

    public function test_tanggal_dokumen_dipakai_pada_kartu_kendali(): void
    {
        // Faktur kerap baru sampai ke gudang beberapa hari setelah tanggalnya,
        // dan kartu kendali harus menunjuk tanggal dokumennya, bukan tanggal
        // barisnya diketik.
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 5);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 10,
            'sumber'      => 'pembelian',
            'nomor_dasar' => '34/F/HI/VIII/2026',
            'tanggal'     => now()->subDays(6)->toDateString(),
        ])->assertHasNoActionErrors();

        $this->assertSame(
            now()->subDays(6)->toDateString(),
            MutasiStok::sole()->tanggal->toDateString(),
            'Kartu kendali harus memakai tanggal dokumen, bukan tanggal pencatatan.',
        );
    }

    /**
     * Nota yang baru ditemukan belakangan harus dapat dicatat pada tanggal
     * sebenarnya, bukan pada tanggal penemuannya.
     */
    public function test_tanggal_mundur_diterima_dan_disisipkan_pada_urutannya(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 5);

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 10,
            'sumber'      => 'pembelian',
            'nomor_dasar' => '34/F/HI/VIII/2026',
            'tanggal'     => now()->subDays(3)->toDateString(),
        ])->assertHasNoActionErrors();

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 4,
            'sumber'      => 'pembelian',
            'nomor_dasar' => '35/F/HI/VIII/2026',
            'tanggal'     => now()->subDays(10)->toDateString(),
        ])->assertHasNoActionErrors();

        $this->assertSame(2, MutasiStok::count());
        $this->assertSame(19, $barang->refresh()->stok_fisik, 'Stok akhir adalah 5 + 10 + 4.');
    }

    /**
     * Inilah alasan seluruh saldo dihitung ulang, bukan hanya baris barunya:
     * penyisipan di tengah menggeser kolom Sisa setiap baris sesudahnya.
     */
    public function test_kolom_sisa_dihitung_ulang_menurut_urutan_tanggal(): void
    {
        $petugas = $this->buatPengguna('petugas_gudang');
        $barang  = $this->buatBarang(stokFisik: 5);
        $stok    = app(\App\Services\StokService::class);

        $catat = fn (int $jumlah, string $tanggal, string $nomor) => $stok->tambah(
            barangId: $barang->id,
            jumlah: $jumlah,
            sumber: 'pembelian',
            nomorDasar: $nomor,
            keterangan: null,
            petugasId: $petugas->id,
            tanggal: $tanggal,
        );

        $catat(10, now()->subDays(3)->toDateString(), 'NOTA-B');
        $catat(4, now()->subDays(10)->toDateString(), 'NOTA-A');

        $urut = MutasiStok::orderBy('tanggal')->orderBy('id')->get();

        $this->assertSame(['NOTA-A', 'NOTA-B'], $urut->pluck('nomor_dasar')->all());
        $this->assertSame(
            [9, 19],
            $urut->pluck('saldo_sesudah')->map(fn ($n) => (int) $n)->all(),
            'Saldo 5 lalu +4 menjadi 9, lalu +10 menjadi 19 — bukan urutan pencatatannya.',
        );
    }

    /**
     * Stok awal yang tidak punya baris buku besar tidak boleh lenyap ketika
     * saldo dihitung ulang. Katalog dimasukkan dengan stok fisik apa adanya,
     * sehingga buku besar tidak memuat seluruh riwayat barang.
     */
    public function test_stok_awal_tanpa_baris_buku_besar_tetap_terhitung(): void
    {
        $petugas = $this->buatPengguna('petugas_gudang');
        $barang  = $this->buatBarang(stokFisik: 40);

        app(\App\Services\StokService::class)->tambah(
            barangId: $barang->id,
            jumlah: 6,
            sumber: 'pembelian',
            nomorDasar: 'NOTA-C',
            keterangan: null,
            petugasId: $petugas->id,
            tanggal: now()->toDateString(),
        );

        $this->assertSame(46, $barang->refresh()->stok_fisik);
        $this->assertSame(46, (int) MutasiStok::sole()->saldo_sesudah);
    }

    public function test_nomor_dasar_yang_terlalu_panjang_ditolak(): void
    {
        // Batasnya mengikuti lebar kolom nomor_dasar pada tabel mutasi_stok
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang();

        $this->catat([
            'barang_id'   => $barang->id,
            'jumlah'      => 1,
            'sumber'      => 'pembelian',
            'nomor_dasar' => str_repeat('X', 61),
        ])->assertHasActionErrors(['nomor_dasar']);
    }

    // =====================================================================
    // SATU NOTA BERISI BANYAK BARANG
    // =====================================================================

    /**
     * Satu nota lazimnya memuat banyak jenis barang. Sebelumnya sumber, nomor
     * dokumen, dan tanggalnya diketik ulang untuk setiap barang.
     */
    public function test_satu_nota_mencatat_banyak_barang_sekaligus(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $satu = $this->buatBarang(stokFisik: 0);
        $dua  = $this->buatBarang(stokFisik: 5);
        $tiga = $this->buatBarang(stokFisik: 0);

        $this->catatNota([
            'sumber'      => 'pembelian',
            'nomor_dasar' => '34/F/HI/VIII/2026',
            'tanggal'     => now()->toDateString(),
        ], [
            ['barang_id' => $satu->id, 'jumlah' => 10, 'keterangan' => null],
            ['barang_id' => $dua->id,  'jumlah' => 4,  'keterangan' => 'Dus penyok, isi lengkap'],
            ['barang_id' => $tiga->id, 'jumlah' => 2,  'keterangan' => null],
        ])->assertHasNoActionErrors();

        $this->assertSame(3, MutasiStok::count());
        $this->assertSame(10, $satu->refresh()->stok_fisik);
        $this->assertSame(9, $dua->refresh()->stok_fisik);
        $this->assertSame(2, $tiga->refresh()->stok_fisik);

        // Ketiganya harus tampak sekelompok pada kartu kendali masing-masing.
        $this->assertSame(
            ['34/F/HI/VIII/2026'],
            MutasiStok::pluck('nomor_dasar')->unique()->values()->all(),
        );
    }

    /** Keterangan melekat pada barisnya sendiri, bukan pada seluruh nota. */
    public function test_keterangan_tercatat_per_barang(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $satu = $this->buatBarang(stokFisik: 0);
        $dua  = $this->buatBarang(stokFisik: 0);

        $this->catatNota([
            'sumber'      => 'pembelian',
            'nomor_dasar' => '35/F/HI/VIII/2026',
            'tanggal'     => now()->toDateString(),
        ], [
            ['barang_id' => $satu->id, 'jumlah' => 3, 'keterangan' => 'Dus penyok'],
            ['barang_id' => $dua->id,  'jumlah' => 3, 'keterangan' => null],
        ])->assertHasNoActionErrors();

        $this->assertSame('Dus penyok', MutasiStok::where('barang_id', $satu->id)->sole()->keterangan);
        $this->assertNull(MutasiStok::where('barang_id', $dua->id)->sole()->keterangan);
    }

    /**
     * Barang yang sama dua kali dalam satu nota menghasilkan dua transaksi
     * bernomor dasar dan bertanggal sama, yang kemudian mustahil dibedakan dan
     * tampak persis seperti pencatatan ganda.
     */
    public function test_barang_yang_sama_tidak_boleh_dua_kali_dalam_satu_nota(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $barang = $this->buatBarang(stokFisik: 0);

        $this->catatNota([
            'sumber'      => 'pembelian',
            'nomor_dasar' => '36/F/HI/VIII/2026',
            'tanggal'     => now()->toDateString(),
        ], [
            ['barang_id' => $barang->id, 'jumlah' => 3, 'keterangan' => null],
            ['barang_id' => $barang->id, 'jumlah' => 4, 'keterangan' => null],
        ])->assertHasActionErrors();

        $this->assertSame(0, MutasiStok::count());
        $this->assertSame(0, $barang->refresh()->stok_fisik);
    }

    /**
     * Nota adalah satu kejadian: tercatat separuh meninggalkan stok yang tidak
     * sesuai dokumen mana pun, dan itu sulit ditemukan justru karena
     * sebagiannya tampak benar.
     */
    public function test_satu_baris_gagal_membatalkan_seluruh_nota(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $sah = $this->buatBarang(stokFisik: 0);

        try {
            $this->catatNota([
                'sumber'      => 'pembelian',
                'nomor_dasar' => '37/F/HI/VIII/2026',
                'tanggal'     => now()->toDateString(),
            ], [
                ['barang_id' => $sah->id, 'jumlah' => 5, 'keterangan' => null],
                // Barang yang tidak ada menggagalkan layanan di tengah jalan.
                ['barang_id' => 999999, 'jumlah' => 5, 'keterangan' => null],
            ]);
        } catch (\Throwable $e) {
            // Kegagalannya memang diharapkan; yang diuji adalah akibatnya.
        }

        $this->assertSame(0, MutasiStok::count(), 'Baris pertama tidak boleh tertinggal tercatat.');
        $this->assertSame(0, $sah->refresh()->stok_fisik);
    }

    // =====================================================================
    // PENGULANG BARANG DAN KONFIRMASI SEBELUM MENYIMPAN
    // =====================================================================

    /** Pengulang barang pada formulir yang sedang terbuka. */
    private function pengulangBarang($uji): Repeater
    {
        $halaman = $uji->instance();

        return $halaman
            ->getSchema($halaman->getMountedActionSchemaName())
            ->getComponent(fn ($komponen) => $komponen instanceof Repeater);
    }

    /** Isian satu nota berisi dua barang, dipakai beberapa pengujian di bawah. */
    private function notaDuaBarang(int $satu, int $dua): array
    {
        return [
            'sumber'      => 'pembelian',
            'nomor_dasar' => '00002/UP_TUP/539159/2026',
            'tanggal'     => now()->toDateString(),
            'barang'      => [
                ['barang_id' => $satu, 'jumlah' => 30, 'keterangan' => null],
                ['barang_id' => $dua, 'jumlah' => 15, 'keterangan' => null],
            ],
        ];
    }

    public function test_urutan_baris_barang_tidak_dapat_diubah(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        $uji = Livewire::test(StokMasuk::class)->mountAction('catat');

        $this->assertFalse(
            $this->pengulangBarang($uji)->isReorderable(),
            'Urutan barang dalam satu nota tidak membawa arti, jadi tidak boleh dapat diseret.',
        );
    }

    public function test_baris_barang_terakhir_tidak_dapat_dihapus(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $satu = $this->buatBarang(stokFisik: 0);
        $dua  = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000777', 'nama_barang' => 'Barang Kedua']);

        $uji = Livewire::test(StokMasuk::class)->mountAction('catat');

        $this->assertFalse(
            $this->pengulangBarang($uji)->isDeletable(),
            'Dengan satu baris tersisa, tombol Hapus tidak boleh ditampilkan.',
        );

        $uji->setActionData($this->notaDuaBarang($satu->id, $dua->id));

        $this->assertTrue(
            $this->pengulangBarang($uji)->isDeletable(),
            'Dengan dua baris, Hapus kembali tersedia tanpa konfirmasi.',
        );
    }

    /** Baris yang dibuang dari formulir tidak boleh ikut masuk buku besar. */
    public function test_barang_yang_dihapus_dari_formulir_tidak_ikut_tercatat(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $satu    = $this->buatBarang(stokFisik: 0);
        $dua     = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000777', 'nama_barang' => 'Barang Kedua']);
        $dibuang = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000888', 'nama_barang' => 'Barang Dibuang']);

        $nota = $this->notaDuaBarang($satu->id, $dua->id);

        $uji = Livewire::test(StokMasuk::class)
            ->mountAction('catat')
            ->setActionData([
                ...$nota,
                'barang' => [
                    ...$nota['barang'],
                    ['barang_id' => $dibuang->id, 'jumlah' => 7, 'keterangan' => null],
                ],
            ])
            // Baris ketiga dihapus sebelum nota disimpan
            ->setActionData($nota)
            ->callMountedAction()
            ->callMountedAction();

        $uji->assertHasNoActionErrors();

        $this->assertSame(2, MutasiStok::count());
        $this->assertSame(0, $dibuang->refresh()->stok_fisik, 'Barang yang dihapus tidak boleh bertambah stoknya.');
        $this->assertSame(30, $satu->refresh()->stok_fisik);
        $this->assertSame(15, $dua->refresh()->stok_fisik);
    }

    /**
     * Simpan hanya membuka dialog; buku besar belum disentuh sama sekali.
     * Ringkasannya diturunkan dari isian formulir, sehingga yang dibaca
     * pengguna memang nota yang akan tercatat, bukan contoh.
     */
    public function test_simpan_membuka_konfirmasi_berisi_ringkasan_dan_belum_mencatat(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $satu = $this->buatBarang(stokFisik: 0);
        $dua  = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000777', 'nama_barang' => 'Barang Kedua']);

        $uji = Livewire::test(StokMasuk::class)
            ->callAction('catat', $this->notaDuaBarang($satu->id, $dua->id));

        $this->assertSame(
            ['catat', 'konfirmasiCatat'],
            collect($uji->get('mountedActions'))->pluck('name')->all(),
            'Dialog konfirmasi bertumpuk di atas formulir yang tetap terpasang.',
        );

        $this->assertSame(0, MutasiStok::count(), 'Belum ada yang tercatat sebelum "Ya, Simpan" ditekan.');

        $uji->assertMountedActionModalSee('Apakah data barang sudah sesuai?')
            ->assertMountedActionModalSee('00002/UP_TUP/539159/2026')
            ->assertMountedActionModalSee('Pembelian')
            ->assertMountedActionModalSee(now()->format('d-m-Y'))
            ->assertMountedActionModalSee('2 barang')
            ->assertMountedActionModalSee('45 unit')
            ->assertMountedActionModalSee('Ya, Simpan')
            ->assertMountedActionModalSee('Batal');
    }

    public function test_batal_pada_konfirmasi_mengembalikan_formulir_beserta_isinya(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $satu = $this->buatBarang(stokFisik: 0);
        $dua  = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000777', 'nama_barang' => 'Barang Kedua']);

        $nota = $this->notaDuaBarang($satu->id, $dua->id);

        $uji = Livewire::test(StokMasuk::class)->callAction('catat', $nota);

        // Tombol Batal menutup dialognya saja: begitulah modal Filament
        // memanggil unmountAction ketika aksi anak ditutup.
        $uji->unmountAction(false);

        $this->assertSame(
            ['catat'],
            collect($uji->get('mountedActions'))->pluck('name')->all(),
            'Formulirnya harus tetap terbuka.',
        );

        $uji->assertActionDataSet([
            'sumber'      => $nota['sumber'],
            'nomor_dasar' => $nota['nomor_dasar'],
            'tanggal'     => $nota['tanggal'],
        ]);

        $this->assertCount(2, $this->pengulangBarang($uji)->getRawState(), 'Kedua baris barang masih terisi.');
        $this->assertSame(0, MutasiStok::count(), 'Batal tidak boleh mencatat apa pun.');
        $this->assertSame(0, $satu->refresh()->stok_fisik);
    }

    public function test_ya_simpan_mencatat_nota_satu_barang(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 5);

        Livewire::test(StokMasuk::class)
            ->callAction('catat', [
                'sumber'      => 'pembelian',
                'nomor_dasar' => '00003/UP_TUP/539159/2026',
                'tanggal'     => now()->toDateString(),
                'barang'      => [['barang_id' => $barang->id, 'jumlah' => 20, 'keterangan' => null]],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $mutasi = MutasiStok::sole();
        $this->assertSame(20, $mutasi->jumlah);
        $this->assertSame('00003/UP_TUP/539159/2026', $mutasi->nomor_dasar);
        $this->assertSame(25, $barang->refresh()->stok_fisik);
    }

    public function test_ya_simpan_mencatat_nota_berisi_banyak_barang(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $satu = $this->buatBarang(stokFisik: 0);
        $dua  = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000777', 'nama_barang' => 'Barang Kedua']);

        Livewire::test(StokMasuk::class)
            ->callAction('catat', $this->notaDuaBarang($satu->id, $dua->id))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(2, MutasiStok::count(), 'Satu nota, satu baris buku besar untuk tiap barangnya.');
        $this->assertSame(
            ['00002/UP_TUP/539159/2026', '00002/UP_TUP/539159/2026'],
            MutasiStok::pluck('nomor_dasar')->all(),
        );
        $this->assertSame(30, $satu->refresh()->stok_fisik);
        $this->assertSame(15, $dua->refresh()->stok_fisik);
    }

    /**
     * Menekan "Ya, Simpan" dua kali — misalnya karena sambungan terasa lambat —
     * tidak boleh menghasilkan nota kembar. Dialognya ikut menutup formulir
     * begitu tersimpan, sehingga tekanan kedua tidak menemukan apa pun lagi.
     */
    public function test_konfirmasi_yang_ditekan_dua_kali_tidak_menggandakan_catatan(): void
    {
        $this->actingAs($this->buatPengguna('petugas_gudang'));
        $barang = $this->buatBarang(stokFisik: 0);

        $uji = Livewire::test(StokMasuk::class)
            ->callAction('catat', [
                'sumber'      => 'pembelian',
                'nomor_dasar' => '00004/UP_TUP/539159/2026',
                'tanggal'     => now()->toDateString(),
                'barang'      => [['barang_id' => $barang->id, 'jumlah' => 12, 'keterangan' => null]],
            ])
            ->callMountedAction();

        $this->assertSame([], $uji->get('mountedActions'), 'Formulir dan dialognya ikut tertutup setelah tersimpan.');

        $uji->callMountedAction();

        $this->assertSame(1, MutasiStok::count());
        $this->assertSame(12, $barang->refresh()->stok_fisik);
    }
}
