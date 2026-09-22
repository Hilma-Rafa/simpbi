<?php

namespace App\Filament\Resources\AsetTetaps\Pages;

use App\Filament\Resources\AsetTetaps\AsetTetapResource;
use App\Filament\Support\AksiHapusTerlindung;
use Filament\Resources\Pages\EditRecord;

class EditAsetTetap extends EditRecord
{
    protected static string $resource = AsetTetapResource::class;

    /**
     * Apakah aset ini belum punya tim kerja sebelum penyimpanan berjalan.
     *
     * Dibaca sebelum formulir dituangkan ke model, sebab sesudahnya nilai lama
     * itu sudah tertimpa dan tidak ada lagi cara mengetahui bahwa timnya baru
     * terisi pada penyimpanan ini.
     */
    protected bool $timSemulaKosong = false;

    protected function getHeaderActions(): array
    {
        return [
            AksiHapusTerlindung::tunggal(AsetTetapResource::ALASAN_TAK_DAPAT_DIHAPUS),
        ];
    }

    /**
     * Filament memanggil kait ini sebelum data formulir dituangkan ke model,
     * sehingga yang terbaca di sini masih nilai sebagaimana tersimpan.
     */
    protected function beforeSave(): void
    {
        $this->timSemulaKosong = blank($this->record->tim_penempatan_id);
    }

    /**
     * Membuka penempatan awal ketika tim kerja aset baru terisi pertama kali.
     *
     * Aset dapat dicatat lebih dulu tanpa tim kerja, misalnya barang yang sudah
     * diterima tetapi belum diputuskan penempatannya. Pengisian tim yang pertama
     * itulah penempatan awalnya, dan tanpa kait ini aset semacam itu tidak akan
     * pernah memiliki titik awal riwayat.
     *
     * Syaratnya sengaja sempit, yaitu hanya perpindahan dari kosong menjadi
     * terisi. Dua hal yang ditutup olehnya:
     *
     *  - Penyuntingan aset yang timnya memang sudah terisi tidak menghasilkan
     *    riwayat apa pun, sehingga menyunting nama atau kondisi aset lama tidak
     *    diam-diam menambal data yang memang belum pernah ada. Pengisian riwayat
     *    bagi aset lama adalah prosedur tersendiri, bukan efek samping menyimpan
     *    formulir.
     *  - Penggantian tim pada aset yang sudah ditempatkan tetap tidak dicatat di
     *    sini. Perpindahan aset antar tim kerja adalah mutasi, dan mutasi hanya
     *    sah melalui BAST — penyuntingan biasa tidak boleh menjadi penggantinya.
     *
     * Penjaga terhadap baris kembar tetap berada di dalam catatPenempatanAwal().
     */
    protected function afterSave(): void
    {
        if (! $this->timSemulaKosong) {
            return;
        }

        $this->record->catatPenempatanAwal();
    }
}
