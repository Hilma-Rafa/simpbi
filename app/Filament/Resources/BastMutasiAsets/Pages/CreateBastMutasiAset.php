<?php

namespace App\Filament\Resources\BastMutasiAsets\Pages;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Services\DokumenBastService;
use App\Services\MutasiAsetService;
use App\Services\NotifikasiService;
use App\Services\StokService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateBastMutasiAset extends CreateRecord
{
    protected static string $resource = BastMutasiAsetResource::class;

    /** Nomor BAST, pembuat, dan status awal ditetapkan sistem (UC-16). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['nomor_bast'] = app(MutasiAsetService::class)->nomorBaru();
        $data['dibuat_oleh_id'] = auth()->id();
        $data['status'] = 'menunggu_pengesahan';

        return $data;
    }

    /**
     * Aset diperiksa di server dalam transaksi yang sama dengan pembuatan BAST,
     * dengan baris aset terkunci (A-011); penolakan tampil sebagai notifikasi
     * dan tidak ada BAST yang terbentuk.
     *
     * Bentrok UNIQUE pada nomor BAST (dua pembuatan bersamaan menerima nomor
     * yang sama) diulang dengan nomor baru, paling banyak tiga kali; sesudah itu
     * berhenti dengan pesan umum, bukan galat basis data.
     */
    protected function handleRecordCreation(array $data): Model
    {
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return DB::transaction(function () use ($data): Model {
                    app(MutasiAsetService::class)->periksaPembuatan((int) ($data['aset_id'] ?? 0), isset($data['tim_asal_id']) ? (int) $data['tim_asal_id'] : null);

                    return parent::handleRecordCreation($data);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($percobaan >= 3) {
                    Notification::make()
                        ->title('Nomor BAST gagal diterbitkan')
                        ->body('Coba lagi, atau hubungi Sub-Bagian Umum bila berulang.')
                        ->danger()
                        ->send();

                    $this->halt();
                }

                $data['nomor_bast'] = app(MutasiAsetService::class)->nomorBaru();
            } catch (\RuntimeException $e) {
                // Penolakan aturan bisnis tampil apa adanya; galat teknis dilempar ulang.
                $pesan = StokService::pesanAturan($e);

                if ($pesan === null) {
                    throw $e;
                }

                Notification::make()
                    ->title('BAST tidak dapat dibuat')
                    ->body($pesan)
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }

    /** Bentuk berkas draf BAST (belum ber-e-TTD) agar dapat dipratinjau. */
    protected function afterCreate(): void
    {
        $path = app(DokumenBastService::class)->buat($this->record);
        $this->record->update(['file_bast_path' => $path]);

        // Memberitahukan pihak yang harus bertindak berikutnya, yaitu Kasubbag
        // Umum yang mengesahkan BAST ini (UC-17).
        app(NotifikasiService::class)->bastBerubah($this->record->refresh());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
