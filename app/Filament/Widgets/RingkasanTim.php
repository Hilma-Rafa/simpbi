<?php

namespace App\Filament\Widgets;

use App\Models\PermintaanBarang;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan permintaan untuk akun Tim (Instruksi §38).
 *
 * Dibatasi pada tim milik pengguna (tim_id). Jangan menampilkan banyak
 * grafik jika transaksi pengguna sedikit — cukup empat ukuran ringkas.
 */
class RingkasanTim extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected const STATUS_BERJALAN = [
        'menunggu_ketua', 'menunggu_verifikasi', 'menunggu_kasubbag',
        'siap_diproses', 'menunggu_pengesahan',
    ];

    public static function canView(): bool
    {
        return auth()->user()?->role === 'tim';
    }

    protected function getStats(): array
    {
        $timId = auth()->user()?->tim_id;

        $base = fn () => PermintaanBarang::query()->where('tim_pemohon_id', $timId);

        $menunggu   = (clone $base())->where('status', 'menunggu_ketua')->count();
        $berjalan   = (clone $base())->whereIn('status', self::STATUS_BERJALAN)->count();
        $siapDiambil = (clone $base())->where('status', 'siap_diambil')->count();
        $selesai    = (clone $base())->where('status', 'selesai')->count();

        return [
            Stat::make('Menunggu Persetujuan', $menunggu)
                ->description($menunggu > 0 ? 'Menunggu keputusan Ketua Tim' : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-clock')
                ->color($menunggu > 0 ? 'warning' : 'gray'),

            Stat::make('Sedang Diproses', $berjalan)
                ->description('Permintaan sedang berjalan')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($berjalan > 0 ? 'info' : 'gray'),

            Stat::make('Siap Diambil', $siapDiambil)
                ->description($siapDiambil > 0 ? 'Barang dapat diambil di gudang' : 'Tidak ada yang siap')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($siapDiambil > 0 ? 'success' : 'gray'),

            Stat::make('Selesai', $selesai)
                ->description('Permintaan telah selesai')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
