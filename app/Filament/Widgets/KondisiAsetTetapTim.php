<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AsetTetapTimSaya;
use App\Filament\Widgets\Concerns\JudulPanelBertaut;
use App\Models\AsetTetap;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Kondisi Aset Tetap Tim Saya — adaptasi KondisiAsetTetap bagi Ketua Tim
 * dan Tim, yang tidak berwenang atas data induk aset tetap dan karena itu
 * tidak melihat panel Kondisi Aset Tetap lintas kantor.
 *
 * Bagan, warna, dan cara pencacahannya sama persis; satu-satunya beda adalah
 * cakupan datanya dibatasi pada aset yang sedang ditempatkan pada tim
 * penggunanya, memakai kolom penempatan yang sama dipakai AsetTetapResource
 * — bukan mekanisme baru.
 */
class KondisiAsetTetapTim extends ChartWidget
{
    use JudulPanelBertaut;

    // Menempati posisi yang sama dengan KondisiAsetTetap; keduanya saling
    // lepas menurut peran sehingga tidak pernah tampil bersamaan.
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 4,
    ];

    protected ?string $maxHeight = '260px';

    /** @var array<string,int>|null */
    protected ?array $cache = null;

    protected const KONDISI = [
        'baik'         => 'Baik',
        'rusak_ringan' => 'Rusak Ringan',
        'rusak_berat'  => 'Rusak Berat',
    ];

    protected const WARNA = [
        '#16A34A', // Baik         — success
        '#F59E0B', // Rusak Ringan — accent/peringatan
        '#DC2626', // Rusak Berat  — danger
    ];

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['ketua_tim', 'tim']);
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->judulBertaut(
            'Kondisi Aset Tetap Tim Saya',
            AsetTetapTimSaya::getUrl(),
        );
    }

    public function getDescription(): ?string
    {
        $total = array_sum($this->cacah());

        return $total === 0
            ? 'Belum ada aset tetap'
            : 'Total ' . $total . ' aset tetap aktif';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string,int> */
    protected function cacah(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $hasil = AsetTetap::query()
            ->where('status_aktif', true)
            // Pengguna tanpa tim tidak melihat aset apa pun (bukan aset yang belum ditempatkan).
            ->when(
                auth()->user()?->tim_id,
                fn ($query, $timId) => $query->where('tim_penempatan_id', $timId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->selectRaw('kondisi, COUNT(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi');

        $cacah = [];
        foreach (self::KONDISI as $nilai => $label) {
            $cacah[$label] = (int) ($hasil[$nilai] ?? 0);
        }

        return $this->cache = $cacah;
    }

    protected function getData(): array
    {
        $cacah = $this->cacah();

        if (array_sum($cacah) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Jumlah aset',
                    'data'            => array_values($cacah),
                    'backgroundColor' => self::WARNA,
                    'borderWidth'     => 0,
                ],
            ],
            'labels' => array_keys($cacah),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout'  => '68%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels'   => ['boxWidth' => 10, 'usePointStyle' => true],
                ],
            ],
        ];
    }

    public function getEmptyStateHeading(): string
    {
        return 'Belum ada aset tetap';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'Kondisi aset akan muncul setelah tim Anda memiliki aset tetap yang ditempatkan.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-chart-pie';
    }
}
