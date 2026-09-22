<?php

namespace App\Filament\Resources\Tims\Widgets;

use App\Models\Tim;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Strip "kapan data Tim Kerja terakhir diperbarui", dibaca dari synced_at
 * yang sudah diisi ImporTimKerja (Instruksi bagian F).
 *
 * Sengaja diletakkan di luar app/Filament/Widgets — folder itu dipindai
 * AdminPanelProvider::discoverWidgets() dan otomatis ditawarkan sebagai
 * widget Dashboard. Widget ini bukan widget Dashboard; ia hanya dipakai
 * eksplisit oleh ListTims::getHeaderWidgets(), sehingga harus berada di
 * luar jalur pemindaian itu supaya tidak menambah apa pun ke Dashboard
 * peran mana pun.
 */
class StatusSinkronisasiTim extends Widget
{
    protected string $view = 'filament.partials.status-sinkronisasi';

    protected int|string|array $columnSpan = 'full';

    public ?Carbon $syncedAt = null;

    public function mount(): void
    {
        $terakhir = Tim::max('synced_at');

        $this->syncedAt = $terakhir ? Carbon::parse($terakhir) : null;
    }
}
