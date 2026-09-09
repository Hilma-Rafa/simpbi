<?php

namespace App\Filament\Resources\PermintaanBarangs\Pages;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\IconPosition;

/**
 * Halaman rincian satu permintaan barang (Instruksi §41).
 *
 * Menggantikan tombol "Detail" pada setiap baris tabel: baris permintaan kini
 * dapat diklik dan mengarah ke halaman ini. Isinya memakai kembali partial
 * filament.partials.detail-permintaan yang sebelumnya ditampilkan di dalam
 * dialog, sehingga informasi yang disajikan tidak berubah — status, informasi
 * permintaan, daftar barang, ketidaksesuaian, dan riwayat proses dalam satu
 * tampilan, tanpa dipecah menjadi beberapa tab.
 *
 * Kewenangan mengikuti PermintaanBarangResource, termasuk pembatasan tim pada
 * getEloquentQuery(), sehingga Tim dan Ketua Tim tidak dapat membuka permintaan
 * milik tim lain walaupun menebak alamat halamannya.
 */
class DetailPermintaanBarang extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PermintaanBarangResource::class;

    protected string $view = 'filament.pages.detail-permintaan';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->record->load([
            'tim',
            'detail.barang',
            'ketidaksesuaian',
            'persetujuan.pelaksana',
        ]);
    }

    public function getTitle(): string
    {
        return 'Permintaan ' . $this->record->kode_permintaan;
    }

    public function getBreadcrumb(): string
    {
        return $this->record->kode_permintaan;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon('heroicon-m-arrow-left')
                ->iconPosition(IconPosition::Before)
                ->color('gray')
                ->outlined()
                ->url(PermintaanBarangResource::getUrl('index')),

            PermintaanBarangResource::aksiUnduhBukti(),
        ];
    }
}
