<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\KanvasTandaTangan;
use App\Models\User;
use App\Support\NomorWhatsApp;
use App\Support\Onboarding;
use App\Support\TandaTangan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Pelengkapan data awal akun pada pemakaian pertama.
 *
 * Halaman tersendiri, bukan menumpang Pengaturan, dengan alasan yang sama
 * dengan halaman penggantian kata sandi: di Pengaturan seluruh kolom bersifat
 * opsional dan berdampingan dengan belasan isian lain, sehingga pengguna dapat
 * menekan Simpan tanpa benar-benar melengkapi apa pun. Di sini hanya ada data
 * yang memang wajib, dan tidak ada jalan keluar selain mengisinya.
 *
 * Disajikan sebagai wizard bertahap dalam satu halaman — aktivasi akun yang
 * jelas urutannya — memakai komponen Wizard bawaan Filament agar stepper,
 * penggantian nomor menjadi centang, penghubung antartahap, serta penjagaan
 * "tak dapat maju sebelum tahap ini benar" mengikuti design system panel apa
 * adanya, bukan gaya baru yang dibuat sendiri.
 *
 * Tahapnya menyesuaikan peran, mengikuti aturan yang sudah dipusatkan di
 * {@see Onboarding}: setiap peran hanya melewati tahap yang memang wajib
 * baginya. Anggota Tim dan Kasubbag Umum tidak menandatangani dokumen
 * tergambar, sehingga tahap Tanda Tangan tidak muncul bagi keduanya — bukan
 * ditampilkan lalu dilewati. Nama lengkap dan NIP tidak diminta ulang; keduanya
 * sudah menjadi data pakem akun dan hanya ditampilkan agar pengguna tahu atas
 * nama siapa tanda tangannya kelak dibubuhkan.
 *
 * Urutan gerbang pemakaian pertama: kata sandi awal diganti lebih dulu (oleh
 * {@see \App\Http\Middleware\PaksaGantiKataSandi}), kemudian data ini
 * dilengkapi (oleh {@see \App\Http\Middleware\PaksaLengkapiAkun}).
 */
class LengkapiAkun extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.lengkapi-akun';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $title = 'Lengkapi Akun';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string,mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        // Pengguna yang datanya sudah lengkap tidak punya urusan di sini.
        if (Onboarding::lengkap(auth()->user())) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        $this->form->fill([
            'no_hp' => auth()->user()->no_hp,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make($this->langkah(auth()->user()))
                    // Tombol tahap terakhir. Aksi tersendiri, bukan submit polos,
                    // supaya konfirmasi "Apakah data sudah sesuai?" bisa disematkan
                    // sebelum data benar-benar disimpan.
                    ->submitAction($this->selesaiAction())
                    ->nextAction(fn (Action $action) => $action->label('Lanjut'))
                    ->previousAction(fn (Action $action) => $action->label('Sebelumnya')),
            ])
            ->statePath('data');
    }

    /**
     * Tahapan wizard sesuai peran pengguna.
     *
     * Dirakit dari aturan {@see Onboarding} yang sama dengan yang dipakai
     * gerbang middleware, sehingga tahap yang muncul di sini persis data yang
     * benar-benar ditahan gerbang itu — tidak ada tahap yang ditampilkan tanpa
     * guna, dan tidak ada yang wajib namun terlewat.
     *
     * @return array<int,Step>
     */
    protected function langkah(User $pengguna): array
    {
        $langkah = [$this->tahapDataDiri($pengguna)];

        if (Onboarding::butuhNomorWa($pengguna)) {
            $langkah[] = $this->tahapWhatsApp();
        }

        if (Onboarding::butuhTandaTangan($pengguna)) {
            $langkah[] = $this->tahapTandaTangan();
        }

        $langkah[] = $this->tahapKonfirmasi($pengguna);

        return $langkah;
    }

    protected function tahapDataDiri(User $pengguna): Step
    {
        // Akun Tim mewakili tim kerja, bukan seorang pegawai, sehingga tidak
        // ber-NIP. Baris NIP karena itu tidak ditampilkan baginya — bukan
        // ditampilkan berisi tanda hubung, yang justru menyiratkan data yang
        // belum lengkap. Peran lain tetap menampilkannya.
        $tampilkanNip = $pengguna->role !== 'tim';

        return Step::make('Data Diri')
            ->columns(2)
            ->schema([
                Placeholder::make('catatan_identitas')
                    ->hiddenLabel()
                    // Keterangan mengikuti apa yang benar-benar tampil: menyebut
                    // "NIP" bagi Tim yang barisnya tidak ada akan menunjuk kolom
                    // yang tidak pernah muncul.
                    ->content(new HtmlString(
                        '<p class="text-sm text-gray-500 dark:text-gray-400">'
                        . ($tampilkanNip
                            ? 'Nama lengkap dan NIP berikut sudah terdaftar pada akun Anda dan dipakai '
                                . 'pada dokumen serta proses SIMPBI. Keduanya tidak diubah di sini.'
                            : 'Nama lengkap berikut sudah terdaftar pada akun Anda dan dipakai pada '
                                . 'dokumen serta proses SIMPBI. Data ini tidak diubah di sini.')
                        . '</p>'
                    ))
                    ->columnSpanFull(),

                // Ditampilkan sebagai Placeholder, bukan kolom isian yang
                // dinonaktifkan: keduanya memang tidak dapat diubah di sini, dan
                // Placeholder menampilkan nilainya apa adanya tanpa bergantung
                // pada keadaan formulir yang ikut berpindah tiap tahap.
                Placeholder::make('nama_terdaftar')
                    ->label('Nama Lengkap')
                    ->content($pengguna->name),

                Placeholder::make('nip_terdaftar')
                    ->label('NIP')
                    ->content($pengguna->nip ?: '—')
                    ->visible($tampilkanNip),
            ]);
    }

    protected function tahapWhatsApp(): Step
    {
        return Step::make('WhatsApp')
            ->schema([
                TextInput::make('no_hp')
                    ->label('Nomor WhatsApp')
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->placeholder('08xxxxxxxxxx')
                    ->helperText('Dipakai untuk menerima notifikasi alur kerja bila kanal WhatsApp diaktifkan.')
                    // Pesan validasi diindonesiakan di sini, bukan lewat berkas
                    // bahasa seluruh aplikasi: yang perlu diperbaiki hanya wizard
                    // ini, dan pesan bawaan Laravel untuk kolom lain tidak ikut
                    // tersentuh. Memakai mekanisme validationMessages bawaan
                    // Filament, bukan aturan validasi baru.
                    ->validationMessages([
                        'required' => 'Nomor WhatsApp wajib diisi.',
                        'max'      => 'Nomor WhatsApp maksimal :max karakter.',
                    ]),
            ]);
    }

    protected function tahapTandaTangan(): Step
    {
        return Step::make('Tanda Tangan')
            ->schema([
                KanvasTandaTangan::make('tanda_tangan')
                    ->label('Tanda Tangan')
                    ->required()
                    ->helperText('Bubuhkan sekali di sini. Tanda tangan ini dibubuhkan otomatis pada dokumen yang '
                        . 'membutuhkannya — Anda tidak perlu menggambarnya lagi pada setiap tindakan.')
                    ->validationMessages([
                        'required' => 'Tanda tangan wajib dibubuhkan.',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function tahapKonfirmasi(User $pengguna): Step
    {
        return Step::make('Konfirmasi')
            ->columns(2)
            ->schema([
                Placeholder::make('ringkas_nama')
                    ->label('Nama Lengkap')
                    ->content($pengguna->name),

                // NIP tidak diringkas bagi Tim, sejalan dengan tahap Data Diri
                // yang juga tidak menampilkannya.
                Placeholder::make('ringkas_nip')
                    ->label('NIP')
                    ->content($pengguna->nip ?: '—')
                    ->visible($pengguna->role !== 'tim'),

                // Ringkasan dibaca dari state formulir, bukan dari basis data:
                // setiap perpindahan tahap adalah satu putaran Livewire, sehingga
                // nilai yang baru diketik sudah sampai di peladen ketika tahap
                // ini digambar ulang.
                Placeholder::make('ringkas_wa')
                    ->label('Nomor WhatsApp')
                    ->content(fn (Get $get): string => filled($get('no_hp')) ? $get('no_hp') : '—')
                    ->visible(Onboarding::butuhNomorWa($pengguna)),

                Placeholder::make('ringkas_ttd')
                    ->label('Tanda Tangan')
                    ->content(function (Get $get) use ($pengguna): HtmlString {
                        $uri = $get('tanda_tangan') ?: TandaTangan::dataUri($pengguna);

                        if (blank($uri)) {
                            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">Belum dibubuhkan</span>');
                        }

                        return new HtmlString(
                            '<div class="space-y-2">'
                            . '<span class="inline-flex items-center gap-1.5 text-sm font-medium text-success-600 dark:text-success-400">'
                            . '<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
                            . '<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />'
                            . '</svg>Tanda tangan siap disimpan</span>'
                            . '<div class="fi-simpbi-ttd-kotak fi-simpbi-ttd-tersimpan" style="max-width:20rem">'
                            . '<img src="' . e($uri) . '" alt="Pratinjau tanda tangan">'
                            . '</div>'
                            . '</div>'
                        );
                    })
                    ->visible(Onboarding::butuhTandaTangan($pengguna))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tombol penyelesai onboarding.
     *
     * Meminta konfirmasi lebih dulu, sebab sesudahnya nomor dan tanda tangan
     * langsung menjadi data pakem yang dipakai dokumen — bukan langkah yang
     * enak dibatalkan. Gayanya mengikuti aksi utama panel (biru merek), bukan
     * gaya tersendiri.
     */
    public function selesaiAction(): Action
    {
        return Action::make('selesai')
            ->label('Selesai')
            ->icon('heroicon-m-check')
            ->color('primary')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-clipboard-document-check')
            ->modalHeading('Apakah data sudah sesuai?')
            ->modalDescription('Pastikan informasi yang dimasukkan sudah benar. Data ini akan digunakan dalam proses dan dokumen SIMPBI.')
            ->modalSubmitActionLabel('Ya, Simpan')
            ->modalCancelActionLabel('Kembali')
            ->action(fn () => $this->simpan());
    }

    public function simpan(): void
    {
        $data     = $this->form->getState();
        $pengguna = auth()->user();

        DB::transaction(function () use ($data, $pengguna) {
            if (Onboarding::butuhNomorWa($pengguna)) {
                // Disimpan sudah dalam bentuk seragam, sebab nilai inilah yang
                // dibaca langsung sebagai nomor tujuan oleh job pengiriman.
                $pengguna->forceFill([
                    'no_hp' => NomorWhatsApp::normalkan($data['no_hp'] ?? null) ?: ($data['no_hp'] ?? null),
                ])->save();
            }

            if (Onboarding::butuhTandaTangan($pengguna) && filled($data['tanda_tangan'] ?? null)) {
                TandaTangan::simpan($pengguna, $data['tanda_tangan']);
            }
        });

        Notification::make()
            ->title('Akun Anda sudah lengkap')
            ->body('Selamat datang di SIMPBI.')
            ->success()
            ->send();

        $this->redirect(Dashboard::getUrl());
    }
}
