<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

/**
 * Penggantian kata sandi yang diwajibkan pada kesempatan masuk pertama.
 *
 * Halaman tersendiri, bukan menumpang halaman Pengaturan, karena di sana
 * kolom kata sandi bersifat opsional dan berdampingan dengan belasan isian
 * lain — pengguna yang ditahan di sana dapat menekan Simpan tanpa mengganti
 * apa pun dan merasa sudah selesai. Di sini hanya ada dua kolom dan keduanya
 * wajib, sehingga tidak ada jalan keluar selain benar-benar menggantinya.
 *
 * Tidak muncul di menu samping: halaman ini bukan tempat yang dituju, tetapi
 * tempat yang menahan.
 */
class GantiKataSandi extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.ganti-kata-sandi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $title = 'Ganti Kata Sandi';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string,mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kata Sandi Baru')
                    ->description(
                        'Akun Anda masih memakai kata sandi awal yang diberikan Administrator. '
                        . 'Gantilah lebih dulu sebelum memakai sistem.'
                    )
                    ->icon('heroicon-m-key')
                    ->schema([
                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->confirmed(),

                        TextInput::make('password_confirmation')
                            ->label('Ulangi Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->autocomplete('new-password'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('simpan')
                ->label('Simpan dan Lanjutkan')
                ->submit('simpan'),
        ];
    }

    public function simpan(): void
    {
        $data = $this->form->getState();
        $pengguna = auth()->user();

        $pengguna->forceFill([
            // Kolom password memakai cast "hashed", sehingga tidak dihash ganda
            'password'          => $data['password'],
            'harus_ganti_sandi' => false,
        ])->save();

        Notification::make()
            ->title('Kata sandi tersimpan')
            ->body('Selamat datang di SIMPBI.')
            ->success()
            ->send();

        $this->redirect(Dashboard::getUrl());
    }
}
