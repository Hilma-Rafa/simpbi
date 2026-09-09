<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use Filament\Widgets\ChartWidget;

/**
 * Status Permintaan Tim Saya (Instruksi §37).
 *
 * Batang jumlah permintaan tim pengguna menurut empat kelas status pada
 * Instruksi §43: Selesai, Berjalan, Ditolak, dan Kedaluwarsa. Panel ini
 * hanya ditujukan bagi akun Tim dan Ketua Tim, sebab merekalah yang perlu
 * memantau permintaan timnya sendiri; peran pengelola sudah memiliki panel
 * lintas tim tersendiri.
 *
 * Pembatasan data tidak ditulis ulang di sini, melainkan mengambil kembali
 * PermintaanBarangResource::getEloquentQuery() yang sudah membatasi Tim dan
 * Ketua Tim pada tim_pemohon_id miliknya (Instruksi §54), sehingga permintaan
 * tim lain tidak mungkin ikut terhitung.
 */
class StatusPermintaanTim extends ChartWidget
{
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

    /**
     * Penggabungan sebelas status alur permintaan menjadi kelas yang dibaca
     * pengguna. Empat kelas pertama mengikuti Instruksi §43; Bermasalah
     * dipisahkan sebagai batang tersendiri karena permintaan pada keadaan itu
     * bukan penolakan dan bukan pula berjalan normal, melainkan tertahan oleh
     * ketidaksesuaian yang tidak dapat diatasi sehingga perlu terlihat jelas
     * oleh tim pemohon.
     *
     * Seluruh status terpetakan, sehingga jumlah kelima batang selalu sama
     * dengan jumlah permintaan tim.
     */
    protected const KELAS = [
        'Selesai' => ['selesai'],
        'Berjalan' => [
            'menunggu_ketua', 'menunggu_verifikasi', 'menunggu_kasubbag',
            'siap_diproses', 'siap_diambil', 'menunggu_pengesahan',
        ],
        'Ditolak'     => ['ditolak_ketua', 'ditolak_kasubbag'],
        'Kedaluwarsa' => ['kedaluwarsa'],
        'Bermasalah'  => ['bermasalah'],
    ];

    /** Warna semantik mengikuti Instruksi §25. */
    protected const WARNA = [
        '#16A34A', // Selesai     — success
        '#1557A6', // Berjalan    — blue, aksi berjalan
        '#DC2626', // Ditolak     — danger
        '#94A3B8', // Kedaluwarsa — netral, berakhir tanpa keputusan
        '#F59E0B', // Bermasalah  — accent, menuntut perhatian
    ];

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['tim', 'ketua_tim']);
    }

    public function getHeading(): ?string
    {
        return 'Status Permintaan Tim Saya';
    }

    public function getDescription(): ?string
    {
        $total = array_sum($this->cacah());

        // Satu baris agar tinggi kepala panel sama dengan Pola Permintaan
        return $total === 0
            ? 'Belum ada permintaan'
            : 'Total ' . $total . ' permintaan tim';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Cacah permintaan tim per kelas status, satu kueri agregat.
     *
     * @return array<string,int> [kelas => jumlah]
     */
    protected function cacah(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $perStatus = PermintaanBarangResource::getEloquentQuery()
            // Relasi yang dimuat pada daftar tidak diperlukan untuk agregasi
            ->setEagerLoads([])
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $cacah = [];
        foreach (self::KELAS as $kelas => $status) {
            $cacah[$kelas] = (int) collect($status)
                ->sum(fn (string $s) => (int) ($perStatus[$s] ?? 0));
        }

        return $this->cache = $cacah;
    }

    protected function getData(): array
    {
        $cacah = $this->cacah();

        // Tanpa satu pun permintaan, bagan diganti keadaan kosong
        if (array_sum($cacah) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Jumlah permintaan',
                    'data'            => array_values($cacah),
                    'backgroundColor' => self::WARNA,
                    'borderRadius'    => 4,
                ],
            ],
            'labels' => array_keys($cacah),
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Chart.js memakai perbandingan 2:1 untuk batang, sehingga kanvasnya
            // lebih pendek daripada bagan donat yang memakai 1:1. Disamakan agar
            // tinggi kartunya sama dengan Pola Permintaan di sebelahnya.
            'aspectRatio' => 1,
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
                    'grid' => ['display' => false],
                    // Seluruh label kelas harus tampil; Chart.js dibiarkan
                    // memiringkannya bila kartu terlalu sempit
                    'ticks' => ['autoSkip' => false],
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
        return 'Sebaran status akan muncul setelah tim Anda mengajukan permintaan barang.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }
}
