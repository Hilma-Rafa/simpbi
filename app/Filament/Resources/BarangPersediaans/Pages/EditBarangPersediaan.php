<?php

namespace App\Filament\Resources\BarangPersediaans\Pages;

use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\AksiKembali;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\HtmlString;

class EditBarangPersediaan extends EditRecord
{
    protected static string $resource = BarangPersediaanResource::class;

    /**
     * Penanda bahwa pengguna sudah menyetujui perubahan stok fisik.
     *
     * Hanya bernilai benar di dalam permintaan yang berasal dari tombol
     * konfirmasi. Ia dikembalikan ke semula sesudah penyimpanan, supaya
     * penyuntingan kedua pada halaman yang sama tetap dimintai persetujuan.
     */
    public bool $stokDikonfirmasi = false;

    protected function getHeaderActions(): array
    {
        return [
            AksiKembali::keDaftar(static::getResource()),
            AksiHapusTerlindung::tunggal(BarangPersediaanResource::ALASAN_TAK_DAPAT_DIHAPUS),
        ];
    }

    /**
     * Dialog persetujuan perubahan stok fisik.
     *
     * Stok fisik adalah satu-satunya kolom pada formulir ini yang angkanya
     * dipakai di luar halaman ini juga — katalog barang, dasbor, dan peringatan
     * stok menipis semuanya membacanya. Mengubahnya karena itu bukan
     * penyuntingan biasa seperti membetulkan ejaan nama barang, dan pantas
     * ditanyakan sekali sebelum tersimpan.
     *
     * Kata-katanya sengaja tidak menjanjikan pencatatan transaksi. Perubahan
     * ini memang perubahan data induk, bukan mutasi stok, dan kalimat seperti
     * "koreksi akan dicatat" akan membuat pengguna mencarinya di Kartu
     * Kendali — tempat ia tidak akan pernah muncul.
     */
    public function konfirmasiStokAction(): Action
    {
        return Action::make('konfirmasiStok')
            ->modalHeading('Perubahan Stok Fisik')
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p>Stok fisik <strong>' . e($this->record->nama_barang) . '</strong> akan diubah dari '
                . '<strong>' . e($this->stokTersimpan()) . ' ' . e($this->record->satuan) . '</strong> menjadi '
                . '<strong>' . e($this->stokBaru()) . ' ' . e($this->record->satuan) . '</strong>.</p>'
                . '<p class="mt-2">Perubahan ini akan memengaruhi jumlah stok tersedia, katalog '
                . 'permintaan barang, serta ringkasan dan peringatan stok pada dasbor.</p>'
                . '<p class="mt-2">Penyesuaian ini merupakan perubahan data induk, bukan transaksi '
                . 'mutasi stok, sehingga tidak menghasilkan baris baru pada Kartu Kendali.</p>'
                . '<p class="mt-2">Pastikan jumlah tersebut sesuai dengan kondisi fisik barang di '
                . 'gudang sebelum melanjutkan.</p>'
            ))
            ->modalSubmitActionLabel('Ya, Simpan Perubahan')
            ->modalCancelActionLabel('Batal')
            ->color('warning')
            ->action(function (): void {
                $this->stokDikonfirmasi = true;

                $this->save();
            });
    }

    /**
     * Menahan penyimpanan sampai perubahan stok fisik disetujui.
     *
     * Diletakkan pada kait simpan, bukan pada tombolnya, sebab tombol bukan
     * satu-satunya jalan: formulir juga tersimpan lewat tombol Enter dan
     * pintasan papan tik. Penjaga di sini dilewati ketiganya, sehingga tidak
     * ada jalur yang luput.
     */
    protected function beforeSave(): void
    {
        if ($this->stokDikonfirmasi || ! $this->stokFisikBerubah()) {
            return;
        }

        $this->mountAction('konfirmasiStok');

        // Transaksi digulung balik: tidak ada satu pun kolom yang tersimpan
        // sampai penggunanya menekan tombol konfirmasi.
        $this->halt(true);
    }

    /**
     * Jaring pengaman di server; lihat keterangan pada
     * CreateBarangPersediaan::handleRecordCreation().
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (UniqueConstraintViolationException) {
            Notification::make()
                ->title('Kode barang sudah dipakai pada kategori ini.')
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    /** Penandanya dilepas supaya penyuntingan berikutnya ditanyakan lagi. */
    protected function afterSave(): void
    {
        $this->stokDikonfirmasi = false;
    }

    /**
     * Apakah stok fisik pada formulir berbeda dengan yang tersimpan.
     *
     * Nilai tersimpan dibaca dari basis data, bukan dari model yang sedang
     * dipegang halaman, sebab pada saat kait simpan berjalan model itu belum
     * dituangi data formulir — dan setelah dituangi, nilai lamanya sudah tidak
     * dapat ditemukan lagi.
     */
    protected function stokFisikBerubah(): bool
    {
        return $this->stokBaru() !== $this->stokTersimpan();
    }

    protected function stokTersimpan(): int
    {
        return (int) $this->record->getOriginal('stok_fisik');
    }

    protected function stokBaru(): int
    {
        return (int) ($this->data['stok_fisik'] ?? $this->stokTersimpan());
    }
}
