<?php

namespace Tests\Feature;

use App\Filament\Pages\KatalogBarang;
use App\Filament\Pages\Pengaturan;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\PermintaanBarang;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * A-015: nama dan NIP dikunci pada Pengaturan.
 *
 * Keduanya tercetak pada dokumen resmi, sehingga hanya Administrator yang
 * mengubahnya, lewat menu Pengguna. Nomor WhatsApp dan email tetap dapat
 * disimpan sendiri, dan Nama Pemohon pada Katalog tetap bebas diisi.
 */
class NamaNipTerkunciPengaturanTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const KETERANGAN = 'Diubah oleh Administrator melalui menu Pengguna.';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    /** @return array<string, string|null> peran => tim */
    private function semuaPeran(): array
    {
        return ['admin' => false, 'kasubbag' => false, 'petugas_gudang' => false, 'ketua_tim' => true, 'tim' => true];
    }

    public function test_nama_dan_nip_tampil_tetapi_terkunci_untuk_semua_peran_termasuk_admin(): void
    {
        foreach ($this->semuaPeran() as $peran => $butuhTim) {
            $pengguna = $this->buatPengguna($peran, $butuhTim ? $this->buatTim('Tim ' . $peran) : null);
            $this->actingAs($pengguna);

            Livewire::test(Pengaturan::class)
                ->assertFormFieldIsDisabled('name')
                ->assertFormFieldIsDisabled('nip')
                ->assertFormSet(['name' => $pengguna->name, 'nip' => $pengguna->nip])
                ->assertSee(self::KETERANGAN);
        }
    }

    public function test_muatan_yang_dimodifikasi_tidak_mengubah_nama_dan_nip_untuk_setiap_peran(): void
    {
        foreach ($this->semuaPeran() as $peran => $butuhTim) {
            $pengguna = $this->buatPengguna($peran, $butuhTim ? $this->buatTim('Tim ' . $peran) : null, ['email' => $peran . '.muatan@bps.go.id']);
            $nama = $pengguna->name;
            $nip  = $pengguna->nip;
            $this->actingAs($pengguna);

            Livewire::test(Pengaturan::class)
                ->fillForm(['name' => 'Nama Palsu', 'nip' => '999999999999999999'])
                ->call('simpan')
                ->assertHasNoFormErrors();

            $pengguna->refresh();
            $this->assertSame($nama, $pengguna->name, "Nama {$peran} tidak boleh berubah.");
            $this->assertSame($nip, $pengguna->nip, "NIP {$peran} tidak boleh berubah.");
        }
    }

    /** Muatan nama dikosongkan pun tidak menggagalkan penyimpanan bagian lain. */
    public function test_muatan_nama_kosong_diabaikan_dan_bagian_lain_tetap_tersimpan(): void
    {
        $pengguna = $this->buatPengguna('kasubbag', null, ['email' => 'kasubbag.kosong@bps.go.id']);
        $nama = $pengguna->name;
        $this->actingAs($pengguna);

        Livewire::test(Pengaturan::class)
            ->fillForm(['name' => '', 'nip' => '', 'no_hp' => '081200005555'])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $pengguna->refresh();
        $this->assertSame($nama, $pengguna->name);
        $this->assertNotNull($pengguna->nip);
        $this->assertSame('081200005555', $pengguna->no_hp);
    }

    public function test_nomor_whatsapp_tetap_dapat_disimpan(): void
    {
        foreach ($this->semuaPeran() as $peran => $butuhTim) {
            $pengguna = $this->buatPengguna($peran, $butuhTim ? $this->buatTim('Tim ' . $peran) : null);
            $this->actingAs($pengguna);

            Livewire::test(Pengaturan::class)
                ->fillForm(['no_hp' => '081277778888'])
                ->call('simpan')
                ->assertHasNoFormErrors();

            $this->assertSame('081277778888', $pengguna->refresh()->no_hp);
        }
    }

    public function test_admin_mengubah_nama_dan_nip_lewat_menu_pengguna_dan_hasilnya_tampil_pada_pengaturan(): void
    {
        $admin  = $this->buatPengguna('admin');
        $target = $this->buatPengguna('kasubbag', null, ['email' => 'target.nama@bps.go.id']);

        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['name' => 'Nama Resmi Baru', 'nip' => '198501012010011002'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->actingAs($target->refresh());
        Livewire::test(Pengaturan::class)
            ->assertFormSet(['name' => 'Nama Resmi Baru', 'nip' => '198501012010011002']);
    }

    /** Nama Pemohon pada Katalog sengaja tidak dikunci: mencatat siapa yang benar-benar meminta lewat akun bersama. */
    public function test_nama_pemohon_di_katalog_tetap_bebas_diisi(): void
    {
        $akunTim = $this->buatPengguna('tim', $this->buatTim());
        $barang  = $this->buatBarang(stokFisik: 20);
        $this->actingAs($akunTim);

        Livewire::test(KatalogBarang::class)->callAction('ajukan', [
            'nama_pemohon' => 'Rekan Yang Benar-benar Meminta',
            'nip_pemohon'  => '199001012020121001',
            'items'        => [['barang_id' => $barang->id, 'jumlah' => 2]],
            'keperluan'    => 'Keperluan uji',
        ]);

        $this->assertSame(1, PermintaanBarang::where('nama_pemohon', 'Rekan Yang Benar-benar Meminta')->count());
    }
}
