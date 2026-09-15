<?php

namespace Tests\Feature;

use App\Livewire\LoncengNotifikasi;
use App\Models\Notifikasi;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Menyingkirkan satu notifikasi dari panel lonceng.
 *
 * Panel lonceng adalah daftar hal yang masih perlu diperhatikan, sehingga
 * pengguna harus dapat membersihkannya satu per satu. Yang dijaga di sini
 * adalah batas tindakan itu: yang hilang hanya dari pandangan pengguna
 * tersebut, sedangkan barisnya tetap ada sebagai rekam jejak dan transaksi
 * yang dirujuknya sama sekali tidak tersentuh.
 */
class SembunyikanNotifikasiTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // tautan() pada komponen menanyakan kewenangan kepada resource, dan
        // pertanyaan itu hanya dapat dijawab di dalam sebuah panel Filament.
        Filament::setCurrentPanel('admin');
    }

    private function buatNotifikasi(User $penerima, string $judul, array $tambahan = []): Notifikasi
    {
        return Notifikasi::create([
            'user_id' => $penerima->id,
            'judul'   => $judul,
            'pesan'   => $judul . ' menunggu tindakan Anda.',
            'tipe'    => 'permintaan',
            'channel' => 'in_app',
            ...$tambahan,
        ]);
    }

    /**
     * Empat notifikasi, sebagaimana keadaan yang hendak dibersihkan pengguna.
     *
     * @return array{0:User,1:array<int,Notifikasi>}
     */
    private function empatNotifikasi(): array
    {
        $penerima = $this->buatPengguna('tim', $this->buatTim());

        $daftar = collect(range(1, 4))
            ->map(fn (int $i) => $this->buatNotifikasi($penerima, 'PB-2026-000' . $i))
            ->all();

        return [$penerima, $daftar];
    }

    // =====================================================================
    // YANG HILANG HANYA SATU
    // =====================================================================

    /**
     * Daftar disusun dari yang terbaru, sehingga baris kedua pada panel adalah
     * notifikasi ketiga yang diterbitkan. Yang diperiksa bukan sekadar
     * jumlahnya berkurang, melainkan susunannya sesudah itu: baris di bawahnya
     * harus naik mengisi tempatnya, dengan urutan yang tidak berubah.
     */
    public function test_hanya_baris_yang_disingkirkan_yang_hilang_dan_sisanya_naik(): void
    {
        [$penerima, [$n1, $n2, $n3, $n4]] = $this->empatNotifikasi();

        $lonceng = Livewire::actingAs($penerima)->test(LoncengNotifikasi::class);

        $this->assertSame(
            [$n4->id, $n3->id, $n2->id, $n1->id],
            $lonceng->instance()->daftar->pluck('id')->all(),
            'Panel menampilkan keempatnya, terbaru di atas.',
        );

        // Baris kedua pada panel.
        $lonceng->call('sembunyikan', $n3->id);

        $this->assertSame(
            [$n4->id, $n2->id, $n1->id],
            $lonceng->instance()->daftar->pluck('id')->all(),
            'Hanya baris kedua yang hilang; yang di bawahnya naik tanpa bertukar urutan.',
        );

        $lonceng->assertOk()
            ->assertSee('PB-2026-0004')
            ->assertSee('PB-2026-0002')
            ->assertSee('PB-2026-0001')
            ->assertDontSee('PB-2026-0003');
    }

    /**
     * Pemeriksaan berkala memakai kueri yang sama dengan daftarnya, sehingga
     * notifikasi yang sudah disingkirkan tidak dapat kembali lewat pintu itu —
     * baik sebagai baris panel maupun sebagai toast.
     */
    public function test_yang_disingkirkan_tidak_kembali_setelah_pemeriksaan_berkala(): void
    {
        [$penerima, [, , $n3]] = $this->empatNotifikasi();

        $lonceng = Livewire::actingAs($penerima)->test(LoncengNotifikasi::class);
        $lonceng->call('sembunyikan', $n3->id);

        $lonceng->call('periksa')->assertOk()->assertDontSee('PB-2026-0003');

        // Komponen yang baru dipasang, mewakili halaman yang dimuat ulang.
        Livewire::actingAs($penerima)
            ->test(LoncengNotifikasi::class)
            ->assertOk()
            ->assertDontSee('PB-2026-0003')
            ->assertSee('PB-2026-0004');
    }

    // =====================================================================
    // YANG TIDAK IKUT BERUBAH
    // =====================================================================

    /**
     * Tabel `notifikasi` merangkap rekam jejak pengiriman yang dibaca halaman
     * Riwayat. Menyingkirkan dari panel karena itu tidak boleh menyentuh
     * barisnya sendiri.
     */
    public function test_barisnya_tetap_ada_dan_masih_terbaca_riwayat(): void
    {
        [$penerima, [, , $n3]] = $this->empatNotifikasi();

        Livewire::actingAs($penerima)->test(LoncengNotifikasi::class)->call('sembunyikan', $n3->id);

        $this->assertDatabaseHas('notifikasi', ['id' => $n3->id, 'judul' => 'PB-2026-0003']);
        $this->assertNotNull($n3->fresh()->disembunyikan_at);

        $this->assertSame(
            4,
            Notifikasi::query()->where('user_id', $penerima->id)->count(),
            'Riwayat membaca tabelnya tanpa penyaring panel, jadi keempatnya tetap terlihat di sana.',
        );
    }

    /** Disingkirkan tanpa dibuka bukan berarti sudah dibaca. */
    public function test_menyingkirkan_tidak_menandai_notifikasi_terbaca(): void
    {
        [$penerima, [, , $n3]] = $this->empatNotifikasi();

        Livewire::actingAs($penerima)->test(LoncengNotifikasi::class)->call('sembunyikan', $n3->id);

        $this->assertNull($n3->fresh()->dibaca_at);
    }

    /**
     * Badge menghitung notifikasi yang masih menuntut perhatian. Angka yang
     * memuat notifikasi yang sudah tidak tampak di panel tidak dapat
     * ditindaklanjuti pengguna, sehingga ia harus ikut turun.
     */
    public function test_badge_mengikuti_notifikasi_yang_masih_aktif(): void
    {
        [$penerima, [, , $n3]] = $this->empatNotifikasi();

        $lonceng = Livewire::actingAs($penerima)->test(LoncengNotifikasi::class);

        $this->assertSame(4, $lonceng->instance()->jumlahBelumDibaca);

        $lonceng->call('sembunyikan', $n3->id);

        $this->assertSame(3, $lonceng->instance()->jumlahBelumDibaca);
    }

    /**
     * Notifikasi hanyalah pemberitahuan atas sebuah transaksi; menyingkirkan
     * pemberitahuannya tidak boleh menyentuh transaksi yang diberitahukan.
     */
    public function test_permintaan_yang_dirujuk_tidak_berubah(): void
    {
        $tim      = $this->buatTim();
        $penerima = $this->buatPengguna('tim', $tim);
        $barang   = $this->buatBarang();

        $permintaan = $this->buatPermintaan(
            tim: $tim,
            pengaju: $penerima,
            rincian: [['barang' => $barang, 'diminta' => 5]],
            status: 'menunggu_ketua',
        );

        $notifikasi = $this->buatNotifikasi($penerima, $permintaan->kode_permintaan, [
            'referensi_tabel' => 'permintaan_barang',
            'referensi_id'    => $permintaan->id,
        ]);

        Livewire::actingAs($penerima)->test(LoncengNotifikasi::class)->call('sembunyikan', $notifikasi->id);

        $segar = $permintaan->fresh('detail');

        $this->assertSame('menunggu_ketua', $segar->status);
        $this->assertCount(1, $segar->detail);
        $this->assertSame(5, (int) $segar->detail->first()->jumlah_diminta);
    }

    /**
     * Sisa notifikasi tetap dapat diperlakukan seperti biasa, dan "Tandai semua
     * dibaca" tetap pekerjaan yang lain: ia mengosongkan badge, bukan panelnya.
     */
    public function test_notifikasi_lain_tetap_berfungsi(): void
    {
        [$penerima, [$n1, $n2, $n3, $n4]] = $this->empatNotifikasi();

        $lonceng = Livewire::actingAs($penerima)->test(LoncengNotifikasi::class);

        $lonceng->call('sembunyikan', $n3->id);
        $lonceng->call('tandaiDibaca', $n4->id);

        $this->assertNotNull($n4->fresh()->dibaca_at);
        $this->assertNull($n4->fresh()->disembunyikan_at, 'Membaca bukan menyingkirkan.');

        $lonceng->call('tandaiSemuaDibaca');

        $this->assertSame(0, $lonceng->instance()->jumlahBelumDibaca);
        $this->assertSame(
            [$n4->id, $n2->id, $n1->id],
            $lonceng->instance()->daftar->pluck('id')->all(),
            'Menandai semua dibaca tidak mengosongkan panel.',
        );
        $this->assertNull(
            $n3->fresh()->dibaca_at,
            'Yang sudah disingkirkan berada di luar jangkauan tindakan panel.',
        );
    }

    /**
     * Penulisannya melewati kueri yang sudah tersaring kepemilikan, sehingga id
     * milik orang lain tidak dapat dipakai untuk menyingkirkan notifikasinya.
     */
    public function test_tidak_dapat_menyingkirkan_notifikasi_milik_pengguna_lain(): void
    {
        [$penerima] = $this->empatNotifikasi();
        $orangLain  = $this->buatPengguna('tim', $this->buatTim('Statistik Produksi'));

        $milikOrangLain = $this->buatNotifikasi($orangLain, 'PB-2026-9999');

        Livewire::actingAs($penerima)
            ->test(LoncengNotifikasi::class)
            ->call('sembunyikan', $milikOrangLain->id);

        $this->assertNull($milikOrangLain->fresh()->disembunyikan_at);
    }
}
