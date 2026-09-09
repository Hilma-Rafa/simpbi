<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Models\BarangPersediaan;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Barang yang perlu perhatian gudang (Instruksi §36).
 *
 * Menyajikan dua daftar ringkas: barang dengan stok tersedia terendah, dan
 * barang yang dipantau namun telah mendekati/mencapai stok minimum. Nilai
 * stok tersedia diturunkan (stok_fisik - stok_hold), bukan kolom tersimpan.
 */
class BarangPerluPerhatian extends Widget
{
    protected string $view = 'filament.widgets.barang-perlu-perhatian';

    protected static ?int $sort = 4;

    // Setengah lebar pada kisi dua belas kolom dasbor (App\Filament\Pages\Dashboard)
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 6,
    ];

    protected const BATAS_TAMPIL = 5;

    public static function canView(): bool
    {
        return auth()->user()?->role === 'petugas_gudang';
    }

    /** Barang aktif dengan stok tersedia terendah. */
    public function getStokTerendahProperty(): Collection
    {
        return BarangPersediaan::query()
            ->where('status_aktif', true)
            ->selectRaw('*, (stok_fisik - stok_hold) as stok_tersedia')
            ->orderBy('stok_tersedia')
            ->orderBy('nama_barang')
            ->limit(self::BATAS_TAMPIL)
            ->get()
            ->map(fn ($b) => [
                'nama'     => $b->nama_barang,
                'tersedia' => max(0, (int) $b->stok_tersedia) . ' ' . $b->satuan,
            ]);
    }

    /** Barang yang dipantau (stok_minimum > 0) dan telah mencapai/di bawah minimum. */
    public function getMendekatiMinimumProperty(): Collection
    {
        return BarangPersediaan::query()
            ->where('status_aktif', true)
            ->where('stok_minimum', '>', 0)
            ->whereRaw('(stok_fisik - stok_hold) <= stok_minimum')
            ->selectRaw('*, (stok_fisik - stok_hold) as stok_tersedia')
            ->orderBy('stok_tersedia')
            ->limit(self::BATAS_TAMPIL)
            ->get()
            ->map(fn ($b) => [
                'nama'     => $b->nama_barang,
                'tersedia' => max(0, (int) $b->stok_tersedia) . ' ' . $b->satuan,
                'minimum'  => (int) $b->stok_minimum . ' ' . $b->satuan,
            ]);
    }

    public function getTautanSemuaProperty(): string
    {
        return BarangPersediaanResource::getUrl('index');
    }
}
