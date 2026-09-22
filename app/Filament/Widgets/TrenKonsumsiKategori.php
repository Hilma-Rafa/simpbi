<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\KartuKendali;
use App\Filament\Widgets\Concerns\JudulPanelBertaut;
use App\Models\Kategori;
use App\Models\MutasiStok;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tren konsumsi barang persediaan, dapat disaring per kategori.
 *
 * Menjawab visualisasi "tren konsumsi per kategori" pada UC-22, keluaran
 * sistem "Rekap stok barang per-kategori dan periode tertentu", serta butir
 * monitoring stok "perubahan stok dari waktu ke waktu" pada revisi sempro.
 *
 * Yang dihitung adalah barang yang **benar-benar keluar**, dibaca dari buku
 * besar mutasi stok. Bukan dari jumlah yang diminta: permintaan yang ditolak
 * atau kedaluwarsa tidak pernah mengurangi persediaan, sehingga
 * memasukkannya akan menggambarkan konsumsi yang tidak pernah terjadi.
 * Sumber yang sama dipakai Kartu Kendali, sehingga angka pada panel ini dan
 * pada kartu tidak mungkin berselisih.
 *
 * Panel ini **bukan** pengganti Pola Permintaan. Keduanya mengukur hal yang
 * berbeda: Pola Permintaan mencacah banyaknya transaksi permintaan (butir
 * monitoring permintaan barang), sedangkan panel ini menjumlahkan barang yang
 * dikonsumsi (butir monitoring stok dan distribusi). Sepuluh permintaan kecil
 * dan satu permintaan besar tampak sangat berbeda pada kedua panel, dan
 * perbedaan itulah yang berguna.
 */
class TrenKonsumsiKategori extends ChartWidget
{
    use JudulPanelBertaut;

    /** Berpasangan dengan Barang Paling Sering Diminta pada baris analisis. */
    protected static ?int $sort = 8;

    /**
     * Tujuh dari dua belas kolom. Lebih lebar daripada pasangannya karena
     * penyaring kategori dirakit Filament sebaris dengan judul panel, dan
     * nama kategori persediaan cukup panjang sehingga kotak pilihannya
     * memakan ruang yang tidak dimiliki panel selebar setengah kisi.
     */
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 7,
    ];

    /**
     * Periodenya satu tahun kalender penuh, Januari sampai Desember.
     *
     * Bukan dua belas bulan berjalan. Kartu Kendali menerbitkan kartunya per
     * tahun kalender, dan monitoring persediaan yang memakai sumber data yang
     * sama sebaiknya tidak menawarkan bentuk periode yang berbeda — pembacanya
     * akan membandingkan angka pada keduanya. Rentang berjalan juga membuat
     * sumbu bagan berpindah setiap bulan dan mencampur dua tahun dalam satu
     * gambar, sehingga jumlah setahun tidak pernah dapat dibaca langsung.
     *
     * Tahunnya mengikuti tahun berjalan, sama dengan tahun bawaan halaman
     * Kartu Kendali. Tidak ada penyaring tahun di sini: penyaring tunggal yang
     * disediakan bagan Filament sudah dipakai untuk kategori, dan kategori
     * itulah dimensi yang membuat panel ini disebut "per kategori".
     */
    protected function tahun(): int
    {
        return (int) now()->year;
    }

    /** Kategori terpilih; kosong berarti seluruh kategori persediaan. */
    public ?string $filter = 'semua';

    /**
     * Kasubbag Umum saja, mengikuti UC-22 yang menetapkannya sebagai aktor
     * analisis pola permintaan dan tren konsumsi untuk perencanaan pengadaan.
     */
    public static function canView(): bool
    {
        return auth()->user()?->role === 'kasubbag';
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->judulBertaut('Tren Konsumsi', KartuKendali::getUrl());
    }

    /**
     * Ditulis sependek mungkin dan tanpa menyebut kategori terpilih.
     * Penyaring kategori dirakit Filament tepat di sebelah kepala panel ini,
     * sehingga mengulang namanya di sini hanya memakan baris — dan pada panel
     * selebar setengah kisi, baris itulah yang mendesak judulnya sampai patah.
     */
    public function getDescription(): ?string
    {
        // Tahunnya ikut disebut karena label sumbu hanya memuat nama bulan;
        // tanpa itu tidak ada satu pun tempat pada panel yang menyatakan
        // periode mana yang sedang digambarkan.
        return 'Barang keluar per bulan · ' . $this->tahun();
    }

    /**
     * Pilihan kategori, dibaca dari kategori persediaan yang benar-benar ada.
     *
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return ['semua' => 'Seluruh kategori'] + Kategori::query()
            ->where('tipe', 'persediaan')
            ->orderBy('nama_kategori')
            ->pluck('nama_kategori', 'id')
            ->mapWithKeys(fn (string $nama, int $id) => [(string) $id => $nama])
            ->all();
    }

    /**
     * Batang, bukan garis.
     *
     * Konsumsi adalah jumlah yang terkumpul sepanjang satu bulan, bukan nilai
     * yang berjalan menerus. Garis akan menyiratkan bahwa di antara dua bulan
     * terdapat nilai antara yang dapat dibaca, padahal tidak ada.
     */
    protected function getType(): string
    {
        return 'bar';
    }

    /** Nama kategori yang sedang dipilih, atau null bila seluruh kategori. */
    protected function namaKategori(): ?string
    {
        if ($this->filter === null || $this->filter === 'semua') {
            return null;
        }

        return Kategori::whereKey($this->filter)->value('nama_kategori');
    }

    /**
     * Jumlah barang keluar per bulan, satu kueri agregat.
     *
     * Pengelompokan dilakukan pada tingkat tanggal karena fungsi DATE()
     * tersedia baik pada MySQL maupun SQLite; penggabungan menjadi bulan
     * dilakukan di dalam PHP atas hasil yang sudah ringkas, mengikuti cara
     * yang sama dipakai panel Pola Permintaan agar keduanya tidak bergantung
     * pada fungsi tanggal khas satu basis data.
     *
     * Jumlah pada baris keluar bernilai negatif, sehingga tandanya dibalik
     * agar yang tersaji adalah banyaknya barang yang keluar.
     *
     * @return Collection<string,int> [Y-m-d => jumlah keluar]
     */
    protected function keluarPerHari(): Collection
    {
        /*
         * Batas periode ditulis sebagai selang setengah terbuka memakai tanggal
         * murni, persis cara Kartu Kendali membatasi tahunnya. Sebagian baris
         * buku besar tersimpan berikut jamnya, dan pada SQLite keduanya
         * dibandingkan sebagai teks — dengan batas atas berupa 1 Januari tahun
         * berikutnya, transaksi 31 Desember yang berimbuhan jam tetap masuk ke
         * tahunnya sendiri alih-alih terbuang. Cara ini juga menjaga indeks
         * (barang_id, tanggal) tetap terpakai.
         */
        $mulai      = Carbon::create($this->tahun(), 1, 1)->toDateString();
        $berikutnya = Carbon::create($this->tahun() + 1, 1, 1)->toDateString();

        return MutasiStok::query()
            ->where('mutasi_stok.jenis', 'keluar')
            ->where('mutasi_stok.tanggal', '>=', $mulai)
            ->where('mutasi_stok.tanggal', '<', $berikutnya)
            ->when(
                $this->filter !== null && $this->filter !== 'semua',
                fn ($q) => $q->whereHas(
                    'barang',
                    fn ($b) => $b->where('kategori_id', $this->filter)
                )
            )
            ->selectRaw('DATE(tanggal) as hari, SUM(-jumlah) as jumlah')
            ->groupBy('hari')
            ->pluck('jumlah', 'hari')
            ->mapWithKeys(fn ($jumlah, $hari) => [
                Carbon::parse($hari)->toDateString() => (int) $jumlah,
            ]);
    }

    /**
     * Deret bulanan yang digambarkan bagan ini.
     *
     * Dijadikan properti terhitung publik seperti pada panel Permintaan per
     * Tim Kerja, sehingga angkanya dapat dibaca dan diuji tanpa melewati
     * perakitan bagan — yang diperiksa memang besarannya, bukan bentuk
     * keluaran Chart.js-nya.
     *
     * @return array{label: array<int,string>, nilai: array<int,int>}
     */
    public function getSeriProperty(): array
    {
        $perHari = $this->keluarPerHari();

        // Tanpa satu pun pengeluaran, deretan batang nol tidak menjelaskan
        // apa pun; keadaan kosong lebih terbaca dan lebih jujur.
        if ($perHari->isEmpty()) {
            return ['label' => [], 'nilai' => []];
        }

        $label = [];
        $nilai = [];

        /*
         * Dua belas bulan selalu digambar, dari Januari sampai Desember, juga
         * bulan yang belum atau tidak punya transaksi. Bulan bernilai nol
         * merupakan keterangan tersendiri — ia menyatakan tidak ada konsumsi,
         * bukan bahwa datanya hilang — dan menghilangkannya membuat jarak
         * antar batang tidak lagi sebanding dengan jarak waktunya.
         *
         * Nama bulan ditulis tanpa tahun, sebab seluruh batang berada pada
         * tahun yang sama dan tahunnya sudah disebut pada keterangan panel.
         */
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $awal   = Carbon::create($this->tahun(), $bulan, 1);
            $awalan = $awal->format('Y-m');

            $label[] = $awal->translatedFormat('M');
            $nilai[] = (int) $perHari
                ->filter(fn ($jumlah, $hari) => str_starts_with($hari, $awalan))
                ->sum();
        }

        return ['label' => $label, 'nilai' => $nilai];
    }

    protected function getData(): array
    {
        $seri = $this->seri;

        if ($seri['nilai'] === []) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label'        => 'Barang keluar',
                    'data'         => $seri['nilai'],
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $seri['label'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Tinggi kanvas datang dari CSS (--simpbi-tinggi-bagan), sama
            // dengan bagan dasbor lainnya.
            'maintainAspectRatio' => false,
            'plugins' => [
                // Satu seri saja, sehingga legenda hanya mengulang judul panel.
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    // Banyaknya barang selalu bilangan bulat
                    'ticks'       => ['precision' => 0],
                ],
                'x' => [
                    'grid'  => ['display' => false],
                    'ticks' => ['maxRotation' => 0, 'autoSkip' => true, 'maxTicksLimit' => 12],
                ],
            ],
        ];
    }

    public function getEmptyStateHeading(): string
    {
        return 'Belum ada barang keluar';
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->namaKategori() === null
            ? 'Tren konsumsi tahun ' . $this->tahun() . ' akan muncul setelah ada permintaan barang yang diterima pemohon.'
            : 'Belum ada barang kategori ini yang keluar sepanjang tahun ' . $this->tahun() . '.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-chart-bar-square';
    }
}
