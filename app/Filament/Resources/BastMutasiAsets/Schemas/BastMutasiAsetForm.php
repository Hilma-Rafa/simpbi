<?php

namespace App\Filament\Resources\BastMutasiAsets\Schemas;

use App\Models\AsetTetap;
use App\Models\Tim;
use App\Services\MutasiAsetService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Form pembuatan BAST mutasi aset (UC-16). Data hasil koordinasi di luar
 * sistem (NUP, tim asal, tim tujuan, alasan) dicatat oleh Petugas Gudang.
 *
 * "Pihak Penyerah"/"Pihak Penerima" tidak lagi diminta di sini (D): dokumen
 * BAST sudah merender otomatis nama Ketua Tim asal dan tujuan beserta tanda
 * tangannya (lihat DokumenBastService), tanpa bergantung pada teks bebas yang
 * dulu diketik operator. Kolomnya tetap ada di skema database (tanpa
 * migration) dan diisi otomatis dari nama Ketua Tim yang sama saat BAST
 * dibuat (lihat CreateAction pada ListBastMutasiAsets).
 */
class BastMutasiAsetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aset yang Dimutasi')
                    ->columns(2)
                    ->schema([
                        Select::make('aset_id')
                            ->label('Aset (NUP — Nama)')
                            ->relationship('aset', 'nama_aset', fn ($query) => $query->where('status_aktif', true))
                            ->getOptionLabelFromRecordUsing(fn (AsetTetap $r): string => "{$r->nup} — {$r->nama_aset}")
                            ->searchable(['nup', 'nama_aset'])
                            ->preload()
                            ->required()
                            ->live()
                            // Tim kerja asal otomatis mengikuti penempatan aset saat ini.
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $aset = AsetTetap::find($state);
                                $set('tim_asal_id', $aset?->tim_penempatan_id);

                                if ($aset && blank($aset->tim_penempatan_id)) {
                                    Notification::make()
                                        ->title('Aset belum memiliki penempatan')
                                        ->body(app(MutasiAsetService::class)->pesanAsetTidakDapatDimutasi($aset))
                                        ->warning()
                                        ->send();
                                }
                            })
                            // Aset tanpa penempatan, atau yang masih punya BAST menunggu
                            // konfirmasi penerimaan, tidak dapat dimutasi. Pemeriksaan yang
                            // mengikat ada di server (MutasiAsetService::periksaPembuatan).
                            ->rule(static fn (): \Closure => static function (string $attribute, $value, \Closure $fail): void {
                                $aset = filled($value) ? AsetTetap::find($value) : null;

                                if ($aset && ($pesan = app(MutasiAsetService::class)->pesanAsetTidakDapatDimutasi($aset))) {
                                    $fail($pesan);
                                }
                            })
                            ->columnSpanFull(),
                        Select::make('tim_asal_id')
                            ->label('Tim Kerja Asal')
                            ->relationship('timAsal', 'nama_tim')
                            ->required()
                            // Selalu mengikuti penempatan aset saat ini; tampil terkunci,
                            // bukan field isian — tidak dapat diubah.
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Terisi otomatis dari penempatan aset saat ini.'),
                        Select::make('tim_tujuan_id')
                            ->label('Tim Kerja Tujuan')
                            ->relationship('timTujuan', 'nama_tim')
                            ->required()
                            ->different('tim_asal_id')
                            // Reaktif: penjaga tanda tangan di bawah perlu menghitung
                            // ulang begitu tim tujuan berganti (E).
                            ->live(),
                        // Penjaga tanda tangan (E): Ketua Tim asal dan Ketua Tim tujuan
                        // sama-sama wajib sudah punya tanda tangan tersimpan sebelum BAST
                        // dapat dibuat, sebab dokumen menyematkan tanda tangan keduanya.
                        // Ini lapis UX; jaring pengaman yang mengikat ada di server
                        // (MutasiAsetService::periksaPembuatan(), konsisten dengan A-011).
                        Placeholder::make('penjagaTandaTangan')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => filled(static::pesanTandaTangan($get)))
                            ->content(fn (Get $get) => new HtmlString(
                                '<p class="text-sm text-danger-600 dark:text-danger-400">'
                                . e(static::pesanTandaTangan($get))
                                . '</p>'
                            ))
                            ->columnSpanFull(),
                    ]),

                Section::make('Berita Acara')
                    ->columns(2)
                    ->schema([
                        Textarea::make('alasan_mutasi')
                            ->label('Alasan Mutasi')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Pesan penjagaan tanda tangan untuk keadaan form saat ini, atau null bila
     * kedua Ketua Tim (asal dan tujuan) sudah lengkap. Dipakai baik oleh
     * Placeholder peringatan di atas maupun oleh CreateAction (ListBastMutasiAsets)
     * untuk membekukan tombol Buat — sumbernya sama, MutasiAsetService, supaya
     * form dan penjaga tombol tidak pernah berbeda pendapat.
     */
    public static function pesanTandaTangan(Get $get): ?string
    {
        $aset = filled($get('aset_id')) ? AsetTetap::find($get('aset_id')) : null;
        $timTujuan = filled($get('tim_tujuan_id')) ? Tim::find($get('tim_tujuan_id')) : null;

        if (! $aset?->timPenempatan && ! $timTujuan) {
            return null;
        }

        return app(MutasiAsetService::class)->pesanTandaTanganBelumLengkap($aset?->timPenempatan, $timTujuan);
    }
}
