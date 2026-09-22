<?php

namespace App\Filament\Resources\BastMutasiAsets\Tables;

use App\Filament\Support\KeadaanKosong;
use App\Models\BastMutasiAset;
use App\Services\MutasiAsetService;
use App\Services\NotifikasiService;
use App\Services\StokService;
use App\Support\TandaTangan;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BastMutasiAsetsTable
{
    protected const STATUS_LABEL = [
        'menunggu_pengesahan'   => 'Menunggu Pengesahan',
        'menunggu_konfirmasi'   => 'Menunggu Konfirmasi',
        'selesai_administratif' => 'Selesai Administratif',
    ];

    protected const STATUS_COLOR = [
        'menunggu_pengesahan'   => 'warning',
        'menunggu_konfirmasi'   => 'info',
        'selesai_administratif' => 'success',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            /**
             * Kalimat keadaan kosong dibedakan: daftar yang memang belum berisi
             * memerlukan ajakan mengisi, sedangkan pencarian yang tidak
             * menemukan apa pun memerlukan jalan keluar dari penyaringnya.
             */
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada BAST yang cocok'
                : 'Belum ada mutasi aset')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali nomor BAST yang dicari.'
                : 'BAST terbit ketika aset tetap dipindahkan antar tim kerja. Buat form BAST untuk mencatat perpindahan pertama.')
            ->columns([
                TextColumn::make('nomor_bast')
                    ->label('Nomor BAST')
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('aset.nama_aset')
                    ->label('Aset')
                    ->description(fn (BastMutasiAset $r): string => 'NUP ' . ($r->aset?->nup ?? '-'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('timAsal.nama_tim')
                    ->label('Asal'),
                TextColumn::make('timTujuan.nama_tim')
                    ->label('Tujuan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABEL[$state] ?? $state)
                    ->color(fn (string $state): string => self::STATUS_COLOR[$state] ?? 'gray'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d-m-Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUS_LABEL),
            ])
            ->recordActions([
                // UC-17 — Pengesahan oleh Kasubbag Umum.
                Action::make('sahkan')
                    ->label('Sahkan')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sahkan BAST Mutasi Aset')
                    ->modalDescription(fn (BastMutasiAset $r): string => "Sahkan {$r->nomor_bast}? Penempatan aset akan dipindahkan ke {$r->timTujuan?->nama_tim} dan e-TTD dibubuhkan pada dokumen.")
                    ->visible(fn (BastMutasiAset $r): bool => $r->status === 'menunggu_pengesahan' && auth()->user()?->role === 'kasubbag')
                    ->action(function (BastMutasiAset $r): void {
                        try {
                            app(MutasiAsetService::class)->sahkan($r, auth()->id());
                        } catch (\RuntimeException $e) {
                            // Penempatan aset berubah sejak BAST dibuat: ditolak tanpa
                            // perubahan apa pun. Galat teknis dilempar ulang.
                            $pesan = StokService::pesanAturan($e);

                            if ($pesan === null) {
                                throw $e;
                            }

                            Notification::make()
                                ->title('BAST tidak dapat disahkan')
                                ->body($pesan)
                                ->danger()
                                ->send();

                            return;
                        }

                        // Ketua Tim tujuan kini berkepentingan: aset sudah
                        // berpindah dan menunggu konfirmasi penerimaannya.
                        app(NotifikasiService::class)->bastBerubah($r->refresh());

                        Notification::make()->title('BAST disahkan')->success()->send();
                    }),

                // UC-18 — Konfirmasi penerimaan oleh Ketua Tim tujuan.
                //
                // Penerima cukup mengkonfirmasi; ia tidak menggambar tanda
                // tangan. Tanda tangan yang dibubuhkan pada BAST adalah yang
                // sudah terdaftar di akunnya, dipasang saat dokumen dibentuk
                // ulang oleh MutasiAsetService::konfirmasi().
                Action::make('konfirmasi')
                    ->label('Konfirmasi Penerimaan')
                    ->icon('heroicon-m-hand-thumb-up')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Konfirmasikan bahwa aset telah diterima oleh tim kerja Anda. Tanda tangan Anda yang tersimpan akan dibubuhkan pada BAST sebagai pihak penerima.')
                    ->modalSubmitActionLabel('Konfirmasi Penerimaan')
                    ->visible(fn (BastMutasiAset $r): bool => $r->status === 'menunggu_konfirmasi'
                        && auth()->user()?->role === 'ketua_tim'
                        && auth()->user()?->tim_id === $r->tim_tujuan_id)
                    ->action(function (BastMutasiAset $r): void {
                        // Lapis pertahanan terakhir: onboarding sudah menuntut
                        // Ketua Tim mendaftarkan tanda tangan, tetapi bila entah
                        // bagaimana belum ada, konfirmasi dihentikan daripada
                        // menerbitkan BAST dengan ruang tanda tangan penerima
                        // yang kosong.
                        if (! TandaTangan::terdaftar(auth()->user())) {
                            Notification::make()
                                ->title('Tanda tangan belum tersedia')
                                ->body('Lengkapi tanda tangan Anda melalui pelengkapan akun sebelum mengkonfirmasi penerimaan aset.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        try {
                            app(MutasiAsetService::class)->konfirmasi($r, auth()->id());
                        } catch (\RuntimeException $e) {
                            // Status berubah sejak halaman dibuka: ditolak tanpa perubahan
                            // apa pun. Galat teknis dilempar ulang.
                            $pesan = StokService::pesanAturan($e);

                            if ($pesan === null) {
                                throw $e;
                            }

                            Notification::make()
                                ->title('Penerimaan tidak dapat dikonfirmasi')
                                ->body($pesan)
                                ->danger()
                                ->send();

                            return;
                        }

                        // Pembuat BAST dan pengesahnya diberi tahu bahwa
                        // mutasinya sudah tuntas secara administratif.
                        app(NotifikasiService::class)->bastBerubah($r->refresh());

                        Notification::make()->title('Penerimaan aset dikonfirmasi')->success()->send();
                    }),

                // Unduh dokumen BAST (tersedia setelah disahkan).
                //
                // Gaya disamakan dengan PermintaanBarangResource::aksiUnduhBukti(),
                // sebab keduanya sama-sama tombol pengunduhan dokumen yang sudah
                // disahkan — bergaris warna utama, bukan tautan abu-abu yang
                // tampak berbeda sendiri di antara tombol pengunduhan lain.
                Action::make('unduh')
                    ->label('Unduh BAST')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->iconPosition(\Filament\Support\Enums\IconPosition::Before)
                    ->color('primary')
                    ->button()
                    ->outlined()
                    ->tooltip('Unduh dokumen BAST yang telah disahkan')
                    ->url(fn (BastMutasiAset $r): ?string => $r->file_bast_path ? route('bast.unduh', $r) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (BastMutasiAset $r): bool => filled($r->file_bast_path) && filled($r->disahkan_at)),
            ]);
    }
}
