<?php

namespace App\Providers;

use App\Services\WhatsApp\PengirimCatat;
use App\Services\WhatsApp\PengirimWhatsApp;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Pelaksana pengiriman WhatsApp dipilih dari berkas konfigurasi, bukan
         * ditulis mati, agar gerbangnya dapat diganti — misalnya dari gerbang
         * yang dipasang sendiri ke WhatsApp Cloud API resmi — tanpa menyentuh
         * alur notifikasi. Pelaksana baru cukup didaftarkan pada daftar ini.
         */
        $this->app->bind(PengirimWhatsApp::class, function () {
            return match (config('whatsapp.driver')) {
                default => new PengirimCatat(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Carbon\Carbon::setLocale('id');

        // Toast pemberitahuan muncul di sudut kanan bawah layar, agar tidak
        // menutupi bilah atas dan tombol aksi di kepala halaman.
        Notifications::alignment(Alignment::End);
        Notifications::verticalAlignment(VerticalAlignment::End);
    }
}
