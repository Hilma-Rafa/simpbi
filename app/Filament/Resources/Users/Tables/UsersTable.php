<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Support\AksiImpor;
use App\Services\Impor\ImporPengguna;
use Filament\Actions\BulkActionGroup;
use App\Filament\Support\AksiHapusTerlindung;
use App\Filament\Support\KeadaanKosong;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\AksiUbah;
use App\Models\User;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    /** Warna badge peran (sejalan dengan makna warna Instruksi §25). */
    protected const ROLE_COLORS = [
        'admin'          => 'gray',
        'kasubbag'       => 'primary',
        'petugas_gudang' => 'info',
        'ketua_tim'      => 'gray',
        'tim'            => 'gray',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            /**
             * Kalimat keadaan kosong dibedakan: daftar yang memang belum berisi
             * memerlukan ajakan mengisi, sedangkan pencarian yang tidak
             * menemukan apa pun memerlukan jalan keluar dari penyaringnya.
             */
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada pengguna yang cocok'
                : 'Belum ada pengguna')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali nama maupun surel yang dicari.'
                : 'Tambahkan akun pegawai beserta perannya, atau impor daftarnya sekaligus dari berkas.')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn ($record) => $record->username)
                    ->searchable(['name', 'username'])
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Alamat Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserForm::ROLE_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => self::ROLE_COLORS[$state] ?? 'gray')
                    ->sortable(),
                TextColumn::make('tim.nama_tim')
                    ->label('Tim Kerja')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                /** Lihat catatan yang sama pada tabel Barang Persediaan. */
                IconColumn::make('status_aktif')
                    ->label('Aktif')
                    ->boolean()
                    ->trueColor('gray')
                    ->falseColor('danger'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(UserForm::ROLE_OPTIONS),
                TernaryFilter::make('status_aktif')
                    ->label('Status Akun')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                AksiUbah::buat(),
            ])
            ->headerActions([
                AksiImpor::buat(
                    judul: ImporPengguna::JUDUL,
                    kolom: ImporPengguna::kolom(),
                    namaTemplate: 'Template-Impor-Pengguna.xlsx',
                    impor: fn (string $lintasan) => app(ImporPengguna::class)->jalankan($lintasan),
                ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AksiHapusTerlindung::massal(UserResource::ALASAN_TAK_DAPAT_DIHAPUS, fn ($akun) => User::alasanTidakDapatDihapus($akun)),
                ]),
            ]);
    }
}
