<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Aksi hapus bagi data induk yang riwayatnya dilindungi.
 *
 * Penolakannya sendiri dikerjakan peristiwa `deleting` pada modelnya, sebab
 * itulah satu-satunya tempat yang dilewati seluruh jalur penghapusan. Kelas ini
 * hanya mengurus apa yang dilihat dan dibaca penggunanya: alasan penolakan, dan
 * ke mana ia sebaiknya pergi.
 *
 * Tombolnya sengaja tidak disembunyikan. Tombol yang hilang tanpa keterangan
 * membuat orang mengira sistemnya rusak; tombol yang menolak sambil menjelaskan
 * alasannya justru mengajarkan aturan yang berlaku.
 */
class AksiHapusTerlindung
{
    /**
     * Penghapusan satu data, menolak sambil menerangkan alasannya.
     *
     * `$penjaga` (opsional) menerima kumpulan data yang akan dihapus dan
     * mengembalikan alasan penolakan, atau null bila boleh. Ia berdiri di
     * samping penjaga riwayat, bukan menggantikannya.
     */
    public static function tunggal(string $alasan, ?\Closure $penjaga = null): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (Model $record, DeleteAction $action) use ($alasan, $penjaga): void {
                if ($penjaga && ($pesan = $penjaga(collect([$record])))) {
                    static::tolak($pesan);

                    $action->cancel();
                }

                if (! $record->punyaRiwayat()) {
                    return;
                }

                Notification::make()
                    ->danger()
                    ->title('Tidak dapat dihapus')
                    ->body($alasan)
                    ->persistent()
                    ->send();

                $action->cancel();
            });
    }

    /**
     * Penghapusan banyak data sekaligus.
     *
     * `fetchSelectedRecords()` ditetapkan tegas, meski itu memang bawaannya.
     * Tanpa pengambilan per data, Filament menghapus lewat satu kueri massal
     * yang tidak membangkitkan peristiwa model sama sekali — dan penjaga di
     * modelnya akan terlewati tanpa suara.
     *
     * Keterangannya muncul sebelum pengguna menekan tombol, dan Filament
     * sendiri melaporkan berapa yang berhasil dari berapa yang dipilih.
     */
    public static function massal(string $alasan, ?\Closure $penjaga = null): DeleteBulkAction
    {
        $aksi = DeleteBulkAction::make()
            ->fetchSelectedRecords()
            ->modalDescription($alasan);

        // Diperiksa atas seluruh pilihan sebelum satu pun dihapus, sehingga
        // penolakannya atomik: tidak ada penghapusan sebagian.
        if ($penjaga) {
            $aksi->before(function (\Illuminate\Support\Collection $records, DeleteBulkAction $action) use ($penjaga): void {
                if ($pesan = $penjaga($records)) {
                    static::tolak($pesan);

                    $action->cancel();
                }
            });
        }

        return $aksi;
    }

    protected static function tolak(string $pesan): void
    {
        Notification::make()
            ->danger()
            ->title('Tidak dapat dihapus')
            ->body($pesan)
            ->persistent()
            ->send();
    }
}
