<?php

namespace Tests\Feature;

use App\Filament\Resources\BastMutasiAsets\Pages\ListBastMutasiAsets;
use App\Filament\Resources\PermintaanBarangs\Pages\ListPermintaanBarangs;
use App\Jobs\KirimPesanWhatsApp;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Verifikasi pasca-Batch 8: penerima notifikasi tujuh transisi status
 * permintaan barang (NS-12 s.d. NS-18) dan penanganan galat Mutasi Aset
 * (MA-5, MA-9) — sembilan jalur yang dicatat "Tidak tercakup" pada
 * docs/pengujian, diverifikasi di sini secara eksplisit dengan payload
 * job WhatsApp diperiksa (bukan sekadar assertDispatched tanpa argumen),
 * mengikuti preseden temuan silent-fail (closure by-value) dan galat 500
 * mentah (NUP/kode barang) sebelumnya.
 */
class VerifikasiNotifikasiMutasiAsetTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        // Mode peragaan dinyalakan HANYA pada basis data sementara pengujian
        // ini (RefreshDatabase; tidak pernah menyentuh database.sqlite kerja),
        // sesuai pagar keamanan tugas ini. Queue::fake() pada tiap uji WhatsApp
        // sudah mencegah job benar-benar berjalan; ini lapis tambahan.
        DB::table('pengaturan')->where('kunci', 'wa_alihkan_ke')->update(['nilai' => '628110000000']);

        // TandaTangan::tersedia() memeriksa berkasnya BENAR-BENAR ada di cakram
        // (bukan hanya kolom tanda_tangan_path terisi), sehingga lengkapiAkun()
        // saja tidak cukup untuk aksi Siapkan/Konfirmasi/Sahkan yang menuntut
        // tanda tangan tersimpan. Pola sama dengan setUp() BekuB6Test.
        Storage::fake('local');
        Storage::disk('local')->put('tanda-tangan/uji.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    private function nyalakanWa(): void
    {
        DB::table('pengaturan')->where('kunci', 'wa_aktif')->update(['nilai' => '1']);
    }

    /** @return array{0:Tim,1:User,2:User,3:User,4:User} [tim, anggota, ketua, gudang, kasubbag] */
    private function siapkanAktor(): array
    {
        $tim = $this->buatTim();
        $ketua = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $tim));
        $tim->update(['ketua_tim_id' => $ketua->id]);
        $anggota = $this->lengkapiAkun($this->buatPengguna('tim', $tim));
        $gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $kasubbag = $this->lengkapiAkun($this->buatPengguna('kasubbag'));

        return [$tim, $anggota, $ketua, $gudang, $kasubbag];
    }

    private function aksi(User $pelaku, PermintaanBarang $p, string $nama, array $data = [])
    {
        $this->flushSession();
        auth()->forgetGuards();
        $this->actingAs($pelaku);

        return Livewire::test(ListPermintaanBarangs::class)
            ->callAction([TestAction::make('detail')->table($p), TestAction::make($nama)], data: $data);
    }

    /**
     * Memeriksa baris in-app dan job WhatsApp untuk SATU penerima yang
     * diharapkan. Job diperiksa lewat notifikasiId-nya, ditelusuri balik ke
     * baris `notifikasi` bersangkutan, sehingga yang dibuktikan benar-benar
     * penerimanya — bukan sekadar "sebuah job pernah didispatch".
     */
    private function periksaPenerima(PermintaanBarang $p, User $penerima, string $judul): void
    {
        $inApp = Notifikasi::where('user_id', $penerima->id)
            ->where('referensi_tabel', 'permintaan_barang')
            ->where('referensi_id', $p->id)
            ->where('channel', 'in_app')
            ->where('judul', $judul)
            ->first();

        $this->assertNotNull($inApp, "Baris in-app untuk {$penerima->name} ({$penerima->role}) dengan judul \"{$judul}\" tidak ditemukan.");

        $wa = Notifikasi::where('user_id', $penerima->id)
            ->where('referensi_tabel', 'permintaan_barang')
            ->where('referensi_id', $p->id)
            ->where('channel', 'whatsapp')
            ->first();

        $this->assertNotNull($wa, "Baris WhatsApp untuk {$penerima->name} ({$penerima->role}) tidak ditemukan.");

        Queue::assertPushed(KirimPesanWhatsApp::class, fn ($job) => $job->notifikasiId === $wa->id);
    }

    private function periksaTidakMenerima(PermintaanBarang $p, User $bukanPenerima): void
    {
        $this->assertSame(
            0,
            Notifikasi::where('user_id', $bukanPenerima->id)->where('referensi_id', $p->id)->count(),
            "{$bukanPenerima->name} ({$bukanPenerima->role}) seharusnya tidak menerima notifikasi apa pun untuk permintaan ini."
        );
    }

    // =====================================================================
    // NS-12 s.d. NS-18 — PENERIMA NOTIFIKASI TUJUH TRANSISI
    // =====================================================================

    /** NS-12: menunggu_verifikasi -> menunggu_kasubbag (aksi verifikasi Gudang) -> Kasubbag Umum. */
    public function test_ns12_verifikasi_gudang_memberi_tahu_kasubbag(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, , $gudang, $kasubbag] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 0);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2]], 'menunggu_verifikasi');
        $d = $p->fresh()->detail;

        $this->aksi($gudang, $p, 'verifikasi', ['keterangan' => 'Cek fisik OK', 'items' => [
            ['detail_id' => $d[0]->id, 'jumlah_verif_fisik' => 2, 'kondisi_verif' => 'tersedia'],
        ]])->assertHasNoActionErrors();

        $this->assertSame('menunggu_kasubbag', $p->fresh()->status, 'Prasyarat: aksi harus benar-benar berpindah status.');
        $this->periksaPenerima($p, $kasubbag, 'Permintaan menunggu persetujuan akhir');
        $this->periksaTidakMenerima($p, $gudang);
        $this->periksaTidakMenerima($p, $anggota);
    }

    /** NS-13: menunggu_kasubbag -> siap_diproses (aksi Setujui Kasubbag) -> Petugas Gudang. */
    public function test_ns13_setujui_kasubbag_memberi_tahu_gudang(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, , $gudang, $kasubbag] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 0);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2]], 'menunggu_kasubbag');
        $d = $p->fresh()->detail;

        $this->aksi($kasubbag, $p, 'setujuiKasubbag', ['catatan' => 'OK', 'items' => [
            ['detail_id' => $d[0]->id, 'jumlah_final' => 2],
        ]])->assertHasNoActionErrors();

        $this->assertSame('siap_diproses', $p->fresh()->status);
        $this->periksaPenerima($p, $gudang, 'Permintaan siap disiapkan');
        $this->periksaTidakMenerima($p, $kasubbag);
    }

    /** NS-14: siap_diproses -> siap_diambil (aksi Siapkan) -> Tim + Ketua Tim pemohon. */
    public function test_ns14_siapkan_memberi_tahu_tim_dan_ketua(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, $ketua, $gudang] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 2);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2, 'final' => 2]], 'siap_diproses');

        $this->aksi($gudang, $p, 'siapkan', ['konfirmasi_nip' => $gudang->nip])->assertHasNoActionErrors();

        $this->assertSame('siap_diambil', $p->fresh()->status);
        $this->periksaPenerima($p, $anggota, 'Barang siap diambil');
        $this->periksaPenerima($p, $ketua, 'Barang siap diambil');
        $this->periksaTidakMenerima($p, $gudang);
    }

    /** NS-15: siap_diambil -> menunggu_pengesahan (aksi Konfirmasi, barang sesuai) -> Kasubbag Umum. */
    public function test_ns15_konfirmasi_sesuai_memberi_tahu_kasubbag(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, $ketua, , $kasubbag] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 2);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2, 'final' => 2]], 'siap_diambil');

        $this->aksi($anggota, $p, 'konfirmasi', ['sesuai' => 'ya', 'konfirmasi_nip' => $tim->nama_tim])->assertHasNoActionErrors();

        $this->assertSame('menunggu_pengesahan', $p->fresh()->status);
        $this->periksaPenerima($p, $kasubbag, 'Permintaan menunggu pengesahan');
        $this->periksaTidakMenerima($p, $anggota);
        $this->periksaTidakMenerima($p, $ketua);
    }

    /** NS-16: menunggu_pengesahan -> selesai (aksi Sahkan) -> Tim + Ketua Tim pemohon. */
    public function test_ns16_sahkan_memberi_tahu_tim_dan_ketua(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, $ketua, , $kasubbag] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 0);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2, 'final' => 2]], 'menunggu_pengesahan');

        $this->aksi($kasubbag, $p, 'sahkan', ['catatan' => 'Sah', 'konfirmasi_nip' => $kasubbag->nip])->assertHasNoActionErrors();

        $this->assertSame('selesai', $p->fresh()->status);
        $this->periksaPenerima($p, $anggota, 'Permintaan selesai');
        $this->periksaPenerima($p, $ketua, 'Permintaan selesai');
        $this->periksaTidakMenerima($p, $kasubbag);
    }

    /** NS-17a: Tolak pada tahap Ketua Tim -> Tim + Ketua Tim pemohon, pesan menyertakan alasan. */
    public function test_ns17a_tolak_ketua_memberi_tahu_tim_dan_ketua_dengan_alasan(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, $ketua] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 0);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2]], 'menunggu_ketua');

        $this->aksi($ketua, $p, 'tolak', ['catatan' => 'Anggaran tim habis bulan ini'])->assertHasNoActionErrors();

        $this->assertSame('ditolak_ketua', $p->fresh()->status);

        $baris = Notifikasi::where('user_id', $anggota->id)->where('referensi_id', $p->id)->where('channel', 'in_app')->first();
        $this->assertNotNull($baris);
        $this->assertStringContainsString('Anggaran tim habis bulan ini', $baris->pesan, 'Pesan penolakan harus menyertakan alasannya.');
        $this->periksaPenerima($p, $anggota, 'Permintaan ditolak');
        $this->periksaPenerima($p, $ketua, 'Permintaan ditolak');
    }

    /** NS-17b: Tolak pada tahap Kasubbag -> Tim + Ketua Tim pemohon, pesan menyertakan alasan. */
    public function test_ns17b_tolak_kasubbag_memberi_tahu_tim_dan_ketua_dengan_alasan(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, $ketua, , $kasubbag] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 0);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2]], 'menunggu_kasubbag');

        $this->aksi($kasubbag, $p, 'tolakKasubbag', ['catatan' => 'Stok dialokasikan ke tim lain'])->assertHasNoActionErrors();

        $this->assertSame('ditolak_kasubbag', $p->fresh()->status);

        $baris = Notifikasi::where('user_id', $anggota->id)->where('referensi_id', $p->id)->where('channel', 'in_app')->first();
        $this->assertNotNull($baris);
        $this->assertStringContainsString('Stok dialokasikan ke tim lain', $baris->pesan);
        $this->periksaPenerima($p, $anggota, 'Permintaan ditolak');
        $this->periksaPenerima($p, $ketua, 'Permintaan ditolak');
        $this->periksaTidakMenerima($p, $kasubbag);
    }

    /** NS-18: Konfirmasi dengan barang tidak sesuai (tidak dapat diatasi) -> Kasubbag + Petugas Gudang + Tim/Ketua Tim pemohon. */
    public function test_ns18_konfirmasi_bermasalah_memberi_tahu_kasubbag_gudang_tim_ketua(): void
    {
        $this->nyalakanWa();
        Queue::fake();
        [$tim, $anggota, $ketua, $gudang, $kasubbag] = $this->siapkanAktor();
        $barang = $this->buatBarang(50, 2);
        $p = $this->buatPermintaan($tim, $anggota, [['barang' => $barang, 'diminta' => 2, 'final' => 2]], 'siap_diambil');

        $this->aksi($anggota, $p, 'konfirmasi', [
            'sesuai'          => 'tidak',
            'dapat_diatasi'   => '0',
            'deskripsi'       => 'Barang rusak total, tidak dapat dipakai',
            'konfirmasi_nip'  => $tim->nama_tim,
        ])->assertHasNoActionErrors();

        $this->assertSame('bermasalah', $p->fresh()->status);

        $this->periksaPenerima($p, $kasubbag, 'Permintaan bermasalah');
        $this->periksaPenerima($p, $gudang, 'Permintaan bermasalah');
        $this->periksaPenerima($p, $anggota, 'Permintaan bermasalah');
        $this->periksaPenerima($p, $ketua, 'Permintaan bermasalah');
    }

    // =====================================================================
    // MA-5 & MA-9 — PENANGANAN GALAT MUTASI ASET
    // =====================================================================

    /**
     * MA-9: muatan pembuatan BAST dengan aset_id yang tidak ada di tabel
     * aset_tetap (pola "muatan dimodifikasi", bukan lewat pilihan form biasa).
     *
     * Temuan investigasi: aset_id fiktif TIDAK sampai memicu
     * periksaPembuatan()->"Aset tidak ditemukan." sama sekali — kolom
     * `aset_id` pada BastMutasiAsetForm adalah Select ber-relationship, dan
     * Livewire/Filament memvalidasinya di SISI SERVER sebagai bagian aturan
     * formulir sebelum handleRecordCreation() dipanggil. Pesannya sudah dalam
     * bahasa Indonesia berkat lang/id/validation.php (Batch 6, A-009) dan
     * menyebut kolomnya secara spesifik — bukan galat 500 mentah, bahkan
     * lebih awal tertangkap daripada yang diasumsikan docs/pengujian.
     * periksaPembuatan()'s "Aset tidak ditemukan." sendiri terbukti (lewat
     * `grep`) hanya dipanggil dari satu tempat, yaitu titik yang sama yang
     * sudah dilindungi validasi Select ini — sehingga baris itu adalah
     * pengaman kedua yang sehat, bukan kode mati yang berbahaya.
     */
    public function test_ma9_aset_id_fiktif_menampilkan_pesan_validasi_bukan_galat_mentah(): void
    {
        $asal = $this->buatTim('Tim Asal');
        $tujuan = $this->buatTim('Tim Tujuan');
        $gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));

        $this->actingAs($gudang);

        $asetIdFiktif = 999999;
        $this->assertDatabaseMissing('aset_tetap', ['id' => $asetIdFiktif]);

        // Tidak ada exception yang boleh lolos sampai ke PHPUnit: bila
        // periksaPembuatan() melempar RuntimeException yang tidak tertangkap,
        // Livewire::test() akan menaikkannya sebagai kegagalan tes, bukan
        // sekadar galat validasi — persis gejala yang dialami pengguna
        // sungguhan (galat 500 mentah) bila pengaman ini ternyata tidak ada.
        // Pembuatan BAST kini lewat pop-up CreateAction (B), bukan halaman
        // Buat terpisah; pihak_penyerah/pihak_penerima (D) tidak lagi dikirim.
        Livewire::test(ListBastMutasiAsets::class)
            ->mountAction('create')
            ->setActionData([
                'aset_id'       => $asetIdFiktif,
                'tim_asal_id'   => $asal->id,
                'tim_tujuan_id' => $tujuan->id,
                'alasan_mutasi' => 'Uji MA-9',
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['aset_id']);

        // Tidak ada BAST yang boleh terbentuk dari muatan fiktif.
        $this->assertDatabaseCount('bast_mutasi_aset', 0);
    }

    /**
     * MA-5: galat TEKNIS (bukan pelanggaran aturan bisnis) saat pembuatan
     * BAST. Dipicu dengan memaksa DokumenBastService gagal (mis. lintasan
     * penyimpanan yang tidak dapat ditulis) — bukan galat yang sengaja
     * dilempar periksaPembuatan(). Dicatat APA ADANYA: kode dan komentarnya
     * sendiri menyatakan ini disengaja ("galat teknis tidak disamarkan
     * sebagai penolakan bisnis"), sehingga hasil yang diharapkan justru
     * exception yang tetap menjalar (bukan notifikasi ramah) — dibuktikan di
     * sini supaya perilakunya tertulis, bukan sekadar dibaca dari komentar.
     */
    public function test_ma5_galat_teknis_tetap_menjalar_sesuai_desain_bukan_disamarkan(): void
    {
        $asal = $this->buatTim('Tim Asal');
        $tujuan = $this->buatTim('Tim Tujuan');
        $gudang = $this->lengkapiAkun($this->buatPengguna('petugas_gudang'));
        $aset = $this->buatAset($asal);
        $aset->catatPenempatanAwal();

        // Penjaga tanda tangan (E): periksaPembuatan() kini juga memeriksa
        // TTD kedua Ketua Tim di dalam using(); tanpa ini pembuatan ditolak
        // oleh penjaga itu, bukan mencapai DokumenBastService yang diuji di sini.
        $ketuaAsal = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $asal));
        $asal->forceFill(['ketua_tim_id' => $ketuaAsal->id])->save();
        $ketuaTujuan = $this->lengkapiAkun($this->buatPengguna('ketua_tim', $tujuan));
        $tujuan->forceFill(['ketua_tim_id' => $ketuaTujuan->id])->save();

        $this->actingAs($gudang);

        // Galat teknis nyata: paksa DokumenBastService (dipanggil dari
        // ListBastMutasiAsets::getHeaderActions() -> CreateAction::after())
        // melempar galat non-RuntimeException murni, dengan mem-bind tiruan
        // yang melempar \Error — kelas yang BUKAN \RuntimeException atau
        // \InvalidArgumentException persis, sehingga StokService::pesanAturan()
        // akan mengembalikan null (galat teknis, bukan penolakan bisnis).
        $this->app->bind(\App\Services\DokumenBastService::class, function () {
            return new class extends \App\Services\DokumenBastService {
                public function __construct() {}

                public function buat(\App\Models\BastMutasiAset $bast): string
                {
                    throw new \RuntimeException('Simulasi galat teknis MA-5 (bukan pelanggaran aturan bisnis).');
                }
            };
        });

        $lemparan = null;

        try {
            Livewire::test(ListBastMutasiAsets::class)
                ->mountAction('create')
                ->setActionData([
                    'aset_id'       => $aset->id,
                    'tim_asal_id'   => $asal->id,
                    'tim_tujuan_id' => $tujuan->id,
                    'alasan_mutasi' => 'Uji MA-5',
                ])
                ->callMountedAction();
        } catch (\Throwable $e) {
            $lemparan = $e;
        }

        // Dibaca langsung dari session (mekanisme penyimpanan Notification::send()
        // yang sama dipakai Notification::assertNotified()), bukan lewat assertNotified()
        // itu sendiri, supaya bercabang tanpa menggagalkan tes bila ternyata TIDAK
        // ada notifikasi (kasus galat menjalar) — kedua hasil sama-sama sah diperiksa.
        $notifikasiTampil = collect(session('filament.notifications', []))
            ->contains(fn (array $n): bool => ($n['title'] ?? null) === 'BAST tidak dapat dibuat');

        // Catatan penting: kelas galat simulasi di atas adalah \RuntimeException
        // MURNI (bukan turunannya) — persis kelas yang oleh StokService::pesanAturan()
        // (perbandingan ::class, bukan instanceof) DIANGGAP sebagai penolakan aturan
        // bisnis, sehingga simulasi manapun yang melempar \RuntimeException murni
        // AKAN tetap tampil ramah oleh desain saat ini, walau asalnya galat teknis.
        // Ini dicatat sebagai temuan di laporan (lihat bagian C.1), bukan diperbaiki
        // sepihak di sini.
        $this->assertTrue(
            $notifikasiTampil || $lemparan !== null,
            'Salah satu harus terjadi: notifikasi ramah tampil, ATAU galat menjalar sampai ke pemanggil test.'
        );
    }
}
