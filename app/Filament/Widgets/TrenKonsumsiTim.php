<?php

namespace App\Filament\Widgets;

use App\Models\Kategori;
use App\Models\MutasiStok;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tren Konsumsi Tim Saya — adaptasi TrenKonsumsiKategori bagi Ketua Tim dan
 * Tim, dibatasi pada barang keluar yang benar-benar menjadi milik tim
 * penggunanya.
 *
 * Sumber datanya tetap buku besar mutasi_stok yang sama dipakai Tren
 * Konsumsi dan Kartu Kendali, bukan jumlah yang diminta. Penautan ke tim
 * pemohon memakai `referensi_tabel`/`referensi_id` yang sudah ada — cara yang
 * sama dipakai panel Permintaan per Tim Kerja untuk besaran "barang
 * disalurkan" — bukan relasi baru.
 *
 * Panel ini tidak diberi judul bertaut. Kartu Kendali, tujuan tautan Tren
 * Konsumsi bagi Kasubbag, tertutup bagi Ketua Tim dan Tim (Instruksi §4), dan
 * tidak ada halaman lain yang menyajikan buku besar tersaring per tim.
 */
class TrenKonsumsiTim extends ChartWidget
{
    // Menempati posisi yang sama dengan TrenKonsumsiKategori.
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 8,
    ];

    protected function tahun(): int
    {
        return (int) now()->year;
    }

    /** Kategori terpilih; kosong berarti seluruh kategori persediaan. */
    public ?string $filter = 'semua';

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['ketua_tim', 'tim']);
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Tren Konsumsi Tim Saya';
    }

    public function getDescription(): ?string
    {
        return 'Barang keluar per bulan · ' . $this->tahun();
    }

    /** @return array<string, string> */
    protected function getFilters(): ?array
    {
        return ['semua' => 'Seluruh kategori'] + Kategori::query()
            ->where('tipe', 'persediaan')
            ->orderBy('nama_kategori')
            ->pluck('nama_kategori', 'id')
            ->mapWithKeys(fn (string $nama, int $id) => [(string) $id => $nama])
            ->all();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function namaKategori(): ?string
    {
        if ($this->filter === null || $this->filter === 'semua') {
            return null;
        }

        return Kategori::whereKey($this->filter)->value('nama_kategori');
    }

    /**
     * Jumlah barang keluar milik tim pengguna per bulan, satu kueri agregat.
     *
     * Ditautkan ke permintaan_barang lewat referensi_tabel/referensi_id,
     * persis cara panel Permintaan per Tim Kerja menghitung "barang
     * disalurkan" per tim.
     *
     * @return Collection<string,int> [Y-m-d => jumlah keluar]
     */
    protected function keluarPerHari(): Collection
    {
        $mulai      = Carbon::create($this->tahun(), 1, 1)->toDateString();
        $berikutnya = Carbon::create($this->tahun() + 1, 1, 1)->toDateString();
        $timId      = auth()->user()?->tim_id;

        return MutasiStok::query()
            ->join('permintaan_barang', function (JoinClause $join): void {
                $join->on('permintaan_barang.id', '=', 'mutasi_stok.referensi_id')
                    ->where('mutasi_stok.referensi_tabel', '=', 'permintaan_barang');
            })
            ->where('mutasi_stok.jenis', 'keluar')
            ->where('permintaan_barang.tim_pemohon_id', $timId)
            ->where('mutasi_stok.tanggal', '>=', $mulai)
            ->where('mutasi_stok.tanggal', '<', $berikutnya)
            ->when(
                $this->filter !== null && $this->filter !== 'semua',
                fn ($q) => $q->whereHas(
                    'barang',
                    fn ($b) => $b->where('kategori_id', $this->filter)
                )
            )
            ->selectRaw('DATE(mutasi_stok.tanggal) as hari, SUM(-mutasi_stok.jumlah) as jumlah')
            ->groupBy('hari')
            ->pluck('jumlah', 'hari')
            ->mapWithKeys(fn ($jumlah, $hari) => [
                Carbon::parse($hari)->toDateString() => (int) $jumlah,
            ]);
    }

    /** @return array{label: array<int,string>, nilai: array<int,int>} */
    public function getSeriProperty(): array
    {
        $perHari = $this->keluarPerHari();

        if ($perHari->isEmpty()) {
            return ['label' => [], 'nilai' => []];
        }

        $label = [];
        $nilai = [];

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
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
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
            ? 'Tren konsumsi tahun ' . $this->tahun() . ' akan muncul setelah tim Anda menerima barang dari permintaan.'
            : 'Belum ada barang kategori ini yang keluar untuk tim Anda sepanjang tahun ' . $this->tahun() . '.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-chart-bar-square';
    }
}
