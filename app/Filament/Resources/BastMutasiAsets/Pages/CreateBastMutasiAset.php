<?php

namespace App\Filament\Resources\BastMutasiAsets\Pages;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Services\DokumenBastService;
use App\Services\MutasiAsetService;
use Filament\Resources\Pages\CreateRecord;

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

    /** Bentuk berkas draf BAST (belum ber-e-TTD) agar dapat dipratinjau. */
    protected function afterCreate(): void
    {
        $path = app(DokumenBastService::class)->buat($this->record);
        $this->record->update(['file_bast_path' => $path]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
