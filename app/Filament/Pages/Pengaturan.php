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
use App\Filament\Forms\Components\KanvasTandaTangan;
use App\Support\NomorWhatsApp;
use App\Support\PengalihanWhatsApp;
use App\Support\TandaTangan;
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

    /**
     * Kunci pengaturan sistem beserta labelnya, sesuai isi tabel pengaturan.
     *
     * Satuannya jam kerja, bukan jam dinding — delapan berarti satu hari kerja
     * penuh. Satuan itu disebutkan pada label supaya Administrator tidak
     * mengira nilai 24 berarti sehari semalam, padahal artinya tiga hari kerja.
     */
    protected const KUNCI_SISTEM = [
        'batas_ketua_jam'       => 'Batas persetujuan Ketua Tim (jam kerja)',
        'batas_verifikasi_jam'  => 'Batas verifikasi stok fisik (jam kerja)',
        'batas_kasubbag_jam'    => 'Batas persetujuan akhir Kasubbag (jam kerja)',
        'batas_penyiapan_jam'   => 'Batas penyiapan barang (jam kerja)',
        'batas_pengambilan_jam' => 'Batas pengambilan oleh pemohon (jam kerja)',
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

            // Kontak bantuan bukan angka seperti batas tahapan, sehingga
            // diambil terpisah dari perulangan KUNCI_SISTEM di atas.
            $awal['kontak_bantuan_wa'] = $nilai['kontak_bantuan_wa'] ?? null;
            $awal['wa_alihkan_ke']     = $nilai['wa_alihkan_ke'] ?? null;
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

                        /*
                         * Tanda tangan hanya ditawarkan kepada peran yang
                         * memang membubuhkannya pada dokumen bukti. Peran lain
                         * tidak perlu dibebani kolom yang tidak akan pernah
                         * dipakai, sekaligus mengurangi jumlah tanda tangan
                         * pegawai yang tersimpan tanpa keperluan.
                         *
                         * Ketua Tim dapat menyimpannya di sini lebih dulu, dan
                         * itu bukan kemudahan belaka: ketika penerimaan barang
                         * dikonfirmasi anggota timnya, dokumen tetap terbit
                         * atas nama Ketua Tim, sehingga tanda tangan yang
                         * dibubuhkan haruslah yang sudah tersimpan di sini.
                         */
                        KanvasTandaTangan::make('tanda_tangan')
                            ->label('Tanda Tangan')
                            ->tandaTanganTersimpan(fn (): ?string => TandaTangan::dataUri($pengguna))
                            ->helperText(fn (): string => TandaTangan::tersedia($pengguna)
                                ? 'Tanda tangan ini dibubuhkan pada dokumen bukti permintaan. Tekan "Gambar Ulang" bila hendak menggantinya.'
                                : 'Belum ada tanda tangan tersimpan. Bubuhkan sekali di sini, lalu dipakai pada setiap dokumen bukti permintaan.')
                            ->visible(fn (): bool => in_array($pengguna->role, ['petugas_gudang', 'ketua_tim', 'kasubbag']))
                            ->columnSpanFull(),
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
                                ->suffix('jam kerja')
                                ->helperText('8 jam kerja = 1 hari kerja (08.00-16.00, hari kerja saja)'))
                            ->values()
                            ->all(),

                        Toggle::make('wa_aktif')
                            ->label('Aktifkan notifikasi WhatsApp')
                            ->helperText('Bila nonaktif, notifikasi hanya dikirim di dalam aplikasi.')
                            ->columnSpanFull(),

                        // SIMPBI sengaja tidak menyediakan pemulihan kata sandi
                        // mandiri, sehingga nomor inilah satu-satunya jalan
                        // keluar pengguna yang terkunci di halaman masuk.
                        TextInput::make('kontak_bantuan_wa')
                            ->label('Nomor WhatsApp bantuan masuk')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('08xxxxxxxxxx')
                            ->helperText('Ditampilkan sebagai tautan pada halaman masuk. Kosongkan bila belum ada nomor kedinasan — kaki halaman masuk akan kembali tanpa tautan.')
                            ->columnSpanFull(),

                        // Ditempatkan paling bawah karena sifatnya sementara:
                        // dipakai saat sistem diperagakan, lalu dikosongkan
                        // kembali. Keterangannya menyebut keadaan yang sedang
                        // berlaku, bukan sekadar menjelaskan kolomnya, supaya
                        // pengalihan yang tertinggal menyala segera ketahuan.
                        TextInput::make('wa_alihkan_ke')
                            ->label('Alihkan semua notifikasi ke satu nomor (mode peragaan)')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('Kosongkan untuk pengiriman normal')
                            ->helperText(fn (): string => PengalihanWhatsApp::menyala()
                                ? 'SEDANG MENYALA. Seluruh notifikasi WhatsApp dikirim ke nomor ini, '
                                    . 'termasuk milik Ketua Tim, dan nomor pada akun pegawai tidak dipakai. '
                                    . 'Kosongkan kolom ini setelah peragaan selesai.'
                                : 'Selama terisi, seluruh notifikasi WhatsApp dibelokkan ke nomor ini alih-alih '
                                    . 'ke nomor pegawai. Berguna untuk peragaan, agar alur dapat dicoba tanpa '
                                    . 'mengirim pesan kepada Ketua Tim yang sebenarnya.')
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

            /*
             * Kolom ini bernilai null selama pengguna tidak menggambar apa pun,
             * dan null memang berarti "tidak ada yang berubah" — bukan perintah
             * menghapus. Tanda tangan yang sudah tersimpan karena itu tidak
             * ikut terhapus hanya karena formulir disimpan untuk urusan lain,
             * misalnya mengganti kata sandi.
             */
            if (filled($data['tanda_tangan'] ?? null)) {
                TandaTangan::simpan($pengguna, $data['tanda_tangan']);
            }

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

            // Disimpan sudah dalam bentuk seragam, bukan apa adanya, supaya
            // halaman masuk tidak perlu menebak bentuk nomor yang diketik dan
            // Administrator langsung melihat hasil bacaan sistem saat kembali
            // ke halaman ini.
            DB::table('pengaturan')
                ->where('kunci', 'kontak_bantuan_wa')
                ->update([
                    'nilai'      => NomorWhatsApp::normalkan($data['kontak_bantuan_wa'] ?? null) ?? '',
                    'updated_at' => now(),
                ]);

            // Diseragamkan dengan aturan yang sama, sebab nilainya dibandingkan
            // dan dipakai langsung sebagai nomor tujuan oleh job pengiriman.
            DB::table('pengaturan')
                ->where('kunci', 'wa_alihkan_ke')
                ->update([
                    'nilai'      => NomorWhatsApp::normalkan($data['wa_alihkan_ke'] ?? null) ?? '',
                    'updated_at' => now(),
                ]);
        });

        // Kata sandi tidak perlu tertinggal pada state formulir
        $this->form->fill(array_merge($data, [
            'password'              => null,
            'password_confirmation' => null,
            // Kanvas dikembalikan ke keadaan "tidak ada goresan baru", supaya
            // penyimpanan berikutnya tidak menulis ulang gambar yang sama.
            'tanda_tangan'          => null,
        ]));

        Notification::make()
            ->title('Pengaturan tersimpan')
            ->success()
            ->send();
    }
}
