<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                            ->live(),
                        Select::make('tim_id')
                            ->label('Unit Kerja / Tim')
                            ->relationship('tim', 'nama_tim')
                            ->searchable()
                            ->preload()
                            // Hanya peran berbasis tim yang wajib terhubung ke tim.
                            ->required(fn (Get $get): bool => in_array($get('role'), ['tim', 'ketua_tim']))
                            ->helperText('Wajib untuk peran Tim dan Ketua Tim.'),
                        Toggle::make('status_aktif')
                            ->label('Akun Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
