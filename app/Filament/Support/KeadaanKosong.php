<?php

namespace App\Filament\Support;

use Filament\Tables\Contracts\HasTable;

/**
 * Pembeda antara daftar yang memang belum berisi dan daftar yang sedang
 * disaring sehingga tidak menemukan apa pun.
 *
 * Filament memakai satu keadaan kosong untuk kedua hal itu, padahal
 * kalimatnya harus berbeda: "belum ada data" menyesatkan ketika pengguna
 * sebenarnya sedang mencari kata yang tidak ada, dan "coba ubah penyaring"
 * membingungkan ketika tabelnya memang masih kosong. Pemeriksaan ini dipakai
 * bersama oleh seluruh tabel agar kalimatnya konsisten dan tidak ditulis
 * ulang di tiap berkas.
 */
class KeadaanKosong
{
    /** Apakah pengguna sedang mencari atau menyaring daftar ini? */
    public static function sedangDisaring(HasTable $livewire): bool
    {
        if (filled($livewire->getTableSearch())) {
            return true;
        }

        // Nilai penyaring tersimpan sebagai larik bersarang, misalnya
        // ['kategori_id' => ['value' => 3]]. Penyaring yang belum dipakai
        // bernilai null atau string kosong, sehingga cukup diperiksa
        // apakah masih ada nilai yang terisi setelah dirata-datarkan.
        return collect($livewire->tableFilters ?? [])
            ->flatten()
            ->contains(fn ($nilai): bool => filled($nilai) && $nilai !== false);
    }
}
