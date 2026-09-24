<?php

namespace Tests\Feature;

use App\Filament\Resources\AsetTetaps\Pages\CreateAsetTetap;
use App\Filament\Resources\AsetTetaps\Pages\EditAsetTetap;
use App\Filament\Resources\AsetTetaps\Pages\ListAsetTetaps;
use App\Models\AsetTetap;
use App\Models\Kategori;
use App\Models\RiwayatPenempatanAset;
use App\Services\MutasiAsetService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Riwayat penempatan aset tetap (temuan audit T-10).
 *
 * Yang dijaga di sini bukan tampilannya, melainkan keutuhan jejak: sebuah aset
 * harus punya titik awal penempatan sejak ia dicatat, sebab tanpa titik itu
 * rentang penempatan pertamanya — yang biasanya paling panjang — tidak pernah
 * dapat dihitung. Dua kekeliruan yang paling merugikan dijaga berpasangan:
 * jejak yang tidak pernah terbentuk, dan jejak yang terbentuk dua kali.
 *
 * Diuji pula bahwa aset tanpa tim penempatan tidak memperoleh baris apa pun.
 * Mengarang tim bagi aset yang memang belum ditempatkan berarti menuliskan
 * penempatan yang tidak pernah terjadi, dan itu lebih buruk daripada riwayat
 * yang kosong.
 */
class RiwayatPenempatanAsetTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mengesahkan BAST menulis PDF; jangan sampai jatuh ke disk sungguhan.
        Storage::fake('public');
        Storage::fake('local');

        Filament::setCurrentPanel('admin');

        $this->actingAs($this->buatPengguna('admin'));
    }

    /** Isian formulir Aset Tetap secukupnya, dengan atau tanpa tim penempatan. */
    private function isianAset(?int $timId, array $tambahan = []): array
    {
        static $urutan = 0;
        $urutan++;

        $kategori = Kategori::firstOrCreate(
            ['kode_kategori' => 'PM'],
            ['kode_akun' => '1.3.2', 'nama_kategori' => 'Peralatan dan Mesin', 'tipe' => 'aset_tetap'],
        );

        return [
            'nup'               => 'NUP-UI-' . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT),
            'nama_aset'         => 'Laptop Uji ' . $urutan,
            'kategori_id'       => $kategori->id,
            'tim_penempatan_id' => $timId,
            'kondisi'           => 'baik',
            'status_aktif'      => true,
            'sumber_data'       => 'manual',
            ...$tambahan,
        ];
    }

    // =====================================================================
    // PEMBENTUKAN RIWAYAT AWAL
    // =====================================================================

    public function test_aset_yang_dibuat_lewat_antarmuka_memperoleh_penempatan_awal(): void
    {
        $tim = $this->buatTim('Statistik Sosial');

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset($tim->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset = AsetTetap::latest('id')->firstOrFail();

        $this->assertDatabaseHas('riwayat_penempatan_aset', [
            'aset_id'         => $aset->id,
            'tim_id'          => $tim->id,
            'jenis'           => 'penempatan_awal',
            'tanggal_selesai' => null,
            'bast_id'         => null,
        ]);
    }

    /**
     * Tanggal mulainya adalah tanggal penempatan itu dicatat.
     *
     * Ketika aset ditambahkan lengkap dengan tim kerjanya, tanggal itu sama
     * dengan tanggal pencatatan asetnya — keduanya terjadi bersamaan.
     */
    public function test_tanggal_mulai_mengikuti_tanggal_penempatan_dicatat(): void
    {
        $tim = $this->buatTim();

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset($tim->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset    = AsetTetap::latest('id')->firstOrFail();
        $riwayat = $aset->riwayatPenempatan()->firstOrFail();

        $this->assertSame(
            $aset->created_at->toDateString(),
            $riwayat->tanggal_mulai->toDateString(),
        );
    }

    /**
     * Aset yang dicatat jauh sebelum ditempatkan tidak boleh memundurkan
     * riwayatnya ke tanggal pembuatan aset.
     *
     * Inilah bug yang sempat ada: `tanggal_mulai` diambil dari `created_at`,
     * sehingga aset yang dicatat Januari lalu ditempatkan hari ini menyatakan
     * tim tersebut memegangnya sejak Januari — dan kolom Lama pada dialog
     * riwayat melaporkan ratusan hari yang tidak pernah terjadi. Tanggal aset
     * dan tanggal penempatan di sini sengaja dibuat berjauhan supaya bug itu
     * tidak dapat lolos lagi.
     */
    public function test_penempatan_menyusul_tidak_memundurkan_tanggal_mulai(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $aset = $this->buatAset();

        $aset->forceFill(['created_at' => now()->subMonths(8)])->save();

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['tim_penempatan_id' => $tim->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $riwayat = $aset->refresh()->riwayatPenempatan()->firstOrFail();

        $this->assertSame(
            now()->toDateString(),
            $riwayat->tanggal_mulai->toDateString(),
            'Penempatan dicatat hari ini, bukan pada tanggal aset dibuat.',
        );
        $this->assertNotSame(
            $aset->created_at->toDateString(),
            $riwayat->tanggal_mulai->toDateString(),
        );
        $this->assertSame(0, (int) $riwayat->tanggal_mulai->diffInDays(now()));
    }

    /** Hanya satu baris, sekali pun. */
    public function test_hanya_satu_riwayat_awal_yang_terbentuk(): void
    {
        $tim = $this->buatTim();

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset($tim->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset = AsetTetap::latest('id')->firstOrFail();

        $this->assertSame(1, $aset->riwayatPenempatan()->count());
    }

    /**
     * Batch F: Tim Kerja kini wajib diisi pada form Buat, sehingga aset tanpa
     * penempatan tidak lagi bisa lahir lewat form ini sama sekali — form
     * menolak submit-nya (bukan lagi diterima dengan penempatan kosong).
     * BAST tidak dapat dibuat untuk aset tanpa penempatan (MA-6/MA-11), jadi
     * membiarkan aset lahir tanpa penempatan hanya menunda masalah.
     *
     * Skenario "aset tanpa tim tidak memperoleh riwayat" bagi data LAMA (yang
     * masih mungkin ada di database sebelum Batch F) tetap diuji lewat jalur
     * lain — lihat test_aset_lama_bertim_tanpa_riwayat_tidak_ditambal_saat_disunting
     * dan pengaman `catatPenempatanAwal()` yang tidak disentuh batch ini.
     */
    public function test_form_buat_menolak_submit_tanpa_tim_penempatan(): void
    {
        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset(null))
            ->call('create')
            ->assertHasFormErrors(['tim_penempatan_id' => 'required']);

        $this->assertSame(0, AsetTetap::count());
        $this->assertSame(0, RiwayatPenempatanAset::count());
    }

    /** Pemanggilan berulang tidak menambah baris kedua. */
    public function test_pemanggilan_berulang_tidak_menghasilkan_baris_kembar(): void
    {
        $tim  = $this->buatTim();
        $aset = $this->buatAset($tim);

        $this->assertNotNull($aset->catatPenempatanAwal());
        $this->assertNull($aset->catatPenempatanAwal(), 'Pemanggilan kedua harus ditolak.');
        $this->assertNull($aset->catatPenempatanAwal());

        $this->assertSame(1, $aset->riwayatPenempatan()->count());
    }

    // =====================================================================
    // TIM KERJA YANG BARU TERISI LEWAT PENYUNTINGAN
    // =====================================================================

    /**
     * Aset lama boleh sudah tercatat tanpa tim kerja (dari sebelum Batch F,
     * atau lewat impor/API — form Buat sendiri kini mewajibkannya), lalu
     * ditempatkan kemudian lewat Ubah. Pengisian tim yang pertama itulah
     * penempatan awalnya. Aset dibuat lewat `buatAset()` (langsung ke basis
     * data), bukan lewat form Buat, sebab form Buat kini menolak tim kosong.
     */
    public function test_mengisi_tim_pertama_kali_membentuk_penempatan_awal(): void
    {
        $tim = $this->buatTim('Statistik Sosial');

        $aset = $this->buatAset();
        $this->assertSame(0, $aset->riwayatPenempatan()->count());

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['tim_penempatan_id' => $tim->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('riwayat_penempatan_aset', [
            'aset_id'         => $aset->id,
            'tim_id'          => $tim->id,
            'jenis'           => 'penempatan_awal',
            'tanggal_selesai' => null,
            'bast_id'         => null,
        ]);

        $this->assertSame(1, $aset->refresh()->riwayatPenempatan()->count());
    }

    /**
     * Penyuntingan berikutnya tidak menambah baris kedua. Aset dibuat lewat
     * `buatAset()` (data lama tanpa tim), sebab form Buat kini mewajibkan
     * tim kerja (Batch F).
     */
    public function test_penyuntingan_berikutnya_tidak_menambah_riwayat(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');

        $aset = $this->buatAset();

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['tim_penempatan_id' => $tim->id])
            ->call('save')
            ->assertHasNoFormErrors();

        // Penyuntingan kedua: mengganti tim yang sudah terisi.
        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['tim_penempatan_id' => $lain->id])
            ->call('save')
            ->assertHasNoFormErrors();

        // Penyuntingan ketiga: mengubah kolom lain saja.
        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['nama_aset' => 'Nama Yang Sudah Diubah'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $aset->refresh()->riwayatPenempatan()->count());
        $this->assertSame(
            $tim->id,
            $aset->riwayatPenempatan()->firstOrFail()->tim_id,
            'Penempatan awal harus tetap menunjuk tim kerja yang pertama.',
        );
    }

    /**
     * Perpindahan aset antar tim kerja tetap urusan BAST.
     *
     * Mengganti tim lewat penyuntingan biasa tidak boleh menghasilkan baris
     * riwayat apa pun, sebab itu akan menjadikan penyuntingan sebagai jalan
     * pintas yang menggantikan alur mutasi.
     */
    public function test_mengganti_tim_lewat_penyuntingan_tidak_dicatat_sebagai_mutasi(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $lain = $this->buatTim('Statistik Distribusi');

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset($tim->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset = AsetTetap::latest('id')->firstOrFail();

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['tim_penempatan_id' => $lain->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $aset->refresh()->riwayatPenempatan()->count());
        $this->assertSame(
            0,
            $aset->riwayatPenempatan()->where('jenis', 'mutasi')->count(),
            'Penyuntingan tidak boleh menerbitkan baris mutasi.',
        );
        $this->assertSame($tim->id, $aset->tim_penempatan_id, 'Penempatan tidak berpindah lewat penyuntingan (F-002).');
    }

    /**
     * Aset lama yang sudah bertim tetapi belum berriwayat tidak ditambal
     * diam-diam ketika formulirnya disimpan.
     *
     * Aset semacam ini ada pada pemasangan yang berjalan sebelum perbaikan ini.
     * Pengisian riwayatnya adalah prosedur tersendiri, bukan efek samping
     * menyimpan formulir.
     */
    public function test_aset_lama_bertim_tanpa_riwayat_tidak_ditambal_saat_disunting(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $aset = $this->buatAset($tim);

        $this->assertSame(0, $aset->riwayatPenempatan()->count());

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm(['nama_aset' => 'Nama Yang Sudah Diubah'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            0,
            $aset->refresh()->riwayatPenempatan()->count(),
            'Riwayat aset lama hanya boleh diisi lewat prosedur tersendiri.',
        );
        $this->assertSame('Nama Yang Sudah Diubah', $aset->nama_aset);
    }

    /**
     * Menyimpan ulang formulir tidak menambah riwayat.
     *
     * Aset yang timnya sudah terisi sejak awal sudah punya penempatan awalnya,
     * sehingga penyuntingan sengaja tidak menyentuh riwayat sama sekali.
     */
    public function test_menyunting_aset_tidak_menambah_riwayat(): void
    {
        $tim   = $this->buatTim('Statistik Sosial');
        $lain  = $this->buatTim('Statistik Distribusi');

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset($tim->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset = AsetTetap::latest('id')->firstOrFail();

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm([
                'nama_aset'         => 'Nama Yang Sudah Diubah',
                'tim_penempatan_id' => $lain->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $aset->refresh()->riwayatPenempatan()->count());
        $this->assertSame(
            $tim->id,
            $aset->riwayatPenempatan()->firstOrFail()->tim_id,
            'Riwayat yang sudah tercatat tidak boleh ikut berubah.',
        );
    }

    // =====================================================================
    // TAMPILAN RIWAYAT
    // =====================================================================

    /**
     * Isi dialog diuji dengan merender partial-nya langsung.
     *
     * Filament merakit dialog di sisi peramban, sehingga isinya tidak pernah
     * muncul pada HTML yang dikembalikan uji Livewire. Karena itu ketersediaan
     * tombolnya dan isi tampilannya diuji terpisah: yang pertama memastikan
     * jalannya ada, yang kedua memastikan yang dibaca pengguna benar.
     */
    private function tampilanRiwayat(AsetTetap $aset): string
    {
        return view('filament.partials.riwayat-penempatan-aset', [
            'aset'    => $aset,
            'riwayat' => $aset->riwayatPenempatan()->with(['tim', 'bast'])->get(),
        ])->render();
    }

    public function test_aksi_riwayat_penempatan_tersedia_pada_daftar_aset(): void
    {
        $aset = $this->buatAset($this->buatTim());
        $aset->catatPenempatanAwal();

        Livewire::test(ListAsetTetaps::class)
            ->assertActionVisible(TestAction::make('riwayatPenempatan')->table($aset));
    }

    public function test_tampilan_riwayat_memuat_tim_tanggal_dan_kejadian(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $aset = $this->buatAset($tim);
        $aset->catatPenempatanAwal();

        $html = $this->tampilanRiwayat($aset);

        $this->assertStringContainsString('Statistik Sosial', $html);
        $this->assertStringContainsString('Penempatan Awal', $html);
        $this->assertStringContainsString($aset->nup, $html);
        $this->assertStringContainsString(now()->format('d-m-Y'), $html);
        $this->assertStringContainsString('Sedang berlaku', $html);
    }

    /**
     * Baris mutasi ikut terbaca beserta nomor BAST yang menjadi dasarnya.
     *
     * Baris ini dibuat oleh alur BAST yang sudah ada dan tidak disentuh
     * perbaikan ini; yang diuji hanyalah bahwa tampilan riwayat membacanya.
     *
     * Urutan alur dibalik (A): aset kini berpindah pada langkah Konfirmasi
     * Penerimaan, bukan lagi pada Sahkan — baris riwayat mutasi karena itu
     * dibentuk oleh konfirmasi(), bukan sahkan().
     */
    public function test_tampilan_riwayat_memuat_baris_mutasi_beserta_nomor_bast(): void
    {
        $asal   = $this->buatTim('Sub Bagian Umum');
        $tujuan = $this->buatTim('Statistik Distribusi');
        $ketuaTujuan = $this->buatPengguna('ketua_tim', $tujuan);
        $bast   = $this->buatBast($asal, $tujuan, $this->buatPengguna('petugas_gudang'), status: 'menunggu_konfirmasi');

        app(MutasiAsetService::class)->konfirmasi($bast, $ketuaTujuan->id);

        $aset = AsetTetap::findOrFail($bast->aset_id);
        $html = $this->tampilanRiwayat($aset);

        $this->assertStringContainsString('Mutasi', $html);
        $this->assertStringContainsString($bast->nomor_bast, $html);
        $this->assertStringContainsString('Statistik Distribusi', $html);
    }

    /**
     * Aset lama yang belum berriwayat tetap dapat dibuka.
     *
     * Tampilannya menerangkan mengapa kosong, bukan gagal — aset semacam ini
     * memang ada, yaitu yang tercatat sebelum perbaikan ini berlaku.
     */
    public function test_tampilan_riwayat_menerangkan_keadaan_kosong(): void
    {
        $aset = $this->buatAset($this->buatTim());

        $this->assertStringContainsString(
            'belum memiliki riwayat penempatan',
            $this->tampilanRiwayat($aset),
        );
    }

    /** Aset yang belum ditempatkan diberi keterangan yang berbeda. */
    public function test_tampilan_riwayat_membedakan_aset_yang_belum_ditempatkan(): void
    {
        $aset = $this->buatAset();

        $this->assertStringContainsString(
            'belum ditempatkan pada tim kerja mana pun',
            $this->tampilanRiwayat($aset),
        );
    }

    /**
     * Tampilan riwayat memakai istilah "Tim Kerja", bukan "Unit".
     *
     * Pemeriksaan dibatasi pada tampilan yang memang dibuat T-10. Penyeragaman
     * istilah di seluruh sistem adalah temuan tersendiri dan tidak disentuh
     * dari sini.
     */
    public function test_tampilan_riwayat_memakai_istilah_tim_kerja(): void
    {
        $aset = $this->buatAset($this->buatTim());
        $aset->catatPenempatanAwal();

        $html = $this->tampilanRiwayat($aset);

        $this->assertStringContainsString('Tim Kerja', $html);
        $this->assertStringNotContainsString('Unit Penempatan', $html);
        $this->assertStringNotContainsString('Unit Kerja', $html);
    }

    /**
     * Baris riwayat yang sudah tercatat tidak boleh ikut berubah ketika
     * asetnya disunting — termasuk baris yang dibuat di luar antarmuka.
     */
    public function test_riwayat_yang_sudah_ada_tidak_berubah_saat_aset_disunting(): void
    {
        $tim  = $this->buatTim('Statistik Sosial');
        $aset = $this->buatAset($tim);

        $riwayat = RiwayatPenempatanAset::create([
            'aset_id'       => $aset->id,
            'tim_id'        => $tim->id,
            'tanggal_mulai' => now()->subMonths(6)->toDateString(),
            'jenis'         => 'penempatan_awal',
        ]);

        // Tanggal dibandingkan sebagai teks, bukan sebagai objek Carbon —
        // assertSame pada objek membandingkan identitasnya, bukan nilainya.
        $ringkas = fn (RiwayatPenempatanAset $r): array => [
            'tim_id'          => $r->tim_id,
            'tanggal_mulai'   => $r->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $r->tanggal_selesai?->toDateString(),
            'jenis'           => $r->jenis,
            'bast_id'         => $r->bast_id,
        ];

        $semula = $ringkas($riwayat);

        Livewire::test(EditAsetTetap::class, ['record' => $aset->getKey()])
            ->fillForm([
                'nama_aset'         => 'Nama Yang Sudah Diubah',
                'tim_penempatan_id' => $this->buatTim('Statistik Distribusi')->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $aset->refresh()->riwayatPenempatan()->count());
        $this->assertSame($semula, $ringkas($riwayat->refresh()));
    }

    // =====================================================================
    // PERLINDUNGAN PENGHAPUSAN TETAP BERLAKU
    // =====================================================================

    /**
     * Riwayat awal yang baru terbentuk langsung menjadikan asetnya terlindung.
     *
     * Ini akibat yang disengaja: begitu sebuah aset punya jejak penempatan,
     * menghapusnya berarti memusnahkan jejak itu secara berantai.
     */
    public function test_aset_berriwayat_awal_tidak_dapat_dihapus(): void
    {
        $tim = $this->buatTim();

        Livewire::test(CreateAsetTetap::class)
            ->fillForm($this->isianAset($tim->id))
            ->call('create')
            ->assertHasNoFormErrors();

        $aset = AsetTetap::latest('id')->firstOrFail();

        $this->assertTrue($aset->punyaRiwayat());
        $this->assertFalse($aset->delete());

        $this->assertDatabaseHas('aset_tetap', ['id' => $aset->id]);
        $this->assertSame(1, $aset->riwayatPenempatan()->count());
    }

    /**
     * Aset yang memang belum berriwayat tetap dapat dihapus seperti semula.
     * Dibuat lewat `buatAset()` (data lama tanpa tim), sebab form Buat kini
     * mewajibkan tim kerja (Batch F).
     */
    public function test_aset_tanpa_riwayat_masih_dapat_dihapus(): void
    {
        $aset = $this->buatAset();

        $this->assertFalse($aset->punyaRiwayat());
        $this->assertTrue($aset->delete());

        $this->assertDatabaseMissing('aset_tetap', ['id' => $aset->id]);
    }
}
