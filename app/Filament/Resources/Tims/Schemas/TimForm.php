<?php

namespace App\Filament\Resources\Tims\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Tim')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nama_tim')
                            ->label('Nama Tim')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        // Ketua Tim harus pengguna aktif berperan Ketua Tim yang terdaftar
                        // pada tim ini, agar penyetuju permintaan dan penanda tangan
                        // bukti tidak dapat berbeda orang (A-012). Pada tim baru belum ada
                        // pengguna yang terdaftar, sehingga pilihannya kosong.
                        Select::make('ketua_tim_id')
                            ->label('Ketua Tim')
                            ->relationship(
                                'ketuaTim',
                                'name',
                                modifyQueryUsing: fn ($query, $record) => $record?->getKey()
                                    ? $query->where('status_aktif', true)->where('role', 'ketua_tim')->where('tim_id', $record->getKey())
                                    : $query->whereRaw('1 = 0'),
                            )
                            // Data lama yang menyimpang tetap tampil namanya (bukan kosong),
                            // lalu penyimpanan menuntut perbaikannya.
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                            ->searchable()
                            ->preload()
                            ->placeholder('Belum ditetapkan')
                            ->helperText('Hanya pengguna aktif berperan Ketua Tim yang terdaftar pada tim ini.')
                            ->rule(static fn ($record): \Closure => static function (string $attribute, $value, \Closure $fail) use ($record): void {
                                if (blank($value)) {
                                    return;
                                }

                                $sah = $record?->getKey() && User::query()
                                    ->whereKey($value)
                                    ->where('status_aktif', true)
                                    ->where('role', 'ketua_tim')
                                    ->where('tim_id', $record->getKey())
                                    ->exists();

                                if (! $sah) {
                                    $fail('Ketua Tim harus pengguna aktif berperan Ketua Tim yang terdaftar pada tim ini. Tetapkan peran dan tim lewat menu Pengguna terlebih dahulu.');
                                }
                            }),
                        Toggle::make('status_aktif')
                            ->label('Tim Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Sinkronisasi Data')
                    ->description('Diisi otomatis saat sinkronisasi data tim kerja.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        // Diisi impor dan sinkronisasi; tampil saja, tidak ikut disimpan dari form.
                        TextInput::make('external_id')
                            ->label('ID Eksternal')
                            ->maxLength(50)
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('synced_at')
                            ->label('Waktu Sinkronisasi')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
