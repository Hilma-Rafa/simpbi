<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(\App\Filament\Auth\Login::class)
            // Identitas SIMPBI (Instruksi §61).
            ->brandName('SIMPBI')
            // Lambang gabungan: logo BPS berdampingan dengan nama sistem,
            // supaya identitas SIMPBI ikut terbaca dan tidak hanya logo lembaga.
            // Dikembalikan sebagai HtmlString, sebab Filament memasang nilai
            // string biasa sebagai alamat gambar, bukan sebagai potongan HTML.
            ->brandLogo(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                view('filament.brand')->render()
            ))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/logo-bps.png'))
            // Palet resmi sesuai Instruksi §25: Navy = identitas, Blue = aksi utama,
            // Orange = accent. Warna semantik mengikuti tabel design token.
            ->colors([
                'primary' => Color::hex('#1557A6'),
                'navy' => Color::hex('#0B2A5B'),
                'accent' => Color::hex('#F59E0B'),
                'success' => Color::hex('#16A34A'),
                'danger' => Color::hex('#DC2626'),
                'warning' => Color::hex('#D97706'),
                'info' => Color::hex('#2563EB'),
                'gray' => Color::Slate,
            ])
            // Typography (Instruksi §26): Inter, system-ui, sans-serif.
            ->font('Inter')
            // Huruf judul dan wordmark halaman muka ikut dimuat, supaya
            // tampilan sesudah masuk mengalir dari halaman sebelum masuk
            // dan bukan terasa seperti aplikasi yang berbeda.
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.fonts')->render(),
            )
            ->sidebarCollapsibleOnDesktop()
            // Kelompok menu (Instruksi §58).
            ->navigationGroups([
                'Dashboard',
                'Permintaan & Distribusi',
                'Persediaan',
                'Inventaris',
                'Monitoring',
                'Administrasi',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            // Pengaturan diletakkan di dalam menu profil, bukan menu samping,
            // agar konfigurasi tidak bercampur dengan menu operasional.
            // Lonceng notifikasi diletakkan tepat sebelum menu profil pada
            // bilah atas, memakai tabel `notifikasi` milik proyek.
            ->renderHook(
                \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render(
                    '@livewire(\'lonceng-notifikasi\')'
                ),
            )
            ->userMenuItems([
                'pengaturan' => \Filament\Navigation\MenuItem::make()
                    ->label('Pengaturan')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->url(fn (): string => \App\Filament\Pages\Pengaturan::getUrl()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
