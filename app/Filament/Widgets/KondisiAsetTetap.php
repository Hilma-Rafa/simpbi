<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use App\Filament\Widgets\Concerns\JudulPanelBertaut;
use App\Models\AsetTetap;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Kondisi aset tetap yang dikelola.
 *
 * Bagan donat dipilih karena ketiga kondisi pada kolom aset_tetap.kondisi
 * bersifat saling lepas: satu aset tercatat pada tepat satu kondisi,
 * sehingga jumlah seluruh irisan sama dengan jumlah aset. Inilah syarat
 * yang membuat bagan lingkaran dapat dibaca sebagai porsi dari keseluruhan.
 *
 * Kondisi mengikuti enum yang sudah ada pada basis data, yaitu baik,
 * rusak_ringan, dan rusak_berat. Pencacahan dibatasi pada aset berstatus
 * aktif, mengikuti cara yang sama dipakai panel Ringkasan Admin Sistem.
 *
 * Hak akses menyalin AsetTetapResource::canAccess(), yaitu Admin Sistem dan
 * Kasubbag Umum. Petugas Gudang dan Ketua Tim tidak dapat membuka data induk
 * aset tetap, sehingga bagan ini pun tidak ditampilkan bagi mereka; tidak ada
 * aturan akses baru yang dibuat di sini.
 */
class KondisiAsetTetap extends ChartWidget
{
    use JudulPanelBertaut;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 4,
    ];

    protected ?string $maxHeight = '260px';

    /**
     * Hasil pencacahan disimpan per instance agar kueri tidak diulang ketika
     * judul, keterangan, dan data bagan dibaca pada permintaan yang sama.
     * Sengaja bukan variabel static di dalam metode, sebab nilainya akan
     * dipakai bersama oleh seluruh instance kelas pada satu proses PHP.
     *
     * @var array<string,int>|null
     */
    protected ?array $cache = null;

    /** Label yang dibaca pengguna untuk tiap nilai enum kondisi. */
    protected const KONDISI = [
        'baik'         => 'Baik',
        'rusak_ringan' => 'Rusak Ringan',
        'rusak_berat'  => 'Rusak Berat',
    ];

    /** Palet mengikuti warna semantik Instruksi §25. */
    protected const WARNA = [
        '#16A34A', // Baik         — success
        '#F59E0B', // Rusak Ringan — accent/peringatan
        '#DC2626', // Rusak Berat  — danger
    ];

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'kasubbag']);
    }

    /**
     * Judul sekaligus tautan ke daftar Aset Tetap. Hak akses daftar itu sama
     * dengan hak lihat panel ini, sehingga tautan tidak pernah mengarah ke
     * halaman yang tertutup bagi penggunanya.
     */
    public function getHeading(): string|Htmlable|null
    {
        return $this->judulBertaut(
            'Kondisi Aset Tetap',
            AsetTetapResource::getUrl('index'),
        );
    }

    public function getDescription(): ?string
    {
        $total = array_sum($this->cacah());

        // Ditulis satu baris agar tinggi kepala panel sama dengan Pola Permintaan
        return $total === 0
            ? 'Belum ada aset tetap'
            : 'Total ' . $total . ' aset tetap aktif';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * Cacah aset per kondisi dalam satu kueri agregat.
     *
     * @return array<string,int> [label kondisi => jumlah]
     */
    protected function cacah(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $hasil = AsetTetap::query()
            ->where('status_aktif', true)
            ->selectRaw('kondisi, COUNT(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi');

        // Ketiga kondisi selalu ditampilkan, termasuk yang belum ada asetnya,
        // agar susunan irisan dan warnanya tidak berubah-ubah antar periode
        $cacah = [];
        foreach (self::KONDISI as $nilai => $label) {
            $cacah[$label] = (int) ($hasil[$nilai] ?? 0);
        }

        return $this->cache = $cacah;
    }

    protected function getData(): array
    {
        $cacah = $this->cacah();

        // Tanpa satu pun aset, bagan diganti keadaan kosong
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
            // Tinggi kanvas datang dari CSS (--simpbi-tinggi-bagan), bukan dari
            // perbandingan sisi, supaya bagan ini berakhir pada garis yang sama
            // dengan bagan pasangannya yang kolomnya lebih lebar.
            'maintainAspectRatio' => false,
            // Bagian tengah dikosongkan sehingga berbentuk donat
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
        return 'Kondisi aset akan muncul setelah data aset tetap tercatat pada sistem.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-chart-pie';
    }
}
