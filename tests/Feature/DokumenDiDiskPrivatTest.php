<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\Pages\CreateBastMutasiAset;
use App\Models\BastMutasiAset;
use App\Models\PermintaanBarang;
use App\Services\DokumenBastService;
use App\Services\DokumenPermintaanService;
use App\Services\MutasiAsetService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Audit C-001: dokumen bukti dan BAST disimpan di disk privat dan tidak pernah
 * di disk public (yang terjangkau /storage bila `storage:link` dijalankan).
 * Path pada basis data tetap relatif dan sama seperti sebelumnya.
 */
class DokumenDiDiskPrivatTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('tanda-tangan/uji.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
        Filament::setCurrentPanel('admin');
    }

    public function test_bukti_permintaan_ditulis_ke_disk_privat_dengan_path_relatif_yang_sama(): void
    {
        $tim = $this->buatTim();
        $ketua = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $tim));
        $tim->update(['ketua_tim_id' => $ketua->id]);
        $pengaju = $this->lengkapiAkun($this->buatPengguna('tim', $tim));
        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $this->buatBarang(stokFisik: 20, stokHold: 2), 'diminta' => 2],
        ], status: 'menunggu_pengesahan');

        $lintasan = app(DokumenPermintaanService::class)->buat($permintaan);

        $asli = 'bukti-permintaan/' . $permintaan->kode_permintaan . '.pdf';
        $berfootnote = 'bukti-permintaan/' . $permintaan->kode_permintaan . '-berfootnote.pdf';

        $this->assertSame($asli, $lintasan, 'Path relatif pada basis data tidak berubah.');
        $this->assertSame($berfootnote, DokumenPermintaanService::lintasanBerfootnote($permintaan));

        foreach ([$asli, $berfootnote] as $berkas) {
            Storage::disk('local')->assertExists($berkas);
            Storage::disk('public')->assertMissing($berkas);
        }

        $this->assertSame([], Storage::disk('public')->allFiles(), 'Tidak ada apa pun di disk public.');
    }

    public function test_draf_dan_bast_disahkan_ditulis_ke_disk_privat(): void
    {
        $asal = $this->buatTim('Tim Asal');
        $tujuan = $this->buatTim('Tim Tujuan');
        $gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));
        $aset = $this->buatAset($asal);

        $this->actingAs($gudang);
        Livewire::test(CreateBastMutasiAset::class)->fillForm([
            'aset_id'        => $aset->id,
            'tim_asal_id'    => $asal->id,
            'tim_tujuan_id'  => $tujuan->id,
            'alasan_mutasi'  => 'Uji disk privat',
            'pihak_penyerah' => 'Penyerah',
            'pihak_penerima' => 'Penerima',
        ])->call('create')->assertHasNoFormErrors();

        $bast = BastMutasiAset::sole();
        $berkas = 'bast-mutasi/' . $bast->nomor_bast . '.pdf';

        // Draf yang dibuat saat BAST dibuat.
        $this->assertSame($berkas, $bast->file_bast_path);
        Storage::disk('local')->assertExists($berkas);
        Storage::disk('public')->assertMissing($berkas);

        // Berkas yang dibentuk ulang saat disahkan.
        app(MutasiAsetService::class)->sahkan($bast, $kasubbag->id);
        $this->assertSame($berkas, $bast->fresh()->file_bast_path);
        Storage::disk('local')->assertExists($berkas);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Tidak ada apa pun di disk public.');
    }

    public function test_rute_tidak_membaca_dokumen_lama_yang_masih_di_disk_public(): void
    {
        // Sebelum simpbi:pindah-dokumen dijalankan, dokumen lama belum ada di disk privat.
        $tim = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $this->buatBarang(), 'diminta' => 1],
        ], status: 'selesai', tambahan: [
            'file_bukti_path' => 'bukti-permintaan/LAMA.pdf',
            'qr_token'        => str_repeat('c', 40),
            'pengesahan_at'   => now(),
        ]);
        Storage::disk('public')->put('bukti-permintaan/LAMA.pdf', '%PDF-lama');
        $admin = $this->lengkapiAkun($this->buatPengguna('admin'));

        $this->actingAs($admin)->get(route('bukti.unduh', $permintaan))->assertNotFound();
        $this->get(route('bukti.asli', str_repeat('c', 40)))->assertNotFound();

        // Sesudah dipindahkan (disalin ke disk privat) rute kembali berfungsi.
        Storage::disk('local')->put('bukti-permintaan/LAMA.pdf', '%PDF-lama');
        $this->actingAs($admin)->get(route('bukti.unduh', $permintaan))->assertOk();
        $this->get(route('bukti.asli', str_repeat('c', 40)))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_layanan_dokumen_tidak_lagi_membuat_tautan_publik(): void
    {
        $this->assertSame(
            [],
            array_filter(
                array_map('file_get_contents', [
                    (new \ReflectionClass(DokumenBastService::class))->getFileName(),
                    (new \ReflectionClass(DokumenPermintaanService::class))->getFileName(),
                    base_path('routes/web.php'),
                ]),
                fn (string $isi): bool => str_contains($isi, "disk('public')") || str_contains($isi, 'asset(\'storage') || str_contains($isi, 'Storage::url'),
            ),
            'Tidak boleh ada lagi pemakaian disk public atau URL /storage untuk dokumen.',
        );
    }
}
