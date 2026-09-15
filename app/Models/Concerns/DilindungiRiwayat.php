<?php

namespace App\Models\Concerns;

/**
 * Penolakan penghapusan bagi data induk yang sudah punya riwayat.
 *
 * Penjaganya dipasang pada model, bukan pada tombol, karena tombol hanya
 * mengatur apa yang terlihat. Penghapusan dapat datang dari jalur lain —
 * permintaan yang disusun sendiri ke alamat aksi Filament, aksi massal, atau
 * perintah konsol — dan seluruhnya melewati peristiwa `deleting` ini.
 *
 * Mengembalikan false membatalkan penghapusan tanpa melempar galat, sehingga
 * aksi massal yang memuat sebagian data terlindung tidak berhenti di tengah
 * jalan: yang boleh dihapus tetap terhapus, yang dilindungi dilewati. Lapisan
 * antarmuka yang menjelaskan kepada penggunanya ada di masing-masing aksi.
 */
trait DilindungiRiwayat
{
    protected static function bootDilindungiRiwayat(): void
    {
        static::deleting(fn (self $model): bool => ! $model->punyaRiwayat());
    }

    /**
     * Apakah data ini sudah dirujuk catatan lain yang tidak boleh hilang.
     */
    abstract public function punyaRiwayat(): bool;
}
