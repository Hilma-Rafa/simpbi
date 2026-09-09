<?php

namespace App\Filament\Widgets;

use App\Models\PermintaanBarang;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan permintaan tim untuk Ketua Tim (Instruksi §37).
 *
 * Seluruh angka dibatasi pada tim milik pengguna (tim_id), sesuai prinsip
 * scope per peran pada Instruksi §54.
 */
class RingkasanKetua extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Status yang dianggap sedang berjalan (belum selesai/ditolak/berakhir). */
    protected const STATUS_BERJALAN = [
        'menunggu_verifikasi', 'menunggu_kasubbag',
        'siap_diproses', 'siap_diambil', 'menunggu_pengesahan',
    ];

    public static function canView(): bool
    {
        return auth()->user()?->role === 'ketua_tim';
    }

    protected function getStats(): array
    {
        $timId = auth()->user()?->tim_id;

        $base = fn () => PermintaanBarang::query()->where('tim_pemohon_id', $timId);

        $menunggu = (clone $base())->where('status', 'menunggu_ketua')->count();
        $berjalan = (clone $base())->whereIn('status', self::STATUS_BERJALAN)->count();
        $selesai  = (clone $base())->where('status', 'selesai')->count();

        return [
            Stat::make('Menunggu Persetujuan', $menunggu)
                ->description($menunggu > 0 ? 'Permintaan perlu keputusan Anda' : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-clock')
                ->color($menunggu > 0 ? 'warning' : 'gray'),

            Stat::make('Sedang Diproses', $berjalan)
                ->description('Permintaan tim sedang berjalan')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($berjalan > 0 ? 'info' : 'gray'),

            Stat::make('Selesai', $selesai)
                ->description('Permintaan tim telah selesai')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
