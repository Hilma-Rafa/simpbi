<?php

namespace App\Filament\Resources\BarangPersediaans\Schemas;

use App\Support\KodeBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class BarangPersediaanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Barang')
                    ->columns(2)
                    ->schema([
                        Select::make('kategori_id')
                            ->label('Kategori')
                            // Hanya kategori persediaan, sama dengan dialog Barang Baru di
                            // Stok Masuk: kategori aset tetap (mis. Peralatan dan Mesin)
                            // tidak menaungi barang persediaan, dan barang yang tersimpan
                            // di sana tidak muncul pada ringkasan stok per kategori.
                            ->relationship(
                                'kategori',
                                'nama_kategori',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('tipe', 'persediaan'),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('kode_barang')
                            ->label('Kode Barang')
                            ->required()
                            // Enam digit dari kartu kendali persediaan (KodeBarang). Masker dan
                            // papan angka mencegah salah ketik sejak di peramban; aturan regex
                            // tetap menjaga di peladen. Sengaja bukan numeric(): isian angka
                            // membuang nol di depan, padahal 000122 dan 122 adalah kode berbeda.
                            ->mask('999999')
                            ->inputMode('numeric')
                            ->placeholder(KodeBarang::CONTOH)
                            ->helperText(KodeBarang::BANTUAN)
                            ->regex(KodeBarang::POLA)
                            // Bentuk diperiksa sebelum keunikan: kode 12345 cukup dijawab
                            // "harus 6 digit", bukan ikut dicocokkan dengan kode lain.
                            ->rule('bail')
                            // Dipangkas dulu, baru dibandingkan: MySQL mengabaikan spasi
                            // ujung pada pembanding unik sedangkan SQLite tidak, sehingga
                            // tanpa ini hasil pemeriksaan bergantung pada driver.
                            ->trim()
                            // Kode hanya unik di dalam kategorinya (indeks unik
                            // kategori_id + kode_barang), sama dengan aturan dialog
                            // Barang Baru di Stok Masuk; tanpa aturan ini bentrok baru
                            // ketahuan sebagai galat basis data saat menyimpan (T-2).
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('kategori_id', $get('kategori_id')),
                            )
                            ->validationMessages([
                                'regex'  => KodeBarang::PESAN,
                                'unique' => 'Kode barang sudah dipakai pada kategori ini.',
                            ]),
                        TextInput::make('nama_barang')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('satuan')
                            ->label('Satuan')
                            ->placeholder('mis. Rim, Box, Buah')
                            ->required()
                            ->maxLength(20),
                    ]),

                Section::make('Stok')
                    ->columns(3)
                    ->schema([
                        TextInput::make('stok_fisik')
                            ->label('Stok Fisik')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('stok_hold')
                            ->label('Stok Terkunci')
                            ->helperText('Dikelola otomatis oleh sistem melalui mekanisme HOLD.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            // Hanya tampil: kunci stok dikelola mekanisme HOLD. Barang baru
                            // memakai nilai bawaan kolom (0).
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('stok_minimum')
                            ->label('Stok Minimum')
                            ->helperText('Isi 0 bila barang tidak dipantau terhadap stok minimum.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('status_aktif')
                            ->label('Barang Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
