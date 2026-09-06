<?php

namespace App\Filament\Widgets;

use App\Models\BarangPersediaan;
use App\Models\PermintaanBarang;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan monitoring untuk Kasubbag Umum.
 *
 * Menyajikan empat ukuran yang paling membutuhkan perhatian, yaitu
 * permintaan yang menunggu tindakan Kasubbag, permintaan yang sedang
 * berjalan, serta kondisi persediaan yang menipis dan habis.
 */
class RingkasanKasubbag extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->role === 'kasubbag';
    }

    protected function getStats(): array
    {
        // Permintaan yang menunggu keputusan persetujuan Kasubbag Umum
        $menungguPersetujuan = PermintaanBarang::query()
            ->where('status', 'menunggu_kasubbag')
            ->count();

        // Permintaan yang telah diterima pemohon dan menunggu pengesahan akhir
        $menungguPengesahan = PermintaanBarang::query()
            ->where('status', 'menunggu_pengesahan')
            ->count();

        // Barang yang tidak dapat diminta karena stok tersedia habis
        $tidakTersedia = BarangPersediaan::query()
            ->where('status_aktif', true)
            ->whereRaw('(stok_fisik - stok_hold) <= 0')
            ->count();

        // Barang yang stok tersedianya berada pada atau di bawah stok minimum,
        // dihitung hanya untuk barang yang dipantau (stok_minimum lebih dari nol)
        $stokKritis = BarangPersediaan::query()
            ->where('status_aktif', true)
            ->where('stok_minimum', '>', 0)
            ->whereRaw('(stok_fisik - stok_hold) > 0')
            ->whereRaw('(stok_fisik - stok_hold) <= stok_minimum')
            ->count();

        return [
            Stat::make('Perlu Persetujuan', $menungguPersetujuan)
                ->description($menungguPersetujuan > 0
                    ? 'Permintaan perlu keputusan Anda'
                    : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-clock')
                ->color($menungguPersetujuan > 0 ? 'warning' : 'gray'),

            Stat::make('Perlu Pengesahan', $menungguPengesahan)
                ->description($menungguPengesahan > 0
                    ? 'Penerimaan perlu disahkan'
                    : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($menungguPengesahan > 0 ? 'warning' : 'gray'),

            Stat::make('Stok Kritis', $stokKritis)
                ->description($stokKritis > 0
                    ? 'Barang mendekati atau di bawah minimum'
                    : 'Stok tersedia dalam batas aman')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stokKritis > 0 ? 'warning' : 'success'),

            Stat::make('Barang Tidak Tersedia', $tidakTersedia)
                ->description($tidakTersedia > 0
                    ? 'Tidak dapat diminta saat ini'
                    : 'Seluruh barang tersedia')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($tidakTersedia > 0 ? 'danger' : 'success'),
        ];
    }
}