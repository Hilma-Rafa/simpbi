<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Filament\Widgets\Concerns\JudulPanelBertaut;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pola permintaan barang dari waktu ke waktu (Instruksi §35 dan §43).
 *
 * Menampilkan banyaknya permintaan yang diajukan pada tiap satuan waktu.
 * Pilihan periode disamakan dengan panel Permintaan per Tim Kerja, yaitu
 * 30 hari, 90 hari, dan seluruh periode. Satuan pengelompokan menyesuaikan
 * panjang periode agar jumlah titik tetap terbaca: harian untuk 30 hari,
 * mingguan untuk 90 hari, dan bulanan untuk seluruh periode.
 *
 * Pembatasan data mengikuti cakupan yang sudah berlaku pada daftar
 * Permintaan Barang, sehingga Tim dan Ketua Tim hanya melihat permintaan
 * timnya sendiri, sedangkan Admin Sistem, Petugas Gudang, dan Kasubbag Umum
 * melihat seluruh permintaan. Tidak ada aturan akses baru yang dibuat.
 */
class PolaPermintaan extends ChartWidget
{
    use JudulPanelBertaut;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 8,
    ];

    /**
     * Delapan kolom ketika berpasangan dengan bagan selebar empat kolom di
     * sebelahnya, yaitu Kondisi Aset Tetap bagi Admin dan Kasubbag, atau
     * Status Permintaan Tim Saya bagi Tim dan Ketua Tim. Bagi peran yang
     * tidak memiliki keduanya, yakni Petugas Gudang, bagan melebar penuh
     * agar barisnya tidak menyisakan ruang kosong.
     */
    public function getColumnSpan(): int|string|array
    {
        return (KondisiAsetTetap::canView() || StatusPermintaanTim::canView())
            ? $this->columnSpan
            : 'full';
    }

    protected ?string $maxHeight = '260px';

    /** Periode terpilih; nilainya sama dengan panel Permintaan per Tim Kerja. */
    public ?string $filter = '30';

    public static function canView(): bool
    {
        return in_array(
            auth()->user()?->role,
            ['admin', 'kasubbag', 'petugas_gudang', 'ketua_tim', 'tim']
        );
    }

    /**
     * Judul sekaligus tautan ke daftar Permintaan Barang, sehingga pengguna
     * dapat berpindah dari pola yang terlihat menuju permintaan yang
     * membentuknya. Daftar tidak disaring, sebab bagan ini menampilkan
     * seluruh periode terpilih, bukan satu titik tertentu.
     *
     * Judulnya menyebut "Tim Saya" bagi Tim dan Ketua Tim, sebab bagi
     * keduanya daftar yang dituju memang sudah tersaring pada tim mereka
     * sendiri (lihat cacahPerHari()); Admin, Kasubbag, dan Petugas Gudang
     * tetap membaca judul lintas kantor karena begitu pula cakupan datanya.
     */
    public function getHeading(): string|Htmlable|null
    {
        $judul = in_array(auth()->user()?->role, ['ketua_tim', 'tim'])
            ? 'Pola Permintaan Tim Saya'
            : 'Pola Permintaan';

        return $this->judulBertaut(
            $judul,
            PermintaanBarangResource::getUrl('index'),
        );
    }

    public function getDescription(): ?string
    {
        return match ($this->filter) {
            '90'    => 'Jumlah permintaan per minggu',
            'semua' => 'Jumlah permintaan per bulan',
            default => 'Jumlah permintaan per hari',
        };
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            '30'    => '30 hari terakhir',
            '90'    => '90 hari terakhir',
            'semua' => 'Seluruh periode',
        ];
    }

    /**
     * Cacah permintaan per tanggal, satu kueri agregat.
     *
     * Pengelompokan dilakukan pada tingkat tanggal karena fungsi DATE()
     * tersedia baik pada MySQL maupun SQLite; penggabungan menjadi minggu
     * atau bulan dilakukan di dalam PHP atas hasil yang sudah ringkas,
     * sehingga tidak bergantung pada fungsi tanggal khas satu basis data.
     *
     * @return Collection<string,int> [Y-m-d => jumlah]
     */
    protected function cacahPerHari(): Collection
    {
        $query = PermintaanBarangResource::getEloquentQuery()
            // Relasi yang dimuat pada daftar tidak diperlukan untuk agregasi
            ->setEagerLoads([]);

        if ($batas = $this->batasWaktu()) {
            $query->where('created_at', '>=', $batas);
        }

        return $query
            ->selectRaw('DATE(created_at) as hari, COUNT(*) as jumlah')
            ->groupBy('hari')
            ->orderBy('hari')
            ->pluck('jumlah', 'hari')
            ->mapWithKeys(fn ($jumlah, $hari) => [
                Carbon::parse($hari)->toDateString() => (int) $jumlah,
            ]);
    }

    /** Batas awal periode, atau null apabila seluruh periode dipilih. */
    protected function batasWaktu(): ?Carbon
    {
        return match ($this->filter) {
            '90'    => now()->subDays(89)->startOfDay(),
            'semua' => null,
            default => now()->subDays(29)->startOfDay(),
        };
    }

    protected function getData(): array
    {
        $perHari = $this->cacahPerHari();

        // Tanpa satu pun permintaan pada periode terpilih, garis nol tidak
        // menjelaskan apa pun, sehingga keadaan kosong lebih terbaca.
        if ($perHari->isEmpty()) {
            return [];
        }

        [$label, $nilai] = match ($this->filter) {
            '90'    => $this->kelompokMingguan($perHari),
            'semua' => $this->kelompokBulanan($perHari),
            default => $this->kelompokHarian($perHari),
        };

        return [
            'datasets' => [
                [
                    'label'       => 'Permintaan',
                    'data'        => $nilai,
                    'fill'        => true,
                    'tension'     => 0.3,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $label,
        ];
    }

    /** Tiga puluh titik harian, termasuk hari tanpa permintaan. */
    protected function kelompokHarian(Collection $perHari): array
    {
        $label = [];
        $nilai = [];

        for ($hari = now()->subDays(29)->startOfDay(); $hari <= now(); $hari->addDay()) {
            $label[] = $hari->translatedFormat('j M');
            $nilai[] = $perHari->get($hari->toDateString(), 0);
        }

        return [$label, $nilai];
    }

    /** Tiga belas titik mingguan, ditandai tanggal awal pekan. */
    protected function kelompokMingguan(Collection $perHari): array
    {
        $label = [];
        $nilai = [];

        $mulai = now()->subDays(89)->startOfWeek();

        for ($pekan = $mulai->copy(); $pekan <= now(); $pekan->addWeek()) {
            $akhir = $pekan->copy()->endOfWeek();

            $label[] = $pekan->translatedFormat('j M');
            $nilai[] = $perHari
                ->filter(fn ($jumlah, $hari) => $hari >= $pekan->toDateString()
                    && $hari <= $akhir->toDateString())
                ->sum();
        }

        return [$label, $nilai];
    }

    /** Titik bulanan sejak permintaan pertama tercatat. */
    protected function kelompokBulanan(Collection $perHari): array
    {
        $label = [];
        $nilai = [];

        $mulai = Carbon::parse($perHari->keys()->first())->startOfMonth();

        for ($bulan = $mulai->copy(); $bulan <= now(); $bulan->addMonth()) {
            $awalan = $bulan->format('Y-m');

            $label[] = $bulan->translatedFormat('M Y');
            $nilai[] = $perHari
                ->filter(fn ($jumlah, $hari) => str_starts_with($hari, $awalan))
                ->sum();
        }

        return [$label, $nilai];
    }

    protected function getOptions(): array
    {
        return [
            // Tinggi kanvas datang dari CSS (--simpbi-tinggi-bagan), bukan dari
            // perbandingan sisi, supaya bagan ini berakhir pada garis yang sama
            // dengan bagan pasangannya yang kolomnya lebih lebar.
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    // Jumlah permintaan selalu bilangan bulat
                    'ticks'       => ['precision' => 0],
                ],
                'x' => [
                    'ticks' => ['maxRotation' => 0, 'autoSkip' => true, 'maxTicksLimit' => 12],
                ],
            ],
        ];
    }

    public function getEmptyStateHeading(): string
    {
        return 'Belum ada permintaan';
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->filter === 'semua'
            ? 'Pola permintaan akan muncul setelah ada permintaan barang yang diajukan.'
            : 'Tidak ada permintaan barang yang diajukan pada periode terpilih.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-presentation-chart-line';
    }
}
