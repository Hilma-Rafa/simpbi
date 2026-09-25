<?php

namespace Tests\Feature;

use App\Filament\Pages\GantiKataSandi;
use App\Filament\Pages\LengkapiAkun;
use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use App\Filament\Resources\AsetTetaps\Pages\CreateAsetTetap;
use App\Filament\Resources\AsetTetaps\Pages\EditAsetTetap;
use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Resources\BarangPersediaans\Pages\CreateBarangPersediaan;
use App\Filament\Resources\BarangPersediaans\Pages\EditBarangPersediaan;
use App\Filament\Resources\Kategoris\KategoriResource;
use App\Filament\Resources\Kategoris\Pages\CreateKategori;
use App\Filament\Resources\Kategoris\Pages\EditKategori;
use App\Filament\Resources\Tims\Pages\CreateTim;
use App\Filament\Resources\Tims\Pages\EditTim;
use App\Filament\Resources\Tims\TimResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Tombol "Kembali" pada kesepuluh halaman Buat dan Ubah data induk (bagian E).
 * Menuju halaman daftar tanpa penyaring (G-9); tidak dipasang pada halaman
 * penjaga Ganti Kata Sandi dan Lengkapi Akun, dan tidak membuka jalan pintas
 * melewati penjaga itu.
 */
class TombolKembaliTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->buatPengguna('admin', null, ['email' => 'admin.kembali@bps.go.id']));
    }

    /** @return array<string,array{0:class-string,1:class-string,2:?string}> */
    public static function halaman(): array
    {
        return [
            'Buat Kategori'          => [CreateKategori::class, KategoriResource::class, null],
            'Ubah Kategori'          => [EditKategori::class, KategoriResource::class, 'kategori'],
            'Buat Barang Persediaan' => [CreateBarangPersediaan::class, BarangPersediaanResource::class, null],
            'Ubah Barang Persediaan' => [EditBarangPersediaan::class, BarangPersediaanResource::class, 'barang'],
            'Buat Aset Tetap'        => [CreateAsetTetap::class, AsetTetapResource::class, null],
            'Ubah Aset Tetap'        => [EditAsetTetap::class, AsetTetapResource::class, 'aset'],
            'Buat Tim Kerja'         => [CreateTim::class, TimResource::class, null],
            'Ubah Tim Kerja'         => [EditTim::class, TimResource::class, 'tim'],
            'Buat Pengguna'          => [CreateUser::class, UserResource::class, null],
            'Ubah Pengguna'          => [EditUser::class, UserResource::class, 'pengguna'],
        ];
    }

    private function rekaman(string $jenis): Model
    {
        return match ($jenis) {
            'kategori' => $this->buatBarang()->kategori,
            'barang'   => $this->buatBarang(),
            'aset'     => $this->buatAset(),
            'tim'      => $this->buatTim(),
            'pengguna' => $this->buatPengguna('tim', $this->buatTim()),
        };
    }

    #[DataProvider('halaman')]
    public function test_halaman_memiliki_tombol_kembali_ke_daftar(string $kelas, string $resource, ?string $jenis): void
    {
        $parameter = $jenis ? ['record' => $this->rekaman($jenis)->getRouteKey()] : [];

        Livewire::test($kelas, $parameter)
            ->assertActionExists('kembali')
            ->assertActionHasLabel('kembali', 'Kembali')
            ->assertActionHasIcon('kembali', 'heroicon-m-arrow-left')
            ->assertActionHasColor('kembali', 'gray')
            ->assertActionHasUrl('kembali', $resource::getUrl('index'));
    }

    public function test_halaman_penjaga_tidak_memiliki_tombol_kembali(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.penjaga@bps.go.id', 'harus_ganti_sandi' => true]);
        $this->actingAs($pengguna);

        Livewire::test(GantiKataSandi::class)->assertActionDoesNotExist('kembali');
        Livewire::test(LengkapiAkun::class)->assertActionDoesNotExist('kembali');
    }

    /**
     * Tombol Kembali hanyalah tautan GET ke halaman daftar; penjaga kata sandi
     * tetap memeriksa permintaan itu dan mengalihkannya.
     */
    public function test_tujuan_tombol_kembali_tetap_dijaga_penjaga_kata_sandi(): void
    {
        $pengguna = $this->lengkapiAkun($this->buatPengguna('admin', null, ['email' => 'admin.wajib.ganti@bps.go.id']));
        $pengguna->forceFill(['harus_ganti_sandi' => true])->save();

        $this->actingAs($pengguna->refresh())
            ->get(KategoriResource::getUrl('index'))
            ->assertRedirect(GantiKataSandi::getUrl());
    }
}
