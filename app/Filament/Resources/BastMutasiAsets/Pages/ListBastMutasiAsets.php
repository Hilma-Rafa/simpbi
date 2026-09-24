<?php

namespace App\Filament\Resources\BastMutasiAsets\Pages;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\BastMutasiAsets\Schemas\BastMutasiAsetForm;
use App\Models\BastMutasiAset;
use App\Models\Tim;
use App\Services\DokumenBastService;
use App\Services\MutasiAsetService;
use App\Services\NotifikasiService;
use App\Services\StokService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ListBastMutasiAsets extends ListRecords
{
    protected static string $resource = BastMutasiAsetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pembuatan BAST sebagai pop-up (B), bukan lagi halaman Buat
            // terpisah — BastMutasiAsetResource tidak lagi mendaftarkan
            // halaman 'create', sehingga CreateAction bawaan Filament ini
            // otomatis terbuka sebagai modal memakai skema BastMutasiAsetForm,
            // bukan berpindah ke URL lain. Rincian (aset, Tim Asal terkunci,
            // Tim Tujuan, Alasan Mutasi) wajib terlihat di dalam modal ini
            // sebelum tombol "Buat BAST" di kakinya dapat ditekan; tidak ada
            // langkah konfirmasi kedua dan tidak ada kolom ketik NIP.
            CreateAction::make()
                ->label('Buat BAST')
                ->modalHeading('Buat BAST Mutasi Aset')
                ->modalSubmitActionLabel('Buat BAST')
                ->modalCancelActionLabel('Batal')
                // Lapis UI (perbaikan akses, pasca-Fase 0-6): hanya Petugas
                // Gudang, sama seperti canCreate() milik resource ini dan pola
                // yang sudah dipakai aksi Sahkan (khusus Kasubbag)/Konfirmasi
                // (khusus Ketua Tim tim tujuan) di BastMutasiAsetsTable.php.
                // BUKAN satu-satunya penjaga — CreateAction bawaan Filament
                // TIDAK otomatis terikat ke Resource::canCreate() (berbeda
                // dari halaman CreateRecord lama yang memanggil
                // authorizeAccess() di mount()); jaring pengaman yang mengikat
                // ada di handleCreation() di bawah.
                ->visible(fn (): bool => BastMutasiAsetResource::canCreate())
                // Penjaga tanda tangan (E), lapis UX: Filament tidak dapat
                // membekukan tombol pemicu (header) berdasarkan nilai field DI
                // DALAM modal yang belum dibuka — ->disabled() pada Action itu
                // sendiri dievaluasi juga saat modalnya belum ter-mount, dan
                // Get $get tidak dapat mengikat ke schema yang belum ada.
                // Peringatan reaktif karena itu ditaruh sebagai Placeholder DI
                // DALAM form (BastMutasiAsetForm::pesanTandaTangan(), field
                // 'penjagaTandaTangan') yang tampil begitu Tim Tujuan dipilih.
                // Jaring pengaman yang mengikat tetap di server, lihat
                // handleCreation() di bawah — percobaan submit dengan tanda
                // tangan yang belum lengkap ditolak dengan notifikasi, bukan
                // dibuat diam-diam.
                ->mutateFormDataUsing(function (array $data): array {
                    $data['nomor_bast'] = app(MutasiAsetService::class)->nomorBaru();
                    $data['dibuat_oleh_id'] = auth()->id();
                    // Status awal pada alur yang dibalik (A): BAST menunggu
                    // konfirmasi penerimaan Ketua Tim tujuan lebih dulu,
                    // bukan menunggu pengesahan Kasubbag.
                    $data['status'] = 'menunggu_konfirmasi';

                    // Pihak Penyerah/Penerima (D, G.3): kolom NOT NULL tanpa
                    // default pada skema, tidak lagi diminta di form — diisi
                    // otomatis dari nama Ketua Tim asal/tujuan, sumber yang
                    // sama persis dengan yang dipakai DokumenBastService
                    // merender tanda tangan pada dokumen.
                    $timAsal = filled($data['tim_asal_id'] ?? null) ? Tim::find($data['tim_asal_id']) : null;
                    $timTujuan = filled($data['tim_tujuan_id'] ?? null) ? Tim::find($data['tim_tujuan_id']) : null;
                    $data['pihak_penyerah'] = $timAsal?->ketuaTim?->name ?? '';
                    $data['pihak_penerima'] = $timTujuan?->ketuaTim?->name ?? '';

                    return $data;
                })
                ->using(fn (array $data): Model => static::handleCreation($data))
                ->after(function (BastMutasiAset $record): void {
                    // Bentuk berkas draf BAST (belum ber-e-TTD) agar dapat
                    // dipratinjau.
                    $path = app(DokumenBastService::class)->buat($record);
                    $record->update(['file_bast_path' => $path]);

                    // Memberitahukan pihak yang harus bertindak berikutnya
                    // pada alur yang dibalik, yaitu Ketua Tim tujuan yang
                    // mengkonfirmasi penerimaan (UC-18) — bukan lagi Kasubbag.
                    app(NotifikasiService::class)->bastBerubah($record->refresh());
                }),

            // Pintasan ke halaman Riwayat dengan jenis Mutasi Aset terpilih
            \Filament\Actions\Action::make('riwayat')
                ->label('Riwayat')
                ->icon('heroicon-m-archive-box')
                ->iconPosition(\Filament\Support\Enums\IconPosition::Before)
                ->color('gray')
                ->outlined()
                ->tooltip('Lihat BAST mutasi aset yang sudah disahkan')
                ->url(\App\Filament\Pages\Riwayat::getUrl(['jenis' => 'mutasi_aset']))
                ->visible(fn () => \App\Filament\Pages\Riwayat::canAccess()),
        ];
    }

    /**
     * Aset diperiksa di server dalam transaksi yang sama dengan pembuatan
     * BAST, dengan baris aset terkunci (A-011, E); penolakan tampil sebagai
     * notifikasi dan tidak ada BAST yang terbentuk. Dipindah dari
     * CreateBastMutasiAset::handleRecordCreation() (halaman lama) ke sini
     * sebagai bagian pembuatan BAST lewat CreateAction (B).
     *
     * Bentrok UNIQUE pada nomor BAST (dua pembuatan bersamaan menerima nomor
     * yang sama) diulang dengan nomor baru, paling banyak tiga kali; sesudah
     * itu berhenti dengan pesan umum, bukan galat basis data.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function handleCreation(array $data): Model
    {
        // Jaring pengaman UTAMA (perbaikan akses, pasca-Fase 0-6): hanya
        // Petugas Gudang, ditegakkan di sini terlepas dari tampilan tombol.
        // Ini memulihkan persis pemeriksaan yang dulu dilakukan
        // CreateRecord::mount() (dihapus bersama halaman lama
        // CreateBastMutasiAset saat Fase 0-6 mengganti pembuatan BAST menjadi
        // pop-up) — CreateAction bawaan Filament tidak melakukannya sendiri.
        abort_unless(BastMutasiAsetResource::canCreate(), 403);

        for ($percobaan = 1; ; $percobaan++) {
            try {
                return DB::transaction(function () use (&$data): Model {
                    app(MutasiAsetService::class)->periksaPembuatan(
                        (int) ($data['aset_id'] ?? 0),
                        isset($data['tim_asal_id']) ? (int) $data['tim_asal_id'] : null,
                        isset($data['tim_tujuan_id']) ? (int) $data['tim_tujuan_id'] : null,
                    );

                    return BastMutasiAset::create($data);
                });
            } catch (UniqueConstraintViolationException) {
                if ($percobaan >= 3) {
                    Notification::make()
                        ->title('Nomor BAST gagal diterbitkan')
                        ->body('Coba lagi, atau hubungi Sub-Bagian Umum bila berulang.')
                        ->danger()
                        ->send();

                    throw new Halt;
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

                throw new Halt;
            }
        }
    }
}
