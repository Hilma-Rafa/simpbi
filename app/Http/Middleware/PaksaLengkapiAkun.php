<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LengkapiAkun;
use App\Support\Onboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan pengguna yang belum melengkapi data awal akunnya.
 *
 * Gerbang kedua pemakaian pertama, sesudah penggantian kata sandi. Selama nomor
 * WhatsApp atau tanda tangan yang memang diwajibkan peran pengguna belum
 * terisi, seluruh permintaan halaman biasa dialihkan ke halaman pelengkapan
 * akun. Aturan "apa yang wajib" dipusatkan di {@see Onboarding}.
 *
 * Beberapa keadaan sengaja dibiarkan lewat supaya tidak mengambil alih gerbang
 * lain:
 *   - Akun yang masih wajib mengganti kata sandi ditangani lebih dulu oleh
 *     {@see PaksaGantiKataSandi}; menahannya di sini akan bersaing dengan
 *     gerbang itu dan mengaburkan urutannya.
 *   - Akun nonaktif dibiarkan lewat agar penolakan 403 dari panel tetap yang
 *     menjawabnya, bukan dialihkan ke halaman pelengkapan yang tak akan pernah
 *     dapat ia selesaikan.
 *
 * Seperti gerbang kata sandi, hanya permintaan halaman biasa yang ditahan;
 * lalu lintas Livewire dibiarkan lewat sebab halaman pelengkapan itu sendiri
 * bekerja lewat Livewire.
 */
class PaksaLengkapiAkun
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if (! $pengguna) {
            return $next($request);
        }

        // Diserahkan kepada gerbang kata sandi dan panel masing-masing.
        if ($pengguna->harus_ganti_sandi || ! $pengguna->status_aktif) {
            return $next($request);
        }

        if (Onboarding::lengkap($pengguna)) {
            return $next($request);
        }

        if ($this->dikecualikan($request)) {
            return $next($request);
        }

        return redirect()->to(LengkapiAkun::getUrl());
    }

    /**
     * Permintaan yang tetap boleh lewat: halaman pelengkapan itu sendiri,
     * seluruh lalu lintas Livewire, dan keluar dari sistem.
     */
    protected function dikecualikan(Request $request): bool
    {
        return $request->routeIs('filament.admin.pages.lengkapi-akun')
            || $request->is('livewire/*')
            || $request->is('livewire-*/*')
            || $request->routeIs('filament.admin.auth.logout')
            || $request->isMethod('POST') && $request->routeIs('*logout*');
    }
}
