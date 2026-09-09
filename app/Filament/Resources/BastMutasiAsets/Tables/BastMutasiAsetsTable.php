<?php

namespace App\Filament\Resources\BastMutasiAsets\Tables;

use App\Models\BastMutasiAset;
use App\Services\MutasiAsetService;
use App\Services\NotifikasiService;
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
                    ->dateTime('d M Y')
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
                        app(MutasiAsetService::class)->sahkan($r, auth()->id());

                        // Ketua Tim tujuan kini berkepentingan: aset sudah
                        // berpindah dan menunggu konfirmasi penerimaannya.
                        app(NotifikasiService::class)->bastBerubah($r->refresh());

                        Notification::make()->title('BAST disahkan')->success()->send();
                    }),

                // UC-18 — Konfirmasi penerimaan oleh Ketua Tim tujuan.
                Action::make('konfirmasi')
                    ->label('Konfirmasi Penerimaan')
                    ->icon('heroicon-m-hand-thumb-up')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Konfirmasikan bahwa aset telah diterima oleh unit kerja Anda.')
                    ->visible(fn (BastMutasiAset $r): bool => $r->status === 'menunggu_konfirmasi'
                        && auth()->user()?->role === 'ketua_tim'
                        && auth()->user()?->tim_id === $r->tim_tujuan_id)
                    ->action(function (BastMutasiAset $r): void {
                        app(MutasiAsetService::class)->konfirmasi($r, auth()->id());

                        // Pembuat BAST dan pengesahnya diberi tahu bahwa
                        // mutasinya sudah tuntas secara administratif.
                        app(NotifikasiService::class)->bastBerubah($r->refresh());

                        Notification::make()->title('Penerimaan aset dikonfirmasi')->success()->send();
                    }),

                // Unduh dokumen BAST (tersedia setelah disahkan).
                Action::make('unduh')
                    ->label('Unduh BAST')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (BastMutasiAset $r): ?string => $r->file_bast_path ? route('bast.unduh', $r) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (BastMutasiAset $r): bool => filled($r->file_bast_path)),
            ]);
    }
}
