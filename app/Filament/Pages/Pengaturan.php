<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\KanvasTandaTangan;
use App\Support\JamKerja;
use App\Support\NomorWhatsApp;
use App\Support\PengalihanWhatsApp;
use App\Support\TandaTangan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
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
class Pengaturan extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
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
     * Kartu "Ubah Kata Sandi" tertutup sampai tombolnya ditekan, supaya tiga
     * kolom kata sandi tidak membebani formulir bagi pengguna yang hanya
     * hendak memperbarui data akunnya. Bukan bagian dari $data: keadaan
     * buka/tutup ini murni tampilan, tidak pernah disimpan.
     */
    public bool $sedangUbahSandi = false;

    /**
     * Cap sidik data formulir saat terakhir dimuat/disimpan, dipakai bar
     * "Ada perubahan yang belum disimpan" di sisi klien untuk membandingkan
     * dengan sidik data yang sedang berjalan. Rumus dan bahan bakunya (JSON
     * dari $data, digenapkan md5) sama persis dengan yang sudah dipakai
     * Filament sendiri pada peringatan perubahan belum tersimpan bawaannya
     * (Filament\Pages\Concerns\HasUnsavedDataChangesAlert) — dipakai ulang di
     * sini, bukan ditulis ulang sebagai mekanisme baru, dan window.jsMd5 yang
     * dipanggil di sisi klien sudah termuat pada setiap halaman panel karena
     * Filament sendiri membundelnya.
     */
    public string $savedDataHash = '';

    /**
     * Kunci pengaturan sistem beserta labelnya, sesuai isi tabel pengaturan.
     *
     * Satuannya jam kerja, bukan jam dinding — delapan berarti satu hari kerja
     * penuh. Satuan itu disebutkan pada label supaya Administrator tidak
     * mengira nilai 24 berarti sehari semalam, padahal artinya tiga hari kerja.
     */
    protected const KUNCI_SISTEM = [
        'batas_ketua_jam'       => 'Persetujuan Ketua Tim',
        'batas_verifikasi_jam'  => 'Verifikasi stok fisik',
        'batas_kasubbag_jam'    => 'Persetujuan akhir Kasubbag',
        'batas_penyiapan_jam'   => 'Penyiapan barang',
        'batas_pengambilan_jam' => 'Pengambilan oleh pemohon',
    ];

    public static function bolehMengaturSistem(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    /** Banyaknya hari kerja penuh yang terkandung pada sejumlah jam kerja. */
    public static function keHariKerja(int|float $jam): string
    {
        $hari = $jam / JamKerja::JAM_PER_HARI;

        // Ditulis satu angka desimal hanya bila memang tidak genap, supaya
        // "16 jam kerja" terbaca "2 hari kerja", bukan "2.0 hari kerja".
        return rtrim(rtrim(number_format($hari, 1, ',', ''), '0'), ',');
    }

    /**
     * Jumlah jam kerja seluruh tahap alur, dibaca dari nilai formulir yang
     * sedang berjalan — bukan dari basis data — sehingga kartu Batas Waktu
     * Alur dan panel Ringkasan Alur pada sisi tetap melaporkan angka yang
     * sama persis begitu Administrator mengubah salah satu tahapnya, bahkan
     * sebelum disimpan.
     */
    public function totalJamAlur(): float
    {
        return collect(array_keys(self::KUNCI_SISTEM))
            ->sum(fn (string $kunci) => (float) ($this->data[$kunci] ?? 0));
    }

    /**
     * Inisial nama untuk avatar lokal (A-019): huruf pertama tiap kata, tanda
     * baca di awal kata dilewati, paling banyak dua huruf ("Administrator
     * Sistem" → "AS"). Logikanya sama dengan penyedia avatar bawaan Filament
     * (UiAvatarsProvider), tetapi tanpa permintaan ke pihak ketiga.
     */
    public static function inisial(string $nama): string
    {
        $inisial = str($nama)
            ->trim()
            ->explode(' ')
            ->map(function (string $bagian): string {
                $huruf = preg_replace('/^[^\p{L}\p{N}]+/u', '', $bagian);

                return filled($huruf) ? mb_substr($huruf, 0, 1) : '';
            })
            ->join('');

        return mb_strtoupper(mb_substr($inisial, 0, 2));
    }

    /** Sebutan peran pengguna, sumbernya sama dengan subjudul Dasbor. */
    public function labelPeran(): string
    {
        return \App\Filament\Resources\Users\Schemas\UserForm::ROLE_OPTIONS[auth()->user()?->role] ?? '—';
    }

    /** Banyaknya hari kerja maksimal seluruh tahap alur, untuk panel Ringkasan Alur. */
    public function totalHariAlur(): string
    {
        return self::keHariKerja($this->totalJamAlur());
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
        $this->rememberDataHash();
    }

    protected function rememberDataHash(): void
    {
        $this->savedDataHash = md5((string) str(json_encode($this->data, JSON_UNESCAPED_UNICODE))->replace('\\', ''));
    }

    public function form(Schema $schema): Schema
    {
        $pengguna = auth()->user();

        return $schema
            ->components([
                Section::make('Akun Saya')
                    ->description('Data diri yang dipakai pada dokumen dan notifikasi sistem.')
                    ->icon('heroicon-m-user-circle')
                    // Dua kolom sama lebar di semua breakpoint ≥ sm: baris 1
                    // Nama/Email, baris 2 NIP/Nomor WhatsApp selebar kolom di
                    // atasnya — bukan lagi seperempat lebar kartu, supaya NIP
                    // 18 digit tidak terpotong.
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        // Nama dan NIP tercetak pada dokumen resmi, sehingga hanya
                        // Administrator yang mengubahnya, lewat menu Pengguna.
                        // Tidak ikut disimpan dari formulir ini untuk peran apa pun.
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Diubah oleh Administrator melalui menu Pengguna.'),

                        // Email adalah kredensial masuk: seperti nama dan NIP, hanya
                        // Administrator yang mengubahnya, lewat menu Pengguna.
                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Diubah oleh Administrator melalui menu Pengguna.'),

                        TextInput::make('nip')
                            ->label('NIP')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Diubah oleh Administrator melalui menu Pengguna.'),

                        TextInput::make('no_hp')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('08xxxxxxxxxx')
                            /*
                             * Prefix murni presentasi (dekorasi Filament di
                             * luar nilai yang tersimpan), ditampilkan hanya
                             * saat kolom masih kosong — tidak pernah
                             * menyentuh atau menata ulang nilai yang sudah
                             * ada. Kolom ini disimpan apa adanya seperti
                             * ketikan pengguna (tidak melalui normalisasi
                             * 62xxx seperti kontak_bantuan_wa/wa_alihkan_ke),
                             * sehingga prefix ini hanya petunjuk format yang
                             * diharapkan, bukan jaminan nilai tersimpan
                             * selalu berawalan 62.
                             */
                            ->prefix(fn (?string $state): ?string => blank($state) ? '+62' : null)
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
                            // Kasubbag tidak diikutkan: pengesahannya memakai
                            // e-TTD (nama + kode QR), bukan gambar tanda tangan,
                            // sehingga kolom ini tak pernah terpakai olehnya.
                            ->visible(fn (): bool => in_array($pengguna->role, ['petugas_gudang', 'ketua_tim']))
                            ->columnSpanFull(),
                    ]),

                Section::make('Ubah Kata Sandi')
                    ->description('Kosongkan bila kata sandi tidak diubah.')
                    ->icon('heroicon-m-key')
                    ->schema([
                        Actions::make([
                            Action::make('bukaUbahSandi')
                                ->label('Ubah Kata Sandi')
                                ->icon('heroicon-m-key')
                                ->color('gray')
                                ->outlined()
                                ->action(fn () => $this->sedangUbahSandi = true),
                        ])->visible(fn (): bool => ! $this->sedangUbahSandi),

                        TextInput::make('current_password')
                            ->label('Kata Sandi Saat Ini')
                            ->password()
                            ->revealable()
                            ->autocomplete('current-password')
                            ->requiredWith('password')
                            // Diperiksa hanya ketika Kata Sandi Baru benar-benar
                            // diisi, sehingga mengetik nomor yang keliru di sini
                            // tidak ikut menggagalkan penyimpanan bagian lain
                            // formulir bila pengguna urung mengganti sandinya.
                            ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($pengguna, $get): void {
                                if (filled($get('password')) && ! Hash::check((string) $value, $pengguna->password)) {
                                    $fail('Kata sandi saat ini tidak sesuai.');
                                }
                            })
                            ->visible(fn (): bool => $this->sedangUbahSandi),

                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->rule(fn (): \Closure => GantiKataSandi::aturanBerbedaDariSaatIni())
                            ->autocomplete('new-password')
                            ->live(debounce: 500)
                            ->same('password_confirmation')
                            /*
                             * Checklist ini hanya membaca ulang aturan yang
                             * sudah berlaku pada Password::default() di atas —
                             * satu-satunya syarat yang aktif adalah panjang
                             * minimal, sesuai pemeriksaan yang sudah dilakukan
                             * sebelum penyuntingan ini. Bukan indikator
                             * kekuatan kata sandi: hanya menandai terpenuhi
                             * atau belum, tanpa tingkatan lemah/sedang/kuat.
                             */
                            ->helperText(fn (?string $state): HtmlString => new HtmlString(
                                '<span class="inline-flex items-center gap-1 ' .
                                    (strlen((string) $state) >= 8 ? 'text-success-600 dark:text-success-400' : 'text-gray-500 dark:text-gray-400') .
                                    '">' .
                                    (strlen((string) $state) >= 8 ? '✓' : '○') .
                                    ' Minimal 8 karakter</span>'
                            ))
                            ->visible(fn (): bool => $this->sedangUbahSandi),

                        TextInput::make('password_confirmation')
                            ->label('Ulangi Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->requiredWith('password')
                            ->dehydrated(false)
                            ->visible(fn (): bool => $this->sedangUbahSandi),
                    ]),

                Section::make('Batas Waktu Alur')
                    ->description('8 jam kerja = 1 hari kerja (08.00–16.00, hari kerja saja).')
                    ->icon('heroicon-m-clock')
                    // Hanya Admin Sistem yang berwenang atas konfigurasi ini
                    ->visible(fn () => static::bolehMengaturSistem())
                    ->schema([
                        ...collect(self::KUNCI_SISTEM)
                            ->values()
                            ->map(function (string $nama, int $urutan): Flex {
                                $kunci = array_keys(self::KUNCI_SISTEM)[$urutan];

                                /*
                                 * Baris tiga bagian: label (melebar, minimal
                                 * ~300px di desktop) — isian angka (lebar
                                 * tetap 96px, rata tengah) — konversi hari
                                 * (di LUAR kotak isian, bukan suffix yang
                                 * ikut melebar bersama kotaknya). Tanda wajib
                                 * ditulis manual di sini karena labelnya kini
                                 * komponen teks biasa, bukan label bawaan
                                 * TextInput (disembunyikan via hiddenLabel()
                                 * — required() pada TextInput tetap berlaku,
                                 * hanya tampilannya yang berpindah).
                                 */
                                return Flex::make([
                                    Text::make(new HtmlString(
                                        ($urutan + 1) . '. ' . e($nama) . ' <span class="text-danger-600 dark:text-danger-400">*</span>'
                                    ))
                                        ->grow()
                                        ->extraAttributes(['class' => 'sm:min-w-[18.75rem]']),

                                    Flex::make([
                                        TextInput::make($kunci)
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(720)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->extraInputAttributes(['class' => 'w-24 text-center']),

                                        Text::make(function (Get $get) use ($kunci): string {
                                            $nilai = $get($kunci);

                                            return 'jam kerja'
                                                . (filled($nilai) && is_numeric($nilai)
                                                    ? ' = ' . self::keHariKerja((float) $nilai) . ' hari kerja'
                                                    : '');
                                        })
                                            ->color('gray'),
                                    ])->verticalAlignment('center'),
                                ])
                                    ->from('sm')
                                    ->verticalAlignment('center');
                            })
                            ->all(),

                        Text::make(fn (): HtmlString => new HtmlString(
                            'Total maksimal alur: <strong class="text-gray-900 dark:text-white">'
                            . (int) $this->totalJamAlur() . ' jam kerja ('
                            . self::keHariKerja($this->totalJamAlur()) . ' hari kerja)</strong>'
                        ))
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),

                Section::make('Notifikasi WhatsApp')
                    ->afterHeader(fn (): ?HtmlString => filled($this->data['wa_alihkan_ke'] ?? null)
                        ? new HtmlString(Blade::render('<x-filament::badge color="warning">Mode peragaan aktif</x-filament::badge>'))
                        : null)
                    ->description('Kanal notifikasi WhatsApp dan mode peragaan untuk simulasi tanpa mengirim ke pegawai sungguhan.')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    // Dua kolom sama lebar, sama seperti Akun Saya — kotak
                    // isian sebelumnya dibatasi max-w-xs di dalam kolom
                    // selebar kartu penuh, menyisakan ruang kosong di
                    // sampingnya pada layar lebar.
                    ->columns(['default' => 1, 'sm' => 2])
                    // Hanya Admin Sistem yang berwenang atas konfigurasi ini
                    ->visible(fn () => static::bolehMengaturSistem())
                    ->schema([
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
                            ->helperText('Ditampilkan sebagai tautan pada halaman masuk. Kosongkan bila belum ada nomor kedinasan — kaki halaman masuk akan kembali tanpa tautan.'),

                        // Ditempatkan paling bawah karena sifatnya sementara:
                        // dipakai saat sistem diperagakan, lalu dikosongkan
                        // kembali. Keterangannya menyebut keadaan yang sedang
                        // berlaku, bukan sekadar menjelaskan kolomnya, supaya
                        // pengalihan yang tertinggal menyala segera ketahuan.
                        TextInput::make('wa_alihkan_ke')
                            ->label('(mode peragaan)')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('Kosongkan untuk pengiriman normal')
                            ->live()
                            ->helperText(fn (): string => PengalihanWhatsApp::menyala()
                                ? 'SEDANG MENYALA. Seluruh notifikasi WhatsApp dikirim ke nomor ini, '
                                    . 'termasuk milik Ketua Tim, dan nomor pada akun pegawai tidak dipakai. '
                                    . 'Kosongkan kolom ini setelah peragaan selesai.'
                                : 'Selama terisi, seluruh notifikasi WhatsApp dibelokkan ke nomor ini alih-alih '
                                    . 'ke nomor pegawai. Berguna untuk peragaan, agar alur dapat dicoba tanpa '
                                    . 'mengirim pesan kepada Ketua Tim yang sebenarnya.'),

                        Callout::make('Mode peragaan sedang menyala')
                            ->description(
                                'Seluruh notifikasi WhatsApp dialihkan ke satu nomor ini, termasuk notifikasi '
                                . 'Ketua Tim, dan nomor pada akun pegawai tidak digunakan selama kolom ini terisi.'
                            )
                            ->warning()
                            ->visible(fn (Get $get): bool => filled($get('wa_alihkan_ke')))
                            ->columnSpanFull(),

                        \Filament\Schemas\Components\Text::make(function (Get $get): ?HtmlString {
                            $bantuan  = NomorWhatsApp::normalkan($get('kontak_bantuan_wa'));
                            $peragaan = NomorWhatsApp::normalkan($get('wa_alihkan_ke'));

                            if ($bantuan === null || $peragaan === null || $bantuan !== $peragaan) {
                                return null;
                            }

                            return new HtmlString(
                                'Nomor ini sama dengan nomor WhatsApp bantuan masuk di atas.'
                            );
                        })
                            ->color('gray')
                            ->visible(fn (Get $get): bool => filled($get('wa_alihkan_ke')))
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Aksi "Simpan Perubahan" yang dipakai bar bawah. Pemrosesannya tetap
     * satu jalur — memanggil simpan() yang sama seperti sebelumnya — dialog
     * konfirmasi hanya ditambahkan di depannya ketika perubahan yang hendak
     * disimpan mengaktifkan mode peragaan atau mengubah nomor pengalihannya.
     * Mematikan mode peragaan tidak memerlukan konfirmasi.
     */
    public function simpanAction(): Action
    {
        return Action::make('simpan')
            ->label('Simpan Perubahan')
            ->action(fn () => $this->simpan())
            ->requiresConfirmation(fn (): bool => $this->perluKonfirmasiPeragaan())
            // Teks dialog di bawah membuat Filament membuka dialog walau
            // konfirmasi tidak diperlukan; modal() memutuskannya tegas.
            ->modal(fn (): bool => $this->perluKonfirmasiPeragaan())
            ->modalHeading(fn (): string => PengalihanWhatsApp::menyala()
                ? 'Ubah nomor mode peragaan?'
                : 'Aktifkan mode peragaan?')
            ->modalDescription(
                'Seluruh notifikasi WhatsApp akan dialihkan ke nomor yang baru saja diisi, termasuk '
                . 'notifikasi Ketua Tim, dan nomor pada akun pegawai tidak akan dipakai selama nomor ini '
                . 'terisi. Kosongkan kembali kolomnya setelah peragaan selesai.'
            )
            ->modalSubmitActionLabel('Ya, Simpan');
    }

    /**
     * Apakah penyimpanan ini mengaktifkan mode peragaan atau mengubah nomornya.
     *
     * Pembandingnya nomor yang TERSIMPAN, dibaca dari basis data, bukan nilai
     * yang diingat komponen: properti protected Livewire tidak bertahan dari
     * satu permintaan ke permintaan berikutnya, sehingga nomor awal yang
     * diingat itu selalu kosong ketika tombol ditekan.
     */
    protected function perluKonfirmasiPeragaan(): bool
    {
        if (! static::bolehMengaturSistem()) {
            return false;
        }

        $baru = NomorWhatsApp::normalkan($this->data['wa_alihkan_ke'] ?? null);

        return $baru !== null && $baru !== PengalihanWhatsApp::nomor();
    }

    /** Mengembalikan formulir ke kondisi terakhir tersimpan. */
    public function batal(): void
    {
        $this->mount();
        $this->sedangUbahSandi = false;
    }

    public function simpan(): void
    {
        $data     = $this->form->getState();
        $pengguna = auth()->user();

        DB::transaction(function () use ($data, $pengguna) {
            $pengguna->fill([
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

        // Sesudah kata sandi berganti, perangkat ini tetap masuk; sesi
        // perangkat lain tidak berlaku lagi.
        if (filled($data['password'] ?? null)) {
            GantiKataSandi::perbaruiHashSesi();
        }

        // Kata sandi tidak perlu tertinggal pada state formulir
        $this->form->fill(array_merge($data, [
            // Tidak ikut tersimpan dari formulir; ditampilkan sesuai akun.
            'name'                  => $pengguna->name,
            'email'                 => $pengguna->email,
            'nip'                   => $pengguna->nip,
            'current_password'      => null,
            'password'              => null,
            'password_confirmation' => null,
            // Kanvas dikembalikan ke keadaan "tidak ada goresan baru", supaya
            // penyimpanan berikutnya tidak menulis ulang gambar yang sama.
            'tanda_tangan'          => null,
        ]));

        $this->sedangUbahSandi = false;

        $this->rememberDataHash();

        Notification::make()
            ->title('Pengaturan tersimpan')
            ->success()
            ->send();
    }
}
