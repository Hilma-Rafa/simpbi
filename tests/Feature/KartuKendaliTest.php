<?php

namespace Tests\Feature;

use App\Filament\Pages\KartuKendali;
use App\Models\BarangPersediaan;
use App\Services\KartuKendaliService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Halaman kartu kendali dan ekspornya ke lembar sebar.
 *
 * Kartu kendali adalah bahan rekonsiliasi, sehingga yang dijaga bukan sekadar
 * berkasnya terbentuk melainkan angkanya benar: kolom Sisa harus berjalan
 * menurut urutan tanggal, dan tata letaknya harus tetap sama dengan berkas
 * Sub-Bagian Umum supaya dapat langsung menggantikan berkas lama.
 */
class KartuKendaliTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    /**
     * Satu barang dengan riwayat yang sudah dapat diperiksa: saldo pembawaan
     * tahun lalu, lalu satu pembelian di dalam periode.
     */
    private function barangBerriwayat(): BarangPersediaan
    {
        $barang  = $this->buatBarang(stokFisik: 0);
        $petugas = $this->buatPengguna('petugas_gudang');
        $stok    = app(StokService::class);

        $stok->tambah(
            barangId: $barang->id,
            jumlah: 12,
            sumber: 'stok_awal',
            nomorDasar: null,
            keterangan: null,
            petugasId: $petugas->id,
            tanggal: now()->startOfYear()->toDateString(),
        );

        $stok->tambah(
            barangId: $barang->id,
            jumlah: 10,
            sumber: 'pembelian',
            nomorDasar: '34/F/HI/VIII/2026',
            keterangan: null,
            petugasId: $petugas->id,
            tanggal: now()->toDateString(),
        );

        return $barang->refresh();
    }

    // =====================================================================
    // HAK AKSES
    // =====================================================================

    public function test_hanya_petugas_gudang_dan_kasubbag_yang_dapat_membuka(): void
    {
        foreach (['petugas_gudang', 'kasubbag'] as $peran) {
            $this->actingAs($this->buatPengguna($peran));
            $this->assertTrue(KartuKendali::canAccess(), $peran . ' seharusnya boleh membuka.');
        }

        foreach (['admin', 'ketua_tim', 'tim'] as $peran) {
            $this->actingAs($this->buatPengguna($peran, $peran === 'tim' || $peran === 'ketua_tim' ? $this->buatTim() : null));
            $this->assertFalse(KartuKendali::canAccess(), $peran . ' seharusnya tidak boleh membuka.');
        }
    }

    public function test_halaman_menampilkan_barang_beserta_saldonya(): void
    {
        $barang = $this->barangBerriwayat();
        $this->actingAs($this->buatPengguna('petugas_gudang'));

        Livewire::test(KartuKendali::class)
            ->assertOk()
            ->assertSee($barang->nama_barang)
            ->assertSee($barang->kode_barang);
    }

    // =====================================================================
    // ANGKA PADA KARTU
    // =====================================================================

    public function test_saldo_berjalan_mengikuti_urutan_tanggal(): void
    {
        $barang = $this->barangBerriwayat();

        $kartu = app(KartuKendaliService::class)->data($barang, (int) now()->year);

        $this->assertSame(12, $kartu['awal'], 'Saldo pembawaan mengisi kolom Stok Awal.');
        $this->assertSame(22, $kartu['akhir']);
        $this->assertSame([22], $kartu['mutasi']->pluck('saldo_sesudah')->map(fn ($n) => (int) $n)->all());
    }

    /**
     * Saldo akhir tahun lalu menjadi stok awal tahun ini. Inilah yang membuat
     * kartu tiap tahun bersambung, bukan berdiri sendiri-sendiri.
     */
    public function test_stok_awal_diambil_dari_saldo_sebelum_periode(): void
    {
        $barang  = $this->buatBarang(stokFisik: 0);
        $petugas = $this->buatPengguna('petugas_gudang');

        app(StokService::class)->tambah(
            barangId: $barang->id,
            jumlah: 40,
            sumber: 'pembelian',
            nomorDasar: 'NOTA-LAMA',
            keterangan: null,
            petugasId: $petugas->id,
            tanggal: now()->subYear()->toDateString(),
        );

        $kartu = app(KartuKendaliService::class)->data($barang->refresh(), (int) now()->year);

        $this->assertSame(40, $kartu['awal']);
        $this->assertSame(40, $kartu['akhir'], 'Tanpa transaksi tahun ini, sisa sama dengan stok awal.');
        $this->assertCount(0, $kartu['mutasi']);
    }

    /**
     * SIMPBI mulai dipakai dengan katalog yang sudah berstok, sehingga saldo
     * akhir kartu 2025 dicatat sebagai baris "Stok Awal" bertanggal 1 Januari.
     * Baris itu saldo pembuka, bukan barang yang baru datang; menampilkannya
     * pada kolom Masuk membuat satu angka yang sama muncul dua kali.
     */
    public function test_saldo_pembawaan_tidak_ikut_sebagai_transaksi_masuk(): void
    {
        $barang = $this->barangBerriwayat();

        $kartu = app(KartuKendaliService::class)->data($barang, (int) now()->year);

        $this->assertCount(1, $kartu['mutasi'], 'Hanya pembelian yang menjadi baris transaksi.');
        $this->assertSame('pembelian', $kartu['mutasi']->first()->sumber);
        $this->assertSame(
            10,
            (int) $kartu['mutasi']->sum('jumlah'),
            'Kolom Masuk tidak boleh ikut menghitung saldo pembawaan.',
        );
    }

    /**
     * Transaksi 31 Desember pernah hilang dari kartunya sendiri: baris buku
     * besar tersimpan berikut jamnya ("2025-12-31 00:00:00") sementara batas
     * periode dituliskan sebagai tanggal murni, sehingga pada SQLite yang
     * membandingkan keduanya sebagai teks baris itu terbaca melewati batas.
     *
     * Akibatnya bukan sekadar satu baris yang tidak tercetak: Stok Akhir kartu
     * tahun itu berhenti sebelum transaksinya sedangkan Stok Awal tahun
     * berikutnya sudah memperhitungkannya, dan kedua kartu berhenti bersambung
     * — padahal bersambungnya kartu antartahun itulah yang membuatnya dapat
     * dipakai sebagai bahan rekonsiliasi.
     */
    public function test_transaksi_31_desember_tercatat_pada_kartu_tahun_itu(): void
    {
        $barang  = $this->buatBarang(stokFisik: 0);
        $petugas = $this->buatPengguna('petugas_gudang');

        app(StokService::class)->tambah(
            barangId: $barang->id,
            jumlah: 6,
            sumber: 'pembelian',
            nomorDasar: 'NOTA-3112',
            keterangan: null,
            petugasId: $petugas->id,
            tanggal: now()->subYear()->endOfYear()->toDateString(),
        );

        $layanan = app(KartuKendaliService::class);
        $barang  = $barang->refresh();

        $lalu = $layanan->data($barang, (int) now()->subYear()->year);
        $ini  = $layanan->data($barang, (int) now()->year);

        $this->assertCount(1, $lalu['mutasi'], 'Transaksi 31 Desember adalah baris kartu tahun itu.');
        $this->assertSame(6, $lalu['akhir']);

        $this->assertCount(0, $ini['mutasi'], 'Transaksi itu tidak boleh ikut ke kartu tahun berikutnya.');
        $this->assertSame(
            $lalu['akhir'],
            $ini['awal'],
            'Stok Akhir tahun lalu harus menjadi Stok Awal tahun ini.',
        );
    }

    // =====================================================================
    // EKSPOR LEMBAR SEBAR
    // =====================================================================

    /**
     * Teks sebuah sel.
     *
     * Sebagian sel lembar master berisi RichText — bawaan ekspor Google Sheets
     * pada berkas Sub-Bagian Umum — sehingga nilainya tidak selalu berupa
     * string biasa meski yang terbaca di layar sama saja.
     */
    private function teks(Worksheet $lembar, string $sel): ?string
    {
        $nilai = $lembar->getCell($sel)->getValue();

        return $nilai instanceof RichText ? $nilai->getPlainText() : $nilai;
    }

    private function buka(BinaryFileResponse $respons): Spreadsheet
    {
        return IOFactory::createReader('Xlsx')->load($respons->getFile()->getPathname());
    }

    private function ekspor(BarangPersediaan ...$barang): Spreadsheet
    {
        $daftar = BarangPersediaan::whereIn('id', array_map(fn ($b) => $b->id, $barang))
            ->orderBy('kode_barang')
            ->get();

        return $this->buka(app(KartuKendaliService::class)->spreadsheet($daftar, (int) now()->year));
    }

    /**
     * Bita PDF hasil ekspor kartu kendali.
     *
     * @return array{0: string, 1: string} bita PDF dan tampilan HTML yang membentuknya
     */
    private function eksporPdf(BarangPersediaan ...$barang): array
    {
        $daftar = BarangPersediaan::whereIn('id', array_map(fn ($b) => $b->id, $barang))
            ->orderBy('kode_barang')
            ->get();

        $tahun   = (int) now()->year;
        $layanan = app(KartuKendaliService::class);

        $respons = $layanan->pdf($daftar, $tahun);

        ob_start();
        $respons->sendContent();
        $pdf = (string) ob_get_clean();

        // Isi PDF terkompresi sehingga memeriksa teksnya dari dalam berkas
        // rapuh. Tampilan yang membentuknya diperiksa terpisah, dari bahan yang
        // sama persis, sehingga kegagalan pengujian tetap mudah dibaca.
        $html = view('pdf.kartu-kendali', [
            'dicetak'  => now(),
            'pencetak' => null,
            'judul'    => 'uji',
            'kartu'    => $daftar->map(fn (BarangPersediaan $b) => $layanan->data($b, $tahun))->all(),
        ])->render();

        return [$pdf, $html];
    }

    /**
     * Sub-Bagian Umum memakai kartu kendali untuk dicetak dan diarsipkan,
     * sehingga PDF menjadi bentuk utamanya.
     */
    public function test_ekspor_pdf_menghasilkan_berkas_pdf_yang_sah(): void
    {
        [$pdf] = $this->eksporPdf($this->barangBerriwayat());

        $this->assertStringStartsWith('%PDF', $pdf, 'Keluaran harus berkas PDF, bukan yang lain.');
        $this->assertStringContainsString('%%EOF', $pdf, 'Berkas PDF harus lengkap sampai penandanya.');
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    /** Identitas barang dan seluruh transaksinya harus muncul pada kartu. */
    public function test_pdf_memuat_identitas_barang_beserta_transaksinya(): void
    {
        $barang = $this->barangBerriwayat();

        [, $html] = $this->eksporPdf($barang);

        // Nama barang ditulis dengan huruf besar seluruhnya, sama seperti sel
        // C4 pada lembar master; katalog sendiri menyimpannya apa adanya.
        $this->assertStringContainsString(mb_strtoupper($barang->nama_barang), $html);
        $this->assertStringContainsString($barang->kode_lengkap, $html);
        $this->assertStringContainsString($barang->satuan, $html);
        $this->assertStringContainsString('34/F/HI/VIII/2026', $html, 'Nomor dasar transaksi harus tercantum.');
        // Tanggal mengikuti format berkas Excel (numFmt dd/MM/yyyy), bukan
        // format layar dd-mm-yyyy, sebab PDF harus seidentik mungkin dengannya.
        $this->assertStringContainsString(now()->format('d/m/Y'), $html, 'Tanggal ditulis dd/MM/yyyy seperti Excel.');
    }

    /**
     * Saldo pada PDF harus sama dengan yang dihitung layanan — keduanya memang
     * berangkat dari sumber yang sama, dan pengujian ini menjaganya tetap
     * begitu.
     */
    public function test_saldo_pada_pdf_sama_dengan_perhitungan_layanan(): void
    {
        $barang = $this->barangBerriwayat();
        $kartu  = app(KartuKendaliService::class)->data($barang, (int) now()->year);

        [, $html] = $this->eksporPdf($barang);

        $this->assertSame(12, $kartu['awal']);
        $this->assertSame(22, $kartu['akhir'], 'Stok awal 12 ditambah pembelian 10.');

        $this->assertMatchesRegularExpression(
            '/Stok Awal.*?>' . $kartu['awal'] . '</s',
            $html,
            'Baris Stok Awal harus memuat saldo pembuka.',
        );
        $this->assertMatchesRegularExpression(
            '/Stok Akhir.*?>' . $kartu['akhir'] . '</s',
            $html,
            'Baris Stok Akhir harus memuat saldo penutup.',
        );
    }

    /**
     * Barang yang tidak bergerak tetap mendapat kartunya, sebagaimana pada XLSX.
     *
     * Kartunya bukan diberi kalimat "tidak ada transaksi": berkas master tidak
     * memuat kalimat seperti itu, melainkan grid kosong yang siap diisi tangan.
     * Karena itu kartu kosong tetap menggambar Stok Awal, kedua puluh slot, dan
     * Stok Akhir apa adanya.
     */
    public function test_barang_tanpa_transaksi_tetap_mendapat_kartu(): void
    {
        $diam = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000999', 'nama_barang' => 'Barang Diam']);

        [$pdf, $html] = $this->eksporPdf($diam);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString(mb_strtoupper('Barang Diam'), $html);
        $this->assertStringContainsString('Stok Awal', $html);
        $this->assertStringContainsString('Stok Akhir', $html);
        // Slot ke-20 tetap tergambar dan bernomor meski kartunya kosong.
        $this->assertMatchesRegularExpression('/>\s*20\s*</', $html, 'Grid dua puluh slot tetap utuh.');
    }

    /** Satu kartu per halaman, sebab kartu diarsipkan per barang. */
    public function test_setiap_barang_mendapat_halamannya_sendiri(): void
    {
        $satu = $this->barangBerriwayat();
        $dua  = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000999', 'nama_barang' => 'Barang Diam']);

        [, $html] = $this->eksporPdf($satu, $dua);

        // Dihitung dari atribut kelasnya, bukan dari kata "pemisah-halaman"
        // begitu saja, sebab kata itu juga muncul sekali pada aturan gayanya.
        $this->assertSame(
            1,
            substr_count($html, 'class="pemisah-halaman"'),
            'Dua kartu dipisahkan tepat satu pemisah halaman.',
        );
        $this->assertStringContainsString(mb_strtoupper($satu->nama_barang), $html);
        $this->assertStringContainsString(mb_strtoupper('Barang Diam'), $html);
    }

    /**
     * Kartu yang transaksinya melampaui satu halaman harus utuh, dan judul
     * kolomnya ikut terbawa ke halaman berikutnya.
     *
     * Pengulangan judul itu dikerjakan DomPDF sendiri atas dasar `<thead>`,
     * sehingga yang dijaga di sini adalah keberadaan `<thead>` dan keutuhan
     * seluruh barisnya.
     */
    public function test_kartu_panjang_tetap_utuh_dengan_judul_kolom_berulang(): void
    {
        $barang  = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000998']);
        $petugas = $this->buatPengguna('petugas_gudang');
        $stok    = app(StokService::class);

        for ($i = 1; $i <= 60; $i++) {
            $stok->tambah(
                barangId: $barang->id,
                jumlah: 2,
                sumber: 'pembelian',
                nomorDasar: 'BON-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                keterangan: null,
                petugasId: $petugas->id,
                tanggal: now()->toDateString(),
            );
        }

        [$pdf, $html] = $this->eksporPdf($barang);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('<thead>', $html, 'Judul kolom harus berada di dalam thead agar ikut terulang.');

        foreach (['BON-001', 'BON-030', 'BON-060'] as $nomor) {
            $this->assertStringContainsString($nomor, $html, "Transaksi {$nomor} tidak boleh hilang.");
        }

        $this->assertSame(
            120,
            (int) app(KartuKendaliService::class)->data($barang, (int) now()->year)['akhir'],
            'Saldo akhir enam puluh pembelian dua satuan.',
        );
    }

    public function test_ekspor_menghasilkan_satu_lembar_per_barang(): void
    {
        $satu = $this->barangBerriwayat();
        $dua  = $this->buatBarang(stokFisik: 0, tambahan: ['kode_barang' => '000999', 'nama_barang' => 'Barang Diam']);

        $buku = $this->ekspor($satu, $dua);

        $this->assertSame(2, $buku->getSheetCount(), 'Barang yang tidak bergerak tetap mendapat lembarnya sendiri.');
        $this->assertSame(
            ['1', '2'],
            $buku->getSheetNames(),
            'Lembar dinamai menurut nomor urut, mengikuti berkas Sub-Bagian Umum.',
        );
    }

    /** Tata letaknya harus tetap sama dengan kartu kendali Sub-Bagian Umum. */
    public function test_tata_letak_lembar_mengikuti_kartu_kendali_subbagian_umum(): void
    {
        $barang = $this->barangBerriwayat();

        $lembar = $this->ekspor($barang)->getSheet(0);

        $this->assertSame('KARTU KENDALI BARANG PERSEDIAAN (ATK/ARK)', $this->teks($lembar, 'A1'));
        $this->assertStringContainsString('BADAN PUSAT STATISTIK KOTA JAKARTA BARAT', (string) $this->teks($lembar, 'A2'));

        $this->assertSame('Kode Barang', $this->teks($lembar, 'A3'));
        $this->assertSame($barang->kode_lengkap, $this->teks($lembar, 'C3'));
        $this->assertSame('Nama Barang', $this->teks($lembar, 'A4'));
        $this->assertSame(
            mb_strtoupper($barang->nama_barang),
            $this->teks($lembar, 'C4'),
            'Nama barang ditulis dengan huruf besar seperti berkas aslinya.',
        );
        $this->assertSame('Satuan', $this->teks($lembar, 'A5'));
        $this->assertSame('Periode', $this->teks($lembar, 'A6'));
        $this->assertNull($this->teks($lembar, 'A7'), 'Baris tujuh adalah pemisah kop dan tabel.');

        $this->assertSame(
            ['No.', 'Nomor Dasar M/K', 'Tanggal M/K', 'Uraian M/K', 'Harga Satuan', 'Masuk (M)', 'Keluar (K)', 'Sisa', 'Nilai'],
            array_map(fn (string $kolom) => $this->teks($lembar, $kolom . '8'), range('A', 'I')),
        );

        // Penomoran kolom ditulis sebagai bilangan negatif berformat kurung —
        // begitulah berkas aslinya, dan bilangan tetap dapat dihitung.
        $this->assertSame(
            [-1, -2, -3, -4, -5, -6, -7, -8, -9],
            array_map(fn (string $kolom) => $lembar->getCell($kolom . '9')->getValue(), range('A', 'I')),
        );

        $this->assertSame('Stok Awal', $this->teks($lembar, 'A10'));
        $this->assertSame(12, $lembar->getCell('H10')->getValue());
        $this->assertSame('dst', $this->teks($lembar, 'A31'));
        $this->assertSame('Stok Akhir', $this->teks($lembar, 'A32'));
        $this->assertSame(22, $lembar->getCell('H32')->getValue(), 'Kartu ditutup saldo akhir periode.');

        $this->assertSame(
            ['A1:I1', 'A2:I2', 'A10:G10', 'A32:G32'],
            array_values(array_map(fn ($m) => (string) $m, $lembar->getMergeCells())),
        );

        // Dua puluh slot transaksi tetap bernomor walau kosong, seperti grid
        // berkas aslinya yang selalu siap diisi tangan.
        $this->assertSame(20, $lembar->getCell('A30')->getValue());
    }

    public function test_baris_transaksi_memuat_nomor_dasar_tanggal_dan_rumus_sisa(): void
    {
        $barang = $this->barangBerriwayat();

        $lembar = $this->ekspor($barang)->getSheet(0);

        $this->assertSame('34/F/HI/VIII/2026', $this->teks($lembar, 'B11'));
        $this->assertSame('Pembelian', $this->teks($lembar, 'D11'));
        $this->assertSame(10, $lembar->getCell('F11')->getValue(), 'Masuk terisi.');
        $this->assertNull($lembar->getCell('G11')->getValue(), 'Keluar kosong pada transaksi masuk.');

        $this->assertSame(
            TanggalExcel::PHPToExcel(now()->startOfDay()),
            $lembar->getCell('C11')->getValue(),
            'Tanggal ditulis sebagai nilai tanggal Excel, bukan teks.',
        );
        $this->assertSame('dd/MM/yyyy', $lembar->getStyle('C11')->getNumberFormat()->getFormatCode());

        $this->assertSame(
            '=H10+F11-G11',
            $lembar->getCell('H11')->getValue(),
            'Kolom Sisa memakai rumus, seperti berkas Sub-Bagian Umum.',
        );
    }

    /**
     * Seluruh slot transaksi bertulisan sama.
     *
     * Berkas Sub-Bagian Umum yang menjadi acuan memakai Times New Roman 10
     * sebagai gaya bawaan buku kerjanya, sehingga setiap sel yang dulu tidak
     * pernah disentuh pengelolanya tetap begitu — pada 114 dari 119 lembarnya,
     * slot transaksi yang belum terpakai masih bertulisan bawaan itu, dengan
     * batas baris yang berpindah-pindah tiap lembar. Cacat itu ikut terbawa ke
     * templat dan muncul kembali pada hasil ekspor: angka pada kolom Masuk
     * baris ketiga tercetak Times New Roman 10 rata kiri di tengah tabel yang
     * selebihnya Arial 11 rata tengah.
     *
     * Pengujian ini menjaga agar templat tetap seragam, sebab cacatnya tidak
     * lahir dari kode melainkan dari berkas templat yang sewaktu-waktu dapat
     * disalin ulang dari acuan.
     */
    public function test_seluruh_slot_transaksi_bertulisan_seragam(): void
    {
        $barang = $this->barangBerriwayat();
        $lembar = $this->ekspor($barang)->getSheet(0);

        foreach (range(11, 31) as $baris) {
            foreach (range('A', 'H') as $kolom) {
                $gaya = $lembar->getStyle($kolom . $baris);

                $this->assertSame(
                    'Arial',
                    $gaya->getFont()->getName(),
                    "Sel {$kolom}{$baris} memakai rupa huruf yang berbeda dari isi tabel lainnya.",
                );
                $this->assertSame(11.0, (float) $gaya->getFont()->getSize(), "Ukuran huruf {$kolom}{$baris}.");
                $this->assertSame(
                    'center',
                    $gaya->getAlignment()->getHorizontal(),
                    "Perataan {$kolom}{$baris}.",
                );
            }
        }
    }

    /**
     * Nomor bon pengeluaran, bukan kode permintaan, yang mengisi kolom Nomor
     * Dasar — itulah yang dikenali Sub-Bagian Umum.
     */
    public function test_pemakaian_memakai_nomor_bon_pada_kolom_nomor_dasar(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 30, stokHold: 6);

        $permintaan = $this->buatPermintaan(
            $tim,
            $pengaju,
            [['barang' => $barang, 'diminta' => 6]],
            status: 'siap_diambil',
        );

        app(StokService::class)->konversi($permintaan, $this->buatPengguna('petugas_gudang')->id);

        $lembar = $this->ekspor($barang)->getSheet(0);

        $this->assertSame($permintaan->refresh()->nomor_bon, $this->teks($lembar, 'B11'));
        $this->assertSame('Pemakaian', $this->teks($lembar, 'D11'));
        $this->assertSame(6, $lembar->getCell('G11')->getValue(), 'Keluar terisi.');
        $this->assertNull($lembar->getCell('F11')->getValue(), 'Masuk kosong pada transaksi keluar.');
    }

    /**
     * Master hanya menyediakan dua puluh slot; barang yang ramai harus tetap
     * termuat tanpa kehilangan baris penutupnya.
     */
    public function test_riwayat_lebih_dari_dua_puluh_baris_melebarkan_tabel(): void
    {
        $barang  = $this->buatBarang(stokFisik: 0);
        $petugas = $this->buatPengguna('petugas_gudang');
        $stok    = app(StokService::class);

        foreach (range(0, 24) as $ke) {
            $stok->tambah(
                barangId: $barang->id,
                jumlah: 1,
                sumber: 'pembelian',
                nomorDasar: 'NOTA-' . $ke,
                keterangan: null,
                petugasId: $petugas->id,
                tanggal: now()->startOfYear()->addDays($ke)->toDateString(),
            );
        }

        $lembar = $this->ekspor($barang->refresh())->getSheet(0);

        $this->assertSame(25, $lembar->getCell('A35')->getValue(), 'Baris tambahan ikut bernomor.');
        $this->assertSame('=H34+F35-G35', $lembar->getCell('H35')->getValue());
        $this->assertSame('dst', $this->teks($lembar, 'A36'), 'Baris "dst" turun mengikuti tabel.');
        $this->assertSame('Stok Akhir', $this->teks($lembar, 'A37'));
        $this->assertSame(25, $lembar->getCell('H37')->getValue());

        $this->assertSame(
            ['A1:I1', 'A2:I2', 'A10:G10', 'A37:G37'],
            array_values(array_map(fn ($m) => (string) $m, $lembar->getMergeCells())),
            'Blok Stok Akhir ikut bergeser beserta gabungan selnya.',
        );

        $this->assertSame(
            'thin',
            $lembar->getStyle('B35')->getBorders()->getLeft()->getBorderStyle(),
            'Baris tambahan tetap bergaris seperti slot bawaan.',
        );
    }

    /**
     * Perbandingan langsung dengan berkas Sub-Bagian Umum.
     *
     * Yang diperiksa properti lembar yang tidak terlihat dari isinya — gabungan
     * sel, lebar dan kolom tersembunyi, tinggi baris, pengaturan cetak, dan
     * warna tab — sebab justru itulah yang membuat berkas terasa "berubah"
     * ketika tidak sama, padahal angkanya benar semua.
     */
    public function test_properti_lembar_sama_dengan_berkas_subbagian_umum(): void
    {
        $acuan = base_path('docs/Kartu-Kendali-Persediaan-2025.xlsx');

        if (! is_file($acuan)) {
            $this->markTestSkipped('Berkas acuan docs/Kartu-Kendali-Persediaan-2025.xlsx tidak tersedia.');
        }

        $pembaca = IOFactory::createReader('Xlsx');
        $pembaca->setLoadSheetsOnly(['1']);
        $asli = $pembaca->load($acuan)->getSheet(0);

        $hasil = $this->ekspor($this->barangBerriwayat())->getSheet(0);

        $this->assertSame(
            array_values(array_map(fn ($m) => (string) $m, $asli->getMergeCells())),
            array_values(array_map(fn ($m) => (string) $m, $hasil->getMergeCells())),
        );

        foreach (range('A', 'I') as $kolom) {
            $this->assertSame(
                $asli->getColumnDimension($kolom)->getVisible(),
                $hasil->getColumnDimension($kolom)->getVisible(),
                'Kolom ' . $kolom . ' harus sama-sama tampak atau sama-sama tersembunyi.',
            );
        }

        foreach (range(1, 32) as $baris) {
            $this->assertEqualsWithDelta(
                $asli->getRowDimension($baris)->getRowHeight(),
                $hasil->getRowDimension($baris)->getRowHeight(),
                0.01,
                'Tinggi baris ' . $baris . ' menyimpang.',
            );
        }

        $this->assertSame(
            $asli->getSheetView()->getZoomScale() ?? 100,
            $hasil->getSheetView()->getZoomScale() ?? 100,
        );
        $this->bandingkanPengaturanCetak($asli, $hasil);

        $this->assertSame(
            $asli->getTabColor()->getRGB(),
            $hasil->getTabColor()->getRGB(),
        );
    }

    private function bandingkanPengaturanCetak(Worksheet $asli, Worksheet $hasil): void
    {
        $cetakAsli  = $asli->getPageSetup();
        $cetakHasil = $hasil->getPageSetup();

        $this->assertSame($cetakAsli->getOrientation(), $cetakHasil->getOrientation());
        $this->assertSame($cetakAsli->getPaperSize(), $cetakHasil->getPaperSize());
        $this->assertSame($cetakAsli->getScale(), $cetakHasil->getScale());
        /*
         * Fit-to-page harus mati supaya skala 96% benar-benar dipakai. Tidak
         * dibandingkan dengan berkas acuan: acuan menyimpan <pageSetUpPr/>
         * kosong yang dibaca PhpSpreadsheet sebagai menyala, sedangkan menurut
         * standarnya — dan menurut Excel — atribut yang tidak ditulis berarti
         * mati, persis seperti hasil ekspor ini.
         */
        $this->assertFalse($cetakHasil->getFitToPage());

        foreach (['getLeft', 'getRight', 'getTop', 'getBottom', 'getHeader', 'getFooter'] as $sisi) {
            $this->assertEqualsWithDelta(
                $asli->getPageMargins()->{$sisi}(),
                $hasil->getPageMargins()->{$sisi}(),
                0.001,
                'Margin ' . $sisi . ' menyimpang.',
            );
        }
    }
}
