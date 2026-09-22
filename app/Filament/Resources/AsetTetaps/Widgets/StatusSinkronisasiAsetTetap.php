<?php

namespace App\Filament\Resources\AsetTetaps\Widgets;

use App\Models\AsetTetap;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Strip "kapan data Aset Tetap terakhir diperbarui", dibaca dari synced_at
 * yang sudah diisi ImporAsetTetap (Instruksi bagian F).
 *
 * Aset yang dicatat manual tidak pernah mengisi synced_at, sehingga
 * MAX(synced_at) secara alami hanya mencerminkan aset yang memang berasal
 * dari sinkronisasi — tidak perlu penyaring sumber_data tambahan.
 *
 * Diletakkan di luar app/Filament/Widgets agar tidak ikut terpindai sebagai
 * widget Dashboard; lihat catatan yang sama pada StatusSinkronisasiTim.
 */
class StatusSinkronisasiAsetTetap extends Widget
{
    protected string $view = 'filament.partials.status-sinkronisasi';

    protected int|string|array $columnSpan = 'full';

    public ?Carbon $syncedAt = null;

    public function mount(): void
    {
        $terakhir = AsetTetap::max('synced_at');

        $this->syncedAt = $terakhir ? Carbon::parse($terakhir) : null;
    }
}
