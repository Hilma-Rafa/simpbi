<?php

namespace App\Services;

use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PenulisXlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penurunan data kartu kendali barang persediaan.
 *
 * Dikumpulkan di satu tempat karena angka yang sama kini muncul di tiga muka:
 * dialog rincian di layar, hasil cetak PDF per barang, dan ekspor lembar sebar
 * seluruh barang. Ketiganya wajib menampilkan angka yang sama persis — kartu
 * kendali adalah bahan rekonsiliasi, dan dua kartu yang berbeda isinya untuk
 * barang yang sama justru merusak gunanya.
 */
class KartuKendaliService
{
    /**
     * Lembar master kartu kendali.
     *
     * Berkas ini disuling dari kartu kendali 2025 milik Sub-Bagian Umum: satu
     * lembar aslinya, isinya dikosongkan, gayanya dibiarkan utuh. Tata letak
     * ekspor tidak dibangun ulang lewat kode melainkan disalin dari sini,
     * sebab yang membuat dua berkas terasa sama bukan hanya yang terlihat —
     * lebar kolom, kolom tersembunyi, tinggi baris, warna tab, dan pengaturan
     * cetaknya ikut menentukan, dan semuanya sudah tersimpan di dalam berkas
     * ini tanpa perlu ditulis ulang sebagai kode.
     */
    public const MASTER = 'resources/templates/kartu-kendali.xlsx';

    /** Baris saldo pembuka pada lembar master. */
    protected const BARIS_STOK_AWAL = 10;

    /** Baris transaksi pertama pada lembar master. */
    protected const BARIS_TRANSAKSI = 11;

    /** Banyaknya slot transaksi yang sudah tergaris pada lembar master. */
    protected const SLOT_BAWAAN = 20;

    /** Baris "dst" pada lembar master, tepat sesudah slot terakhir. */
    protected const BARIS_DST = 31;

    /** Baris saldo penutup pada lembar master. */
    protected const BARIS_STOK_AKHIR = 32;

    /**
     * Tahun-tahun yang dapat diterbitkan kartunya.
     *
     * Berisi tahun yang benar-benar memiliki pergerakan stok, ditambah tahun
     * berjalan supaya kartu tahun ini tetap dapat dicetak meski belum ada
     * transaksi. Urutan menurun agar tahun terbaru berada di paling atas.
     *
     * @return array<int,string>
     */
    public function tahunTersedia(?BarangPersediaan $barang = null): array
    {
        $kueri = $barang
            ? $barang->mutasi()
            : DB::table('mutasi_stok');

        $tahun = $kueri
            ->selectRaw('DISTINCT ' . $this->petikTahun() . ' AS tahun')
            ->pluck('tahun')
            ->map(fn ($t) => (int) $t)
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return $tahun->mapWithKeys(fn (int $t) => [$t => (string) $t])->all();
    }

    /**
     * Data satu kartu kendali.
     *
     * Stok awal diambil dari saldo transaksi terakhir sebelum periode, bukan
     * dihitung mundur dari stok fisik, sebab kartu kendali harus mencerminkan
     * buku besar apa adanya. Bila barang belum memiliki pergerakan sebelum
     * periode itu, stok awalnya nol dan selisih terhadap stok fisik justru
     * terlihat sebagai temuan. Stok akhir memakai saldo transaksi terakhir di
     * dalam periode, dan kembali ke stok awal bila periodenya kosong.
     *
     * Pelaksana tiap baris hanya dimuat bila diminta. Satu-satunya tampilan
     * yang menyebutkannya adalah dialog rincian pergerakan; daftar kartu
     * kendali dan berkas PDF tidak menampilkannya sama sekali. Dimuat selalu,
     * ia menambah satu kueri `users` untuk setiap barang pada daftar — sepuluh
     * kueri per halaman untuk keterangan yang tidak pernah terlihat.
     *
     * @return array<string,mixed>
     */
    public function data(BarangPersediaan $barang, int $tahun, bool $denganPelaksana = false): array
    {
        // Periode dinyatakan sebagai selang setengah terbuka: sejak 1 Januari
        // tahun ini sampai sebelum 1 Januari tahun berikutnya.
        //
        // Sebagian baris buku besar tersimpan berikut jamnya — Eloquent
        // menuliskan kolom bertipe DATE memakai format tanggal bawaannya,
        // sehingga transaksi 31 Desember tercatat sebagai "2025-12-31 00:00:00"
        // sementara baris hasil seeder tertulis "2025-12-31" saja. Dengan batas
        // atas berupa tanggal murni, transaksi yang berimbuhan jam itu justru
        // terbuang dari kartunya sendiri: pada SQLite keduanya dibandingkan
        // sebagai teks, dan teks yang lebih panjang selalu lebih besar. Batas
        // bawah tidak terkena masalah yang sama, sebab imbuhan jam membuat
        // nilainya lebih besar daripada tanggal murni 1 Januari.
        //
        // Cara ini dipilih alih-alih membungkus kolomnya dengan fungsi tanggal
        // supaya indeks (barang_id, tanggal) tetap terpakai.
        $mulai      = Carbon::create($tahun, 1, 1)->toDateString();
        $berikutnya = Carbon::create($tahun + 1, 1, 1)->toDateString();

        $awal = (int) ($barang->mutasi()
            ->where(fn (Builder $kueri) => $this->saldoPembuka($kueri, $mulai))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->value('saldo_sesudah') ?? 0);

        $mutasi = $barang->mutasi()
            ->when($denganPelaksana, fn (Builder $kueri) => $kueri->with('petugas'))
            ->where('tanggal', '>=', $mulai)
            ->where('tanggal', '<', $berikutnya)
            // Baris saldo pembawaan sudah terwakili kolom Stok Awal di atas;
            // membiarkannya ikut sebagai transaksi membuat satu angka yang sama
            // muncul dua kali pada kartu yang sama.
            ->whereNot(fn (Builder $kueri) => $this->bawaanTahunLalu($kueri, $mulai))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        return [
            'barang'  => $barang->loadMissing('kategori'),
            'mutasi'  => $mutasi,
            'tahun'   => $tahun,
            'periode' => 'Januari s.d. Desember ' . $tahun,
            'awal'    => $awal,
            'akhir'   => $mutasi->isNotEmpty() ? (int) $mutasi->last()->saldo_sesudah : $awal,
        ];
    }

    /**
     * Transaksi yang membentuk saldo pembuka periode.
     *
     * Selain seluruh transaksi sebelum periode, baris "Stok Awal" bertanggal 1
     * Januari ikut dihitung. Baris itu memang tercatat di dalam periode, tetapi
     * maknanya saldo akhir tahun sebelumnya: SIMPBI mulai dipakai dengan
     * katalog yang sudah berstok sementara buku besarnya masih kosong, sehingga
     * saldo pembawaan itu tidak punya tempat lain untuk dicatat.
     */
    protected function saldoPembuka(Builder $kueri, string $mulai): Builder
    {
        return $kueri
            ->whereDate('tanggal', '<', $mulai)
            ->orWhere(fn (Builder $q) => $this->bawaanTahunLalu($q, $mulai));
    }

    /** Baris saldo pembawaan: "Stok Awal" yang jatuh tepat pada awal periode. */
    protected function bawaanTahunLalu(Builder $kueri, string $mulai): Builder
    {
        return $kueri
            ->whereDate('tanggal', $mulai)
            ->where('sumber', 'stok_awal');
    }

    /**
     * Ringkasan satu baris per barang, untuk ditampilkan pada daftar.
     *
     * @param  Collection<int,BarangPersediaan>  $barang
     * @return Collection<int,array<string,mixed>>
     */
    public function ringkasan(Collection $barang, int $tahun): Collection
    {
        return $barang->map(function (BarangPersediaan $b) use ($tahun): array {
            $kartu = $this->data($b, $tahun);

            return [
                'barang' => $b,
                'awal'   => $kartu['awal'],
                'masuk'  => (int) $kartu['mutasi']->where('jumlah', '>', 0)->sum('jumlah'),
                'keluar' => (int) abs($kartu['mutasi']->where('jumlah', '<', 0)->sum('jumlah')),
                'akhir'  => $kartu['akhir'],
                'baris'  => $kartu['mutasi']->count(),
            ];
        });
    }

    /**
     * Uraian M/K sebagaimana ditulis pada kartu kendali.
     *
     * Daftarnya tinggal satu, di MutasiStok, dan dibaca dari sana. Sebelumnya
     * kelas ini menyimpan salinannya sendiri yang sempat berbeda satu kata —
     * "Reklasifikasi Aset" di sini, "Reklasifikasi ke Aset" di sana — padahal
     * keseragaman ejaan itulah salah satu hal yang hendak diperbaiki sistem.
     */
    public static function uraian(?string $sumber): string
    {
        return MutasiStok::URAIAN[$sumber] ?? (string) $sumber;
    }

    /**
     * Ekspor kartu kendali sebagai berkas sebar, satu lembar per barang.
     *
     * Setiap lembar adalah salinan utuh lembar master, sehingga seluruh tata
     * letak berkas Sub-Bagian Umum terbawa apa adanya dan yang dikerjakan di
     * sini tinggal mengisi selnya. Lembarnya dinamai menurut nomor urut,
     * mengikuti penamaan berkas aslinya.
     *
     * @param  Collection<int,BarangPersediaan>  $barang
     */
    /**
     * Kartu kendali sebagai PDF, satu kartu per halaman.
     *
     * Bentuk utama yang diminta Sub-Bagian Umum: kartu kendali diarsipkan dan
     * dicetak, bukan diolah. Berkas sebar tetap disediakan bagi yang memang
     * perlu mengolah angkanya.
     *
     * Datanya diambil lewat {@see static::data()}, sumber yang sama dengan
     * ekspor XLSX, sehingga tidak ada perhitungan kedua yang dapat menyimpang.
     * Penamaan berkas dan penyematan waktu cetak diserahkan kepada
     * EksporRiwayatService agar seragam dengan dokumen SIMPBI lainnya.
     *
     * @param  Collection<int,BarangPersediaan>  $barang
     */
    public function pdf(Collection $barang, int $tahun): Response
    {
        $judul = 'Kartu Kendali Persediaan ' . $tahun;

        // Lanskap, seperti kartu kendali Sub-Bagian Umum: tanpa ini
        // EksporRiwayatService memilih potret bawaannya dan kartunya tercetak
        // tegak, berlawanan dengan berkas Excel yang ditiru.
        return app(EksporRiwayatService::class)->pdfTampilan($judul, 'pdf.kartu-kendali', [
            'judul' => $judul,
            'kartu' => $barang->values()->map(fn (BarangPersediaan $b): array => $this->data($b, $tahun))->all(),
        ], 'landscape');
    }

    public function spreadsheet(Collection $barang, int $tahun): BinaryFileResponse
    {
        $buku   = IOFactory::createReader('Xlsx')->load(base_path(static::MASTER));
        $master = $buku->getSheet(0);

        // Nama lembar harus unik di dalam satu berkas, jadi master melepas nama
        // "1" lebih dulu supaya lembar pertama hasil ekspor dapat memakainya.
        $master->setTitle('Master');

        foreach ($barang->values() as $urutan => $b) {
            $lembar = clone $master;
            $lembar->setTitle((string) ($urutan + 1));
            $buku->addSheet($lembar);

            $this->isiLembar($lembar, $b, $tahun);
        }

        if ($barang->isEmpty()) {
            // Berkas tanpa satu lembar pun tidak dapat dibuka Excel, sehingga
            // masternya sendiri yang diterbitkan sebagai kartu kosong.
            $master->setTitle('1');
        } else {
            $buku->removeSheetByIndex($buku->getIndex($master));
        }

        $buku->setActiveSheetIndex(0);

        $berkas = tempnam(sys_get_temp_dir(), 'simpbi_kk_');

        (new PenulisXlsx($buku))->save($berkas);

        // Lembar dilepas dari memori begitu berkasnya jadi; seratus lembar
        // kartu yang tetap tersimpan akan membebani permintaan berikutnya.
        $buku->disconnectWorksheets();

        return response()
            ->download($berkas, 'Kartu-Kendali-Persediaan-' . $tahun . '.xlsx')
            ->deleteFileAfterSend();
    }

    /** Mengisi satu lembar salinan master dengan kartu satu barang. */
    protected function isiLembar(Worksheet $lembar, BarangPersediaan $barang, int $tahun): void
    {
        $kartu  = $this->data($barang, $tahun);
        $mutasi = $kartu['mutasi'];

        $tambahan = max(0, $mutasi->count() - static::SLOT_BAWAAN);

        if ($tambahan > 0) {
            $this->lebarkanTabel($lembar, $tambahan);
        }

        $lembar->setCellValue('A2', 'BADAN PUSAT STATISTIK KOTA JAKARTA BARAT TAHUN ' . $tahun);
        $lembar->setCellValue('C3', $barang->kode_lengkap);
        // Berkas Sub-Bagian Umum menulis nama barang dengan huruf besar
        // seluruhnya. Katalog menyimpannya apa adanya, jadi hurufnya dinaikkan
        // di sini saja — yang tersimpan di basis data tidak ikut berubah.
        $lembar->setCellValue('C4', mb_strtoupper($barang->nama_barang));
        $lembar->setCellValue('C5', $barang->satuan);
        $lembar->setCellValue('C6', $kartu['periode']);

        $lembar->setCellValue('H' . static::BARIS_STOK_AWAL, $kartu['awal']);
        $lembar->setCellValue('I' . static::BARIS_STOK_AWAL, 0);

        foreach ($mutasi->values() as $urutan => $baris) {
            $nomorBaris = static::BARIS_TRANSAKSI + $urutan;

            $lembar->setCellValue('A' . $nomorBaris, $urutan + 1);
            $lembar->setCellValue('B' . $nomorBaris, $baris->nomor_dasar);
            // Tanggal ditulis sebagai nilai tanggal Excel, bukan teks, supaya
            // dapat diurutkan dan disaring; tampilannya diatur format sel yang
            // sudah dibawa master.
            $lembar->setCellValue('C' . $nomorBaris, TanggalExcel::PHPToExcel($baris->tanggal));
            $lembar->setCellValue('D' . $nomorBaris, static::uraian($baris->sumber));
            $lembar->setCellValue('F' . $nomorBaris, $baris->jumlah > 0 ? (int) $baris->jumlah : null);
            $lembar->setCellValue('G' . $nomorBaris, $baris->jumlah < 0 ? (int) abs($baris->jumlah) : null);
            // Sisa ditulis sebagai rumus, seperti berkas aslinya: kartu yang
            // sudah terbit kerap dikoreksi di tempat, dan rumus membuat seluruh
            // kolomnya ikut menyesuaikan alih-alih menjadi angka yang bohong.
            $lembar->setCellValue(
                'H' . $nomorBaris,
                '=H' . ($nomorBaris - 1) . '+F' . $nomorBaris . '-G' . $nomorBaris,
            );
        }

        $barisAkhir = static::BARIS_STOK_AKHIR + $tambahan;

        $lembar->setCellValue('H' . $barisAkhir, $kartu['akhir']);
        $lembar->setCellValue('I' . $barisAkhir, 0);
    }

    /**
     * Menambah slot transaksi bagi barang yang riwayatnya lebih dari dua puluh.
     *
     * Baris disisipkan sebelum baris "dst" supaya penutup kartu tetap berada di
     * bawah, dan gayanya disalin dari slot terakhir bawaan master — baris baru
     * lahir tanpa garis, dan tabel yang separuh barisnya tidak bergaris justru
     * lebih mencolok daripada tabel yang seluruhnya polos.
     */
    protected function lebarkanTabel(Worksheet $lembar, int $tambahan): void
    {
        $lembar->insertNewRowBefore(static::BARIS_DST, $tambahan);

        $contoh = static::BARIS_TRANSAKSI + static::SLOT_BAWAAN - 1;

        for ($baris = static::BARIS_DST; $baris < static::BARIS_DST + $tambahan; $baris++) {
            $lembar->getRowDimension($baris)
                ->setRowHeight($lembar->getRowDimension($contoh)->getRowHeight());

            foreach (range('A', 'I') as $kolom) {
                $lembar->duplicateStyle($lembar->getStyle($kolom . $contoh), $kolom . $baris);
            }
        }
    }

    /**
     * Pemetikan tahun dari kolom tanggal.
     *
     * MySQL dan SQLite memakai fungsi yang berbeda, sedangkan lingkungan
     * sebenarnya MySQL (Instruksi §52) dan lingkungan pengembangan lokal
     * memakai SQLite, sehingga keduanya perlu dilayani.
     */
    protected function petikTahun(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', tanggal) AS INTEGER)"
            : 'YEAR(tanggal)';
    }
}
