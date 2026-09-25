<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Pages\GantiKataSandi;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\AksiHapusTerlindung;
use App\Models\User;
use App\Filament\Support\AksiKembali;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AksiKembali::keDaftar(static::getResource()),
            AksiHapusTerlindung::tunggal(UserResource::ALASAN_TAK_DAPAT_DIHAPUS, fn ($akun) => User::alasanTidakDapatDihapus($akun)),
        ];
    }

    /**
     * Penjaga sisi peladen: Admin tidak boleh mengunci dirinya sendiri, dan
     * sistem tidak boleh kehilangan Admin aktif terakhirnya. Dibaca dari
     * muatan formulir mentah, sebab pada akun sendiri kolom peran dan status
     * dikunci dan tidak ikut disimpan.
     *
     * Yang menentukan jatuh ke nilai tersimpan adalah ada-tidaknya kunci pada
     * muatan, bukan nilainya. Kunci yang ada tetapi bernilai null tetap dibaca
     * seperti Toggle menyimpannya (null = false); membacanya sebagai "tidak
     * berubah" membuat Admin aktif terakhir dapat dinonaktifkan lewat muatan
     * null (temuan white-box T-1).
     *
     * Kunci status yang hilang hanya berarti "tidak berubah" pada akun
     * sendiri, sebab hanya di sana kolomnya memang tidak didehidrasi. Pada
     * akun lain kolom itu tetap didehidrasi, sehingga kunci yang hilang
     * tersimpan sebagai false; membacanya sebagai status tersimpan membuka
     * kembali celah yang sama lewat muatan tanpa kunci (temuan uji ulang T-1b).
     */
    protected function beforeSave(): void
    {
        $akun        = $this->getRecord();
        $akunSendiri = $akun->is(auth()->user());
        $peranBaru   = array_key_exists('role', $this->data) ? $this->data['role'] : $akun->role;

        if (array_key_exists('status_aktif', $this->data)) {
            $aktifBaru = (bool) $this->data['status_aktif'];
        } elseif ($akunSendiri) {
            $aktifBaru = (bool) $akun->status_aktif;
        } else {
            $aktifBaru = false;
        }

        if ($akunSendiri
            && ($peranBaru !== $akun->role || $aktifBaru !== (bool) $akun->status_aktif)) {
            $this->tolakPerubahan(UserForm::PESAN_AKUN_SENDIRI);
        }

        $adminAktifTerakhir = $akun->role === 'admin'
            && $akun->status_aktif
            && ! User::where('role', 'admin')->where('status_aktif', true)->whereKeyNot($akun->getKey())->exists();

        if ($adminAktifTerakhir && ($peranBaru !== 'admin' || ! $aktifBaru)) {
            $this->tolakPerubahan('Harus ada minimal satu Admin aktif.');
        }
    }

    /**
     * Admin yang mengganti kata sandi akunnya sendiri tetap masuk di
     * perangkatnya (G-003): hash di sesinya diperbarui dengan pembantu yang
     * sama dengan Ganti Kata Sandi dan Pengaturan (A-008). Akun pengguna lain
     * tidak menyentuh sesi Admin; sesi lama pengguna itu tidak berlaku lagi.
     */
    protected function afterSave(): void
    {
        $akun = $this->getRecord();

        if ($akun->is(auth()->user()) && $akun->wasChanged('password')) {
            // Instans pengguna yang masuk memegang hash lama; muat ulang dahulu.
            auth()->user()->refresh();

            GantiKataSandi::perbaruiHashSesi();
        }
    }

    protected function tolakPerubahan(string $pesan): void
    {
        Notification::make()
            ->danger()
            ->title('Perubahan ditolak')
            ->body($pesan)
            ->send();

        $this->halt();
    }
}
