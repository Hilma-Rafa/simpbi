<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Pengaturan pengguna dan sistem.
 *
 * Halaman ini tidak didaftarkan pada menu samping; jalan masuknya melalui
 * menu profil di pojok kanan atas, sesuai kebutuhan agar konfigurasi tidak
 * bercampur dengan menu operasional.
 *
 * Isinya menyesuaikan peran. Seluruh peran dapat memperbarui data akunnya
 * sendiri, sedangkan bagian Pengaturan Sistem — batas waktu tiap tahap
 * persetujuan dan kanal notifikasi — hanya tampil dan hanya dapat disimpan
 * oleh Admin Sistem, mengikuti kewenangan yang sudah berlaku pada
 * UserResource. Penjagaannya dilakukan dua kali: bagian formulir disembunyikan,
 * dan nilainya diabaikan pada saat penyimpanan.
 *
 * Nilai batas waktu dibaca dari tabel pengaturan yang memang disiapkan untuk
 * itu (Instruksi §52), bukan dari kolom baru.
 */
class Pengaturan extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.pengaturan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Pengaturan';

    /** Jalan masuknya melalui menu profil, bukan menu samping. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /** Kunci pengaturan sistem beserta labelnya, sesuai isi tabel pengaturan. */
    protected const KUNCI_SISTEM = [
        'batas_ketua_jam'       => 'Batas persetujuan Ketua Tim (jam)',
        'batas_verifikasi_jam'  => 'Batas verifikasi stok fisik (jam)',
        'batas_kasubbag_jam'    => 'Batas persetujuan akhir Kasubbag (jam)',
        'batas_penyiapan_jam'   => 'Batas penyiapan barang (jam)',
        'batas_pengambilan_jam' => 'Batas pengambilan oleh pemohon (jam)',
    ];

    public static function bolehMengaturSistem(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $pengguna = auth()->user();

        $awal = [
            'name'   => $pengguna->name,
            'email'  => $pengguna->email,
            'nip'    => $pengguna->nip,
            'no_hp'  => $pengguna->no_hp,
        ];

        if (static::bolehMengaturSistem()) {
            $nilai = DB::table('pengaturan')->pluck('nilai', 'kunci');

            foreach (array_keys(self::KUNCI_SISTEM) as $kunci) {
                $awal[$kunci] = $nilai[$kunci] ?? null;
            }

            $awal['wa_aktif'] = ($nilai['wa_aktif'] ?? '0') === '1';
        }

        $this->form->fill($awal);
    }

    public function form(Schema $schema): Schema
    {
        $pengguna = auth()->user();

        return $schema
            ->components([
                Section::make('Akun Saya')
                    ->description('Data diri yang dipakai pada dokumen dan notifikasi sistem.')
                    ->icon('heroicon-m-user-circle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->maxLength(150)
                            ->unique('users', 'email', ignorable: $pengguna),

                        TextInput::make('nip')
                            ->label('NIP')
                            ->maxLength(30)
                            ->helperText('Dicantumkan pada dokumen bukti permintaan.'),

                        TextInput::make('no_hp')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Dipakai bila notifikasi WhatsApp diaktifkan Administrator.'),
                    ]),

                Section::make('Ubah Kata Sandi')
                    ->description('Kosongkan bila kata sandi tidak diubah.')
                    ->icon('heroicon-m-key')
                    ->columns(2)
                    ->schema([
                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->live(debounce: 500)
                            ->same('password_confirmation'),

                        TextInput::make('password_confirmation')
                            ->label('Ulangi Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->requiredWith('password')
                            ->dehydrated(false),
                    ]),

                Section::make('Pengaturan Sistem')
                    ->description('Batas waktu tiap tahap alur permintaan dan kanal notifikasi. Berlaku bagi seluruh pengguna.')
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->columns(2)
                    // Hanya Admin Sistem yang berwenang atas konfigurasi ini
                    ->visible(fn () => static::bolehMengaturSistem())
                    ->schema([
                        ...collect(self::KUNCI_SISTEM)
                            ->map(fn (string $label, string $kunci) => TextInput::make($kunci)
                                ->label($label)
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(720)
                                ->required()
                                ->suffix('jam'))
                            ->values()
                            ->all(),

                        Toggle::make('wa_aktif')
                            ->label('Aktifkan notifikasi WhatsApp')
                            ->helperText('Bila nonaktif, notifikasi hanya dikirim di dalam aplikasi.')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('simpan')
                ->label('Simpan Perubahan')
                ->submit('simpan'),
        ];
    }

    public function simpan(): void
    {
        $data     = $this->form->getState();
        $pengguna = auth()->user();

        DB::transaction(function () use ($data, $pengguna) {
            $pengguna->fill([
                'name'  => $data['name'],
                'email' => $data['email'],
                'nip'   => $data['nip'] ?: null,
                'no_hp' => $data['no_hp'] ?: null,
            ]);

            if (filled($data['password'] ?? null)) {
                // Kolom password memakai cast "hashed", sehingga tidak dihash ganda
                $pengguna->password = $data['password'];
            }

            $pengguna->save();

            // Penjagaan kedua: nilai pengaturan sistem hanya diterima dari Admin
            if (! static::bolehMengaturSistem()) {
                return;
            }

            foreach (array_keys(self::KUNCI_SISTEM) as $kunci) {
                DB::table('pengaturan')
                    ->where('kunci', $kunci)
                    ->update(['nilai' => (string) (int) $data[$kunci], 'updated_at' => now()]);
            }

            DB::table('pengaturan')
                ->where('kunci', 'wa_aktif')
                ->update(['nilai' => $data['wa_aktif'] ? '1' : '0', 'updated_at' => now()]);
        });

        // Kata sandi tidak perlu tertinggal pada state formulir
        $this->form->fill(array_merge($data, [
            'password'              => null,
            'password_confirmation' => null,
        ]));

        Notification::make()
            ->title('Pengaturan tersimpan')
            ->success()
            ->send();
    }
}
