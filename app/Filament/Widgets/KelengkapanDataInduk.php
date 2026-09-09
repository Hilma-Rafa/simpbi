<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Resources\Tims\TimResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\BarangPersediaan;
use App\Models\Tim;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Panel kelengkapan data induk untuk Admin Sistem (Instruksi §39).
 *
 * Menggantikan panel "Perlu Tindakan" (yang bersifat approval) dengan daftar
 * kekurangan data induk yang dapat dilengkapi Admin. Istilah mengikuti §39:
 * "belum ditetapkan stok minimum", bukan "wajib dimonitor".
 */
class KelengkapanDataInduk extends Widget
{
    protected string $view = 'filament.widgets.kelengkapan-data-induk';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function getPemeriksaanProperty(): Collection
    {
        // Pengguna dengan peran berbasis tim namun belum terhubung ke tim.
        $penggunaTanpaTim = User::query()
            ->whereIn('role', ['tim', 'ketua_tim'])
            ->whereNull('tim_id')
            ->count();

        // Barang aktif yang belum ditetapkan stok minimumnya (stok_minimum = 0).
        $barangTanpaMinimum = BarangPersediaan::query()
            ->where('status_aktif', true)
            ->where('stok_minimum', 0)
            ->count();

        // Tim aktif yang belum memiliki Ketua Tim.
        $timTanpaKetua = Tim::query()
            ->where('status_aktif', true)
            ->whereNull('ketua_tim_id')
            ->count();

        return collect([
            [
                'jumlah'  => $penggunaTanpaTim,
                'label'   => 'pengguna belum terhubung dengan tim',
                'tautan'  => UserResource::getUrl('index'),
            ],
            [
                'jumlah'  => $barangTanpaMinimum,
                'label'   => 'barang belum ditetapkan stok minimum',
                'tautan'  => BarangPersediaanResource::getUrl('index'),
            ],
            [
                'jumlah'  => $timTanpaKetua,
                'label'   => 'tim belum memiliki Ketua Tim',
                'tautan'  => TimResource::getUrl('index'),
            ],
        ])->filter(fn ($item) => $item['jumlah'] > 0)->values();
    }
}
