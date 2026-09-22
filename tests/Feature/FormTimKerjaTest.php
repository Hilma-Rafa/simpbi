<?php

namespace Tests\Feature;

use App\Filament\Resources\Tims\Pages\CreateTim;
use App\Filament\Resources\Tims\Pages\EditTim;
use App\Filament\Resources\Tims\Widgets\StatusSinkronisasiTim;
use App\Models\Tim;
use App\Models\User;
use App\Services\Impor\ImporTimKerja;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Form Tim Kerja.
 *
 * A-031: ID Eksternal dan Waktu Sinkronisasi bertuliskan "diisi otomatis",
 * sehingga tampil tetapi tidak dapat disunting dan tidak ikut disimpan dari form.
 *
 * A-012: Ketua Tim hanya dapat dipilih dari pengguna aktif berperan Ketua Tim
 * yang terdaftar pada tim itu sendiri, dan server menolak selainnya.
 */
class FormTimKerjaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    private const PESAN_KETUA = 'Ketua Tim harus pengguna aktif berperan Ketua Tim yang terdaftar pada tim ini. Tetapkan peran dan tim lewat menu Pengguna terlebih dahulu.';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->buatPengguna('admin'));
    }

    private function timBersinkron(string $nama = 'Statistik Sosial'): Tim
    {
        return Tim::create([
            'nama_tim'     => $nama,
            'external_id'  => 'EXT-001',
            'synced_at'    => '2026-09-01 08:00:00',
            'status_aktif' => true,
        ]);
    }

    /** @return array<int|string,string> */
    private function opsiKetua($uji): array
    {
        $opsi = [];

        $uji->assertFormFieldExists('ketua_tim_id', function (Select $kolom) use (&$opsi): bool {
            $opsi = $kolom->getOptions();

            return true;
        });

        return $opsi;
    }

    // =====================================================================
    // A-031: BAGIAN SINKRONISASI
    // =====================================================================

    public function test_kolom_sinkronisasi_tampil_tetapi_tidak_dapat_diubah(): void
    {
        $tim = $this->timBersinkron();

        Livewire::test(EditTim::class, ['record' => $tim->getKey()])
            ->assertFormFieldIsDisabled('external_id')
            ->assertFormFieldIsDisabled('synced_at')
            ->assertFormSet(['external_id' => 'EXT-001']);
    }

    public function test_form_ubah_tidak_mengubah_kolom_sinkronisasi_walau_muatan_dimodifikasi(): void
    {
        $tim = $this->timBersinkron();

        Livewire::test(EditTim::class, ['record' => $tim->getKey()])
            ->fillForm([
                'nama_tim'    => 'Statistik Sosial Baru',
                'external_id' => 'DIUBAH',
                'synced_at'   => '2030-01-01 00:00:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tim->refresh();
        $this->assertSame('Statistik Sosial Baru', $tim->nama_tim);
        $this->assertSame('EXT-001', $tim->external_id);
        $this->assertSame('2026-09-01 08:00:00', $tim->synced_at);
    }

    public function test_strip_status_sinkronisasi_tidak_bergeser_oleh_form(): void
    {
        $tim = $this->timBersinkron();

        Livewire::test(EditTim::class, ['record' => $tim->getKey()])
            ->fillForm(['synced_at' => '2035-05-05 05:05:00'])
            ->call('save');

        $strip = Livewire::test(StatusSinkronisasiTim::class)->instance();

        $this->assertSame('2026-09-01 08:00:00', $strip->syncedAt->format('Y-m-d H:i:s'));
    }

    public function test_form_buat_berhasil_dan_kolom_sinkronisasi_tetap_kosong(): void
    {
        Livewire::test(CreateTim::class)
            ->fillForm(['nama_tim' => 'Tim Baru', 'external_id' => 'DISUSUPKAN', 'synced_at' => '2030-01-01 00:00:00'])
            ->call('create')
            ->assertHasNoFormErrors();

        $tim = Tim::where('nama_tim', 'Tim Baru')->sole();
        $this->assertNull($tim->external_id);
        $this->assertNull($tim->synced_at);
        $this->assertTrue((bool) $tim->status_aktif);
        $this->assertNull($tim->ketua_tim_id);
    }

    public function test_impor_tetap_mengisi_kedua_kolom(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $lintasan = tempnam(sys_get_temp_dir(), 'uji_impor_') . '.xlsx';
        $writer = new Writer;
        $writer->openToFile($lintasan);
        foreach ([['Nama Tim *', 'ID Sumber', 'Aktif'], ['Tim Impor', 'SRC-77', 'Ya']] as $baris) {
            $writer->addRow(Row::fromValues($baris));
        }
        $writer->close();

        app(ImporTimKerja::class)->jalankan($lintasan);
        @unlink($lintasan);

        $tim = Tim::where('nama_tim', 'Tim Impor')->sole();
        $this->assertSame('SRC-77', $tim->external_id);
        $this->assertSame('2026-09-22 10:00:00', $tim->synced_at);

        $strip = Livewire::test(StatusSinkronisasiTim::class)->instance();
        $this->assertSame('2026-09-22 10:00:00', $strip->syncedAt->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    // =====================================================================
    // A-012: KETUA TIM
    // =====================================================================

    /** @return array{Tim,User,User,User,User,User} tim, ketua sah, ketua tim lain, ketua nonaktif, anggota biasa, kasubbag */
    private function timDenganPenggunaCampuran(): array
    {
        $tim = $this->timBersinkron();
        $lain = $this->buatTim('Tim Lain');

        $sah = $this->buatPengguna('ketua_tim', $tim, ['name' => 'Ketua Sah']);
        $dariLain = $this->buatPengguna('ketua_tim', $lain, ['name' => 'Ketua Tim Lain']);
        $nonaktif = $this->buatPengguna('ketua_tim', $tim, ['name' => 'Ketua Nonaktif', 'status_aktif' => false]);
        $anggota = $this->buatPengguna('tim', $tim, ['name' => 'Anggota Biasa']);
        $kasubbag = $this->buatPengguna('kasubbag', null, ['name' => 'Kasubbag Bebas']);

        return [$tim, $sah, $dariLain, $nonaktif, $anggota, $kasubbag];
    }

    public function test_pilihan_ketua_hanya_memuat_pengguna_yang_memenuhi_syarat(): void
    {
        [$tim, $sah] = $this->timDenganPenggunaCampuran();

        $opsi = $this->opsiKetua(Livewire::test(EditTim::class, ['record' => $tim->getKey()]));

        $this->assertSame([$sah->id => 'Ketua Sah'], $opsi);
    }

    public function test_pilihan_ketua_kosong_pada_form_buat(): void
    {
        $this->timDenganPenggunaCampuran();

        $this->assertSame([], $this->opsiKetua(Livewire::test(CreateTim::class)));
    }

    public function test_menyimpan_ketua_yang_tidak_memenuhi_syarat_ditolak(): void
    {
        [$tim, , $dariLain, $nonaktif, $anggota, $kasubbag] = $this->timDenganPenggunaCampuran();

        foreach ([$dariLain, $nonaktif, $anggota, $kasubbag] as $pengguna) {
            Livewire::test(EditTim::class, ['record' => $tim->getKey()])
                ->fillForm(['ketua_tim_id' => $pengguna->id])
                ->call('save')
                ->assertHasFormErrors(['ketua_tim_id' => self::PESAN_KETUA]);

            $this->assertNull($tim->fresh()->ketua_tim_id, "{$pengguna->name} tidak boleh tersimpan.");
        }
    }

    public function test_menyimpan_pengguna_yang_tidak_ada_ditolak(): void
    {
        [$tim] = $this->timDenganPenggunaCampuran();

        Livewire::test(EditTim::class, ['record' => $tim->getKey()])
            ->fillForm(['ketua_tim_id' => 987654])
            ->call('save')
            ->assertHasFormErrors(['ketua_tim_id' => self::PESAN_KETUA]);

        $this->assertNull($tim->fresh()->ketua_tim_id);
    }

    public function test_menyimpan_ketua_yang_memenuhi_syarat_berhasil(): void
    {
        [$tim, $sah] = $this->timDenganPenggunaCampuran();

        Livewire::test(EditTim::class, ['record' => $tim->getKey()])
            ->fillForm(['ketua_tim_id' => $sah->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($sah->id, $tim->fresh()->ketua_tim_id);
    }

    public function test_kolom_kosong_tetap_boleh_seperti_sebelumnya(): void
    {
        [$tim, $sah] = $this->timDenganPenggunaCampuran();
        $tim->update(['ketua_tim_id' => $sah->id]);

        Livewire::test(EditTim::class, ['record' => $tim->getKey()])
            ->fillForm(['ketua_tim_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($tim->fresh()->ketua_tim_id);

        Livewire::test(CreateTim::class)
            ->fillForm(['nama_tim' => 'Tim Tanpa Ketua'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Tim::where('nama_tim', 'Tim Tanpa Ketua')->value('ketua_tim_id'));
    }

    public function test_form_buat_menolak_ketua_yang_dikirim_walau_pilihannya_kosong(): void
    {
        [, , $dariLain] = $this->timDenganPenggunaCampuran();

        Livewire::test(CreateTim::class)
            ->fillForm(['nama_tim' => 'Tim Susupan', 'ketua_tim_id' => $dariLain->id])
            ->call('create')
            ->assertHasFormErrors(['ketua_tim_id' => self::PESAN_KETUA]);

        $this->assertSame(0, Tim::where('nama_tim', 'Tim Susupan')->count());
    }

    public function test_form_ubah_dengan_data_lama_menyimpang_tidak_galat_dan_menuntut_perbaikan(): void
    {
        [$tim, $sah, $dariLain, $nonaktif] = $this->timDenganPenggunaCampuran();

        foreach ([$dariLain, $nonaktif] as $menyimpang) {
            $tim->update(['ketua_tim_id' => $menyimpang->id]);

            $uji = Livewire::test(EditTim::class, ['record' => $tim->getKey()])
                ->assertSuccessful()
                ->assertFormSet(['ketua_tim_id' => $menyimpang->id]);

            // Nama pilihan lama tetap tampil (bukan kosong), tetapi tidak ada di daftar pilihan.
            $uji->assertFormFieldExists('ketua_tim_id', fn (Select $kolom): bool => $kolom->getOptionLabel() === $menyimpang->name);
            $this->assertArrayNotHasKey($menyimpang->id, $this->opsiKetua($uji));

            // Menyimpan tanpa memperbaikinya ditolak; memperbaikinya berhasil.
            $uji->call('save')->assertHasFormErrors(['ketua_tim_id' => self::PESAN_KETUA]);
            $this->assertSame($menyimpang->id, $tim->fresh()->ketua_tim_id);

            $uji->fillForm(['ketua_tim_id' => $sah->id])->call('save')->assertHasNoFormErrors();
            $this->assertSame($sah->id, $tim->fresh()->ketua_tim_id);
        }
    }
}
