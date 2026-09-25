<?php

namespace App\Filament\Resources\Kategoris\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class KategoriForm
{
    /** Dipakai aturan form dan jaring pengaman di halaman Buat/Ubah, agar pesannya satu. */
    public const PESAN_KODE_DIPAKAI = 'Kode kategori sudah dipakai kategori lain.';

    /**
     * Kode akun hanya keterangan: tidak dipakai stok, kartu kendali, maupun
     * dokumen mana pun (lihat docs/audit/rencana-data-induk-persediaan.md,
     * bagian A). Kalimat bantuannya mengatakan itu, supaya pengisi tidak
     * menyangka salah isi di sini akan mengacaukan laporan.
     */
    public const BANTUAN_KODE_AKUN = 'Kode akun neraca (Bagan Akun Standar) tempat kategori ini dilaporkan, '
        . 'mis. 117111 untuk Barang Konsumsi. Hanya keterangan: kode ini tidak memengaruhi stok, '
        . 'kartu kendali, maupun dokumen.';

    /** Dipakai juga oleh ImporKategori agar form dan impor tidak berbeda ukuran. */
    public const POLA_KODE_AKUN_PERSEDIAAN = '/^\d{6}$/';

    public const PESAN_KODE_AKUN_PERSEDIAAN = 'Kode akun kategori persediaan harus tepat 6 digit angka (mis. 117111).';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_kategori')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(100)
                    ->columnSpanFull(),
                TextInput::make('kode_kategori')
                    ->label('Kode Kategori')
                    ->required()
                    ->maxLength(20)
                    // Dipangkas lebih dulu karena pembanding unik MySQL mengabaikan
                    // spasi ujung sedangkan SQLite tidak (alasan yang sama dengan
                    // kode barang pada T-2).
                    ->trim()
                    // Indeks unik kode_kategori sudah ada di basis data; tanpa
                    // aturan ini kode ganda baru ketahuan sebagai galat basis data
                    // mentah saat menyimpan.
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => self::PESAN_KODE_DIPAKAI]),
                TextInput::make('kode_akun')
                    ->label('Kode Akun')
                    ->placeholder('117111')
                    ->helperText(self::BANTUAN_KODE_AKUN)
                    ->required()
                    ->maxLength(10)
                    // Kategori persediaan: tepat enam digit, bentuk kode akun BAS
                    // yang dipakai SAKTI (117111). Kategori aset tetap dibiarkan
                    // bebas karena data berjalannya memakai notasi bertitik
                    // (1.3.2) — keputusan pemilik G-2, opsi A-1.
                    ->rule('regex:' . self::POLA_KODE_AKUN_PERSEDIAAN, fn (Get $get): bool => $get('tipe') === 'persediaan')
                    ->validationMessages(['regex' => self::PESAN_KODE_AKUN_PERSEDIAAN]),
                Select::make('tipe')
                    ->label('Tipe')
                    ->options(['persediaan' => 'Persediaan', 'aset_tetap' => 'Aset Tetap'])
                    ->native(false)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
