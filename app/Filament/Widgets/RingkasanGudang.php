<?php

namespace App\Filament\Widgets;

use App\Models\PermintaanBarang;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan pekerjaan gudang untuk Petugas Gudang (Instruksi §36).
 *
 * Empat ukuran mengikuti tahapan yang menjadi tanggung jawab gudang:
 * verifikasi ketersediaan, penyiapan, barang siap diambil, dan permintaan
 * bermasalah yang perlu ditindaklanjuti.
 */
class RingkasanGudang extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->role === 'petugas_gudang';
    }

    protected function getStats(): array
    {
        $perluVerifikasi = PermintaanBarang::query()->where('status', 'menunggu_verifikasi')->count();
        $perluDisiapkan  = PermintaanBarang::query()->where('status', 'siap_diproses')->count();
        $siapDiambil     = PermintaanBarang::query()->where('status', 'siap_diambil')->count();
        $bermasalah      = PermintaanBarang::query()->where('status', 'bermasalah')->count();

        return [
            Stat::make('Perlu Verifikasi', $perluVerifikasi)
                ->description($perluVerifikasi > 0 ? 'Ketersediaan perlu diperiksa' : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($perluVerifikasi > 0 ? 'warning' : 'gray'),

            Stat::make('Perlu Disiapkan', $perluDisiapkan)
                ->description($perluDisiapkan > 0 ? 'Barang perlu disiapkan' : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color($perluDisiapkan > 0 ? 'warning' : 'gray'),

            Stat::make('Siap Diambil', $siapDiambil)
                ->description($siapDiambil > 0 ? 'Menunggu pengambilan pemohon' : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($siapDiambil > 0 ? 'info' : 'gray'),

            Stat::make('Permintaan Bermasalah', $bermasalah)
                ->description($bermasalah > 0 ? 'Terdapat ketidaksesuaian' : 'Tidak ada masalah')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($bermasalah > 0 ? 'danger' : 'success'),
        ];
    }
}
