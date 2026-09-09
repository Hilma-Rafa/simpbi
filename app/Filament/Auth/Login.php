<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Halaman login dua sisi sesuai Instruksi §28.
 *
 * Menggunakan seluruh mekanisme autentikasi native Filament (validasi,
 * rate limiting, remember me, revealable password). Hanya presentasi yang
 * dikustomisasi: panel branding navy di kiri, form di kanan.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login-content';

    protected static string $layout = 'filament.auth.two-panel';

    public function getLayout(): string
    {
        return static::$layout;
    }

    public function getHeading(): string|Htmlable
    {
        return 'Selamat Datang';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Masuk menggunakan akun yang telah diberikan Administrator.';
    }

    /**
     * Penamaan label mengikuti ejaan judul yang dipakai SIMPBI, menggantikan
     * terjemahan bawaan Filament yang berejaan kalimat. Hanya labelnya yang
     * diubah; aturan validasi, autofokus, dan pelengkapan otomatis tetap
     * berasal dari definisi bawaan.
     */
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Alamat Email')
            ->placeholder('nama@bps.go.id');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Kata Sandi')
            ->placeholder('Masukkan kata sandi');
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->label('Tetap masuk di perangkat ini');
    }
}
