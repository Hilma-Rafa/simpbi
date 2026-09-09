<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Permintaan per tim kerja (Instruksi §35 dan §43).
 *
 * Batang horizontal yang membandingkan jumlah permintaan antar tim kerja.
 * Seluruh tim kerja aktif ditampilkan, termasuk yang belum mengajukan
 * permintaan pada periode terpilih, sebab konteks panel ini adalah
 * perbandingan antar unit — tim yang tidak mengajukan pun merupakan
 * informasi. Karena membandingkan antar tim, panjang batang di sini
 * diukur terhadap tim dengan permintaan terbanyak, berbeda dengan panel
 * Kondisi Stok yang mengukur terhadap stok kategorinya sendiri.
 */
class PermintaanPerTim extends Widget
{
    protected string $view = 'filament.widgets.permintaan-per-tim';

    protected static ?int $sort = 4;

    // Setengah lebar pada kisi dua belas kolom dasbor (App\Filament\Pages\Dashboard)
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 6,
    ];

    /** Periode pengamatan yang sedang dipilih (Instruksi §43). */
    public string $periode = '30';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'kasubbag';
    }

    /** @return array<string, string> */
    public function getPilihanPeriodeProperty(): array
    {
        return [
            '30'  => '30 hari terakhir',
            '90'  => '90 hari terakhir',
            'semua' => 'Seluruh periode',
        ];
    }

    public function getTimProperty(): Collection
    {
        $jumlah = PermintaanBarang::query()
            ->when(
                $this->batasWaktu(),
                fn ($q, $batas) => $q->where('created_at', '>=', $batas)
            )
            ->selectRaw('tim_pemohon_id, COUNT(*) as jumlah')
            ->groupBy('tim_pemohon_id')
            ->pluck('jumlah', 'tim_pemohon_id');

        $daftar = Tim::query()
            ->where('status_aktif', true)
            ->orderBy('nama_tim')
            ->get(['id', 'nama_tim'])
            ->map(fn (Tim $t) => [
                'id'     => $t->id,
                'nama'   => $t->nama_tim,
                'jumlah' => (int) ($jumlah[$t->id] ?? 0),
            ])
            ->sortByDesc('jumlah');

        // Pembanding adalah tim dengan permintaan terbanyak pada periode ini
        $tertinggi = max(1, (int) $daftar->max('jumlah'));

        return $daftar->map(fn (array $t) => $t + [
            'lebar'  => round($t['jumlah'] / $tertinggi * 100, 1),
            'tautan' => $this->tautanTim($t['id']),
        ])->values();
    }

    public function getTotalProperty(): int
    {
        return (int) $this->tim->sum('jumlah');
    }

    /** Batas awal periode, atau null apabila seluruh periode dipilih. */
    protected function batasWaktu(): ?\Illuminate\Support\Carbon
    {
        return match ($this->periode) {
            '30' => now()->subDays(30),
            '90' => now()->subDays(90),
            default => null,
        };
    }

    /** Tautan daftar permintaan dengan penyaring tim telah diterapkan. */
    protected function tautanTim(int $timId): string
    {
        return PermintaanBarangResource::getUrl('index', [
            'filters' => ['tim_pemohon_id' => ['value' => $timId]],
        ]);
    }

    public function getTautanSemuaProperty(): string
    {
        return PermintaanBarangResource::getUrl('index');
    }
}
