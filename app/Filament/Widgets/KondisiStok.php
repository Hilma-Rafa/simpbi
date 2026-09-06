<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Kondisi persediaan per kategori barang.
 *
 * Menyajikan hubungan antara stok fisik, stok yang sedang dikunci oleh
 * permintaan berjalan, dan stok yang dapat diminta. Panjang batang
 * merepresentasikan stok fisik, yang terbagi menjadi bagian tersedia dan
 * bagian terkunci, sebab keduanya merupakan bagian dari stok fisik.
 *
 * Rincian barang yang sedang dikunci ditampilkan hanya ketika penunjuk
 * diarahkan ke bagian terkunci, agar panel tetap ringkas.
 */
class KondisiStok extends Widget
{
    protected string $view = 'filament.widgets.kondisi-stok';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 1,
    ];

    /** Jumlah kategori yang ditampilkan pada panel. */
    protected const BATAS_TAMPIL = 5;

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['kasubbag', 'petugas_gudang']);
    }

    public function getKategoriProperty(): Collection
    {
        $ringkasan = Kategori::query()
            ->where('tipe', 'persediaan')
            ->withSum(
                ['barang as total_fisik' => fn ($q) => $q->where('status_aktif', true)],
                'stok_fisik'
            )
            ->withSum(
                ['barang as total_hold' => fn ($q) => $q->where('status_aktif', true)],
                'stok_hold'
            )
            ->get()
            ->filter(fn ($k) => (int) $k->total_fisik > 0 || (int) $k->total_hold > 0)
            // Kategori yang paling terdampak penguncian berada di urutan atas,
            // apabila tidak ada penguncian, diurutkan menurut stok fisik terbesar
            ->sortByDesc(fn ($k) => [(int) $k->total_hold, (int) $k->total_fisik])
            ->take(self::BATAS_TAMPIL);

        $skala = max(1, $ringkasan->max(fn ($k) => (int) $k->total_fisik) ?: 1);

        return $ringkasan->map(function (Kategori $k) use ($skala) {
            $fisik    = (int) $k->total_fisik;
            $terkunci = min((int) $k->total_hold, $fisik);
            $tersedia = max(0, $fisik - $terkunci);

            return [
                'id'            => $k->id,
                'nama'          => $k->nama_kategori,
                'fisik'         => $fisik,
                'terkunci'      => $terkunci,
                'tersedia'      => $tersedia,
                'lebarTersedia' => round($tersedia / $skala * 100, 1),
                'lebarTerkunci' => round($terkunci / $skala * 100, 1),
                'daftarHold'    => $terkunci > 0 ? $this->barangTerkunci($k->id) : collect(),
                'tautan'        => BarangPersediaanResource::getUrl('index', [
                    'tableFilters' => ['kategori_id' => ['value' => $k->id]],
                ]),
            ];
        })->values();
    }

    /** Daftar barang yang sedang dikunci pada suatu kategori. */
    protected function barangTerkunci(int $kategoriId): Collection
    {
        return BarangPersediaan::query()
            ->where('kategori_id', $kategoriId)
            ->where('status_aktif', true)
            ->where('stok_hold', '>', 0)
            ->orderByDesc('stok_hold')
            ->limit(6)
            ->get(['nama_barang', 'satuan', 'stok_hold'])
            ->map(fn ($b) => [
                'nama'   => $b->nama_barang,
                'jumlah' => $b->stok_hold . ' ' . $b->satuan,
            ]);
    }

    public function getTautanSemuaProperty(): string
    {
        return BarangPersediaanResource::getUrl('index');
    }
}