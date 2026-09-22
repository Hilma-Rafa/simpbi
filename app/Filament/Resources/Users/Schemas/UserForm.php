<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    /** Peran sesuai Instruksi §4 — jangan menambah/mengubah peran. */
    public const ROLE_OPTIONS = [
        'admin'          => 'Admin Sistem',
        'kasubbag'       => 'Kasubbag Umum',
        'petugas_gudang' => 'Petugas Gudang',
        'ketua_tim'      => 'Ketua Tim',
        'tim'            => 'Tim',
    ];

    /** Keterangan bagi Admin yang membuka akunnya sendiri, juga pesan penolakan di server. */
    public const PESAN_AKUN_SENDIRI = 'Anda tidak dapat menonaktifkan atau mengubah peran akun Anda sendiri.';

    /** Akun yang sedang dibuka adalah akun pengguna yang masuk. */
    protected static function akunSendiri(?Model $record): bool
    {
        return $record !== null && $record->is(auth()->user());
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(30),
                        TextInput::make('no_hp')
                            ->label('No. HP')
                            ->tel()
                            ->maxLength(20),
                    ]),

                Section::make('Akun & Peran')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->revealable()
                            // Wajib saat membuat akun baru; saat mengubah, biarkan kosong
                            // untuk mempertahankan kata sandi lama.
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Kosongkan bila tidak ingin mengubah kata sandi.')
                            ->maxLength(255),
                        Select::make('role')
                            ->label('Peran')
                            ->options(self::ROLE_OPTIONS)
                            ->required()
                            ->native(false)
                            ->live()
                            // Admin tidak dapat mengunci dirinya sendiri: kolom tampil
                            // tetapi tidak dapat diubah, dan EditUser menolak muatan
                            // yang dimodifikasi.
                            ->disabled(fn (?Model $record): bool => static::akunSendiri($record))
                            ->dehydrated(fn (?Model $record): bool => ! static::akunSendiri($record))
                            ->helperText(fn (?Model $record): ?string => static::akunSendiri($record) ? self::PESAN_AKUN_SENDIRI : null),
                        Select::make('tim_id')
                            ->label('Tim Kerja')
                            ->relationship('tim', 'nama_tim')
                            ->searchable()
                            ->preload()
                            // Hanya peran berbasis tim yang wajib terhubung ke tim.
                            ->required(fn (Get $get): bool => in_array($get('role'), ['tim', 'ketua_tim']))
                            ->helperText('Wajib untuk peran Tim dan Ketua Tim.'),
                        Toggle::make('status_aktif')
                            ->label('Akun Aktif')
                            ->default(true)
                            ->inline(false)
                            ->disabled(fn (?Model $record): bool => static::akunSendiri($record))
                            ->dehydrated(fn (?Model $record): bool => ! static::akunSendiri($record))
                            ->helperText(fn (?Model $record): ?string => static::akunSendiri($record) ? self::PESAN_AKUN_SENDIRI : null),
                    ]),
            ]);
    }
}
