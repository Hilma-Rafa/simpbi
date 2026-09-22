<?php

namespace App\Filament\Resources\PermintaanBarangs\Pages;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Pengalih tautan rincian lama menuju pop-up Rincian pada daftar.
 *
 * Rincian permintaan kini selalu dibuka sebagai pop-up di atas Daftar
 * Permintaan Barang (Instruksi §41), termasuk ketika dituju dari notifikasi
 * lonceng maupun pesan WhatsApp. Halaman rincian `/{id}` tersendiri tidak lagi
 * dipakai, tetapi routenya dipertahankan sebagai pengalih agar tautan lama yang
 * masih beredar tetap mendarat di tempat yang benar — Daftar Permintaan yang
 * tersaring pada permintaan itu dengan pop-up Rincian yang langsung terbuka.
 *
 * Kewenangan tetap ditegakkan lebih dulu lewat resolveRecord(), yang memakai
 * getEloquentQuery() milik resource — termasuk pembatasan tim — sehingga Tim
 * dan Ketua Tim tidak dapat mengalihkan diri ke permintaan milik tim lain
 * walau menebak alamatnya.
 */
class DetailPermintaanBarang extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PermintaanBarangResource::class;

    protected string $view = 'filament.pages.detail-permintaan';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->redirect(PermintaanBarangResource::urlRincian($this->record));
    }
}
