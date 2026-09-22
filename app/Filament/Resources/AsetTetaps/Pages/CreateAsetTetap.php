<?php

namespace App\Filament\Resources\AsetTetaps\Pages;

use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAsetTetap extends CreateRecord
{
    protected static string $resource = AsetTetapResource::class;

    /**
     * Membuka riwayat penempatan pertama aset, bila timnya sudah diisi.
     *
     * Diletakkan pada halaman ini, bukan pada peristiwa `created` model, karena
     * yang perlu diubah memang hanya penambahan lewat antarmuka. Seeder menulis
     * barisnya sendiri lewat query builder, dan aturan bagi perubahan tim
     * penempatan sesudah aset tersimpan belum ditetapkan — keduanya tidak boleh
     * ikut terbawa hanya karena kaitnya dipasang di tempat yang lebih dalam.
     *
     * Penjaga terhadap baris kembar berada di dalam catatPenempatanAwal(),
     * sehingga menyimpan ulang formulir tidak menambah riwayat kedua.
     */
    protected function afterCreate(): void
    {
        $this->record->catatPenempatanAwal();
    }
}
