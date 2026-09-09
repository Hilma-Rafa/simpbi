<?php

namespace App\Filament\Widgets;

use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\Tim;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan data induk untuk Admin Sistem (Instruksi §39).
 *
 * Admin tidak memiliki alur operasional permintaan, sehingga ukuran yang
 * ditampilkan bersifat informatif atas kelengkapan data induk, bukan
 * pekerjaan yang menunggu persetujuan.
 */
class RingkasanAdmin extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function getStats(): array
    {
        $penggunaAktif  = User::query()->where('status_aktif', true)->count();
        $timAktif       = Tim::query()->where('status_aktif', true)->count();
        $barang         = BarangPersediaan::query()->where('status_aktif', true)->count();
        $kategoriBarang = Kategori::query()->where('tipe', 'persediaan')->count();

        return [
            Stat::make('Pengguna Aktif', $penggunaAktif)
                ->description('Akun yang dapat masuk ke sistem')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Tim Kerja Aktif', $timAktif)
                ->description('Unit kerja yang terdaftar')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Barang Persediaan', $barang)
                ->description('Barang aktif pada master')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            Stat::make('Kategori Barang', $kategoriBarang)
                ->description('Kategori persediaan')
                ->descriptionIcon('heroicon-m-tag')
                ->color('primary'),
        ];
    }
}
