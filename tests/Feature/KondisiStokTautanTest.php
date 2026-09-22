<?php

namespace Tests\Feature;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Widgets\KondisiStok;
use App\Models\Kategori;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-013: widget Kondisi Stok pada dasbor Petugas Gudang.
 *
 * Nama kategori dan judul panel menaut ke /admin/barang-persediaans, yang
 * tertutup bagi Petugas Gudang (403). Bagi Gudang tautan itu kini tidak ada
 * (teks biasa); bagi Kasubbag tidak berubah. Angka dan isi panel sama.
 */
class KondisiStokTautanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /** Kategori yang dibuat sendiri; helper buatBarang() menambah satu kategori lagi. */
    private const JUMLAH_KATEGORI = 15;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (range(1, self::JUMLAH_KATEGORI) as $i) {
            $kategori = Kategori::create([
                'kode_kategori' => 'PS' . $i, 'kode_akun' => '117' . $i, 'nama_kategori' => 'Kategori Uji ' . $i, 'tipe' => 'persediaan',
            ]);
            $this->buatBarang(stokFisik: 10 + $i, stokHold: $i % 4, tambahan: ['kategori_id' => $kategori->id]);
        }
    }

    private function tautanBarang(string $html): int
    {
        return preg_match_all('#<a\b[^>]*href="[^"]*barang-persediaans[^"]*"#', $html);
    }

    public function test_petugas_gudang_tidak_lagi_melihat_tautan_ke_barang_persediaan(): void
    {
        $this->actingAs($this->lengkapiAkun($this->buatPengguna('petugas_gudang')));

        $html = Livewire::test(KondisiStok::class)->html();

        $this->assertSame(0, $this->tautanBarang($html), 'Tidak boleh ada tautan ke /admin/barang-persediaans (dulu 16).');
        $this->assertSame(0, preg_match_all('#<a[\s>]#', $html), 'Panel ini tidak memuat elemen tautan sama sekali bagi Gudang.');
    }

    public function test_angka_dan_isi_panel_gudang_sama_dengan_kasubbag(): void
    {
        $this->actingAs($this->lengkapiAkun($this->buatPengguna('petugas_gudang')));
        $gudang = Livewire::test(KondisiStok::class);
        $daftarGudang = $gudang->instance()->kategori;

        $this->actingAs($this->lengkapiAkun($this->buatPengguna('kasubbag')));
        $kasubbag = Livewire::test(KondisiStok::class);
        $daftarKasubbag = $kasubbag->instance()->kategori;

        $this->assertCount(Kategori::where('tipe', 'persediaan')->count(), $daftarGudang);
        $this->assertEquals($daftarKasubbag->pluck('nama', 'id'), $daftarGudang->pluck('nama', 'id'));
        $this->assertEquals($daftarKasubbag->pluck('fisik', 'id'), $daftarGudang->pluck('fisik', 'id'));
        $this->assertEquals($daftarKasubbag->pluck('terkunci', 'id'), $daftarGudang->pluck('terkunci', 'id'));
        $gudang->assertSee('Kategori Uji 1')->assertSee('Kondisi Stok per Kategori')->assertSee('Stok fisik');
    }

    public function test_kasubbag_tetap_melihat_seluruh_tautan_dan_semuanya_terbuka(): void
    {
        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));
        $this->actingAs($kasubbag);

        $html = Livewire::test(KondisiStok::class)->html();

        // Judul panel + satu tautan per kategori.
        $this->assertSame(Kategori::where('tipe', 'persediaan')->count() + 1, $this->tautanBarang($html));

        preg_match_all('#<a\b[^>]*href="([^"]*barang-persediaans[^"]*)"#', $html, $cocok);
        foreach (array_unique($cocok[1]) as $tautan) {
            auth()->forgetGuards();
            $this->actingAs($kasubbag)->get(html_entity_decode($tautan))->assertOk();
        }
    }

    public function test_gudang_memang_tidak_boleh_membuka_daftar_barang_persediaan(): void
    {
        $this->actingAs($this->lengkapiAkun($this->buatPengguna('petugas_gudang')));

        $this->get(BarangPersediaanResource::getUrl('index'))->assertForbidden();
    }
}
